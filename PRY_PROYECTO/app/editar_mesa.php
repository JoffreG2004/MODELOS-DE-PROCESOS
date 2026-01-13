<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../conexion/db.php';

/* 🔴 Mostrar errores reales de PDO */
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'JSON inválido'
    ]);
    exit;
}

/* =========================
   DATOS LIMPIOS
========================= */
$id = isset($data['id']) ? (int)$data['id'] : 0;
$numero_mesa = strtoupper(trim($data['numero_mesa'] ?? ''));
$capacidad_minima = isset($data['capacidad_minima']) ? (int)$data['capacidad_minima'] : 1;
$capacidad_maxima = isset($data['capacidad_maxima']) ? (int)$data['capacidad_maxima'] : 0;
$ubicacion = trim($data['ubicacion'] ?? 'interior');
$estado = trim($data['estado'] ?? 'disponible');
$descripcion = trim($data['descripcion'] ?? '');

/* =========================
   VALIDACIONES (IGUALES A CREAR)
========================= */

// ID válido
if ($id <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'ID de mesa inválido'
    ]);
    exit;
}

// Número de mesa: M, T, V, B + número (ceros permitidos)
if (!preg_match('/^(M|T|V|B)[0-9]+$/', $numero_mesa)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Número de mesa inválido. Ej: B02, M01, T10'
    ]);
    exit;
}

// Capacidad máxima: 1 a 15
if ($capacidad_maxima < 1 || $capacidad_maxima > 15) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'La capacidad máxima permitida es 15 personas'
    ]);
    exit;
}

// Capacidad mínima válida
if ($capacidad_minima < 1 || $capacidad_minima > $capacidad_maxima) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Capacidad mínima inválida'
    ]);
    exit;
}

/* =========================
   VALIDAR DUPLICADO
========================= */
$stmt = $pdo->prepare(
    "SELECT id FROM mesas WHERE numero_mesa = ? AND id != ?"
);
$stmt->execute([$numero_mesa, $id]);

if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El número de mesa ya existe en otra mesa'
    ]);
    exit;
}

/* =========================
   VERIFICAR EXISTENCIA
========================= */
$stmt = $pdo->prepare("SELECT id FROM mesas WHERE id = ?");
$stmt->execute([$id]);

if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'La mesa no existe'
    ]);
    exit;
}

/* =========================
   UPDATE
========================= */
$stmt = $pdo->prepare("
    UPDATE mesas SET
        numero_mesa = ?,
        capacidad_minima = ?,
        capacidad_maxima = ?,
        ubicacion = ?,
        estado = ?,
        descripcion = ?
    WHERE id = ?
");

$stmt->execute([
    $numero_mesa,
    $capacidad_minima,
    $capacidad_maxima,
    $ubicacion,
    $estado,
    $descripcion,
    $id
]);

echo json_encode([
    'success' => true,
    'message' => 'Mesa actualizada correctamente'
]);
