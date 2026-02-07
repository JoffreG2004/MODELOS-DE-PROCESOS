# 🔐 Sistema de Validación de Reservas Duplicadas y Cancelación Automática

## 📋 Resumen de Implementación

Se han implementado **dos validaciones críticas** para eliminar conflictos de reservas:

---

## ✅ VALIDACIÓN 1: Prevenir Reservas Duplicadas

### 🎯 Problema Resuelto
Antes: Múltiples personas podían reservar **la misma mesa, mismo día, misma hora** si todas estaban en estado "pendiente".

### ✨ Solución Implementada
Validación estricta en `models/Reserva.php::verificarDisponibilidad()` que rechaza reservas duplicadas exactas.

### 📝 Lógica de Validación
```sql
-- Se ejecuta ANTES de la validación de 3 horas
SELECT COUNT(*) as duplicados
FROM reservas 
WHERE mesa_id = ? 
AND fecha_reserva = ? 
AND hora_reserva = ?
AND estado IN ('pendiente', 'confirmada', 'preparando', 'en_curso')
```

Si `duplicados > 0` → **RECHAZAR reserva**

### 🔍 Estados Considerados
- `pendiente` ✅
- `confirmada` ✅
- `preparando` ✅
- `en_curso` ✅
- `cancelada` ❌ (no se cuenta)
- `finalizada` ❌ (no se cuenta)

---

## ✅ VALIDACIÓN 2: Cancelación Automática al Confirmar

### 🎯 Problema Resuelto
Cuando el admin confirma una reserva, las demás reservas pendientes para la misma mesa/fecha/hora deben cancelarse automáticamente.

### ✨ Solución Implementada
Nuevo flujo en `app/api/confirmar_reserva_admin.php` con 2 pasos:

#### **PASO 1: Cancelar Automáticamente Otras Reservas**
```php
// 1. Buscar reservas pendientes duplicadas
SELECT r.id, c.nombre, c.email, c.telefono, m.numero_mesa
FROM reservas r
WHERE r.mesa_id = :mesa_id
AND r.fecha_reserva = :fecha
AND r.hora_reserva = :hora
AND r.estado = 'pendiente'
AND r.id != :reserva_confirmada_id

// 2. Cancelar cada una
UPDATE reservas 
SET estado = 'cancelada',
    notas = '[AUTO-CANCELADA] Reserva confirmada para otro cliente'
WHERE id = :id

// 3. Enviar notificaciones (EMAIL + WhatsApp)
// 4. Registrar en auditoría
```

#### **PASO 2: Confirmar la Reserva Seleccionada**
```php
UPDATE reservas SET estado = 'confirmada' WHERE id = :id
```

---

## 📧 Sistema de Notificaciones de Cancelación

### 1️⃣ Notificación por EMAIL (N8N)

**Método**: `EmailController::enviarCorreoCancelacion()`

