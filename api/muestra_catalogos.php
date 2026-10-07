<?php
// Catálogos para el formulario de "Solicitar muestra" del vendedor -- ver
// vendedor/solicitar_muestra.php y api/muestra_solicitar.php.
//
// "Clientes" son los PROPIOS clientes/prospectos de este vendedor en
// Visitas (no el catálogo del ERP -- un vendedor externo debe poder pedir
// una muestra para ganarse a un prospecto que todavía ni siquiera es
// cliente formal allá). "Estilos" es el catálogo de la Nueva
// cotización -- eso no tiene equivalente de "prospecto".

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';
require_once __DIR__ . '/../includes/db_erp.php';

$u  = requireRole('vendedor');
$db = getDB();

$stmt = $db->prepare(
    "SELECT id, nombre, etapa, direccion FROM clientes WHERE vendedor_id = ? ORDER BY nombre"
);
$stmt->execute([$u['id']]);
$clientes = $stmt->fetchAll();

// Estilos: el mismo catálogo que la Nueva cotización (modelos del cotizador
// anterior + estilos del ERP con precio), leído directo del esquema "public"
// del ERP (ver includes/db_erp.php).
$estilos = [];
$errorEstilos = null;
try {
    $estilos = catalogoParaMuestraErp();
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_catalogos: ' . $e->getMessage());
    $errorEstilos = 'No se pudo cargar el catálogo de estilos. Intenta más tarde.';
}
$atributos = [];
try { $atributos = atributosSeguridadErp(); } catch (Throwable $e) { /* solo informativo */ }
$usadas = muestrasDelMesVendedor($db, (int)$u['id']);
jsonResponse([
    'ok' => true, 'clientes' => $clientes, 'estilos' => $estilos, 'error_estilos' => $errorEstilos,
    'atributos' => $atributos,
    'usadas_mes' => $usadas, 'tope_mes' => MUESTRAS_TOPE_MES,
]);
