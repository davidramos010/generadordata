-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS admin_BD;
USE admin_BD;

-- Crear la tabla conter para el contador de visitas
CREATE TABLE IF NOT EXISTS conter (
    page VARCHAR(50) PRIMARY KEY,
    num INT DEFAULT 0
);

-- Insertar registro inicial para la página index
INSERT INTO conter (page, num) VALUES ('index', 0) 
ON DUPLICATE KEY UPDATE page = page;
