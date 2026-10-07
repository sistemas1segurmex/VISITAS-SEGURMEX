<?php
// Catálogos para el formulario de "Solicitar muestra" del vendedor -- ver
// vendedor/solicitar_muestra.php y api/muestra_solicitar.php.
//
// "Clientes" son los PROPIOS clientes/prospectos de este vendedor en
// Visitas (no el catálogo del ERP -- un vendedor externo debe poder pedir
// una muestra para ganarse a un prospecto que todavía ni siquiera es
// cliente formal allá). "Estilos" sí es el catálogo real de
// productos del ERP -- eso no tiene equivalente de "prospecto".

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';
require_once __DIR__ . '/../includes/db_erp.php';

$u  = requireRole('vendedor');
$db = getDB();

$stmt = $db->prepare(
    "SELECT id, nombre, etapa FROM clientes WHERE vendedor_id = ? ORDER BY nombre"
);
$stmt->execute([$u['id']]);
$clientes = $stmt->fetchAll();

// Estilos: se leen directo del esquema "public" del ERP (misma base de
// Supabase, ver includes/db_erp.php) -- igual que el cotizador de Visitas.
// Antes pasaba por la API del ERP (api/visitas_catalogos.php) y necesitaba
// ERP_API_URL/ERP_API_SECRET en el .env; ya no hace falta.
$estilos = [];
$errorEstilos = null;
try {
    $estilos = estilosParaMuestraErp();
} catch (Throwable $e) {
    error_log('[VISITAS] muestra_catalogos: ' . $e->getMessage());
    $errorEstilos = 'No se pudo cargar el catálogo de estilos. Intenta más tarde.';
}
$usadas = muestrasDelMesVendedor($db, (int)$u['id']);
$responsables = responsablesMuestras($db);
jsonResponse([
    'ok' => true, 'clientes' => $clientes, 'estilos' => $estilos, 'error_estilos' => $errorEstilos,
    'usadas_mes' => $usadas, 'tope_mes' => MUESTRAS_TOPE_MES,
    'responsable' => $responsables ? $responsables[0]['nombre'] : null,
]);
