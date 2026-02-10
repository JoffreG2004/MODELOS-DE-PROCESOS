<?php
// Forzar que TODOS los errores se devuelvan como JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error interno del servidor: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once '../../conexion/db.php';
require_once __DIR__ . '/../../validacion/ValidadorReserva.php';

// Verificar autenticación
if (!isset($_SESSION['cliente_id']) || !$_SESSION['cliente_authenticated']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No estás autenticado'
    ]);
    exit;
}

// Verificar mesa seleccionada
if (!isset($_SESSION['mesa_seleccionada_id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Debes seleccionar una mesa primero'
    ]);
    exit;
}

// Verificar que hay items en el carrito
if (empty($_SESSION['carrito'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'El carrito está vacío'
    ]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $cliente_id = $_SESSION['cliente_id'];
    $mesa_id = $_SESSION['mesa_seleccionada_id'];
    $fecha_reserva = $input['fecha_reserva'] ?? date('Y-m-d');
    $hora_reserva = $input['hora_reserva'] ?? date('H:i:s');
    $numero_personas_raw = $input['numero_personas'] ?? null;
    $validacionPersonas = ValidadorReserva::validarNumeroPersonas($numero_personas_raw, 1, null);
    if (!$validacionPersonas['valido']) {
        throw new Exception($validacionPersonas['mensaje']);
    }
    $numero_personas = $validacionPersonas['valor'];

    $normalizarHora = function($hora) {
        if (!$hora) return $hora;
        return strlen($hora) === 5 ? ($hora . ':00') : $hora;
    };
    $hora_reserva = $normalizarHora($hora_reserva);
    
    // 1. Validar horario de atención
    $dia_semana = date('N', strtotime($fecha_reserva));
    
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
    
    // Verificar días cerrados (formato: dd-mm,dd-mm o números de día de semana 0-6)
    if (isset($configs['dias_cerrados']) && !empty($configs['dias_cerrados'])) {
        $dias_cerrados = array_map('trim', explode(',', $configs['dias_cerrados']));
        $fecha_formato = date('d-m', strtotime($fecha_reserva));
        $num_dia_semana = date('w', strtotime($fecha_reserva)); // 0=Domingo
        
        // Verificar si la fecha específica está cerrada o el día de la semana
        if (in_array($fecha_formato, $dias_cerrados) || in_array($num_dia_semana, $dias_cerrados)) {
            throw new Exception('El restaurante está cerrado en esta fecha');
        }
    }

    // Validar ventana de reserva: hoy hasta +14 días
    $tz = new DateTimeZone('America/Guayaquil');
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha_reserva, $tz);
    if (!$fechaObj) {
        throw new Exception('Fecha de reserva inválida');
    }
    $hoy = new DateTime('today', $tz);
    $maxAdelanto = new DateTime('today', $tz);
    $maxAdelanto->modify('+14 days');
    if ($fechaObj < $hoy) {
        throw new Exception('No se pueden hacer reservas con fechas pasadas');
    }
    if ($fechaObj > $maxAdelanto) {
        throw new Exception('Solo se permiten reservas desde hoy hasta 14 días en adelante');
    }
    
    // Determinar horario según día de la semana
    // Priorizar horarios específicos, si no existen usar horarios generales
    if ($dia_semana >= 1 && $dia_semana <= 5) {
        $hora_inicio = $configs['horario_lunes_viernes_inicio'] ?? $configs['hora_apertura'] ?? '10:00';
        $hora_fin = $configs['horario_lunes_viernes_fin'] ?? $configs['hora_cierre'] ?? '22:00';
        $tipo_dia = 'Lunes a Viernes';
    } elseif ($dia_semana == 6) {
        $hora_inicio = $configs['horario_sabado_inicio'] ?? $configs['hora_apertura'] ?? '11:00';
        $hora_fin = $configs['horario_sabado_fin'] ?? $configs['hora_cierre'] ?? '23:00';
        $tipo_dia = 'Sábado';
    } else {
        $hora_inicio = $configs['horario_domingo_inicio'] ?? $configs['hora_apertura'] ?? '12:00';
        $hora_fin = $configs['horario_domingo_fin'] ?? $configs['hora_cierre'] ?? '21:00';
        $tipo_dia = 'Domingo';
    }
    
    // Validar hora (solo si no está vacío el valor)
    $hora_reserva_sin_segundos = substr($hora_reserva, 0, 5); // Normalizar a HH:MM
    if (!empty($hora_inicio) && !empty($hora_fin) && 
        ($hora_reserva_sin_segundos < $hora_inicio || $hora_reserva_sin_segundos > $hora_fin)) {
        throw new Exception("Hora no válida. $tipo_dia el restaurante atiende de $hora_inicio a $hora_fin");
    }
    
    $pdo->beginTransaction();
    
    // 2. Verificar que la mesa está disponible y validar capacidad
    $stmt = $pdo->prepare("SELECT estado, numero_mesa, capacidad_minima, capacidad_maxima, ubicacion FROM mesas WHERE id = :id FOR UPDATE");
    $stmt->bindParam(':id', $mesa_id, PDO::PARAM_INT);
    $stmt->execute();
    $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mesa) {
        throw new Exception('Mesa no encontrada');
    }
    
    if ($mesa['estado'] !== 'disponible') {
        throw new Exception('La mesa ' . $mesa['numero_mesa'] . ' no está disponible');
    }

    // Bloqueo por reserva de zona confirmada/activa: si existe una reserva de zona
    // confirmada para esa fecha y zona, no permitir reservas normales en el mismo bloque.
    if (!empty($mesa['ubicacion'])) {
        $hora_normal = $hora_reserva;
        $stmtZona = $pdo->prepare("
            SELECT rz.id, rz.hora_reserva, rz.zonas
            FROM reservas_zonas rz
            WHERE rz.fecha_reserva = ?
              AND rz.estado IN ('confirmada', 'preparando', 'en_curso')
        ");
        $stmtZona->execute([$fecha_reserva]);
        while ($row = $stmtZona->fetch(PDO::FETCH_ASSOC)) {
            $zonasRow = json_decode($row['zonas'] ?? '[]', true);
            if (!is_array($zonasRow) || !in_array($mesa['ubicacion'], $zonasRow, true)) {
                continue;
            }
            if (strlen($hora_normal) === 5) $hora_normal .= ':00';
            $hora_zona = $row['hora_reserva'];
            if (strlen($hora_zona) === 5) $hora_zona .= ':00';
            $stmtOverlap = $pdo->prepare("
                SELECT TIME_TO_SEC(TIMEDIFF(TIME(?), TIME(?))) AS diff_seg
            ");
            $stmtOverlap->execute([$hora_zona, $hora_normal]);
            $diff = (int)$stmtOverlap->fetchColumn();
            if ($diff < 10800) {
                throw new Exception("No se puede reservar: hay una reserva de zona en {$mesa['ubicacion']} desde {$row['hora_reserva']} y bloquea el resto del día");
            }
        }
    }
    
    // Validar capacidad
    if ($numero_personas < $mesa['capacidad_minima']) {
        throw new Exception('La mesa ' . $mesa['numero_mesa'] . ' requiere mínimo ' . $mesa['capacidad_minima'] . ' personas');
    }
    
    if ($numero_personas > $mesa['capacidad_maxima']) {
        throw new Exception('La mesa ' . $mesa['numero_mesa'] . ' permite máximo ' . $mesa['capacidad_maxima'] . ' personas. Seleccionaste ' . $numero_personas);
    }
    
    // Bloquear si ya existe una reserva confirmada/activa en el mismo bloque de 3 horas
    $duracion_horas = 3;
    $hora_inicio_dt = DateTime::createFromFormat('H:i:s', $hora_reserva) ?: DateTime::createFromFormat('H:i', $hora_reserva);
    if (!$hora_inicio_dt) {
        throw new Exception('Hora de reserva inválida');
    }
    $hora_fin_dt = clone $hora_inicio_dt;
    $hora_fin_dt->modify('+3 hours');

    $stmtConflicto = $pdo->prepare("
        SELECT r.id, m.numero_mesa, TIME_FORMAT(r.hora_reserva, '%H:%i') as hora
        FROM reservas r
        INNER JOIN mesas m ON r.mesa_id = m.id
        WHERE r.mesa_id = :mesa_id
          AND DATE(r.fecha_reserva) = DATE(:fecha)
          AND r.estado IN ('confirmada', 'preparando', 'en_curso')
          AND ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_ref)))) < 10800
        ORDER BY ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_ref_orden)))) ASC
        LIMIT 1
    ");
    $stmtConflicto->execute([
        'mesa_id' => $mesa_id,
        'fecha' => $fecha_reserva,
        'hora_ref' => $hora_inicio_dt->format('H:i:s'),
        'hora_ref_orden' => $hora_inicio_dt->format('H:i:s')
    ]);
    $conflicto = $stmtConflicto->fetch(PDO::FETCH_ASSOC);
    if ($conflicto) {
        $hora_conflicto = DateTime::createFromFormat('H:i', $conflicto['hora']);
        $hora_antes = $hora_conflicto ? $hora_conflicto->modify('-3 hours')->format('H:i') : null;
        $hora_conflicto = DateTime::createFromFormat('H:i', $conflicto['hora']);
        $hora_despues = $hora_conflicto ? $hora_conflicto->modify('+3 hours')->format('H:i') : null;
        $sugerencia = '';
        if ($hora_antes && $hora_despues) {
            $sugerencia = " Por favor reserva a las {$hora_antes} o a las {$hora_despues}.";
        }
        throw new Exception(
            "La mesa {$conflicto['numero_mesa']} ya tiene una reserva a las {$conflicto['hora']} dentro del bloque de 3 horas." .
            $sugerencia . " Solo se libera si el administrador la libera."
        );
    }

    // 2. Crear la reserva en estado PENDIENTE (el admin debe confirmarla)
    $stmt = $pdo->prepare("INSERT INTO reservas 
                           (cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, estado) 
                           VALUES 
                           (:cliente_id, :mesa_id, :fecha_reserva, :hora_reserva, :numero_personas, 'pendiente')");
    
    $stmt->bindParam(':cliente_id', $cliente_id, PDO::PARAM_INT);
    $stmt->bindParam(':mesa_id', $mesa_id, PDO::PARAM_INT);
    $stmt->bindParam(':fecha_reserva', $fecha_reserva);
    $stmt->bindParam(':hora_reserva', $hora_reserva);
    $stmt->bindParam(':numero_personas', $numero_personas, PDO::PARAM_INT);
    $stmt->execute();
    
    $reserva_id = $pdo->lastInsertId();
    
    // 3. Procesar cada item del carrito
    $total_reserva = 0;
    $platos_agregados = [];
    
    foreach ($_SESSION['carrito'] as $item) {
        $plato_id = $item['id'];
        $cantidad = $item['cantidad'];
        $precio_unitario = $item['precio'];
        $subtotal = $item['subtotal'];
        
        // Verificar stock actual y bloquearlo
        $stmt = $pdo->prepare("SELECT stock_disponible, nombre FROM platos WHERE id = :id FOR UPDATE");
        $stmt->bindParam(':id', $plato_id, PDO::PARAM_INT);
        $stmt->execute();
        $plato = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$plato) {
            throw new Exception('Plato ID ' . $plato_id . ' no encontrado');
        }
        
        if ($plato['stock_disponible'] < $cantidad) {
            throw new Exception('Stock insuficiente para: ' . $plato['nombre'] . 
                              '. Disponible: ' . $plato['stock_disponible']);
        }
        
        // Descontar stock
        $nuevo_stock = $plato['stock_disponible'] - $cantidad;
        $stmt = $pdo->prepare("UPDATE platos SET stock_disponible = :stock WHERE id = :id");
        $stmt->bindParam(':stock', $nuevo_stock, PDO::PARAM_INT);
        $stmt->bindParam(':id', $plato_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Insertar pre_pedido
        $stmt = $pdo->prepare("INSERT INTO pre_pedidos 
                               (reserva_id, plato_id, cantidad, precio_unitario, subtotal) 
                               VALUES 
                               (:reserva_id, :plato_id, :cantidad, :precio_unitario, :subtotal)");
        
        $stmt->bindParam(':reserva_id', $reserva_id, PDO::PARAM_INT);
        $stmt->bindParam(':plato_id', $plato_id, PDO::PARAM_INT);
        $stmt->bindParam(':cantidad', $cantidad, PDO::PARAM_INT);
        $stmt->bindParam(':precio_unitario', $precio_unitario);
        $stmt->bindParam(':subtotal', $subtotal);
        $stmt->execute();
        
        $total_reserva += $subtotal;
        $platos_agregados[] = [
            'nombre' => $plato['nombre'],
            'cantidad' => $cantidad,
            'subtotal' => $subtotal
        ];
    }
    
    // 4. Cambiar estado de la mesa a 'reservada'
    $stmt = $pdo->prepare("UPDATE mesas SET estado = 'reservada' WHERE id = :id");
    $stmt->bindParam(':id', $mesa_id, PDO::PARAM_INT);
    $stmt->execute();
    
    // 5. Crear nota de consumo (borrador)
    $numero_nota = 'NC-' . date('Ymd') . '-' . str_pad($reserva_id, 6, '0', STR_PAD_LEFT);
    $subtotal = $total_reserva;
    $impuesto = round($subtotal * 0.12, 2); // IVA 12%
    $total_con_iva = round($subtotal + $impuesto, 2);
    
    $stmt = $pdo->prepare("INSERT INTO notas_consumo 
                           (reserva_id, numero_nota, subtotal, impuesto, total, estado) 
                           VALUES 
                           (:reserva_id, :numero_nota, :subtotal, :impuesto, :total, 'borrador')");
    
    $stmt->bindParam(':reserva_id', $reserva_id, PDO::PARAM_INT);
    $stmt->bindParam(':numero_nota', $numero_nota);
    $stmt->bindParam(':subtotal', $subtotal);
    $stmt->bindParam(':impuesto', $impuesto);
    $stmt->bindParam(':total', $total_con_iva);
    $stmt->execute();
    
    // Confirmar transacción
    $pdo->commit();
    
    // NO enviar WhatsApp aquí - se enviará cuando el admin confirme la reserva
    
    // Limpiar carrito y sesión de mesa
    $_SESSION['carrito'] = [];
    unset($_SESSION['mesa_seleccionada_id']);
    unset($_SESSION['mesa_seleccionada_numero']);
    
    echo json_encode([
        'success' => true,
        'message' => '¡Reserva confirmada exitosamente!',
        'reserva' => [
            'id' => $reserva_id,
            'numero_nota' => $numero_nota,
            'mesa' => $mesa['numero_mesa'],
            'fecha' => $fecha_reserva,
            'hora' => $hora_reserva,
            'personas' => $numero_personas,
            'platos' => $platos_agregados,
            'subtotal' => $subtotal,
            'impuesto' => $impuesto,
            'total' => $total_con_iva
        ]
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error en confirmar_reserva_con_platos.php: " . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Error al confirmar reserva: ' . $e->getMessage(),
        'error_detail' => $e->getFile() . ' en línea ' . $e->getLine()
    ]);
} catch (Throwable $e) {
    // Capturar errores fatales de PHP 7+
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error fatal en confirmar_reserva_con_platos.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error crítico del servidor: ' . $e->getMessage()
    ]);
}
?>
