<?php
// Script de un solo uso: agrega el estado 'cancelada' y la columna 'motivo'
// a la tabla citas (necesario para poder cancelar citas pendientes y para
// registrar cuando el cliente no se presenta).
// Uso: abre en el navegador http://localhost/VISITAS-SEGURMEX-main/db_seed/migrar_cancelacion.php
// Es seguro correrlo más de una vez (no duplica nada).

require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();

echo "<pre>";

$pdo->exec("ALTER TABLE citas ADD COLUMN IF NOT EXISTS motivo TEXT");
echo "OK: columna 'motivo' lista.\n";

$stmt = $pdo->query("
    SELECT con.conname AS nombre
    FROM pg_constraint con
    JOIN pg_class rel ON rel.oid = con.conrelid
    JOIN pg_namespace nsp ON nsp.oid = rel.relnamespace
    WHERE nsp.nspname = 'visitas'
      AND rel.relname = 'citas'
      AND con.contype = 'c'
      AND pg_get_constraintdef(con.oid) LIKE '%estado%'
");
$fila = $stmt->fetch();

if ($fila && !empty($fila['nombre'])) {
    $pdo->exec('ALTER TABLE citas DROP CONSTRAINT "' . $fila['nombre'] . '"');
    echo "OK: restricción anterior de 'estado' eliminada (" . $fila['nombre'] . ").\n";
}

$pdo->exec("
    ALTER TABLE citas ADD CONSTRAINT citas_estado_check
    CHECK (estado IN ('pendiente','en_curso','completada','no_realizada','cancelada'))
");
echo "OK: ahora 'estado' acepta también 'cancelada'.\n";

echo "\nListo. Ya puedes cancelar citas pendientes y registrar 'cliente no llegó'.\n";
echo "</pre>";
