-- Registro explícito de "jornada de prospección": cuando el vendedor no
-- tiene citas programadas pero sale a buscar clientes nuevos por su cuenta.
-- Es una acción consciente del vendedor (botón "Salí a buscar clientes"),
-- distinta del tracking GPS pasivo, y sirve como evidencia de actividad en
-- días sin citas (junto con los clientes nuevos que registre ese día).
-- Ejecutar una sola vez en: Supabase Dashboard > SQL Editor > pegar todo > Run.
-- (También se puede correr con db_seed/migrar_prospeccion.php desde la app).

SET search_path TO visitas;

CREATE TABLE IF NOT EXISTS prospecciones (
  id SERIAL PRIMARY KEY,
  vendedor_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  fecha DATE NOT NULL,
  hora_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  hora_fin TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT uq_prospeccion_vendedor_fecha UNIQUE (vendedor_id, fecha)
);

CREATE INDEX IF NOT EXISTS idx_prospecciones_fecha ON prospecciones(fecha);

-- Nuevo tipo de alerta para el dashboard del admin: 'sin_actividad'
-- (además de los ya existentes retraso, fuera_de_zona, sin_checkin).
-- No requiere cambios de esquema porque "tipo" ya es texto libre.
