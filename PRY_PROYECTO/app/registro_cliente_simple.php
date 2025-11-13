<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../conexion/db.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos del formulario
$nombre = trim($_POST['nombre'] ?? '');
$apellido = trim($_POST['apellido'] ?? '');
$cedula = trim($_POST['cedula'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$ciudad = trim($_POST['ciudad'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = $_POST['password'] ?? '';
$email = trim($_POST['email'] ?? '');

// Validar campos requeridos
if (empty($nombre) || empty($apellido) || empty($cedula) || empty($telefono) || empty($usuario) || empty($password) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
    exit;
}

// Basic DB connection check
if (!isset($mysqli) || ($mysqli instanceof mysqli && $mysqli->connect_errno)) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a la base de datos']);
    exit;
}

try {
    // Verificar si el usuario ya existe
    $check_sql = "SELECT id FROM clientes WHERE usuario = ? OR cedula = ? OR email = ? LIMIT 1";
    $check_stmt = $mysqli->prepare($check_sql);
    
    if (!$check_stmt) {
        throw new Exception('Error al verificar usuario existente');
    }
    
    $check_stmt->bind_param('sss', $usuario, $cedula, $email);
    $check_stmt->execute();
    $check_stmt->store_result();
    
    if ($check_stmt->num_rows > 0) {
        $check_stmt->close();
        echo json_encode(['success' => false, 'message' => 'El usuario, cédula o email ya están registrados']);
        exit;
    }
    $check_stmt->close();
    
    // Insertar nuevo cliente (password sin hash en columna password_hash)
    $insert_sql = "INSERT INTO clientes (nombre, apellido, cedula, telefono, ciudad, usuario, password_hash, email) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $insert_stmt = $mysqli->prepare($insert_sql);
    
    if (!$insert_stmt) {
        throw new Exception('Error al preparar inserción: ' . $mysqli->error);
    }
    
    $insert_stmt->bind_param('ssssssss', $nombre, $apellido, $cedula, $telefono, $ciudad, $usuario, $password, $email);
    
    if ($insert_stmt->execute()) {
        $nuevo_id = $insert_stmt->insert_id;
        $insert_stmt->close();
        
        // Crear sesión automáticamente después del registro
        $_SESSION['cliente_id'] = $nuevo_id;
        $_SESSION['cliente_usuario'] = $usuario;
        $_SESSION['cliente_nombre'] = $nombre;
        $_SESSION['cliente_apellido'] = $apellido;
        $_SESSION['cliente_telefono'] = $telefono;
        $_SESSION['cliente_ciudad'] = $ciudad;
        $_SESSION['cliente_authenticated'] = true;
        $_SESSION['cliente_login_time'] = time();
        
        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso',
            'cliente' => [
                'id' => $nuevo_id,
                'usuario' => $usuario,
                'nombre' => $nombre,
                'apellido' => $apellido,
                'telefono' => $telefono
            ]
        ]);
    } else {
        throw new Exception('Error al ejecutar inserción: ' . $insert_stmt->error);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error al registrar: ' . $e->getMessage()]);
}

$mysqli->close();
