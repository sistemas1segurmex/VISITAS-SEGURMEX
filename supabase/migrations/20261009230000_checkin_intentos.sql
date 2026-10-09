-- Intentos de check-in que no terminaron en un registro (9-oct-2026).
--
-- Caso que lo motivó: Norma Figueroa en Grupo corporativo papelero. El GPS
-- la ubica en el lugar desde las 2:14 p.m., pero la entrada quedó a las
-- 3:04 p.m. y no había forma de saber qué pasó en medio (GPS impreciso,
-- error de red, o simplemente no abrió la pantalla). Aquí queda cada
-- intento: lo que rechaza api/checkin.php y lo que nunca llega al
-- servidor (lo reporta vendedor/checkin.php a api/checkin_intento.php).
--
-- etapa:
--   abrio_pantalla     abrió la pantalla de check-in (una vez cada 5 min)
--   gps_impreciso      el celular no logró una ubicación aceptable
--   pregunta_ubicacion el servidor le preguntó si está en el cliente
--   rechazo_servidor   api/checkin.php contestó con error (motivo = mensaje)
--   error_envio        no hubo respuesta (sin señal / tiempo agotado)
--
-- El código funciona aunque esta tabla no exista todavía (los registros se
-- omiten y el panel no muestra la línea de intentos), pero conviene correr
-- esto ANTES de subir el código.
-- Idempotente.
SET search_path TO visitas;

CREATE TABLE IF NOT EXISTS checkin_intentos (
  id BIGSERIAL PRIMARY KEY,
  cita_id INTEGER NOT NULL REFERENCES citas(id) ON DELETE CASCADE,
  vendedor_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
  tipo VARCHAR(10) NOT NULL,
  etapa VARCHAR(30) NOT NULL,
  motivo TEXT,
  lat NUMERIC(10,7),
  lng NUMERIC(10,7),
  accuracy DOUBLE PRECISION,
  distancia_metros NUMERIC(10,2),
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_checkin_intentos_cita ON checkin_intentos(cita_id, tipo, creado_en);
