<?php
// Exportar a Excel la cartera (clientes y prospectos) de los vendedores
// externos (12-nov-2026). Solo la responsable de muestras y los admin
// (Dirección/Sistemas); los vendedores no exportan su cartera.
//
//   GET ?vendedor_id=12  -> un vendedor, una hoja
//   GET ?todos=1         -> todos los vendedores, una hoja por vendedor
//
// Va todo lo que cada vendedor tiene asignado hoy (sin importar filtros de
// pantalla). Cada exportación queda en la Bitácora (accion 'descarga',
// entidad 'cartera'; migración 20261112120000_bitacora_descarga.sql).
// Solo consulta: no cambia ningún dato.

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/cartera_clientes.php';
require_once __DIR__ . '/../includes/xlsx_simple.php';

$u  = requireAnyRole(['muestras', 'admin']);
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['ok' => false, 'error' => 'Sin permiso para esta acción'], 403);
}
$todos      = !empty($_GET['todos']);
$vendedorId = (int)($_GET['vendedor_id'] ?? 0);
if (!$todos && $vendedorId <= 0) {
    jsonResponse(['ok' => false, 'error' => 'Falta el vendedor'], 400);
}

$vendedores = carteraVendedores($db, $todos ? null : $vendedorId);
if (!$vendedores) {
    jsonResponse(['ok' => false, 'error' => 'Vendedor no encontrado'], 404);
}
$filas = carteraClientesExportar($db, array_map(fn($v) => (int)$v['id'], $vendedores));
$porVendedor = [];
foreach ($filas as $f) $porVendedor[(int)$f['vendedor_id']][] = $f;

// ── Textos ──────────────────────────────────────────────────────────────
$zona = new DateTimeZone('America/Mexico_City');
$ahora = new DateTime('now', $zona);

/** Fecha 'AAAA-MM-DD' en hora de CDMX. Sin zona = se guardó en UTC (sesión UTC);
 *  $local = true para valores que ya están en hora local (citas.fecha_hora). */
function carteraFecha($valor, DateTimeZone $zona, bool $local = false): ?string {
    if ($valor === null || $valor === '') return null;
    try {
        $tieneZona = (bool)preg_match('/([zZ]|[+-]\d{2}(:?\d{2})?)$/', trim((string)$valor));
        $d = new DateTime((string)$valor, $tieneZona ? null : ($local ? $zona : new DateTimeZone('UTC')));
        $d->setTimezone($zona);
        return $d->format('Y-m-d');
    } catch (Throwable $e) {
        return null;
    }
}
function carteraPlural(int $n, string $palabra): string {
    return $n . ' ' . $palabra . ($n === 1 ? '' : 's');
}
$etapas = [
    'prospecto_agregado' => 'Prospecto', 'contacto_establecido' => 'Contacto establecido',
    'reunion_presentacion' => 'Reunión de presentación', 'propuesta_enviada' => 'Propuesta enviada',
    'convertido' => 'Cliente', 'perdido' => 'Perdido',
];
$intereses = ['bajo' => 'Bajo', 'medio' => 'Medio', 'interesado' => 'Interesado', 'muy_interesado' => 'Muy interesado'];

$columnas = [
    ['Nombre', 34, 'texto'], ['Tipo', 11, 'texto'], ['Etapa', 20, 'texto'], ['Motivo perdido', 26, 'texto'],
    ['Contacto', 24, 'texto'], ['Teléfono', 15, 'texto'], ['Calle y número', 32, 'texto'], ['Colonia', 22, 'texto'],
    ['CP', 8, 'texto'], ['Municipio', 18, 'texto'], ['Estado', 16, 'texto'], ['Ubicación (mapa)', 19, 'enlace'],
    ['Registrado el', 13, 'fecha'], ['Cliente desde', 13, 'fecha'],
    ['Visitas hechas', 10, 'numero'], ['Última visita', 13, 'fecha'], ['Próxima visita', 13, 'fecha'], ['Último interés', 14, 'texto'],
    ['Cotizaciones', 12, 'numero'], ['Última cotización', 13, 'fecha'],
    ['Muestras pedidas', 11, 'numero'], ['Última muestra', 13, 'fecha'],
];

