# Configuración de n8n para Envío de Correos HTML

## 📧 Sistema de Notificaciones por Correo Electrónico

Este sistema permite enviar correos HTML elegantes a los clientes cuando se confirma una reserva, utilizando n8n como plataforma de automatización.

---

## 🚀 Características

- ✅ Correos HTML profesionales y responsivos
- ✅ Envío automático al confirmar reservas
- ✅ Integración con n8n mediante webhooks
- ✅ Registro de todos los correos enviados
- ✅ Modo de prueba para desarrollo
- ✅ Plantillas personalizables

---

## 📋 Requisitos

1. **Cuenta de n8n** (cloud o self-hosted)
2. **Servicio de correo** (Gmail, SendGrid, AWS SES, etc.)
3. **PHP 7.4+** con cURL habilitado
4. **MySQL** para registro de envíos

---

## ⚙️ Configuración

### 1. Configurar Variables de Entorno

Agrega estas variables a tu archivo `.env`:

```bash
# Configuración n8n
N8N_WEBHOOK_URL=https://tu-instancia-n8n.com/webhook/enviar-correo-reserva
N8N_AUTO_SEND_ENABLED=true
N8N_TEST_MODE=false
N8N_TIMEOUT=10

# Configuración de correo
FROM_EMAIL=noreply@lesalondelumiere.com
FROM_NAME=Le Salon de Lumière

# Información del restaurante
RESTAURANT_NAME=Le Salon de Lumière
RESTAURANT_PHONE=+593999999999
RESTAURANT_ADDRESS=Av. Principal 123, Quito, Ecuador
RESTAURANT_WEBSITE=https://www.lesalondelumiere.com
RESTAURANT_LOGO=https://www.lesalondelumiere.com/assets/img/logo.png
```

### 2. Configurar el Archivo de Configuración

Edita `config/n8n_config.php` con tus valores o usa las variables de entorno.

```php
<?php
return [
    'webhook_url' => 'https://tu-n8n.com/webhook/enviar-correo-reserva',
    'from_email' => 'noreply@turestaurante.com',
    'from_name' => 'Tu Restaurante',
    // ... más configuraciones
];
```

---

## 🔧 Configuración de n8n Workflow

### Paso 1: Crear Webhook en n8n

1. Abre n8n y crea un nuevo workflow
2. Agrega un nodo **Webhook**
3. Configura:
   - **Path**: `enviar-correo-reserva`
   - **Method**: POST
   - **Response Mode**: When Last Node Finishes
   - **Response Data**: First Entry JSON

### Paso 2: Configurar Nodo de Email

Agrega un nodo de email según tu proveedor:

#### Opción A: Gmail

```
Nodo: Gmail
Operación: Send Email
- To: {{ $json.to }}
- Subject: {{ $json.subject }}
- Email Type: HTML
- Message: {{ $json.html }}
```

#### Opción B: SendGrid

```
Nodo: SendGrid
Operación: Send Email
- To Email: {{ $json.to }}
- From Email: {{ $json.from }}
- Subject: {{ $json.subject }}
- Content Type: text/html
- Content: {{ $json.html }}
```

#### Opción C: AWS SES

```
Nodo: AWS SES
Operación: Send Email
- To Addresses: {{ $json.to }}
- From Email: {{ $json.from }}
- Subject: {{ $json.subject }}
- Body (HTML): {{ $json.html }}
```

### Paso 3: Respuesta del Webhook

Agrega un nodo **Respond to Webhook** al final:

```json
{
  "success": true,
  "message": "Correo enviado exitosamente",
  "reserva_id": "{{ $json.reserva_id }}"
}
```

### Ejemplo de Workflow Completo

```
Webhook → Gmail/SendGrid/SES → Respond to Webhook
```

---

## 📤 Estructura del Payload

El sistema envía este JSON a n8n:

```json
{
  "to": "cliente@example.com",
  "to_name": "Juan Pérez",
  "from": "noreply@lesalondelumiere.com",
  "from_name": "Le Salon de Lumière",
  "subject": "✅ Reserva Confirmada - Le Salon de Lumière",
  "html": "<html>...</html>",
  "tipo": "reserva_confirmada",
  "reserva_id": 123
}
```

---

## 🎨 Personalizar Plantillas HTML

Las plantillas están en `templates/email_reserva_confirmada.php`

### Modificar Colores

