-- Con qué se dio de alta cada registro en la app: "Prospecto nuevo"
-- (prospecto_agregado) o "Cliente ya ganado" (convertido). Hasta ahora solo
-- quedaba en la bitácora ("Etapa inicial"), y Métricas contaba todo junto como
-- "Clientes nuevos". Con esta columna se separan:
--   Prospectos nuevos             -> etapa_inicial = 'prospecto_agregado'
--   Clientes existentes capturados -> etapa_inicial = 'convertido'
--   Prospectos ganados            -> pasaron a 'convertido' sin haber entrado así
-- NULL = no se sabe (no hay registro en bitácora y no se pudo deducir).

SET search_path TO visitas;

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS etapa_inicial VARCHAR(30);

-- 1) Lo que dice la bitácora al darlo de alta.
UPDATE clientes cl
SET etapa_inicial = CASE b.cambios->'Etapa inicial'->>1
                      WHEN 'Prospecto'  THEN 'prospecto_agregado'
                      WHEN 'Convertido' THEN 'convertido'
                    END
FROM bitacora_cambios b
WHERE b.entidad = 'cliente' AND b.entidad_id = cl.id AND b.accion = 'alta'
  AND b.cambios->'Etapa inicial'->>1 IN ('Prospecto', 'Convertido')
  AND cl.etapa_inicial IS NULL;

-- 2) Sin bitácora: si sigue en "Prospecto" nunca se movió, entró como prospecto.
UPDATE clientes SET etapa_inicial = 'prospecto_agregado'
WHERE etapa_inicial IS NULL AND etapa = 'prospecto_agregado';

-- 3) Sin bitácora: "Convertido" con la etapa puesta al mismo tiempo que el alta
--    (los que ya existían antes del embudo, ver 20260908150000, o capturados
--    como "Cliente ya ganado").
UPDATE clientes SET etapa_inicial = 'convertido'
WHERE etapa_inicial IS NULL AND etapa = 'convertido'
  AND etapa_actualizada_en IS NOT NULL
  AND ABS(EXTRACT(EPOCH FROM (etapa_actualizada_en - created_at))) < 60;