$xlsx = new XlsxSimple();
$total = 0;
foreach ($vendedores as $v) {
    $lista = $porVendedor[(int)$v['id']] ?? [];
    $cuenta = ['clientes' => 0, 'prospectos' => 0, 'perdidos' => 0];
    $renglones = [];
    foreach ($lista as $c) {
        $etapa = $c['etapa'] ?: 'prospecto_agregado';
        $cuenta[$etapa === 'convertido' ? 'clientes' : ($etapa === 'perdido' ? 'perdidos' : 'prospectos')]++;
        $calle = trim((string)($c['calle_numero'] ?? '')) !== '' ? $c['calle_numero'] : ($c['direccion'] ?? '');
        $mapa = ($c['lat'] !== null && $c['lat'] !== '' && $c['lng'] !== null && $c['lng'] !== '')
            ? ['https://www.google.com/maps/search/?api=1&query=' . rawurlencode($c['lat'] . ',' . $c['lng']), 'Ver en Google Maps']
            : null;
        $renglones[] = [
            $c['nombre'],
            ($c['tipo_cliente'] ?? '') === 'persona' ? 'Persona' : 'Empresa',
            $etapas[$etapa] ?? $etapa,
            $etapa === 'perdido' ? ($c['etapa_perdido_motivo'] ?? '') : '',
            $c['nombre_contacto'] ?? '',
            $c['telefono'] ?? '',
            $calle,
            $c['colonia'] ?? '',
            $c['codigo_postal'] ?? '',
            $c['municipio'] ?? '',
            $c['estado'] ?? '',
            $mapa,
            carteraFecha($c['created_at'], $zona),
            carteraFecha($c['cliente_desde'], $zona),
            (int)$c['visitas_hechas'],
            carteraFecha($c['ultima_visita'], $zona, true),
            carteraFecha($c['proxima_visita'], $zona, true),
            $intereses[$c['ultimo_interes'] ?? ''] ?? ($c['ultimo_interes'] ?? ''),
            (int)$c['cotizaciones'],
            carteraFecha($c['ultima_cotizacion'], $zona),
            (int)$c['muestras'],
            carteraFecha($c['ultima_muestra'], $zona),
        ];
    }
    $n = count($lista);
    $total += $n;
    $detalle = $n
        ? carteraPlural($n, 'registro') . ' (' . carteraPlural($cuenta['clientes'], 'cliente') . ', '
          . carteraPlural($cuenta['prospectos'], 'prospecto') . ', ' . carteraPlural($cuenta['perdidos'], 'perdido') . ')'
        : 'sin registros';
    $xlsx->agregarHoja($v['nombre'], [
        'titulo'    => 'Cartera de ' . $v['nombre'] . (!(int)$v['activo'] ? ' (inactivo)' : ''),
        'subtitulo' => 'Exportado el ' . $ahora->format('d/m/Y H:i') . ' por ' . $u['nombre'] . ' · ' . $detalle,
        'columnas'  => $columnas,
        'filas'     => $renglones,
        'vacio'     => 'Sin clientes ni prospectos',
    ]);
}

// ── Bitácora (si falla, la descarga sigue: es auditoría) ─────────────────
$resumen = $todos
    ? 'Exportó la cartera de todos los vendedores (' . (count($vendedores) === 1 ? '1 vendedor' : count($vendedores) . ' vendedores') . ', ' . carteraPlural($total, 'registro') . ')'
    : 'Exportó la cartera de ' . $vendedores[0]['nombre'] . ' (' . carteraPlural($total, 'registro') . ')';
registrarCambio($db, (int)$u['id'], 'cartera', $todos ? null : (int)$vendedores[0]['id'], 'descarga', mb_substr($resumen, 0, 255));

$nombreArchivo = $todos
    ? 'Cartera_vendedores_' . $ahora->format('Y-m-d') . '.xlsx'
    : 'Cartera_' . preg_replace('/\s+/u', '_', trim($vendedores[0]['nombre'])) . '_' . $ahora->format('Y-m-d') . '.xlsx';
$xlsx->enviar($nombreArchivo);
