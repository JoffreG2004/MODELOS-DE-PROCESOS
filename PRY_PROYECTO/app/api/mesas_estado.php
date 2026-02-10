<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

require_once '../../conexion/db.php';

try {
    // Aceptar fecha y hora para verificar disponibilidad en bloque de 3 horas
    $fecha_param = $_GET['fecha'] ?? '';
    $fecha_consulta = (!empty($fecha_param) && trim($fecha_param) !== '') ? trim($fecha_param) : date('Y-m-d');
    $hora_consulta = isset($_GET['hora']) && trim($_GET['hora']) !== '' ? trim($_GET['hora']) : null;
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_consulta)) {
        throw new Exception('Formato de fecha inválido. Use YYYY-MM-DD');
    }

    $tz = new DateTimeZone('America/Guayaquil');
    $fechaObj = DateTime::createFromFormat('!Y-m-d', $fecha_consulta, $tz);
    if (!$fechaObj) {
        throw new Exception('Formato de fecha inválido. Use YYYY-MM-DD');
    }
    $hoy = new DateTime('today', $tz);
    $maxAdelanto = new DateTime('today', $tz);
    $maxAdelanto->modify('+14 days');
    if ($fechaObj < $hoy) {
        throw new Exception('No se pueden consultar mesas para fechas pasadas');
    }
    if ($fechaObj > $maxAdelanto) {
        throw new Exception('Solo se permiten reservas desde hoy hasta 14 días en adelante');
    }

    // Obtener reservas de zonas para la fecha
    $stmtZonas = $pdo->prepare("
        SELECT hora_reserva, zonas
        FROM reservas_zonas
        WHERE fecha_reserva = ?
          AND estado IN ('confirmada', 'preparando', 'en_curso')
    ");
    $stmtZonas->execute([$fecha_consulta]);
    $zonaInicioPorZona = [];
    while ($row = $stmtZonas->fetch(PDO::FETCH_ASSOC)) {
        $zonasRow = json_decode($row['zonas'] ?? '[]', true);
        if (!is_array($zonasRow)) {
            continue;
        }
        $horaZona = substr((string)$row['hora_reserva'], 0, 5);
        if ($horaZona === '') {
            continue;
        }
        foreach ($zonasRow as $zona) {
            if (!isset($zonaInicioPorZona[$zona]) || strtotime($horaZona) < strtotime($zonaInicioPorZona[$zona])) {
                $zonaInicioPorZona[$zona] = $horaZona;
            }
        }
    }

    if ($hora_consulta !== null) {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $hora_consulta)) {
            throw new Exception('Formato de hora inválido. Use HH:MM');
        }
        if (strlen($hora_consulta) === 5) {
            $hora_consulta .= ':00';
        }
    }

    if ($hora_consulta !== null) {
        // Cuando hay hora seleccionada:
        // - bloquea por reservas confirmadas/activas en el tramo de 3 horas
        // - trae la reserva en conflicto más cercana por mesa
        $query = "
            SELECT 
                m.id,
                m.numero_mesa,
                m.capacidad_minima,
                m.capacidad_maxima,
                m.ubicacion,
                m.estado,
                m.descripcion,
                m.precio_reserva,
                r.id as reserva_id,
                CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre,
                c.telefono,
                r.fecha_reserva,
                r.hora_reserva,
                r.numero_personas,
                r.estado as reserva_estado,
                COALESCE(r.duracion_horas, 3) as duracion_horas,
                r.diferencia_minutos
            FROM mesas m
            LEFT JOIN (
                SELECT r2.*
                FROM (
                    SELECT 
                        r.*,
                        ROUND(
                            ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_ref_1)))) / 60
                        ) as diferencia_minutos,
                        ROW_NUMBER() OVER (
                            PARTITION BY r.mesa_id 
                            ORDER BY ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_ref_2)))) ASC, r.hora_reserva ASC
                        ) as rn
                    FROM reservas r
                    WHERE DATE(r.fecha_reserva) = :fecha_consulta
                    AND r.estado IN ('confirmada', 'preparando', 'en_curso')
                    AND ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_ref_3)))) < 10800
                ) r2
                WHERE r2.rn = 1
            ) r ON m.id = r.mesa_id
            LEFT JOIN clientes c ON r.cliente_id = c.id
            ORDER BY m.numero_mesa ASC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':fecha_consulta', $fecha_consulta, PDO::PARAM_STR);
        $stmt->bindValue(':hora_ref_1', $hora_consulta, PDO::PARAM_STR);
        $stmt->bindValue(':hora_ref_2', $hora_consulta, PDO::PARAM_STR);
        $stmt->bindValue(':hora_ref_3', $hora_consulta, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        // Sin hora seleccionada: vista general del día
        $query = "
            SELECT 
                m.id,
                m.numero_mesa,
                m.capacidad_minima,
                m.capacidad_maxima,
                m.ubicacion,
                m.estado,
                m.descripcion,
                m.precio_reserva,
                r.id as reserva_id,
                CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre,
                c.telefono,
                r.fecha_reserva,
                r.hora_reserva,
                r.numero_personas,
                r.estado as reserva_estado,
                COALESCE(r.duracion_horas, 3) as duracion_horas,
                NULL as diferencia_minutos
            FROM mesas m
            LEFT JOIN (
                SELECT r.*, 
                       ROW_NUMBER() OVER (PARTITION BY r.mesa_id ORDER BY r.hora_reserva ASC) as rn
                FROM reservas r
                WHERE DATE(r.fecha_reserva) = :fecha_consulta
                AND r.estado IN ('confirmada', 'preparando', 'en_curso')
            ) r ON m.id = r.mesa_id AND r.rn = 1
            LEFT JOIN clientes c ON r.cliente_id = c.id
            ORDER BY m.numero_mesa ASC
        ";
        $stmt = $pdo->prepare($query);
        $stmt->bindValue(':fecha_consulta', $fecha_consulta, PDO::PARAM_STR);
        $stmt->execute();
    }

    $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear datos para el frontend
    $mesas_formateadas = array_map(function($mesa) use ($hora_consulta, $zonaInicioPorZona) {
        $duracion_horas = max(1, (int)($mesa['duracion_horas'] ?? 3));
        $hora_inicio = $mesa['hora_reserva'] ? date('H:i', strtotime($mesa['hora_reserva'])) : null;
        $hora_fin = $mesa['hora_reserva'] ? date('H:i', strtotime($mesa['hora_reserva'] . " +{$duracion_horas} hours")) : null;
        $sugerencia_antes = $mesa['hora_reserva'] ? date('H:i', strtotime($mesa['hora_reserva'] . ' -3 hours')) : null;
        $sugerencia_despues = $mesa['hora_reserva'] ? date('H:i', strtotime($mesa['hora_reserva'] . ' +3 hours')) : null;
        $zona_inicio = $zonaInicioPorZona[$mesa['ubicacion']] ?? null;
        $bloqueada_por_zona = false;
        if ($zona_inicio) {
            if ($hora_consulta !== null) {
                $toMin = function($h) {
                    [$hh, $mm] = array_map('intval', explode(':', $h));
                    return $hh * 60 + $mm;
                };
                $hora_ref = strlen($hora_consulta) === 8 ? substr($hora_consulta, 0, 5) : $hora_consulta;
                $diff = $toMin($zona_inicio) - $toMin($hora_ref);
                if ($diff < 180) {
                    $bloqueada_por_zona = true;
                }
            } else {
                $bloqueada_por_zona = true;
            }
        }

        // Si hay hora seleccionada, la disponibilidad se calcula por bloque de 3 horas
        if ($mesa['estado'] === 'mantenimiento') {
            $estado_calculado = 'mantenimiento';
        } elseif ($hora_consulta !== null) {
            $estado_calculado = ($mesa['reserva_id'] || $bloqueada_por_zona) ? 'reservada' : 'disponible';
        } else {
            $estado_calculado = $bloqueada_por_zona ? 'reservada' : $mesa['estado'];
        }

        return [
            'id' => $mesa['id'],
            'numero' => $mesa['numero_mesa'],
            'capacidad' => (int)$mesa['capacidad_maxima'],
            'capacidad_minima' => (int)$mesa['capacidad_minima'],
            'capacidad_maxima' => (int)$mesa['capacidad_maxima'],
            'tipo' => $mesa['ubicacion'],
            'ubicacion' => $mesa['ubicacion'],
            'estado' => $estado_calculado,
            'estado_original' => $mesa['estado'],
            'descripcion' => $mesa['descripcion'],
            'precio_reserva' => (float)$mesa['precio_reserva'],
            'reserva' => $mesa['reserva_id'] ? [
                'id' => $mesa['reserva_id'],
                'cliente' => $mesa['cliente_nombre'],
                'telefono' => $mesa['telefono'],
                'fecha' => $mesa['fecha_reserva'],
                'hora' => $hora_inicio,
                'hora_fin' => $hora_fin,
                'personas' => (int)$mesa['numero_personas'],
                'estado' => $mesa['reserva_estado'],
                'duracion_horas' => $duracion_horas,
                'diferencia_minutos' => $mesa['diferencia_minutos'] !== null ? (int)$mesa['diferencia_minutos'] : null,
                'sugerencia_antes' => $sugerencia_antes,
                'sugerencia_despues' => $sugerencia_despues
            ] : null,
            'reserva_zona' => $zona_inicio ? [
                'hora_inicio' => $zona_inicio,
                'motivo' => 'Zona reservada'
            ] : null,
            'disponible_desde' => $estado_calculado === 'disponible' ? date('H:i') : null,
            'recordatorio_3h' => 'Puedes reservar en esta mesa solo fuera del tramo de 3 horas de una reserva confirmada o activa.',
            'color_estado' => in_array($estado_calculado, ['ocupada', 'reservada', 'mantenimiento'], true) ? '#dc3545' : '#28a745'
        ];
    }, $mesas);
    
    // Estadísticas rápidas
    $total_mesas = count($mesas_formateadas);
    $mesas_ocupadas = count(array_filter($mesas_formateadas, fn($m) => $m['estado'] !== 'disponible'));
    $mesas_disponibles = $total_mesas - $mesas_ocupadas;
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'fecha_consultada' => $fecha_consulta,
        'hora_consultada' => $hora_consulta ? substr($hora_consulta, 0, 5) : null,
        'bloque_horas' => 3,
        'recordatorio' => 'Recuerda: puedes reservar 3 horas antes o 3 horas después de una reserva confirmada o activa en la misma mesa.',
        'mesas' => $mesas_formateadas,
        'resumen' => [
            'total' => $total_mesas,
            'ocupadas' => $mesas_ocupadas,
            'disponibles' => $mesas_disponibles,
            'porcentaje_ocupacion' => $total_mesas > 0 ? round(($mesas_ocupadas / $total_mesas) * 100, 1) : 0
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}
?>
