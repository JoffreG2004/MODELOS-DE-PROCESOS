<?php
header('Content-Type: application/json; charset=UTF-8');

require_once '../../conexion/db.php';

try {
    // Ejecutar procedimiento almacenado
    $stmt = $pdo->query("CALL activar_reservas_programadas()");
    
    // Contar cuántas reservas fueron actualizadas
    $stmt2 = $pdo->query("SELECT 
        COUNT(*) as total_en_curso 
        FROM reservas 
        WHERE estado = 'en_curso' 
        AND CONCAT(fecha_reserva, ' ', hora_reserva) <= NOW()");
    
    $resultado = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reservas actualizadas correctamente',
        'reservas_activas' => $resultado['total_en_curso']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar reservas: ' . $e->getMessage()
    ]);
}
?>
