-- Foto de perfil de usuarios (vendedores/admins) — opcional, se sube al dar
-- de alta o editar desde admin/usuarios.php. Mismo patrón que checkins.foto_path
-- (guarda solo la ruta relativa dentro de uploads/, no la imagen en la BD).
-- Idempotente.
SET search_path TO visitas;

ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS foto_path VARCHAR(255);
