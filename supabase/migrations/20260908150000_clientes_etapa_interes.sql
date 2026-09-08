-- Fase 1 del embudo de ventas (prospecto -> cliente): cada registro de
-- "clientes" ahora lleva en qué etapa va y qué tan interesado está, para
-- poder darle seguimiento desde "Mis clientes" y desde el historial.
--
-- Etapas (en orden): prospecto_agregado -> contacto_establecido ->
-- reunion_presentacion -> propuesta_enviada -> convertido, con "perdido"
-- como salida alterna en cualquier punto del camino.
--
-- No se valida con un CHECK/ENUM de Postgres a propósito: la lista de
-- etapas vive en vendedor.js (ETAPAS_CLIENTE) y en api/cambiar_etapa.php,
-- igual que ya se hace con citas.estado -- así se puede agregar una etapa
-- nueva sin migración.

SET search_path TO visitas;

ALTER TABLE clientes
  ADD COLUMN IF NOT EXISTS etapa VARCHAR(30) NOT NULL DEFAULT 'prospecto_agregado',
  ADD COLUMN IF NOT EXISTS interes VARCHAR(20),
  ADD COLUMN IF NOT EXISTS etapa_actualizada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS etapa_perdido_motivo VARCHAR(255);

-- Los clientes que ya existían antes de este cambio no son prospectos
-- nuevos -- se dan por buenos como "convertido" para no mostrarlos de
-- golpe como leads sin trabajar en el embudo.
UPDATE clientes SET etapa = 'convertido', etapa_actualizada_en = created_at WHERE etapa = 'prospecto_agregado';

CREATE INDEX IF NOT EXISTS idx_clientes_etapa ON clientes(etapa);
