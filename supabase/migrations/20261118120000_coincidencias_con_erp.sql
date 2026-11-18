-- Coincidencias también contra el ERP (18-nov-2026).
--
-- Hasta ahora un cliente nuevo de Visitas solo se comparaba contra los
-- clientes de los DEMÁS vendedores externos (visitas.clientes). Desde hoy
-- también contra lo de las vendedoras INTERNAS en el ERP: clientes del ERP
-- (public.clientes) y prospectos del ERP (public.cotizador_prospectos) que no
-- vienen de Visitas. Esa coincidencia se decide aquí mismo, en la bandeja de
-- Coincidencias de la responsable de muestras (lo que registran las internas
-- se decide en el ERP, Ventas › Coincidencias).
--
-- existente_tipo: 'visitas' (como hasta hoy, cliente_existente_id) |
--                 'erp_cliente' | 'erp_prospecto' (existente_erp_id).
-- Con un registro del ERP no hay cliente ni vendedor de Visitas: esas dos
-- columnas quedan NULL y el nombre del vendedor se guarda aparte.
--
-- Correr ANTES de subir el código. Idempotente.
SET search_path TO visitas;

ALTER TABLE visitas.coincidencias_clientes ALTER COLUMN cliente_existente_id DROP NOT NULL;
ALTER TABLE visitas.coincidencias_clientes ALTER COLUMN vendedor_existente_id DROP NOT NULL;
ALTER TABLE visitas.coincidencias_clientes ADD COLUMN IF NOT EXISTS existente_tipo VARCHAR(20) NOT NULL DEFAULT 'visitas';
ALTER TABLE visitas.coincidencias_clientes ADD COLUMN IF NOT EXISTS existente_erp_id INTEGER;
ALTER TABLE visitas.coincidencias_clientes ADD COLUMN IF NOT EXISTS vendedor_existente_nombre VARCHAR(150);
CREATE INDEX IF NOT EXISTS idx_coincidencias_existente_erp
  ON visitas.coincidencias_clientes (existente_tipo, existente_erp_id) WHERE existente_erp_id IS NOT NULL;

RESET search_path;
