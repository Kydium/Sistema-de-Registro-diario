-- ============================================================
-- Sistema de Registro de Servicios Técnicos
-- Ejecuta este archivo UNA SOLA VEZ para crear la base de datos
-- ============================================================

CREATE DATABASE IF NOT EXISTS servicios_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE servicios_db;

-- Usuarios del sistema
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  rol ENUM('administrador','tecnico') NOT NULL DEFAULT 'tecnico',
  creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categorías de servicio
CREATE TABLE IF NOT EXISTS categorias (
  id VARCHAR(120) PRIMARY KEY,
  label VARCHAR(255) NOT NULL,
  icon VARCHAR(20) NOT NULL DEFAULT '🔧',
  color VARCHAR(20) NOT NULL DEFAULT '#475569',
  bg VARCHAR(20) NOT NULL DEFAULT '#eef2f6',
  orden INT DEFAULT 0
);

-- Campos extra del formulario
CREATE TABLE IF NOT EXISTS campos_extra (
  id VARCHAR(120) PRIMARY KEY,
  label VARCHAR(255) NOT NULL,
  tipo VARCHAR(50) DEFAULT 'text',
  placeholder VARCHAR(255) DEFAULT '',
  orden INT DEFAULT 0
);

-- Registros de atenciones
CREATE TABLE IF NOT EXISTS registros (
  id BIGINT PRIMARY KEY,
  nombre VARCHAR(255) NOT NULL,
  fecha DATE NOT NULL,
  documento VARCHAR(50) NOT NULL,
  telefono VARCHAR(50) NOT NULL,
  telefono2 VARCHAR(50) DEFAULT '',
  tipo VARCHAR(120) NOT NULL,
  contexto TEXT,
  completado TINYINT(1) DEFAULT 0,
  creado_por VARCHAR(100) DEFAULT '',
  creado_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Valores de campos extra por registro
CREATE TABLE IF NOT EXISTS registro_extras (
  registro_id BIGINT NOT NULL,
  campo_id VARCHAR(120) NOT NULL,
  valor TEXT,
  PRIMARY KEY (registro_id, campo_id),
  FOREIGN KEY (registro_id) REFERENCES registros(id) ON DELETE CASCADE
);

-- ── DATOS INICIALES ──────────────────────────────────────────

INSERT IGNORE INTO usuarios (usuario, password, nombre, rol) VALUES
('admin',   SHA2('admin123',   256), 'Administrador', 'administrador'),
('tecnico', SHA2('tecnico123', 256), 'Técnico',       'tecnico');

INSERT IGNORE INTO categorias (id, label, icon, color, bg, orden) VALUES
('instalacion',   'Instalación nueva',   '🔌', '#0d7a3d', '#e8f5ee', 1),
('soporte',       'Soporte técnico',     '🔧', '#b54708', '#fef3e8', 2),
('mantenimiento', 'Mantenimiento',       '⚙️', '#1d4ed8', '#e8eefe', 3),
('configuracion', 'Configuración de red','📡', '#6d28d9', '#f0eafd', 4),
('otro',          'Otro',                '📋', '#475569', '#eef2f6', 5);
