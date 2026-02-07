<?php
/**
 * Modelo Reserva
 * Gestión de reservas del restaurante
 */

require_once __DIR__ . '/../config/database.php';

class Reserva {
    private $db;
    private $table = 'reservas';
    
    public $id;
    public $cliente_id;
    public $mesa_id;
    public $fecha_reserva;
    public $hora_reserva;
    public $num_personas;
    public $estado;
    public $notas;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    /**
     * Obtener todas las reservas
     */
    public function getAll() {
        $query = "SELECT r.*, 
                         c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.email as cliente_email,
                         m.numero_mesa, m.precio_reserva
                  FROM {$this->table} r
                  LEFT JOIN clientes c ON r.cliente_id = c.id
                  LEFT JOIN mesas m ON r.mesa_id = m.id
                  ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener reservas por cliente
     */
    public function getByCliente($cliente_id) {
        $query = "SELECT r.*, m.numero_mesa, m.ubicacion, m.precio_reserva
                  FROM {$this->table} r
                  LEFT JOIN mesas m ON r.mesa_id = m.id
                  WHERE r.cliente_id = ?
                  ORDER BY r.fecha_reserva DESC, r.hora_reserva DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$cliente_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener reserva por ID
     */
    public function getById($id) {
        $query = "SELECT r.*, 
                         c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.email as cliente_email,
                         m.numero_mesa, m.ubicacion, m.precio_reserva
                  FROM {$this->table} r
                  LEFT JOIN clientes c ON r.cliente_id = c.id
                  LEFT JOIN mesas m ON r.mesa_id = m.id
                  WHERE r.id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nueva reserva
     */
    public function create($data) {
        $query = "INSERT INTO {$this->table} 
                  (cliente_id, mesa_id, fecha_reserva, hora_reserva, num_personas, estado, notas) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($query);
        
        $result = $stmt->execute([
            $data['cliente_id'],
            $data['mesa_id'],
            $data['fecha_reserva'],
            $data['hora_reserva'],
            $data['num_personas'],
            $data['estado'] ?? 'pendiente',
            $data['notas'] ?? ''
        ]);
        
        if ($result) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Actualizar reserva
     */
    public function update($id, $data) {
        $query = "UPDATE {$this->table} 
                  SET fecha_reserva = ?, hora_reserva = ?, num_personas = ?, estado = ?, notas = ?
                  WHERE id = ?";
        
        $stmt = $this->db->prepare($query);
        
        return $stmt->execute([
            $data['fecha_reserva'],
            $data['hora_reserva'],
            $data['num_personas'],
            $data['estado'],
            $data['notas'] ?? '',
            $id
        ]);
    }
    
    /**
     * Cambiar estado de reserva
     */
    public function cambiarEstado($id, $estado) {
        $query = "UPDATE {$this->table} SET estado = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$estado, $id]);
    }
    
    /**
     * Eliminar reserva
     */
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$id]);
    }
    
    /**
     * Verificar disponibilidad de mesa
     * VALIDACIÓN 1: No permitir reservas duplicadas exactas (misma mesa/fecha/hora)
     * VALIDACIÓN 2: Requiere mínimo 3 horas de separación entre reservas
     * Incluye 1 hora de preparación antes + duración estimada + limpieza
     */
    public function verificarDisponibilidad($mesa_id, $fecha, $hora, $excluir_reserva_id = null) {
        // VALIDACIÓN 1: Verificar si ya existe una reserva EXACTA (mismo día, hora y mesa)
        // Esto previene que múltiples personas reserven la misma mesa a la misma hora
        $queryDuplicado = "SELECT COUNT(*) as duplicados,
                                  GROUP_CONCAT(
                                      CONCAT('Reserva #', id, ' - Estado: ', estado)
                                      SEPARATOR ' | '
                                  ) as detalles_duplicado
                           FROM {$this->table} 
                           WHERE mesa_id = ? 
                           AND fecha_reserva = ? 
                           AND hora_reserva = ?
                           AND estado IN ('pendiente', 'confirmada', 'preparando', 'en_curso')";
        
        $paramsDuplicado = [$mesa_id, $fecha, $hora];
        
        if ($excluir_reserva_id) {
            $queryDuplicado .= " AND id != ?";
            $paramsDuplicado[] = $excluir_reserva_id;
        }
        
        $stmtDuplicado = $this->db->prepare($queryDuplicado);
        $stmtDuplicado->execute($paramsDuplicado);
        $resultadoDuplicado = $stmtDuplicado->fetch(PDO::FETCH_ASSOC);
        
        // Si hay duplicados exactos, rechazar de inmediato
        if ($resultadoDuplicado['duplicados'] > 0) {
            return false;
        }
        
        // VALIDACIÓN 2: Verificar separación mínima de 3 horas
        // Configuración: Mínimo 3 horas de separación entre reservas de la misma mesa
        // Esto incluye: 1h preparación + 2h reserva promedio
        $tiempo_minimo_separacion = 180; // 3 horas en minutos
        
        // Verificar conflictos considerando:
        // 1. Preparación: 1 hora ANTES de la reserva
        // 2. Duración: tiempo estimado de la reserva (o 2h por defecto)
        // 3. Limpieza: incluido en el tiempo de separación
        
        $query = "SELECT COUNT(*) as conflictos,
                         GROUP_CONCAT(
                             CONCAT('Reserva #', id, ' a las ', TIME_FORMAT(hora_reserva, '%H:%i'))
                             SEPARATOR ', '
                         ) as detalles_conflicto
                  FROM {$this->table} 
                  WHERE mesa_id = ? 
                  AND fecha_reserva = ? 
                  AND estado IN ('pendiente', 'confirmada', 'preparando', 'en_curso')
                  AND (
                      -- Verificar que la nueva reserva no esté muy cerca de una existente
                      -- Nueva reserva debe estar al menos 3 horas antes o después
                      ABS(TIMESTAMPDIFF(MINUTE, 
                          TIMESTAMP(?, ?), 
                          TIMESTAMP(fecha_reserva, hora_reserva)
                      )) < ?
                  )";
        
        $params = [$mesa_id, $fecha, $fecha, $hora, $tiempo_minimo_separacion];
        
        if ($excluir_reserva_id) {
            $query .= " AND id != ?";
            $params[] = $excluir_reserva_id;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Retornar true si NO hay conflictos
        return $resultado['conflictos'] == 0;
    }
    
    /**
     * Verificar disponibilidad con detalles (para mostrar mensajes al usuario)
     */
    public function verificarDisponibilidadConDetalles($mesa_id, $fecha, $hora, $excluir_reserva_id = null) {
        $tiempo_minimo_separacion = 180; // 3 horas
        
        $query = "SELECT 
                      id,
                      TIME_FORMAT(hora_reserva, '%H:%i') as hora_formateada,
                      TIMESTAMPDIFF(MINUTE, ?, hora_reserva) as diferencia_minutos,
                      estado,
                      num_personas
                  FROM {$this->table} 
                  WHERE mesa_id = ? 
                  AND fecha_reserva = ? 
                  AND estado IN ('pendiente', 'confirmada', 'preparando', 'en_curso')
                  AND ABS(TIMESTAMPDIFF(MINUTE, ?, hora_reserva)) < ?";
        
        $params = [$hora, $mesa_id, $fecha, $hora, $tiempo_minimo_separacion];
        
        if ($excluir_reserva_id) {
            $query .= " AND id != ?";
            $params[] = $excluir_reserva_id;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $conflictos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'disponible' => count($conflictos) == 0,
            'conflictos' => $conflictos,
            'tiempo_minimo' => $tiempo_minimo_separacion
        ];
    }
    
    /**
     * Obtener reservas recientes (últimas 10)
     */
    public function getRecientes($limit = 10) {
        $query = "SELECT r.*, 
                         c.nombre as cliente_nombre, c.apellido as cliente_apellido,
                         m.numero_mesa
                  FROM {$this->table} r
                  LEFT JOIN clientes c ON r.cliente_id = c.id
                  LEFT JOIN mesas m ON r.mesa_id = m.id
                  ORDER BY r.fecha_creacion DESC
                  LIMIT ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * NUEVO: Validación de bloques de 3 horas
     * - Reservas normales: máximo 3 horas, después se puede reservar otra
     * - Si reserva a las 11:00, está disponible a las 14:00
     * - El admin puede liberar antes si el cliente se fue
     */
    public function validarBloquesDeTresHoras($mesa_id, $fecha, $hora_inicio, $es_zona_completa = false) {
        $duracion_bloque = 3; // horas
        
        if ($es_zona_completa) {
            // Zona completa: verificar si hay CUALQUIER reserva ese día
            $query = "SELECT COUNT(*) as conflictos
                      FROM {$this->table}
                      WHERE mesa_id = ?
                      AND DATE(fecha_reserva) = ?
                      AND estado IN ('confirmada', 'pendiente', 'preparando', 'en_curso')";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$mesa_id, $fecha]);
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $resultado['conflictos'] == 0;
        } else {
            // Reserva normal de 3 horas
            // Convertir hora a minutos desde medianoche
            $partes = explode(':', $hora_inicio);
            $minutos_inicio = $partes[0] * 60 + $partes[1];
            $minutos_fin = $minutos_inicio + ($duracion_bloque * 60);
            
            // Buscar conflictos con reservas existentes
            $query = "SELECT r.*,
                             TIME_TO_SEC(r.hora_reserva) as segundos_inicio,
                             (TIME_TO_SEC(r.hora_reserva) + (COALESCE(r.duracion_horas, 3) * 3600)) as segundos_fin
                      FROM {$this->table} r
                      WHERE r.mesa_id = ?
                      AND DATE(r.fecha_reserva) = ?
                      AND r.estado IN ('confirmada', 'pendiente', 'preparando', 'en_curso')";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$mesa_id, $fecha]);
            $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Verificar si hay conflictos (superposición de horarios)
            foreach ($reservas as $res) {
                $segundos_inicio_nuevo = $minutos_inicio * 60;
                $segundos_fin_nuevo = $minutos_fin * 60;
                
                $segundos_inicio_res = $res['segundos_inicio'];
                $segundos_fin_res = $res['segundos_fin'];
                
                // Si hay solapamiento, no disponible
                if ($segundos_inicio_nuevo < $segundos_fin_res && $segundos_fin_nuevo > $segundos_inicio_res) {
                    return false;
                }
            }
            
            return true;
        }
    }
    
    /**
     * Obtener el próximo horario disponible después de un horario ocupado
     */
    public function obtenerProximoHorarioDisponible($mesa_id, $fecha, $hora_ocupada) {
        // Si la reserva ocupa 3 horas desde $hora_ocupada, 
        // el próximo disponible es hora_ocupada + 3 horas
        $fecha_hora = DateTime::createFromFormat('H:i', $hora_ocupada);
        $fecha_hora->modify('+3 hours');
        return $fecha_hora->format('H:i');
    }
}
?>
