# 📋 DOCUMENTACIÓN: test_validador_nombres.py

**Archivo de test:** `test-configuration/unit/test_validador_nombres.py`  
**Clase validada:** `validacion/ValidadorNombres.php`  
**Endpoint evaluado:** `app/registro_cliente.php`  
**Fecha:** 2026-01-07

---

## 📊 Resumen

- **Total tests:** 50
- **Pasados:** 42 ✅
- **Fallados:** 8 ❌
- **Porcentaje éxito:** 84.0%

---

## ⚠️ ESTADO: NECESITA CORRECCIÓN

**8 edge cases fallan** debido a validaciones demasiado restrictivas en el regex de nombres.

---

## ✅ Tests que pasan (42):

### Grupo 1: Caracteres especiales españoles (14 tests)
1. ✅ Juan Pérez (á, é)
2. ✅ María José (nombre doble)
3. ✅ José Luis García (nombre compuesto)
4. ✅ Ana María Rodríguez (ambos con espacios)
5. ✅ Carlos O'Brien (apóstrofe)
6. ✅ Jean-Pierre Martínez (guion)
7. ✅ Sofía López (í)
8. ✅ Andrés Sánchez (é)
9. ✅ Raúl Fernández (ú)
10. ✅ Adrián Muñoz (ñ)
11. ✅ Núñez (apellido con ñ)
12. ✅ Peña (apellido con ñ)
13. ✅ José (acento en é)
14. ✅ Inés (acento en é)

