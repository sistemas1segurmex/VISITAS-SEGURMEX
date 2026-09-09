-- Límite de sesiones concurrentes por usuario (máx. 2 activas a la vez).
-- Cada login exitoso registra una fila aquí; login.php cuenta las que
-- siguen "activas" (última_actividad reciente) antes de dejar entrar a un
-- tercer dispositivo. includes/auth.php refresca ultima_actividad en cada
-- request autenticado, y logout.php borra la fila al cerrar sesión --
-- las que nadie cierra (se cerró el navegador sin dar "salir") simplemente
-- dejan de contar solas cuando pasa la ventana de inactividad.
-- Idempotente.
SET search_path TO visitas;

CREATE TABLE IF NOT EXISTS usuarios_sesiones (
  id SERIAL PRIMARY KEY,
  usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  session_id VARCHAR(128) NOT NULL UNIQUE,
  ip VARCHAR(64),
  user_agent VARCHAR(255),
  iniciada_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ultima_actividad TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_usuarios_sesiones_usuario ON usuarios_sesiones(usuario_id, ultima_actividad);
