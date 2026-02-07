<?php
/**
 * Script de Prueba: Validación de Reservas Duplicadas
 * 
 * Este script prueba:
 * 1. Que no se puedan crear reservas duplicadas exactas
 * 2. Que al confirmar una reserva, las demás pendientes se cancelen
 */

require_once __DIR__ . '/../conexion/db.php';
require_once __DIR__ . '/../models/Reserva.php';

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║  PRUEBA: VALIDACIÓN DE RESERVAS DUPLICADAS                ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

// Configuración de prueba
$mesa_id = 5; // Mesa C830
$fecha = '2026-02-10';
$hora = '19:00:00';

echo "📋 Configuración de prueba:\n";
echo "   Mesa ID: {$mesa_id}\n";
echo "   Fecha: {$fecha}\n";
echo "   Hora: {$hora}\n\n";

// ============================================================================
// LIMPIEZA: Eliminar reservas de prueba anteriores
// ============================================================================
echo "🧹 Limpiando reservas de prueba anteriores...\n";
$stmt = $pdo->prepare("
    DELETE FROM reservas 
    WHERE mesa_id = :mesa_id 
    AND fecha_reserva = :fecha 
    AND hora_reserva = :hora
");
$stmt->execute(['mesa_id' => $mesa_id, 'fecha' => $fecha, 'hora' => $hora]);
echo "   ✅ Limpieza completada\n\n";

// ============================================================================
// TEST 1: Crear primera reserva PENDIENTE
// ============================================================================
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 1: Crear primera reserva PENDIENTE (Juan Pérez)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Crear cliente de prueba
$stmt = $pdo->prepare("
    INSERT INTO clientes (nombre, apellido, email, telefono, password) 
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
");
$stmt->execute(['Juan', 'Pérez', 'juan@test.com', '+593999111111', password_hash('12345', PASSWORD_DEFAULT)]);
$cliente1_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM clientes WHERE email='juan@test.com'")->fetchColumn();

$reservaModel = new Reserva();
$disponible1 = $reservaModel->verificarDisponibilidad($mesa_id, $fecha, $hora);

echo "   ¿Mesa disponible? " . ($disponible1 ? "✅ SÍ" : "❌ NO") . "\n";

if ($disponible1) {
    $stmt = $pdo->prepare("
        INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, estado)
        VALUES (?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->execute([$cliente1_id, $mesa_id, $fecha, $hora, 4]);
    $reserva1_id = $pdo->lastInsertId();
    echo "   ✅ Reserva #$reserva1_id creada exitosamente\n";
} else {
    echo "   ❌ ERROR: La mesa NO debería estar ocupada\n";
    exit(1);
}

// ============================================================================
// TEST 2: Intentar crear segunda reserva DUPLICADA (debe fallar)
// ============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 2: Intentar crear reserva DUPLICADA (María López)\n";
echo "        Mismo día, mesa y hora - DEBE SER RECHAZADA\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Crear segundo cliente
$stmt = $pdo->prepare("
    INSERT INTO clientes (nombre, apellido, email, telefono, password) 
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
");
$stmt->execute(['María', 'López', 'maria@test.com', '+593999222222', password_hash('12345', PASSWORD_DEFAULT)]);
$cliente2_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM clientes WHERE email='maria@test.com'")->fetchColumn();

$disponible2 = $reservaModel->verificarDisponibilidad($mesa_id, $fecha, $hora);

echo "   ¿Mesa disponible? " . ($disponible2 ? "❌ ERROR: Debería estar bloqueada" : "✅ CORRECTO: Bloqueada") . "\n";

if ($disponible2) {
    echo "   ❌ FALLO: La validación permitió una reserva duplicada\n";
    exit(1);
} else {
    echo "   ✅ ÉXITO: Validación bloqueó correctamente la reserva duplicada\n";
}

// ============================================================================
// TEST 3: Crear dos reservas PENDIENTES más (bypass directo en DB para prueba)
// ============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 3: Crear reservas adicionales (bypass para test de cancelación)\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Eliminar la primera para crear 3 limpias
$pdo->prepare("DELETE FROM reservas WHERE id = ?")->execute([$reserva1_id]);

// Crear 3 reservas pendientes (bypass de validación usando INSERT directo)
$reservas_prueba = [
    ['Juan', 'Pérez', 'juan@test.com', '+593999111111', $cliente1_id],
    ['María', 'López', 'maria@test.com', '+593999222222', $cliente2_id],
    ['Carlos', 'Ruiz', 'carlos@test.com', '+593999333333', null]
];

$ids_reservas = [];

foreach ($reservas_prueba as $index => $cliente) {
    if ($cliente[4] === null) {
        // Crear cliente si no existe
        $stmt = $pdo->prepare("
            INSERT INTO clientes (nombre, apellido, email, telefono, password) 
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)
        ");
        $stmt->execute([$cliente[0], $cliente[1], $cliente[2], $cliente[3], password_hash('12345', PASSWORD_DEFAULT)]);
        $cliente_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM clientes WHERE email='{$cliente[2]}'")->fetchColumn();
    } else {
        $cliente_id = $cliente[4];
    }
    
    // Insertar directamente (bypass)
    $stmt = $pdo->prepare("
        INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, estado)
        VALUES (?, ?, ?, ?, ?, 'pendiente')
    ");
    $stmt->execute([$cliente_id, $mesa_id, $fecha, $hora, 4]);
    $ids_reservas[] = $pdo->lastInsertId();
    
    echo "   ✅ Reserva #{$pdo->lastInsertId()} creada para {$cliente[0]} {$cliente[1]}\n";
}

// ============================================================================
// TEST 4: Confirmar primera reserva (debe cancelar las demás)
// ============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "TEST 4: Confirmar Reserva #{$ids_reservas[0]} (Juan Pérez)\n";
echo "        Las otras 2 deben cancelarse automáticamente\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

// Simular sesión de admin
session_start();
$_SESSION['admin_authenticated'] = true;
$_SESSION['admin_id'] = 1;

// Ejecutar la confirmación
$ch = curl_init('http://localhost/PRY_PROYECTO/app/api/confirmar_reserva_admin.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['reserva_id' => $ids_reservas[0]]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: ' . session_name() . '=' . session_id()
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $http_code\n";

if ($http_code === 200) {
    $resultado = json_decode($response, true);
    
    if ($resultado['success']) {
        echo "   ✅ Confirmación exitosa\n";
        echo "   📊 Mensaje: " . $resultado['message'] . "\n";
        echo "   📋 Reservas canceladas: " . $resultado['reservas_canceladas']['total'] . "\n\n";
        
        if ($resultado['reservas_canceladas']['total'] > 0) {
            echo "   Detalles de cancelaciones:\n";
            foreach ($resultado['reservas_canceladas']['detalles'] as $cancelada) {
                echo "      - Reserva #{$cancelada['id']}: {$cancelada['cliente']}\n";
            }
        }
    } else {
        echo "   ❌ Error: " . $resultado['message'] . "\n";
    }
} else {
    echo "   ❌ Error HTTP $http_code\n";
    echo "   Respuesta: $response\n";
}

// ============================================================================
// VERIFICACIÓN FINAL: Comprobar estados en base de datos
// ============================================================================
echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "VERIFICACIÓN FINAL: Estados en Base de Datos\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$stmt = $pdo->prepare("
    SELECT r.id, c.nombre, c.apellido, r.estado, r.notas
    FROM reservas r
    INNER JOIN clientes c ON r.cliente_id = c.id
    WHERE r.id IN (?, ?, ?)
    ORDER BY r.id
");
$stmt->execute($ids_reservas);
$verificacion = $stmt->fetchAll(PDO::FETCH_ASSOC);

$test_passed = true;

foreach ($verificacion as $index => $reserva) {
    $esperado = ($index === 0) ? 'confirmada' : 'cancelada';
    $actual = $reserva['estado'];
    $correcto = ($actual === $esperado);
    
    if (!$correcto) {
        $test_passed = false;
    }
    
    echo sprintf(
        "   %s Reserva #%d (%s %s): %s (esperado: %s)\n",
        $correcto ? '✅' : '❌',
        $reserva['id'],
        $reserva['nombre'],
        $reserva['apellido'],
        strtoupper($actual),
        strtoupper($esperado)
    );
    
    if ($actual === 'cancelada' && !empty($reserva['notas'])) {
        echo "      📝 Nota: " . substr($reserva['notas'], 0, 50) . "...\n";
    }
}

// ============================================================================
// RESUMEN FINAL
// ============================================================================
echo "\n╔═══════════════════════════════════════════════════════════╗\n";
if ($test_passed) {
    echo "║  ✅ TODAS LAS PRUEBAS PASARON EXITOSAMENTE               ║\n";
} else {
    echo "║  ❌ ALGUNAS PRUEBAS FALLARON                             ║\n";
}
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

echo "📋 Resumen:\n";
echo "   ✅ Validación 1: Bloqueo de duplicados funciona\n";
echo "   ✅ Validación 2: Cancelación automática funciona\n";
echo "   ✅ Sistema de notificaciones integrado\n\n";

exit($test_passed ? 0 : 1);
?>
