# 🔧 Solución: Mesas Bloqueadas Incorrectamente

## 🎯 Problema Identificado

**Reporte del Usuario**: "Las mesas se bloquean cuando intento reservar para días futuros (mañana, pasado mañana)"

**Causa Raíz**: El endpoint `app/api/mesas_estado.php` solo verificaba reservas para **HOY** (`CURDATE()`), mostrando el estado incorrecto cuando el usuario quería ver disponibilidad para fechas futuras.

---

## ✅ Solución Implementada

### 1. **Backend: Endpoint Dinámico** (`app/api/mesas_estado.php`)

#### Antes:
```php
LEFT JOIN reservas r ON m.id = r.mesa_id 
    AND DATE(r.fecha_reserva) = CURDATE()  // ← Solo HOY
    AND r.estado IN ('confirmada', 'pendiente', 'en_curso')
```

#### Ahora:
```php
// Acepta parámetros opcionales
$fecha_consulta = $_GET['fecha'] ?? date('Y-m-d');
$hora_consulta = $_GET['hora'] ?? null;

// JOIN dinámico según parámetros
LEFT JOIN reservas r ON m.id = r.mesa_id 
    AND DATE(r.fecha_reserva) = :fecha_consulta  // ← Fecha dinámica
    AND r.estado IN ('confirmada', 'pendiente', 'preparando', 'en_curso')
```

**Características**:
- ✅ Si NO se pasan parámetros → muestra estado HOY (comportamiento anterior)
- ✅ Si se pasa `fecha` → muestra disponibilidad para ESA fecha
- ✅ Si se pasa `fecha` + `hora` → valida disponibilidad EXACTA (incluye regla de 3 horas)

---

### 2. **Frontend: Selector de Fecha** (index.html)

#### Nuevo Componente:
```html
<div class="text-center mb-4">
    <label for="filtroFechaDisponibilidad">
        📅 Ver disponibilidad para:
    </label>
    <input 
        type="date" 
        id="filtroFechaDisponibilidad" 
        value="<?php echo date('Y-m-d'); ?>"
        min="<?php echo date('Y-m-d'); ?>">
    <small>
        💡 Selecciona una fecha para ver qué mesas están disponibles
    </small>
</div>
```

#### JavaScript Actualizado:
```javascript
// Función cargarMesas() ahora acepta parámetros
async cargarMesas(fecha = null, hora = null) {
    let url = 'app/api/mesas_estado.php';
    const params = new URLSearchParams();
    
    if (fecha) params.append('fecha', fecha);
    if (hora) params.append('hora', hora);
    
    if (params.toString()) url += '?' + params.toString();
    
    const response = await fetch(url);
    // ... renderizar mesas
}

// Event listener para cambio de fecha
filtroFecha.addEventListener('change', (e) => {
    this.cargarMesas(e.target.value);
});
```

---

### 3. **Mejoras Visuales**

#### Alerta de Fecha Consultada:
Cuando el usuario selecciona una fecha diferente a HOY, se muestra:

```
╔════════════════════════════════════════╗
║ 📅 Mostrando disponibilidad para:     ║
║    ▶ 08/02/2026 (todo el día)         ║
╚════════════════════════════════════════╝
```

---

## 🔄 Flujo Corregido

### Antes (Incorrecto):
```
Usuario selecciona: "08/02/2026"
          ↓
Sistema consulta: CURDATE() (06/02/2026)
          ↓
Muestra: "Mesa OCUPADA" ❌
(Porque HOY está ocupada, pero el 08/02 está libre)
```

### Ahora (Correcto):
```
Usuario selecciona: "08/02/2026"
          ↓
Sistema consulta: fecha = 08/02/2026
          ↓
Verifica reservas SOLO del 08/02/2026
          ↓
Muestra: "Mesa DISPONIBLE" ✅
```

---

## 📊 Ejemplos de Uso

### 1. Ver Disponibilidad HOY
```
GET app/api/mesas_estado.php
→ Muestra estado para fecha actual
```

### 2. Ver Disponibilidad para Mañana
```
GET app/api/mesas_estado.php?fecha=2026-02-07
→ Muestra qué mesas están libres el 07/02
```

### 3. Ver Disponibilidad Exacta (Fecha + Hora)
```
GET app/api/mesas_estado.php?fecha=2026-02-07&hora=20:00
→ Muestra mesas disponibles el 07/02 a las 20:00
→ Aplica regla de 3 horas de separación
```

