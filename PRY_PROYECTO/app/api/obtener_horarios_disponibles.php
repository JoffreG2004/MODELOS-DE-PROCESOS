<?php
header('Content-Type: application/json');
require_once '../../conexion/db.php';

/**
 * Sistema de bloques de 3 horas
 * - Reservas normales: máximo 3 horas
 * - Zona completa: todo el día (10:00 - 22:00)
 * - Si reserva a las 11:00, ocupa hasta las 14:00
 * - A las 14:00 se puede reservar la siguiente
 */

try {
    $mesa_id = $_GET['mesa_id'] ?? null;
    $fecha = $_GET['fecha'] ?? date('Y-m-d');
    $es_zona_completa = $_GET['zona_completa'] ?? false;
    
    if (!$mesa_id) {
        throw new Exception('Mesa no especificada');
    }
    
    // Validar formato de fecha
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        throw new Exception('Formato de fecha inválido');
    }
    
    // Obtener configuracion de horarios del restaurante
    $stmtCfg = $pdo->query("SELECT clave, valor FROM configuracion_restaurante");
    $configs = [];
    while ($row = $stmtCfg->fetch(PDO::FETCH_ASSOC)) {
        $configs[$row['clave']] = $row['valor'];
    }

    if (isset($configs['reservas_activas']) && $configs['reservas_activas'] !== '1') {
        echo json_encode([
            'success' => false,
            'message' => 'Reservas deshabilitadas'
        ]);
        exit;
    }

    $fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$fechaObj) {
        throw new Exception('Formato de fecha inválido');
    }

    // Validar dias cerrados
    $dias_cerrados = $configs['dias_cerrados'] ?? '';
    if (!empty($dias_cerrados)) {
        $diaSemana = (int)$fechaObj->format('w'); // 0=Domingo
        $diasCerradosArray = array_map('trim', explode(',', $dias_cerrados));
        if (in_array((string)$diaSemana, $diasCerradosArray, true)) {
            echo json_encode([
                'success' => true,
                'fecha' => $fecha,
                'mesa' => null,
                'bloques' => [],
                'configuracion' => [
                    'duracion_bloque_horas' => 3,
                    'hora_apertura' => null,
                    'hora_cierre' => null,
                    'es_zona_completa' => (bool)$es_zona_completa,
                    'cerrado' => true
                ],
                'resumen' => [
                    'total_bloques' => 0,
                    'bloques_disponibles' => 0
                ],
                'message' => 'Restaurante cerrado en esta fecha'
            ]);
            exit;
        }
    }

    // Definir horarios segun dia
    $dia_semana = (int)$fechaObj->format('N'); // 1=Lunes, 7=Domingo
    if ($dia_semana >= 1 && $dia_semana <= 5) {
        $hora_apertura = $configs['horario_lunes_viernes_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
        $hora_cierre = $configs['horario_lunes_viernes_fin'] ?? $configs['hora_cierre'] ?? '20:00';
    } elseif ($dia_semana == 6) {
        $hora_apertura = $configs['horario_sabado_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
        $hora_cierre = $configs['horario_sabado_fin'] ?? $configs['hora_cierre'] ?? '20:00';
    } else {
        $hora_apertura = $configs['horario_domingo_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
        $hora_cierre = $configs['horario_domingo_fin'] ?? $configs['hora_cierre'] ?? '20:00';
    }

    $hora_apertura = substr((string)$hora_apertura, 0, 5);
    $hora_cierre = substr((string)$hora_cierre, 0, 5);

    $bloques_duracion = 3; // 3 horas por bloque
    
    // Obtener todas las reservas confirmadas y pendientes para esta mesa en esta fecha
    $query = "
        SELECT 
            hora_reserva,
            duracion_horas,
            estado
        FROM reservas
        WHERE mesa_id = :mesa_id
        AND DATE(fecha_reserva) = :fecha
        AND estado IN ('confirmada', 'pendiente', 'preparando', 'en_curso')
        ORDER BY hora_reserva ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->bindValue(':mesa_id', $mesa_id, PDO::PARAM_INT);
    $stmt->bindValue(':fecha', $fecha, PDO::PARAM_STR);
    $stmt->execute();
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener zona de la mesa
    $stmt_mesa = $pdo->prepare("SELECT * FROM mesas WHERE id = ?");
    $stmt_mesa->execute([$mesa_id]);
    $mesa = $stmt_mesa->fetch(PDO::FETCH_ASSOC);
    if (!$mesa) {
        throw new Exception('Mesa no encontrada');
    }

    // Buscar reservas de zona que afecten esta mesa
    $zona_bloqueo_inicio = null;
    $stmtZonas = $pdo->prepare("
        SELECT hora_reserva, zonas
        FROM reservas_zonas
        WHERE fecha_reserva = ?
          AND estado IN ('pendiente', 'confirmada')
    ");
    $stmtZonas->execute([$fecha]);
    while ($row = $stmtZonas->fetch(PDO::FETCH_ASSOC)) {
        $zonasRow = json_decode($row['zonas'] ?? '[]', true);
        if (!is_array($zonasRow) || !in_array($mesa['ubicacion'], $zonasRow, true)) {
            continue;
        }
        $horaZona = substr((string)$row['hora_reserva'], 0, 5);
        if ($horaZona !== '') {
            $zona_bloqueo_inicio = $zona_bloqueo_inicio === null
                ? $horaZona
                : (strtotime($horaZona) < strtotime($zona_bloqueo_inicio) ? $horaZona : $zona_bloqueo_inicio);
        }
    }
    
    // Generar bloques horarios disponibles
    $bloques_disponibles = [];
    
    if ($es_zona_completa) {
        // Zona completa: solo un bloque de todo el día
        $bloques_disponibles[] = [
            'hora_inicio' => $hora_apertura,
            'hora_fin' => $hora_cierre,
            'duracion_horas' => 12,
            'disponible' => true,
            'descripcion' => 'Zona Completa (Todo el día)'
        ];
    } else {
        // Reservas normales: bloques de 3 horas
        $toMinutes = function($horaStr) {
            [$h, $m] = array_map('intval', explode(':', $horaStr));
            return $h * 60 + $m;
        };
        $apertura_min = $toMinutes($hora_apertura);
        $cierre_min = $toMinutes($hora_cierre);

        for ($inicio_min = $apertura_min; $inicio_min < $cierre_min; $inicio_min += ($bloques_duracion * 60)) {
            $fin_min = min($inicio_min + ($bloques_duracion * 60), $cierre_min);
            $hora_inicio = sprintf('%02d:%02d', intdiv($inicio_min, 60), $inicio_min % 60);
            $hora_fin = sprintf('%02d:%02d', intdiv($fin_min, 60), $fin_min % 60);
            
            // Convertir a minutos para comparación
            $inicio_minutos = $inicio_min;
            $fin_minutos = $fin_min;
            
            // Verificar si este bloque está disponible
            $disponible = true;
            $razon_no_disponible = null;
            
            // Bloqueo por reserva de zona: no permitir bloques que inicien menos de 3h antes del inicio de la zona
            if ($zona_bloqueo_inicio !== null) {
                $zona_inicio_min = $toMinutes($zona_bloqueo_inicio);
                $diff = $zona_inicio_min - $inicio_minutos;
                if ($diff < ($bloques_duracion * 60)) {
                    $disponible = false;
                    $razon_no_disponible = "Zona reservada desde {$zona_bloqueo_inicio}";
                }
            }

            foreach ($reservas as $reserva) {
                $hora_res = strtotime($reserva['hora_reserva']);
                $minutos_res = ($hora_res / 60) % 1440; // minutos desde medianoche
                $duracion_res = ($reserva['duracion_horas'] ?? 3) * 60; // convertir a minutos
                
                // Si hay conflicto de horarios
                if ($disponible && $minutos_res < $fin_minutos && ($minutos_res + $duracion_res) > $inicio_minutos) {
                    $disponible = false;
                    $razon_no_disponible = "Ocupado de " . $reserva['hora_reserva'] . " a " . 
                        date('H:i', strtotime($reserva['hora_reserva']) + $duracion_res * 60);
                    break;
                }
            }
            
            $bloques_disponibles[] = [
                'hora_inicio' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'duracion_horas' => $bloques_duracion,
                'disponible' => $disponible,
                'razon_no_disponible' => $razon_no_disponible,
                'proxima_disponibilidad' => !$disponible ? $hora_fin : null
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'mesa' => [
            'id' => $mesa['id'],
            'numero' => $mesa['numero_mesa'],
            'ubicacion' => $mesa['ubicacion'],
            'precio_reserva' => $mesa['precio_reserva']
        ],
        'bloques' => $bloques_disponibles,
        'configuracion' => [
            'duracion_bloque_horas' => $bloques_duracion,
            'hora_apertura' => $hora_apertura,
            'hora_cierre' => $hora_cierre,
            'es_zona_completa' => (bool)$es_zona_completa
        ],
        'resumen' => [
            'total_bloques' => count($bloques_disponibles),
            'bloques_disponibles' => count(array_filter($bloques_disponibles, fn($b) => $b['disponible']))
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener horarios',
        'error' => $e->getMessage()
    ]);
}
?>
