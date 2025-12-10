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
    
    if (empty($id)) {
        throw new Exception('ID de reserva requerido');
    }
    
    // Obtener datos actuales de la reserva
    $stmt = $pdo->prepare("SELECT * FROM reservas WHERE id = ?");
    $stmt->execute([$id]);
    $reservaActual = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservaActual) {
        throw new Exception('Reserva no encontrada');
    }
    
    // Verificar si se está intentando cambiar fecha u hora
    $cambioHorario = false;
    $nuevaFecha = $data['fecha_reserva'] ?? $reservaActual['fecha_reserva'];
    $nuevaHora = $data['hora_reserva'] ?? $reservaActual['hora_reserva'];
    $nuevaMesaId = $data['mesa_id'] ?? $reservaActual['mesa_id'];
    
    if ($nuevaFecha !== $reservaActual['fecha_reserva'] || 
        $nuevaHora !== $reservaActual['hora_reserva'] ||
        $nuevaMesaId != $reservaActual['mesa_id']) {
        $cambioHorario = true;
        
        // Validar que el nuevo horario esté dentro del horario de atención
        $dia_semana = date('N', strtotime($nuevaFecha));
        
        // Obtener configuraciones de horarios
        $stmt = $pdo->query("SELECT clave, valor FROM configuracion_restaurante");
        $configs = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $configs[$row['clave']] = $row['valor'];
        }
        
        // Verificar si las reservas están activas
        if (isset($configs['reservas_activas']) && $configs['reservas_activas'] !== '1') {
            throw new Exception('Las reservas están temporalmente deshabilitadas');
        }
        
        // Determinar horario según día de la semana
        if ($dia_semana >= 1 && $dia_semana <= 5) {
            $hora_inicio = $configs['horario_lunes_viernes_inicio'] ?? $configs['hora_apertura'] ?? '10:00';
            $hora_fin = $configs['horario_lunes_viernes_fin'] ?? $configs['hora_cierre'] ?? '22:00';
        } elseif ($dia_semana == 6) {
            $hora_inicio = $configs['horario_sabado_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
            $hora_fin = $configs['horario_sabado_fin'] ?? $configs['hora_cierre'] ?? '23:00';
        } else {
            $hora_inicio = $configs['horario_domingo_inicio'] ?? $configs['hora_apertura'] ?? '12:00';
            $hora_fin = $configs['horario_domingo_fin'] ?? $configs['hora_cierre'] ?? '21:00';
        }
        
        // Validar hora
        $hora_sin_segundos = substr($nuevaHora, 0, 5);
        if (!empty($hora_inicio) && !empty($hora_fin) && 
            ($hora_sin_segundos < $hora_inicio || $hora_sin_segundos > $hora_fin)) {
            throw new Exception("El nuevo horario debe estar entre $hora_inicio y $hora_fin");
        }
        
        // Verificar que no haya otra reserva en la misma mesa, fecha y hora
        $stmt = $pdo->prepare("
            SELECT r.id, r.hora_reserva, m.numero_mesa 
            FROM reservas r
            INNER JOIN mesas m ON r.mesa_id = m.id
            WHERE r.mesa_id = ? 
            AND r.fecha_reserva = ? 
            AND r.hora_reserva = ?
            AND r.id != ?
            AND r.estado IN ('pendiente', 'confirmada')
        ");
        $stmt->execute([$nuevaMesaId, $nuevaFecha, $nuevaHora, $id]);
        $conflicto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conflicto) {
            throw new Exception('Ya existe otra reserva para la mesa ' . $conflicto['numero_mesa'] . ' en ese horario. Por favor, elige otra hora o mesa.');
        }
    }
    
    // Construir query dinámicamente según los campos enviados
    $campos = [];
    $valores = [];
    
    if (isset($data['cliente_id'])) {
        $campos[] = "cliente_id = ?";
        $valores[] = $data['cliente_id'];
    }
    
    if (isset($data['mesa_id'])) {
        $campos[] = "mesa_id = ?";
        $valores[] = $data['mesa_id'];
    }
    
    if (isset($data['fecha_reserva'])) {
        $campos[] = "fecha_reserva = ?";
        $valores[] = $data['fecha_reserva'];
    }
    
    if (isset($data['hora_reserva'])) {
        $campos[] = "hora_reserva = ?";
        $valores[] = $data['hora_reserva'];
    }
    
    if (isset($data['numero_personas'])) {
        $campos[] = "numero_personas = ?";
        $valores[] = $data['numero_personas'];
    }
    
    if (isset($data['estado'])) {
        $campos[] = "estado = ?";
        $valores[] = $data['estado'];
    }
    
    if (empty($campos)) {
        throw new Exception('No hay campos para actualizar');
    }
    
    $valores[] = $id;
    
    $query = "UPDATE reservas SET " . implode(', ', $campos) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($valores);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reserva actualizada exitosamente',
        'cambio_horario' => $cambioHorario
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
