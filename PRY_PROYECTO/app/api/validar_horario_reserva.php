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
    if (isset($configs['reservas_activas']) && $configs['reservas_activas'] !== '1') {
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => 'Las reservas están temporalmente deshabilitadas'
        ]);
        exit;
    }
    
    // Obtener hora de apertura y cierre
    $hora_apertura = $configs['hora_apertura'] ?? '11:00';
    $hora_cierre = $configs['hora_cierre'] ?? '23:00';
    $dias_cerrados = $configs['dias_cerrados'] ?? '';
    
    // Validar día de la semana no esté cerrado
    if (!empty($dias_cerrados)) {
        $fechaObj = new DateTime($fecha);
        $diaSemana = $fechaObj->format('w'); // 0=Domingo, 1=Lunes, ..., 6=Sábado
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
        echo json_encode([
            'success' => false,
            'valido' => false,
            'message' => "El horario de reserva debe estar entre $hora_apertura y $hora_cierre",
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
