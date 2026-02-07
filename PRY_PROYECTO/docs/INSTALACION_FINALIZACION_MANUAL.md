# ================================================
# GUÍA DE INSTALACIÓN - SISTEMA DE FINALIZACIÓN MANUAL
# ================================================
# Fecha: 4 de Febrero 2026
# Descripción: Instalación de mejoras para finalización manual de reservas

## 📋 PRERREQUISITOS

- XAMPP/LAMPP funcionando
- MySQL/MariaDB activo
- Base de datos `crud_proyecto` existente
- Acceso a terminal/consola

---

## 🚀 PASO 1: EJECUTAR SCRIPT SQL

### Opción A: Desde Terminal (Recomendado)

```bash
cd /opt/lampp/htdocs/MODELOS-DE-PROCESOS/PRY_PROYECTO

# Ejecutar script
mysql -u root -p crud_proyecto < sql/mejoras_reservas_finalizacion.sql

# O si tienes contraseña:
mysql -u crud_proyecto -p12345 crud_proyecto < sql/mejoras_reservas_finalizacion.sql
```

### Opción B: Desde phpMyAdmin

1. Abrir phpMyAdmin: `http://localhost/phpmyadmin`
2. Seleccionar base de datos `crud_proyecto`
3. Ir a pestaña "SQL"
4. Copiar y pegar contenido de `sql/mejoras_reservas_finalizacion.sql`
5. Click en "Continuar"

### ✅ Verificación

```sql
-- Verificar que se agregaron los campos
DESC reservas;

-- Deberías ver estos nuevos campos:
-- duracion_estimada
-- cliente_llego
-- hora_llegada
-- hora_finalizacion
-- finalizada_por
-- observaciones_finalizacion
-- notificacion_noshow_enviada
```

---

## ⚙️ PASO 2: CONFIGURAR VARIABLES DE ENTORNO

Editar archivo `.env`:

```bash
nano .env
```

Agregar las siguientes líneas:

```env
# Notificaciones de No-Show
N8N_WEBHOOK_NOSHOW=http://localhost:5678/webhook/reserva-noshow
ADMIN_EMAIL=admin@lesalondelumiere.com
ADMIN_NAME=Administrador
ADMIN_PHONE=+593999999999
```

---

## 🔧 PASO 3: CONFIGURAR N8N (SOLO EMAIL +15MIN)

### 1. Crear Workflow en N8N

**Nombre:** Notificación No-Show Reservas

**Trigger:** Webhook
- URL: `http://localhost:5678/webhook/reserva-noshow`
- Método: POST
- Authentication: None

### 2. Nodo Send Email

**Configuración:**
```json
{
  "to": "{{$json.destinatario}}",
  "subject": "{{$json.asunto}}",
  "text": "Reserva #{{$json.reserva_id}} - Cliente NO ha llegado",
  "html": "
    <h2>⚠️ ALERTA - Cliente NO ha llegado</h2>
    
    <h3>Reserva en Curso</h3>
    <ul>
      <li><strong>Reserva #:</strong> {{$json.reserva_id}}</li>
      <li><strong>Mesa:</strong> {{$json.mesa}} ({{$json.zona}})</li>
      <li><strong>Cliente:</strong> {{$json.cliente_nombre}}</li>
      <li><strong>Teléfono:</strong> {{$json.cliente_telefono}}</li>
      <li><strong>Hora programada:</strong> {{$json.hora}}</li>
      <li><strong>Hora actual:</strong> {{$json.hora_actual}}</li>
      <li><strong>Retraso:</strong> {{$json.minutos_retraso}} minutos</li>
      <li><strong>Personas:</strong> {{$json.personas}}</li>
    </ul>
    
    <h3>🎯 ¿Llegaron los clientes?</h3>
    <p>Han pasado <strong>15 minutos</strong> desde la hora de la reserva.</p>
    
    <h4>Acciones sugeridas:</h4>
    <ol>
      <li>Llamar al cliente: <strong>{{$json.cliente_telefono}}</strong></li>
      <li>Si NO contestó o NO viene: <strong>Cancelar la reserva</strong></li>
      <li>Si está en camino: <strong>Marcar como 'llegando'</strong></li>
      <li>Si ya llegó: <strong>Marcar como llegado en el panel</strong></li>
    </ol>
    
    <p>
      <a href='http://localhost/PRY_PROYECTO/admin.php#reservas' 
         style='background:#d4af37; color:#fff; padding:10px 20px; text-decoration:none; display:inline-block; margin:10px 5px;'>
        Ir al Panel Admin
      </a>
    </p>
  "
}
```

### 3. Activar Workflow

Click en "Active" (switch en la esquina superior derecha)

---

## 🕐 PASO 4: CONFIGURAR CRON JOB

Para que las notificaciones se envíen automáticamente cada 5 minutos:

```bash
# Editar crontab
crontab -e

# Agregar esta línea:
*/5 * * * * /usr/bin/php /opt/lampp/htdocs/MODELOS-DE-PROCESOS/PRY_PROYECTO/scripts/enviar_notificaciones_noshow.php >> /opt/lampp/htdocs/MODELOS-DE-PROCESOS/PRY_PROYECTO/logs/noshow.log 2>&1
```

**Alternativa manual:** Ejecutar desde navegador cada 5-10 minutos:
```
http://localhost/PRY_PROYECTO/scripts/enviar_notificaciones_noshow.php
```

