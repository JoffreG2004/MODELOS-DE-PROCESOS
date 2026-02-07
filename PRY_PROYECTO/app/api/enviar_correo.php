<?php
/**
 * API para enviar correo de reserva confirmada vía n8n
 * Similar a enviar_whatsapp.php pero para correos electrónicos
 */

header('Content-Type: application/json; charset=UTF-8');

require_once '../../conexion/db.php';
require_once '../../controllers/EmailController.php';

try {
    // Obtener datos del request
    $data = json_decode(file_get_contents('php://input'), true);
    $reserva_id = $data['reserva_id'] ?? null;
    
    if (!$reserva_id) {
        throw new Exception('ID de reserva requerido');
    }
    
    // Obtener datos completos de la reserva
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.nombre, c.apellido, c.email as correo, c.telefono,
               m.numero_mesa, m.ubicacion as zona, m.precio_reserva as precio_total
        FROM reservas r
        INNER JOIN clientes c ON r.cliente_id = c.id
        INNER JOIN mesas m ON r.mesa_id = m.id
        WHERE r.id = :id
    ");
    $stmt->execute(['id' => $reserva_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    if (empty($reserva['correo'])) {
        throw new Exception('El cliente no tiene correo electrónico registrado');
    }
    
    // Enviar correo usando el controlador
    $emailController = new EmailController($pdo);
    $resultado = $emailController->enviarCorreoReservaConfirmada($reserva);
    
    if ($resultado['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Correo enviado exitosamente',
            'data' => [
                'reserva_id' => $reserva_id,
                'correo_destino' => $reserva['correo'],
                'cliente' => $reserva['nombre'] . ' ' . $reserva['apellido'],
                'test_mode' => $resultado['test_mode'] ?? false
            ]
        ]);
    } else {
        throw new Exception($resultado['error'] ?? 'Error desconocido al enviar correo');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
