<?php
/**
 * Utilidades para recuperación de contraseña con token de un solo uso.
 */

if (!function_exists('ensurePasswordResetTable')) {
    function ensurePasswordResetTable($pdo) {
        $sql = "
            CREATE TABLE IF NOT EXISTS password_reset_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cliente_id INT NOT NULL,
                email VARCHAR(100) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                requested_identifier VARCHAR(100) NULL,
                requested_ip VARCHAR(45) NULL,
                user_agent TEXT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_token_hash (token_hash),
                KEY idx_cliente_estado (cliente_id, used_at, expires_at),
                KEY idx_email_created (email, created_at),
                CONSTRAINT fk_password_reset_cliente
                    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        $pdo->exec($sql);
    }
}

if (!function_exists('generatePasswordResetToken')) {
    function generatePasswordResetToken() {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}

if (!function_exists('hashPasswordResetToken')) {
    function hashPasswordResetToken($token) {
        return hash('sha256', (string)$token);
    }
}

if (!function_exists('createPasswordResetToken')) {
    function createPasswordResetToken($pdo, $clienteId, $email, $requestedIdentifier = null, $requestedIp = null, $userAgent = null, $ttlMinutes = 30) {
        ensurePasswordResetTable($pdo);

        // Invalidar tokens activos anteriores para ese cliente
        $stmtInvalidate = $pdo->prepare("
            UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE cliente_id = :cliente_id
              AND used_at IS NULL
              AND expires_at >= NOW()
        ");
        $stmtInvalidate->execute(['cliente_id' => $clienteId]);

        $tokenPlano = generatePasswordResetToken();
        $tokenHash = hashPasswordResetToken($tokenPlano);
        $expiresAt = date('Y-m-d H:i:s', time() + max(5, (int)$ttlMinutes) * 60);

        $stmtInsert = $pdo->prepare("
            INSERT INTO password_reset_tokens
            (cliente_id, email, token_hash, requested_identifier, requested_ip, user_agent, expires_at)
            VALUES
            (:cliente_id, :email, :token_hash, :requested_identifier, :requested_ip, :user_agent, :expires_at)
        ");
        $stmtInsert->execute([
            'cliente_id' => $clienteId,
            'email' => $email,
            'token_hash' => $tokenHash,
            'requested_identifier' => $requestedIdentifier,
            'requested_ip' => $requestedIp,
            'user_agent' => $userAgent,
            'expires_at' => $expiresAt
        ]);

        return [
            'token' => $tokenPlano,
            'expires_at' => $expiresAt
        ];
    }
}

if (!function_exists('findValidPasswordResetToken')) {
    function findValidPasswordResetToken($pdo, $tokenPlano) {
        ensurePasswordResetTable($pdo);

        $stmt = $pdo->prepare("
            SELECT
                prt.id,
                prt.cliente_id,
                prt.email,
                prt.expires_at,
                c.nombre,
                c.apellido,
                c.usuario
            FROM password_reset_tokens prt
            INNER JOIN clientes c ON c.id = prt.cliente_id
            WHERE prt.token_hash = :token_hash
              AND prt.used_at IS NULL
              AND prt.expires_at >= NOW()
            ORDER BY prt.id DESC
            LIMIT 1
        ");
        $stmt->execute([
            'token_hash' => hashPasswordResetToken($tokenPlano)
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}

if (!function_exists('markPasswordResetTokenUsed')) {
    function markPasswordResetTokenUsed($pdo, $tokenId, $clienteId) {
        $stmtUse = $pdo->prepare("
            UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE id = :id AND used_at IS NULL
        ");
        $stmtUse->execute(['id' => $tokenId]);

        $stmtInvalidateOthers = $pdo->prepare("
            UPDATE password_reset_tokens
            SET used_at = NOW()
            WHERE cliente_id = :cliente_id
              AND used_at IS NULL
              AND id <> :id
        ");
        $stmtInvalidateOthers->execute([
            'cliente_id' => $clienteId,
            'id' => $tokenId
        ]);
    }
}
