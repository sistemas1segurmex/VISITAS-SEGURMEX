<?php
// Script de un solo uso: crea la tabla del catálogo SEPOMEX y las columnas
// de dirección estructurada en clientes.
// Uso: http://localhost/VISITAS-SEGURMEX-main/db_seed/migrar_sepomex.php
// Después de correr este, corre db_seed/importar_sepomex.php para llenar
// el catálogo con los datos reales.

require_once __DIR__ . '/../includes/db.php';

$pdo = getDB();
echo "<pre>";

$pdo->exec("
    CREATE TABLE IF NOT EXISTS sepomex_colonias (
      id SERIAL PRIMARY KEY,
      cp VARCHAR(5) NOT NULL,
      asentamiento VARCHAR(150) NOT NULL,
      tipo_asentamiento VARCHAR(50),
      municipio VARCHAR(150) NOT NULL,
      estado VARCHAR(100) NOT NULL,
      ciudad VARCHAR(150),
      zona VARCHAR(20)
    )
");
echo "OK: tabla sepomex_colonias lista.\n";

$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sepomex_cp ON sepomex_colonias (cp)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sepomex_estado ON sepomex_colonias (estado)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sepomex_estado_municipio ON sepomex_colonias (estado, municipio)");
$pdo->exec("CREATE INDEX IF NOT EXISTS idx_sepomex_asentamiento ON sepomex_colonias (lower(asentamiento))");
echo "OK: índices listos.\n";

$pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS calle_numero VARCHAR(200)");
$pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS colonia VARCHAR(150)");
$pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS municipio VARCHAR(150)");
$pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS estado VARCHAR(100)");
$pdo->exec("ALTER TABLE clientes ADD COLUMN IF NOT EXISTS cp VARCHAR(10)");
echo "OK: columnas de dirección estructurada agregadas a clientes.\n";

echo "\nListo. Ahora corre db_seed/importar_sepomex.php para llenar el catálogo.\n";
echo "</pre>";
