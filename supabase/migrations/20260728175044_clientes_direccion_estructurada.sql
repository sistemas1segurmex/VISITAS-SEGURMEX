-- Agrega campos estructurados de dirección al cliente (código postal, estado,
-- municipio, colonia), para autocompletar por CP usando el catálogo SEPOMEX.
-- La columna "direccion" existente se sigue usando como texto completo/legible
-- (se compone automáticamente en el backend); no se toca su contenido actual.

SET search_path TO visitas;

ALTER TABLE clientes
  ADD COLUMN IF NOT EXISTS codigo_postal VARCHAR(5),
  ADD COLUMN IF NOT EXISTS estado VARCHAR(100),
  ADD COLUMN IF NOT EXISTS municipio VARCHAR(150),
  ADD COLUMN IF NOT EXISTS colonia VARCHAR(150);

CREATE INDEX IF NOT EXISTS idx_clientes_estado ON clientes(estado);
