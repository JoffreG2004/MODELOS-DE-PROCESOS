<?php
/**
 * TEST: Finalización Manual de Reservas
 */

// Simular sesión de admin
session_start();
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_usuario'] = 'admin_test';

require_once '../app/finalizar_reserva_manual.php';
?>
