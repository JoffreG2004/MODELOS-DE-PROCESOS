<?php
/**
 * Migra contraseñas en texto plano a hash seguro.
 *
 * Uso:
 *   /opt/lampp/bin/php scripts/security/migrate_passwords_to_hash.php
 *   /opt/lampp/bin/php scripts/security/migrate_passwords_to_hash.php --dry-run
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Este script solo puede ejecutarse por CLI.\n";
    exit(1);
}

require_once __DIR__ . '/../../conexion/db.php';
require_once __DIR__ . '/../../utils/security/password_utils.php';

if (!isset($pdo) || !$pdo) {
    fwrite(STDERR, "No se pudo inicializar la conexión PDO.\n");
    exit(1);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);

try {
    if (!$dryRun) {
        $pdo->beginTransaction();
    }

    $resumen = [
        'clientes' => ['total' => 0, 'actualizados' => 0, 'ya_hasheados' => 0, 'vacios' => 0],
        'administradores' => ['total' => 0, 'actualizados' => 0, 'ya_hasheados' => 0, 'vacios' => 0],
        'modo' => $dryRun ? 'dry-run' : 'apply'
    ];

    $stmtClientes = $pdo->query("SELECT id, password_hash FROM clientes");
    $updCliente = $pdo->prepare("UPDATE clientes SET password_hash = ? WHERE id = ?");
    while ($row = $stmtClientes->fetch(PDO::FETCH_ASSOC)) {
        $resumen['clientes']['total']++;
        $id = (int)$row['id'];
        $actual = (string)($row['password_hash'] ?? '');

        if ($actual === '') {
            $resumen['clientes']['vacios']++;
            continue;
        }

        if (esPasswordHash($actual)) {
            $resumen['clientes']['ya_hasheados']++;
            continue;
        }

        $nuevoHash = hashPasswordSeguro($actual);
        if (!$dryRun) {
            $updCliente->execute([$nuevoHash, $id]);
        }
        $resumen['clientes']['actualizados']++;
    }

    $stmtAdmins = $pdo->query("SELECT id, password FROM administradores");
    $updAdmin = $pdo->prepare("UPDATE administradores SET password = ? WHERE id = ?");
    while ($row = $stmtAdmins->fetch(PDO::FETCH_ASSOC)) {
        $resumen['administradores']['total']++;
        $id = (int)$row['id'];
        $actual = (string)($row['password'] ?? '');

        if ($actual === '') {
            $resumen['administradores']['vacios']++;
            continue;
        }

        if (esPasswordHash($actual)) {
            $resumen['administradores']['ya_hasheados']++;
            continue;
        }

        $nuevoHash = hashPasswordSeguro($actual);
        if (!$dryRun) {
            $updAdmin->execute([$nuevoHash, $id]);
        }
        $resumen['administradores']['actualizados']++;
    }

    if (!$dryRun) {
        $pdo->commit();
    }

    echo json_encode([
        'success' => true,
        'resumen' => $resumen
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (Throwable $e) {
    if (!$dryRun && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Error en migración: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
