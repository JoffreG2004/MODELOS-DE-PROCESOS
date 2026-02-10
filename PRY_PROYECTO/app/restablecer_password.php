<?php
/**
 * Restablecer contraseña usando token de recuperación (cliente).
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../utils/security/password_utils.php';
require_once __DIR__ . '/../utils/security/password_reset_utils.php';

function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        jsonResponse(false, 'Método no permitido');
    }

    $input = $_POST;
    if (empty($input)) {
        $raw = json_decode(file_get_contents('php://input'), true);
        if (is_array($raw)) {
            $input = $raw;
        }
    }

    $token = trim((string)($input['token'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $passwordConfirm = (string)($input['password_confirm'] ?? '');

    if ($token === '' || $password === '' || $passwordConfirm === '') {
        http_response_code(400);
        jsonResponse(false, 'Todos los campos son obligatorios');
    }

    if ($password !== $passwordConfirm) {
        http_response_code(400);
        jsonResponse(false, 'Las contraseñas no coinciden');
    }

    $validacionPassword = validarPoliticaPasswordSegura($password);
    if (!$validacionPassword['valido']) {
        http_response_code(400);
        jsonResponse(false, $validacionPassword['mensaje']);
    }

    ensurePasswordResetTable($pdo);

    $txIniciada = false;
    if (!$pdo->inTransaction()) {
        $txIniciada = (bool)$pdo->beginTransaction();
    }

    $tokenRow = findValidPasswordResetToken($pdo, $token);
    if (!$tokenRow) {
        if ($txIniciada && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        jsonResponse(false, 'El enlace no es válido o ya expiró');
    }

    $nuevoHash = hashPasswordSeguro($password);

    $stmtUpdatePass = $pdo->prepare("
        UPDATE clientes
        SET password_hash = :password_hash
        WHERE id = :cliente_id
    ");
    $stmtUpdatePass->execute([
        'password_hash' => $nuevoHash,
        'cliente_id' => $tokenRow['cliente_id']
    ]);

    markPasswordResetTokenUsed($pdo, (int)$tokenRow['id'], (int)$tokenRow['cliente_id']);

    if ($txIniciada && $pdo->inTransaction()) {
        $pdo->commit();
    }

    jsonResponse(true, 'Contraseña actualizada correctamente');

} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Error restablecer_password: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, 'No se pudo restablecer la contraseña');
}
