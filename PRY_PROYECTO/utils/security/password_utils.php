<?php
/**
 * Utilidades de seguridad para manejo de contraseñas.
 */

if (!function_exists('passwordAlgoSeguro')) {
    function passwordAlgoSeguro() {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }
}

if (!function_exists('esPasswordHash')) {
    function esPasswordHash($valor) {
        if (!is_string($valor) || $valor === '') {
            return false;
        }
        $info = password_get_info($valor);
        return !empty($info['algo']);
    }
}

if (!function_exists('hashPasswordSeguro')) {
    function hashPasswordSeguro($passwordPlano) {
        return password_hash((string)$passwordPlano, passwordAlgoSeguro());
    }
}

if (!function_exists('permitirPasswordPlanoLegacy')) {
    function permitirPasswordPlanoLegacy() {
        $valor = $_ENV['ALLOW_LEGACY_PLAINTEXT_PASSWORDS']
            ?? $_SERVER['ALLOW_LEGACY_PLAINTEXT_PASSWORDS']
            ?? getenv('ALLOW_LEGACY_PLAINTEXT_PASSWORDS');

        if ($valor === false || $valor === null) {
            return false;
        }

        return in_array(strtolower((string)$valor), ['1', 'true', 'yes', 'on'], true);
    }
}

if (!function_exists('verificarPasswordSeguro')) {
    function verificarPasswordSeguro($passwordPlano, $passwordGuardado) {
        if (!is_string($passwordGuardado) || $passwordGuardado === '') {
            return false;
        }

        if (esPasswordHash($passwordGuardado)) {
            return password_verify((string)$passwordPlano, $passwordGuardado);
        }

        // Compatibilidad legacy opcional: por seguridad está DESACTIVADO por defecto.
        if (!permitirPasswordPlanoLegacy()) {
            return false;
        }

        return hash_equals((string)$passwordGuardado, (string)$passwordPlano);
    }
}

if (!function_exists('requiereRehashPassword')) {
    function requiereRehashPassword($passwordGuardado) {
        if (!esPasswordHash($passwordGuardado)) {
            return false;
        }
        return password_needs_rehash($passwordGuardado, passwordAlgoSeguro());
    }
}

if (!function_exists('validarPoliticaPasswordSegura')) {
    function validarPoliticaPasswordSegura($passwordPlano) {
        $password = (string)$passwordPlano;

        if (strlen($password) < 8) {
            return ['valido' => false, 'mensaje' => 'La contraseña debe tener mínimo 8 caracteres'];
        }

        if (!preg_match('/[A-Z]/', $password)) {
            return ['valido' => false, 'mensaje' => 'La contraseña debe incluir al menos 1 letra mayúscula'];
        }

        if (!preg_match('/\d/', $password)) {
            return ['valido' => false, 'mensaje' => 'La contraseña debe incluir al menos 1 número'];
        }

        return ['valido' => true, 'mensaje' => ''];
    }
}
