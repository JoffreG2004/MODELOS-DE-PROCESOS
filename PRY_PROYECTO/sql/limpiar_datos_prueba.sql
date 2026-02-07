-- ============================================
-- LIMPIAR DATOS DE PRUEBA Y DEJAR BASE LIMPIA
-- ============================================

USE crud_proyecto;

-- Desactivar foreign key checks temporalmente
SET FOREIGN_KEY_CHECKS = 0;

-- Limpiar todas las reservas (excepto las del día de hoy si hay)
DELETE FROM reservas WHERE fecha_reserva < CURDATE();
DELETE FROM reservas WHERE id > 10;

-- Limpiar clientes (dejar solo 10)
DELETE FROM clientes WHERE id > 10;

-- Limpiar mesas (dejar solo 10)
DELETE FROM mesas WHERE id > 10;

-- Limpiar logs de auditoría antiguos (si existe la tabla)
DELETE FROM auditoria_reservas WHERE fecha_accion < DATE_SUB(NOW(), INTERVAL 7 DAY);

-- Reactivar foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- INSERTAR 10 MESAS LIMPIAS Y BIEN CONFIGURADAS
-- ============================================

DELETE FROM mesas;

INSERT INTO mesas (numero_mesa, capacidad, ubicacion, zona, estado, precio_base) VALUES
(1, 2, 'Ventana principal', 'terraza', 'disponible', 25.00),
(2, 4, 'Centro del salón', 'interior', 'disponible', 35.00),
(3, 2, 'Esquina romántica', 'vip', 'disponible', 45.00),
(4, 6, 'Salón principal', 'interior', 'disponible', 50.00),
(5, 4, 'Junto a la barra', 'interior', 'disponible', 30.00),
(6, 2, 'Terraza exterior', 'terraza', 'disponible', 28.00),
(7, 8, 'Sala privada VIP', 'vip', 'disponible', 80.00),
(8, 4, 'Vista al jardín', 'terraza', 'disponible', 38.00),
(9, 2, 'Balcón', 'terraza', 'disponible', 32.00),
(10, 6, 'Salón central', 'interior', 'disponible', 48.00);

-- ============================================
-- INSERTAR 10 CLIENTES DE PRUEBA
-- ============================================

DELETE FROM clientes;

INSERT INTO clientes (nombre, apellido, correo, telefono, password) VALUES
('Joffre', 'Quezada', 'joffregq2004@gmail.com', '+593987654321', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('María', 'González', 'maria.gonzalez@gmail.com', '+593987654322', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Carlos', 'Rodríguez', 'carlos.rodriguez@gmail.com', '+593987654323', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Ana', 'Martínez', 'ana.martinez@gmail.com', '+593987654324', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Luis', 'Fernández', 'luis.fernandez@gmail.com', '+593987654325', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Laura', 'López', 'laura.lopez@gmail.com', '+593987654326', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Pedro', 'Sánchez', 'pedro.sanchez@gmail.com', '+593987654327', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Isabel', 'Ramírez', 'isabel.ramirez@gmail.com', '+593987654328', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Diego', 'Torres', 'diego.torres@gmail.com', '+593987654329', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890'),
('Carmen', 'Flores', 'carmen.flores@gmail.com', '+593987654330', '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890');

-- ============================================
-- INSERTAR 5 RESERVAS DE PRUEBA (PENDIENTES)
-- ============================================

DELETE FROM reservas;

INSERT INTO reservas (cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, estado, notas) VALUES
(1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '19:00:00', 2, 'pendiente', 'Mesa junto a la ventana'),
(2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '20:00:00', 4, 'pendiente', 'Celebración de aniversario'),
(3, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '19:30:00', 2, 'pendiente', 'Cena romántica'),
(4, 4, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '21:00:00', 6, 'pendiente', 'Reunión familiar'),
(5, 5, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '18:30:00', 4, 'pendiente', 'Reserva empresarial');

-- ============================================
-- VERIFICAR DATOS
-- ============================================

SELECT 'Mesas creadas:' as Info, COUNT(*) as Total FROM mesas;
SELECT 'Clientes creados:' as Info, COUNT(*) as Total FROM clientes;
SELECT 'Reservas pendientes:' as Info, COUNT(*) as Total FROM reservas WHERE estado = 'pendiente';
SELECT 'Administradores:' as Info, COUNT(*) as Total FROM administradores;

SELECT '=== BASE DE DATOS LIMPIA Y LISTA PARA PROBAR ===' as Estado;
