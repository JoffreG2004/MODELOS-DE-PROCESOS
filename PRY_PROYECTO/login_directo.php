<?php
// login_directo.php - Login automático para desarrollo
session_start();
require_once 'conexion/db.php';

// Buscar admin en la base de datos
$stmt = $pdo->prepare("SELECT * FROM administradores WHERE usuario = 'admin'");
$stmt->execute();
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if ($admin) {
    // Crear sesión
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_usuario'] = $admin['usuario'];
    $_SESSION['admin_nombre'] = $admin['nombre'] . ' ' . $admin['apellido'];
    $_SESSION['admin_rol'] = $admin['rol'];
    
    // Actualizar último acceso
    $stmt = $pdo->prepare("UPDATE administradores SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$admin['id']]);
    
    // Redirigir al dashboard
    header('Location: admin.php');
    exit;
} else {
    echo "Error: Usuario admin no encontrado";
}
?>