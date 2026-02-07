# Guía Rápida: Configurar n8n Workflow

## 🎯 Objetivo
Crear un workflow en n8n que reciba la petición de tu aplicación PHP y envíe un correo HTML elegante.

---

## 📋 Pasos Rápidos

### 1️⃣ Crear Nuevo Workflow en n8n

1. Inicia sesión en tu cuenta de n8n
2. Haz clic en **"New Workflow"**
3. Dale un nombre: `Enviar Correo Reserva Confirmada`

---

### 2️⃣ Agregar Nodo Webhook

1. Haz clic en el botón **"+"** para agregar un nodo
2. Busca y selecciona **"Webhook"**
3. Configura:
   ```
   Webhook URLs: Production URL
   HTTP Method: POST
   Path: enviar-correo-reserva
   Authentication: None (o configura según necesites)
   Response Mode: When Last Node Finishes
   Response Data: First Entry JSON
   ```

4. **Copia la URL del webhook** que aparece (ejemplo: `https://tu-n8n.app.n8n.cloud/webhook/enviar-correo-reserva`) http://localhost:5678/webhook-test/enviar-correo-reserva


---

### 3️⃣ Agregar Nodo de Email (Elige tu proveedor)

#### Opción A: Gmail (Más Fácil) 📧

1. Agrega un nuevo nodo → busca **"Gmail"**
2. Haz clic en **"Connect to Gmail"**
3. Autoriza tu cuenta de Gmail
4. Configura:
   ```
   Resource: Message
   Operation: Send
   To: {{ $json.to }}
   Subject: {{ $json.subject }}
   Email Type: HTML
   Message (HTML): {{ $json.html }}
   Sender Name (optional): {{ $json.from_name }}
   ```

#### Opción B: SendGrid (Profesional) 📨

1. Agrega un nuevo nodo → busca **"SendGrid"**
2. Crea credenciales:
   - API Key: Tu API key de SendGrid
3. Configura:
   ```
   Resource: Email
   Operation: Send
   To Email: {{ $json.to }}
   From Email: {{ $json.from }}
   From Name: {{ $json.from_name }}
   Subject: {{ $json.subject }}
   Content Type: text/html
   Content: {{ $json.html }}
   ```

#### Opción C: SMTP Genérico (Universal) 📮

1. Agrega un nuevo nodo → busca **"Email"** (Send Email - SMTP)
2. Configura credenciales SMTP:
   ```
   User: tu-email@example.com
   Password: tu-contraseña
   Host: smtp.example.com
   Port: 587
   Secure: true
   ```
3. Configura el mensaje:
   ```
   From Email: {{ $json.from }}
   From Name: {{ $json.from_name }}
   To Email: {{ $json.to }}
   Subject: {{ $json.subject }}
   Email Format: HTML
   Text: {{ $json.html }}
   ```

---

### 4️⃣ Agregar Nodo de Respuesta

1. Agrega un nuevo nodo → busca **"Respond to Webhook"**
2. Configura:
   ```
   Response Body: JSON
   
   En el campo JSON escribe:
   {
     "success": true,
     "message": "Correo enviado exitosamente",
     "reserva_id": "{{ $json.reserva_id }}"
   }
   ```

---

### 5️⃣ Conectar los Nodos

Conecta en este orden:
```
Webhook → Gmail/SendGrid/SMTP → Respond to Webhook
```

Debería verse así:
```
[Webhook] → [Gmail] → [Respond to Webhook]
```

---

### 6️⃣ Activar el Workflow

1. Haz clic en el switch **"Inactive"** en la esquina superior derecha
2. Cambiará a **"Active"** con color verde ✅

---

### 7️⃣ Configurar la Aplicación PHP

1. Edita tu archivo `.env` o `config/n8n_config.php`
2. Pega la URL del webhook:
   ```php
   'webhook_url' => 'https://tu-n8n.app.n8n.cloud/webhook/enviar-correo-reserva',
   ```

---

## 🧪 Probar el Workflow

### Método 1: Desde n8n

1. En el nodo Webhook, haz clic en **"Listen for Test Event"**
2. Desde tu aplicación, confirma una reserva
3. Deberías ver los datos llegar a n8n

