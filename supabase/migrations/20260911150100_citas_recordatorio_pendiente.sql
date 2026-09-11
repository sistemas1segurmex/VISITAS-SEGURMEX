-- Marca cuándo se le avisó al vendedor (push) que dejó una cita con entrada
-- registrada pero sin salida -- evita mandarle el mismo aviso cada vez que
-- corre el cron de recordatorios (ver api/cron_recordatorios.php).
ALTER TABLE "citas" ADD COLUMN IF NOT EXISTS "recordatorio_pendiente_enviado_en" timestamptz;
