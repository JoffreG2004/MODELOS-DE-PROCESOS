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
    
    $cliente_id = $data['cliente_id'] ?? null;
    $mesa_id = $data['mesa_id'] ?? null;
    $fecha_reserva = $data['fecha_reserva'] ?? null;
    $hora_reserva = $data['hora_reserva'] ?? null;
    $numero_personas = $data['numero_personas'] ?? null;
    $estado = $data['estado'] ?? 'pendiente';
    
    if (empty($cliente_id) || empty($mesa_id) || empty($fecha_reserva) || empty($hora_reserva) || empty($numero_personas)) {
        throw new Exception('Todos los campos obligatorios son requeridos');
    }
    
    // Verificar que el cliente existe
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    if (!$stmt->fetch()) {
        throw new Exception('El cliente no existe');
    }
    
    // Verificar que la mesa existe
    $stmt = $pdo->prepare("SELECT id FROM mesas WHERE id = ?");
    $stmt->execute([$mesa_id]);
    if (!$stmt->fetch()) {
        throw new Exception('La mesa no existe');
    }
    
    // Verificar conflictos de reserva
    $stmt = $pdo->prepare("
        SELECT id FROM reservas 
        WHERE mesa_id = ? 
        AND fecha_reserva = ? 
        AND hora_reserva = ?
        AND estado IN ('pendiente', 'confirmada', 'en_curso')
    ");
    $stmt->execute([$mesa_id, $fecha_reserva, $hora_reserva]);
    if ($stmt->fetch()) {
        throw new Exception('Ya existe una reserva para esta mesa en el mismo horario');
    }
    
    $query = "INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, estado) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$cliente_id, $mesa_id, $fecha_reserva, $hora_reserva, $numero_personas, $estado]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reserva creada exitosamente',
        'id' => $pdo->lastInsertId()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
