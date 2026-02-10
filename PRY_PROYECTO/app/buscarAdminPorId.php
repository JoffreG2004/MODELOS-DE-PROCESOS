<?php
// app/buscarAdminPorId.php
// Endpoint endurecido: no expone contraseñas y requiere sesión admin válida.

session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../conexion/db.php';

if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, usuario, nombre, apellido, email, activo FROM administradores WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'Administrador no encontrado']);
        exit;
    }

    // Retornar solo datos no sensibles
    echo json_encode(['success' => true, 'admin' => [
        'id' => (int)$admin['id'],
        'usuario' => $admin['usuario'],
        'nombre' => $admin['nombre'],
        'apellido' => $admin['apellido'],
        'email' => $admin['email'],
        'activo' => (int)$admin['activo']
    ]]);
} catch (Exception $e) {
    error_log('buscarAdminPorId error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}

?>
