-- Catálogo de colonias/CP de México (SEPOMEX) para validar direcciones al
-- registrar clientes, y columnas estructuradas de dirección en clientes.
-- Ejecutar una sola vez en: Supabase Dashboard > SQL Editor > pegar todo > Run.
-- (También se puede correr con db_seed/migrar_sepomex.php desde la app).

SET search_path TO visitas;

CREATE TABLE IF NOT EXISTS sepomex_colonias (
  id SERIAL PRIMARY KEY,
  cp VARCHAR(5) NOT NULL,
  asentamiento VARCHAR(150) NOT NULL,
  tipo_asentamiento VARCHAR(50),
  municipio VARCHAR(150) NOT NULL,
  estado VARCHAR(100) NOT NULL,
  ciudad VARCHAR(150),
  zona VARCHAR(20)
);

CREATE INDEX IF NOT EXISTS idx_sepomex_cp ON sepomex_colonias (cp);
CREATE INDEX IF NOT EXISTS idx_sepomex_estado ON sepomex_colonias (estado);
CREATE INDEX IF NOT EXISTS idx_sepomex_estado_municipio ON sepomex_colonias (estado, municipio);
CREATE INDEX IF NOT EXISTS idx_sepomex_asentamiento ON sepomex_colonias (lower(asentamiento));

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS calle_numero VARCHAR(200);
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS colonia VARCHAR(150);
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS municipio VARCHAR(150);
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS estado VARCHAR(100);
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cp VARCHAR(10);
