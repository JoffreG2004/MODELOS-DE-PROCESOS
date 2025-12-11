<?php
/**
 * API: Crear Reserva de Zona Completa (VERSIÓN SIMPLIFICADA)
 * Crea UNA SOLA reserva de zona en la tabla reservas_zonas
 * NO crea reservas individuales por mesa
 */
session_start();
header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['cliente_authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../../conexion/db.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $cliente_id = $_SESSION['cliente_id'];
    $zonas = $data['zonas'] ?? []; // Array de zonas: ['interior', 'terraza', 'vip', 'bar']
    $fecha_reserva = $data['fecha_reserva'] ?? '';
    $hora_reserva = $data['hora_reserva'] ?? '';
    $numero_personas = $data['numero_personas'] ?? 0;
    
    // Validaciones
    if (empty($zonas) || !is_array($zonas)) {
        echo json_encode(['success' => false, 'message' => 'Debe seleccionar al menos una zona']);
        exit;
    }
    
    if (empty($fecha_reserva) || empty($hora_reserva)) {
        echo json_encode(['success' => false, 'message' => 'Fecha y hora son requeridas']);
        exit;
    }
    
    // Calcular precio según cantidad de zonas
    $precios = [
        1 => 60.00,  // 1 zona
        2 => 100.00, // 2 zonas
        3 => 120.00, // 3 zonas
        4 => 140.00  // 4 zonas (todas)
    ];
    
    $cantidad_zonas = count($zonas);
    $precio_total = $precios[$cantidad_zonas] ?? 60.00;
    
    // Contar las mesas totales en las zonas seleccionadas
    $placeholders = str_repeat('?,', count($zonas) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_mesas
        FROM mesas 
        WHERE ubicacion IN ($placeholders)
        AND estado NOT IN ('mantenimiento')
    ");
    $stmt->execute($zonas);
    $cantidad_mesas = $stmt->fetch(PDO::FETCH_ASSOC)['total_mesas'];
    
    if ($cantidad_mesas == 0) {
        echo json_encode(['success' => false, 'message' => 'No hay mesas disponibles en las zonas seleccionadas']);
        exit;
    }
    
    // Verificar que no haya otra reserva de zona en la misma fecha/hora
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as conflictos
        FROM reservas_zonas
        WHERE fecha_reserva = ?
        AND estado IN ('pendiente', 'confirmada')
        AND (
            (? >= hora_reserva AND ? < ADDTIME(hora_reserva, '02:00:00'))
            OR
            (ADDTIME(?, '02:00:00') > hora_reserva AND ADDTIME(?, '02:00:00') <= ADDTIME(hora_reserva, '02:00:00'))
            OR
            (? <= hora_reserva AND ADDTIME(?, '02:00:00') >= ADDTIME(hora_reserva, '02:00:00'))
        )
    ");
    $stmt->execute([$fecha_reserva, $hora_reserva, $hora_reserva, $hora_reserva, $hora_reserva, $hora_reserva, $hora_reserva]);
    $conflictos = $stmt->fetch(PDO::FETCH_ASSOC)['conflictos'];
    
    if ($conflictos > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'Ya existe una reserva de zona en ese horario'
        ]);
        exit;
    }
    
    // Crear UNA SOLA reserva de zona (NO múltiples reservas por mesa)
    $stmt = $pdo->prepare("
        INSERT INTO reservas_zonas 
        (cliente_id, zonas, fecha_reserva, hora_reserva, numero_personas, precio_total, cantidad_mesas, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    
    $zonas_json = json_encode($zonas);
    $stmt->execute([
        $cliente_id,
        $zonas_json,
        $fecha_reserva,
        $hora_reserva,
        $numero_personas,
        $precio_total,
        $cantidad_mesas
    ]);
    
    $reserva_id = $pdo->lastInsertId();
    
    // Nombres de zonas para mostrar
    $nombres_zonas = [
        'interior' => 'Salón Principal',
        'terraza' => 'Terraza',
        'vip' => 'Área VIP',
        'bar' => 'Bar & Lounge'
    ];
    
    $zonas_texto = array_map(function($z) use ($nombres_zonas) {
        return $nombres_zonas[$z] ?? $z;
    }, $zonas);
    
    echo json_encode([
        'success' => true,
        'message' => 'Solicitud de reserva de zona enviada exitosamente',
        'data' => [
            'reserva_id' => $reserva_id,
            'zonas' => $zonas_texto,
            'cantidad_mesas' => $cantidad_mesas,
            'precio_total' => $precio_total,
            'fecha' => $fecha_reserva,
            'hora' => $hora_reserva,
            'personas' => $numero_personas
        ]
    ]);

    
} catch (Exception $e) {
    error_log('Error en crear_reserva_zona.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear reserva de zona: ' . $e->getMessage()
    ]);
}
?>
