-- Confirmación de la ubicación del cliente en la primera visita.
--
-- Casi todos los clientes se registran desde la oficina buscando la
-- dirección, y en calles largas o carreteras la búsqueda puede dejar el pin
-- a cientos de metros o kilómetros del lugar real -- entonces TODAS las
-- visitas salían "Fuera de zona" aunque el vendedor sí estuviera ahí. Ahora,
-- mientras el pin no esté confirmado, el check-in de entrada con buen GPS le
-- ofrece al vendedor "¿Estás en el lugar del cliente?" y, si dice que sí,
-- el pin pasa a donde está (ver api/checkin.php). Solo una vez por cliente.
--
-- Correr ANTES de subir el código (api/checkin.php y compañía leen estas
-- columnas).
-- Idempotente.
SET search_path TO visitas;

-- ubicacion_fuente: 'registro' (búsqueda/mapa al darlo de alta),
-- 'estoy_aqui' (GPS del vendedor en el lugar al registrarlo/editarlo),
-- 'edicion' (pin movido a mano en Editar cliente), 'checkin' (corregido en
-- una visita), 'checkin_verificado' (un check-in cayó dentro del radio).
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS ubicacion_confirmada BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS ubicacion_fuente VARCHAR(20);
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS ubicacion_confirmada_en TIMESTAMP;

-- El check-in que corrigió el pin cuenta como verificado (verificado = 1,
-- para no mover reportes ni contadores); esto solo cambia la etiqueta.
ALTER TABLE checkins ADD COLUMN IF NOT EXISTS ubicacion_corregida BOOLEAN NOT NULL DEFAULT FALSE;

-- Historial de correcciones, para que el admin las revise y pueda revertir.
-- estado: 'aplicada' (hasta 3 km, se corrigió sola), 'por_revisar' (de 3 a
-- 20 km, o la salida se registró lejos de la entrada), 'aprobada' (el admin
-- la revisó), 'revertida' (el admin la deshizo: pin anterior de vuelta y
-- ese check-in queda "Fuera de zona").
CREATE TABLE IF NOT EXISTS correcciones_ubicacion (
  id SERIAL PRIMARY KEY,
  cliente_id INTEGER NOT NULL REFERENCES clientes(id) ON DELETE CASCADE,
  checkin_id INTEGER NOT NULL REFERENCES checkins(id) ON DELETE CASCADE,
  vendedor_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  lat_anterior NUMERIC(10,7),
  lng_anterior NUMERIC(10,7),
  lat_nueva NUMERIC(10,7) NOT NULL,
  lng_nueva NUMERIC(10,7) NOT NULL,
  distancia_metros NUMERIC(10,2),
  accuracy DOUBLE PRECISION,
  estado VARCHAR(20) NOT NULL,
  nota TEXT,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  revisado_por INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  revisado_en TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_correcciones_ubicacion_estado ON correcciones_ubicacion(estado, creado_en);

-- Clientes que ya tienen un check-in verificado: su pin ya está comprobado.
UPDATE clientes cl
SET ubicacion_confirmada = TRUE,
    ubicacion_fuente = 'checkin_verificado',
    ubicacion_confirmada_en = CURRENT_TIMESTAMP
WHERE cl.ubicacion_confirmada = FALSE
  AND EXISTS (
    SELECT 1 FROM checkins ch JOIN citas c ON c.id = ch.cita_id
    WHERE c.cliente_id = cl.id AND ch.verificado = 1
  );
