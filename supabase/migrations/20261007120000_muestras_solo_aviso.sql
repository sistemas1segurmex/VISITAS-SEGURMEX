-- Solicitudes de muestra de los vendedores externos -- etapa "solo aviso"
-- (07-oct-2026).
--
-- Hasta ahora "Solicitar muestra" mandaba la solicitud al ERP (flujo
-- completo: Dirección autoriza -> Diseño -> Compras -> Producción ->
-- Almacén). Ese flujo todavía no se va a usar para los externos: por ahora
-- la solicitud se queda AQUÍ, en Visitas, y solo se le avisa a la
-- responsable de muestras (una vendedora interna, rol 'muestras'), que la
-- surte por fuera y va marcando el avance:
--   enviada -> en_preparacion -> embarcada   (o cancelada, con motivo)
-- Cada cambio le avisa al vendedor por correo y con un aviso dentro del
-- sistema (tabla avisos, campanita). El admin solo consulta.
--
-- Correr ANTES de subir el código (api/muestra_*.php, muestras/*.php y
-- api/avisos.php leen estas tablas). Idempotente.
SET search_path TO visitas;

-- 1) Rol nuevo 'muestras'. El CHECK original venía en línea en el CREATE
-- TABLE (Postgres lo nombra usuarios_rol_check); se busca por definición
-- para no depender del nombre.
DO $$
DECLARE c RECORD;
BEGIN
  FOR c IN
    SELECT con.conname
    FROM pg_constraint con
    JOIN pg_class rel ON rel.oid = con.conrelid
    JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
    WHERE nsp.nspname = 'visitas' AND rel.relname = 'usuarios'
      AND con.contype = 'c' AND pg_get_constraintdef(con.oid) ILIKE '%rol%'
  LOOP
    EXECUTE format('ALTER TABLE visitas.usuarios DROP CONSTRAINT %I', c.conname);
  END LOOP;
END $$;
ALTER TABLE usuarios ADD CONSTRAINT usuarios_rol_check
  CHECK (rol IN ('vendedor', 'admin', 'muestras'));

-- 2) Solicitudes. cliente_nombre y estilo_nombre se guardan como foto del
-- momento (el estilo viene del catálogo del ERP y el cliente puede
-- renombrarse después) para que la solicitud siempre se lea igual.
-- cambios: arreglo JSON de {categoria, categoria_otro?, descripcion} cuando
-- tipo = 'variante'.
CREATE TABLE IF NOT EXISTS muestras_solicitudes (
  id SERIAL PRIMARY KEY,
  folio VARCHAR(20) UNIQUE,
  vendedor_id INTEGER NOT NULL REFERENCES usuarios(id),
  cliente_id INTEGER REFERENCES clientes(id) ON DELETE SET NULL,
  cliente_nombre VARCHAR(200) NOT NULL,
  id_estilo_erp INTEGER NOT NULL,
  estilo_nombre VARCHAR(200) NOT NULL,
  talla VARCHAR(30),
  fecha_promesa DATE,
  tipo VARCHAR(10) NOT NULL DEFAULT 'identico' CHECK (tipo IN ('identico', 'variante')),
  cambios JSONB NOT NULL DEFAULT '[]'::jsonb,
  destino_direccion TEXT NOT NULL,
  estado VARCHAR(20) NOT NULL DEFAULT 'enviada'
    CHECK (estado IN ('enviada', 'en_preparacion', 'embarcada', 'cancelada')),
  envio_modo VARCHAR(20) CHECK (envio_modo IN ('paqueteria', 'en_persona')),
  paqueteria VARCHAR(80),
  guia VARCHAR(80),
  motivo_cancelacion VARCHAR(500),
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizada_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT ck_muestra_embarcada CHECK (estado <> 'embarcada' OR envio_modo IS NOT NULL),
  CONSTRAINT ck_muestra_cancelada CHECK (estado <> 'cancelada' OR motivo_cancelacion IS NOT NULL)
);
CREATE INDEX IF NOT EXISTS idx_muestras_sol_vendedor ON muestras_solicitudes (vendedor_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_muestras_sol_estado   ON muestras_solicitudes (estado, created_at DESC);

-- 3) Historial: una fila por cada cambio de estado (la alta incluida), con
-- quién lo hizo -- se muestra en el detalle de la solicitud.
CREATE TABLE IF NOT EXISTS muestras_solicitudes_historial (
  id SERIAL PRIMARY KEY,
  solicitud_id INTEGER NOT NULL REFERENCES muestras_solicitudes(id) ON DELETE CASCADE,
  estado VARCHAR(20) NOT NULL,
  usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
  nota VARCHAR(500),
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_muestras_hist_solicitud ON muestras_solicitudes_historial (solicitud_id, creado_en);

-- 4) Avisos dentro del sistema (campanita), por usuario. Genérica a
-- propósito: hoy solo la usan las muestras, pero cualquier otro módulo
-- puede insertar aquí. leido_en NULL = sin leer.
CREATE TABLE IF NOT EXISTS avisos (
  id SERIAL PRIMARY KEY,
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  tipo VARCHAR(40) NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  mensaje VARCHAR(500) NOT NULL,
  enlace VARCHAR(255),
  solicitud_muestra_id INTEGER REFERENCES muestras_solicitudes(id) ON DELETE CASCADE,
  leido_en TIMESTAMP,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_avisos_usuario ON avisos (usuario_id, leido_en, creado_en DESC);

RESET search_path;
