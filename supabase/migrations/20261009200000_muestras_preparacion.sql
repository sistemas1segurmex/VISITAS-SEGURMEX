-- «En preparación» con detalle (09-oct-2026). Al marcarla en preparación la
-- responsable de muestras indica de dónde sale:
--   'pt'            -- ya hay en Producto Terminado; solo falta preparar el envío
--   'por_programar' -- hay que mandarla a fabricar (fecha estimada opcional)
-- Una 'por_programar' puede pasar después a 'pt' («Ya está en Producto
-- Terminado»). El estado sigue siendo 'en_preparacion' en ambos casos: la
-- bandeja, los contadores y el tope no cambian. Las de antes quedan en NULL.
--
-- Correr ANTES de subir el código (includes/muestras.php actualiza estas
-- columnas). Idempotente.
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS preparacion VARCHAR(15);
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS fecha_estimada_pt DATE;

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_preparacion;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT ck_muestra_preparacion
  CHECK (preparacion IS NULL OR preparacion IN ('pt', 'por_programar'));
