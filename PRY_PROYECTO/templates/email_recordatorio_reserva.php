<?php
/**
 * Template de email HTML para recordatorio de reserva (2 horas antes)
 */

function generarHTMLRecordatorioReserva($reserva) {
    $cliente = htmlspecialchars($reserva['nombre'] . ' ' . $reserva['apellido']);
    $fecha = date('d/m/Y', strtotime($reserva['fecha_reserva']));
    $hora = date('H:i', strtotime($reserva['hora_reserva']));
    $mesa = htmlspecialchars($reserva['numero_mesa']);
    $zona = ucfirst($reserva['zona']);
    $personas = $reserva['numero_personas'];
    
    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de Reserva</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 20px;">
                <table width="600" cellpadding="0" cellspacing="0" style="background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 20px; border-radius: 10px 10px 0 0; text-align: center;">
                            <h1 style="color: white; margin: 0; font-size: 28px;">⏰ Recordatorio de Reserva</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 30px;">
                            <p style="font-size: 16px; color: #333;">Estimado/a <strong>{$cliente}</strong>,</p>
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <p style="margin: 0; font-size: 16px; color: #856404;"><strong>⏰ ¡Tu reserva es en 2 horas!</strong></p>
                            </div>
                            <p style="font-size: 18px; color: #28a745; font-weight: bold;">Te esperamos muy pronto en Le Salon de Lumière</p>
                            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h3 style="color: #667eea; margin-top: 0;">📋 Detalles de tu Reserva:</h3>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="padding: 10px 0; border-bottom: 1px solid #dee2e6;"><strong>📅 Fecha:</strong> {$fecha}</li>
                                    <li style="padding: 10px 0; border-bottom: 1px solid #dee2e6;"><strong>🕐 Hora:</strong> {$hora}</li>
                                    <li style="padding: 10px 0; border-bottom: 1px solid #dee2e6;"><strong>🪑 Mesa:</strong> {$mesa} - {$zona}</li>
                                    <li style="padding: 10px 0;"><strong>👥 Personas:</strong> {$personas}</li>
                                </ul>
                            </div>
                            <div style="background-color: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0; border-radius: 4px;">
                                <h4 style="margin-top: 0; color: #0c5393;">💡 Recordatorios:</h4>
                                <ul style="color: #0c5393;">
                                    <li>Por favor llega 10 minutos antes</li>
                                    <li>Si necesitas cancelar, hazlo con anticipación</li>
                                </ul>
                            </div>
                            <p style="font-size: 14px; color: #666;">¡Estamos preparando todo para tu llegada!<br>El equipo de <strong>Le Salon de Lumière</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; text-align: center; border-top: 1px solid #dee2e6;">
                            <p style="margin: 0; font-size: 12px; color: #999;">Este es un correo automático de recordatorio.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    return $html;
}
