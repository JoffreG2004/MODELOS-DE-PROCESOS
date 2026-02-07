# 🔒 Sistema de Seguridad de Formularios

## Descripción

Se ha implementado un sistema de seguridad para **deshabilitar el click derecho** y otras acciones potencialmente inseguras en todos los campos de formulario del sistema.

## 📋 Características Implementadas

### ✅ Protecciones Activas (Por Defecto)

1. **Deshabilitar Click Derecho (Menú Contextual)**
   - Bloquea el menú contextual en todos los campos: `input`, `textarea`, `select`
   - Previene: Inspeccionar elemento, copiar/pegar desde menú, ver código fuente

2. **Deshabilitar Arrastrar y Soltar**
   - Previene arrastrar contenido hacia/desde campos de formulario
   - Protege contra ataques de drag & drop

3. **Deshabilitar Copiar/Pegar en Campos Sensibles**
   - Aplica a: `input[type="password"]`, `input[type="email"]`
   - Previene: Ctrl+C, Ctrl+V, Ctrl+X en estos campos específicos

### 🔧 Protecciones Opcionales (Comentadas)

Estas están desactivadas por defecto pero pueden activarse descomentando las líneas en el archivo:

4. **Deshabilitar Selección de Texto**
   - Solo en campos de contraseña
   - Muy restrictivo - usar con precaución

5. **Deshabilitar Herramientas de Desarrollo**
   - Bloquea: F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U, Ctrl+Shift+C
   - MUY restrictivo - no recomendado en producción

## 📂 Archivos Modificados

### Archivo Principal
```
/public/js/security.js
```
Este archivo contiene toda la lógica de seguridad.

### Archivos que Incluyen el Script
1. ✅ `index.html` - Página principal
2. ✅ `admin.php` - Panel de administración
3. ✅ `mesas.php` - Sistema de reservas de mesas
4. ✅ `perfil_cliente.php` - Perfil del cliente

## 🚀 Cómo Funciona

El script se ejecuta automáticamente cuando la página carga:

```javascript
// Se auto-ejecuta en función IIFE
(function() {
    'use strict';
    
    function init() {
        disableContextMenu();
        disableDragDrop();
        disableCopyPaste();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

## 🎯 Campos Protegidos

### Todos los Campos
- ❌ Click derecho
- ❌ Arrastrar y soltar

### Campos de Contraseña y Email
- ❌ Copiar (Ctrl+C)
- ❌ Pegar (Ctrl+V)
- ❌ Cortar (Ctrl+X)

## ⚙️ Configuración Personalizada

### Para Activar Protecciones Adicionales

Edita `/public/js/security.js` y descomenta las funciones que desees:

```javascript
function init() {
    disableContextMenu();      // ✅ Activa
    disableDragDrop();         // ✅ Activa
    disableCopyPaste();        // ✅ Activa
    
    // Descomenta para activar:
    // disableSelection();     // ⚠️  Muy restrictivo
    // disableDevTools();      // ⚠️  Muy restrictivo
}
```

### Para Modificar Campos Específicos

Cambia los selectores CSS en cada función:

```javascript
// Solo proteger passwords
const formFields = 'input[type="password"]';

// Proteger todos los inputs
const formFields = 'input';

// Proteger inputs y textareas
const formFields = 'input, textarea';
```

## 🧪 Cómo Probar

1. **Abrir cualquier página del sistema**
2. **Intentar hacer click derecho en un campo de formulario**
   - ❌ No debe aparecer el menú contextual
3. **Intentar copiar/pegar en campo de password/email**
   - ❌ No debe permitir la acción
4. **Abrir consola del navegador**
   - ✅ Debe mostrar: `🔒 Protecciones de seguridad de formularios activadas`

## 📊 Impacto en Experiencia de Usuario

### ✅ Ventajas
- Mayor seguridad contra inspección de código
- Previene copiar datos sensibles fácilmente
- Protege contra ataques básicos de ingeniería social

### ⚠️ Consideraciones
- Los usuarios NO podrán:
  - Hacer click derecho en campos (esperado)
  - Copiar/pegar contraseñas (puede frustrar a algunos usuarios)
  - Usar herramientas de auto-completado que usan drag & drop

### 💡 Recomendaciones
- ✅ Mantener activas las protecciones básicas (contextmenu, drag&drop)
- ⚠️ Evaluar si realmente necesitas bloquear copiar/pegar
- ❌ NO activar `disableDevTools()` en producción (muy restrictivo)

## 🔍 Debugging

Si necesitas desactivar temporalmente las protecciones:

1. **Opción 1: Comentar la carga del script**
```html
<!-- <script src="public/js/security.js"></script> -->
```

2. **Opción 2: Modificar el script**
```javascript
function init() {
    // disableContextMenu();
    // disableDragDrop();
    // disableCopyPaste();
    console.log('Protecciones temporalmente desactivadas');
}
```

## 🛡️ Nivel de Seguridad

| Protección | Nivel | Estado | Impacto Usuario |
|------------|-------|--------|-----------------|
| Click Derecho | Alto | ✅ Activo | Bajo |
| Drag & Drop | Medio | ✅ Activo | Bajo |
| Copy/Paste | Alto | ✅ Activo | Medio |
| Selección | Muy Alto | ⏸️ Opcional | Alto |
| DevTools | Extremo | ⏸️ Opcional | Muy Alto |

## 📝 Notas Importantes

1. **Compatibilidad**: Funciona en todos los navegadores modernos
2. **Rendimiento**: Mínimo impacto en rendimiento
3. **Accesibilidad**: No afecta a usuarios con lectores de pantalla
4. **SEO**: No afecta el posicionamiento

## 🔄 Mantenimiento

- **Actualizar selectores**: Si agregas nuevos tipos de campos
- **Revisar logs**: Verificar que las protecciones estén activas
- **Testing**: Probar en diferentes navegadores

## 👨‍💻 Soporte Técnico

Si encuentras algún problema:
1. Verificar que `/public/js/security.js` existe
2. Verificar que el script está incluido en el HTML
3. Abrir consola y buscar mensajes de error
4. Verificar que el orden de carga es correcto (después de DOM)

---

**Última actualización**: 4 de Febrero de 2026  
**Versión**: 1.0  
**Autor**: Sistema de Reservas - Le Salon de Lumière
