<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../conexion/db.php';
require_once __DIR__ . '/../validacion/ValidadorReserva.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $cliente_id = $data['cliente_id'] ?? null;
    $mesa_id = $data['mesa_id'] ?? null;
    $fecha_reserva = $data['fecha_reserva'] ?? null;
    $hora_reserva = $data['hora_reserva'] ?? null;
    $numero_personas = $data['numero_personas'] ?? null;
    $duracion_horas = $data['duracion_horas'] ?? 3; // Por defecto 3 horas
    $es_zona_completa = $data['es_zona_completa'] ?? false;
    $estado = $data['estado'] ?? 'pendiente';
    $crear_nota_consumo = !empty($data['crear_nota_consumo']);
    $subtotal_nota_input = isset($data['subtotal_nota']) ? (float)$data['subtotal_nota'] : null;
    
    if (empty($cliente_id) || empty($mesa_id) || empty($fecha_reserva) || empty($hora_reserva)) {
        throw new Exception('Todos los campos obligatorios son requeridos');
    }

    $validacionPersonas = ValidadorReserva::validarNumeroPersonas($numero_personas, 1, null);
    if (!$validacionPersonas['valido']) {
        throw new Exception($validacionPersonas['mensaje']);
    }
    $numero_personas = $validacionPersonas['valor'];

    $tz = new DateTimeZone('America/Guayaquil');
    $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha_reserva, $tz);
    if (!$fechaObj) {
        throw new Exception('Fecha de reserva inválida');
    }
    $hoy = new DateTime('now', $tz);
    $hoy->setTime(0, 0, 0);
    if ($fechaObj < $hoy) {
        throw new Exception('No se pueden hacer reservas con fechas pasadas');
    }
    $maxAdelanto = new DateTime('today', $tz);
    $maxAdelanto->modify('+14 days');
    if ($fechaObj > $maxAdelanto) {
        throw new Exception('Solo se permiten reservas desde hoy hasta 14 días en adelante');
    }

    $normalizarHora = function($hora) {
        if (!$hora) return $hora;
        return strlen($hora) === 5 ? ($hora . ':00') : $hora;
    };
    $hora_reserva = $normalizarHora($hora_reserva);

    $pdo->beginTransaction();
    
    // Verificar que el cliente existe
    $stmt = $pdo->prepare("SELECT id FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    if (!$stmt->fetch()) {
        throw new Exception('El cliente no existe');
    }
    
    // Verificar que la mesa existe y obtener zona/capacidad/precio
    $stmt = $pdo->prepare("SELECT id, ubicacion, capacidad_minima, capacidad_maxima, precio_reserva FROM mesas WHERE id = ? FOR UPDATE");
    $stmt->execute([$mesa_id]);
    $mesa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mesa) {
        throw new Exception('La mesa no existe');
    }
    $zona_mesa = $mesa['ubicacion'] ?? null;
    $cap_min = isset($mesa['capacidad_minima']) ? (int)$mesa['capacidad_minima'] : 1;
    $cap_max = isset($mesa['capacidad_maxima']) ? (int)$mesa['capacidad_maxima'] : null;
    if ($cap_max !== null && ($numero_personas < $cap_min || $numero_personas > $cap_max)) {
        throw new Exception("La mesa permite entre {$cap_min} y {$cap_max} personas");
    }

    // Bloqueo por reserva de zona confirmada/activa: no bloquear por pendientes.
    if ($zona_mesa) {
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
            if (!is_array($zonasRow) || !in_array($zona_mesa, $zonasRow, true)) {
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
                throw new Exception("No se puede reservar: hay una reserva de zona en {$zona_mesa} desde {$row['hora_reserva']} y bloquea el resto del día");
            }
        }
    }
    
    // BLOQUEO: Si ya hay una reserva confirmada/activa en el mismo bloque de 3 horas,
    // no permitir nuevas reservas para esa mesa/fecha/hora.
    $duracion_horas = is_numeric($duracion_horas) ? (int)$duracion_horas : 3;
    $hora_inicio_dt = DateTime::createFromFormat('H:i:s', $hora_reserva) ?: DateTime::createFromFormat('H:i', $hora_reserva);
    if (!$hora_inicio_dt) {
        throw new Exception('Hora de reserva inválida');
    }
    $hora_fin_dt = clone $hora_inicio_dt;
    $hora_fin_dt->modify('+' . max(1, $duracion_horas) . ' hours');

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
    
    // Duración personalizada para zona completa
    if ($es_zona_completa) {
        $duracion_horas = 12; // Zona completa: de 10:00 a 22:00
    } else {
        $duracion_horas = $duracion_horas ?? 3; // 3 horas por defecto para reservas normales
    }
    
    $query = "INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, duracion_horas, estado) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $cliente_id, 
        $mesa_id, 
        $fecha_reserva, 
        $hora_reserva, 
        $numero_personas, 
        $duracion_horas,
        $estado
    ]);
    
    $reserva_id = $pdo->lastInsertId();
    $nota_consumo = null;

    if ($crear_nota_consumo) {
        $subtotal_nota = $subtotal_nota_input;
        if ($subtotal_nota === null) {
            $subtotal_nota = isset($mesa['precio_reserva']) ? (float)$mesa['precio_reserva'] : 0.00;
        }
        if ($subtotal_nota < 0) {
            $subtotal_nota = 0.00;
        }

        $impuesto_nota = 0.00;
        $total_nota = round($subtotal_nota + $impuesto_nota, 2);
        $numero_nota = 'NC-' . date('Ymd') . '-' . str_pad($reserva_id, 6, '0', STR_PAD_LEFT);

        // Crear nota solo si la tabla existe (compatibilidad con instalaciones antiguas)
        $stmtExisteTabla = $pdo->prepare("
            SELECT COUNT(1)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'notas_consumo'
        ");
        $stmtExisteTabla->execute();
        $existeTablaNotas = ((int)$stmtExisteTabla->fetchColumn()) > 0;

        if ($existeTablaNotas) {
            $stmtNota = $pdo->prepare("
                INSERT INTO notas_consumo
                (reserva_id, numero_nota, subtotal, impuesto, total, estado, observaciones)
                VALUES
                (?, ?, ?, ?, ?, 'borrador', ?)
            ");
            $obsNota = 'Nota creada automáticamente desde flujo cliente (reserva sin platos).';
            $stmtNota->execute([
                $reserva_id,
                $numero_nota,
                $subtotal_nota,
                $impuesto_nota,
                $total_nota,
                $obsNota
            ]);

            $nota_consumo = [
                'numero_nota' => $numero_nota,
                'subtotal' => $subtotal_nota,
                'impuesto' => $impuesto_nota,
                'total' => $total_nota,
                'estado' => 'borrador'
            ];
        }
    }
    
    // Si el admin crea con estado confirmada, enviar WhatsApp
    if ($estado === 'confirmada') {
        try {
            $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $basePath = preg_replace('#/app$#', '', $scriptDir);
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $urlWhatsApp = $scheme . '://' . $host . $basePath . '/app/api/enviar_whatsapp.php';

            $ch = curl_init($urlWhatsApp);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reserva_id' => $reserva_id]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $e) {
            error_log("Error al enviar WhatsApp: " . $e->getMessage());
        }
    }

    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reserva creada exitosamente',
        'id' => $reserva_id,
        'duracion_horas' => $duracion_horas,
        'nota_consumo' => $nota_consumo
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
