<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../conexion/db.php';

// Protección simple para evitar ejecuciones accidentales
$token = $_GET['token'] ?? '';
if ($token !== 'SEED_TEST_2026') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

// Helpers
function date_range($start, $end) {
    $dates = [];
    $cur = new DateTime($start);
    $endDt = new DateTime($end);
    while ($cur <= $endDt) {
        $dates[] = $cur->format('Y-m-d');
        $cur->modify('+1 day');
    }
    return $dates;
}

function pick_random($arr) {
    return $arr[array_rand($arr)];
}

function time_to_minutes($time) {
    $parts = explode(':', $time);
    return ((int)$parts[0]) * 60 + ((int)$parts[1]);
}

function has_conflict($times, $candidate) {
    $cand = time_to_minutes($candidate);
    foreach ($times as $t) {
        if (abs($cand - $t) < 180) {
            return true;
        }
    }
    return false;
}

try {
    // Cargar clientes
    $clientes = $pdo->query("SELECT id FROM clientes")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($clientes)) {
        throw new Exception('No hay clientes en la base de datos');
    }

    // Cargar mesas
    $mesas = $pdo->query("SELECT id, ubicacion, capacidad_minima, capacidad_maxima, precio_reserva FROM mesas")
        ->fetchAll(PDO::FETCH_ASSOC);
    if (empty($mesas)) {
        throw new Exception('No hay mesas en la base de datos');
    }

    // Mapa de zonas -> mesas
    $zonas = [];
    foreach ($mesas as $m) {
        $zona = $m['ubicacion'] ?: 'sin_zona';
        if (!isset($zonas[$zona])) $zonas[$zona] = [];
        $zonas[$zona][] = $m;
    }
    $zonasKeys = array_keys($zonas);

    $pastDates = date_range('2025-11-25', '2026-02-06');
    $futureDates = date_range('2026-02-07', '2026-02-20');
    $slots = ['11:00:00','14:00:00','17:00:00','20:00:00'];

    // Reservas existentes para evitar conflictos
    $existing = $pdo->query("SELECT mesa_id, fecha_reserva, hora_reserva FROM reservas WHERE fecha_reserva BETWEEN '2025-11-25' AND '2026-02-20'")
        ->fetchAll(PDO::FETCH_ASSOC);
    $timesByMesaDate = [];
    foreach ($existing as $r) {
        $key = $r['mesa_id'] . '|' . $r['fecha_reserva'];
        if (!isset($timesByMesaDate[$key])) $timesByMesaDate[$key] = [];
        $timesByMesaDate[$key][] = time_to_minutes(substr($r['hora_reserva'], 0, 5));
    }

    // Fechas con reservas de zona existentes
    $existingZones = $pdo->query("SELECT fecha_reserva FROM reservas_zonas WHERE fecha_reserva BETWEEN '2025-11-25' AND '2026-02-20'")
        ->fetchAll(PDO::FETCH_COLUMN);
    $zoneDates = array_fill_keys($existingZones, true);

    $pdo->beginTransaction();

    $insertedNormales = 0;
    $insertedZonas = 0;
    $errors = [];

    // Seleccionar fechas para zonas: 20 pasadas + 10 futuras
    shuffle($pastDates);
    shuffle($futureDates);
    $zoneDatesPast = array_slice($pastDates, 0, 20);
    $zoneDatesFuture = array_slice($futureDates, 0, 10);
    $zoneDatesAll = array_merge($zoneDatesPast, $zoneDatesFuture);
    foreach ($zoneDatesAll as $d) $zoneDates[$d] = true;

    // Crear 30 reservas de zona
    foreach ($zoneDatesAll as $date) {
        $cliente_id = pick_random($clientes);
        $zonaCount = (count($zonasKeys) > 1 && rand(0, 100) < 40) ? 2 : 1;
        $zonasSel = $zonasKeys;
        shuffle($zonasSel);
        $zonasSel = array_slice($zonasSel, 0, $zonaCount);

        $mesasIncluidas = [];
        $precioTotal = 0;
        foreach ($zonasSel as $z) {
            foreach ($zonas[$z] as $m) {
                $mesasIncluidas[] = $m;
                $precioTotal += (float)($m['precio_reserva'] ?? 0);
            }
        }
        $cantidadMesas = count($mesasIncluidas);
        $numero_personas = rand(10, 50);
        $hora = pick_random($slots);

        $estado = 'finalizada';
        if (in_array($date, $futureDates, true)) {
            $r = rand(1, 100);
            if ($r <= 65) $estado = 'confirmada';
            else if ($r <= 85) $estado = 'pendiente';
            else $estado = 'cancelada';
        } else {
            $estado = (rand(0, 100) < 70) ? 'finalizada' : 'cancelada';
        }

        $stmt = $pdo->prepare("INSERT INTO reservas_zonas (cliente_id, zonas, fecha_reserva, hora_reserva, numero_personas, precio_total, cantidad_mesas, estado) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $cliente_id,
            json_encode(array_values($zonasSel)),
            $date,
            $hora,
            $numero_personas,
            $precioTotal,
            $cantidadMesas,
            $estado
        ]);
        $insertedZonas++;
    }

    // Crear 70 reservas normales: 50 pasadas + 20 futuras
    $normalTargets = [
        'past' => 50,
        'future' => 20
    ];

    $makeNormals = function($dates, $target, $isFuture) use ($clientes, $mesas, $slots, &$timesByMesaDate, $zoneDates, &$insertedNormales) {
        $attempts = 0;
        while ($insertedNormales < $target && $attempts < 10000) {
            $attempts++;
            $date = pick_random($dates);
            if (isset($zoneDates[$date])) {
                continue; // evitar fechas con zonas
            }
            $mesa = pick_random($mesas);
            $mesaId = $mesa['id'];
            $hora = pick_random($slots);

            $key = $mesaId . '|' . $date;
            if (!isset($timesByMesaDate[$key])) $timesByMesaDate[$key] = [];
            if (has_conflict($timesByMesaDate[$key], $hora)) {
                continue;
            }

            $minCap = (int)($mesa['capacidad_minima'] ?? 1);
            $maxCap = (int)($mesa['capacidad_maxima'] ?? max(2, $minCap));
            if ($maxCap < $minCap) $maxCap = $minCap;
            $personas = rand($minCap, $maxCap);

            if ($isFuture) {
                $r = rand(1, 100);
                if ($r <= 70) $estado = 'confirmada';
                else if ($r <= 85) $estado = 'pendiente';
                else $estado = 'cancelada';
            } else {
                $estado = (rand(0, 100) < 70) ? 'finalizada' : 'cancelada';
            }

            $stmt = $GLOBALS['pdo']->prepare("INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, estado) VALUES (?,?,?,?,?,?)");
            $stmt->execute([
                pick_random($clientes),
                $mesaId,
                $date,
                $hora,
                $personas,
                $estado
            ]);

            $timesByMesaDate[$key][] = time_to_minutes(substr($hora, 0, 5));
            $insertedNormales++;
        }
    };

    $insertedNormales = 0;
    $makeNormals($pastDates, $normalTargets['past'], false);
    $insertedNormalesPast = $insertedNormales;
    $makeNormals($futureDates, $normalTargets['past'] + $normalTargets['future'], true);
    $insertedNormalesFuture = $insertedNormales - $insertedNormalesPast;

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Reservas de prueba generadas',
        'normales' => [
            'total' => $insertedNormales,
            'pasadas' => $insertedNormalesPast,
            'futuras' => $insertedNormalesFuture
        ],
        'zonas' => [
            'total' => $insertedZonas,
            'pasadas' => 20,
            'futuras' => 10
        ]
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
