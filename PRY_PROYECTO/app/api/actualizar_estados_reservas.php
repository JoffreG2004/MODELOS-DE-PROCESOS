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
    $pendientesCanceladas = 0;
    $pendientesZonasCanceladas = 0;
    $confirmadasEnCurso = 0;
    $enCursoFinalizadas = 0;
    $fechaActual = date('Y-m-d');
    $horaActual = date('H:i:s');

    $pdo->beginTransaction();

    // 1. Auto-cancelar reservas pendientes vencidas (fecha/hora ya pasó y nunca se confirmaron)
    $stmt = $pdo->prepare("
        UPDATE reservas
        SET estado = 'cancelada',
            motivo_cancelacion = COALESCE(NULLIF(motivo_cancelacion, ''), 'Auto-cancelada: fecha y hora superadas sin confirmación')
        WHERE estado = 'pendiente'
          AND TIMESTAMP(fecha_reserva, hora_reserva) < NOW()
    ");
    $stmt->execute();
    $pendientesCanceladas = $stmt->rowCount();
    $actualizados += $pendientesCanceladas;

    // 2. Auto-cancelar reservas de zona pendientes vencidas
    $stmt = $pdo->prepare("
        UPDATE reservas_zonas
        SET estado = 'cancelada',
            motivo_cancelacion = COALESCE(NULLIF(motivo_cancelacion, ''), 'Auto-cancelada: fecha y hora superadas sin confirmación')
        WHERE estado = 'pendiente'
          AND TIMESTAMP(fecha_reserva, hora_reserva) < NOW()
    ");
    $stmt->execute();
    $pendientesZonasCanceladas = $stmt->rowCount();
    $actualizados += $pendientesZonasCanceladas;

    // 3. Cambiar reservas confirmadas a "en_curso" si la hora de reserva ya llegó
    $stmt = $pdo->prepare("
        UPDATE reservas
        SET estado = 'en_curso'
        WHERE estado = 'confirmada'
          AND TIMESTAMP(fecha_reserva, hora_reserva) <= NOW()
          AND TIMESTAMP(fecha_reserva, ADDTIME(hora_reserva, '02:00:00')) > NOW()
    ");
    $stmt->execute();
    $confirmadasEnCurso = $stmt->rowCount();
    $actualizados += $confirmadasEnCurso;

    // 4. Cambiar reservas "en_curso" a "finalizada" si ya pasaron 2 horas desde la hora de reserva
    $stmt = $pdo->prepare("
        UPDATE reservas
        SET estado = 'finalizada'
        WHERE estado = 'en_curso'
          AND TIMESTAMP(fecha_reserva, ADDTIME(hora_reserva, '02:00:00')) < NOW()
    ");
    $stmt->execute();
    $enCursoFinalizadas = $stmt->rowCount();
    $actualizados += $enCursoFinalizadas;

    $pdo->commit();
    
    // 3. Actualizar estado de las mesas según las reservas activas
    // SOLO actualizar mesas que tienen reservas activas del sistema
    // NUNCA cambiar mesas a disponible automáticamente - solo el admin puede hacerlo
    
    // Marcar como ocupadas SOLO las mesas con reservas en curso
    $pdo->exec("
        UPDATE mesas m
        INNER JOIN reservas r ON m.id = r.mesa_id
        SET m.estado = 'ocupada'
        WHERE r.estado = 'en_curso'
        AND m.estado != 'mantenimiento'
    ");
    
    // Marcar como reservadas SOLO las mesas con reservas confirmadas para hoy
    $stmt = $pdo->prepare("
        UPDATE mesas m
        INNER JOIN reservas r ON m.id = r.mesa_id
        SET m.estado = 'reservada'
        WHERE r.estado = 'confirmada'
        AND r.fecha_reserva = :fecha_actual
        AND m.estado NOT IN ('mantenimiento', 'ocupada')
    ");
    $stmt->execute(['fecha_actual' => $fechaActual]);
    
    // NO liberamos mesas automáticamente - solo el administrador puede cambiarlas a disponible
    
    echo json_encode([
        'success' => true,
        'message' => 'Estados actualizados correctamente',
        'actualizados' => $actualizados,
        'detalle' => [
            'pendientes_canceladas' => $pendientesCanceladas,
            'pendientes_zonas_canceladas' => $pendientesZonasCanceladas,
            'confirmadas_a_en_curso' => $confirmadasEnCurso,
            'en_curso_finalizadas' => $enCursoFinalizadas
        ],
        'fecha' => $fechaActual,
        'hora' => $horaActual
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar estados: ' . $e->getMessage()
    ]);
}
?>
