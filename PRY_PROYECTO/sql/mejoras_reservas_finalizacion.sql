-- =====================================================
-- MEJORAS SISTEMA DE RESERVAS - FINALIZACIÓN MANUAL
-- =====================================================
-- Fecha: 4 de Febrero 2026
-- Descripción: 
--   - Agregar campos para control de llegada y finalización
--   - Auto-finalizar después de 1 día
--   - Bloqueo inteligente de mesas (3-4 horas entre reservas)
-- =====================================================

USE crud_proyecto;

-- -----------------------------------------------------
-- 1. AGREGAR CAMPOS A TABLA RESERVAS
-- -----------------------------------------------------

-- Campo: Duración estimada en minutos (flexible)
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS duracion_estimada INT DEFAULT 120 
COMMENT 'Duración estimada en minutos (120=2h, 480=8h, 1440=día completo)';

-- Campo: Cliente llegó (confirmación manual)
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS cliente_llego TINYINT(1) DEFAULT 0 
COMMENT '0=No llegó, 1=Llegó confirmado';

-- Campo: Hora real de llegada
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS hora_llegada DATETIME NULL 
COMMENT 'Hora real cuando el cliente llegó';

-- Campo: Hora de finalización real
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS hora_finalizacion DATETIME NULL 
COMMENT 'Hora cuando el admin finalizó la reserva';

-- Campo: Admin que finalizó
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS finalizada_por VARCHAR(100) NULL 
COMMENT 'Usuario admin que marcó como finalizada';

-- Campo: Observaciones de finalización
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS observaciones_finalizacion TEXT NULL 
COMMENT 'Notas del admin al finalizar';

-- Campo: Notificación de no-show enviada
ALTER TABLE reservas 
ADD COLUMN IF NOT EXISTS notificacion_noshow_enviada TINYINT(1) DEFAULT 0 
COMMENT '0=No enviada, 1=Email +15min enviado';

-- -----------------------------------------------------
-- 2. AGREGAR CAMPOS A TABLA RESERVAS_ZONAS
-- -----------------------------------------------------

ALTER TABLE reservas_zonas 
ADD COLUMN IF NOT EXISTS duracion_estimada INT DEFAULT 240 
COMMENT 'Duración estimada en minutos para reservas de zona';

ALTER TABLE reservas_zonas 
ADD COLUMN IF NOT EXISTS cliente_llego TINYINT(1) DEFAULT 0;

ALTER TABLE reservas_zonas 
ADD COLUMN IF NOT EXISTS hora_llegada DATETIME NULL;

ALTER TABLE reservas_zonas 
ADD COLUMN IF NOT EXISTS hora_finalizacion DATETIME NULL;

ALTER TABLE reservas_zonas 
ADD COLUMN IF NOT EXISTS finalizada_por VARCHAR(100) NULL;

ALTER TABLE reservas_zonas 
ADD COLUMN IF NOT EXISTS observaciones_finalizacion TEXT NULL;

ALTER TABLE reservas_zonas 
ADD COLUMN IF NOT EXISTS notificacion_noshow_enviada TINYINT(1) DEFAULT 0;

-- -----------------------------------------------------
-- 3. ÍNDICES PARA OPTIMIZACIÓN
-- -----------------------------------------------------

-- Índice para búsqueda rápida de reservas activas
CREATE INDEX IF NOT EXISTS idx_reservas_estado_fecha 
ON reservas(estado, fecha_reserva, hora_reserva);

-- Índice para finalización automática
CREATE INDEX IF NOT EXISTS idx_reservas_finalizacion_auto 
ON reservas(estado, fecha_reserva, hora_finalizacion);

-- -----------------------------------------------------
-- 4. ACTUALIZAR DURACIONES POR DEFECTO EN RESERVAS EXISTENTES
-- -----------------------------------------------------

-- Reservas normales: 2 horas por defecto
UPDATE reservas 
SET duracion_estimada = 120 
WHERE duracion_estimada IS NULL;

-- Reservas de zona: 4 horas por defecto
UPDATE reservas_zonas 
SET duracion_estimada = 240 
WHERE duracion_estimada IS NULL;

-- -----------------------------------------------------
-- 5. PROCEDIMIENTO ALMACENADO: ACTUALIZAR ESTADOS AUTOMÁTICOS
-- -----------------------------------------------------

DELIMITER $$

DROP PROCEDURE IF EXISTS activar_reservas_programadas$$

