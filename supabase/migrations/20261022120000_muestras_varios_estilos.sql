-- Solicitud de muestra con varios estilos y varias tallas (22-oct-2026).
--
-- Antes: una solicitud = un estilo, un color, una talla y N pares (todo en
-- muestras_solicitudes). Ahora, igual que el cotizador anterior
-- (cotizador.segurmex.com.mx), una solicitud puede llevar varios estilos y
-- cada estilo varias tallas:
--
--   muestras_solicitudes           cliente, contacto, entrega, motivo, fechas, estado (como hoy)
--   └─ muestras_solicitudes_estilos   estilo + color + tipo (idéntico/variante) + cambios
--      └─ muestras_solicitudes_tallas    talla + pares + nota
--
-- Las columnas de estilo que ya tenía muestras_solicitudes se quedan como
-- RESUMEN (para buscar en la bandeja y para lo que ya las lee):
--   estilo_nombre = primer estilo (+ "y N estilos más"), cantidad = total de
--   pares, tipo = 'variante' si algún estilo lo es, id_estilo_erp /
--   id_modelo_legacy = los del primer estilo. color/talla/cambios quedan
--   vacíos cuando hay más de un estilo o talla.
--
-- Tope (lo valida la app, includes/muestras.php): 6 pares por cliente por mes
-- calendario (hora de CDMX), sin contar las canceladas. Se quita el tope de 5
-- solicitudes al mes por vendedor.
--
-- Se puede correr más de una vez. Todo va con el esquema escrito (visitas.)
-- porque el editor SQL de Supabase no respeta SET search_path entre sentencias.

CREATE TABLE IF NOT EXISTS visitas.muestras_solicitudes_estilos (
  id               SERIAL PRIMARY KEY,
  solicitud_id     INTEGER NOT NULL REFERENCES visitas.muestras_solicitudes(id) ON DELETE CASCADE,
  orden            INTEGER NOT NULL DEFAULT 1,
  id_estilo_erp    INTEGER,
  id_modelo_legacy INTEGER,
  estilo_nombre    VARCHAR(200) NOT NULL,
  color            VARCHAR(40),
  tipo             VARCHAR(10) NOT NULL DEFAULT 'identico',
  cambios          JSONB NOT NULL DEFAULT '[]'::jsonb,
  CONSTRAINT ck_mse_tipo CHECK (tipo IN ('identico', 'variante')),
  CONSTRAINT ck_mse_estilo_o_modelo CHECK (id_estilo_erp IS NOT NULL OR id_modelo_legacy IS NOT NULL)
);
CREATE INDEX IF NOT EXISTS idx_mse_solicitud ON visitas.muestras_solicitudes_estilos (solicitud_id, orden);

CREATE TABLE IF NOT EXISTS visitas.muestras_solicitudes_tallas (
  id         SERIAL PRIMARY KEY,
  estilo_id  INTEGER NOT NULL REFERENCES visitas.muestras_solicitudes_estilos(id) ON DELETE CASCADE,
  orden      INTEGER NOT NULL DEFAULT 1,
  talla      VARCHAR(30),           -- NULL solo en las de antes que no la indicaron
  pares      INTEGER NOT NULL DEFAULT 1,
  nota       VARCHAR(500),
  CONSTRAINT ck_mst_pares CHECK (pares BETWEEN 1 AND 99),
  CONSTRAINT uq_mst_estilo_talla UNIQUE (estilo_id, talla)
);
CREATE INDEX IF NOT EXISTS idx_mst_estilo ON visitas.muestras_solicitudes_tallas (estilo_id, orden);

-- Para el tope por cliente por mes.
CREATE INDEX IF NOT EXISTS idx_muestras_sol_cliente ON visitas.muestras_solicitudes (cliente_id, created_at DESC);

-- Las solicitudes que ya existían pasan a un estilo con una talla.
INSERT INTO visitas.muestras_solicitudes_estilos
       (solicitud_id, orden, id_estilo_erp, id_modelo_legacy, estilo_nombre, color, tipo, cambios)
SELECT s.id, 1, s.id_estilo_erp, s.id_modelo_legacy, s.estilo_nombre, s.color, s.tipo, s.cambios
FROM visitas.muestras_solicitudes s
WHERE NOT EXISTS (SELECT 1 FROM visitas.muestras_solicitudes_estilos x WHERE x.solicitud_id = s.id);

INSERT INTO visitas.muestras_solicitudes_tallas (estilo_id, orden, talla, pares)
SELECT e.id, 1, NULLIF(TRIM(s.talla), ''), GREATEST(1, COALESCE(s.cantidad, 1))
FROM visitas.muestras_solicitudes_estilos e
JOIN visitas.muestras_solicitudes s ON s.id = e.solicitud_id
WHERE NOT EXISTS (SELECT 1 FROM visitas.muestras_solicitudes_tallas t WHERE t.estilo_id = e.id);

