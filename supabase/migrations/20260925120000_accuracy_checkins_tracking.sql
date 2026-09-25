-- Precisión (metros) del GPS en check-ins y tracking. api/checkin.php y
-- api/tracking.php ya la guardan desde los commits 18c38b9 / f70e919, pero
-- las columnas se agregaron a mano en producción ("SQL aparte") y nunca
-- quedaron en una migración -- una base nueva (p.ej. la local) truena con
-- "column accuracy does not exist" y el vendedor lo veía como "Error de
-- conexión". En producción no hace nada (ya existen).
-- Idempotente.
SET search_path TO visitas;

ALTER TABLE checkins ADD COLUMN IF NOT EXISTS accuracy DOUBLE PRECISION;
ALTER TABLE tracking_ubicaciones ADD COLUMN IF NOT EXISTS accuracy DOUBLE PRECISION;
