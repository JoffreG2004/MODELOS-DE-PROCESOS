<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../conexion/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? null;
    
    if (empty($id)) {
        throw new Exception('ID de reserva requerido');
    }
    
    $query = "DELETE FROM reservas WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reserva eliminada exitosamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
