<?php
session_start();
header('Content-Type: application/json');

echo json_encode([
    'session_data' => $_SESSION,
    'has_admin_id' => isset($_SESSION['admin_id']),
    'admin_id' => $_SESSION['admin_id'] ?? 'NO SET'
]);
?>
