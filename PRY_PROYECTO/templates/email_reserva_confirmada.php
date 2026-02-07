<?php
/**
 * Plantilla HTML para correo de reserva confirmada
 */

function generarHTMLReservaConfirmada($data) {
    $restaurantName = htmlspecialchars($data['restaurant_name'] ?? 'Le Salon de Lumière');
    $restaurantPhone = htmlspecialchars($data['restaurant_phone'] ?? '');
    $restaurantAddress = htmlspecialchars($data['restaurant_address'] ?? '');
    $restaurantWebsite = htmlspecialchars($data['restaurant_website'] ?? '');
    $restaurantLogo = htmlspecialchars($data['restaurant_logo'] ?? '');
    
    $clienteNombre = htmlspecialchars($data['cliente_nombre'] ?? '');
    $clienteApellido = htmlspecialchars($data['cliente_apellido'] ?? '');
    $fecha = htmlspecialchars($data['fecha'] ?? '');
    $hora = htmlspecialchars($data['hora'] ?? '');
    $numeroMesa = htmlspecialchars($data['numero_mesa'] ?? '');
    $numeroPersonas = htmlspecialchars($data['numero_personas'] ?? '');
    $zona = htmlspecialchars($data['zona'] ?? '');
    $precioTotal = htmlspecialchars($data['precio_total'] ?? '0.00');
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva Confirmada - {$restaurantName}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px 20px;
            text-align: center;
        }
        .header img {
            max-width: 150px;
            margin-bottom: 15px;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .success-icon {
            font-size: 60px;
            margin: 20px 0;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        .message {
            font-size: 16px;
            margin-bottom: 25px;
            color: #555;
        }
        .reservation-details {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 25px 0;
            border-radius: 5px;
        }
        .reservation-details h2 {
            color: #667eea;
            font-size: 20px;
            margin-bottom: 15px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            font-weight: 600;
            color: #555;
        }
        .detail-value {
            color: #333;
            font-weight: 500;
        }
        .highlight {
            background: #667eea;
            color: white;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
            margin: 25px 0;
            font-size: 16px;
        }
        .cta-button {
            display: inline-block;
            background: #667eea;
            color: #ffffff;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 25px;
            margin: 20px 0;
            font-weight: 600;
            transition: background 0.3s;
        }
        .cta-button:hover {
            background: #764ba2;
        }
        .footer {
            background: #f8f9fa;
            padding: 25px;
            text-align: center;
            color: #777;
            font-size: 14px;
        }
        .footer-info {
            margin: 10px 0;
        }
        .social-links {
            margin: 15px 0;
        }
        .social-links a {
            display: inline-block;
            margin: 0 10px;
            color: #667eea;
            text-decoration: none;
        }
        .divider {
            height: 1px;
            background: #e0e0e0;
            margin: 20px 0;
        }
        @media only screen and (max-width: 600px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            .content {
                padding: 20px;
            }
            .detail-row {
                flex-direction: column;
            }
            .detail-value {
                margin-top: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <img src="{$restaurantLogo}" alt="{$restaurantName}" onerror="this.style.display='none'">
            <h1>{$restaurantName}</h1>
            <div class="success-icon">✅</div>
            <h2>¡Reserva Confirmada!</h2>
        </div>
        
        <!-- Content -->
        <div class="content">
            <p class="greeting">Estimado/a <strong>{$clienteNombre} {$clienteApellido}</strong>,</p>
            
            <p class="message">
                ¡Excelentes noticias! Su reserva ha sido <strong>confirmada exitosamente</strong>. 
                Estamos emocionados de recibirle y brindarle una experiencia gastronómica excepcional.
            </p>
            
            <!-- Detalles de la Reserva -->
            <div class="reservation-details">
                <h2>📋 Detalles de su Reserva</h2>
                
                <div class="detail-row">
                    <span class="detail-label">📅 Fecha:</span>
                    <span class="detail-value">{$fecha}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">🕐 Hora:</span>
                    <span class="detail-value">{$hora}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">🪑 Mesa:</span>
                    <span class="detail-value">#{$numeroMesa}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">👥 Número de Personas:</span>
                    <span class="detail-value">{$numeroPersonas}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">📍 Zona:</span>
                    <span class="detail-value">{$zona}</span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">💰 Precio Total:</span>
                    <span class="detail-value">\${$precioTotal}</span>
                </div>
            </div>
            
            <div class="highlight">
                <strong>⏰ Importante:</strong> Por favor llegue 10 minutos antes de su hora de reserva.
            </div>
            
            <p class="message">
                Si necesita modificar o cancelar su reserva, por favor contáctenos con al menos 24 horas de anticipación.
            </p>
            
            <div style="text-align: center;">
                <a href="{$restaurantWebsite}" class="cta-button">Ver Menú</a>
            </div>
        </div>
        
        <div class="divider"></div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="footer-info">
                <strong>{$restaurantName}</strong>
            </div>
            <div class="footer-info">
                📍 {$restaurantAddress}
            </div>
            <div class="footer-info">
                📞 {$restaurantPhone}
            </div>
            <div class="footer-info">
                🌐 <a href="{$restaurantWebsite}" style="color: #667eea; text-decoration: none;">{$restaurantWebsite}</a>
            </div>
            
            <div class="divider"></div>
            
            <p style="font-size: 12px; color: #999; margin-top: 15px;">
                Este es un correo automático generado por nuestro sistema de reservas. Por favor no responda a este mensaje.
            </p>
        </div>
    </div>
</body>
</html>
HTML;
    
    return $html;
}
