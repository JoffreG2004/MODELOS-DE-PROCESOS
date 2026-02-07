-- ============================================
-- LIMPIAR BASE DE DATOS Y DEJAR 10 REGISTROS DE PRUEBA
-- Basado en INSTALACION_COMPLETA_BASE_DATOS.sql
-- ============================================

USE crud_proyecto;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- LIMPIAR TODAS LAS TABLAS (excepto admin)
-- ============================================

DELETE FROM auditoria_reservas;
DELETE FROM auditoria_sistema;
DELETE FROM auditoria_horarios;
DELETE FROM historial_estados;
DELETE FROM notificaciones_whatsapp;
DELETE FROM reservas_zonas;
DELETE FROM notas_consumo;
DELETE FROM pre_pedidos;
DELETE FROM reservas;
DELETE FROM mesas;
DELETE FROM clientes;
DELETE FROM platos;
DELETE FROM categorias_platos;

-- ============================================
-- INSERTAR 6 CATEGORÍAS DE PLATOS
-- ============================================

INSERT INTO categorias_platos (id, nombre, descripcion, orden_menu, activo) VALUES 
(1, 'Entradas', 'Platos para comenzar la experiencia gastronómica', 1, 1),
(2, 'Platos Principales', 'Especialidades de la casa y platos principales', 2, 1),
(3, 'Carnes', 'Selección premium de carnes y parrillas', 3, 1),
(4, 'Mariscos', 'Frescos del mar preparados con maestría', 4, 1),
(5, 'Postres', 'Dulces creaciones para finalizar', 5, 1),
(6, 'Bebidas', 'Selección de bebidas y cocteles', 6, 1);

-- ============================================
-- INSERTAR 10 PLATOS DE EJEMPLO
-- ============================================

INSERT INTO platos (id, categoria_id, nombre, descripcion, precio, stock_disponible, imagen_url, activo, tiempo_preparacion) VALUES 
(1, 1, 'Croquetas de jamón', 'Crujientes croquetas de jamón serrano', 7.50, 20, 'https://picsum.photos/200?food1', 1, 15),
(2, 1, 'Ensalada César', 'Clásica ensalada con aderezo César', 6.80, 25, 'https://picsum.photos/200?food2', 1, 10),
(3, 2, 'Lasaña boloñesa', 'Lasaña casera con salsa boloñesa', 14.50, 15, 'https://picsum.photos/200?food3', 1, 25),
(4, 2, 'Risotto de hongos', 'Risotto cremoso con variedad de hongos', 16.20, 12, 'https://picsum.photos/200?food4', 1, 20),
(5, 3, 'Lomo fino', 'Lomo fino a la parrilla en su punto', 22.50, 10, 'https://picsum.photos/200?food5', 1, 25),
(6, 3, 'Costillas BBQ', 'Costillas de cerdo con salsa BBQ', 19.90, 12, 'https://picsum.photos/200?food6', 1, 30),
(7, 4, 'Ceviche mixto', 'Ceviche de pescado y mariscos frescos', 13.80, 18, 'https://picsum.photos/200?food7', 1, 15),
(8, 4, 'Camarones al ajillo', 'Camarones salteados en ajo', 15.50, 16, 'https://picsum.photos/200?food8', 1, 18),
(9, 5, 'Tiramisú', 'Postre italiano con café', 6.50, 20, 'https://picsum.photos/200?food9', 1, 5),
(10, 6, 'Limonada natural', 'Refrescante limonada casera', 3.50, 40, 'https://picsum.photos/200?food10', 1, 5);

-- ============================================
-- INSERTAR 10 MESAS LIMPIAS
-- ============================================

INSERT INTO mesas (id, numero_mesa, capacidad_minima, capacidad_maxima, precio_reserva, ubicacion, estado, descripcion) VALUES 
(1, 'M01', 1, 2, 5.00, 'interior', 'disponible', 'Mesa íntima junto a la ventana'),
(2, 'M02', 2, 4, 6.00, 'interior', 'disponible', 'Mesa familiar en el centro del salón'),
(3, 'M03', 2, 4, 6.00, 'interior', 'disponible', 'Mesa mediana cerca de la cocina'),
(4, 'M04', 4, 6, 8.00, 'interior', 'disponible', 'Mesa grande para grupos'),
(5, 'T01', 2, 4, 6.00, 'terraza', 'disponible', 'Mesa con vista al jardín'),
(6, 'T02', 4, 6, 8.00, 'terraza', 'disponible', 'Mesa grande en terraza'),
(7, 'V01', 4, 8, 10.00, 'vip', 'disponible', 'Mesa VIP con servicio exclusivo'),
(8, 'V02', 6, 10, 10.00, 'vip', 'disponible', 'Sala privada VIP grande'),
(9, 'B01', 2, 4, 6.00, 'bar', 'disponible', 'Mesa de bar alta'),
(10, 'B02', 1, 2, 5.00, 'bar', 'disponible', 'Mesa de bar con vista a cocina');

