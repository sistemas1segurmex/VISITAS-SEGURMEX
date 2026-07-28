-- Esquema "visitas" para el sistema de Control de Visitas de Vendedores.
-- Vive en el MISMO proyecto Supabase que el ERP, pero en un esquema aparte
-- (no toca las tablas de public/ERP). Ejecutar una sola vez en:
-- Supabase Dashboard > SQL Editor > pegar todo > Run.

CREATE SCHEMA IF NOT EXISTS visitas;
SET search_path TO visitas;

CREATE TABLE IF NOT EXISTS usuarios (
  id SERIAL PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  rol VARCHAR(20) NOT NULL DEFAULT 'vendedor' CHECK (rol IN ('vendedor','admin')),
  telefono VARCHAR(20),
  estado_operacion VARCHAR(100), -- estado/region donde opera el vendedor
  activo SMALLINT NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clientes (
  id SERIAL PRIMARY KEY,
  vendedor_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  nombre VARCHAR(150) NOT NULL,
  direccion VARCHAR(255) NOT NULL,
  lat NUMERIC(10,7),
  lng NUMERIC(10,7),
  telefono VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS citas (
  id SERIAL PRIMARY KEY,
  vendedor_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  cliente_id INT NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  fecha_hora TIMESTAMP NOT NULL,
  notas TEXT,
  estado VARCHAR(20) NOT NULL DEFAULT 'pendiente'
      CHECK (estado IN ('pendiente','en_curso','completada','no_realizada')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS checkins (
  id SERIAL PRIMARY KEY,
  cita_id INT NOT NULL REFERENCES citas(id) ON DELETE CASCADE,
  tipo VARCHAR(10) NOT NULL DEFAULT 'entrada' CHECK (tipo IN ('entrada','salida')),
  lat NUMERIC(10,7) NOT NULL,
  lng NUMERIC(10,7) NOT NULL,
  distancia_metros NUMERIC(10,2),
  foto_path VARCHAR(255),
  verificado SMALLINT NOT NULL DEFAULT 0,
  fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS tracking_ubicaciones (
  id BIGSERIAL PRIMARY KEY,
  vendedor_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  lat NUMERIC(10,7) NOT NULL,
  lng NUMERIC(10,7) NOT NULL,
  fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_tracking_vendedor_fecha ON tracking_ubicaciones(vendedor_id, fecha_hora);

CREATE TABLE IF NOT EXISTS alertas (
  id SERIAL PRIMARY KEY,
  vendedor_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  cita_id INT REFERENCES citas(id) ON DELETE SET NULL,
  tipo VARCHAR(50) NOT NULL, -- retraso, fuera_de_zona, sin_checkin
  mensaje VARCHAR(255) NOT NULL,
  resuelta SMALLINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_citas_fecha ON citas(fecha_hora);
CREATE INDEX IF NOT EXISTS idx_citas_vendedor ON citas(vendedor_id);

-- Usuarios de prueba (password para ambos: Segurmex2026). Cámbienla después del primer login.
INSERT INTO usuarios (nombre, email, password_hash, rol, estado_operacion, activo) VALUES
('Ing. Yancy', 'sistemas1@segurmex.com.mx', '$2b$10$TYtiS7k4VrCNNmXBCabeVOUze1z0i9Cd8FGsG5iFOmY0nScXLlVKO', 'admin', NULL, 1),
('Vendedor Demo', 'vendedor.demo@segurmex.com.mx', '$2b$10$TYtiS7k4VrCNNmXBCabeVOUze1z0i9Cd8FGsG5iFOmY0nScXLlVKO', 'vendedor', 'Jalisco', 1)
ON CONFLICT (email) DO NOTHING;
