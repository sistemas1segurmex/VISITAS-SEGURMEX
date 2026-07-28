-- Esquema de la base de datos "visitas_control"
-- Sistema de control de visitas de vendedores (independiente del ERP).
-- Generado y ya ejecutado en el MySQL local de XAMPP. Este archivo queda
-- como respaldo/documentación para poder recrear la base en otro entorno.

CREATE DATABASE IF NOT EXISTS visitas_control CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE visitas_control;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('vendedor','admin') NOT NULL DEFAULT 'vendedor',
  telefono VARCHAR(20) DEFAULT NULL,
  estado_operacion VARCHAR(100) DEFAULT NULL COMMENT 'estado/region donde opera el vendedor',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE clientes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendedor_id INT NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  direccion VARCHAR(255) NOT NULL,
  lat DECIMAL(10,7) DEFAULT NULL,
  lng DECIMAL(10,7) DEFAULT NULL,
  telefono VARCHAR(20) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_clientes_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE citas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendedor_id INT NOT NULL,
  cliente_id INT NOT NULL,
  fecha_hora DATETIME NOT NULL,
  notas TEXT,
  estado ENUM('pendiente','en_curso','completada','no_realizada') NOT NULL DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_citas_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_citas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE checkins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cita_id INT NOT NULL,
  tipo ENUM('entrada','salida') NOT NULL DEFAULT 'entrada',
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  distancia_metros DECIMAL(10,2) DEFAULT NULL,
  foto_path VARCHAR(255) DEFAULT NULL,
  verificado TINYINT(1) NOT NULL DEFAULT 0,
  fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_checkins_cita FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tracking_ubicaciones (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  vendedor_id INT NOT NULL,
  lat DECIMAL(10,7) NOT NULL,
  lng DECIMAL(10,7) NOT NULL,
  fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_tracking_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_tracking_vendedor_fecha ON tracking_ubicaciones(vendedor_id, fecha_hora);

CREATE TABLE alertas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  vendedor_id INT NOT NULL,
  cita_id INT DEFAULT NULL,
  tipo VARCHAR(50) NOT NULL COMMENT 'retraso, fuera_de_zona, sin_checkin',
  mensaje VARCHAR(255) NOT NULL,
  resuelta TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_alertas_vendedor FOREIGN KEY (vendedor_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_alertas_cita FOREIGN KEY (cita_id) REFERENCES citas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_citas_fecha ON citas(fecha_hora);
CREATE INDEX idx_citas_vendedor ON citas(vendedor_id);

-- Usuarios de prueba (password para ambos: Segurmex2026)
-- Cambien la contraseña después del primer login.
INSERT INTO usuarios (nombre, email, password_hash, rol, estado_operacion, activo) VALUES
('Ing. Yancy', 'sistemas1@segurmex.com.mx', '$2b$10$TYtiS7k4VrCNNmXBCabeVOUze1z0i9Cd8FGsG5iFOmY0nScXLlVKO', 'admin', NULL, 1),
('Vendedor Demo', 'vendedor.demo@segurmex.com.mx', '$2b$10$TYtiS7k4VrCNNmXBCabeVOUze1z0i9Cd8FGsG5iFOmY0nScXLlVKO', 'vendedor', 'Jalisco', 1);
