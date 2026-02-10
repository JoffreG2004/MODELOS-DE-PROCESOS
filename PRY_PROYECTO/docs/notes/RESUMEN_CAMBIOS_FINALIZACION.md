# 📋 RESUMEN DE CAMBIOS - SISTEMA DE FINALIZACIÓN MANUAL

## 🎯 OBJETIVO
Implementar sistema flexible de finalización manual de reservas con:
- ✅ Auto-finalización después de 1 día (backup)
- ✅ Solo 1 email: +15 minutos (no-show alert)
- ✅ Bloqueo inteligente: 3 horas mínimo entre reservas
- ✅ Estado PREPARANDO: 1 hora antes (solo bloqueo)

---

## 📂 ARCHIVOS CREADOS/MODIFICADOS

### 1. Base de Datos
```
✅ sql/mejoras_reservas_finalizacion.sql
```
- Agrega 7 campos nuevos a `reservas` y `reservas_zonas`
- Modifica procedimiento `activar_reservas_programadas()`
- Crea vista `vista_reservas_activas`
- Auto-finaliza después de 24 horas

### 2. Modelo
```
✅ models/Reserva.php (modificado)
```
- Nueva función `verificarDisponibilidad()` con bloqueo de 3 horas
- Nueva función `verificarDisponibilidadConDetalles()`

### 3. Endpoints API
```
✅ app/finalizar_reserva_manual.php (nuevo)
✅ app/marcar_cliente_llego.php (nuevo)
✅ app/obtener_reservas_activas.php (nuevo)
```

### 4. Configuración
```
✅ config/notificaciones_config.php (nuevo)
```
- Solo notificación +15min habilitada
- Preparación y recordatorios DESHABILITADOS

### 5. Scripts
```
✅ scripts/enviar_notificaciones_noshow.php (nuevo)
✅ install_finalizacion.sh (nuevo)
```

### 6. Documentación
```
✅ docs/INSTALACION_FINALIZACION_MANUAL.md (nuevo)
```

---

## 🔄 FLUJO DE ESTADOS (NUEVO)

```
PENDIENTE
    ↓ (admin confirma)
CONFIRMADA
    ↓ (1 hora antes - AUTOMÁTICO)
PREPARANDO ← Mesa bloqueada, sin email
    ↓ (hora de reserva - AUTOMÁTICO)
EN_CURSO ← Email +15min si cliente no llegó
    ↓ (MANUAL por admin)
FINALIZADA
    ↓ (24h después - AUTO BACKUP)
FINALIZADA (por sistema)
```

---

## ⏰ TIEMPOS Y BLOQUEOS

| Evento | Tiempo | Acción | Email |
|--------|--------|--------|-------|
| **Preparación** | 1h antes | Mesa bloqueada | ❌ No |
| **Inicio** | Hora exacta | Estado EN_CURSO | ❌ No |
| **No-Show** | +15 min | Alerta admin | ✅ Sí |
| **Finalización** | Manual | Admin decide | ❌ No |
| **Auto-Finalizar** | +24 horas | Sistema limpia | ❌ No |

### Bloqueo de Mesas
- ⏱️ **3 horas mínimo** entre reservas de la misma mesa
- Incluye: 1h preparación + 2h reserva promedio
- Evita conflictos y da tiempo para limpieza

---

## 📧 EMAIL +15 MINUTOS (ÚNICO)

### Cuándo se envía:
- Reserva en estado `EN_CURSO`
- Cliente NO ha sido marcado como llegado
- Han pasado **exactamente 15 minutos**
- Email NO ha sido enviado previamente

### Contenido:
```
Asunto: ⚠️ ALERTA - Cliente NO ha llegado (Mesa X)

Contenido:
- Datos de reserva
- Teléfono del cliente
- Minutos de retraso
- Acciones sugeridas:
  1. Llamar al cliente
  2. Cancelar si no viene
  3. Marcar como llegado si aparece
```

### Destinatario:
- Email del admin (configurado en `.env`)

---

## 🗃️ NUEVOS CAMPOS EN BASE DE DATOS

### Tabla `reservas` y `reservas_zonas`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `duracion_estimada` | INT | Minutos estimados (120=2h, 1440=día) |
| `cliente_llego` | TINYINT | 0=No, 1=Sí |
| `hora_llegada` | DATETIME | Hora real de llegada |
| `hora_finalizacion` | DATETIME | Hora de finalización |
| `finalizada_por` | VARCHAR(100) | Usuario admin que finalizó |
| `observaciones_finalizacion` | TEXT | Notas al finalizar |
| `notificacion_noshow_enviada` | TINYINT | Control de email único |

---

## 🚀 INSTALACIÓN

### Opción A: Script Automático
```bash
cd /opt/lampp/htdocs/MODELOS-DE-PROCESOS/PRY_PROYECTO
bash install_finalizacion.sh
```

