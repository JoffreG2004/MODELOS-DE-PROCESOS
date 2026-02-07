<?php
/**
 * Obtener Reservas Activas
 * Lista todas las reservas en estado PREPARANDO o EN_CURSO
 * Para panel de finalización rápida
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación
if (!isset($_SESSION['admin_authenticated']) || $_SESSION['admin_authenticated'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'No autorizado'
    ]);
    exit;
}

require_once '../conexion/db.php';

try {
    // Obtener filtros opcionales
    $filtro_zona = $_GET['zona'] ?? null;
    $filtro_mesa = $_GET['mesa'] ?? null;
    
    // ========================================
    // RESERVAS NORMALES
    // ========================================
    $query = "
        SELECT 
            r.id,
            r.mesa_id,
            m.numero_mesa,
            m.ubicacion as zona,
            c.nombre as cliente_nombre,
            c.apellido as cliente_apellido,
            c.telefono as cliente_telefono,
            r.fecha_reserva,
            TIME_FORMAT(r.hora_reserva, '%H:%i') as hora_reserva,
            r.numero_personas as num_personas,
            r.estado,
            r.duracion_estimada,
            r.cliente_llego,
            r.hora_llegada,
            r.notificacion_noshow_enviada,
            TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) as minutos_transcurridos,
            CASE 
                WHEN r.cliente_llego = 1 THEN 'llegado'
                WHEN TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) > 15 THEN 'no_llego'
                ELSE 'esperando'
            END as estado_llegada,
            'normal' as tipo_reserva
        FROM reservas r
        INNER JOIN mesas m ON r.mesa_id = m.id
        INNER JOIN clientes c ON r.cliente_id = c.id
        WHERE r.estado IN ('preparando', 'en_curso')
    ";
    
    $params = [];
    
    if ($filtro_zona) {
        $query .= " AND m.ubicacion = ?";
        $params[] = $filtro_zona;
    }
    
    if ($filtro_mesa) {
        $query .= " AND m.numero_mesa = ?";
        $params[] = intval($filtro_mesa);
    }
    
    $query .= " ORDER BY r.fecha_reserva, r.hora_reserva";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $reservas_normales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ========================================
    // RESERVAS DE ZONAS
    // ========================================
    $query_zonas = "
        SELECT 
            rz.id,
            NULL as mesa_id,
            'Múltiples' as numero_mesa,
            rz.zonas,
            c.nombre as cliente_nombre,
            c.apellido as cliente_apellido,
            c.telefono as cliente_telefono,
            rz.fecha_reserva,
            TIME_FORMAT(rz.hora_reserva, '%H:%i') as hora_reserva,
            rz.numero_personas as num_personas,
            rz.estado,
            rz.duracion_estimada,
            rz.cliente_llego,
            rz.hora_llegada,
            rz.notificacion_noshow_enviada,
            TIMESTAMPDIFF(MINUTE, TIMESTAMP(rz.fecha_reserva, rz.hora_reserva), NOW()) as minutos_transcurridos,
            CASE 
                WHEN rz.cliente_llego = 1 THEN 'llegado'
                WHEN TIMESTAMPDIFF(MINUTE, TIMESTAMP(rz.fecha_reserva, rz.hora_reserva), NOW()) > 15 THEN 'no_llego'
                ELSE 'esperando'
            END as estado_llegada,
            'zona' as tipo_reserva
        FROM reservas_zonas rz
        INNER JOIN clientes c ON rz.cliente_id = c.id
        WHERE rz.estado IN ('preparando', 'en_curso')
        ORDER BY rz.fecha_reserva, rz.hora_reserva
    ";
    
    $stmt = $pdo->prepare($query_zonas);
    $stmt->execute();
    $reservas_zonas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Combinar ambas listas
    $reservas = array_merge($reservas_normales, $reservas_zonas);
    
    // Ordenar por fecha/hora
    usort($reservas, function($a, $b) {
        $tiempo_a = strtotime($a['fecha_reserva'] . ' ' . $a['hora_reserva']);
        $tiempo_b = strtotime($b['fecha_reserva'] . ' ' . $b['hora_reserva']);
        return $tiempo_a - $tiempo_b;
    });
    
    // Formatear datos para respuesta
    foreach ($reservas as &$reserva) {
        // Formatear duración
        $horas = floor($reserva['duracion_estimada'] / 60);
        $mins = $reserva['duracion_estimada'] % 60;
        $reserva['duracion_formateada'] = $horas > 0 ? "{$horas}h" : "";
        $reserva['duracion_formateada'] .= $mins > 0 ? " {$mins}min" : "";
        $reserva['duracion_formateada'] = trim($reserva['duracion_formateada']);
        
        // Formatear tiempo transcurrido
        $min_trans = $reserva['minutos_transcurridos'];
        $horas_trans = floor(abs($min_trans) / 60);
        $mins_trans = abs($min_trans) % 60;
        
        // Mostrar formato diferente si es negativo (reserva no ha comenzado) o positivo (ya pasó la hora)
        if ($min_trans < 0) {
            // Tiempo hasta la reserva (falta tiempo)
            $reserva['tiempo_transcurrido'] = "En {$horas_trans}h {$mins_trans}min";
        } else {
            // Tiempo desde la reserva (ya pasó)
            $reserva['tiempo_transcurrido'] = "Hace {$horas_trans}h {$mins_trans}min";
        }
        
        // Indicadores visuales
        $reserva['icono_estado'] = [
            'llegado' => '🟢',
            'esperando' => '🟡',
            'no_llego' => '🔴'
        ][$reserva['estado_llegada']] ?? '⚪';
        
        $reserva['texto_estado'] = [
            'llegado' => 'Cliente llegó',
            'esperando' => 'Esperando llegada',
            'no_llego' => 'No ha llegado'
        ][$reserva['estado_llegada']] ?? 'Desconocido';
        
        // Decodificar zonas si es reserva de zona
        if ($reserva['tipo_reserva'] === 'zona') {
            $zonas_array = json_decode($reserva['zonas'], true);
            $nombres_zonas = [
                'interior' => 'Salón Principal',
                'terraza' => 'Terraza',
                'vip' => 'Área VIP',
                'bar' => 'Bar & Lounge'
            ];
            $reserva['zona'] = implode(', ', array_map(function($z) use ($nombres_zonas) {
                return $nombres_zonas[$z] ?? $z;
            }, $zonas_array));
        }
    }
    
    echo json_encode([
        'success' => true,
        'total' => count($reservas),
        'data' => $reservas
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