```php
// Cambiar el gradiente del header
.header {
    background: linear-gradient(135deg, #TU_COLOR1 0%, #TU_COLOR2 100%);
}

// Cambiar color de botones
.cta-button {
    background: #TU_COLOR;
}
```

### Agregar Logo

```php
$restaurantLogo = 'https://tu-dominio.com/logo.png';
```

---

## 🧪 Pruebas

### Modo de Prueba

Activa el modo de prueba en `config/n8n_config.php`:

```php
'test_mode' => true,
```

Esto registrará los correos en la base de datos sin enviarlos realmente.

### Probar Envío Manual

```bash
curl -X POST http://localhost/PRY_PROYECTO/app/api/enviar_correo.php \
  -H "Content-Type: application/json" \
  -d '{"reserva_id": 1}'
```

### Verificar Registro de Correos

```sql
SELECT * FROM notificaciones_email 
ORDER BY fecha_envio DESC 
LIMIT 10;
```

---

## 📊 Base de Datos

La tabla `notificaciones_email` se crea automáticamente:

```sql
CREATE TABLE notificaciones_email (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reserva_id INT NOT NULL,
    correo VARCHAR(255) NOT NULL,
    tipo_email VARCHAR(50) NOT NULL,
    mensaje TEXT,
    estado ENUM('enviado', 'fallido', 'test') NOT NULL,
    fecha_envio DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🔍 Flujo de Envío

1. **Admin confirma reserva** → `confirmar_reserva_admin.php`
2. **Se actualiza estado** a "confirmada"
3. **Se envía WhatsApp** (sistema existente)
4. **Se envía correo HTML** vía n8n
5. **Se registra en BD** el resultado

---

## 🛠️ Solución de Problemas

### Error: "Webhook URL not configured"

- Verifica que `N8N_WEBHOOK_URL` esté configurado
- Revisa `config/n8n_config.php`

### Error: "Connection timeout"

- Aumenta el timeout en la configuración
- Verifica que n8n esté accesible

### Error: "Cliente sin correo electrónico"

- Asegúrate de que los clientes tengan correo en la BD
- Verifica el campo `correo` en la tabla `clientes`

### Los correos no llegan

1. Verifica el workflow de n8n
2. Revisa los logs de n8n
3. Comprueba la configuración de tu proveedor de email
4. Verifica que el correo no esté en spam

---

## 📝 Logs y Monitoreo

### Ver Correos Enviados

```php
<?php
require_once 'conexion/db.php';

$stmt = $pdo->query("
    SELECT ne.*, r.fecha, r.hora, c.nombre, c.apellido
    FROM notificaciones_email ne
    LEFT JOIN reservas r ON ne.reserva_id = r.id
    LEFT JOIN clientes c ON r.cliente_id = c.id
    ORDER BY ne.fecha_envio DESC
    LIMIT 20
");

$correos = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($correos);
```

### Estadísticas

```sql
-- Total de correos por estado
SELECT estado, COUNT(*) as total 
FROM notificaciones_email 
GROUP BY estado;

-- Correos del último mes
SELECT DATE(fecha_envio) as fecha, COUNT(*) as total
FROM notificaciones_email
WHERE fecha_envio >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
GROUP BY DATE(fecha_envio);
```

---

## 🎯 Tipos de Correo

### Actualmente Implementados

- ✅ **Reserva Confirmada** - Cuando el admin confirma una reserva
- ✅ **Reserva Modificada** - Cuando se edita una reserva existente

### Por Implementar (Opcional)

- 🔲 Reserva Cancelada
- 🔲 Recordatorio 24h antes
- 🔲 Encuesta post-visita

---

## 🔐 Seguridad

- ✅ Validación de datos de entrada
- ✅ Uso de prepared statements
- ✅ HTTPS recomendado para webhooks
- ✅ Timeout configurables
- ✅ Registro de todos los intentos

---

## 📞 Soporte

Para problemas o preguntas:
1. Revisa los logs de errores PHP
2. Revisa los logs de n8n
3. Verifica la tabla `notificaciones_email`
4. Contacta al administrador del sistema

---

## 📄 Archivos Relacionados

- `config/n8n_config.php` - Configuración principal
- `controllers/EmailController.php` - Lógica de envío
- `templates/email_reserva_confirmada.php` - Plantilla HTML
- `app/api/enviar_correo.php` - API endpoint
- `app/api/confirmar_reserva_admin.php` - Integración

---

**¡Sistema listo para usar!** 🎉
