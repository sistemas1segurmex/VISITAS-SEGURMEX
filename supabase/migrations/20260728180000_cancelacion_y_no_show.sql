-- Agrega soporte para cancelar citas pendientes y para registrar cuando el
-- vendedor sí llegó pero el cliente no se presentó.
-- Ejecutar una sola vez en: Supabase Dashboard > SQL Editor > pegar todo > Run.
-- (También se puede correr con db_seed/migrar_cancelacion.php desde la app).

SET search_path TO visitas;

ALTER TABLE citas ADD COLUMN IF NOT EXISTS motivo TEXT;

DO $$
DECLARE
  nombre_constraint text;
BEGIN
  SELECT con.conname INTO nombre_constraint
  FROM pg_constraint con
  JOIN pg_class rel ON rel.oid = con.conrelid
  JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
  WHERE nsp.nspname = 'visitas'
    AND rel.relname = 'citas'
    AND con.contype = 'c'
    AND pg_get_constraintdef(con.oid) LIKE '%estado%'
  LIMIT 1;

  IF nombre_constraint IS NOT NULL THEN
    EXECUTE format('ALTER TABLE citas DROP CONSTRAINT %I', nombre_constraint);
  END IF;

  ALTER TABLE citas ADD CONSTRAINT citas_estado_check
    CHECK (estado IN ('pendiente','en_curso','completada','no_realizada','cancelada'));
END $$;
