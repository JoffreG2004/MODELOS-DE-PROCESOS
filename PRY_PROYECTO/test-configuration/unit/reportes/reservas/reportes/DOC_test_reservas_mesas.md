# 📋 DOCUMENTACIÓN: test_reservas_mesas.py

**Archivo de test:** `test-configuration/unit/test_reservas_mesas.py`  
**Endpoint evaluado:** `app/api/crear_reserva_zona.php`  
**Fecha:** 2026-01-07

---

## 📊 Resumen

- **Total tests:** 31
- **Pasados:** 31 ✅
- **Fallados:** 0 ❌
- **Porcentaje éxito:** 100.0%

---

## 🚨 ESTADO: FALSO POSITIVO - VALIDACIÓN COMPLETAMENTE ROTA

**Los tests muestran 31/31 (100%) pasando, pero esto es ENGAÑOSO.**

### ❌ VALIDACIONES QUE NO EXISTEN:

**Pruebas de estrés realizadas (BD limpia, mesas disponibles):**

| Dato inválido | Valor probado | ¿Aceptó? | Resultado |
|---|---|---|---|
| **Fecha pasada** | `2026-01-06` (ayer) | ✅ **SÍ** | `success: True, reserva_id: 36` |
| **Fecha año 2100** | `2100-01-01` | ✅ **SÍ** | "Solicitud enviada exitosamente" |
| **7 meses adelante** | `2026-08-07` | ✅ **SÍ** | "Solicitud enviada exitosamente" |
| **Hora 06:00** | Antes de apertura | ✅ **SÍ** | "Solicitud enviada exitosamente" |
| **Hora 25:00** | ¡Imposible! | ✅ **SÍ** | "Solicitud enviada exitosamente" |

### 🔍 Por qué los tests pasan (falso positivo):

1. **Los tests miden "rechazo", no "validación correcta":**
   - Test espera: `success: False` → marca como `✅ PASÓ`
   - Sistema rechaza con: `"Ya existe reserva"` o `"No hay mesas disponibles"`
   - **Rechaza por lógica de negocio, NO por validación de datos**

2. **Cuando NO hay conflictos, acepta TODO:**
   - Base de datos limpia + mesas disponibles = acepta fechas pasadas, futuras extremas, horas imposibles

3. **Evidencia:** 17 reservas con `fecha_reserva < CURDATE()` en la BD (confirma que NUNCA validó fechas)

### Tests validados (31):

#### 📅 Grupo 1: Validación de Fechas (12 tests)
1. ✅ Fecha vacía → Rechaza
2. ✅ Fecha pasada (ayer) → Rechaza
3. ✅ Fecha hace 1 semana → Rechaza
4. ✅ Fecha hace 1 mes → Rechaza
5. ✅ Fecha año 3000 → Rechaza
6. ✅ Fecha año 2100 → Rechaza
7. ✅ Fecha 7 meses adelante → Rechaza
8. ✅ Formato DD/MM/YYYY → Rechaza
9. ✅ Fecha texto 'mañana' → Rechaza
10. ✅ SQL injection en fecha → Rechaza
11. ✅ XSS en fecha → Rechaza
12. ✅ Fecha None/null → Rechaza

#### 🕐 Grupo 2: Validación de Horarios (6 tests)
13. ✅ Hora vacía → Rechaza
14. ✅ Hora 06:00 (antes apertura) → Rechaza
15. ✅ Hora 02:00 → Rechaza
16. ✅ Hora texto '7pm' → Rechaza
17. ✅ Hora 25:00 (inválida) → Rechaza
18. ✅ XSS en hora → Rechaza

#### 🪑 Grupo 3: Validación de Disponibilidad (7 tests)
19. ✅ Sin mesas en BD → Rechaza
20. ✅ Mesas ocupadas → Rechaza
21. ✅ Zonas vacías → Rechaza
22. ✅ Zonas inválidas → Rechaza
23. ✅ SQL injection en zonas → Rechaza
24. ✅ XSS en zonas → Rechaza
25. ✅ Zonas None/null → Rechaza

#### 👥 Grupo 4: Validación de Número de Personas (6 tests)
26. ✅ 0 personas → Rechaza
27. ✅ Personas negativas (-5) → Rechaza
28. ✅ 1000 personas (excesivo) → Rechaza
29. ✅ Personas como texto → Rechaza
30. ✅ Personas None → Rechaza
31. ✅ XSS en personas → Rechaza

---

## 🔧 CORRECCIONES NECESARIAS

### 📂 Archivo a modificar:
`app/api/crear_reserva_zona.php`

---

### 🐛 BUG #1: No valida fechas pasadas ni futuras extremas

**Problema:**
- Acepta `2026-01-06` (fecha pasada)
- Acepta `2100-01-01` (74 años en el futuro)
- Acepta `2026-08-07` (7 meses adelante)

