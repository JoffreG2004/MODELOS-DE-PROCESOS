<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../../conexion/db.php';

try {
    // Estadísticas generales
    $stats = [];
    
    // Total de mesas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM mesas");
    $stats['mesas_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Mesas disponibles
    $stmt = $pdo->query("SELECT COUNT(*) as disponibles FROM mesas WHERE estado = 'disponible'");
    $stats['mesas_disponibles'] = $stmt->fetch(PDO::FETCH_ASSOC)['disponibles'];
    
    // Mesas ocupadas
    $stmt = $pdo->query("SELECT COUNT(*) as ocupadas FROM mesas WHERE estado IN ('ocupada', 'reservada')");
    $stats['mesas_ocupadas'] = $stmt->fetch(PDO::FETCH_ASSOC)['ocupadas'];
    
    // Reservas de hoy
    $stmt = $pdo->query("SELECT COUNT(*) as hoy FROM reservas WHERE DATE(fecha_reserva) = CURDATE()");
    $stats['reservas_hoy'] = $stmt->fetch(PDO::FETCH_ASSOC)['hoy'];
    
    // Reservas activas (en curso)
    $stmt = $pdo->query("SELECT COUNT(*) as activas FROM reservas WHERE estado = 'en_curso'");
    $stats['reservas_activas'] = $stmt->fetch(PDO::FETCH_ASSOC)['activas'];
    
    // Reservas pendientes
    $stmt = $pdo->query("SELECT COUNT(*) as pendientes FROM reservas WHERE estado = 'pendiente'");
    $stats['reservas_pendientes'] = $stmt->fetch(PDO::FETCH_ASSOC)['pendientes'];
    
    // Total de clientes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes");
    $stats['clientes_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Ingresos estimados del día (simulado)
    $ingresos = $stats['reservas_hoy'] * 45.50; // Promedio por reserva
    $stats['ingresos_estimados'] = number_format($ingresos, 2);
    
    // Porcentaje de ocupación
    $ocupacion = $stats['mesas_total'] > 0 ? ($stats['mesas_ocupadas'] / $stats['mesas_total']) * 100 : 0;
    $stats['porcentaje_ocupacion'] = round($ocupacion, 1);
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $stats,
        'meta' => [
            'fecha' => date('Y-m-d'),
            'hora' => date('H:i:s'),
            'servidor' => 'Dashboard API v1.0',
            'cache_time' => 30
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error del servidor',
        'error' => $e->getMessage()
    ]);
}
?>