# 📋 DOCUMENTACIÓN: test_registro_cliente.py

**Archivo de test:** `test-configuration/unit/test_registro_cliente.py`  
**Endpoint evaluado:** `app/registro_cliente.php`  
**Fecha:** 2026-01-07

---

## 📊 Resumen

- **Total tests:** 36
- **Pasados:** 29 ✅
- **Fallados:** 7 ❌
- **Porcentaje éxito:** 80.6%

---

## ❌ TESTS FALLADOS (7)

### 1. Nombre con diéresis (válido): "Müller"

**Problema:** El validador rechaza nombres con ü, ö (diéresis alemanas)  
**Respuesta:** "El nombre contiene caracteres no válidos"

**📍 Archivo afectado:** `validacion/ValidadorNombres.php`  
**Línea aproximada:** Regex de validación

**🔧 Qué cambiar:**
```php
// ANTES (línea ~20-25 en ValidadorNombres.php)
$patron = '/^[a-záéíóúñA-ZÁÉÍÓÚÑ\s\']+$/u';

// DESPUÉS (agregar ü, ö)
$patron = '/^[a-záéíóúüöñA-ZÁÉÍÓÚÜÖÑ\s\']+$/u';
```

---

### 2. Nombre con acentos (válido): "José María"

**Problema:** Error interno del servidor  
**Respuesta:** "Error interno del servidor"

**📍 Archivo afectado:** `app/registro_cliente.php`  
**Causa probable:** Falta validación de caracteres especiales en la inserción a BD

**🔧 Qué revisar:**
- Verificar encoding UTF-8 en la conexión a BD
- Verificar que no hay escape incorrecto de caracteres acentuados
- Revisar logs PHP: `/opt/lampp/logs/php_error_log`

---

### 3. Nombre con ñ (válido): "Núñez"

**Problema:** Error interno del servidor  
**Respuesta:** "Error interno del servidor"

**📍 Archivo afectado:** `app/registro_cliente.php`  
**Causa:** Mismo problema que test #2

**🔧 Qué cambiar:**
```php
// En app/registro_cliente.php, verificar:
1. La conexión PDO tiene charset UTF-8
2. Los prepared statements manejan bien UTF-8
3. No hay funciones que filtren caracteres especiales incorrectamente
```

---

### 4. Nombre/apellido válidos: "Juan Pérez"

**Problema:** Error interno del servidor  
**Respuesta:** "Error interno del servidor"

**📍 Archivo afectado:** `app/registro_cliente.php`  

**🔧 Solución recomendada:**
```php
// Verificar en registro_cliente.php que:
$pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

---

### 5. Cliente base (para duplicado)

**Problema:** No puede crear cliente de prueba  
**Respuesta:** "Error interno del servidor"

**📍 Archivo afectado:** `app/registro_cliente.php`  

---

### 6. Cédula válida

**Problema:** No puede registrar con cédula ecuatoriana válida  
**Respuesta:** "Error interno del servidor"

**📍 Archivo afectado:** `app/registro_cliente.php`  

---

### 7. Registro completo válido

**Problema:** Registro completo falla  
**Respuesta:** "Error interno del servidor"

**📍 Archivo afectado:** `app/registro_cliente.php`  

---

## ✅ TESTS QUE SÍ PASARON (29)

- ✅ Nombre vacío
- ✅ Nombre muy corto (1 char)
- ✅ Nombre muy largo (>50 chars)
- ✅ Nombre con números
- ✅ Nombre con caracteres especiales
- ✅ Apellido vacío
- ✅ Apellido muy corto
- ✅ Apellido muy largo
- ✅ Apellido con números
- ✅ Cédula vacía
- ✅ Cédula muy corta
- ✅ Cédula muy larga
- ✅ Cédula con letras
- ✅ Cédula ecuatoriana inválida (checksum)
- ✅ Cédula provincia inválida (>24)
- ✅ Teléfono vacío
- ✅ Teléfono muy corto
- ✅ Teléfono muy largo
- ✅ Teléfono con letras
- ✅ Usuario vacío
- ✅ Usuario muy corto
- ✅ Usuario muy largo
- ✅ Usuario con espacios
- ✅ Password vacío
- ✅ Password muy corto
- ✅ Password sin mayúsculas
- ✅ Password sin números
- ✅ SQL injection en nombre
- ✅ XSS en apellido

---

## 🎯 PROBLEMAS PRINCIPALES

### 1. ValidadorNombres rechaza caracteres válidos españoles/alemanes

**Impacto:** MEDIO  
**Archivos:** `validacion/ValidadorNombres.php`  
**Solución:** Actualizar regex para incluir ü, ö

### 2. Error interno en registro con caracteres acentuados

**Impacto:** ALTO (bloquea registros válidos)  
**Archivos:** `app/registro_cliente.php`, `conexion/db.php`  
**Solución:** 
1. Verificar charset UTF-8 en PDO
2. Revisar logs PHP para identificar error exacto
3. Asegurar que BD usa utf8mb4

---

## 🔧 ACCIONES RECOMENDADAS

1. **Inmediato:** Revisar logs PHP para identificar causa de "Error interno del servidor"
2. **Prioridad Alta:** Actualizar ValidadorNombres.php para aceptar ü, ö
3. **Prioridad Alta:** Verificar encoding UTF-8 en toda la cadena (BD, PDO, validadores)
4. **Testing:** Re-ejecutar tests después de correcciones