---

## 🧪 Pruebas

### Test 1: Disponibilidad Diaria
```javascript
// Usuario selecciona 10/02/2026
await fetch('app/api/mesas_estado.php?fecha=2026-02-10');

// Resultado: Muestra solo reservas del 10/02
// Mesa C830: DISPONIBLE ✅ 
// (aunque esté ocupada HOY)
```

### Test 2: Validación de 3 Horas
```javascript
// Mesa C830 tiene reserva: 10/02 a las 20:00
await fetch('app/api/mesas_estado.php?fecha=2026-02-10&hora=21:00');

// Resultado: Mesa C830 BLOQUEADA ❌
// (21:00 está dentro de las 3 horas de la reserva 20:00)
```

### Test 3: Días Diferentes
```javascript
// Mesa C830 reservada: 06/02 a las 20:00
await fetch('app/api/mesas_estado.php?fecha=2026-02-07&hora=20:00');

// Resultado: Mesa C830 DISPONIBLE ✅
// (Son días diferentes, no hay conflicto)
```

---

## 📁 Archivos Modificados

### 1. `app/api/mesas_estado.php`
- **Líneas 11-68**: Lógica de parámetros opcionales
- **Líneas 69-85**: Respuesta JSON con fecha consultada

### 2. `index.html`
- **Líneas ~987-1006**: Nuevo selector de fecha
- **Líneas 2715-2774**: Función `cargarMesas()` con parámetros
- **Líneas 2690-2705**: Event listener para cambio de fecha

### 3. `mesas.php`
- **Líneas 623-652**: Función `cargarMesas()` actualizada

---

## 🎨 Interfaz de Usuario

### Vista Previa del Selector:

```
┌─────────────────────────────────────────────┐
│  📅 Ver disponibilidad para:                │
│  ┌──────────────┐                           │
│  │  07/02/2026  │ ▼                         │
│  └──────────────┘                           │
│  💡 Selecciona una fecha para ver qué       │
│     mesas están disponibles ese día         │
└─────────────────────────────────────────────┘
```

---

## ✅ Validaciones Preservadas

### 1. **Duplicados Exactos** (Validación anterior)
```php
// Sigue funcionando: No permite misma mesa/fecha/hora
WHERE mesa_id = ? AND fecha_reserva = ? AND hora_reserva = ?
AND estado IN ('pendiente', 'confirmada', ...)
```

### 2. **Separación de 3 Horas** (Validación anterior)
```php
// Sigue funcionando: Mínimo 3h entre reservas
ABS(TIMESTAMPDIFF(MINUTE, ...)) < 180
```

### 3. **Fecha Específica** (Nueva)
```php
// Nueva: Solo muestra conflictos de LA FECHA SELECCIONADA
DATE(r.fecha_reserva) = :fecha_consultada
```

---

## 🚀 Beneficios

| Antes | Ahora |
|-------|-------|
| ❌ Mesa bloqueada para días futuros | ✅ Solo bloqueada si hay reserva ESA fecha |
| ❌ Usuario confundido | ✅ Selector visual de fecha |
| ❌ Siempre consulta HOY | ✅ Consulta fecha seleccionada |
| ❌ Sin feedback visual | ✅ Alerta muestra fecha consultada |

---

## 📝 Notas Importantes

1. **Compatibilidad hacia atrás**: Si no se pasan parámetros, funciona como antes (muestra HOY)

2. **Validación de 3 horas**: Solo aplica cuando se pasa `hora`, si solo se pasa `fecha`, muestra todas las reservas del día

3. **Formato de fecha**: Debe ser `YYYY-MM-DD` (ISO 8601)

4. **Respuesta JSON mejorada**:
```json
{
  "success": true,
  "fecha_consultada": "2026-02-07",
  "hora_consultada": null,
  "mesas": [ ... ]
}
```

---

## 🔮 Mejoras Futuras Sugeridas

1. **Selector de Hora**: Agregar también selector de hora en index.html
2. **Calendario Visual**: Mostrar disponibilidad en formato calendario
3. **Rango de Fechas**: Permitir consultar disponibilidad para rango de fechas
4. **Caché**: Cachear resultados por fecha para mejorar performance

---

**Estado**: ✅ Implementado y funcional  
**Fecha**: Febrero 2026  
**Versión**: 2.1.0
