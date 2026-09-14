-- Bitácora para el panel admin: dos tablas nuevas, insert-only (nadie borra
-- filas de aquí, a diferencia de usuarios_sesiones que sí se limpia sola).
--
-- 1) bitacora_cambios: cada alta/edición/baja que un vendedor hace sobre
--    clientes, citas, cotizaciones o muestras. "cambios" guarda el diff
--    campo por campo como JSON: {"campo": ["valor anterior", "valor nuevo"]}
--    -- puede venir NULL en altas simples donde el resumen ya lo dice todo.
--
-- 2) usuarios_accesos_historial: un registro permanente de cada intento de
--    login (correcto o no), separado de usuarios_sesiones (esa tabla es
--    "quién sigue conectado ahorita" y se borra al cerrar sesión o por
--    inactividad -- ver includes/helpers.php). usuario_id puede ser NULL si
--    el correo con el que intentaron ni siquiera existe.
SET search_path TO visitas;

CREATE TABLE IF NOT EXISTS bitacora_cambios (
  id BIGSERIAL PRIMARY KEY,
  vendedor_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  entidad VARCHAR(20) NOT NULL,   -- 'cliente' | 'cita' | 'cotizacion' | 'muestra'
  entidad_id INT,
  accion VARCHAR(10) NOT NULL CHECK (accion IN ('alta','edicion','baja')),
  resumen VARCHAR(255) NOT NULL,
  cambios JSONB,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_bitacora_cambios_vendedor ON bitacora_cambios(vendedor_id, creado_en DESC);
CREATE INDEX IF NOT EXISTS idx_bitacora_cambios_entidad  ON bitacora_cambios(entidad, entidad_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_cambios_fecha    ON bitacora_cambios(creado_en DESC);

CREATE TABLE IF NOT EXISTS usuarios_accesos_historial (
  id BIGSERIAL PRIMARY KEY,
  usuario_id INT REFERENCES usuarios(id) ON DELETE SET NULL,
  email_intentado VARCHAR(150) NOT NULL,
  resultado VARCHAR(20) NOT NULL, -- 'correcto' | 'fallido' | 'bloqueado_limite'
  motivo VARCHAR(255),
  ip VARCHAR(64),
  user_agent VARCHAR(255),
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_accesos_usuario   ON usuarios_accesos_historial(usuario_id, creado_en DESC);
CREATE INDEX IF NOT EXISTS idx_accesos_resultado ON usuarios_accesos_historial(resultado, creado_en DESC);
CREATE INDEX IF NOT EXISTS idx_accesos_fecha     ON usuarios_accesos_historial(creado_en DESC);
