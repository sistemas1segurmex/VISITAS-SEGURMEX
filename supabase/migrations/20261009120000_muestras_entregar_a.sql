-- «Solicitar muestra»: a quién se entrega (09-oct-2026).
-- El vendedor elige entre entregar en la dirección del cliente o entregársela
-- a él (su domicilio o una sucursal de paquetería; la escribe en cada
-- solicitud porque cambia). destino_direccion sigue guardando la dirección.
-- Las solicitudes de antes quedan como 'cliente'.
--
-- Correr ANTES de subir el código (api/muestra_solicitar.php inserta
-- entregar_a). Idempotente.
ALTER TABLE visitas.muestras_solicitudes
  ADD COLUMN IF NOT EXISTS entregar_a VARCHAR(10) NOT NULL DEFAULT 'cliente';

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_entregar_a;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT ck_muestra_entregar_a
  CHECK (entregar_a IN ('cliente', 'vendedor'));
