-- Bitácora: nueva acción 'descarga' (12-nov-2026) para registrar cuando la
-- responsable de muestras o un admin exporta a Excel la cartera de los
-- vendedores (api/exportar_cartera.php, entidad 'cartera'). Antes solo se
-- permitían 'alta', 'edicion' y 'baja'. Si no se corre, la exportación
-- funciona igual, solo no queda en la bitácora (registrarCambio no truena).
SET search_path TO visitas;

ALTER TABLE bitacora_cambios DROP CONSTRAINT IF EXISTS bitacora_cambios_accion_check;
ALTER TABLE bitacora_cambios
  ADD CONSTRAINT bitacora_cambios_accion_check CHECK (accion IN ('alta', 'edicion', 'baja', 'descarga'));
