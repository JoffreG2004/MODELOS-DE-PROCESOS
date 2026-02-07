<?php
/**
 * API para confirmar una reserva pendiente (ADMIN)
 * Cambia el estado de pendiente a confirmada y envía WhatsApp
 */

// Iniciar buffer para evitar output no deseado
ob_start();

// Headers ANTES de incluir archivos
header('Content-Type: application/json; charset=UTF-8');

session_start();

// Respuesta consistente incluso ante errores fatales
$response_sent = false;
register_shutdown_function(function () use (&$response_sent) {
    if ($response_sent) {
        return;
    }
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor al confirmar la reserva'
        ]);
    }
});

try {
    // Incluir archivos necesarios dentro del try para capturar fallos
    require_once '../../conexion/db.php';
    require_once '../../controllers/AuditoriaController.php';
    require_once '../../controllers/EmailController.php';

    // Limpiar cualquier output generado por los includes
    if (ob_get_length()) {
        ob_clean();
    }

    // Verificar que sea admin
    if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Acceso denegado. Solo administradores pueden confirmar reservas.'
        ]);
        $response_sent = true;
        exit;
    }

    $buildInternalUrl = static function (string $relativePath): string {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $basePath = preg_replace('#/app/api$#', '', $scriptDir);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . $basePath . '/' . ltrim($relativePath, '/');
    };

    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    if (!is_array($data)) {
        $data = [];
    }
    $reserva_id = $data['reserva_id']
        ?? $data['id']
        ?? ($_POST['reserva_id'] ?? null)
        ?? ($_POST['id'] ?? null)
        ?? ($_GET['reserva_id'] ?? null)
        ?? ($_GET['id'] ?? null);
    $reserva_id = is_numeric($reserva_id) ? (int)$reserva_id : 0;
    
    if ($reserva_id <= 0) {
        throw new Exception('ID de reserva requerido');
    }
    
    // Verificar que la reserva exista y esté pendiente
    $stmt = $pdo->prepare("
        SELECT r.*, c.nombre, c.apellido, c.telefono, m.numero_mesa 
        FROM reservas r
        INNER JOIN clientes c ON r.cliente_id = c.id
        INNER JOIN mesas m ON r.mesa_id = m.id
        WHERE r.id = :id
    ");
    $stmt->bindParam(':id', $reserva_id, PDO::PARAM_INT);
    $stmt->execute();
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    if ($reserva['estado'] === 'confirmada') {
        throw new Exception('Esta reserva ya está confirmada');
    }
    
    if ($reserva['estado'] === 'cancelada') {
        throw new Exception('No se puede confirmar una reserva cancelada');
    }
    
    // ============================================================
    // VALIDACIÓN CRÍTICA: NO permitir si ya hay otra reserva activa
    // dentro de las 3 horas para la misma mesa y fecha
    // ============================================================
    $stmtValidarBloque = $pdo->prepare("
        SELECT r.id, 
               CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre,
               m.numero_mesa,
               TIME_FORMAT(r.hora_reserva, '%H:%i') as hora
        FROM reservas r
        INNER JOIN clientes c ON r.cliente_id = c.id
        INNER JOIN mesas m ON r.mesa_id = m.id
        WHERE r.mesa_id = :mesa_id
        AND DATE(r.fecha_reserva) = DATE(:fecha)
        AND r.estado IN ('confirmada', 'preparando', 'en_curso')
        AND r.id != :reserva_id
        AND ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_referencia)))) < 10800
        ORDER BY ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_referencia_orden)))) ASC
        LIMIT 1
    ");
    
    // Hora base exacta (sin strtotime para evitar desfases)
    $hora_inicio = substr((string)$reserva['hora_reserva'], 0, 8);
    if (strlen($hora_inicio) === 5) {
        $hora_inicio .= ':00';
    }
    
    $stmtValidarBloque->execute([
        'mesa_id' => $reserva['mesa_id'],
        'fecha' => $reserva['fecha_reserva'],
        'reserva_id' => $reserva_id,
        'hora_referencia' => $hora_inicio,
        'hora_referencia_orden' => $hora_inicio
    ]);
    
    $bloqueOcupado = $stmtValidarBloque->fetch(PDO::FETCH_ASSOC);
    
    if ($bloqueOcupado) {
        $horaConflicto = DateTime::createFromFormat('H:i', $bloqueOcupado['hora']);
        $horaAntes = $horaConflicto ? (clone $horaConflicto)->modify('-3 hours')->format('H:i') : null;
        $horaDespues = $horaConflicto ? (clone $horaConflicto)->modify('+3 hours')->format('H:i') : null;
        $sugerencia = ($horaAntes && $horaDespues)
            ? " Por favor reserva a las {$horaAntes} o a las {$horaDespues}."
            : '';

        throw new Exception(
            "NO se puede confirmar: " . 
            "La mesa {$bloqueOcupado['numero_mesa']} ya está confirmada para " .
            "{$bloqueOcupado['cliente_nombre']} a las {$bloqueOcupado['hora']} " .
            "en el mismo bloque de 3 horas." .
            $sugerencia .
            " Rechaza esta oferta o espera a que liberen la mesa."
        );
    }

    // Bloqueo por reserva de zona: si existe una reserva de zona para esa fecha y zona,
    // no permitir reservas normales que inicien menos de 3 horas antes del inicio de la zona.
    $stmtZonaMesa = $pdo->prepare("SELECT ubicacion FROM mesas WHERE id = :mesa_id");
    $stmtZonaMesa->execute(['mesa_id' => $reserva['mesa_id']]);
    $mesaZona = $stmtZonaMesa->fetch(PDO::FETCH_ASSOC);
    if ($mesaZona && !empty($mesaZona['ubicacion'])) {
        $hora_normal = $hora_inicio;
        $stmtZona = $pdo->prepare("
            SELECT rz.id, rz.hora_reserva, rz.zonas
            FROM reservas_zonas rz
            WHERE rz.fecha_reserva = ?
              AND rz.estado IN ('pendiente', 'confirmada')
        ");
        $stmtZona->execute([$reserva['fecha_reserva']]);
        $zona_conflict_id = null;
        $zona_conflict_hora = null;
        while ($row = $stmtZona->fetch(PDO::FETCH_ASSOC)) {
            $zonasRow = json_decode($row['zonas'] ?? '[]', true);
            if (!is_array($zonasRow) || !in_array($mesaZona['ubicacion'], $zonasRow, true)) {
                continue;
            }
            $hora_zona = $row['hora_reserva'];
            if (strlen($hora_normal) === 5) $hora_normal .= ':00';
            if (strlen($hora_zona) === 5) $hora_zona .= ':00';
            $stmtOverlap = $pdo->prepare("
                SELECT TIME_TO_SEC(TIMEDIFF(TIME(?), TIME(?))) AS diff_seg
            ");
            $stmtOverlap->execute([$hora_zona, $hora_normal]);
            $diff = (int)$stmtOverlap->fetchColumn();
            if ($diff < 10800) {
                $zona_conflict_id = (int)$row['id'];
                $zona_conflict_hora = $row['hora_reserva'];
                break;
            }
        }
        if ($zona_conflict_id !== null) {
            throw new Exception("No se puede confirmar: hay una reserva de zona en {$mesaZona['ubicacion']} desde {$zona_conflict_hora} y bloquea el resto del día");
        }
    }

    // ============================================================
    // PASO EXTRA: Si esta reserva queda confirmada, cancelar reservas
    // de zona PENDIENTES que entren en conflicto (misma fecha y zona,
    // y la reserva de zona inicia menos de 3 horas después de esta reserva).
    // ============================================================
    $zonasCanceladas = [];
    if ($mesaZona && !empty($mesaZona['ubicacion'])) {
        $hora_normal = $hora_inicio;
        if (strlen($hora_normal) === 5) $hora_normal .= ':00';

        $stmtZonasPend = $pdo->prepare("
            SELECT rz.id, rz.hora_reserva, rz.zonas, rz.cliente_id, c.nombre, c.apellido, c.telefono, c.email
            FROM reservas_zonas rz
            INNER JOIN clientes c ON rz.cliente_id = c.id
            WHERE rz.fecha_reserva = ?
              AND rz.estado = 'pendiente'
        ");
        $stmtZonasPend->execute([$reserva['fecha_reserva']]);
        while ($row = $stmtZonasPend->fetch(PDO::FETCH_ASSOC)) {
            $zonasRow = json_decode($row['zonas'] ?? '[]', true);
            if (!is_array($zonasRow) || !in_array($mesaZona['ubicacion'], $zonasRow, true)) {
                continue;
            }
            $hora_zona = $row['hora_reserva'];
            if (strlen($hora_zona) === 5) $hora_zona .= ':00';
            $stmtOverlap = $pdo->prepare("
                SELECT TIME_TO_SEC(TIMEDIFF(TIME(?), TIME(?))) AS diff_seg
            ");
            $stmtOverlap->execute([$hora_zona, $hora_normal]);
            $diff = (int)$stmtOverlap->fetchColumn();
            if ($diff < 10800) {
                $stmtCancelarZona = $pdo->prepare("
                    UPDATE reservas_zonas
                    SET estado = 'cancelada', motivo_cancelacion = 'Auto-cancelada: Reserva normal confirmada en el mismo bloque de 3 horas'
                    WHERE id = ?
                ");
                $stmtCancelarZona->execute([$row['id']]);

                $zonasCanceladas[] = [
                    'id' => (int)$row['id'],
                    'cliente' => $row['nombre'] . ' ' . $row['apellido'],
                    'telefono' => $row['telefono'],
                    'email' => $row['email'],
                    'hora_reserva' => $row['hora_reserva']
                ];

                // Enviar notificacion por EMAIL (N8N)
                try {
                    $emailController = new EmailController($pdo);
                    $emailController->enviarCorreoCancelacion([
                        'id' => $row['id'],
                        'nombre' => $row['nombre'],
                        'apellido' => $row['apellido'],
                        'correo' => $row['email'],
                        'numero_mesa' => 'Zona completa',
                        'fecha_reserva' => $reserva['fecha_reserva'],
                        'hora_reserva' => $row['hora_reserva'],
                        'motivo' => 'Reserva normal confirmada en el mismo bloque de 3 horas'
                    ]);
                } catch (Throwable $e) {
                    error_log("Error enviando email cancelacion zona (ReservaZona #{$row['id']}): " . $e->getMessage());
                }

                // Notificar por WhatsApp (si aplica)
                try {
                    $ch = curl_init($buildInternalUrl('app/api/enviar_whatsapp_cancelacion.php'));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'reserva_id' => $row['id'],
                        'telefono' => $row['telefono'],
                        'cliente_nombre' => $row['nombre'] . ' ' . $row['apellido'],
                        'mesa' => 'ZONA',
                        'fecha' => $reserva['fecha_reserva'],
                        'hora' => $row['hora_reserva'],
                        'motivo' => 'confirmada_para_otro_cliente'
                    ]));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                    curl_exec($ch);
                    curl_close($ch);
                } catch (Throwable $e) {
                    error_log("Error WhatsApp cancelacion zona (ReservaZona #{$row['id']}): " . $e->getMessage());
                }
            }
        }
    }
    // Buscar todas las reservas PENDIENTES de la misma mesa en el bloque de 3 horas
    $stmtBuscarDuplicadas = $pdo->prepare("
        SELECT r.id, r.cliente_id, c.nombre, c.apellido, c.email, c.telefono,
               m.numero_mesa, r.fecha_reserva, r.hora_reserva
        FROM reservas r
        INNER JOIN clientes c ON r.cliente_id = c.id
        INNER JOIN mesas m ON r.mesa_id = m.id
        WHERE r.mesa_id = :mesa_id
        AND DATE(r.fecha_reserva) = DATE(:fecha)
        AND r.estado = 'pendiente'
        AND r.id != :reserva_confirmada_id
        AND ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_base)))) < 10800
    ");
    
    $stmtBuscarDuplicadas->execute([
        'mesa_id' => $reserva['mesa_id'],
        'fecha' => $reserva['fecha_reserva'],
        'reserva_confirmada_id' => $reserva_id,
        'hora_base' => $reserva['hora_reserva']
    ]);
    
    $reservasCanceladas = $stmtBuscarDuplicadas->fetchAll(PDO::FETCH_ASSOC);
    $totalCanceladas = count($reservasCanceladas);
    
    // Cancelar cada reserva duplicada
    if ($totalCanceladas > 0) {
        $stmtCancelar = $pdo->prepare("
            UPDATE reservas 
            SET estado = 'cancelada',
                motivo_cancelacion = 'Auto-cancelada: Mesa confirmada para otro cliente en el mismo bloque de 3 horas'
            WHERE id = :id
        ");
        
        foreach ($reservasCanceladas as $reservaCancelada) {
            // Cancelar la reserva
            $stmtCancelar->execute(['id' => $reservaCancelada['id']]);
            
            // Registrar en auditoría
            $auditoriaController = new AuditoriaController($pdo);
            $auditoriaController->registrarAccionReserva(
                $reservaCancelada['id'],
                $_SESSION['admin_id'] ?? null,
                'cancelar_automatico',
                'pendiente',
                'cancelada',
                ['estado' => 'pendiente'],
                ['estado' => 'cancelada'],
                "Auto-cancelada: Mesa {$reservaCancelada['numero_mesa']} confirmada para otro cliente"
            );
            
            // Enviar notificación por EMAIL (N8N)
            try {
                $emailController = new EmailController($pdo);
                $emailController->enviarCorreoCancelacion([
                    'id' => $reservaCancelada['id'],
                    'nombre' => $reservaCancelada['nombre'],
                    'apellido' => $reservaCancelada['apellido'],
                    'correo' => $reservaCancelada['email'],
                    'numero_mesa' => $reservaCancelada['numero_mesa'],
                    'fecha_reserva' => $reservaCancelada['fecha_reserva'],
                    'hora_reserva' => $reservaCancelada['hora_reserva'],
                    'motivo' => 'La mesa fue confirmada para otro cliente'
                ]);
            } catch (Throwable $e) {
                error_log("Error enviando email de cancelación (Reserva #{$reservaCancelada['id']}): " . $e->getMessage());
            }
            
            // Enviar notificación por WHATSAPP (Twilio)
            try {
                $ch = curl_init($buildInternalUrl('app/api/enviar_whatsapp_cancelacion.php'));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                    'reserva_id' => $reservaCancelada['id'],
                    'telefono' => $reservaCancelada['telefono'],
                    'cliente_nombre' => $reservaCancelada['nombre'] . ' ' . $reservaCancelada['apellido'],
                    'mesa' => $reservaCancelada['numero_mesa'],
                    'fecha' => $reservaCancelada['fecha_reserva'],
                    'hora' => $reservaCancelada['hora_reserva'],
                    'motivo' => 'confirmada_para_otro_cliente'
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $responseCancelacion = curl_exec($ch);
                $httpCodeCancelacion = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCodeCancelacion >= 400) {
                    error_log("Error HTTP {$httpCodeCancelacion} al enviar WhatsApp de cancelación (Reserva #{$reservaCancelada['id']}): {$responseCancelacion}");
                }
            } catch (Throwable $e) {
                error_log("Error enviando WhatsApp de cancelación (Reserva #{$reservaCancelada['id']}): " . $e->getMessage());
            }
        }
    }
    
    // ============================================================
    // PASO 2: CONFIRMAR LA RESERVA SELECCIONADA
    // ============================================================
    $stmt = $pdo->prepare("UPDATE reservas SET estado = 'confirmada' WHERE id = :id");
    $stmt->bindParam(':id', $reserva_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // REGISTRAR EN AUDITORÍA
    $auditoriaController = new AuditoriaController($pdo);
    $auditoriaController->registrarAccionReserva(
        $reserva_id,
        $_SESSION['admin_id'] ?? null,
        'confirmar',
        $reserva['estado'],
        'confirmada',
        ['estado' => $reserva['estado']],
        ['estado' => 'confirmada'],
        'Confirmada por administrador desde dashboard'
    );
    
    // Enviar WhatsApp de confirmación
    $whatsapp_enviado = false;
    $whatsapp_error = null;
    
    try {
        $ch = curl_init($buildInternalUrl('app/api/enviar_whatsapp.php'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reserva_id' => $reserva_id]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        $parsed = json_decode((string)$response, true);
        if ($response === false || !empty($curl_error)) {
            $whatsapp_error = 'Error cURL al invocar envío de WhatsApp: ' . $curl_error;
        } elseif ($http_code >= 200 && $http_code < 300 && is_array($parsed) && !empty($parsed['success'])) {
            $whatsapp_enviado = true;
        } else {
            $detalle = is_array($parsed) ? ($parsed['message'] ?? json_encode($parsed)) : trim((string)$response);
            $whatsapp_error = "Error WhatsApp (HTTP {$http_code})" . ($detalle !== '' ? ": {$detalle}" : '');
        }
    } catch (Throwable $e) {
        $whatsapp_error = $e->getMessage();
        error_log("Error al enviar WhatsApp: " . $e->getMessage());
    }
    
    // Enviar correo HTML con n8n
    $email_enviado = false;
    $email_error = null;
    
    try {
        $emailController = new EmailController($pdo);
        
        // Obtener datos completos de la reserva para el correo
        $stmtEmail = $pdo->prepare("
            SELECT r.*, c.nombre, c.apellido, c.email as correo, c.telefono, 
                   m.numero_mesa, m.ubicacion as zona, m.precio_reserva as precio_total
            FROM reservas r
            INNER JOIN clientes c ON r.cliente_id = c.id
            INNER JOIN mesas m ON r.mesa_id = m.id
            WHERE r.id = :id
        ");
        $stmtEmail->execute(['id' => $reserva_id]);
        $reservaCompleta = $stmtEmail->fetch(PDO::FETCH_ASSOC);
        
        if ($reservaCompleta && !empty($reservaCompleta['correo'])) {
            $resultadoEmail = $emailController->enviarCorreoReservaConfirmada($reservaCompleta);
            $email_enviado = $resultadoEmail['success'];
            $email_error = $resultadoEmail['error'] ?? null;
        } else {
            $email_error = "Cliente sin correo electrónico";
        }
    } catch (Throwable $e) {
        $email_error = $e->getMessage();
        error_log("Error al enviar correo: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Reserva confirmada exitosamente'
            . ($totalCanceladas > 0 ? " y {$totalCanceladas} reserva(s) pendiente(s) cancelada(s) automáticamente" : '')
            . (count($zonasCanceladas) > 0 ? " y " . count($zonasCanceladas) . " reserva(s) de zona cancelada(s) automáticamente" : ''),
        'reserva' => [
            'id' => $reserva_id,
            'cliente' => $reserva['nombre'] . ' ' . $reserva['apellido'],
            'telefono' => $reserva['telefono'],
            'mesa' => $reserva['numero_mesa'],
            'estado' => 'confirmada'
        ],
        'reservas_canceladas' => [
            'total' => $totalCanceladas,
            'detalles' => array_map(function($r) {
                return [
                    'id' => $r['id'],
                    'cliente' => $r['nombre'] . ' ' . $r['apellido'],
                    'telefono' => $r['telefono'],
                    'email' => $r['email']
                ];
            }, $reservasCanceladas)
        ],
        'whatsapp' => [
            'enviado' => $whatsapp_enviado,
            'error' => $whatsapp_error
        ],
        'email' => [
            'enviado' => $email_enviado,
            'error' => $email_error
        ],
        'reservas_zona_canceladas' => [
            'total' => count($zonasCanceladas),
            'detalles' => $zonasCanceladas
        ]
    ]);
    $response_sent = true;
    
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    $response_sent = true;
}
?>
