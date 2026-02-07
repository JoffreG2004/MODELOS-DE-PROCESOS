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
    
    $id = $data['id'] ?? null;
    
    if (empty($id)) {
        throw new Exception('ID de reserva requerido');
    }

    $normalizarHora = function($hora) {
        if (!$hora) return $hora;
        return strlen($hora) === 5 ? ($hora . ':00') : $hora;
    };

    // Obtener valores actuales para validar conflictos
    $stmtActual = $pdo->prepare("SELECT mesa_id, fecha_reserva, hora_reserva, estado FROM reservas WHERE id = ?");
    $stmtActual->execute([$id]);
    $reservaActual = $stmtActual->fetch(PDO::FETCH_ASSOC);
    if (!$reservaActual) {
        throw new Exception('Reserva no encontrada');
    }

    $mesa_id_final = $data['mesa_id'] ?? $reservaActual['mesa_id'];
    $fecha_final = $data['fecha_reserva'] ?? $reservaActual['fecha_reserva'];
    $hora_final = $normalizarHora($data['hora_reserva'] ?? $reservaActual['hora_reserva']);
    $estado_final = $data['estado'] ?? $reservaActual['estado'];

    if (in_array($estado_final, ['pendiente', 'confirmada', 'preparando', 'en_curso'], true)) {
        $tz = new DateTimeZone('America/Guayaquil');
        $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha_final, $tz);
        if (!$fechaObj) {
            throw new Exception('Fecha de reserva inválida');
        }
        $hoy = new DateTime('now', $tz);
        $hoy->setTime(0, 0, 0);
        if ($fechaObj < $hoy) {
            throw new Exception('No se pueden hacer reservas con fechas pasadas');
        }
    }

    // Bloqueo por reserva de zona (si la reserva quedará activa/pendiente)
    if (in_array($estado_final, ['pendiente', 'confirmada', 'preparando', 'en_curso'], true)) {
        $stmtZonaMesa = $pdo->prepare("SELECT ubicacion FROM mesas WHERE id = ?");
        $stmtZonaMesa->execute([$mesa_id_final]);
        $mesaZona = $stmtZonaMesa->fetch(PDO::FETCH_ASSOC);
        if ($mesaZona && !empty($mesaZona['ubicacion'])) {
            $hora_normal = $hora_final;
            $stmtZona = $pdo->prepare("
                SELECT rz.id, rz.hora_reserva, rz.zonas
                FROM reservas_zonas rz
                WHERE rz.fecha_reserva = ?
                  AND rz.estado IN ('pendiente', 'confirmada')
            ");
            $stmtZona->execute([$fecha_final]);
            while ($row = $stmtZona->fetch(PDO::FETCH_ASSOC)) {
                $zonasRow = json_decode($row['zonas'] ?? '[]', true);
                if (!is_array($zonasRow) || !in_array($mesaZona['ubicacion'], $zonasRow, true)) {
                    continue;
                }
                $hora_zona = $row['hora_reserva'];
                if (strlen($hora_normal) === 5) $hora_normal .= ':00';
                if (strlen($hora_zona) === 5) $hora_zona .= ':00';
                $stmtOverlap = $pdo->prepare("
                    SELECT TIME_TO_SEC(TIMEDIFF(TIME(?), TIME(?))) AS diff_seg
                ");
                $stmtOverlap->execute([$hora_zona, $hora_normal]);
                $diff = (int)$stmtOverlap->fetchColumn();
                if ($diff < 10800) {
                    throw new Exception("No se puede actualizar: hay una reserva de zona en {$mesaZona['ubicacion']} desde {$row['hora_reserva']} y bloquea el resto del día");
                }
            }
        }
    }

    // Validar bloque de 3 horas si la reserva quedará activa/pendiente
    if (in_array($estado_final, ['pendiente', 'confirmada', 'preparando', 'en_curso'], true)) {
        $hora_inicio_dt = DateTime::createFromFormat('H:i:s', $hora_final) ?: DateTime::createFromFormat('H:i', $hora_final);
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
              AND r.id != :id
              AND ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_ref)))) < 10800
            ORDER BY ABS(TIME_TO_SEC(TIMEDIFF(TIME(r.hora_reserva), TIME(:hora_ref_orden)))) ASC
            LIMIT 1
        ");
        $stmtConflicto->execute([
            'mesa_id' => $mesa_id_final,
            'fecha' => $fecha_final,
            'hora_ref' => $hora_inicio_dt->format('H:i:s'),
            'hora_ref_orden' => $hora_inicio_dt->format('H:i:s'),
            'id' => $id
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
        $validacionPersonas = ValidadorReserva::validarNumeroPersonas($data['numero_personas'], 1, null);
        if (!$validacionPersonas['valido']) {
            throw new Exception($validacionPersonas['mensaje']);
        }
        $campos[] = "numero_personas = ?";
        $valores[] = $validacionPersonas['valor'];
    }
    
    if (isset($data['estado'])) {
        $campos[] = "estado = ?";
        $valores[] = $data['estado'];
    }
    
    if (isset($data['observaciones'])) {
        $campos[] = "observaciones = ?";
        $valores[] = $data['observaciones'];
    }
    
    if (empty($campos)) {
        throw new Exception('No hay campos para actualizar');
    }
    
    $campos[] = "fecha_actualizacion = CURRENT_TIMESTAMP";
    
    $valores[] = $id;
    
    $query = "UPDATE reservas SET " . implode(', ', $campos) . " WHERE id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute($valores);
    
    echo json_encode([
        'success' => true,
        'message' => 'Reserva actualizada exitosamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
