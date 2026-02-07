<?php
/**
 * Configuración de Notificaciones para Reservas
 * Define cuándo y qué notificaciones se envían
 */

return [
    // ========================================
    // NOTIFICACIÓN: +15 MINUTOS (NO-SHOW)
    // ========================================
    'noshow_notification' => [
        'enabled' => true,
        'tiempo_minutos' => 15, // Esperar 15 min después de la hora de reserva
        'tipo' => 'email', // 'email', 'whatsapp', 'ambos'
        'destinatario' => 'admin', // 'admin' o 'cliente'
        
        'email' => [
            'asunto' => '⚠️ ALERTA - Cliente NO ha llegado (Mesa {mesa})',
            'template' => 'noshow_alert', // Template a usar en N8N
        ],
        
        // Solo se envía UNA VEZ por reserva
        'enviar_una_vez' => true,
        
        // Requiere que el cliente NO esté marcado como llegado
        'requiere_cliente_no_llegado' => true,
    ],
    
    // ========================================
    // AUTO-FINALIZACIÓN (BACKUP)
    // ========================================
    'auto_finalizacion' => [
        'enabled' => true,
        'tiempo_horas' => 24, // Finalizar después de 24 horas
        'motivo' => 'Finalización automática por sistema (más de 24 horas)',
    ],
    
    // ========================================
    // NOTIFICACIONES DESHABILITADAS
    // ========================================
    // Estas NO se envían según los nuevos requisitos
    
    'preparacion_notification' => [
        'enabled' => false, // DESHABILITADO - Solo bloquear mesa, sin email
        'tiempo_minutos' => 60,
    ],
    
    'recordatorio_previo' => [
        'enabled' => false, // DESHABILITADO
        'tiempo_minutos' => 15,
    ],
    
    'inicio_reserva' => [
        'enabled' => false, // DESHABILITADO
    ],
    
    // ========================================
    // CONFIGURACIÓN N8N
    // ========================================
    'n8n' => [
        'webhook_noshow' => env('N8N_WEBHOOK_NOSHOW', 'http://localhost:5678/webhook/reserva-noshow'),
        'timeout' => 10, // segundos
        'retry_attempts' => 2,
    ],
    
    // ========================================
    // DATOS DEL ADMIN (DESTINATARIO)
    // ========================================
    'admin' => [
        'email' => env('ADMIN_EMAIL', 'admin@lesalondelumiere.com'),
        'nombre' => env('ADMIN_NAME', 'Administrador'),
        'telefono' => env('ADMIN_PHONE', '+593999999999'),
    ],
    
    // ========================================
    // CONFIGURACIÓN DE MESAS
    // ========================================
    'mesas' => [
        'tiempo_preparacion_minutos' => 60, // 1 hora antes
        'tiempo_minimo_separacion_minutos' => 180, // 3 horas entre reservas
        'auto_liberar_al_finalizar' => true,
    ],
];
