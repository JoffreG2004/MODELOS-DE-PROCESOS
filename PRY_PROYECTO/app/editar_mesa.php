<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../conexion/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $numero_mesa = $data['numero_mesa'] ?? '';
    $capacidad_minima = $data['capacidad_minima'] ?? 1;
    $capacidad_maxima = $data['capacidad_maxima'] ?? null;
    $ubicacion = $data['ubicacion'] ?? 'interior';
    $estado = $data['estado'] ?? 'disponible';
    $descripcion = $data['descripcion'] ?? null;
    
    if (empty($id) || empty($numero_mesa) || empty($capacidad_maxima)) {
        throw new Exception('ID, número de mesa y capacidad máxima son requeridos');
    }

    $capacidad_minima = (int)$capacidad_minima;
    $capacidad_maxima = (int)$capacidad_maxima;
    if ($capacidad_minima < 1 || $capacidad_maxima < 1 || $capacidad_minima > $capacidad_maxima) {
        throw new Exception('Capacidades inválidas');
    }
    if ($capacidad_minima > 20 || $capacidad_maxima > 20) {
        throw new Exception('No se permiten mesas de más de 20 personas');
    }
    
    // Verificar si el número de mesa ya existe en otra mesa
    $stmt = $pdo->prepare("SELECT id FROM mesas WHERE numero_mesa = ? AND id != ?");
    $stmt->execute([$numero_mesa, $id]);
    if ($stmt->fetch()) {
        throw new Exception('El número de mesa ya existe en otra mesa');
    }

    // No permitir editar mesas ocupadas o reservadas
    $stmt = $pdo->prepare("SELECT estado FROM mesas WHERE id = ?");
    $stmt->execute([$id]);
    $estadoActual = $stmt->fetchColumn();
    if (in_array($estadoActual, ['ocupada', 'reservada'], true)) {
        throw new Exception('No se puede editar una mesa ocupada o reservada');
    }

    // No permitir editar si tiene reservas activas (pendiente/confirmada/preparando/en_curso)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservas
        WHERE mesa_id = ?
          AND estado IN ('pendiente', 'confirmada', 'preparando', 'en_curso')
    ");
    $stmt->execute([$id]);
    if ((int)$stmt->fetchColumn() > 0) {
        throw new Exception('No se puede editar una mesa con reservas activas');
    }

    // Validar máximo 5 mesas por zona (si cambia de zona o excede)
    $stmt = $pdo->prepare("SELECT ubicacion FROM mesas WHERE id = ?");
    $stmt->execute([$id]);
    $actual = $stmt->fetch(PDO::FETCH_ASSOC);
    $zonaActual = $actual['ubicacion'] ?? null;
    if ($zonaActual !== $ubicacion) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM mesas WHERE ubicacion = ?");
        $stmt->execute([$ubicacion]);
        $countZona = (int)$stmt->fetchColumn();
        if ($countZona >= 5) {
            throw new Exception('No se permiten más de 5 mesas por zona');
        }
    }
    
    $query = "UPDATE mesas SET 
              numero_mesa = ?, 
              capacidad_minima = ?, 
              capacidad_maxima = ?, 
              ubicacion = ?, 
              estado = ?, 
              descripcion = ?
              WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$numero_mesa, $capacidad_minima, $capacidad_maxima, $ubicacion, $estado, $descripcion, $id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Mesa actualizada exitosamente'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
