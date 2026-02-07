<?php
/**
 * Marcar Cliente como Llegado
 * Actualiza el estado cuando el cliente llega físicamente
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación de admin
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

require_once '../conexion/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['reserva_id'])) {
        throw new Exception('ID de reserva requerido');
    }
    
    $reserva_id = intval($data['reserva_id']);
    $tipo_reserva = $data['tipo_reserva'] ?? 'normal';
    $tabla = $tipo_reserva === 'zona' ? 'reservas_zonas' : 'reservas';
    
    // Actualizar estado de llegada
    $stmt = $pdo->prepare("
        UPDATE {$tabla} 
        SET cliente_llego = 1,
            hora_llegada = NOW()
        WHERE id = ?
        AND estado = 'en_curso'
    ");
    
    $resultado = $stmt->execute([$reserva_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('No se pudo actualizar. Verifique que la reserva esté EN CURSO.');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cliente marcado como llegado',
        'data' => [
            'reserva_id' => $reserva_id,
            'hora_llegada' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
