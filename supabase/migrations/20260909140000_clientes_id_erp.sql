-- Enlaza un cliente/prospecto de Visitas con su cliente correspondiente en
-- el ERP (esquema/base separada) -- se llena solo la primera vez que ese
-- cliente/prospecto se usa para solicitar una muestra (ver
-- api/muestra_solicitar.php + api/visitas_muestra.php del ERP, que crea el
-- registro mínimo allá si todavía no existe), y se reutiliza después para
-- no duplicarlo en el ERP cada vez.
-- Idempotente.
SET search_path TO visitas;

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS id_cliente_erp INTEGER;