### Método 2: Desde cURL

```bash
curl -X POST https://tu-n8n.app.n8n.cloud/webhook/enviar-correo-reserva \
-H "Content-Type: application/json" \
-d '{
  "to": "cliente@example.com",
  "to_name": "Juan Pérez",
  "from": "noreply@turestaurante.com",
  "from_name": "Tu Restaurante",
  "subject": "Test de Correo",
  "html": "<h1>Hola Mundo</h1><p>Este es un test</p>",
  "tipo": "reserva_confirmada",
  "reserva_id": 123
}'
```

### Método 3: Desde la Aplicación

Confirma una reserva desde el panel de administración y verifica que llegue el correo.

---

## 🎨 Workflow Completo Visual

```
┌──────────────────┐
│     Webhook      │ ← Recibe petición desde PHP
│  (POST /enviar-  │
│  correo-reserva) │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│   Gmail/SendGrid │ ← Envía el correo HTML
│   Send Email     │
└────────┬─────────┘
         │
         ▼
┌──────────────────┐
│   Respond to     │ ← Responde a PHP
│     Webhook      │
└──────────────────┘
```

---

## 🔧 Configuración Avanzada (Opcional)

### Agregar Validación de Datos

Después del Webhook, agrega un nodo **"IF"**:
```
Condiciones:
- {{ $json.to }} is not empty
- {{ $json.subject }} is not empty
- {{ $json.html }} is not empty
```

### Guardar Log en Google Sheets

Agrega un nodo **"Google Sheets"** en paralelo:
```
Operation: Append
Spreadsheet: Logs de Correos
Sheet: Reservas
Values to Send: Manual Mapping
- Fecha: {{ $now.toISO() }}
- Reserva ID: {{ $json.reserva_id }}
- Destinatario: {{ $json.to }}
- Estado: Enviado
```

### Notificación en Slack

Agrega un nodo **"Slack"**:
```
Operation: Send Message
Channel: #notificaciones
Text: 
Correo enviado ✅
Reserva: {{ $json.reserva_id }}
Para: {{ $json.to }}
```

---

## ⚠️ Solución de Problemas Comunes

### El workflow no se activa

- ✅ Verifica que el switch esté en **"Active"**
- ✅ Revisa que la URL del webhook sea correcta
- ✅ Comprueba que n8n esté funcionando

### Los correos no llegan

- ✅ Verifica la bandeja de spam
- ✅ Comprueba las credenciales del servicio de correo
- ✅ Revisa los límites de envío (Gmail: 500/día)
- ✅ Verifica que el email "from" esté autorizado

### Error de conexión desde PHP

- ✅ Verifica que la URL del webhook sea accesible
- ✅ Comprueba el firewall/CORS
- ✅ Revisa los logs de PHP

---

## 📊 Datos de Prueba

Usa este JSON para probar tu workflow:

```json
{
  "to": "tu-email@example.com",
  "to_name": "Cliente de Prueba",
  "from": "noreply@lesalondelumiere.com",
  "from_name": "Le Salon de Lumière",
  "subject": "✅ Reserva Confirmada - Prueba",
  "html": "<html><body><h1>¡Reserva Confirmada!</h1><p>Esta es una prueba del sistema de correos.</p><p><strong>Fecha:</strong> 15 de Febrero 2026</p><p><strong>Hora:</strong> 19:00</p><p><strong>Mesa:</strong> #5</p></body></html>",
  "tipo": "reserva_confirmada",
  "reserva_id": 999
}
```

---

## ✅ Checklist Final

- [ ] Workflow creado y nombrado
- [ ] Nodo Webhook configurado
- [ ] Nodo de Email configurado (Gmail/SendGrid/SMTP)
- [ ] Credenciales de email añadidas
- [ ] Nodo Respond to Webhook agregado
- [ ] Nodos conectados correctamente
- [ ] Workflow activado (switch verde)
- [ ] URL del webhook copiada
- [ ] PHP configurado con la URL
- [ ] Prueba realizada exitosamente

---

**¡Listo! Tu sistema de correos HTML con n8n está funcionando** 🎉

Cualquier pregunta, revisa la documentación completa en `N8N_EMAIL_CONFIGURATION.md`
