<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once '../../conexion/db.php';

// Verificar autenticación de administrador (opcional para llamadas automáticas)
$requiereAuth = $_GET['auth'] ?? true;
if ($requiereAuth && (!isset($_SESSION['admin_id']) || !$_SESSION['admin_authenticated'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

try {
    $actualizados = 0;
    $fechaActual = date('Y-m-d');
    $horaActual = date('H:i:s');
    
    // 1. Cambiar reservas confirmadas a "en_curso" si la hora de reserva ya llegó
    $stmt = $pdo->prepare("
        UPDATE reservas 
        SET estado = 'en_curso'
        WHERE estado = 'confirmada'
        AND fecha_reserva = :fecha_actual
        AND hora_reserva <= :hora_actual
    ");
    $stmt->execute([
        'fecha_actual' => $fechaActual,
        'hora_actual' => $horaActual
    ]);
    $actualizados += $stmt->rowCount();
    
    // 2. Cambiar reservas "en_curso" a "finalizada" si ya pasaron 2 horas desde la hora de reserva
    $stmt = $pdo->prepare("
        UPDATE reservas 
        SET estado = 'finalizada'
        WHERE estado = 'en_curso'
        AND (
            fecha_reserva < :fecha_actual
            OR (
                fecha_reserva = :fecha_actual2 
                AND ADDTIME(hora_reserva, '02:00:00') < :hora_actual
            )
        )
    ");
    $stmt->execute([
        'fecha_actual' => $fechaActual,
        'fecha_actual2' => $fechaActual,
        'hora_actual' => $horaActual
    ]);
    $actualizados += $stmt->rowCount();
    
    // 3. Actualizar estado de las mesas según las reservas activas
    // Primero marcar todas como disponibles
    $pdo->exec("UPDATE mesas SET estado = 'disponible'");
    
    // Luego marcar como ocupadas las que tienen reservas en curso
    $pdo->exec("
        UPDATE mesas m
        INNER JOIN reservas r ON m.id = r.mesa_id
        SET m.estado = 'ocupada'
        WHERE r.estado = 'en_curso'
    ");
    
    // Marcar como reservadas las que tienen reservas confirmadas para hoy
    $stmt = $pdo->prepare("
        UPDATE mesas m
        INNER JOIN reservas r ON m.id = r.mesa_id
        SET m.estado = 'reservada'
        WHERE r.estado = 'confirmada'
        AND r.fecha_reserva = :fecha_actual
    ");
    $stmt->execute(['fecha_actual' => $fechaActual]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Estados actualizados correctamente',
        'actualizados' => $actualizados,
        'fecha' => $fechaActual,
        'hora' => $horaActual
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar estados: ' . $e->getMessage()
    ]);
}
?>
