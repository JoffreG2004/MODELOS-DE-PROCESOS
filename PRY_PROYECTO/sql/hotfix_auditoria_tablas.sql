-- Hotfix: asegurar tablas de auditoría para instalaciones antiguas/incompletas
-- Ejecutar en la BD del proyecto (ej: crud_proyecto)

CREATE TABLE IF NOT EXISTS `auditoria_horarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `admin_nombre` VARCHAR(100) NOT NULL,
  `accion` VARCHAR(50) NOT NULL,
  `fecha_cambio` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `configuracion_anterior` TEXT NULL,
  `configuracion_nueva` TEXT NULL,
  `reservas_afectadas` INT DEFAULT 0,
  `reservas_canceladas` INT DEFAULT 0,
  `notificaciones_enviadas` INT DEFAULT 0,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  `observaciones` TEXT NULL,
  INDEX `idx_admin_id` (`admin_id`),
  INDEX `idx_fecha_cambio` (`fecha_cambio`),
  INDEX `idx_accion` (`accion`),
  CONSTRAINT `fk_aud_hor_admin` FOREIGN KEY (`admin_id`) REFERENCES `administradores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auditoria_reservas` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `reserva_id` INT NOT NULL,
  `admin_id` INT NULL,
  `accion` VARCHAR(50) NOT NULL,
  `estado_anterior` VARCHAR(50) NULL,
  `estado_nuevo` VARCHAR(50) NULL,
  `datos_anteriores` TEXT NULL,
  `datos_nuevos` TEXT NULL,
  `motivo` TEXT NULL,
  `fecha_accion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  INDEX `idx_reserva_id` (`reserva_id`),
  INDEX `idx_admin_id` (`admin_id`),
  INDEX `idx_fecha_accion` (`fecha_accion`),
  INDEX `idx_accion` (`accion`),
  CONSTRAINT `fk_aud_res_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aud_res_admin` FOREIGN KEY (`admin_id`) REFERENCES `administradores`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auditoria_sistema` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT NULL,
  `usuario_tipo` ENUM('admin','cliente') NOT NULL,
  `usuario_nombre` VARCHAR(100) NOT NULL,
  `modulo` VARCHAR(50) NOT NULL,
  `accion` VARCHAR(50) NOT NULL,
  `tabla_afectada` VARCHAR(50) NULL,
  `registro_id` INT NULL,
  `descripcion` TEXT NOT NULL,
  `datos_anteriores` TEXT NULL,
  `datos_nuevos` TEXT NULL,
  `fecha_hora` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` TEXT NULL,
  INDEX `idx_usuario` (`usuario_id`, `usuario_tipo`),
  INDEX `idx_modulo` (`modulo`),
  INDEX `idx_fecha_hora` (`fecha_hora`),
  INDEX `idx_accion` (`accion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'auditoria_horarios' AS tabla, COUNT(*) AS total FROM auditoria_horarios
UNION ALL
SELECT 'auditoria_reservas' AS tabla, COUNT(*) AS total FROM auditoria_reservas
UNION ALL
SELECT 'auditoria_sistema' AS tabla, COUNT(*) AS total FROM auditoria_sistema;

