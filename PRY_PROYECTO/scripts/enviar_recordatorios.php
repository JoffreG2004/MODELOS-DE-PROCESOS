<?php
/**
 * Script para enviar recordatorios de reservas próximas (2 horas antes)
 * Este script se ejecuta automáticamente cada 30 minutos vía cron job
 */

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../templates/email_recordatorio_reserva.php';

try {
    // Obtener reservas confirmadas que sean en 2 horas (±15 minutos)
    $stmt = $pdo->prepare("
        SELECT r.*, 
               c.nombre, c.apellido, c.email as correo, c.telefono,
               m.numero_mesa, m.ubicacion as zona
        FROM reservas r
        INNER JOIN clientes c ON r.cliente_id = c.id
        INNER JOIN mesas m ON r.mesa_id = m.id
        WHERE r.estado = 'confirmada'
        AND CONCAT(r.fecha_reserva, ' ', r.hora_reserva) BETWEEN 
            DATE_ADD(NOW(), INTERVAL 105 MINUTE) 
            AND DATE_ADD(NOW(), INTERVAL 135 MINUTE)                                                       
        AND r.id NOT IN (
            SELECT reserva_id 
            FROM notificaciones_email 
            WHERE tipo_email = 'recordatorio' 
            AND DATE(fecha_envio) = CURDATE()
        )
    ");
    
    $stmt->execute();
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $enviados = 0;
    $errores = 0;
    
    foreach ($reservas as $reserva) {
        if (empty($reserva['correo'])) {
            $errores++;
            continue;
        }
        
        // Generar HTML del recordatorio
        $htmlContent = generarHTMLRecordatorioReserva($reserva);
        
        // Preparar payload para n8n
        $config = require __DIR__ . '/../config/n8n_config.php';
        
        $payload = [
            'to' => $reserva['correo'],
            'to_name' => $reserva['nombre'] . ' ' . $reserva['apellido'],
            'from' => $config['from_email'],
            'from_name' => $config['from_name'],
            'subject' => '⏰ Recordatorio: Tu reserva es en 2 horas - Le Salon de Lumière',
            'html' => $htmlContent,
            'tipo' => 'recordatorio',
            'reserva_id' => $reserva['id']
        ];
        
        // Enviar a n8n
        $ch = curl_init($config['webhook_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Registrar envío
        $stmtLog = $pdo->prepare("
            INSERT INTO notificaciones_email (reserva_id, correo, tipo_email, mensaje, estado, fecha_envio)
            VALUES (:reserva_id, :correo, 'recordatorio', :mensaje, :estado, NOW())
        ");
        
        $estado = ($httpCode >= 200 && $httpCode < 300) ? 'enviado' : 'fallido';
        
        $stmtLog->execute([
            'reserva_id' => $reserva['id'],
            'correo' => $reserva['correo'],
            'mensaje' => 'Recordatorio enviado 2 horas antes',
            'estado' => $estado
        ]);
        
        if ($estado === 'enviado') {
            $enviados++;
        } else {
            $errores++;
        }
    }
    
    // Log de resultado
    $resultado = [
        'timestamp' => date('Y-m-d H:i:s'),
        'reservas_encontradas' => count($reservas),
        'enviados' => $enviados,
        'errores' => $errores
    ];
    
    echo json_encode($resultado, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
