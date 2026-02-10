<?php
/**
 * API para consultar auditoría del sistema
 */

header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once __DIR__ . '/../../conexion/db.php';
require_once __DIR__ . '/../../controllers/AuditoriaController.php';

if (
    !isset($_SESSION['admin_id']) ||
    !isset($_SESSION['admin_authenticated']) ||
    $_SESSION['admin_authenticated'] !== true
) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

function tableExists(PDO $pdo, $tableName) {
    $stmt = $pdo->prepare("
        SELECT COUNT(1)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
    ");
    $stmt->execute([$tableName]);
    return ((int)$stmt->fetchColumn()) > 0;
}

function safeDate($value) {
    if (!$value) {
        return null;
    }
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y H:i:s', $ts) : null;
}

function normalizeDate(?string $value): ?string {
    $value = trim((string)$value);
    if ($value === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $value));
    if (!checkdate($m, $d, $y)) {
        return null;
    }
    return $value;
}

function bindStatementParams(PDOStatement $stmt, array $params): void {
    foreach ($params as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }
}

try {
    $auditoriaController = new AuditoriaController($pdo);

    $tipo = strtolower(trim((string)($_GET['tipo'] ?? 'horarios')));
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 50;
    $limite = max(1, min($limite, 500));
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $q = trim((string)($_GET['q'] ?? ''));
    if (strlen($q) > 120) {
        $q = substr($q, 0, 120);
    }
    $qLike = $q !== '' ? '%' . $q . '%' : null;
    $fechaInicio = normalizeDate($_GET['fecha_inicio'] ?? null);
    $fechaFin = normalizeDate($_GET['fecha_fin'] ?? null);
    if ($fechaInicio && $fechaFin && $fechaInicio > $fechaFin) {
        [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
    }

    $hasHorarios = tableExists($pdo, 'auditoria_horarios');
    $hasReservas = tableExists($pdo, 'auditoria_reservas');
    $hasSistema = tableExists($pdo, 'auditoria_sistema');

    switch ($tipo) {
        case 'horarios':
            if (!$hasHorarios) {
                echo json_encode([
                    'success' => true,
                    'tipo' => 'horarios',
                    'total' => 0,
                    'datos' => [],
                    'warning' => 'La tabla auditoria_horarios no existe en esta instalación'
                ]);
                break;
            }

            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT 
                        h.id,
                        h.admin_id,
                        CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,'')) as admin_nombre,
                        h.accion,
                        h.fecha_cambio,
                        h.configuracion_anterior,
                        h.configuracion_nueva,
                        h.reservas_afectadas,
                        h.reservas_canceladas,
                        h.notificaciones_enviadas,
                        h.observaciones,
                        h.ip_address,
                        h.user_agent
                    FROM auditoria_horarios h
                    LEFT JOIN administradores u ON h.admin_id = u.id
                    WHERE h.id = :id
                    LIMIT 1
                ");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $datosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = "
                    SELECT 
                        h.id,
                        h.admin_id,
                        CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,'')) as admin_nombre,
                        h.accion,
                        h.fecha_cambio,
                        h.configuracion_anterior,
                        h.configuracion_nueva,
                        h.reservas_afectadas,
                        h.reservas_canceladas,
                        h.notificaciones_enviadas,
                        h.observaciones,
                        h.ip_address,
                        h.user_agent
                    FROM auditoria_horarios h
                    LEFT JOIN administradores u ON h.admin_id = u.id
                    WHERE 1=1
                ";
                $params = [];
                if ($fechaInicio) {
                    $sql .= " AND DATE(h.fecha_cambio) >= :fecha_inicio ";
                    $params[':fecha_inicio'] = $fechaInicio;
                }
                if ($fechaFin) {
                    $sql .= " AND DATE(h.fecha_cambio) <= :fecha_fin ";
                    $params[':fecha_fin'] = $fechaFin;
                }
                if ($qLike) {
                    $sql .= " AND (
                        CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido,'')) LIKE :q
                        OR h.accion LIKE :q
                        OR h.observaciones LIKE :q
                        OR h.ip_address LIKE :q
                    )";
                    $params[':q'] = $qLike;
                }
                $sql .= " ORDER BY h.fecha_cambio DESC LIMIT :limite ";
                $stmt = $pdo->prepare($sql);
                bindStatementParams($stmt, $params);
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                $stmt->execute();
                $datosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $resultado = array_map(function($item) {
                return [
                    'id' => (int)$item['id'],
                    'admin' => trim((string)($item['admin_nombre'] ?? '')) ?: 'Sistema',
                    'accion' => (string)($item['accion'] ?? 'Cambio de horarios'),
                    'fecha' => safeDate($item['fecha_cambio']) ?? (string)($item['fecha_cambio'] ?? ''),
                    'reservas_afectadas' => (int)($item['reservas_afectadas'] ?? 0),
                    'reservas_canceladas' => (int)($item['reservas_canceladas'] ?? 0),
                    'notificaciones_enviadas' => (int)($item['notificaciones_enviadas'] ?? 0),
                    'configuracion_anterior' => $item['configuracion_anterior'] ?? null,
                    'configuracion_nueva' => $item['configuracion_nueva'] ?? null,
                    'observaciones' => $item['observaciones'] ?? null,
                    'ip' => $item['ip_address'] ?? null
                ];
            }, $datosRaw);

            echo json_encode([
                'success' => true,
                'tipo' => 'horarios',
                'total' => count($resultado),
                'datos' => $resultado
            ]);
            break;

        case 'reservas':
            if (!$hasReservas) {
                echo json_encode([
                    'success' => true,
                    'tipo' => 'reservas',
                    'total' => 0,
                    'datos' => [],
                    'warning' => 'La tabla auditoria_reservas no existe en esta instalación'
                ]);
                break;
            }

            $reservaIdFiltro = isset($_GET['reserva_id']) ? (int)$_GET['reserva_id'] : null;

            if ($id) {
                $stmt = $pdo->prepare("
                    SELECT 
                        ar.*,
                        CONCAT(COALESCE(a.nombre,''), ' ', COALESCE(a.apellido,'')) as admin_nombre_completo
                    FROM auditoria_reservas ar
                    LEFT JOIN administradores a ON ar.admin_id = a.id
                    WHERE ar.id = :id
                    LIMIT 1
                ");
                $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
                $datosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $sql = "
                    SELECT 
                        ar.*,
                        CONCAT(COALESCE(a.nombre,''), ' ', COALESCE(a.apellido,'')) as admin_nombre_completo
                    FROM auditoria_reservas ar
                    LEFT JOIN administradores a ON ar.admin_id = a.id
                    WHERE 1=1
                ";
                $params = [];

                if ($reservaIdFiltro && $reservaIdFiltro > 0) {
                    $sql .= " AND ar.reserva_id = :reserva_id ";
                    $params[':reserva_id'] = $reservaIdFiltro;
                }
                if ($fechaInicio) {
                    $sql .= " AND DATE(ar.fecha_accion) >= :fecha_inicio ";
                    $params[':fecha_inicio'] = $fechaInicio;
                }
                if ($fechaFin) {
                    $sql .= " AND DATE(ar.fecha_accion) <= :fecha_fin ";
                    $params[':fecha_fin'] = $fechaFin;
                }
                if ($qLike) {
                    $sql .= " AND (
                        CAST(ar.reserva_id AS CHAR) LIKE :q
                        OR ar.accion LIKE :q
                        OR ar.estado_anterior LIKE :q
                        OR ar.estado_nuevo LIKE :q
                        OR ar.motivo LIKE :q
                        OR CONCAT(COALESCE(a.nombre,''), ' ', COALESCE(a.apellido,'')) LIKE :q
                        OR ar.ip_address LIKE :q
                    )";
                    $params[':q'] = $qLike;
                }

                $sql .= " ORDER BY ar.fecha_accion DESC LIMIT :limite ";
                $stmt = $pdo->prepare($sql);
                bindStatementParams($stmt, $params);
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                $stmt->execute();
                $datosRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $resultado = array_map(function($item) {
                return [
                    'id' => (int)$item['id'],
                    'reserva_id' => (int)$item['reserva_id'],
                    'admin' => trim((string)($item['admin_nombre_completo'] ?? '')) ?: 'Sistema',
                    'accion' => (string)($item['accion'] ?? ''),
                    'fecha' => safeDate($item['fecha_accion']) ?? (string)($item['fecha_accion'] ?? ''),
                    'estado_anterior' => $item['estado_anterior'] ?? null,
                    'estado_nuevo' => $item['estado_nuevo'] ?? null,
                    'datos_anteriores' => json_decode((string)($item['datos_anteriores'] ?? ''), true),
                    'datos_nuevos' => json_decode((string)($item['datos_nuevos'] ?? ''), true),
                    'motivo' => $item['motivo'] ?? null,
                    'ip' => $item['ip_address'] ?? null
                ];
            }, $datosRaw);

            echo json_encode([
                'success' => true,
                'tipo' => 'reservas',
                'total' => count($resultado),
                'datos' => $resultado
            ]);
            break;

        // Compatibilidad backward: historial por reserva específica
        case 'reserva':
            if (!$hasReservas) {
                throw new Exception('La tabla auditoria_reservas no existe en esta instalación');
            }
            $reservaId = isset($_GET['reserva_id']) ? (int)$_GET['reserva_id'] : 0;
            if ($reservaId <= 0) {
                throw new Exception('Se requiere reserva_id');
            }
            $datosRaw = $auditoriaController->obtenerHistorialReserva($reservaId);
            $datosRaw = array_slice($datosRaw, 0, $limite);
            $resultado = array_map(function($item) {
                return [
                    'id' => (int)$item['id'],
                    'reserva_id' => (int)$item['reserva_id'],
                    'admin' => trim((string)($item['admin_nombre_completo'] ?? '')) ?: 'Sistema',
                    'accion' => (string)($item['accion'] ?? ''),
                    'fecha' => safeDate($item['fecha_accion']) ?? (string)($item['fecha_accion'] ?? ''),
                    'estado_anterior' => $item['estado_anterior'] ?? null,
                    'estado_nuevo' => $item['estado_nuevo'] ?? null,
                    'datos_anteriores' => json_decode((string)($item['datos_anteriores'] ?? ''), true),
                    'datos_nuevos' => json_decode((string)($item['datos_nuevos'] ?? ''), true),
                    'motivo' => $item['motivo'] ?? null,
                    'ip' => $item['ip_address'] ?? null
                ];
            }, $datosRaw);

            echo json_encode([
                'success' => true,
                'tipo' => 'reserva',
                'reserva_id' => $reservaId,
                'total' => count($resultado),
                'datos' => $resultado
            ]);
            break;

        case 'admin':
            $adminId = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : (int)$_SESSION['admin_id'];
            $fechaInicio = $_GET['fecha_inicio'] ?? null;
            $fechaFin = $_GET['fecha_fin'] ?? null;
            $datos = [];

            if ($hasHorarios || $hasReservas) {
                if ($hasHorarios && $hasReservas) {
                    $datos = $auditoriaController->obtenerAccionesAdmin($adminId, $fechaInicio, $fechaFin, $limite);
                } else {
                    // Fallback cuando solo existe una de las dos tablas
                    if ($hasHorarios) {
                        $stmt = $pdo->prepare("
                            SELECT 'horarios' as tipo, accion, fecha_cambio as fecha,
                                   reservas_afectadas, observaciones
                            FROM auditoria_horarios
                            WHERE admin_id = ?
                            ORDER BY fecha_cambio DESC
                            LIMIT ?
                        ");
                        $stmt->execute([$adminId, $limite]);
                        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $stmt = $pdo->prepare("
                            SELECT 'reservas' as tipo, accion, fecha_accion as fecha,
                                   NULL as reservas_afectadas, motivo as observaciones
                            FROM auditoria_reservas
                            WHERE admin_id = ?
                            ORDER BY fecha_accion DESC
                            LIMIT ?
                        ");
                        $stmt->execute([$adminId, $limite]);
                        $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }
            }

            $datos = array_map(function($item) {
                $item['fecha'] = safeDate($item['fecha']) ?? (string)($item['fecha'] ?? '');
                return $item;
            }, $datos);

            echo json_encode([
                'success' => true,
                'tipo' => 'admin',
                'admin_id' => $adminId,
                'total' => count($datos),
                'datos' => $datos
            ]);
            break;

        case 'sistema':
            if (!$hasSistema) {
                echo json_encode([
                    'success' => true,
                    'tipo' => 'sistema',
                    'total' => 0,
                    'datos' => [],
                    'warning' => 'La tabla auditoria_sistema no existe en esta instalación'
                ]);
                break;
            }
            $stmt = $pdo->prepare("
                SELECT *
                FROM auditoria_sistema
                ORDER BY fecha_hora DESC
                LIMIT ?
            ");
            $stmt->execute([$limite]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'tipo' => 'sistema',
                'total' => count($rows),
                'datos' => $rows
            ]);
            break;

        case 'resumen':
            $resumen = [
                'total_cambios_horarios' => 0,
                'total_acciones_reservas' => 0,
                'cambios_horarios_hoy' => 0,
                'acciones_reservas_hoy' => 0,
                'total_reservas_canceladas' => 0,
                'total_notificaciones' => 0
            ];

            if ($hasHorarios) {
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_cambios_horarios,
                        SUM(CASE WHEN DATE(fecha_cambio) = CURDATE() THEN 1 ELSE 0 END) as cambios_horarios_hoy,
                        COALESCE(SUM(reservas_canceladas), 0) as total_reservas_canceladas,
                        COALESCE(SUM(notificaciones_enviadas), 0) as total_notificaciones
                    FROM auditoria_horarios
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $resumen['total_cambios_horarios'] = (int)($row['total_cambios_horarios'] ?? 0);
                $resumen['cambios_horarios_hoy'] = (int)($row['cambios_horarios_hoy'] ?? 0);
                $resumen['total_reservas_canceladas'] = (int)($row['total_reservas_canceladas'] ?? 0);
                $resumen['total_notificaciones'] = (int)($row['total_notificaciones'] ?? 0);
            }

            if ($hasReservas) {
                $stmt = $pdo->query("
                    SELECT 
                        COUNT(*) as total_acciones_reservas,
                        SUM(CASE WHEN DATE(fecha_accion) = CURDATE() THEN 1 ELSE 0 END) as acciones_reservas_hoy
                    FROM auditoria_reservas
                ");
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $resumen['total_acciones_reservas'] = (int)($row['total_acciones_reservas'] ?? 0);
                $resumen['acciones_reservas_hoy'] = (int)($row['acciones_reservas_hoy'] ?? 0);
            }

            $ultimosCambios = [];
            if ($hasHorarios) {
                $stmtUltimos = $pdo->query("
                    SELECT admin_nombre, fecha_cambio, reservas_afectadas, observaciones
                    FROM auditoria_horarios
                    ORDER BY fecha_cambio DESC
                    LIMIT 5
                ");
                $ultimosCambios = $stmtUltimos->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'success' => true,
                'tipo' => 'resumen',
                'resumen' => $resumen,
                'ultimos_cambios' => $ultimosCambios,
                'tablas_disponibles' => [
                    'auditoria_horarios' => $hasHorarios,
                    'auditoria_reservas' => $hasReservas,
                    'auditoria_sistema' => $hasSistema
                ]
            ]);
            break;

        default:
            throw new Exception('Tipo de auditoría no válido');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
