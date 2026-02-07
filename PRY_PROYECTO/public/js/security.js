/**
 * Script de Seguridad para Formularios
 * Deshabilita click derecho, copiar/pegar, arrastrar y soltar en campos de formulario
 * @author Sistema de Reservas - Le Salon de Lumière
 * @version 1.0
 */

(function () {
    'use strict';

    /**
     * Deshabilita el menú contextual (click derecho) en campos de formulario
     */
    function disableContextMenu() {
        // Seleccionar todos los campos de entrada
        const formFields = 'input, textarea, select';

        document.addEventListener('contextmenu', function (e) {
            // Verificar si el elemento objetivo es un campo de formulario
            if (e.target.matches(formFields)) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        }, true); // useCapture = true para capturar en fase de captura
    }

    /**
     * Deshabilita copiar/pegar/cortar mediante atajos de teclado en campos específicos
     * (Opcional - puede ser demasiado restrictivo para algunos casos)
     */
    function disableCopyPaste() {
        const formFields = 'input[type="password"], input[type="email"]';

        document.addEventListener('copy', function (e) {
            if (e.target.matches(formFields)) {
                e.preventDefault();
                return false;
            }
        }, true);

        document.addEventListener('cut', function (e) {
            if (e.target.matches(formFields)) {
                e.preventDefault();
                return false;
            }
        }, true);

        document.addEventListener('paste', function (e) {
            if (e.target.matches(formFields)) {
                e.preventDefault();
                return false;
            }
        }, true);
    }

    /**
     * Deshabilita arrastrar y soltar en campos de formulario
     */
    function disableDragDrop() {
        const formFields = 'input, textarea, select';

        document.addEventListener('dragstart', function (e) {
            if (e.target && e.target.matches && e.target.matches(formFields)) {
                e.preventDefault();
                return false;
            }
        }, true);

        document.addEventListener('drop', function (e) {
            if (e.target && e.target.matches && e.target.matches(formFields)) {
                e.preventDefault();
                return false;
            }
        }, true);
    }

    /**
     * Previene la selección de texto en campos específicos (opcional)
     * Comentado por defecto - descomentar si se necesita
     */
    function disableSelection() {
        const formFields = 'input[type="password"]';

        document.addEventListener('selectstart', function (e) {
            if (e.target && e.target.matches && e.target.matches(formFields)) {
                e.preventDefault();
                return false;
            }
        }, true);
    }

    /**
     * Deshabilita inspeccionar elemento con F12 y otros atajos
     * (Opcional - puede ser muy restrictivo)
     */
    function disableDevTools() {
        document.addEventListener('keydown', function (e) {
            // F12
            if (e.key === 'F12') {
                e.preventDefault();
                return false;
            }

            // Ctrl+Shift+I (Inspeccionar)
            if (e.ctrlKey && e.shiftKey && e.key === 'I') {
                e.preventDefault();
                return false;
            }

            // Ctrl+Shift+J (Consola)
            if (e.ctrlKey && e.shiftKey && e.key === 'J') {
                e.preventDefault();
                return false;
            }

            // Ctrl+U (Ver código fuente)
            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                return false;
            }

            // Ctrl+Shift+C (Selector de elementos)
            if (e.ctrlKey && e.shiftKey && e.key === 'C') {
                e.preventDefault();
                return false;
            }
        });
    }

    /**
     * Inicializa todas las protecciones cuando el DOM esté listo
     */
    function init() {
        // Protecciones siempre activas
        disableContextMenu();
        disableDragDrop();

        // Protecciones opcionales - activar según necesidad
        disableCopyPaste(); // Desactivar si los usuarios necesitan copiar/pegar
        disableSelection(); // Muy restrictivo - desactivado por defecto
        disableDevTools(); // Muy restrictivo - desactivado por defecto

        // Log para desarrollo (remover en producción)
        if (typeof console !== 'undefined') {
            console.log('🔒 Protecciones de seguridad de formularios activadas');
        }
    }

    // Ejecutar cuando el DOM esté completamente cargado
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
