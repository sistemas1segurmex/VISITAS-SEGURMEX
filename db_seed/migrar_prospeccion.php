<?php
// Script de un solo uso: crea la tabla 'prospecciones' (jornada de
// prospección libre que el vendedor marca cuando no tiene citas pero sale a
// buscar clientes nuevos).
// Uso: abre en el navegador http://localhost/VISITAS-SEGURMEX-main/db_seed/migrar_prospeccion.php
// Es seguro correrlo más de una vez (no duplica nada).

require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

echo "<pre>";

$pdo->exec("
    CREATE TABLE IF NOT EXISTS prospecciones (
        id SERIAL PRIMARY KEY,
        vendedor_id INT NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
        fecha DATE NOT NULL,
        hora_inicio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        hora_fin TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        CONSTRAINT uq_prospeccion_vendedor_fecha UNIQUE (vendedor_id, fecha)
    )
");
echo "OK: tabla 'prospecciones' lista.\n";

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_prospecciones_fecha ON prospecciones(fecha)");
echo "OK: índice de fecha listo.\n";

echo "\nListo. Ya puedes usar el botón \"Salí a buscar clientes\" en el inicio del vendedor.\n";
echo "</pre>";
