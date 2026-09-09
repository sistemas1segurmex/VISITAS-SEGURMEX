-- Registro público de vendedores nuevos vía link de invitación de un solo
-- uso, con vencimiento, que el admin genera y aprueba después de que el
-- vendedor llena su propio formulario (ver registro_vendedor.php).
-- Idempotente.
SET search_path TO visitas;

CREATE TABLE IF NOT EXISTS invitaciones_vendedor (
  id SERIAL PRIMARY KEY,
  token VARCHAR(64) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL,
  creado_por INTEGER NOT NULL REFERENCES usuarios(id),
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expira_en TIMESTAMP NOT NULL,
  usado_en TIMESTAMP,
  usuario_creado_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_invitaciones_vendedor_token ON invitaciones_vendedor(token);
