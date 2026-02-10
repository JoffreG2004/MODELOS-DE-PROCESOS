-- =====================================================
-- HOTFIX: compatibilidad columna duracion_horas en reservas
-- Error que corrige:
-- SQLSTATE[42S22]: Unknown column 'r.duracion_horas' in 'field list'
-- =====================================================

USE crud_proyecto;

-- 1) Agregar columna legacy si no existe
ALTER TABLE reservas
ADD COLUMN IF NOT EXISTS duracion_horas DECIMAL(5,2) NOT NULL DEFAULT 3.00
COMMENT 'Compatibilidad legacy: duración en horas'
AFTER numero_personas;

-- 2) Si existe duracion_estimada (minutos), sincronizar a horas
SET @has_duracion_estimada := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'reservas'
      AND COLUMN_NAME = 'duracion_estimada'
);

SET @sql_sync := IF(
    @has_duracion_estimada > 0,
    "UPDATE reservas
     SET duracion_horas = CASE
         WHEN duracion_estimada IS NULL OR duracion_estimada <= 0 THEN 3.00
         ELSE ROUND(duracion_estimada / 60, 2)
     END",
    "UPDATE reservas
     SET duracion_horas = IFNULL(NULLIF(duracion_horas, 0), 3.00)"
);

PREPARE stmt_sync FROM @sql_sync;
EXECUTE stmt_sync;
DEALLOCATE PREPARE stmt_sync;

-- 3) Verificación rápida
SELECT
    COUNT(*) AS total_reservas,
    MIN(duracion_horas) AS duracion_min_horas,
    MAX(duracion_horas) AS duracion_max_horas
FROM reservas;

SELECT 'OK - hotfix duracion_horas aplicado' AS resultado;
