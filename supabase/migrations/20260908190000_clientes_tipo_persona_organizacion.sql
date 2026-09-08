-- Distingue si un registro de "clientes" es una persona física o una
-- organización/negocio, para dar de alta con el formulario correcto y
-- mostrarlo claro en la cartera del vendedor.

SET search_path TO visitas;

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS tipo_cliente VARCHAR(20) NOT NULL DEFAULT 'organizacion';
