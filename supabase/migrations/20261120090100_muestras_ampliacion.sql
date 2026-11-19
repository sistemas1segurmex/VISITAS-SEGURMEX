-- Muestras: AMPLIACIÓN autorizada del tope de pares (20-nov-2026).
--
-- El tope normal sigue siendo de 6 pares por cliente al mes. Si el vendedor
-- externo necesita más (hasta 20 pares en una sola solicitud) porque el cliente
-- tiene una proyección de compra de 2,500 pares o más, la solicitud NO se manda
-- directo a producción: queda «por_autorizar» hasta que la responsable de
-- muestras (rol 'muestras') la autorice o la rechace.
--
--   por_autorizar --autoriza--> enviada -> en_preparacion -> embarcada
--   por_autorizar --rechaza---> cancelada (motivo_cancelacion = «No autorizada: …»)
--
-- Columnas nuevas en muestras_solicitudes:
--   ampliacion                bandera: la solicitud pasó el tope normal y pidió autorización
--   proyeccion_pares          proyección de compra que declaró el vendedor (>= 2500)
--   ampliacion_justificacion  por qué la pide
--   ampliacion_usuario_id     quién la autorizó o rechazó
--   ampliacion_resuelta_en    cuándo
-- Las solicitudes con ampliacion = true NO cuentan para el tope normal de 6.
--
-- El CHECK de estado era en línea (sin nombre en la migración original): Postgres
-- lo llamó muestras_solicitudes_estado_check. Se vuelve a crear con 'por_autorizar'.
--
-- Historial (muestras_solicitudes_historial.estado, sin CHECK): se agregan
-- 'por_autorizar' y 'amp_autorizada'. Avisos (tabla avisos): tipos nuevos
-- 'muestra_ampliacion', 'muestra_autorizada' y 'muestra_no_autorizada'.
--
-- Mismo cambio en el ERP para los internos
-- (erp/supabase/migrations/20261120090000_muestras_internas_ampliacion.sql).
--
-- Se puede correr más de una vez. Todo con el esquema escrito (visitas.).
-- Correr primero en Supabase (pruebas) y después en la base real, ANTES de
-- subir el código.
--
-- REVERSA (solo si no hay solicitudes por autorizar):
--   ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_ampliacion;
--   ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS muestras_solicitudes_estado_check;
--   ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT muestras_solicitudes_estado_check
--     CHECK (estado IN ('enviada', 'en_preparacion', 'embarcada', 'cancelada'));
--   ALTER TABLE visitas.muestras_solicitudes DROP COLUMN IF EXISTS ampliacion,
--     DROP COLUMN IF EXISTS proyeccion_pares, DROP COLUMN IF EXISTS ampliacion_justificacion,
--     DROP COLUMN IF EXISTS ampliacion_usuario_id, DROP COLUMN IF EXISTS ampliacion_resuelta_en;

ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS ampliacion               BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS proyeccion_pares         INTEGER;
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS ampliacion_justificacion VARCHAR(500);
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS ampliacion_usuario_id    INTEGER REFERENCES visitas.usuarios(id) ON DELETE SET NULL;
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS ampliacion_resuelta_en   TIMESTAMP;

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS muestras_solicitudes_estado_check;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT muestras_solicitudes_estado_check
  CHECK (estado IN ('por_autorizar', 'enviada', 'en_preparacion', 'embarcada', 'cancelada'));

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_ampliacion;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT ck_muestra_ampliacion
  CHECK (NOT ampliacion OR (proyeccion_pares IS NOT NULL AND proyeccion_pares > 0));

CREATE INDEX IF NOT EXISTS idx_muestras_sol_por_autorizar ON visitas.muestras_solicitudes (created_at) WHERE estado = 'por_autorizar';

-- VERIFICACIÓN:
--   SELECT column_name FROM information_schema.columns
--    WHERE table_schema = 'visitas' AND table_name = 'muestras_solicitudes'
--      AND (column_name LIKE 'ampliacion%' OR column_name = 'proyeccion_pares');   -- 5 renglones
--   SELECT conname FROM pg_constraint WHERE conrelid = 'visitas.muestras_solicitudes'::regclass AND contype = 'c';
--     -- debe listar muestras_solicitudes_estado_check (si el CHECK original tenía otro nombre, el ADD de arriba
--     -- dejaría dos CHECK de estado: borrar el viejo a mano).
