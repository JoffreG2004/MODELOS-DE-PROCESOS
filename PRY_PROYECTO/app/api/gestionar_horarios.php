<?php
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once '../../conexion/db.php';

// Verificar autenticación de administrador
if (!isset($_SESSION['admin_id']) || !$_SESSION['admin_authenticated']) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No autorizado'
    ]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'obtener';
    $forzarActualizacion = $input['forzar'] ?? false; // Para confirmar cambios con reservas afectadas
    
    switch ($action) {
        case 'obtener':
            // Obtener todas las configuraciones
            $stmt = $pdo->query("SELECT * FROM configuracion_restaurante ORDER BY clave");
            $configuraciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Convertir a formato más amigable
            $config = [];
            foreach ($configuraciones as $conf) {
                $config[$conf['clave']] = [
                    'valor' => $conf['valor'],
                    'descripcion' => $conf['descripcion']
                ];
            }
            
            echo json_encode([
                'success' => true,
                'configuracion' => $config
            ]);
            break;
            
        case 'actualizar':
            // Actualizar múltiples configuraciones
            $configuraciones = $input['configuraciones'] ?? [];
            
            if (empty($configuraciones)) {
                throw new Exception('No se enviaron configuraciones');
            }
            
            // VALIDAR SI HAY RESERVAS AFECTADAS POR EL CAMBIO DE HORARIO
            $reservasAfectadas = [];
            
            // Si NO se está forzando la actualización, hacer validación
            if (!$forzarActualizacion) {
                // Obtener configuraciones actuales
                $stmt = $pdo->query("SELECT clave, valor FROM configuracion_restaurante");
                $configActual = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $configActual[$row['clave']] = $row['valor'];
                }
                
                // Verificar cambios en horarios
                $camposHorario = [
                    'hora_apertura', 'hora_cierre',
                    'horario_lunes_viernes_inicio', 'horario_lunes_viernes_fin',
                    'horario_sabado_inicio', 'horario_sabado_fin',
                    'horario_domingo_inicio', 'horario_domingo_fin'
                ];
                
                $huyCambioHorario = false;
                foreach ($camposHorario as $campo) {
                    if (isset($configuraciones[$campo]) && 
                        isset($configActual[$campo]) && 
                        $configuraciones[$campo] !== $configActual[$campo]) {
                        $huyCambioHorario = true;
                        break;
                    }
                }
            
            // Si hay cambio de horario, verificar reservas futuras afectadas
            if ($huyCambioHorario) {
                $fechaHoy = date('Y-m-d');
                
                // Obtener todas las reservas futuras pendientes o confirmadas
                $stmt = $pdo->prepare("
                    SELECT r.id, r.fecha_reserva, r.hora_reserva, r.numero_personas,
                           m.numero_mesa, c.nombre, c.apellido, c.email, c.telefono
                    FROM reservas r
                    INNER JOIN mesas m ON r.mesa_id = m.id
                    INNER JOIN clientes c ON r.cliente_id = c.id
                    WHERE r.fecha_reserva >= :fecha_hoy
                    AND r.estado IN ('pendiente', 'confirmada')
                    ORDER BY r.fecha_reserva, r.hora_reserva
                ");
                $stmt->execute(['fecha_hoy' => $fechaHoy]);
                $reservasFuturas = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Verificar cada reserva contra los nuevos horarios
                foreach ($reservasFuturas as $reserva) {
                    $fecha = $reserva['fecha_reserva'];
                    $hora = substr($reserva['hora_reserva'], 0, 5); // HH:MM
                    $diaSemana = date('N', strtotime($fecha));
                    
                    // Determinar horario nuevo según día
                    if ($diaSemana >= 1 && $diaSemana <= 5) {
                        $horaInicio = $configuraciones['horario_lunes_viernes_inicio'] ?? $configuraciones['hora_apertura'] ?? $configActual['hora_apertura'] ?? '10:00';
                        $horaFin = $configuraciones['horario_lunes_viernes_fin'] ?? $configuraciones['hora_cierre'] ?? $configActual['hora_cierre'] ?? '22:00';
                    } elseif ($diaSemana == 6) {
                        $horaInicio = $configuraciones['horario_sabado_inicio'] ?? $configuraciones['hora_apertura'] ?? $configActual['hora_apertura'] ?? '11:00';
                        $horaFin = $configuraciones['horario_sabado_fin'] ?? $configuraciones['hora_cierre'] ?? $configActual['hora_cierre'] ?? '23:00';
                    } else {
                        $horaInicio = $configuraciones['horario_domingo_inicio'] ?? $configuraciones['hora_apertura'] ?? $configActual['hora_apertura'] ?? '12:00';
                        $horaFin = $configuraciones['horario_domingo_fin'] ?? $configuraciones['hora_cierre'] ?? $configActual['hora_cierre'] ?? '21:00';
                    }
                    
                    // Verificar si la reserva queda fuera del nuevo horario
                    if ($hora < $horaInicio || $hora > $horaFin) {
                        $reservasAfectadas[] = [
                            'id' => $reserva['id'],
                            'cliente' => $reserva['nombre'] . ' ' . $reserva['apellido'],
                            'email' => $reserva['email'],
                            'telefono' => $reserva['telefono'],
                            'fecha' => date('d/m/Y', strtotime($fecha)),
                            'hora' => $hora,
                            'mesa' => $reserva['numero_mesa'],
                            'personas' => $reserva['numero_personas'],
                            'nuevo_horario' => "$horaInicio - $horaFin",
                            'problema' => $hora < $horaInicio ? 'antes_apertura' : 'despues_cierre'
                        ];
                    }
                }
                
                // Si hay reservas afectadas, devolver advertencia SIN actualizar
                if (!empty($reservasAfectadas)) {
                    echo json_encode([
                        'success' => false,
                        'advertencia' => true,
                        'message' => 'Hay ' . count($reservasAfectadas) . ' reserva(s) que quedarían fuera del nuevo horario',
                        'reservas_afectadas' => $reservasAfectadas,
                        'requiere_confirmacion' => true
                    ]);
                    exit;
                }
            }
            }
            
            // Si no hay reservas afectadas o se forzó la actualización, proceder
            $pdo->beginTransaction();
            
            foreach ($configuraciones as $clave => $valor) {
                // Usar INSERT ... ON DUPLICATE KEY UPDATE para crear o actualizar
                $stmt = $pdo->prepare("
                    INSERT INTO configuracion_restaurante (clave, valor, descripcion) 
                    VALUES (:clave, :valor, :descripcion)
                    ON DUPLICATE KEY UPDATE valor = VALUES(valor)
                ");
                
                $descripciones = [
                    'hora_apertura' => 'Hora de apertura del restaurante',
                    'hora_cierre' => 'Hora de cierre del restaurante',
                    'horario_lunes_viernes_inicio' => 'Hora de apertura de lunes a viernes',
                    'horario_lunes_viernes_fin' => 'Hora de cierre de lunes a viernes',
                    'horario_sabado_inicio' => 'Hora de apertura los sábados',
                    'horario_sabado_fin' => 'Hora de cierre los sábados',
                    'horario_domingo_inicio' => 'Hora de apertura los domingos',
                    'horario_domingo_fin' => 'Hora de cierre los domingos',
                    'dias_cerrados' => 'Días de la semana cerrados (0=Domingo,1=Lunes,...,6=Sábado)'
                ];
                
                $stmt->execute([
                    'clave' => $clave,
                    'valor' => $valor,
                    'descripcion' => $descripciones[$clave] ?? 'Configuración del restaurante'
                ]);
            }
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Configuración actualizada correctamente'
            ]);
            break;
            
        default:
            throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
