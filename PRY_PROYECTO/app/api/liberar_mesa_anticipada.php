<?php
session_start();
header('Content-Type: application/json');

// Verificar que es admin
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once '../../conexion/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $reserva_id = $data['reserva_id'] ?? null;
    
    if (!$reserva_id) {
        throw new Exception('ID de reserva no proporcionado');
    }
    
    // Obtener la reserva
    $stmt = $pdo->prepare("
        SELECT r.*, m.numero_mesa, c.nombre, c.apellido, c.email, c.telefono
        FROM reservas r
        JOIN mesas m ON r.mesa_id = m.id
        JOIN clientes c ON r.cliente_id = c.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reserva_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    if (!in_array($reserva['estado'], ['confirmada', 'preparando', 'en_curso'])) {
        throw new Exception('Solo se pueden liberar mesas de reservas activas');
    }
    
    // Calcular tiempo real de uso (desde hora_reserva hasta ahora)
    $fecha_reserva = new DateTime($reserva['fecha_reserva'] . ' ' . $reserva['hora_reserva']);
    $ahora = new DateTime();
    $tiempo_usado = $fecha_reserva->diff($ahora);
    $minutos_usados = $tiempo_usado->h * 60 + $tiempo_usado->i;
    
    // Actualizar la reserva marcándola como completada
    $stmt_update = $pdo->prepare("
        UPDATE reservas 
        SET estado = 'completada',
            duracion_horas = ROUND(:minutos / 60, 1),
            fecha_actualizacion = NOW()
        WHERE id = ?
    ");
    $stmt_update->bindValue(':minutos', max(1, $minutos_usados), PDO::PARAM_INT);
    $stmt_update->execute([$reserva_id]);
    
    // Registrar en auditoría
    require_once '../../controllers/AuditoriaController.php';
    $audit = new AuditoriaController();
    $audit->registrarAccion(
        $_SESSION['admin_id'],
        'liberar_mesa_anticipada',
        'reservas',
        $reserva_id,
        'Mesa liberada anticipadamente. Tiempo usado: ' . round($minutos_usados / 60, 2) . ' horas'
    );
    
    // Enviar notificación al cliente (opcional)
    $mensaje = "Hola " . $reserva['nombre'] . ",\n\n";
    $mensaje .= "Tu reserva en la Mesa " . $reserva['numero_mesa'] . " ha sido finalizada por el administrador.\n";
    $mensaje .= "Tiempo de uso: " . round($minutos_usados / 60, 2) . " horas\n\n";
    $mensaje .= "Gracias por tu visita.";
    
    echo json_encode([
        'success' => true,
        'message' => 'Mesa liberada exitosamente',
        'reserva' => [
            'id' => $reserva['id'],
            'mesa' => $reserva['numero_mesa'],
            'cliente' => $reserva['nombre'] . ' ' . $reserva['apellido'],
            'tiempo_usado_horas' => round($minutos_usados / 60, 2),
            'duracion_original_horas' => $reserva['duracion_horas']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error al liberar mesa',
        'error' => $e->getMessage()
    ]);
}
?>
