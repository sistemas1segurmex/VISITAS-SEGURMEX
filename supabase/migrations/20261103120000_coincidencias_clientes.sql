-- Coincidencias de clientes entre vendedores (03-nov-2026).
--
-- Cuando un vendedor da de alta (o edita) un cliente/prospecto que parece
-- ser el mismo que ya tiene OTRO vendedor en el mismo municipio (nombre
-- parecido + misma colonia/CP/GPS cercano, mismo teléfono o mismo cliente
-- del ERP), el registro se guarda pero queda "en_revision": el vendedor no
-- puede trabajarlo (citas, etapa, edición, cotización, muestras) hasta que
-- la responsable de muestras (rol 'muestras') decida en su pestaña
-- "Coincidencias" (ver includes/coincidencias.php):
--   distintos           -> son clientes diferentes, se libera el nuevo
--   se_queda_existente  -> el nuevo queda "descartado" (no se borra: es la
--                          evidencia de que se intentó registrar)
--   pasa_a_nuevo        -> el cliente existente cambia de vendedor; el
--                          registro nuevo queda "descartado" por duplicado.
--                          Las citas pendientes del vendedor anterior se
--                          quedan con él, pero ya no puede reprogramarlas
--                          ni agendar/cotizar/pedir muestras.
--
-- Misma empresa en otro municipio = sucursal, NO es coincidencia.
-- Correr ANTES de subir el código. Idempotente.
SET search_path TO visitas;

-- NULL = normal; 'en_revision' | 'descartado'. Sin CHECK a propósito (mismo
-- criterio que clientes.etapa: la lista vive en el código).
ALTER TABLE visitas.clientes ADD COLUMN IF NOT EXISTS estado_revision VARCHAR(20);
CREATE INDEX IF NOT EXISTS idx_clientes_estado_revision
  ON visitas.clientes (estado_revision) WHERE estado_revision IS NOT NULL;

CREATE TABLE IF NOT EXISTS visitas.coincidencias_clientes (
  id SERIAL PRIMARY KEY,
  cliente_nuevo_id INTEGER NOT NULL REFERENCES visitas.clientes(id) ON DELETE CASCADE,
  cliente_existente_id INTEGER NOT NULL REFERENCES visitas.clientes(id) ON DELETE CASCADE,
  vendedor_nuevo_id INTEGER NOT NULL REFERENCES visitas.usuarios(id),
  vendedor_existente_id INTEGER NOT NULL REFERENCES visitas.usuarios(id),
  razon VARCHAR(255) NOT NULL,
  origen VARCHAR(10) NOT NULL DEFAULT 'alta',        -- 'alta' | 'edicion'
  estado VARCHAR(25) NOT NULL DEFAULT 'pendiente',   -- pendiente | distintos | se_queda_existente | pasa_a_nuevo | anulada
  nota VARCHAR(500),
  resuelto_por INTEGER REFERENCES visitas.usuarios(id),
  resuelto_en TIMESTAMP,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_coincidencias_estado ON visitas.coincidencias_clientes (estado, creado_en DESC);
CREATE INDEX IF NOT EXISTS idx_coincidencias_nuevo ON visitas.coincidencias_clientes (cliente_nuevo_id);
CREATE INDEX IF NOT EXISTS idx_coincidencias_existente ON visitas.coincidencias_clientes (cliente_existente_id);

RESET search_path;
