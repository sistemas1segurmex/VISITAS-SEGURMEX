-- Nombre de la persona de contacto en el cliente (relevante sobre todo
-- cuando tipo_cliente = 'organizacion', donde el nombre del cliente es el
-- negocio y no necesariamente quien atiende al vendedor).

SET search_path TO visitas;

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS nombre_contacto VARCHAR(150);
