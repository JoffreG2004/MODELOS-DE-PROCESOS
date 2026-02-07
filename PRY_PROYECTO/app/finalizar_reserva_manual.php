<?php
/**
 * Finalizar Reserva Manualmente
 * Solo el admin puede finalizar reservas cuando los clientes se van
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación de admin
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado. Debe iniciar sesión como administrador.'
    ]);
    exit;
}

require_once '../conexion/db.php';

try {
    // Validar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener datos
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['reserva_id']) || empty($data['reserva_id'])) {
        throw new Exception('ID de reserva requerido');
    }
    
    $reserva_id = intval($data['reserva_id']);
    $tipo_reserva = $data['tipo_reserva'] ?? 'normal'; // 'normal' o 'zona'
    $observaciones = $data['observaciones'] ?? '';
    $admin_usuario = $_SESSION['admin_usuario'] ?? $_SESSION['admin_nombre'] ?? 'Admin';
    
    // Determinar tabla según tipo
    $tabla = $tipo_reserva === 'zona' ? 'reservas_zonas' : 'reservas';
    
    // Verificar que la reserva existe y está en curso
    $stmt = $pdo->prepare("SELECT * FROM {$tabla} WHERE id = ?");
    $stmt->execute([$reserva_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    if (!in_array($reserva['estado'], ['preparando', 'en_curso'])) {
        throw new Exception('Solo se pueden finalizar reservas en estado PREPARANDO o EN_CURSO. Estado actual: ' . $reserva['estado']);
    }
    
    // Finalizar la reserva
    $stmt = $pdo->prepare("
        UPDATE {$tabla} 
        SET estado = 'finalizada',
            hora_finalizacion = NOW(),
            finalizada_por = ?,
            observaciones_finalizacion = ?
        WHERE id = ?
    ");
    
    $resultado = $stmt->execute([
        $admin_usuario,
        $observaciones,
        $reserva_id
    ]);
    
    if (!$resultado) {
        throw new Exception('Error al actualizar la reserva');
    }
    
    // Si es reserva normal, liberar la mesa
    if ($tipo_reserva === 'normal' && isset($reserva['mesa_id'])) {
        $stmt = $pdo->prepare("UPDATE mesas SET estado = 'disponible' WHERE id = ?");
        $stmt->execute([$reserva['mesa_id']]);
    }
    
    // Registrar en auditoría
    try {
        $stmt = $pdo->prepare("
            INSERT INTO auditoria_cambios 
            (tabla_afectada, accion, registro_id, usuario, detalles, fecha_hora)
            VALUES (?, 'FINALIZACION_MANUAL', ?, ?, ?, NOW())
        ");
        
        $detalles = "Reserva #{$reserva_id} finalizada manualmente";
        if ($observaciones) {
            $detalles .= ". Obs: " . substr($observaciones, 0, 100);
        }
        
        $stmt->execute([
            $tabla,
            $reserva_id,
            $admin_usuario,
            $detalles
        ]);
    } catch (Exception $e) {
        // No fallar si la auditoría falla
        error_log("Error en auditoría: " . $e->getMessage());
    }
    
    // Calcular tiempo total de la reserva
    $fecha_hora_inicio = strtotime($reserva['fecha_reserva'] . ' ' . $reserva['hora_reserva']);
    $tiempo_total_minutos = round((time() - $fecha_hora_inicio) / 60);
    $horas = floor($tiempo_total_minutos / 60);
    $minutos = $tiempo_total_minutos % 60;
    $tiempo_formateado = "{$horas}h {$minutos}min";
    
    echo json_encode([
        'success' => true,
        'message' => 'Reserva finalizada correctamente',
        'data' => [
            'reserva_id' => $reserva_id,
            'tipo_reserva' => $tipo_reserva,
            'tiempo_total' => $tiempo_formateado,
            'tiempo_total_minutos' => $tiempo_total_minutos,
            'finalizada_por' => $admin_usuario,
            'hora_finalizacion' => date('Y-m-d H:i:s')
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
