<?php
/**
 * Validador de Nombres y Apellidos
 * Valida que solo contengan letras, espacios y caracteres especiales válidos
 */

class ValidadorNombres {
    
    /**
     * Valida un nombre o apellido
     * @param string $texto Nombre o apellido a validar
     * @param string $tipo Tipo de dato ('nombre' o 'apellido')
     * @return array ['valido' => bool, 'mensaje' => string]
     */
    public static function validar($texto, $tipo = 'nombre') {
        $texto = trim($texto);
        
        // Verificar que no esté vacío
        if (empty($texto)) {
            return [
                'valido' => false,
                'mensaje' => "El $tipo es requerido"
            ];
        }
        
        // Verificar longitud mínima
        if (strlen($texto) < 2) {
            return [
                'valido' => false,
                'mensaje' => "El $tipo debe tener al menos 2 caracteres"
            ];
        }
        
        // Verificar longitud máxima
        if (strlen($texto) > 50) {
            return [
                'valido' => false,
                'mensaje' => "El $tipo no puede tener más de 50 caracteres"
            ];
        }
        
        // Verificar que no contenga números
        if (preg_match('/\d/', $texto)) {
            return [
                'valido' => false,
                'mensaje' => "El $tipo no puede contener números"
            ];
        }
        
        // Verificar que solo contenga letras, espacios, acentos y caracteres especiales válidos (ñ, Ñ, ', -)
        if (!preg_match("/^[a-zA-ZáéíóúñÁÉÍÓÚÑ\s'\-]+$/u", $texto)) {
            return [
                'valido' => false,
                'mensaje' => "El $tipo contiene caracteres no válidos"
            ];
        }
        
        // Verificar que no tenga espacios múltiples
        if (preg_match('/\s{2,}/', $texto)) {
            return [
                'valido' => false,
                'mensaje' => "El $tipo no puede tener espacios múltiples"
            ];
        }
        
        return [
            'valido' => true,
            'mensaje' => ucfirst($tipo) . ' válido'
        ];
    }
    
    /**
     * Limpia y formatea un nombre o apellido
     * @param string $texto Texto a limpiar
     * @return string Texto limpio y formateado
     */
    public static function limpiar($texto) {
        // Eliminar espacios al inicio y final
        $texto = trim($texto);
        
        // Eliminar espacios múltiples
        $texto = preg_replace('/\s+/', ' ', $texto);
        
        // Capitalizar primera letra de cada palabra
        $texto = mb_convert_case($texto, MB_CASE_TITLE, 'UTF-8');
        
        return $texto;
    }
}
?>
