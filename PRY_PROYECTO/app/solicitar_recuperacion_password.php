<?php
/**
 * Solicitar recuperación de contraseña (cliente).
 * Genera token temporal y envía enlace por correo usando la configuración de n8n.
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../controllers/EmailController.php';
require_once __DIR__ . '/../utils/security/password_reset_utils.php';
require_once __DIR__ . '/../config/env_loader.php';

function jsonResponse($success, $message, $extra = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $extra));
    exit;
}

function buildResetUrlFromRequest($token) {
    $baseFromEnv = rtrim((string)env('APP_URL', ''), '/');
    if ($baseFromEnv !== '') {
        return $baseFromEnv . '/reset_password.php?token=' . urlencode($token);
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/app/solicitar_recuperacion_password.php';
    $basePath = rtrim(str_replace('\\', '/', dirname(dirname($scriptName))), '/');

    return $scheme . '://' . $host . $basePath . '/reset_password.php?token=' . urlencode($token);
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

    $identificador = trim((string)($input['identificador'] ?? $input['usuario_o_email'] ?? ''));
    if ($identificador === '') {
        http_response_code(400);
        jsonResponse(false, 'Debes ingresar tu usuario o correo');
    }

    if (strlen($identificador) > 100) {
        http_response_code(400);
        jsonResponse(false, 'Dato inválido');
    }

    ensurePasswordResetTable($pdo);

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    if ($ip) {
        // Límite simple: máximo 5 solicitudes por IP cada 15 minutos
        $stmtRate = $pdo->prepare("
            SELECT COUNT(*) AS total
            FROM password_reset_tokens
            WHERE requested_ip = :ip
              AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ");
        $stmtRate->execute(['ip' => $ip]);
        $rate = (int)$stmtRate->fetchColumn();
        if ($rate >= 5) {
            jsonResponse(true, 'Si el usuario/correo existe, se enviará un enlace de recuperación.');
        }
    }

    $stmtCliente = $pdo->prepare("
        SELECT id, nombre, apellido, usuario, email
        FROM clientes
        WHERE usuario = :identificador OR email = :identificador
        LIMIT 1
    ");
    $stmtCliente->execute(['identificador' => $identificador]);
    $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

    // Respuesta genérica para no filtrar si existe o no la cuenta
    $mensajeGenerico = 'Si el usuario/correo existe, se enviará un enlace de recuperación.';

    if (!$cliente || empty($cliente['email'])) {
        jsonResponse(true, $mensajeGenerico);
    }

    $tokenData = createPasswordResetToken(
        $pdo,
        (int)$cliente['id'],
        (string)$cliente['email'],
        $identificador,
        $ip,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        30
    );

    $resetUrl = buildResetUrlFromRequest($tokenData['token']);

    $emailController = new EmailController($pdo);
    $resultadoEmail = $emailController->enviarCorreoRecuperacionPassword([
        'id' => $cliente['id'],
        'nombre' => $cliente['nombre'],
        'apellido' => $cliente['apellido'],
        'correo' => $cliente['email']
    ], $resetUrl, 30);

    if (!$resultadoEmail['success']) {
        error_log('Recuperacion password - error email: ' . ($resultadoEmail['error'] ?? 'desconocido'));
    }

    $extra = [];
    if (env('APP_DEBUG', false) && !empty($resultadoEmail['test_mode'])) {
        $extra['debug_reset_url'] = $resetUrl;
    }

    jsonResponse(true, $mensajeGenerico, $extra);

} catch (Exception $e) {
    error_log('Error solicitar_recuperacion_password: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, 'No se pudo procesar la solicitud');
}
