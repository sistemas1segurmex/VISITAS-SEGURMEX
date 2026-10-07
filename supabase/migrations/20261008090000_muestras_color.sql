-- «Solicitar muestra» pide el color cuando el modelo tiene varios (igual que
-- la Nueva cotización: 5021 negro o café, DK-202 negro, café o miel…).
-- Opcional: las solicitudes de antes quedan sin color.
--
-- Correr ANTES de subir el código (api/muestra_solicitar.php inserta color).
-- Idempotente.
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS color VARCHAR(40);
