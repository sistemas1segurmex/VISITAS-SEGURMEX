-- «Solicitar muestra»: contacto del cliente (09-oct-2026).
-- Se copia de la ficha del cliente al pedir la muestra (foto del momento,
-- igual que cliente_nombre), para que el PDF de una solicitud vieja no cambie
-- si después editan la ficha. Si a la ficha le faltaba el nombre de contacto
-- o el teléfono, el vendedor los escribe en el formulario (obligatorios) y
-- también se guardan en la ficha. Las solicitudes de antes quedan en NULL y
-- toman el dato de la ficha (ver cargarSolicitudMuestra()).
--
-- Correr ANTES de subir el código (api/muestra_solicitar.php inserta estas
-- columnas). Idempotente.
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS contacto_nombre VARCHAR(150);
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS contacto_telefono VARCHAR(20);
