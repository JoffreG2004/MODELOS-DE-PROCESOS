-- =============================================================================================================
-- SCRIPT DE RESETEO COMPLETO DE BASE DE DATOS
-- =============================================================================================================
-- Fecha: 07 Febrero 2026
-- Descripción: Limpia toda la base de datos, resetea PKs y llena con datos de prueba
-- =============================================================================================================

USE crud_proyecto;

-- =============================================
-- PASO 1: DESHABILITAR FOREIGN KEY CHECKS
-- =============================================
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================
-- PASO 2: LIMPIAR TODAS LAS TABLAS
-- =============================================
TRUNCATE TABLE historial_estados;
TRUNCATE TABLE notas_consumo;
TRUNCATE TABLE pre_pedidos;
TRUNCATE TABLE auditoria_reservas;
TRUNCATE TABLE notificaciones_whatsapp;
TRUNCATE TABLE notificaciones_email;
TRUNCATE TABLE reservas_zonas;
TRUNCATE TABLE reservas;
TRUNCATE TABLE mesas;
TRUNCATE TABLE clientes;
TRUNCATE TABLE administradores;

-- =============================================
-- PASO 3: RESETEAR AUTO_INCREMENT A 1
-- =============================================
ALTER TABLE historial_estados AUTO_INCREMENT = 1;
ALTER TABLE notas_consumo AUTO_INCREMENT = 1;
ALTER TABLE pre_pedidos AUTO_INCREMENT = 1;
ALTER TABLE auditoria_reservas AUTO_INCREMENT = 1;
ALTER TABLE auditoria_horarios AUTO_INCREMENT = 1;
ALTER TABLE notificaciones_whatsapp AUTO_INCREMENT = 1;
ALTER TABLE notificaciones_email AUTO_INCREMENT = 1;
ALTER TABLE reservas_zonas AUTO_INCREMENT = 1;
ALTER TABLE reservas AUTO_INCREMENT = 1;
ALTER TABLE mesas AUTO_INCREMENT = 1;
ALTER TABLE clientes AUTO_INCREMENT = 1;
ALTER TABLE administradores AUTO_INCREMENT = 1;

-- =============================================
-- PASO 4: HABILITAR FOREIGN KEY CHECKS
-- =============================================
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- PASO 5: INSERTAR DATOS - ADMINISTRADORES (1)
-- =============================================
INSERT INTO `administradores` (`usuario`, `password`, `nombre`, `apellido`, `email`, `rol`, `activo`, `fecha_creacion`) VALUES
('admin', 'admin', 'Admin', 'Restaurante', 'admin@restaurante.com', 'admin', 1, '2025-11-07 02:05:38');

-- =============================================
-- PASO 6: INSERTAR DATOS - CLIENTES (10)
-- =============================================
INSERT INTO `clientes` (`nombre`, `apellido`, `cedula`, `telefono`, `ciudad`, `usuario`, `password_hash`, `email`) VALUES
('Joffre', 'Gomez', '1728677574', '0998521340', 'Machachi', 'joffre', '20082004', 'joffregq2004@gmail.com'),
('María', 'González', '1234567890', '0991234567', 'Quito', 'maria.g', 'maria123', 'maria.gonzalez@email.com'),
('Carlos', 'Rodríguez', '0987654321', '0992345678', 'Guayaquil', 'carlos.r', 'carlos456', 'carlos.rodriguez@email.com'),
('Ana', 'López', '1122334455', '0993456789', 'Cuenca', 'ana.l', 'ana789', 'ana.lopez@email.com'),
('Diego', 'Martínez', '5544332211', '0994567890', 'Quito', 'diego.m', 'diego321', 'diego.martinez@email.com'),
('Sofía', 'Hernández', '6677889900', '0995678901', 'Ambato', 'sofia.h', 'sofia654', 'sofia.hernandez@email.com'),
('Roberto', 'Silva', '9988776655', '0996789012', 'Manta', 'roberto.s', 'roberto987', 'roberto.silva@email.com'),
('Isabella', 'Morales', '1357924680', '0997890123', 'Loja', 'isabella.m', 'isabella246', 'isabella.morales@email.com'),
('Fernando', 'Castro', '2468013579', '0998901234', 'Machala', 'fernando.c', 'fernando135', 'fernando.castro@email.com'),
('Valentina', 'Ruiz', '3691470258', '0999012345', 'Riobamba', 'valentina.r', 'valentina802', 'valentina.ruiz@email.com');

-- =============================================
-- PASO 7: INSERTAR DATOS - MESAS (10)
-- =============================================
INSERT INTO `mesas` (`numero_mesa`, `capacidad_minima`, `capacidad_maxima`, `precio_reserva`, `ubicacion`, `estado`, `descripcion`) VALUES
('M01', 1, 4, 8.00, 'interior', 'disponible', 'Mesa íntima junto a la ventana'),
('M02', 2, 6, 10.00, 'interior', 'disponible', 'Mesa familiar en el centro del salón'),
('M03', 1, 4, 8.00, 'interior', 'disponible', 'Mesa mediana para grupos'),
('T01', 2, 8, 12.00, 'terraza', 'disponible', 'Mesa grande en terraza'),
('T02', 2, 6, 10.00, 'terraza', 'disponible', 'Mesa con vista al jardín'),
('V01', 4, 10, 20.00, 'vip', 'disponible', 'Mesa VIP con servicio exclusivo'),
('V02', 4, 12, 25.00, 'vip', 'disponible', 'Sala privada VIP'),
('B01', 1, 4, 6.00, 'bar', 'disponible', 'Mesa en la barra'),
('B02', 1, 4, 6.00, 'bar', 'disponible', 'Mesa de bar con vista a cocina'),
('M04', 2, 5, 9.00, 'interior', 'disponible', 'Mesa principal cerca de la cocina');

