<?php
session_start();
header('Content-Type: application/json');

require_once '../conexion/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $cedula = trim($_POST['cedula'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$nombre || !$apellido || !$cedula || !$telefono || !$usuario || !$password) {
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }

    // Verificar que el usuario no exista
    $check = $mysqli->prepare("SELECT id FROM clientes_v2 WHERE usuario = ? LIMIT 1");
    if (!$check) throw new Exception('Error en consulta: ' . $mysqli->error);
    $check->bind_param('s', $usuario);
    $check->execute();
    $res = $check->get_result();
    if ($res && $res->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya está en uso']);
        exit;
    }
    $check->close();

    // Insertar nuevo cliente
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $mysqli->prepare("INSERT INTO clientes_v2 (nombre, apellido, cedula, telefono, ciudad, usuario, password_hash, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) throw new Exception('Error en prepare: ' . $mysqli->error);
    $stmt->bind_param('sssssss', $nombre, $apellido, $cedula, $telefono, $ciudad, $usuario, $password_hash);
    $ok = $stmt->execute();

    if (!$ok) throw new Exception('Error al insertar: ' . $stmt->error);

    $new_id = $stmt->insert_id;
    $stmt->close();

    // Crear sesión automática
    $_SESSION['cliente_id'] = $new_id;
    $_SESSION['cliente_nombre'] = $nombre;
    $_SESSION['cliente_apellido'] = $apellido;
    $_SESSION['cliente_email'] = '';
    $_SESSION['cliente_telefono'] = $telefono;
    $_SESSION['cliente_authenticated'] = true;

    echo json_encode(['success' => true, 'message' => 'Registro exitoso', 'cliente' => ['id' => $new_id, 'nombre' => $nombre, 'apellido' => $apellido]]);
    exit;

} catch (Exception $e) {
    error_log('Error en registro_cliente.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor']);
}

$mysqli->close();

?>
