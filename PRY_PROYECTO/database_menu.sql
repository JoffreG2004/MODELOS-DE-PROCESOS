-- ============================================================================
-- SCRIPT SQL PARA CREAR TABLAS DE MENÚ (CATEGORÍAS Y PLATOS)
-- Sistema de Reservas de Restaurante
-- ============================================================================

-- Usar la base de datos
USE crud_proyecto;

-- ============================================================================
-- TABLA: categorias
-- ============================================================================
CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `orden_menu` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categorias_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: platos
-- ============================================================================
CREATE TABLE IF NOT EXISTS `platos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT 0.00,
  `stock_disponible` int(11) DEFAULT 0,
  `tiempo_preparacion` int(11) DEFAULT NULL COMMENT 'Tiempo en minutos',
  `imagen_url` varchar(500) DEFAULT NULL,
  `ingredientes` text DEFAULT NULL,
  `es_especial` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_platos_nombre_categoria` (`nombre`, `categoria_id`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_es_especial` (`es_especial`),
  CONSTRAINT `fk_platos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DATOS DE EJEMPLO (OPCIONAL - Puedes comentar si no quieres datos de ejemplo)
-- ============================================================================

-- Insertar categorías de ejemplo
INSERT INTO `categorias` (`nombre`, `descripcion`, `orden_menu`, `activo`) VALUES
('Entradas', 'Platos para comenzar la experiencia gastronómica', 1, 1),
('Platos Principales', 'Especialidades de la casa y platos principales', 2, 1),
('Carnes', 'Selección premium de carnes y parrillas', 3, 1),
('Mariscos', 'Frescos del mar preparados con maestría', 4, 1),
('Postres', 'Dulces creaciones para finalizar', 5, 1),
('Bebidas', 'Selección de bebidas y cocteles', 6, 1)
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    orden_menu = VALUES(orden_menu);

-- Insertar platos de ejemplo
INSERT INTO `platos` (`categoria_id`, `nombre`, `descripcion`, `precio`, `stock_disponible`, `tiempo_preparacion`, `imagen_url`, `ingredientes`, `es_especial`, `activo`) VALUES
-- Entradas
(1, 'Ceviche de Camarón', 'Camarones frescos marinados en limón con cebolla morada y cilantro', 12.50, 50, 15, 'https://example.com/ceviche.jpg', 'Camarones, limón, cebolla morada, cilantro, ají', 1, 1),
(1, 'Empanadas de Queso', 'Empanadas crujientes rellenas de queso mozzarella', 5.00, 100, 10, 'https://example.com/empanadas.jpg', 'Masa, queso mozzarella, aceite', 0, 1),

-- Platos Principales
(2, 'Lomo Saltado', 'Carne de res salteada con cebolla, tomate y papas fritas', 18.00, 30, 25, 'https://example.com/lomo.jpg', 'Carne de res, cebolla, tomate, papas, salsa de soja', 1, 1),
(2, 'Arroz con Mariscos', 'Arroz con mariscos variados en salsa de ají', 22.00, 25, 30, 'https://example.com/arroz.jpg', 'Arroz, camarones, calamares, mejillones, ají amarillo', 1, 1),

-- Postres
(5, 'Tiramisu', 'Postre italiano con café y mascarpone', 6.50, 40, 5, 'https://example.com/tiramisu.jpg', 'Queso mascarpone, café, bizcocho, cacao', 0, 1),

-- Bebidas
(6, 'Limonada Natural', 'Refrescante limonada con hielo y menta', 3.50, 999, 5, 'https://example.com/limonada.jpg', 'Limones, azúcar, agua, menta', 0, 1)
ON DUPLICATE KEY UPDATE 
    descripcion = VALUES(descripcion),
    precio = VALUES(precio),
    stock_disponible = VALUES(stock_disponible);

-- ============================================================================
-- VERIFICACIÓN
-- ============================================================================

-- Mostrar categorías creadas
SELECT '=== CATEGORÍAS CREADAS ===' AS '';
SELECT * FROM categorias ORDER BY orden_menu;

-- Mostrar platos creados
SELECT '=== PLATOS CREADOS ===' AS '';
SELECT p.id, c.nombre AS categoria, p.nombre, p.precio, p.stock_disponible, p.activo 
FROM platos p 
JOIN categorias c ON p.categoria_id = c.id 
ORDER BY c.orden_menu, p.nombre;

-- ============================================================================
-- FIN DEL SCRIPT
-- ============================================================================
