-- =====================================================
-- AGREGAR RECUPERACIÓN DE CONTRASEÑA POR TOKEN (CLIENTES)
-- =====================================================
-- Ejecutar en base de datos: crud_proyecto

USE crud_proyecto;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limpieza opcional de tokens viejos (expirados o usados hace más de 7 días)
DELETE FROM password_reset_tokens
WHERE (expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
   OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 7 DAY));

SELECT 'OK - tabla password_reset_tokens lista' AS resultado;
