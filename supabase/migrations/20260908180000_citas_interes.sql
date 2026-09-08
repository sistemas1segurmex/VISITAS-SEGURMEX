-- El nivel de interés se mueve de "clientes" a "citas": el interés cambia
-- visita a visita, y el vendedor sí o sí concreta una cita por cada
-- interacción real, así que tiene más sentido calificarlo ahí (justo al
-- registrar la salida, con el contexto fresco) que como una etiqueta
-- estática en el cliente que nadie vuelve a actualizar.
--
-- No se borra clientes.interes (ya está en producción y no vale la pena el
-- riesgo de un DROP); simplemente deja de usarse desde el código.

SET search_path TO visitas;

ALTER TABLE citas ADD COLUMN IF NOT EXISTS interes VARCHAR(20);