### Opción B: Manual
```bash
# 1. Ejecutar SQL
mysql -u root -p crud_proyecto < sql/mejoras_reservas_finalizacion.sql

# 2. Configurar .env
echo "N8N_WEBHOOK_NOSHOW=http://localhost:5678/webhook/reserva-noshow" >> .env
echo "ADMIN_EMAIL=tuadmin@email.com" >> .env

# 3. Configurar cron (cada 5 minutos)
crontab -e
# Agregar:
*/5 * * * * /usr/bin/php /ruta/scripts/enviar_notificaciones_noshow.php >> /ruta/logs/noshow.log 2>&1
```

---

## 🧪 PRUEBAS RÁPIDAS

### 1. Verificar Bloqueo de 3 Horas
```sql
-- Reservar mesa 1 a las 19:00
-- Intentar reservar misma mesa a las 21:00 → ✅ Debe permitir
-- Intentar reservar misma mesa a las 20:30 → ❌ Debe rechazar
```

### 2. Probar Auto-Estados
```sql
-- Crear reserva para dentro de 30 min
INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, num_personas, estado)
VALUES (1, 1, CURDATE(), ADDTIME(CURTIME(), '00:30:00'), 4, 'confirmada');

-- Esperar y ejecutar:
CALL activar_reservas_programadas();

-- Verificar que cambió a 'preparando' (1h antes) y luego 'en_curso'
```

### 3. Probar Notificación +15min
```bash
# Visitar:
http://localhost/PRY_PROYECTO/scripts/enviar_notificaciones_noshow.php

# Verificar email enviado
```

### 4. Probar Finalización Manual
```bash
curl -X POST http://localhost/PRY_PROYECTO/app/finalizar_reserva_manual.php \
  -H "Content-Type: application/json" \
  -d '{"reserva_id": 1, "tipo_reserva": "normal", "observaciones": "Todo OK"}'
```

---

## 🎛️ CONFIGURACIÓN N8N

### Workflow: Notificación No-Show

1. **Webhook Node**
   - URL: `/webhook/reserva-noshow`
   - Método: POST

2. **Send Email Node**
   - To: `{{$json.destinatario}}`
   - Subject: `{{$json.asunto}}`
   - HTML: Template con datos de reserva

3. **Activar Workflow**

---

## 📊 ENDPOINTS API

### GET /app/obtener_reservas_activas.php
Lista reservas EN_CURSO y PREPARANDO

**Parámetros opcionales:**
- `?zona=vip` - Filtrar por zona
- `?mesa=5` - Filtrar por mesa

**Respuesta:**
```json
{
  "success": true,
  "total": 3,
  "data": [
    {
      "id": 123,
      "mesa_id": 5,
      "cliente_nombre": "Juan",
      "estado": "en_curso",
      "estado_llegada": "no_llego",
      "minutos_transcurridos": 20,
      "tipo_reserva": "normal"
    }
  ]
}
```

### POST /app/finalizar_reserva_manual.php
Finaliza una reserva manualmente

**Body:**
```json
{
  "reserva_id": 123,
  "tipo_reserva": "normal",
  "observaciones": "Cliente satisfecho"
}
```

### POST /app/marcar_cliente_llego.php
Marca cliente como llegado

**Body:**
```json
{
  "reserva_id": 123,
  "tipo_reserva": "normal"
}
```

---

## ✅ CHECKLIST DE FUNCIONALIDADES

- [x] Auto-finalizar después de 24 horas
- [x] Email único +15 minutos (no-show)
- [x] Bloqueo 3 horas entre reservas
- [x] Estado PREPARANDO (1h antes)
- [x] Finalización manual por admin
- [x] Marcar cliente como llegado
- [x] Vista de reservas activas
- [x] Procedimiento almacenado optimizado
- [x] Script de instalación automática
- [x] Documentación completa

---

## 📚 DOCUMENTACIÓN ADICIONAL

- **Instalación:** `docs/INSTALACION_FINALIZACION_MANUAL.md`
- **Estados de reservas:** `docs/ESTADOS_RESERVAS.md`
- **N8N Config:** `docs/N8N_EMAIL_CONFIGURATION.md`

---

## 🔧 PRÓXIMOS PASOS

1. **Instalar sistema:**
   ```bash
   bash install_finalizacion.sh
   ```

2. **Configurar N8N:**
   - Crear workflow de notificación
   - Activar webhook

3. **Probar en ambiente de desarrollo:**
   - Crear reservas de prueba
   - Verificar emails
   - Probar finalización manual

4. **Actualizar interfaz admin** (próxima tarea):
   - Panel de reservas activas
   - Botones de finalización
   - Indicadores visuales

---

**✅ Sistema implementado y listo para instalación**

Fecha: 4 de Febrero 2026  
Versión: 1.0
