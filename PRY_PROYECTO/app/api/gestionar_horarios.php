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
                $stmt = $pdo->prepare("UPDATE configuracion_restaurante SET valor = :valor WHERE clave = :clave");
                $stmt->execute([
                    'valor' => $valor,
                    'clave' => $clave
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