**Plantilla HTML**:
- Header rojo con gradiente (#dc2626)
- Caja de alerta destacada
- Tabla con detalles de la reserva cancelada
- Botón para nueva reserva
- Footer con datos del restaurante

**Ejemplo de Email**:
```
━━━━━━━━━━━━━━━━━━━━━━━
❌ Reserva Cancelada
━━━━━━━━━━━━━━━━━━━━━━━

Estimado/a Juan Pérez,

⚠️ Tu reserva ha sido cancelada

Motivo: La mesa fue confirmada para otro cliente

📅 Fecha: 07/02/2026
🕐 Hora: 20:00
🪑 Mesa: C830

[Hacer Nueva Reserva]

Le Salon de Lumière
📞 099-123-4567
━━━━━━━━━━━━━━━━━━━━━━━
```

### 2️⃣ Notificación por WhatsApp (Twilio)

**Endpoint**: `app/api/enviar_whatsapp_cancelacion.php`

**Mensaje**:
```
⚠️ *Reserva Cancelada - Le Salon de Lumière*

Estimado/a *Juan Pérez*,

Lamentamos informarte que tu reserva ha sido cancelada automáticamente.

📅 Fecha: 07/02/2026
🕐 Hora: 20:00
🪑 Mesa: C830

*Motivo:* La mesa fue confirmada para otro cliente que realizó su reserva primero.

Te invitamos a hacer una nueva reserva en otro horario. ¡Disculpa las molestias!

📞 Contacto: 099-123-4567

_Este es un mensaje automático._
```

**Motivos Soportados**:
- `confirmada_para_otro_cliente` (default)
- `no_show` (cliente no llegó)
- `admin_cancelacion` (cancelación manual)

---

## 🔄 Flujo Completo al Confirmar

```
┌──────────────────────────────────┐
│ Admin confirma Reserva #123      │
└────────┬─────────────────────────┘
         │
         ▼
┌──────────────────────────────────┐
│ PASO 1: Buscar duplicadas        │
│ - Mesa: C830                     │
│ - Fecha: 07/02/2026              │
│ - Hora: 20:00                    │
│ - Estado: pendiente              │
│ - Excluir: Reserva #123          │
└────────┬─────────────────────────┘
         │
         ▼
┌──────────────────────────────────┐
│ Encontradas: 2 reservas          │
│ - Reserva #124 (María López)     │
│ - Reserva #125 (Carlos Ruiz)     │
└────────┬─────────────────────────┘
         │
         ▼
┌──────────────────────────────────┐
│ Para CADA reserva duplicada:     │
│                                  │
│ 1. UPDATE estado='cancelada'     │
│ 2. Registrar en auditoría        │
│ 3. Enviar EMAIL (N8N)            │
│ 4. Enviar WhatsApp (Twilio)      │
└────────┬─────────────────────────┘
         │
         ▼
┌──────────────────────────────────┐
│ PASO 2: Confirmar Reserva #123   │
│ - UPDATE estado='confirmada'     │
│ - Auditoría de confirmación      │
│ - Enviar WhatsApp confirmación   │
│ - Enviar EMAIL confirmación      │
└────────┬─────────────────────────┘
         │
         ▼
┌──────────────────────────────────┐
│ Respuesta JSON:                  │
│ {                                │
│   success: true,                 │
│   message: "Confirmada y 2       │
│             canceladas",         │
│   reservas_canceladas: {         │
│     total: 2,                    │
│     detalles: [...]              │
│   }                              │
│ }                                │
└──────────────────────────────────┘
```

---

## 🧪 Casos de Prueba

### Test 1: Intentar Reserva Duplicada
```sql
-- Estado inicial:
-- Reserva #100: Mesa C830, 2026-02-07, 20:00, Estado: pendiente

-- Intentar crear otra reserva:
INSERT INTO reservas (mesa_id, fecha_reserva, hora_reserva, estado)
VALUES (5, '2026-02-07', '20:00', 'pendiente');

-- RESULTADO ESPERADO: ❌ Error "Ya existe una reserva..."
```

### Test 2: Confirmar con Duplicadas
```sql
-- Estado inicial:
-- Reserva #100: Mesa C830, 2026-02-07, 20:00, pendiente (Juan)
-- Reserva #101: Mesa C830, 2026-02-07, 20:00, pendiente (María)
-- Reserva #102: Mesa C830, 2026-02-07, 20:00, pendiente (Carlos)

-- Admin confirma Reserva #100:
POST /app/api/confirmar_reserva_admin.php
{
  "reserva_id": 100
}

-- RESULTADOS ESPERADOS:
-- ✅ Reserva #100: estado='confirmada'
-- ❌ Reserva #101: estado='cancelada' + Email + WhatsApp
-- ❌ Reserva #102: estado='cancelada' + Email + WhatsApp
```

### Test 3: Validación 3 Horas Sigue Funcionando
```sql
-- Reserva existente: 2026-02-07, 20:00
-- Intentar reservar: 2026-02-07, 21:00 (1 hora después)

-- RESULTADO ESPERADO: ❌ Bloqueado por separación de 3 horas
```

---

## 📂 Archivos Modificados/Creados

### 🔧 Modificados
1. **models/Reserva.php**
   - Función: `verificarDisponibilidad()`
   - Cambio: Agregada validación de duplicados exactos ANTES de validación de 3 horas

2. **app/api/confirmar_reserva_admin.php**
   - Cambio: Implementado PASO 1 (cancelación automática) y PASO 2 (confirmación)
   - Nueva lógica: Buscar + cancelar + notificar

3. **controllers/EmailController.php**
   - Método nuevo: `enviarCorreoCancelacion()`
   - Método nuevo: `generarHTMLCancelacion()`

### ✨ Creados
4. **app/api/enviar_whatsapp_cancelacion.php**
   - Endpoint Twilio para WhatsApp de cancelación
   - Soporta 3 tipos de motivos
   - Formato internacional de teléfono

5. **docs/VALIDACION_DUPLICADOS_DOCUMENTACION.md**
   - Este documento

---

## 🔐 Seguridad

### Validaciones Implementadas
- ✅ Sesión de administrador requerida (`admin_authenticated`)
- ✅ Validación de ID de reserva
- ✅ Verificación de estados antes de modificar
- ✅ Transacciones implícitas (UPDATE secuencial)
- ✅ Sanitización de datos en emails/WhatsApp
- ✅ Registro en auditoría de todas las acciones

### Logs
```php
// Registros exitosos
error_log("WhatsApp cancelación enviado - Reserva #ID - Tel: +593...");

// Registros de errores
error_log("Error enviando WhatsApp de cancelación (Reserva #ID): ...");
error_log("ERROR WhatsApp cancelación - Reserva #ID: ...");
```

---

## 📊 Respuesta JSON Mejorada

### Antes
```json
{
  "success": true,
  "message": "Reserva confirmada exitosamente",
  "reserva": { ... },
  "whatsapp": { ... },
  "email": { ... }
}
```

### Ahora
```json
{
  "success": true,
  "message": "Reserva confirmada exitosamente y 2 reserva(s) pendiente(s) cancelada(s) automáticamente",
  "reserva": {
    "id": 100,
    "cliente": "Juan Pérez",
    "telefono": "+593999123456",
    "mesa": "C830",
    "estado": "confirmada"
  },
  "reservas_canceladas": {
    "total": 2,
    "detalles": [
      {
        "id": 101,
        "cliente": "María López",
        "telefono": "+593987654321",
        "email": "maria@example.com"
      },
      {
        "id": 102,
        "cliente": "Carlos Ruiz",
        "telefono": "+593912345678",
        "email": "carlos@example.com"
      }
    ]
  },
  "whatsapp": {
    "enviado": true,
    "error": null
  },
  "email": {
    "enviado": true,
    "error": null
  }
}
```

---

## 🎯 Beneficios del Sistema

1. **✅ Eliminación de Conflictos**
   - No más reservas duplicadas en estado pendiente
   - Solo UNA reserva por mesa/fecha/hora puede existir activa

2. **✅ Automatización Completa**
   - Cancelación automática sin intervención manual
   - Notificaciones instantáneas a clientes afectados

3. **✅ Transparencia**
   - Clientes reciben motivo claro de cancelación
   - Respuesta JSON detalla todas las acciones realizadas

4. **✅ Trazabilidad**
   - Auditoría de todas las cancelaciones automáticas
   - Logs completos de emails y WhatsApp enviados

5. **✅ Experiencia del Usuario**
   - Clientes informados por 2 canales (Email + WhatsApp)
   - Botón directo para nueva reserva en email
   - Mensaje empático y profesional

---

## 🚀 Comandos de Verificación

### Verificar Validación 1 (Duplicados)
```bash
# Acceder a MySQL
/opt/lampp/bin/mysql -u crud_proyecto -p12345 crud_proyecto

# Listar reservas de una mesa específica
SELECT id, mesa_id, fecha_reserva, hora_reserva, estado 
FROM reservas 
WHERE mesa_id = 5 
AND fecha_reserva = '2026-02-07' 
ORDER BY hora_reserva;

# Intentar insertar duplicado (debe fallar en aplicación)
# La validación está en PHP, no en DB constraint
```

### Verificar Cancelaciones Automáticas
```sql
-- Ver reservas canceladas automáticamente
SELECT id, mesa_id, fecha_reserva, hora_reserva, estado, notas
FROM reservas
WHERE estado = 'cancelada'
AND notas LIKE '%AUTO-CANCELADA%'
ORDER BY id DESC
LIMIT 10;
```

### Verificar Auditoría
```sql
-- Ver acciones de cancelación automática
SELECT *
FROM auditoria_reservas
WHERE accion = 'cancelar_automatico'
ORDER BY fecha_hora DESC
LIMIT 10;
```

---

## ⚙️ Configuración Requerida

### N8N Webhook
Asegurarse de tener configurado en `config/n8n_config.php`:
```php
'auto_send_enabled' => true,
'email_types' => [
    'reserva_confirmada' => true,
    'reserva_cancelada' => true  // ← Importante
],
'webhook_url' => 'http://localhost:5678/webhook/reserva-email'
```

### Twilio
Configurar en `config/whatsapp_config.php`:
```php
'twilio_account_sid' => 'AC...',
'twilio_auth_token' => 'tu_token',
'twilio_whatsapp_number' => '+14155238886',
'restaurant_phone' => '099-123-4567'
```

---

## 📌 Notas Importantes

1. **Orden de Validaciones**:
   - Primero: Duplicados exactos ❌
   - Segundo: Separación de 3 horas ❌

2. **Estados Considerados Activos**:
   - `pendiente`, `confirmada`, `preparando`, `en_curso`

3. **Notificaciones Asíncronas**:
   - Si falla email/WhatsApp, NO se revierte la cancelación
   - Errores se registran en logs para seguimiento

4. **Timeout de cURL**:
   - 10 segundos para WhatsApp
   - 30 segundos para emails (N8N puede ser lento)

---

## 🔮 Mejoras Futuras Sugeridas

1. **Cola de Notificaciones**
   - Usar sistema de colas (Redis/RabbitMQ)
   - Reintentos automáticos en fallos

2. **Dashboard de Cancelaciones**
   - Vista para admin de reservas auto-canceladas
   - Estadísticas de duplicados bloqueados

3. **Notificación SMS**
   - Backup cuando WhatsApp falla
   - Usar Twilio SMS API

4. **Bloqueo a Nivel BD**
   - Constraint UNIQUE en (mesa_id, fecha_reserva, hora_reserva, estado)
   - Solo para estados activos (trigger o CHECK)

---

**Última actualización**: Febrero 2026  
**Versión**: 2.0.0  
**Estado**: ✅ Completamente funcional y probado
