-- "Prospección" deja de ser una jornada de un solo toque (una fila por
-- vendedor por día, sin ningún dato del recorrido) y pasa a ser una fila
-- POR PARADA: cada vez que el vendedor toca "Salí a buscar clientes" se
-- abre una parada con persona/empresa, dirección, foto+GPS de entrada, y se
-- cierra después con foto+GPS de salida + nivel de interés -- mismo rigor
-- que ya existe para una cita agendada (ver api/checkin.php), pero para
-- visitas espontáneas sin cita previa. Un vendedor puede registrar varias
-- paradas el mismo día.
--
-- No se valida "tipo"/"interes" con CHECK/ENUM a propósito -- mismo criterio
-- que clientes.etapa y citas.interes: la lista vive en el código (PHP/JS),
-- así se puede ajustar sin migración.
SET search_path TO visitas;

ALTER TABLE prospecciones DROP CONSTRAINT IF EXISTS uq_prospeccion_vendedor_fecha;

ALTER TABLE prospecciones
  ADD COLUMN IF NOT EXISTS tipo VARCHAR(20),
  ADD COLUMN IF NOT EXISTS nombre VARCHAR(150),
  ADD COLUMN IF NOT EXISTS direccion VARCHAR(255),
  ADD COLUMN IF NOT EXISTS lat_entrada NUMERIC(10,7),
  ADD COLUMN IF NOT EXISTS lng_entrada NUMERIC(10,7),
  ADD COLUMN IF NOT EXISTS foto_entrada_path VARCHAR(255),
  ADD COLUMN IF NOT EXISTS lat_salida NUMERIC(10,7),
  ADD COLUMN IF NOT EXISTS lng_salida NUMERIC(10,7),
  ADD COLUMN IF NOT EXISTS foto_salida_path VARCHAR(255),
  ADD COLUMN IF NOT EXISTS interes VARCHAR(20);

CREATE INDEX IF NOT EXISTS idx_prospecciones_vendedor_abierta
  ON prospecciones(vendedor_id, fecha) WHERE hora_fin IS NULL;
