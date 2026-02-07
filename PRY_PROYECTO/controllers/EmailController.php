<?php
/**
 * Controlador de Correos Electrónicos con n8n
 * Gestiona el envío de correos HTML a través de webhooks de n8n
 */

class EmailController {
    private $pdo;
    private $n8nConfig;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->n8nConfig = require __DIR__ . '/../config/n8n_config.php';
    }
    
    /**
     * Enviar correo de confirmación de reserva
     */
    public function enviarCorreoReservaConfirmada($reserva) {
        try {
            // Verificar si el envío automático está habilitado
            if (!$this->n8nConfig['auto_send_enabled']) {
                return ['success' => false, 'error' => 'Envío automático de correos deshabilitado'];
            }
            
            // Verificar que el tipo de correo esté habilitado
            if (!$this->n8nConfig['email_types']['reserva_confirmada']) {
                return ['success' => false, 'error' => 'Correos de reserva confirmada deshabilitados'];
            }
            
            // Verificar que el cliente tenga correo
            if (empty($reserva['correo'])) {
                return ['success' => false, 'error' => 'El cliente no tiene correo electrónico registrado'];
            }
            
            // Generar HTML del correo
            require_once __DIR__ . '/../templates/email_reserva_confirmada.php';
            
            $emailData = [
                'restaurant_name' => $this->n8nConfig['restaurant_name'],
                'restaurant_phone' => $this->n8nConfig['restaurant_phone'],
                'restaurant_address' => $this->n8nConfig['restaurant_address'],
                'restaurant_website' => $this->n8nConfig['restaurant_website'],
                'restaurant_logo' => $this->n8nConfig['restaurant_logo'],
                'cliente_nombre' => $reserva['nombre'],
                'cliente_apellido' => $reserva['apellido'],
                'fecha' => $this->formatearFecha($reserva['fecha_reserva'] ?? $reserva['fecha'] ?? ''),
                'hora' => $this->formatearHora($reserva['hora_reserva'] ?? $reserva['hora'] ?? ''),
                'numero_mesa' => $reserva['numero_mesa'],
                'numero_personas' => $reserva['numero_personas'],
                'zona' => $reserva['zona'] ?? $reserva['ubicacion'] ?? 'General',
                'precio_total' => number_format($reserva['precio_total'] ?? $reserva['precio_reserva'] ?? 0, 2)
            ];
            
            $htmlContent = generarHTMLReservaConfirmada($emailData);
            
            // Preparar datos para n8n
            $payload = [
                'to' => $reserva['correo'],
                'to_name' => trim($reserva['nombre'] . ' ' . $reserva['apellido']),
                'from' => $this->n8nConfig['from_email'],
                'from_name' => $this->n8nConfig['from_name'],
                'subject' => '✅ Reserva Confirmada - ' . $this->n8nConfig['restaurant_name'],
                'html' => $htmlContent,
                'tipo' => 'reserva_confirmada',
                'reserva_id' => $reserva['id']
            ];
            
            // Modo de prueba
            if ($this->n8nConfig['test_mode']) {
                $this->registrarCorreo(
                    $reserva['id'],
                    $reserva['correo'],
                    'reserva_confirmada',
                    'Modo de prueba - no enviado',
                    'test'
                );
                return [
                    'success' => true,
                    'message' => 'Modo de prueba - correo no enviado realmente',
                    'test_mode' => true
                ];
            }
            
            // Enviar a n8n webhook
            $resultado = $this->enviarWebhookN8N($payload);
            
            // Registrar en log de correos
            $this->registrarCorreo(
                $reserva['id'],
                $reserva['correo'],
                'reserva_confirmada',
                $resultado['success'] ? 'Enviado exitosamente' : $resultado['error'],
                $resultado['success'] ? 'enviado' : 'fallido'
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enviar correo de modificación de reserva
     */
    public function enviarCorreoReservaModificada($reserva, $cambios = []) {
        try {
            if (!$this->n8nConfig['auto_send_enabled'] || 
                !$this->n8nConfig['email_types']['reserva_modificada']) {
                return ['success' => false, 'error' => 'Envío de correos de modificación deshabilitado'];
            }
            
            if (empty($reserva['correo'])) {
                return ['success' => false, 'error' => 'El cliente no tiene correo electrónico'];
            }
            
            // Aquí puedes crear una plantilla específica para modificaciones
            // Por ahora usamos la misma plantilla con un subject diferente
            require_once __DIR__ . '/../templates/email_reserva_confirmada.php';
            
            $emailData = [
                'restaurant_name' => $this->n8nConfig['restaurant_name'],
                'restaurant_phone' => $this->n8nConfig['restaurant_phone'],
                'restaurant_address' => $this->n8nConfig['restaurant_address'],
                'restaurant_website' => $this->n8nConfig['restaurant_website'],
                'restaurant_logo' => $this->n8nConfig['restaurant_logo'],
                'cliente_nombre' => $reserva['nombre'],
                'cliente_apellido' => $reserva['apellido'],
                'fecha' => $this->formatearFecha($reserva['fecha_reserva'] ?? $reserva['fecha'] ?? ''),
                'hora' => $this->formatearHora($reserva['hora_reserva'] ?? $reserva['hora'] ?? ''),
                'numero_mesa' => $reserva['numero_mesa'],
                'numero_personas' => $reserva['numero_personas'],
                'zona' => $reserva['zona'] ?? $reserva['ubicacion'] ?? 'General',
                'precio_total' => number_format($reserva['precio_total'] ?? $reserva['precio_reserva'] ?? 0, 2)
            ];
            
            $htmlContent = generarHTMLReservaConfirmada($emailData);
            
            $payload = [
                'to' => $reserva['correo'],
                'to_name' => trim($reserva['nombre'] . ' ' . $reserva['apellido']),
                'from' => $this->n8nConfig['from_email'],
                'from_name' => $this->n8nConfig['from_name'],
                'subject' => '📝 Reserva Modificada - ' . $this->n8nConfig['restaurant_name'],
                'html' => $htmlContent,
                'tipo' => 'reserva_modificada',
                'reserva_id' => $reserva['id']
            ];
            
            if ($this->n8nConfig['test_mode']) {
                $this->registrarCorreo(
                    $reserva['id'],
                    $reserva['correo'],
                    'reserva_modificada',
                    'Modo de prueba - no enviado',
                    'test'
                );
                return ['success' => true, 'test_mode' => true];
            }
            
            $resultado = $this->enviarWebhookN8N($payload);
            
            $this->registrarCorreo(
                $reserva['id'],
                $reserva['correo'],
                'reserva_modificada',
                $resultado['success'] ? 'Enviado exitosamente' : $resultado['error'],
                $resultado['success'] ? 'enviado' : 'fallido'
            );
            
            return $resultado;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Enviar webhook a n8n
     */
    private function enviarWebhookN8N($payload) {
        try {
            $webhookUrl = $this->n8nConfig['webhook_url'];
            $timeout = $this->n8nConfig['timeout'];
            
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Solo 5 segundos - fire and forget
            curl_setopt($ch, CURLOPT_NOSIGNAL, 1); // Evitar problemas con señales
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            // Si hay timeout, asumir que se envió correctamente (fire and forget)
            if ($curlError && (strpos($curlError, 'timeout') !== false || strpos($curlError, 'timed out') !== false)) {
                return [
                    'success' => true,
                    'response' => 'Email enviado (sin esperar respuesta)',
                    'http_code' => 200
                ];
            }
            
            if ($curlError) {
                throw new Exception("Error de conexión con n8n: {$curlError}");
            }
            
            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'response' => $response,
                    'http_code' => $httpCode
                ];
            } else {
                return [
                    'success' => false,
                    'error' => "Error HTTP {$httpCode}: {$response}",
                    'http_code' => $httpCode
                ];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Formatear fecha para mostrar
     */
    private function formatearFecha($fecha) {
        if (empty($fecha)) {
            return 'Fecha no disponible';
        }
        
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
        
        $timestamp = strtotime($fecha);
        if ($timestamp === false || $timestamp <= 0) {
            return 'Fecha no disponible';
        }
        
        $dia = date('d', $timestamp);
        $mes = $meses[intval(date('m', $timestamp))];
        $anio = date('Y', $timestamp);
        
        return "{$dia} de {$mes} de {$anio}";
    }
    
    /**
     * Formatear hora para mostrar
     */
    private function formatearHora($hora) {
        if (empty($hora)) {
            return 'Hora no disponible';
        }
        
        $timestamp = strtotime($hora);
        if ($timestamp === false) {
            return 'Hora no disponible';
        }
        
        return date('h:i A', $timestamp);
    }
    
    /**
     * Enviar correo de cancelación de reserva
     * Se usa cuando una reserva es cancelada automáticamente porque otra fue confirmada
     */
    public function enviarCorreoCancelacion($reserva) {
        try {
            // Verificar si el envío automático está habilitado
            if (!$this->n8nConfig['auto_send_enabled']) {
                return ['success' => false, 'error' => 'Envío automático de correos deshabilitado'];
            }
            
            // Verificar que el cliente tenga correo
            if (empty($reserva['correo'])) {
                return ['success' => false, 'error' => 'El cliente no tiene correo electrónico registrado'];
            }
            
            // Generar HTML del correo
            $emailData = [
                'restaurant_name' => $this->n8nConfig['restaurant_name'],
                'restaurant_phone' => $this->n8nConfig['restaurant_phone'],
                'restaurant_address' => $this->n8nConfig['restaurant_address'],
                'restaurant_website' => $this->n8nConfig['restaurant_website'],
                'restaurant_logo' => $this->n8nConfig['restaurant_logo'],
                'cliente_nombre' => $reserva['nombre'],
                'cliente_apellido' => $reserva['apellido'],
                'fecha' => $this->formatearFecha($reserva['fecha_reserva']),
                'hora' => $this->formatearHora($reserva['hora_reserva']),
                'numero_mesa' => $reserva['numero_mesa'],
                'motivo' => $reserva['motivo'] ?? 'La mesa fue confirmada para otro cliente'
            ];
            
            $htmlContent = $this->generarHTMLCancelacion($emailData);
            
            // Preparar datos para n8n
            $payload = [
                'to' => $reserva['correo'],
                'to_name' => trim($reserva['nombre'] . ' ' . $reserva['apellido']),
                'from' => $this->n8nConfig['from_email'],
                'from_name' => $this->n8nConfig['from_name'],
                'subject' => '❌ Reserva Cancelada - ' . $this->n8nConfig['restaurant_name'],
                'html' => $htmlContent,
                'tipo' => 'reserva_cancelada',
                'reserva_id' => $reserva['id']
            ];
            
            // Modo de prueba
            if ($this->n8nConfig['test_mode']) {
                $this->registrarCorreo(
                    $reserva['id'],
                    $reserva['correo'],
                    'reserva_cancelada',
                    'Modo de prueba - no enviado',
                    'test'
                );
                return [
                    'success' => true,
                    'message' => 'Modo de prueba - correo de cancelación no enviado realmente',
                    'test_mode' => true
                ];
            }
            
            // Enviar a n8n
            return $this->enviarAN8N($payload, $reserva['id'], 'reserva_cancelada');
            
        } catch (Exception $e) {
            error_log("Error enviando correo de cancelación: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generar HTML para email de cancelación
     */
    private function generarHTMLCancelacion($data) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset=\"UTF-8\">
            <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; }
                .content { padding: 40px 30px; }
                .alert-box { background: #fee2e2; border-left: 4px solid #dc2626; padding: 20px; margin: 20px 0; border-radius: 5px; }
                .alert-box h2 { margin: 0 0 10px 0; color: #991b1b; font-size: 20px; }
                .info-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .info-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
                .info-table td:first-child { font-weight: bold; color: #6b7280; width: 40%; }
                .footer { background: #f9fafb; padding: 30px; text-align: center; color: #6b7280; font-size: 14px; }
                .btn { display: inline-block; background: #d4af37; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class=\"container\">
                <div class=\"header\">
                    <h1>❌ Reserva Cancelada</h1>
                </div>
                
                <div class=\"content\">
                    <p>Estimado/a <strong>' . htmlspecialchars($data['cliente_nombre'] . ' ' . $data['cliente_apellido']) . '</strong>,</p>
                    
                    <div class=\"alert-box\">
                        <h2>⚠️ Tu reserva ha sido cancelada</h2>
                        <p style=\"margin: 0; color: #374151;\">Lamentamos informarte que tu reserva ha sido cancelada automáticamente.</p>
                    </div>
                    
                    <p><strong>Motivo de la cancelación:</strong><br>' . htmlspecialchars($data['motivo']) . '</p>
                    
                    <table class=\"info-table\">
                        <tr>
                            <td>📅 Fecha</td>
                            <td>' . htmlspecialchars($data['fecha']) . '</td>
                        </tr>
                        <tr>
                            <td>🕐 Hora</td>
                            <td>' . htmlspecialchars($data['hora']) . '</td>
                        </tr>
                        <tr>
                            <td>🪑 Mesa</td>
                            <td>' . htmlspecialchars($data['numero_mesa']) . '</td>
                        </tr>
                    </table>
                    
                    <p>Si deseas hacer una nueva reserva, te invitamos a hacerlo en nuestro sistema en línea o contactarnos directamente.</p>
                    
                    <div style=\"text-align: center;\">
                        <a href=\"' . htmlspecialchars($data['restaurant_website']) . '\" class=\"btn\">Hacer Nueva Reserva</a>
                    </div>
                </div>
                
                <div class=\"footer\">
                    <p><strong>' . htmlspecialchars($data['restaurant_name']) . '</strong></p>
                    <p>📞 ' . htmlspecialchars($data['restaurant_phone']) . '</p>
                    <p>📍 ' . htmlspecialchars($data['restaurant_address']) . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Registrar envío de correo en la base de datos
     */
    private function registrarCorreo($reservaId, $correo, $tipo, $mensaje, $estado) {
        try {
            // Crear tabla si no existe
            $this->crearTablaCorreos();
            
            $stmt = $this->pdo->prepare("
                INSERT INTO notificaciones_email 
                (reserva_id, correo, tipo_email, mensaje, estado, fecha_envio)
                VALUES (:reserva_id, :correo, :tipo, :mensaje, :estado, NOW())
            ");
            
            $stmt->execute([
                'reserva_id' => $reservaId,
                'correo' => $correo,
                'tipo' => $tipo,
                'mensaje' => $mensaje,
                'estado' => $estado
            ]);
            
            return true;
        } catch (Exception $e) {
            error_log("Error registrando correo: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Crear tabla de notificaciones de email si no existe
     */
    private function crearTablaCorreos() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS notificaciones_email (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reserva_id INT NOT NULL,
                correo VARCHAR(255) NOT NULL,
                tipo_email VARCHAR(50) NOT NULL,
                mensaje TEXT,
                estado ENUM('enviado', 'fallido', 'test') NOT NULL,
                fecha_envio DATETIME NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_reserva (reserva_id),
                INDEX idx_estado (estado),
                INDEX idx_fecha (fecha_envio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->pdo->exec($sql);
        } catch (Exception $e) {
            error_log("Error creando tabla notificaciones_email: " . $e->getMessage());
        }
    }
}
