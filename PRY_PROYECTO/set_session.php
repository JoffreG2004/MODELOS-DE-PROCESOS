<?php
session_start();

// Simular que hay sesión de admin
$_SESSION['admin_id'] = 1;
$_SESSION['admin_usuario'] = 'admin';
$_SESSION['admin_authenticated'] = true;

echo "Sesión creada. Ahora prueba: http://localhost/PRY_PROYECTO/app/api/dashboard_stats.php";
?>
