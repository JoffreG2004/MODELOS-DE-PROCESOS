<?php

// conexion/db.php - SISTEMA DE GESTIÓN ACADÉMICA
$host = 'localhost';
$dbname = 'crud_proyecto';  // ← Tu nueva base de datos
$username = 'crud_proyecto';  // ← Tu nuevo proyecto
$password = '12345';  // ← La contraseña que pusiste
$charset = 'utf8';
$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

// Crear conexión PDO (usada por algunos scripts)
try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Opcional: establecer el charset correctamente
    $pdo->exec("SET NAMES $charset");
} catch (PDOException $e) {
    // No detenemos inmediatamente: intentamos exponer un error claro
    error_log("PDO connection error: " . $e->getMessage());
    // Si quieres forzar la parada en caso de fallo, descomenta la siguiente línea
    // die("Error de conexión (PDO): " . $e->getMessage());
}

// Crear conexión mysqli (muchos scripts del proyecto usan $mysqli)
$mysqli = new mysqli($host, $username, $password, $dbname);
if ($mysqli->connect_error) {
    error_log("MySQLi connection error: " . $mysqli->connect_error);
    // Nota: evitamos hacer die() aquí para que las páginas puedan manejar el error
} else {
    // Establecer charset
    $mysqli->set_charset($charset);
}

// Dejar disponible ambas variables ($pdo y $mysqli) para compatibilidad
