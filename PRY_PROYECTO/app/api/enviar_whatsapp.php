<?php
/**
 * Servicio de envío automático de WhatsApp usando Twilio
 */

header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once '../../conexion/db.php';

// Función para enviar WhatsApp usando Twilio
function enviarWhatsAppTwilio($telefono, $mensaje) {
    $config = require '../../config/whatsapp_config.php';
    
    if ($config['test_mode']) {
        error_log("MODO PRUEBA - WhatsApp no enviado a: $telefono");
        error_log("Mensaje: $mensaje");
        return [
            'success' => true,
            'message' => 'Mensaje registrado (modo prueba)',
            'test_mode' => true
        ];
    }
    
    if (!$config['auto_send_enabled']) {
        return [
            'success' => false,
            'message' => 'Envío automático de WhatsApp deshabilitado',
            'disabled' => true
        ];
    }

    if (empty($config['twilio_account_sid']) || empty($config['twilio_auth_token']) || empty($config['twilio_whatsapp_from'])) {
        return [
            'success' => false,
            'message' => 'Configuración de Twilio incompleta (SID/Auth Token/From)'
        ];
    }
    
    try {
        // Limpiar y formatear teléfono
        $telefonoLimpio = preg_replace('/\D/', '', (string)$telefono);
        $countryCode = (string)($config['country_code'] ?? '593');
        $countryCode = preg_replace('/\D/', '', $countryCode);

        if ($countryCode === '') {
            $countryCode = '593';
        }

        // Quitar prefijo internacional 00
        if (strpos($telefonoLimpio, '00') === 0) {
            $telefonoLimpio = substr($telefonoLimpio, 2);
        }

        // Si ya trae código de país, respetarlo; si no, prefijarlo
        if (strpos($telefonoLimpio, $countryCode) === 0) {
            $telefonoNormalizado = $telefonoLimpio;
        } elseif (strpos($telefonoLimpio, '0') === 0) {
            $telefonoNormalizado = $countryCode . substr($telefonoLimpio, 1);
        } else {
            $telefonoNormalizado = $countryCode . $telefonoLimpio;
        }

        $telefonoCompleto = 'whatsapp:+' . $telefonoNormalizado;
        
        // Preparar datos para Twilio
        $data = [
            'From' => $config['twilio_whatsapp_from'],
            'To' => $telefonoCompleto,
            'Body' => $mensaje
        ];
        
        // URL de la API de Twilio
        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $config['twilio_account_sid'] . '/Messages.json';
        
        // Inicializar cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_USERPWD, $config['twilio_account_sid'] . ':' . $config['twilio_auth_token']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Ejecutar request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success' => false,
                'message' => 'Error cURL al enviar WhatsApp: ' . $curlError
            ];
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'WhatsApp enviado exitosamente',
                'sid' => $result['sid'] ?? null,
                'status' => $result['status'] ?? 'sent'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Error al enviar WhatsApp: ' . ($result['message'] ?? 'Error desconocido'),
                'error_code' => $result['code'] ?? null
            ];
        }
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Excepción al enviar WhatsApp: ' . $e->getMessage()
        ];
    }
}

// Función para construir mensaje de reserva
function construirMensajeReserva($datosReserva) {
    $config = require '../../config/whatsapp_config.php';
    $restaurantName = $config['restaurant_name'];
    
    $mensaje = "¡Hola {$datosReserva['cliente_nombre']}! ✨\n\n";
    $mensaje .= "¡Su reserva ha sido confirmada exitosamente! 🎉\n\n";
    $mensaje .= "📋 *DETALLES DE SU RESERVA*\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━━━\n";
    $mensaje .= "🎫 Nota: {$datosReserva['numero_nota']}\n";
    $mensaje .= "📅 Fecha: {$datosReserva['fecha_reserva']}\n";
    $mensaje .= "🕐 Hora: {$datosReserva['hora_reserva']}\n";
    $mensaje .= "🪑 Mesa: {$datosReserva['numero_mesa']}\n";
    $mensaje .= "👥 Personas: {$datosReserva['numero_personas']}\n";
    $mensaje .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    // Si hay platos incluidos
    if (!empty($datosReserva['platos'])) {
        $mensaje .= "🍽️ *PLATOS RESERVADOS*\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━\n";
        
        foreach ($datosReserva['platos'] as $plato) {
            $mensaje .= "• {$plato['nombre']} x{$plato['cantidad']}\n";
            $mensaje .= "  $" . number_format($plato['subtotal'], 2) . "\n";
        }
        
        $mensaje .= "\n💰 *RESUMEN DE PAGO*\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━\n";
        $mensaje .= "Reserva de Mesa: $" . number_format($datosReserva['precio_mesa'], 2) . "\n";
        $mensaje .= "Platos: $" . number_format($datosReserva['subtotal_platos'], 2) . "\n";
        $mensaje .= "Subtotal: $" . number_format($datosReserva['precio_mesa'] + $datosReserva['subtotal_platos'], 2) . "\n";
        $mensaje .= "IVA (12%): $" . number_format($datosReserva['impuesto'], 2) . "\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━\n";
        $mensaje .= "✨ *TOTAL: $" . number_format($datosReserva['total'], 2) . "* ✨\n\n";
    } else {
        $mensaje .= "💰 *VALOR DE RESERVA*\n";
        $mensaje .= "━━━━━━━━━━━━━━━━━━━━\n";
        $mensaje .= "Reserva de Mesa: $" . number_format($datosReserva['precio_mesa'], 2) . "\n\n";
    }
    
    $mensaje .= "📍 *{$restaurantName}*\n";
    $mensaje .= "Un placer servirle.\n\n";
    $mensaje .= "⚠️ *Importante:*\n";
    $mensaje .= "• Llegue 10 minutos antes de su hora\n";
    $mensaje .= "• En caso de cancelación, avise con 24h\n";
    $mensaje .= "• Mantenga esta confirmación\n\n";
    $mensaje .= "¡Le esperamos! 🌟";
    
    return $mensaje;
}

