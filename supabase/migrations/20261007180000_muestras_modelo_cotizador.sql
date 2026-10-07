-- «Solicitar muestra» ofrece el MISMO catálogo que la Nueva cotización
-- (07-oct-2026): modelos del cotizador anterior (legacy_cotizador_fb_modelos)
-- más estilos del ERP con precio. Los modelos del cotizador anterior no
-- tienen id de estilo del ERP, así que la solicitud guarda uno u otro.
--
-- Correr ANTES de subir el código (api/muestra_solicitar.php inserta
-- id_modelo_legacy). Idempotente. test_erp puede correrla (es dueño de la
-- tabla, la creó la migración 20261007120000_muestras_solo_aviso.sql).
ALTER TABLE visitas.muestras_solicitudes ALTER COLUMN id_estilo_erp DROP NOT NULL;
ALTER TABLE visitas.muestras_solicitudes ADD COLUMN IF NOT EXISTS id_modelo_legacy INTEGER;

ALTER TABLE visitas.muestras_solicitudes DROP CONSTRAINT IF EXISTS ck_muestra_estilo_o_modelo;
ALTER TABLE visitas.muestras_solicitudes ADD CONSTRAINT ck_muestra_estilo_o_modelo
  CHECK (id_estilo_erp IS NOT NULL OR id_modelo_legacy IS NOT NULL);
