-- Sesiones persistentes (24-sep-2026, ver SESION_DURACION_SEG en
-- includes/helpers.php e iniciarSesionVisitas() en includes/auth.php).
-- La cookie cambia de PHPSESSID a VISITASSID, así que todas las sesiones
-- abiertas antes de este cambio quedan huérfanas (todos vuelven a entrar
-- una vez). Sus filas se borran aquí: con la ventana nueva de 7 días
-- seguirían contando contra el límite de 2 sesiones y bloquearían el
-- login. Correr JUSTO AL SUBIR el código nuevo.
-- Idempotente.
SET search_path TO visitas;

DELETE FROM usuarios_sesiones;
