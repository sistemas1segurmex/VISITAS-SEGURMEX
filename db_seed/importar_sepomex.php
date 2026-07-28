<?php
// Descarga el catálogo nacional de colonias/CP (SEPOMEX, vía el CSV público
// que mantiene IcaliaLabs en GitHub) y lo carga a la tabla sepomex_colonias.
// Corre primero db_seed/migrar_sepomex.php si aún no lo has hecho.
//
// Uso: http://localhost/VISITAS-SEGURMEX-main/db_seed/importar_sepomex.php
// Agrega ?forzar=1 al final de la URL para reemplazar los datos si ya
// habías importado antes (por ejemplo, para actualizar el catálogo).
//
// Tarda uno o dos minutos porque son ~145,000 registros. No cierres la
// pestaña mientras corre.

set_time_limit(0);
require_once __DIR__ . '/../includes/db.php';

echo "<pre>";
echo "Iniciando importación del catálogo SEPOMEX...\n\n";

$pdo = getDB();

$yaTieneDatos = (int)$pdo->query('SELECT COUNT(*) FROM sepomex_colonias')->fetchColumn();
$forzar = isset($_GET['forzar']) && $_GET['forzar'] == '1';

if ($yaTieneDatos > 0 && !$forzar) {
    echo "El catálogo ya tiene $yaTieneDatos registros. No se hizo nada.\n";
    echo "Si quieres reemplazarlo, entra a esta misma URL agregando ?forzar=1\n";
    echo "</pre>";
    exit;
}

// Varias fuentes de respaldo, por si una falla o cambia de lugar.
// Nota: el repo movió el archivo de lib/support/sepomex_db.csv a lib/sepomex_db.csv
// (y ahora es un archivo sin encabezado, delimitado por "|").
$urls = [
    'https://raw.githubusercontent.com/IcaliaLabs/sepomex/main/lib/sepomex_db.csv',
    'https://cdn.jsdelivr.net/gh/IcaliaLabs/sepomex@main/lib/sepomex_db.csv',
    'https://raw.githubusercontent.com/IcaliaLabs/sepomex/master/lib/sepomex_db.csv',
    'https://cdn.jsdelivr.net/gh/IcaliaLabs/sepomex@master/lib/sepomex_db.csv',
];

$csv = null;
$urlUsada = null;
foreach ($urls as $url) {
    echo "Probando: $url ... ";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (VisitasSegurmex/1.0)',
    ]);
    $body = curl_exec($ch);
    $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body !== false && $codigo === 200 && strlen($body) > 100000) {
        echo "OK (" . number_format(strlen($body)) . " bytes)\n";
        $csv = $body;
        $urlUsada = $url;
        break;
    }
    echo "falló (código $codigo" . ($error ? ", $error" : "") . ")\n";
}

if ($csv === null) {
    echo "\nNo se pudo descargar el catálogo desde ninguna fuente. Revisa tu conexión\n";
    echo "a internet o avísale a sistemas para conseguir el archivo manualmente.\n";
    echo "</pre>";
    exit;
}

// Detecta y normaliza a UTF-8 por si acaso.
if (!mb_check_encoding($csv, 'UTF-8')) {
    $csv = mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1');
}

// El archivo actual NO trae encabezado: es texto plano delimitado por "|",
// con 15 columnas fijas en este orden (ver ConvertXmlToCsv del repo fuente):
//   0 d_codigo, 1 d_asenta, 2 d_tipo_asenta, 3 d_mnpio, 4 d_estado,
//   5 d_ciudad, 6 d_cp, 7 c_estado, 8 c_oficina, 9 c_cp, 10 c_tipo_asenta,
//   11 c_mnpio, 12 id_asenta_cpcons, 13 d_zona, 14 c_cve_ciudad
$lineas = preg_split('/\r\n|\n|\r/', $csv);

echo "\nFuente usada: $urlUsada\n";
echo "Procesando " . number_format(count($lineas)) . " líneas...\n";

$pdo->exec('TRUNCATE TABLE sepomex_colonias RESTART IDENTITY');

$insertSql = 'INSERT INTO sepomex_colonias (cp, asentamiento, tipo_asentamiento, municipio, estado, ciudad, zona) VALUES ';
$lote = [];
$total = 0;
$pdo->beginTransaction();

foreach ($lineas as $linea) {
    $linea = trim($linea);
    if ($linea === '') continue;
    $campos = explode('|', $linea);
    if (count($campos) < 14) continue;

    $cp = $campos[0] ?? '';
    $asentamiento = $campos[1] ?? '';
    $tipoAsentamiento = $campos[2] ?? '';
    $municipio = $campos[3] ?? '';
    $estado = $campos[4] ?? '';
    $ciudad = $campos[5] ?? '';
    $zona = $campos[13] ?? '';

    if ($cp === '' || $asentamiento === '' || $municipio === '' || $estado === '') continue;

    $lote[] = [$cp, $asentamiento, $tipoAsentamiento, $municipio, $estado, $ciudad ?: null, $zona ?: null];

    if (count($lote) >= 500) {
        insertarLote($pdo, $insertSql, $lote);
        $total += count($lote);
        $lote = [];
        echo "  ... $total registros\n";
        flush();
    }
}
if (!empty($lote)) {
    insertarLote($pdo, $insertSql, $lote);
    $total += count($lote);
}

$pdo->commit();

echo "\nListo. Se importaron " . number_format($total) . " registros al catálogo.\n";
echo "</pre>";

function insertarLote(PDO $pdo, string $sqlBase, array $lote): void {
    $placeholders = [];
    $valores = [];
    foreach ($lote as $fila) {
        $placeholders[] = '(?,?,?,?,?,?,?)';
        foreach ($fila as $v) $valores[] = $v;
    }
    $stmt = $pdo->prepare($sqlBase . implode(',', $placeholders));
    $stmt->execute($valores);
}
