<?php
session_start();
header('Content-Type: application/json');
require_once '../../conexion/db.php';

// Verificar que sea un administrador autenticado
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Obtener parámetros
    $fechaInicio = $_GET['fecha_inicio'] ?? null;
    $fechaFin = $_GET['fecha_fin'] ?? null;
    $mesaId = $_GET['mesa_id'] ?? null;

    // Construir la consulta SQL para reservas normales
    $sql = "SELECT 
                r.id,
                r.mesa_id,
                r.cliente_id,
                r.fecha_reserva,
                r.hora_reserva,
                r.numero_personas,
                r.estado,
                r.motivo_cancelacion,
                m.numero_mesa,
                m.ubicacion,
                m.capacidad_minima,
                m.capacidad_maxima,
                m.precio_reserva,
                c.nombre as cliente_nombre,
                c.apellido as cliente_apellido,
                c.telefono as cliente_telefono,
                c.email as cliente_email
            FROM reservas r
            INNER JOIN mesas m ON r.mesa_id = m.id
            INNER JOIN clientes c ON r.cliente_id = c.id
            WHERE 1=1";

    $params = [];

    // Agregar filtros 
    if ($fechaInicio) {
        $sql .= " AND r.fecha_reserva >= :fecha_inicio";
        $params[':fecha_inicio'] = $fechaInicio;
    }

    if ($fechaFin) {
        $sql .= " AND r.fecha_reserva <= :fecha_fin";
        $params[':fecha_fin'] = $fechaFin;
    }

    if ($mesaId && $mesaId !== '') {
        $sql .= " AND r.mesa_id = :mesa_id";
        $params[':mesa_id'] = $mesaId;
    }

    // Ordenar por fecha y hora
    $sql .= " ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";

    // Ejecutar consulta
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Marcar tipo para reservas normales
    foreach ($reservas as &$r) {
        $r['tipo_reserva'] = 'mesa';
    }
    unset($r);

    // Incluir reservas de zona si no se filtra por mesa
    if (!$mesaId || $mesaId === '') {
        $sqlZonas = "SELECT 
                        rz.id,
                        NULL as mesa_id,
                        rz.cliente_id,
                        rz.fecha_reserva,
                        rz.hora_reserva,
                        rz.numero_personas,
                        rz.estado,
                        rz.motivo_cancelacion,
                        NULL as numero_mesa,
                        NULL as ubicacion,
                        NULL as capacidad_minima,
                        NULL as capacidad_maxima,
                        rz.precio_total as precio_reserva,
                        c.nombre as cliente_nombre,
                        c.apellido as cliente_apellido,
                        c.telefono as cliente_telefono,
                        c.email as cliente_email,
                        rz.zonas
                    FROM reservas_zonas rz
                    INNER JOIN clientes c ON rz.cliente_id = c.id
                    WHERE 1=1";
        $paramsZ = [];
        if ($fechaInicio) {
            $sqlZonas .= " AND rz.fecha_reserva >= :fecha_inicio";
            $paramsZ[':fecha_inicio'] = $fechaInicio;
        }
        if ($fechaFin) {
            $sqlZonas .= " AND rz.fecha_reserva <= :fecha_fin";
            $paramsZ[':fecha_fin'] = $fechaFin;
        }
        $sqlZonas .= " ORDER BY rz.fecha_reserva DESC, rz.hora_reserva DESC";

        $stmtZ = $pdo->prepare($sqlZonas);
        $stmtZ->execute($paramsZ);
        $reservasZonas = $stmtZ->fetchAll(PDO::FETCH_ASSOC);

        $nombres_zonas = [
            'interior' => 'Salón Principal',
            'terraza' => 'Terraza',
            'vip' => 'Área VIP',
            'bar' => 'Bar & Lounge'
        ];

        foreach ($reservasZonas as &$z) {
            $z['tipo_reserva'] = 'zona';
            $zonasArr = json_decode($z['zonas'] ?? '[]', true);
            if (!is_array($zonasArr)) {
                $zonasArr = [];
            }
            $z['zonas_nombres'] = array_map(function($zn) use ($nombres_zonas) {
                return $nombres_zonas[$zn] ?? $zn;
            }, $zonasArr);
        }
        unset($z);

        $reservas = array_merge($reservas, $reservasZonas);
    }

    // Ordenar combinado por fecha/hora
    usort($reservas, function($a, $b) {
        $fa = $a['fecha_reserva'] . ' ' . $a['hora_reserva'];
        $fb = $b['fecha_reserva'] . ' ' . $b['hora_reserva'];
        return strcmp($fb, $fa);
    });

    echo json_encode([
        'success' => true,
        'reservas' => $reservas,
        'total' => count($reservas)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener reservas: ' . $e->getMessage()
    ]);
}