-- ============================================
-- INSERTAR 10 CLIENTES DE PRUEBA
-- ============================================

INSERT INTO clientes (id, nombre, apellido, cedula, telefono, ciudad, usuario, password_hash, email) VALUES 
(1, 'Joffre', 'Quezada', '1728677574', '+593998521340', 'Machachi', 'joffre', '20082004', 'joffregq2004@gmail.com'),
(2, 'María', 'González', '1234567890', '+593991234567', 'Quito', 'maria.g', 'maria123', 'maria.gonzalez@email.com'),
(3, 'Carlos', 'Rodríguez', '0987654321', '+593992345678', 'Guayaquil', 'carlos.r', 'carlos456', 'carlos.rodriguez@email.com'),
(4, 'Ana', 'López', '1122334455', '+593993456789', 'Cuenca', 'ana.l', 'ana789', 'ana.lopez@email.com'),
(5, 'Diego', 'Martínez', '5544332211', '+593994567890', 'Quito', 'diego.m', 'diego321', 'diego.martinez@email.com'),
(6, 'Sofía', 'Hernández', '6677889900', '+593995678901', 'Ambato', 'sofia.h', 'sofia654', 'sofia.hernandez@email.com'),
(7, 'Roberto', 'Silva', '9988776655', '+593996789012', 'Manta', 'roberto.s', 'roberto987', 'roberto.silva@email.com'),
(8, 'Isabella', 'Morales', '1357924680', '+593997890123', 'Loja', 'isabella.m', 'isabella246', 'isabella.morales@email.com'),
(9, 'Fernando', 'Castro', '2468013579', '+593998901234', 'Machala', 'fernando.c', 'fernando135', 'fernando.castro@email.com'),
(10, 'Valentina', 'Ruiz', '3691470258', '+593999012345', 'Riobamba', 'valentina.r', 'valentina802', 'valentina.ruiz@email.com');

-- ============================================
-- INSERTAR 5 RESERVAS PENDIENTES PARA PROBAR
-- (Fechas futuras para que aparezcan en el panel)
-- ============================================

INSERT INTO reservas (id, cliente_id, mesa_id, fecha_reserva, hora_reserva, numero_personas, estado, motivo_cancelacion) VALUES 
(1, 1, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '19:00:00', 2, 'pendiente', NULL),
(2, 2, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '20:00:00', 4, 'pendiente', NULL),
(3, 3, 3, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '19:30:00', 4, 'pendiente', NULL),
(4, 4, 5, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '21:00:00', 4, 'pendiente', NULL),
(5, 5, 7, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '18:30:00', 6, 'pendiente', NULL);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- VERIFICAR RESULTADOS
-- ============================================

SELECT '✅ BASE DE DATOS LIMPIADA CORRECTAMENTE' as 'RESULTADO';
SELECT '-------------------------------------------' as '';
SELECT CONCAT('Categorías: ', COUNT(*)) as 'Conteo' FROM categorias_platos;
SELECT CONCAT('Platos: ', COUNT(*)) as 'Conteo' FROM platos;
SELECT CONCAT('Mesas: ', COUNT(*)) as 'Conteo' FROM mesas;
SELECT CONCAT('Clientes: ', COUNT(*)) as 'Conteo' FROM clientes;
SELECT CONCAT('Reservas pendientes: ', COUNT(*)) as 'Conteo' FROM reservas WHERE estado = 'pendiente';
SELECT CONCAT('Administradores: ', COUNT(*)) as 'Conteo' FROM administradores;
SELECT '-------------------------------------------' as '';
SELECT '✅ LISTO PARA PROBAR EL SISTEMA' as 'ESTADO';