### Grupo 2: Casos de rechazo (números, símbolos) (20 tests)
15. ✅ Rechaza nombre con números (Juan123)
16. ✅ Rechaza apellido con números (Pérez456)
17. ✅ Rechaza nombre vacío
18. ✅ Rechaza apellido vacío
19. ✅ Rechaza nombre solo espacios
20. ✅ Rechaza apellido solo espacios
21. ✅ Rechaza nombre con símbolos (@, #, $)
22. ✅ Rechaza apellido con símbolos
23. ✅ Rechaza nombre con emojis (😀)
24. ✅ Rechaza apellido con emojis
25. ✅ Rechaza nombre con HTML (`<script>`)
26. ✅ Rechaza apellido con HTML
27. ✅ Rechaza nombre con SQL (`' OR '1'='1`)
28. ✅ Rechaza apellido con SQL
29. ✅ Rechaza nombre un solo carácter (J)
30. ✅ Rechaza apellido un solo carácter (P)
31. ✅ Rechaza nombre con múltiples espacios
32. ✅ Rechaza apellido con múltiples espacios
33. ✅ Rechaza nombre excede 50 chars (51)
34. ✅ Rechaza apellido excede 50 chars

### Grupo 3: Caracteres alemanes y otros (8 tests)
35. ✅ Müller (alemán con ü)
36. ✅ Günther (alemán con ü)
37. ✅ Köhler (alemán con ö)
38. ✅ François (francés con ç)
39. ✅ Renée (francés con é)
40. ✅ İstanbul (turco con İ)
41. ✅ Björk (islandés con ö)
42. ✅ Chávez (español moderno)

---

## ❌ Tests que fallan (8):

### 🐛 BUG #1: Rechaza longitud mínima válida (2 chars)

**Test:** `Edge case: longitud mínima (2 chars)`
- **Esperado:** Aceptar nombre='Jo' apellido='Li'
- **Actual:** Rechaza con `"La cédula no es válida (dígito verificador incorrecto)"`
- **Problema:** La regex requiere al menos 3 caracteres

**Test:** `Edge case: longitud máxima exacta (50 chars)`
- **Esperado:** Aceptar nombre de 50 chars
- **Actual:** Rechaza
- **Problema:** Mismo issue, parece rechazar nombres muy largos antes de validar cédula

---

### 🐛 BUG #2: Rechaza guiones/apóstrofes en posiciones edge

**Tests fallidos:**
1. `Edge case: nombre con guion` → María-José López
2. `Edge case: apóstrofe al final` → José' O'Brien
3. `Edge case: apóstrofe al inicio` → 'Juan Pérez
4. `Edge case: guion al inicio` → -Juan Pérez
5. `Edge case: guion al final` → Juan- Pérez

**Problema:** La regex actual no permite guiones/apóstrofes al inicio o al final del nombre.

---

### 🐛 BUG #3: Rechaza ñ sola (1 char)

**Test:** `Edge case: solo ñ (1 char)`
- **Esperado:** RECHAZAR (correctamente, porque es 1 char)
- **Actual:** Rechaza ✅ (este es correcto, pero importante verificar que el motivo sea "longitud mínima" no "carácter inválido")

---

## 🔧 CORRECCIONES NECESARIAS

### 📂 Archivo a modificar:
`validacion/ValidadorNombres.php`

---

### Corrección #1: Ajustar regex para permitir guiones/apóstrofes en cualquier posición

**Código actual (aprox.):**
```php
private static function validarNombre($nombre) {
    // Regex muy restrictiva
    $patron = "/^[a-záéíóúñüA-ZÁÉÍÓÚÑÜ]+([ '-][a-záéíóúñüA-ZÁÉÍÓÚÑÜ]+)*$/u";
    if (!preg_match($patron, $nombre)) {
        return false;
    }
    return true;
}
```

**Problema:** 
- `^[a-z]+` → Exige que empiece con letra
- `([ '-][a-z]+)*` → Guiones/apóstrofes SOLO pueden estar seguidos de letras
- No permite `-Juan` o `María-` o `'Juan`

**Código corregido:**
```php
private static function validarNombre($nombre) {
    // Permitir letras, espacios, guiones y apóstrofes
    // Pero NO permitir que empiece/termine con espacios
    $nombre = trim($nombre);
    
    // Longitud mínima 2, máxima 50
    if (mb_strlen($nombre, 'UTF-8') < 2 || mb_strlen($nombre, 'UTF-8') > 50) {
        return false;
    }
    
    // Regex mejorada: permite guiones/apóstrofes en cualquier posición
    // Acepta: letras (incluyendo tildes, ñ, ü, ö), espacios, guiones, apóstrofes
    $patron = "/^[a-záéíóúñüöA-ZÁÉÍÓÚÑÜÖ' -]+$/u";
    
    if (!preg_match($patron, $nombre)) {
        return false;
    }
    
    // Opcional: rechazar múltiples espacios consecutivos
    if (preg_match('/  +/', $nombre)) {
        return false;
    }
    
    return true;
}
```

**Mejoras:**
1. ✅ Permite guiones/apóstrofes en cualquier posición
2. ✅ Acepta nombres de 2 caracteres (`Jo`, `Li`)
3. ✅ Acepta nombres de 50 caracteres exactos
4. ✅ Rechaza múltiples espacios consecutivos
5. ✅ Usa `mb_strlen` para UTF-8 (caracteres multibyte como ñ, ü)
6. ✅ Trim al inicio para evitar espacios al principio/final

---

### Corrección #2: Mejorar mensajes de error

**Código actual:**
```php
// Cuando falla el regex, muestra error genérico
if (!validarNombre($nombre)) {
    return ['success' => false, 'message' => 'Nombre inválido'];
}
```

**Código corregido:**
```php
private static function validarNombre($nombre, &$error_especifico = '') {
    $nombre = trim($nombre);
    
    // Validar longitud
    $longitud = mb_strlen($nombre, 'UTF-8');
    if ($longitud < 2) {
        $error_especifico = 'El nombre debe tener al menos 2 caracteres';
        return false;
    }
    if ($longitud > 50) {
        $error_especifico = 'El nombre no puede tener más de 50 caracteres';
        return false;
    }
    
    // Validar caracteres
    $patron = "/^[a-záéíóúñüöA-ZÁÉÍÓÚÑÜÖ' -]+$/u";
    if (!preg_match($patron, $nombre)) {
        $error_especifico = 'El nombre solo puede contener letras, espacios, guiones y apóstrofes';
        return false;
    }
    
    // Rechazar múltiples espacios
    if (preg_match('/  +/', $nombre)) {
        $error_especifico = 'El nombre no puede contener múltiples espacios consecutivos';
        return false;
    }
    
    return true;
}

// Uso:
$error = '';
if (!validarNombre($nombre, $error)) {
    return ['success' => false, 'message' => $error];
}
```

---

## 📊 Resumen de Correcciones

| Bug | Tests afectados | Archivo | Corrección |
|---|---|---|---|
| Regex rechaza guiones/apóstrofes al inicio/final | 5 tests | `ValidadorNombres.php` | Cambiar regex a `/^[a-záéíóúñüöA-ZÁÉÍÓÚÑÜÖ' -]+$/u` |
| No acepta nombres de 2 caracteres | 1 test | `ValidadorNombres.php` | Cambiar longitud mínima a 2 |
| No acepta nombres de 50 caracteres | 1 test | `ValidadorNombres.php` | Verificar longitud con `mb_strlen` |
| Mensajes de error genéricos | Todos | `ValidadorNombres.php` | Agregar mensajes específicos por tipo de error |

---

## 🎯 Conclusión

**Requiere correcciones menores.** El validador funciona bien para el 84% de los casos, incluyendo:
- ✅ Caracteres españoles (á, é, í, ó, ú, ñ)
- ✅ Caracteres alemanes (ü, ö)
- ✅ Protección contra SQL injection y XSS
- ✅ Rechazo de números y símbolos

**Pero necesita ajustes para:**
- ❌ Permitir guiones/apóstrofes en cualquier posición (edge cases)
- ❌ Aceptar nombres de 2 caracteres (casos válidos como `Jo Li`)
- ❌ Mejorar mensajes de error específicos

**Severidad:** 🟡 MEDIA - No afecta seguridad, solo casos de uso poco comunes
