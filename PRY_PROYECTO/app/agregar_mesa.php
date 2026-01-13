<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../conexion/db.php';

/* 🔴 MUY IMPORTANTE: mostrar errores reales */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

/* =========================
   DATOS (con valores seguros)
========================= */
$numero_mesa = strtoupper(trim($data['numero_mesa'] ?? ''));
$capacidad_minima = isset($data['capacidad_minima']) ? (int)$data['capacidad_minima'] : 1;
$capacidad_maxima = isset($data['capacidad_maxima']) ? (int)$data['capacidad_maxima'] : 0;
$ubicacion = trim($data['ubicacion'] ?? 'interior');
$estado = trim($data['estado'] ?? 'disponible');
$descripcion = trim($data['descripcion'] ?? '');

/* =========================
   VALIDACIONES (NO SE TOCAN)
========================= */

// Número de mesa: M T V B + número (con ceros)
if (!preg_match('/^(M|T|V|B)[0-9]+$/', $numero_mesa)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Número de mesa inválido']);
    exit;
}

// Capacidad máxima: SOLO 15
if ($capacidad_maxima < 1 || $capacidad_maxima > 15) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Capacidad máxima permitida: 15']);
    exit;
}

// Capacidad mínima válida
if ($capacidad_minima < 1 || $capacidad_minima > $capacidad_maxima) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Capacidad mínima inválida']);
    exit;
}

/* =========================
   EVITAR DUPLICADOS
========================= */
$stmt = $pdo->prepare("SELECT id FROM mesas WHERE numero_mesa = ?");
$stmt->execute([$numero_mesa]);

if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El número de mesa ya existe']);
    exit;
}

/* =========================
   INSERT (COMPLETO)
========================= */
$stmt = $pdo->prepare("
    INSERT INTO mesas 
    (numero_mesa, capacidad_minima, capacidad_maxima, ubicacion, estado, descripcion)
    VALUES (?, ?, ?, ?, ?, ?)
");

$stmt->execute([
    $numero_mesa,
    $capacidad_minima,
    $capacidad_maxima,
    $ubicacion,
    $estado,
    $descripcion
]);

echo json_encode([
    'success' => true,
    'message' => 'Mesa guardada correctamente',
    'id' => $pdo->lastInsertId()
]);
