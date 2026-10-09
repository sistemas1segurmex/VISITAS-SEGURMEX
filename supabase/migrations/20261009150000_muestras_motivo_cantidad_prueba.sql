-- «Solicitar muestra»: datos que pedía el formato anterior (cotizador) y
-- que Visitas no tenía (09-oct-2026):
--   motivo             -- para qué es la muestra (obligatorio en solicitudes nuevas;
--                         la validación va en api/muestra_solicitar.php porque
--                         las de antes quedan sin motivo)
--   notas_planta       -- detalle libre para quien la prepara (opcional)
--   cantidad           -- pares (antes siempre era 1)
--   tiempo_prueba_dias -- cuántos días la va a probar el cliente (opcional)
-- Todo sale en el detalle, en el correo y en el PDF de la solicitud
-- (muestra_pdf.php).
--
-- Correr ANTES de subir el código (api/muestra_solicitar.php inserta estas
-- columnas). Idempotente.
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS motivo VARCHAR(500);
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS notas_planta VARCHAR(2000);
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS cantidad INTEGER NOT NULL DEFAULT 1;
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS tiempo_prueba_dias INTEGER;

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_cantidad;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT ck_muestra_cantidad
  CHECK (cantidad BETWEEN 1 AND 99);

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_tiempo_prueba;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT ck_muestra_tiempo_prueba
  CHECK (tiempo_prueba_dias IS NULL OR tiempo_prueba_dias BETWEEN 1 AND 365);
