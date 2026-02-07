<?php
/**
 * Configuración de n8n para envío de correos HTML
 */

// Cargar variables de entorno
require_once __DIR__ . '/env_loader.php';

return [
    // URL del webhook de n8n para envío de correos (PRODUCCIÓN)
    'webhook_url' => env('N8N_WEBHOOK_URL', 'http://localhost:5678/webhook/enviar-correo-reserva'),
    
    // Configuración del remitente
    'from_email' => env('FROM_EMAIL', 'noreply@lesalondelumiere.com'),
    'from_name' => env('FROM_NAME', 'Le Salon de Lumière'),
    
    // Configuración del restaurante
    'restaurant_name' => env('RESTAURANT_NAME', 'Le Salon de Lumière'),
    'restaurant_phone' => env('RESTAURANT_PHONE', '+593999999999'),
    'restaurant_address' => env('RESTAURANT_ADDRESS', 'Av. Principal 123, Quito, Ecuador'),
    'restaurant_website' => env('RESTAURANT_WEBSITE', 'https://www.lesalondelumiere.com'),
    'restaurant_logo' => env('RESTAURANT_LOGO', 'https://www.lesalondelumiere.com/assets/img/logo.png'),
    
    // Activar/desactivar envío automático
    'auto_send_enabled' => env('N8N_AUTO_SEND_ENABLED', true),
    
    // Modo de prueba (true = no envía realmente, solo registra en logs)
    'test_mode' => env('N8N_TEST_MODE', false),
    
    // Timeout para la petición a n8n (en segundos)
    'timeout' => env('N8N_TIMEOUT', 10),
    
    // Tipos de correo a enviar
    'email_types' => [
        'reserva_confirmada' => true,
        'reserva_modificada' => true,
        'reserva_cancelada' => true,
        'recordatorio_24h' => false,
    ]
];
