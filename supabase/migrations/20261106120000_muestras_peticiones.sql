-- Muestras: el vendedor PIDE una corrección o una cancelación (06-nov-2026).
--
-- Una vez enviada la solicitud el vendedor ya no la edita: desde «Mis
-- muestras» le pide el cambio a la responsable de muestras y ella decide (la
-- corrige, contesta «no se puede» o la cancela). Mismo flujo en el ERP para
-- los internos (erp/supabase/migrations/20261106120000_muestras_internas_peticiones.sql).
--
-- Una petición pendiente por solicitud (columnas peticion_*); al atenderla se
-- limpian. Lo que pasó queda en muestras_solicitudes_historial con estos
-- valores nuevos en "estado" (la columna no tiene CHECK):
--   correccion_pedida, cancelacion_pedida  el vendedor la pidió (nota = su texto)
--   corregida                              la responsable la corrigió (nota = qué cambió)
--   peticion_rechazada                     la responsable contestó «no se puede» (nota = por qué)
-- La cancelación sigue siendo el estado «cancelada» de siempre.
--
-- Se puede correr más de una vez. Todo con el esquema escrito (visitas.).
-- Correr primero en Supabase (pruebas) y después en la base real, ANTES de
-- subir el código.
--
-- REVERSA:
--   ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_peticion_tipo,
--     DROP COLUMN IF EXISTS peticion_tipo, DROP COLUMN IF EXISTS peticion_texto,
--     DROP COLUMN IF EXISTS peticion_en, DROP COLUMN IF EXISTS peticion_usuario_id;

ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS peticion_tipo       VARCHAR(15);
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS peticion_texto      VARCHAR(1000);
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS peticion_en         TIMESTAMP;
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS peticion_usuario_id INTEGER REFERENCES visitas.usuarios(id) ON DELETE SET NULL;

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_peticion_tipo;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT ck_muestra_peticion_tipo
  CHECK (peticion_tipo IS NULL OR peticion_tipo IN ('correccion', 'cancelacion'));

CREATE INDEX IF NOT EXISTS idx_muestras_peticion ON visitas.muestras_solicitudes (peticion_en) WHERE peticion_tipo IS NOT NULL;

-- VERIFICACIÓN:
--   SELECT column_name FROM information_schema.columns
--    WHERE table_schema = 'visitas' AND table_name = 'muestras_solicitudes' AND column_name LIKE 'peticion%';  -- 4
