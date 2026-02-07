<?php
/**
 * API para enviar WhatsApp de cancelación de reserva
 * Se usa cuando una reserva es cancelada automáticamente
 */

header('Content-Type: application/json; charset=UTF-8');

// Cargar configuración de Twilio
$twilioConfig = require '../../config/whatsapp_config.php';

try {
    // Obtener datos del POST
    $data = json_decode(file_get_contents('php://input'), true);
    
    $reserva_id = $data['reserva_id'] ?? null;
    $telefono = $data['telefono'] ?? null;
    $cliente_nombre = $data['cliente_nombre'] ?? '';
    $mesa = $data['mesa'] ?? '';
    $fecha = $data['fecha'] ?? '';
    $hora = $data['hora'] ?? '';
    $motivo = $data['motivo'] ?? 'confirmada_para_otro_cliente';
    
    // Validaciones
    if (!$reserva_id || !$telefono) {
        throw new Exception('Datos incompletos: ID de reserva y teléfono son requeridos');
    }
    
    if (!empty($twilioConfig['test_mode'])) {
        echo json_encode([
            'success' => true,
            'message' => 'WhatsApp de cancelación registrado en modo prueba',
            'reserva_id' => $reserva_id,
            'telefono' => $telefono,
            'test_mode' => true
        ]);
        exit;
    }

    if (isset($twilioConfig['auto_send_enabled']) && !$twilioConfig['auto_send_enabled']) {
        throw new Exception('Envío automático de WhatsApp deshabilitado');
    }

    if (empty($twilioConfig['twilio_account_sid']) || empty($twilioConfig['twilio_auth_token']) || empty($twilioConfig['twilio_whatsapp_from'])) {
        throw new Exception('Configuración de Twilio incompleta (SID/Auth Token/From)');
    }

    // Formatear número de teléfono (asegurar formato internacional sin duplicar país)
    $telefono = preg_replace('/[^0-9+]/', '', $telefono);
    if (substr($telefono, 0, 1) !== '+') {
        $telefono = preg_replace('/\D/', '', $telefono);
        $countryCode = preg_replace('/\D/', '', (string)($twilioConfig['country_code'] ?? '593'));
        if ($countryCode === '') {
            $countryCode = '593';
        }
        if (strpos($telefono, '00') === 0) {
            $telefono = substr($telefono, 2);
        }
        if (strpos($telefono, $countryCode) === 0) {
            $telefono = '+' . $telefono;
        } elseif (strpos($telefono, '0') === 0) {
            $telefono = '+' . $countryCode . substr($telefono, 1);
        } else {
            $telefono = '+' . $countryCode . $telefono;
        }
    }

    // Mensajes según motivo
    $mensajes = [
        'confirmada_para_otro_cliente' => "⚠️ *Reserva Cancelada - {$twilioConfig['restaurant_name']}*\n\nEstimado/a *{$cliente_nombre}*,\n\nLamentamos informarte que tu reserva ha sido cancelada automáticamente.\n\n📅 Fecha: {$fecha}\n🕐 Hora: {$hora}\n🪑 Mesa: {$mesa}\n\n*Motivo:* La mesa fue confirmada para otro cliente que realizó su reserva primero.\n\nTe invitamos a hacer una nueva reserva en otro horario. ¡Disculpa las molestias!\n\n📞 Contacto: {$twilioConfig['restaurant_phone']}\n\n_Este es un mensaje automático._",
        
        'no_show' => "⚠️ *Reserva Cancelada - {$twilioConfig['restaurant_name']}*\n\nEstimado/a *{$cliente_nombre}*,\n\nTu reserva fue cancelada porque no te presentaste en el horario acordado.\n\n📅 Fecha: {$fecha}\n🕐 Hora: {$hora}\n🪑 Mesa: {$mesa}\n\nPara futuras reservas, por favor confirma tu asistencia.\n\n📞 Contacto: {$twilioConfig['restaurant_phone']}",
        
        'admin_cancelacion' => "⚠️ *Reserva Cancelada - {$twilioConfig['restaurant_name']}*\n\nEstimado/a *{$cliente_nombre}*,\n\nTu reserva ha sido cancelada.\n\n📅 Fecha: {$fecha}\n🕐 Hora: {$hora}\n🪑 Mesa: {$mesa}\n\n📞 Para más información: {$twilioConfig['restaurant_phone']}"
    ];
    
    $mensaje = $mensajes[$motivo] ?? $mensajes['admin_cancelacion'];
    
    // Preparar datos para Twilio
    $fromNumber = $twilioConfig['twilio_whatsapp_from'] ?? 'whatsapp:+14155238886';
    if (strpos($fromNumber, 'whatsapp:') !== 0) {
        $fromNumber = 'whatsapp:' . $fromNumber;
    }

    $postData = [
        'From' => $fromNumber,
        'To' => 'whatsapp:' . $telefono,
        'Body' => $mensaje
    ];
    
    // Construir URL de la API de Twilio
    $url = "https://api.twilio.com/2010-04-01/Accounts/{$twilioConfig['twilio_account_sid']}/Messages.json";
    
    // Inicializar cURL
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($twilioConfig['twilio_account_sid'] . ':' . $twilioConfig['twilio_auth_token'])
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Ejecutar request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Verificar respuesta
    if ($error) {
        throw new Exception("Error de cURL: " . $error);
    }
    
    if ($httpCode >= 200 && $httpCode < 300) {
        $responseData = json_decode($response, true);
        
        echo json_encode([
            'success' => true,
            'message' => 'WhatsApp de cancelación enviado exitosamente',
            'reserva_id' => $reserva_id,
            'telefono' => $telefono,
            'twilio_sid' => $responseData['sid'] ?? null,
            'twilio_status' => $responseData['status'] ?? null
        ]);
        
        // Registrar en log
        error_log("WhatsApp cancelación enviado - Reserva #{$reserva_id} - Tel: {$telefono} - Motivo: {$motivo}");
        
    } else {
        $responseData = json_decode($response, true);
        $errorMessage = $responseData['message'] ?? 'Error desconocido de Twilio';
        
        throw new Exception("Error de Twilio (HTTP {$httpCode}): " . $errorMessage);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al enviar WhatsApp: ' . $e->getMessage(),
        'reserva_id' => $reserva_id ?? null
    ]);
    
    error_log("ERROR WhatsApp cancelación - Reserva #{$reserva_id}: " . $e->getMessage());
}
?>