// Procesar request
try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['reserva_id']) || empty($input['reserva_id'])) {
        throw new Exception('ID de reserva requerido');
    }
    
    $reserva_id = intval($input['reserva_id']);
    
    // Obtener datos completos de la reserva
    $stmt = $pdo->prepare("
        SELECT 
            r.id, r.fecha_reserva, r.hora_reserva, r.numero_personas,
            m.numero_mesa, m.precio_reserva,
            c.nombre, c.apellido, c.telefono,
            nc.numero_nota, nc.subtotal, nc.impuesto, nc.total
        FROM reservas r
        INNER JOIN mesas m ON r.mesa_id = m.id
        INNER JOIN clientes c ON r.cliente_id = c.id
        LEFT JOIN notas_consumo nc ON r.id = nc.reserva_id
        WHERE r.id = ?
    ");
    $stmt->execute([$reserva_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Obtener platos de la reserva (si existen)
    $stmt = $pdo->prepare("
        SELECT p.nombre, pp.cantidad, pp.subtotal
        FROM pre_pedidos pp
        INNER JOIN platos p ON pp.plato_id = p.id
        WHERE pp.reserva_id = ?
    ");
    $stmt->execute([$reserva_id]);
    $platos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Preparar datos para el mensaje
    $datosReserva = [
        'cliente_nombre' => $reserva['nombre'] . ' ' . $reserva['apellido'],
        'numero_nota' => $reserva['numero_nota'] ?? 'NC-' . date('Ymd') . '-' . str_pad($reserva_id, 6, '0', STR_PAD_LEFT),
        'fecha_reserva' => date('d/m/Y', strtotime($reserva['fecha_reserva'])),
        'hora_reserva' => date('H:i', strtotime($reserva['hora_reserva'])),
        'numero_mesa' => $reserva['numero_mesa'],
        'numero_personas' => $reserva['numero_personas'],
        'precio_mesa' => $reserva['precio_reserva'] ?? 0,
        'subtotal_platos' => $reserva['subtotal'] ?? 0,
        'impuesto' => $reserva['impuesto'] ?? 0,
        'total' => $reserva['total'] ?? $reserva['precio_reserva'],
        'platos' => $platos
    ];
    
    // Construir mensaje
    $mensaje = construirMensajeReserva($datosReserva);
    
    // Enviar WhatsApp
    $resultado = enviarWhatsAppTwilio($reserva['telefono'], $mensaje);
    
    // Registrar en base de datos
    if ($resultado['success']) {
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones_whatsapp 
            (reserva_id, telefono, mensaje, estado, sid_twilio, fecha_envio) 
            VALUES (?, ?, ?, 'enviado', ?, NOW())
        ");
        $stmt->execute([
            $reserva_id,
            $reserva['telefono'],
            $mensaje,
            $resultado['sid'] ?? null
        ]);
    } else {
        // Registrar error
        $stmt = $pdo->prepare("
            INSERT INTO notificaciones_whatsapp 
            (reserva_id, telefono, mensaje, estado, error_mensaje, fecha_envio) 
            VALUES (?, ?, ?, 'fallido', ?, NOW())
        ");
        $stmt->execute([
            $reserva_id,
            $reserva['telefono'],
            $mensaje,
            $resultado['message'] ?? 'Error desconocido'
        ]);
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
