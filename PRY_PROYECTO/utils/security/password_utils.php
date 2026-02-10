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

if (!function_exists('verificarPasswordSeguro')) {
    function verificarPasswordSeguro($passwordPlano, $passwordGuardado) {
        if (!is_string($passwordGuardado) || $passwordGuardado === '') {
            return false;
        }

        if (esPasswordHash($passwordGuardado)) {
            return password_verify((string)$passwordPlano, $passwordGuardado);
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

