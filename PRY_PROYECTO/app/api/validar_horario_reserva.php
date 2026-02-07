<?php
header('Content-Type: application/json; charset=UTF-8');

require_once '../../conexion/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $fecha = $input['fecha'] ?? date('Y-m-d');
    $hora = $input['hora'] ?? date('H:i');
    $tz = new DateTimeZone(date_default_timezone_get());
    
    // Obtener configuraciones
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion_restaurante");
    $configs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $configs[$row['clave']] = $row['valor'];
    }
    
    // Verificar si las reservas están activas
    if (isset($configs['reservas_activas']) && $configs['reservas_activas'] !== '1') {
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => 'Las reservas están temporalmente deshabilitadas'
        ]);
        exit;
    }
    
    // Validación de fecha
    $fecha_obj = DateTime::createFromFormat('!Y-m-d', $fecha, $tz);
    if (!$fecha_obj) {
        echo json_encode(['success' => false, 'valido' => false, 'message' => 'Formato de fecha inválido']);
        exit;
    }

    // Validar que no sea fecha pasada
    $hoy = new DateTime('today', $tz);
    if ($fecha_obj < $hoy) {
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => 'No se pueden hacer reservas con fechas pasadas',
            'detalle' => [
                'fecha_seleccionada' => $fecha_obj->format('Y-m-d'),
                'fecha_actual_sistema' => $hoy->format('Y-m-d'),
                'zona_horaria' => $tz->getName()
            ]
        ]);
        exit;
    }
    
    // Validar que no sea más de 180 días (aprox. 6 meses) adelante
    $max_adelanto = new DateTime('today', $tz);
    $max_adelanto->modify('+180 days');
    // Si la fecha es ESTRICTAMENTE mayor que la máxima permitida, rechazar
    // +180 días debe ser permitido, +181 debe ser rechazado
    if ($fecha_obj > $max_adelanto) {
        echo json_encode(['success' => false, 'valido' => false, 'message' => 'No se pueden hacer reservas con más de 6 meses de anticipación']);
        exit;
    }
    
    // Obtener hora de apertura y cierre según día de la semana
    // Prioridad: horario por día -> horario global -> fallback
    $dia_semana = (int)$fecha_obj->format('N'); // 1=Lunes, 7=Domingo
    if ($dia_semana >= 1 && $dia_semana <= 5) { // Lunes-Viernes
        $hora_apertura = $configs['horario_lunes_viernes_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
        $hora_cierre = $configs['horario_lunes_viernes_fin'] ?? $configs['hora_cierre'] ?? '20:00';
    } elseif ($dia_semana == 6) { // Sábado
        $hora_apertura = $configs['horario_sabado_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
        $hora_cierre = $configs['horario_sabado_fin'] ?? $configs['hora_cierre'] ?? '20:00';
    } elseif ($dia_semana == 7) { // Domingo
        $hora_apertura = $configs['horario_domingo_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
        $hora_cierre = $configs['horario_domingo_fin'] ?? $configs['hora_cierre'] ?? '20:00';
    } else {
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => 'Día de la semana inválido'
        ]);
        exit;
    }

    // Normalizar a HH:MM por si llegan con segundos
    $hora_apertura = substr($hora_apertura, 0, 5);
    $hora_cierre = substr($hora_cierre, 0, 5);
    
    $dias_cerrados = $configs['dias_cerrados'] ?? '';
    
    // Validar día de la semana no esté cerrado
    if (!empty($dias_cerrados)) {
        $diaSemana = $fecha_obj->format('w'); // 0=Domingo, 1=Lunes, ..., 6=Sábado
        $diasCerradosArray = array_map('trim', explode(',', $dias_cerrados));
        
        if (in_array($diaSemana, $diasCerradosArray)) {
            $nombresEspañol = [
                0 => 'Domingo', 1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
                4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado'
            ];
            echo json_encode([
                'success' => false,
                'valido' => false,
                'message' => "No se pueden hacer reservas los días {$nombresEspañol[$diaSemana]}. El restaurante está cerrado."
            ]);
            exit;
        }
    }
    
    // Validar que la hora esté dentro del rango de apertura-cierre
    $hora_valida = ($hora >= $hora_apertura && $hora <= $hora_cierre);
    
    if (!$hora_valida) {
        $message = "El horario de reserva debe estar entre $hora_apertura y $hora_cierre";
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => $message,
            'horario_disponible' => [
                'inicio' => $hora_apertura,
                'fin' => $hora_cierre
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'valido' => true,
            'message' => 'Horario válido',
            'horario_disponible' => [
                'inicio' => $hora_apertura,
                'fin' => $hora_cierre
            ]
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al validar horario: ' . $e->getMessage()
    ]);
}
?>
