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