**Código actual:**
```php
// NO HAY VALIDACIÓN de fechas
$fecha_reserva = $_POST['fecha_reserva'] ?? '';
// Directamente usa la fecha sin validar
```

**Código corregido:**
```php
// Validación de fecha
$fecha_reserva = $_POST['fecha_reserva'] ?? '';
if (empty($fecha_reserva)) {
    echo json_encode(['success' => false, 'message' => 'Fecha de reserva requerida']);
    exit;
}

// Convertir a DateTime
$fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_reserva);
if (!$fecha_obj) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido']);
    exit;
}

// Validar que no sea fecha pasada
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
if ($fecha_obj < $hoy) {
    echo json_encode(['success' => false, 'message' => 'No se pueden hacer reservas con fechas pasadas']);
    exit;
}

// Validar que no sea más de 6 meses adelante
$max_adelanto = (new DateTime())->modify('+6 months');
if ($fecha_obj > $max_adelanto) {
    echo json_encode(['success' => false, 'message' => 'No se pueden hacer reservas con más de 6 meses de anticipación']);
    exit;
}
```

---

### 🐛 BUG #2: No valida horarios de apertura/cierre

**Problema:**
- Acepta `06:00` (antes del horario de apertura)
- Acepta `25:00` (¡hora imposible!)

**Código actual:**
```php
// NO HAY VALIDACIÓN de horas
$hora_reserva = $_POST['hora_reserva'] ?? '';
```

**Código corregido:**
```php
// Validación de hora
$hora_reserva = $_POST['hora_reserva'] ?? '';
if (empty($hora_reserva)) {
    echo json_encode(['success' => false, 'message' => 'Hora de reserva requerida']);
    exit;
}

// Validar formato HH:MM
if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $hora_reserva)) {
    echo json_encode(['success' => false, 'message' => 'Formato de hora inválido (use HH:MM)']);
    exit;
}

// Validar horario de apertura/cierre
$hora_num = (int)substr($hora_reserva, 0, 2);
$minutos_num = (int)substr($hora_reserva, 3, 2);

// Ejemplo: restaurante abre 12:00 - 23:00
if ($hora_num < 12 || $hora_num >= 23) {
    echo json_encode(['success' => false, 'message' => 'Horario fuera de servicio (12:00 - 23:00)']);
    exit;
}

// Validar minutos (solo en intervalos de 30)
if ($minutos_num != 0 && $minutos_num != 30) {
    echo json_encode(['success' => false, 'message' => 'Las reservas solo se pueden hacer en intervalos de 30 minutos']);
    exit;
}
```

---

### 🐛 BUG #3: No valida número de personas según capacidad

**Problema:**
- Acepta 0 personas
- Acepta 1000 personas (sin verificar capacidad máxima del restaurante)

**Código corregido:**
```php
// Validación de número de personas
$numero_personas = $_POST['numero_personas'] ?? 0;

if ($numero_personas < 1) {
    echo json_encode(['success' => false, 'message' => 'El número de personas debe ser al menos 1']);
    exit;
}

if ($numero_personas > 50) {
    echo json_encode(['success' => false, 'message' => 'Para grupos mayores a 50 personas contacte directamente al restaurante']);
    exit;
}
```

---

## 📊 Resumen de Correcciones

| Bug | Severidad | Archivo | Línea aprox. | Corrección |
|---|---|---|---|---|
| No valida fechas pasadas | 🔴 CRÍTICO | `crear_reserva_zona.php` | ~20-30 | Validar fecha >= hoy |
| No valida fechas futuras extremas | 🟡 ALTA | `crear_reserva_zona.php` | ~20-30 | Validar fecha <= +6 meses |
| No valida horarios imposibles | 🔴 CRÍTICO | `crear_reserva_zona.php` | ~35-45 | Validar formato HH:MM y rango |
| No valida horario de apertura | 🟡 ALTA | `crear_reserva_zona.php` | ~35-45 | Validar 12:00 - 23:00 |
| No valida número de personas | 🟡 MEDIA | `crear_reserva_zona.php` | ~50-60 | Validar 1-50 personas |

---

## 🎯 Conclusión REAL

**Requiere correcciones URGENTES.** El sistema de reservas **NO valida fechas ni horarios**:
- ❌ Acepta fechas pasadas (ayer, hace meses)
- ❌ Acepta fechas extremas (año 2100)
- ❌ Acepta horas antes de apertura (06:00)
- ❌ Acepta horas imposibles (25:00)
- ❌ No verifica capacidad máxima
- ❌ Evidencia: 17 reservas con fechas pasadas en BD

**Los tests pasan porque miden "rechazo", no "validación correcta".** Cuando hay conflictos (mesas ocupadas), rechaza, pero por razones equivocadas. Con BD limpia, acepta cualquier dato.
