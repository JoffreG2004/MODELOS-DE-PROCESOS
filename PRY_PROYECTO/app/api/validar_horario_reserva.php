<?php
header('Content-Type: application/json; charset=UTF-8');

require_once '../../conexion/db.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $fecha = $input['fecha'] ?? date('Y-m-d');
    $hora = $input['hora'] ?? date('H:i');
    
    // Obtener configuraciones
    $stmt = $pdo->query("SELECT clave, valor FROM configuracion_restaurante");
    $configs = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $configs[$row['clave']] = $row['valor'];
    }
    
    // Verificar si las reservas están activas
    if ($configs['reservas_activas'] !== '1') {
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => 'Las reservas están temporalmente deshabilitadas'
        ]);
        exit;
    }
    
    // Verificar días cerrados (formato: DD-MM,DD-MM)
    $dias_cerrados = explode(',', $configs['dias_cerrado'] ?? '');
    $fecha_formato = date('d-m', strtotime($fecha));
    
    if (in_array($fecha_formato, $dias_cerrados)) {
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => 'El restaurante está cerrado en esta fecha'
        ]);
        exit;
    }
    
    // Determinar día de la semana (1=Lunes, 7=Domingo)
    $dia_semana = date('N', strtotime($fecha));
    
    // Obtener horarios según el día
    if ($dia_semana >= 1 && $dia_semana <= 5) {
        // Lunes a viernes
        $hora_inicio = $configs['horario_lunes_viernes_inicio'];
        $hora_fin = $configs['horario_lunes_viernes_fin'];
        $tipo_dia = 'Lunes a Viernes';
    } elseif ($dia_semana == 6) {
        // Sábado
        $hora_inicio = $configs['horario_sabado_inicio'];
        $hora_fin = $configs['horario_sabado_fin'];
        $tipo_dia = 'Sábado';
    } else {
        // Domingo
        $hora_inicio = $configs['horario_domingo_inicio'];
        $hora_fin = $configs['horario_domingo_fin'];
        $tipo_dia = 'Domingo';
    }
    
    // Validar que la hora esté dentro del rango
    $hora_valida = ($hora >= $hora_inicio && $hora <= $hora_fin);
    
    if (!$hora_valida) {
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => "Hora no válida. $tipo_dia el restaurante atiende de $hora_inicio a $hora_fin",
            'horario_disponible' => [
                'inicio' => $hora_inicio,
                'fin' => $hora_fin,
                'tipo_dia' => $tipo_dia
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'valido' => true,
            'message' => 'Horario válido',
            'horario_disponible' => [
                'inicio' => $hora_inicio,
                'fin' => $hora_fin,
                'tipo_dia' => $tipo_dia
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