CREATE PROCEDURE activar_reservas_programadas()
BEGIN
    -- ========================================
    -- PASO 1: CONFIRMADA → PREPARANDO (1 hora antes)
    -- ========================================
    UPDATE reservas 
    SET estado = 'preparando'
    WHERE estado = 'confirmada'
    AND TIMESTAMP(fecha_reserva, hora_reserva) <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
    AND TIMESTAMP(fecha_reserva, hora_reserva) > NOW();
    
    -- Lo mismo para reservas de zonas
    UPDATE reservas_zonas 
    SET estado = 'preparando'
    WHERE estado = 'confirmada'
    AND TIMESTAMP(fecha_reserva, hora_reserva) <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
    AND TIMESTAMP(fecha_reserva, hora_reserva) > NOW();
    
    -- ========================================
    -- PASO 2: PREPARANDO → EN_CURSO (hora exacta)
    -- ========================================
    UPDATE reservas 
    SET estado = 'en_curso'
    WHERE estado = 'preparando'
    AND TIMESTAMP(fecha_reserva, hora_reserva) <= NOW();
    
    UPDATE reservas_zonas 
    SET estado = 'en_curso'
    WHERE estado = 'preparando'
    AND TIMESTAMP(fecha_reserva, hora_reserva) <= NOW();
    
    -- ========================================
    -- PASO 3: AUTO-FINALIZAR DESPUÉS DE 1 DÍA
    -- ========================================
    -- Si el admin se olvidó de finalizar, auto-finalizar después de 24 horas
    UPDATE reservas 
    SET estado = 'finalizada',
        hora_finalizacion = NOW(),
        finalizada_por = 'SISTEMA_AUTO',
        observaciones_finalizacion = 'Finalizada automáticamente después de 24 horas'
    WHERE estado IN ('en_curso', 'preparando')
    AND TIMESTAMP(fecha_reserva, hora_reserva) < DATE_SUB(NOW(), INTERVAL 1 DAY);
    
    UPDATE reservas_zonas 
    SET estado = 'finalizada',
        hora_finalizacion = NOW(),
        finalizada_por = 'SISTEMA_AUTO',
        observaciones_finalizacion = 'Finalizada automáticamente después de 24 horas'
    WHERE estado IN ('en_curso', 'preparando')
    AND TIMESTAMP(fecha_reserva, hora_reserva) < DATE_SUB(NOW(), INTERVAL 1 DAY);
    
    -- ========================================
    -- PASO 4: AUTO-FINALIZAR CONFIRMADAS ANTIGUAS (más de 2 días)
    -- ========================================
    UPDATE reservas 
    SET estado = 'finalizada',
        hora_finalizacion = NOW(),
        finalizada_por = 'SISTEMA_AUTO',
        observaciones_finalizacion = 'Reserva antigua finalizada automáticamente'
    WHERE estado = 'confirmada'
    AND fecha_reserva < DATE_SUB(CURDATE(), INTERVAL 2 DAY);
    
    UPDATE reservas_zonas 
    SET estado = 'finalizada',
        hora_finalizacion = NOW(),
        finalizada_por = 'SISTEMA_AUTO',
        observaciones_finalizacion = 'Reserva antigua finalizada automáticamente'
    WHERE estado = 'confirmada'
    AND fecha_reserva < DATE_SUB(CURDATE(), INTERVAL 2 DAY);
    
    -- ========================================
    -- PASO 5: LIBERAR MESAS DE RESERVAS FINALIZADAS
    -- ========================================
    UPDATE mesas m
    INNER JOIN reservas r ON m.id = r.mesa_id
    SET m.estado = 'disponible'
    WHERE r.estado = 'finalizada'
    AND m.estado IN ('reservada', 'ocupada');
    
END$$

DELIMITER ;

-- -----------------------------------------------------
-- 6. TRIGGER: MARCAR MESAS AL CAMBIAR ESTADO
-- -----------------------------------------------------

DELIMITER $$

DROP TRIGGER IF EXISTS tr_reservas_cambio_estado_mejorado$$

CREATE TRIGGER tr_reservas_cambio_estado_mejorado
AFTER UPDATE ON reservas
FOR EACH ROW
BEGIN
    -- Si cambia a PREPARANDO o EN_CURSO, marcar mesa como ocupada
    IF NEW.estado IN ('preparando', 'en_curso') AND OLD.estado != NEW.estado THEN
        UPDATE mesas SET estado = 'ocupada' WHERE id = NEW.mesa_id;
    END IF;
    
    -- Si cambia a FINALIZADA o CANCELADA, liberar mesa
    IF NEW.estado IN ('finalizada', 'cancelada') AND OLD.estado != NEW.estado THEN
        UPDATE mesas SET estado = 'disponible' WHERE id = NEW.mesa_id;
    END IF;
    
    -- Registrar cambio en auditoría
    INSERT INTO auditoria_cambios (
        tabla_afectada,
        accion,
        registro_id,
        usuario,
        detalles,
        fecha_hora
    ) VALUES (
        'reservas',
        'UPDATE_ESTADO',
        NEW.id,
        COALESCE(NEW.finalizada_por, 'SISTEMA'),
        CONCAT('Estado: ', OLD.estado, ' → ', NEW.estado),
        NOW()
    );