-- =============================================
-- PASO 8: INSERTAR DATOS - RESERVAS (25)
-- Reglas:
-- - Del 20 nov 2025 al 6 feb 2026: todas finalizadas o canceladas
-- - Del 7 al 10 feb 2026: algunas confirmadas y pendientes
-- - Reservas normales: respetan 3 horas entre reservas de la misma mesa
-- =============================================

INSERT INTO `reservas` (`cliente_id`, `mesa_id`, `fecha_reserva`, `hora_reserva`, `numero_personas`, `estado`, `motivo_cancelacion`) VALUES
-- NOVIEMBRE 2025 - 5 reservas finalizadas
(1, 1, '2025-11-20', '19:00:00', 4, 'finalizada', NULL),
(2, 2, '2025-11-25', '20:00:00', 6, 'finalizada', NULL),
(3, 3, '2025-11-27', '18:30:00', 3, 'cancelada', 'Cliente no pudo asistir'),
(4, 4, '2025-11-28', '19:30:00', 5, 'finalizada', NULL),
(5, 5, '2025-11-30', '20:30:00', 8, 'finalizada', NULL),

-- DICIEMBRE 2025 - 6 reservas finalizadas
(6, 6, '2025-12-05', '19:00:00', 10, 'finalizada', NULL),
(7, 7, '2025-12-10', '20:00:00', 8, 'finalizada', NULL),
(8, 8, '2025-12-15', '18:30:00', 10, 'finalizada', NULL),
(9, 9, '2025-12-20', '19:30:00', 4, 'finalizada', NULL),
(10, 10, '2025-12-24', '21:00:00', 4, 'finalizada', NULL),
(1, 1, '2025-12-28', '19:00:00', 4, 'cancelada', 'Emergencia familiar'),

-- ENERO 2026 - 7 reservas finalizadas
(2, 2, '2026-01-03', '20:00:00', 5, 'finalizada', NULL),
(3, 3, '2026-01-08', '18:30:00', 3, 'finalizada', NULL),
(4, 4, '2026-01-12', '19:30:00', 4, 'finalizada', NULL),
(5, 5, '2026-01-15', '20:30:00', 6, 'finalizada', NULL),
(6, 6, '2026-01-20', '19:00:00', 8, 'finalizada', NULL),
(7, 7, '2026-01-25', '20:00:00', 8, 'cancelada', 'Viaje cancelado'),
(8, 8, '2026-01-30', '18:30:00', 10, 'finalizada', NULL),

-- FEBRERO 2026 (1-6) - 3 reservas finalizadas
(9, 9, '2026-02-02', '19:30:00', 4, 'finalizada', NULL),
(10, 10, '2026-02-04', '20:00:00', 4, 'finalizada', NULL),
(1, 1, '2026-02-06', '19:00:00', 4, 'cancelada', 'Problema de salud'),

-- FEBRERO 2026 (7-10) - 4 reservas activas (confirmadas y pendientes)
(2, 2, '2026-02-07', '20:00:00', 5, 'confirmada', NULL),
(3, 3, '2026-02-08', '19:00:00', 3, 'pendiente', NULL),
(4, 4, '2026-02-09', '18:30:00', 4, 'confirmada', NULL),
(5, 5, '2026-02-10', '19:30:00', 6, 'pendiente', NULL);

-- =============================================
-- PASO 9: INSERTAR DATOS - RESERVAS ZONAS (3)
-- Reglas: Solo 1 reserva de zona por día
-- =============================================

-- RESERVAS DE ZONA FINALIZADAS
INSERT INTO `reservas_zonas` (`cliente_id`, `zonas`, `fecha_reserva`, `hora_reserva`, `numero_personas`, `precio_total`, `cantidad_mesas`, `estado`, `motivo_cancelacion`, `fecha_creacion`) VALUES
(1, '["interior","terraza"]', '2025-12-05', '19:00:00', 20, 150.00, 7, 'finalizada', NULL, '2025-12-01 10:00:00'),
(2, '["vip"]', '2026-01-10', '20:00:00', 15, 100.00, 2, 'finalizada', NULL, '2026-01-05 11:00:00');

-- RESERVA DE ZONA VIGENTE (7-10 feb)
INSERT INTO `reservas_zonas` (`cliente_id`, `zonas`, `fecha_reserva`, `hora_reserva`, `numero_personas`, `precio_total`, `cantidad_mesas`, `estado`, `motivo_cancelacion`, `fecha_creacion`) VALUES
(3, '["bar"]', '2026-02-10', '21:00:00', 10, 50.00, 2, 'confirmada', NULL, '2026-02-05 12:00:00');

-- =============================================
-- FINALIZACIÓN
-- =============================================
SELECT '✓ Base de datos reseteada correctamente' AS Resultado;
SELECT 'Administradores insertados:' AS Tabla, COUNT(*) AS Total FROM administradores
UNION ALL
SELECT 'Clientes insertados:', COUNT(*) FROM clientes
UNION ALL
SELECT 'Mesas insertadas:', COUNT(*) FROM mesas
UNION ALL
SELECT 'Reservas insertadas:', COUNT(*) FROM reservas
UNION ALL
SELECT 'Reservas de zona insertadas:', COUNT(*) FROM reservas_zonas;
