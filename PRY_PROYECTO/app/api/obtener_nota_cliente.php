<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../conexion/db.php';

if (!isset($_SESSION['cliente_id']) || !isset($_SESSION['cliente_authenticated']) || $_SESSION['cliente_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autenticado'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("
        SELECT COUNT(1)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table_name
    ");
    $stmt->execute(['table_name' => $tableName]);
    return ((int)$stmt->fetchColumn()) > 0;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $clienteId = (int)$_SESSION['cliente_id'];
    $reservaId = isset($input['reserva_id']) ? (int)$input['reserva_id'] : 0;
    $tipoReserva = strtolower(trim((string)($input['tipo_reserva'] ?? 'normal')));

    if ($reservaId <= 0) {
        throw new Exception('ID de reserva inválido');
    }

    if ($tipoReserva === 'zona') {
        $stmt = $pdo->prepare("
            SELECT id, zonas, fecha_reserva, hora_reserva, numero_personas, precio_total, cantidad_mesas, estado, fecha_creacion
            FROM reservas_zonas
            WHERE id = :id AND cliente_id = :cliente_id
            LIMIT 1
        ");
        $stmt->execute([
            'id' => $reservaId,
            'cliente_id' => $clienteId
        ]);
        $rz = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$rz) {
            throw new Exception('Reserva de zona no encontrada');
        }

        $nombresZonas = [
            'interior' => 'Salón Principal',
            'terraza' => 'Terraza',
            'vip' => 'Área VIP',
            'bar' => 'Bar & Lounge'
        ];
        $zonasRaw = json_decode($rz['zonas'] ?? '[]', true);
        if (!is_array($zonasRaw)) {
            $zonasRaw = [];
        }
        $zonasTexto = implode(', ', array_map(function($z) use ($nombresZonas) {
            return $nombresZonas[$z] ?? (string)$z;
        }, $zonasRaw));

        $numeroNota = 'NZ-' . date('Ymd', strtotime($rz['fecha_reserva'])) . '-' . str_pad((string)$rz['id'], 6, '0', STR_PAD_LEFT);
        $total = (float)$rz['precio_total'];

        echo json_encode([
            'success' => true,
            'nota' => [
                'tipo_reserva' => 'zona',
                'numero_nota' => $numeroNota,
                'cliente_nombre' => trim((string)(($_SESSION['cliente_nombre'] ?? '') . ' ' . ($_SESSION['cliente_apellido'] ?? ''))),
                'cliente_email' => (string)($_SESSION['cliente_email'] ?? ''),
                'fecha_reserva' => (string)$rz['fecha_reserva'],
                'hora_reserva' => substr((string)$rz['hora_reserva'], 0, 5),
                'estado' => (string)$rz['estado'],
                'detalle_reserva' => $zonasTexto !== '' ? $zonasTexto : 'Reserva de zona',
                'cantidad_mesas' => (int)($rz['cantidad_mesas'] ?? 0),
                'numero_personas' => (int)$rz['numero_personas'],
                'subtotal_mesa' => $total,
                'subtotal_platos' => 0.0,
                'impuesto' => 0.0,
                'descuento' => 0.0,
                'total' => $total,
                'items' => [],
                'fecha_emision' => (string)($rz['fecha_creacion'] ?? date('Y-m-d H:i:s'))
            ]
        ]);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT r.id, r.fecha_reserva, r.hora_reserva, r.numero_personas, r.estado,
               m.numero_mesa, m.precio_reserva,
               c.nombre, c.apellido, c.email
        FROM reservas r
        INNER JOIN mesas m ON m.id = r.mesa_id
        INNER JOIN clientes c ON c.id = r.cliente_id
        WHERE r.id = :id AND r.cliente_id = :cliente_id
        LIMIT 1
    ");
    $stmt->execute([
        'id' => $reservaId,
        'cliente_id' => $clienteId
    ]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }

    $tieneNotas = tableExists($pdo, 'notas_consumo');
    $tienePrePedidos = tableExists($pdo, 'pre_pedidos');

    $notaDb = null;
    if ($tieneNotas) {
        $stmt = $pdo->prepare("
            SELECT id, numero_nota, subtotal, impuesto, descuento, total, estado, metodo_pago, fecha_generacion, observaciones
            FROM notas_consumo
            WHERE reserva_id = :reserva_id
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(['reserva_id' => $reservaId]);
        $notaDb = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    $items = [];
    $subtotalPlatos = 0.0;
    if ($tienePrePedidos) {
        $stmt = $pdo->prepare("
            SELECT p.nombre, pp.cantidad, pp.precio_unitario, pp.subtotal
            FROM pre_pedidos pp
            LEFT JOIN platos p ON p.id = pp.plato_id
            WHERE pp.reserva_id = :reserva_id
            ORDER BY pp.id ASC
        ");
        $stmt->execute(['reserva_id' => $reservaId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $itemSubtotal = (float)($row['subtotal'] ?? 0);
            $subtotalPlatos += $itemSubtotal;
            $items[] = [
                'nombre' => (string)($row['nombre'] ?? 'Plato'),
                'cantidad' => (int)($row['cantidad'] ?? 1),
                'precio_unitario' => (float)($row['precio_unitario'] ?? 0),
                'subtotal' => $itemSubtotal
            ];
        }
    }

    $subtotalMesa = (float)($reserva['precio_reserva'] ?? 0);
    $impuesto = $notaDb ? (float)($notaDb['impuesto'] ?? 0) : 0.0;
    $descuento = $notaDb ? (float)($notaDb['descuento'] ?? 0) : 0.0;
    $totalCalculado = round($subtotalMesa + $subtotalPlatos + $impuesto - $descuento, 2);

    $numeroNota = $notaDb['numero_nota'] ?? ('NC-' . date('Ymd', strtotime($reserva['fecha_reserva'])) . '-' . str_pad((string)$reservaId, 6, '0', STR_PAD_LEFT));
    $totalFinal = $totalCalculado;
    if ($notaDb) {
        $totalNotaDb = (float)($notaDb['total'] ?? 0);
        $subtotalNotaDb = (float)($notaDb['subtotal'] ?? 0);
        // Compatibilidad con notas antiguas que guardaban solo consumo de platos
        if ($subtotalPlatos > 0 && abs($subtotalNotaDb - $subtotalPlatos) < 0.01) {
            $totalFinal = round($subtotalMesa + $totalNotaDb, 2);
        } else {
            $totalFinal = max($totalNotaDb, $totalCalculado);
        }
    }

    echo json_encode([
        'success' => true,
        'nota' => [
            'tipo_reserva' => 'normal',
            'numero_nota' => $numeroNota,
            'cliente_nombre' => trim((string)$reserva['nombre'] . ' ' . (string)$reserva['apellido']),
            'cliente_email' => (string)($reserva['email'] ?? ''),
            'fecha_reserva' => (string)$reserva['fecha_reserva'],
            'hora_reserva' => substr((string)$reserva['hora_reserva'], 0, 5),
            'estado' => (string)$reserva['estado'],
            'detalle_reserva' => 'Mesa ' . (string)$reserva['numero_mesa'],
            'numero_personas' => (int)$reserva['numero_personas'],
            'subtotal_mesa' => $subtotalMesa,
            'subtotal_platos' => $subtotalPlatos,
            'impuesto' => $impuesto,
            'descuento' => $descuento,
            'total' => $totalFinal,
            'items' => $items,
            'fecha_emision' => $notaDb['fecha_generacion'] ?? date('Y-m-d H:i:s'),
            'metodo_pago' => $notaDb['metodo_pago'] ?? null,
            'observaciones' => $notaDb['observaciones'] ?? null
        ]
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