END$$

DELIMITER ;

-- -----------------------------------------------------
-- 7. CONFIGURACIÓN DE DURACIONES PREDEFINIDAS
-- -----------------------------------------------------

CREATE TABLE IF NOT EXISTS configuracion_duraciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    minutos INT NOT NULL,
    descripcion VARCHAR(200),
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar duraciones predefinidas
INSERT INTO configuracion_duraciones (nombre, minutos, descripcion) VALUES
('Corta', 90, 'Almuerzo rápido - 1.5 horas'),
('Normal', 120, 'Cena normal - 2 horas'),
('Larga', 240, 'Evento pequeño - 4 horas'),
('Evento', 480, 'Evento grande - 8 horas'),
('Medio día', 720, 'Alquiler medio día - 12 horas'),
('Día completo', 1440, 'Alquiler día completo - 24 horas')
ON DUPLICATE KEY UPDATE activo = 1;

-- -----------------------------------------------------
-- 8. VISTA: RESERVAS ACTIVAS (PARA PANEL ADMIN)
-- -----------------------------------------------------

CREATE OR REPLACE VIEW vista_reservas_activas AS
SELECT 
    r.id,
    r.cliente_id,
    r.mesa_id,
    m.numero_mesa,
    m.ubicacion as zona,
    c.nombre as cliente_nombre,
    c.apellido as cliente_apellido,
    c.telefono as cliente_telefono,
    r.fecha_reserva,
    r.hora_reserva,
    r.num_personas,
    r.estado,
    r.duracion_estimada,
    r.cliente_llego,
    r.hora_llegada,
    r.notificacion_noshow_enviada,
    TIMESTAMP(r.fecha_reserva, r.hora_reserva) as fecha_hora_inicio,
    TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) as minutos_transcurridos,
    CASE 
        WHEN r.cliente_llego = 1 THEN '🟢 Llegó'
        WHEN TIMESTAMPDIFF(MINUTE, TIMESTAMP(r.fecha_reserva, r.hora_reserva), NOW()) > 15 THEN '🔴 No llegó'
        ELSE '🟡 Esperando'
    END as estado_llegada,
    'normal' as tipo_reserva
FROM reservas r
INNER JOIN mesas m ON r.mesa_id = m.id
INNER JOIN clientes c ON r.cliente_id = c.id
WHERE r.estado IN ('preparando', 'en_curso')
ORDER BY r.fecha_reserva, r.hora_reserva;

-- -----------------------------------------------------
-- 9. FUNCIÓN: CALCULAR TIEMPO MÍNIMO ENTRE RESERVAS
-- -----------------------------------------------------

DELIMITER $$

DROP FUNCTION IF EXISTS calcular_tiempo_minimo_separacion$$

CREATE FUNCTION calcular_tiempo_minimo_separacion(duracion_min INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE tiempo_separacion INT;
    
    -- Duración + tiempo de limpieza/preparación
    -- Mínimo 3 horas entre reservas
    SET tiempo_separacion = GREATEST(duracion_min + 60, 180);
    
    RETURN tiempo_separacion;
END$$

DELIMITER ;

-- -----------------------------------------------------
-- 10. DATOS DE PRUEBA (OPCIONAL - COMENTAR SI NO SE NECESITA)
-- -----------------------------------------------------

-- Actualizar algunas reservas existentes con duraciones
-- UPDATE reservas SET duracion_estimada = 240 WHERE num_personas > 6;
-- UPDATE reservas SET duracion_estimada = 480 WHERE num_personas > 12;

-- -----------------------------------------------------
-- VERIFICACIÓN FINAL
-- -----------------------------------------------------

-- Verificar que los campos se agregaron correctamente
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'crud_proyecto' 
AND TABLE_NAME = 'reservas'
AND COLUMN_NAME IN (
    'duracion_estimada',
    'cliente_llego',
    'hora_llegada',
    'hora_finalizacion',
    'finalizada_por',
    'observaciones_finalizacion',
    'notificacion_noshow_enviada'
);

-- Verificar procedimiento
SHOW PROCEDURE STATUS WHERE Db = 'crud_proyecto' AND Name = 'activar_reservas_programadas';

-- Verificar vista
SHOW CREATE VIEW vista_reservas_activas;

SELECT '✅ Script ejecutado correctamente - Base de datos actualizada' as Resultado;
