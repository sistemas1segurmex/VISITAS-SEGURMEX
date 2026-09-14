-- "Conectado" para la tarjeta de un usuario en admin/usuarios.php: antes se
-- basaba en usuarios_sesiones.ultima_actividad, pero esa tabla se limpia sola
-- pasados SESION_VENTANA_INACTIVIDAD_MIN (20 min, ver includes/helpers.php),
-- así que un vendedor con la app en segundo plano (pantalla apagada, sin
-- pings de GPS) se veía "desconectado" aunque nunca cerró sesión.
-- Este campo vive aparte, en el propio usuario (no se borra por inactividad,
-- solo se limpia al cerrar sesión explícitamente), para reflejar "sigue con
-- sesión abierta" con una ventana mucho más tolerante.
-- Idempotente.
SET search_path TO visitas;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ultima_actividad_en TIMESTAMP;