---

## 📱 PASO 5: ACTUALIZAR PANEL ADMIN

Los nuevos endpoints ya están creados:

✅ `/app/obtener_reservas_activas.php` - Lista reservas en curso  
✅ `/app/finalizar_reserva_manual.php` - Finalizar manualmente  
✅ `/app/marcar_cliente_llego.php` - Marcar llegada  

**Nota:** La interfaz visual se actualizará en el siguiente paso.

---

## 🧪 PASO 6: PROBAR EL SISTEMA

### Prueba 1: Auto-estados

```sql
-- Crear reserva de prueba para HOY a la hora actual + 30 min
INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, num_personas, estado, duracion_estimada)
VALUES (1, 1, CURDATE(), ADDTIME(CURTIME(), '00:30:00'), 4, 'confirmada', 120);

-- Esperar 30 minutos o cambiar manualmente:
UPDATE reservas SET hora_reserva = SUBTIME(CURTIME(), '00:05:00') WHERE id = LAST_INSERT_ID();

-- Ejecutar procedimiento:
CALL activar_reservas_programadas();

-- Verificar que cambió a 'en_curso':
SELECT id, estado, hora_reserva FROM reservas ORDER BY id DESC LIMIT 1;
```

### Prueba 2: Notificación No-Show

```sql
-- Crear reserva hace 20 minutos
INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, num_personas, estado, cliente_llego, notificacion_noshow_enviada)
VALUES (1, 2, CURDATE(), SUBTIME(CURTIME(), '00:20:00'), 2, 'en_curso', 0, 0);

-- Ejecutar script de notificaciones:
```

Visitar: `http://localhost/PRY_PROYECTO/scripts/enviar_notificaciones_noshow.php`

Deberías recibir un email en ADMIN_EMAIL.

### Prueba 3: Finalización Manual

```bash
curl -X POST http://localhost/PRY_PROYECTO/app/finalizar_reserva_manual.php \
  -H "Content-Type: application/json" \
  -d '{"reserva_id": 1, "tipo_reserva": "normal", "observaciones": "Cliente satisfecho"}'
```

### Prueba 4: Bloqueo de Mesas (3 horas)

```sql
-- Intentar reservar mesa 1 a las 19:00
-- Luego intentar reservar la misma mesa a las 21:00
-- Debería PERMITIR (más de 3 horas)

-- Intentar reservar a las 20:30
-- Debería RECHAZAR (menos de 3 horas)
```

---

## ✅ VERIFICACIÓN FINAL

### Checklist de Instalación

- [ ] Script SQL ejecutado sin errores
- [ ] Nuevos campos visibles en tabla `reservas`
- [ ] Procedimiento `activar_reservas_programadas()` creado
- [ ] Variables de entorno configuradas
- [ ] Workflow N8N creado y activo
- [ ] Cron job configurado (opcional)
- [ ] Pruebas de estados automáticos funcionando
- [ ] Prueba de notificación no-show funcionando
- [ ] Prueba de finalización manual funcionando
- [ ] Bloqueo de 3 horas entre reservas funcionando

---

## 🔍 TROUBLESHOOTING

### Error: "Unknown column 'duracion_estimada'"

**Solución:** El script SQL no se ejecutó correctamente.
```sql
-- Ejecutar manualmente:
ALTER TABLE reservas ADD COLUMN duracion_estimada INT DEFAULT 120;
```

### No llegan emails

**Revisar:**
1. N8N está corriendo: `http://localhost:5678`
2. Workflow está activo (switch verde)
3. URL del webhook correcta en `.env`
4. Verificar logs de N8N

### Estados no cambian automáticamente

**Revisar:**
```sql
-- Ver si el procedimiento existe:
SHOW PROCEDURE STATUS WHERE Name = 'activar_reservas_programadas';

-- Ejecutar manualmente:
CALL activar_reservas_programadas();

-- Ver logs de errores:
SHOW WARNINGS;
```

### Cron job no ejecuta

```bash
# Verificar que cron está corriendo:
sudo service cron status

# Ver logs de cron:
tail -f /var/log/syslog | grep CRON

# Probar manualmente:
/usr/bin/php /ruta/scripts/enviar_notificaciones_noshow.php
```

---

## 📊 MONITOREO

### Ver reservas que necesitan atención

```sql
SELECT 
    r.id,
    m.numero_mesa,
    c.nombre,
    r.hora_reserva,
    r.estado,
    r.cliente_llego,
    TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) as minutos
FROM reservas r
JOIN mesas m ON r.mesa_id = m.id
JOIN clientes c ON r.cliente_id = c.id
WHERE r.estado = 'en_curso'
AND r.cliente_llego = 0
AND TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) > 15;
```

### Ver notificaciones enviadas

```sql
SELECT 
    r.id,
    r.estado,
    r.notificacion_noshow_enviada,
    r.cliente_llego,
    r.hora_reserva
FROM reservas r
WHERE r.notificacion_noshow_enviada = 1
ORDER BY r.id DESC
LIMIT 10;
```

---

## 🆘 SOPORTE

Si encuentras problemas:

1. Verificar logs: `/logs/noshow.log`
2. Revisar errores PHP: `/opt/lampp/logs/error_log`
3. Verificar N8N: `http://localhost:5678` → Executions
4. Consultar documentación: `/docs/`

---

**✅ Instalación completada con éxito!**
