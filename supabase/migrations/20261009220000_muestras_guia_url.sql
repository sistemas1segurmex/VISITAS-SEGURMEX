-- «Marcar embarcada» por paquetería (09-oct-2026): la responsable de muestras
-- ya solo pega el LINK de rastreo de la guía (antes escribía paquetería y
-- número de guía). paqueteria y guia se quedan en la tabla solo para no
-- perder lo de las muestras embarcadas antes; las nuevas no las llenan.
--
-- Correr ANTES de subir el código (includes/muestras.php guarda guia_url).
-- Idempotente.
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS guia_url TEXT;
