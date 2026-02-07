CREATE DATABASE IF NOT EXISTS resto_mini CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE resto_mini;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE IF NOT EXISTS mesas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero INT NOT NULL,
    capacidad INT NOT NULL
);

CREATE TABLE IF NOT EXISTS platos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    precio DECIMAL(8,2) NOT NULL
);

CREATE TABLE IF NOT EXISTS reservas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    mesa_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    num_personas INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    FOREIGN KEY (mesa_id) REFERENCES mesas(id)
);

INSERT INTO usuarios (nombre, email, password) VALUES
('Admin', 'admin@demo.com', 'admin123');

INSERT INTO mesas (numero, capacidad) VALUES
(1, 2),
(2, 4),
(3, 4),
(4, 6);

INSERT INTO platos (nombre, precio) VALUES
('Lomo Saltado', 9.50),
('Aji de Gallina', 8.00),
('Ceviche', 10.00),
('Arroz con Pollo', 7.50);

INSERT INTO reservas (usuario_id, mesa_id, fecha, hora, num_personas) VALUES
(1, 2, '2026-02-05', '19:30:00', 4);
