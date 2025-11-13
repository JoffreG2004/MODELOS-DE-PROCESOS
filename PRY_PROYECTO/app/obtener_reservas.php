<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../conexion/db.php';

try {
    // Filtro opcional por estado
    $estado = isset($_GET['estado']) && !empty($_GET['estado']) ? $_GET['estado'] : null;
    
    $query = "
        SELECT 
            r.id,
            r.cliente_id,
            r.mesa_id,
            r.fecha_reserva,
            r.hora_reserva,
            r.numero_personas,
            r.estado,
            r.observaciones,
            r.fecha_creacion,
            CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre,
            c.telefono as cliente_telefono,
            c.email as cliente_email,
            m.numero_mesa as mesa_numero,
            m.ubicacion as mesa_ubicacion
        FROM reservas r
        INNER JOIN clientes c ON r.cliente_id = c.id
        INNER JOIN mesas m ON r.mesa_id = m.id
    ";
    
    if ($estado) {
        $query .= " WHERE r.estado = :estado";
    }
    
    $query .= " ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";
    
    $stmt = $pdo->prepare($query);
    
    if ($estado) {
        $stmt->bindParam(':estado', $estado, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reservas' => $reservas,
        'total' => count($reservas)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al obtener reservas',
        'error' => $e->getMessage()
    ]);
}
?>
