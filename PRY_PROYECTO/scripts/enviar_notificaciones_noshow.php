<?php
/**
 * Script para Enviar Notificaciones de No-Show
 * Se ejecuta periódicamente (cada 5-10 minutos)
 * Detecta reservas donde el cliente NO llegó después de 15 minutos
 */

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../config/notificaciones_config.php';

$config = require __DIR__ . '/../config/notificaciones_config.php';

// Verificar si las notificaciones están habilitadas
if (!$config['noshow_notification']['enabled']) {
    echo json_encode([
        'success' => false,
        'message' => 'Notificaciones de no-show deshabilitadas en configuración'
    ]);
    exit;
}

try {
    $tiempo_espera = $config['noshow_notification']['tiempo_minutos']; // 15 minutos
    
    // ========================================
    // BUSCAR RESERVAS EN_CURSO SIN LLEGADA
    // ========================================
    
    $query = "
        SELECT 
            r.id,
            r.mesa_id,
            m.numero_mesa,
            m.ubicacion as zona,
            c.nombre as cliente_nombre,
            c.apellido as cliente_apellido,
            c.email as cliente_email,
            c.telefono as cliente_telefono,
            r.fecha_reserva,
            TIME_FORMAT(r.hora_reserva, '%H:%i') as hora_reserva_formateada,
            r.hora_reserva,
            r.num_personas,
            r.estado,
            r.cliente_llego,
            r.notificacion_noshow_enviada,
            TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) as minutos_transcurridos,
            'normal' as tipo_reserva
        FROM reservas r
        INNER JOIN mesas m ON r.mesa_id = m.id
        INNER JOIN clientes c ON r.cliente_id = c.id
        WHERE r.estado = 'en_curso'
        AND r.cliente_llego = 0
        AND r.notificacion_noshow_enviada = 0
        AND TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) >= ?
        AND TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) <= ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$tiempo_espera, $tiempo_espera + 10]); // Ventana de 10 minutos
    $reservas_noshow = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // También para reservas de zonas
    $query_zonas = "
        SELECT 
            rz.id,
            NULL as mesa_id,
            'Múltiples mesas' as numero_mesa,
            rz.zonas as zona,
            c.nombre as cliente_nombre,
            c.apellido as cliente_apellido,
            c.email as cliente_email,
            c.telefono as cliente_telefono,
            rz.fecha_reserva,
            TIME_FORMAT(rz.hora_reserva, '%H:%i') as hora_reserva_formateada,
            rz.hora_reserva,
            rz.numero_personas as num_personas,
            rz.estado,
            rz.cliente_llego,
            rz.notificacion_noshow_enviada,
            TIMESTAMPDIFF(MINUTE, TIMESTAMP(rz.fecha_reserva, rz.hora_reserva), NOW()) as minutos_transcurridos,
            'zona' as tipo_reserva
        FROM reservas_zonas rz
        INNER JOIN clientes c ON rz.cliente_id = c.id
        WHERE rz.estado = 'en_curso'
        AND rz.cliente_llego = 0
        AND rz.notificacion_noshow_enviada = 0
        AND TIMESTAMPDIFF(MINUTE, TIMESTAMP(rz.fecha_reserva, rz.hora_reserva), NOW()) >= ?
        AND TIMESTAMPDIFF(MINUTE, TIMESTAMP(rz.fecha_reserva, rz.hora_reserva), NOW()) <= ?
    ";
    
    $stmt = $pdo->prepare($query_zonas);
    $stmt->execute([$tiempo_espera, $tiempo_espera + 10]);
    $reservas_zonas_noshow = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $todas_noshow = array_merge($reservas_noshow, $reservas_zonas_noshow);
    
    if (empty($todas_noshow)) {
        echo json_encode([
            'success' => true,
            'message' => 'No hay reservas pendientes de notificación no-show',
            'total' => 0
        ]);
        exit;
    }
    
    // ========================================
    // ENVIAR NOTIFICACIONES
    // ========================================
    
    $resultados = [
        'total' => count($todas_noshow),
        'enviados' => 0,
        'fallidos' => 0,
        'detalles' => []
    ];
    
    foreach ($todas_noshow as $reserva) {
        try {
            // Preparar datos para N8N
            $datos_email = [
                'destinatario' => $config['admin']['email'],
                'nombre_admin' => $config['admin']['nombre'],
                'asunto' => str_replace('{mesa}', $reserva['numero_mesa'], $config['noshow_notification']['email']['asunto']),
                'reserva_id' => $reserva['id'],
                'tipo_reserva' => $reserva['tipo_reserva'],
                'mesa' => $reserva['numero_mesa'],
                'zona' => $reserva['zona'],
                'cliente_nombre' => $reserva['cliente_nombre'] . ' ' . $reserva['cliente_apellido'],
                'cliente_telefono' => $reserva['cliente_telefono'],
                'cliente_email' => $reserva['cliente_email'],
                'fecha' => date('d/m/Y', strtotime($reserva['fecha_reserva'])),
                'hora' => $reserva['hora_reserva_formateada'],
                'personas' => $reserva['num_personas'],
                'minutos_retraso' => $reserva['minutos_transcurridos'],
                'hora_actual' => date('H:i'),
                'template' => 'noshow_alert'
            ];
            
            // Enviar a N8N
            $ch = curl_init($config['n8n']['webhook_noshow']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos_email));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, $config['n8n']['timeout']);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                // Marcar notificación como enviada
                $tabla = $reserva['tipo_reserva'] === 'zona' ? 'reservas_zonas' : 'reservas';
                $stmt = $pdo->prepare("UPDATE {$tabla} SET notificacion_noshow_enviada = 1 WHERE id = ?");
                $stmt->execute([$reserva['id']]);
                
                $resultados['enviados']++;
                $resultados['detalles'][] = [
                    'reserva_id' => $reserva['id'],
                    'tipo' => $reserva['tipo_reserva'],
                    'mesa' => $reserva['numero_mesa'],
                    'cliente' => $datos_email['cliente_nombre'],
                    'status' => 'enviado'
                ];
            } else {
                throw new Exception("Error HTTP: " . $http_code);
            }
            
        } catch (Exception $e) {
            $resultados['fallidos']++;
            $resultados['detalles'][] = [
                'reserva_id' => $reserva['id'],
                'tipo' => $reserva['tipo_reserva'],
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Procesadas {$resultados['total']} notificaciones",
        'resultados' => $resultados
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
