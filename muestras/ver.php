<?php
// Detalle de una solicitud de muestra. La responsable de muestras (rol
// 'muestras') ve aquí los botones para marcar el avance; el admin la ve
// igual pero solo para consultar. Ver includes/muestras.php.
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/muestras.php';
$u = requireAnyRole(['muestras', 'admin']);
$puedeCambiar = $u['rol'] === 'muestras';

$db = getDB();
$s = cargarSolicitudMuestra($db, (int)($_GET['id'] ?? 0));
if (!$s) {
    http_response_code(404);
    die('No se encontró la solicitud. <a href="index.php">Volver a la bandeja</a>');
}
$historial = historialSolicitudMuestra($db, (int)$s['id']);
$siguientes = MUESTRA_TRANSICIONES[$s['estado']] ?? [];
$conAcciones = $puedeCambiar && $siguientes;
$abierta = in_array($s['estado'], ['enviada', 'en_preparacion'], true);

// Al abrirla, sus avisos de "nueva solicitud" quedan como leídos.
if ($puedeCambiar) {
    $db->prepare('UPDATE avisos SET leido_en = NOW() WHERE usuario_id = ? AND solicitud_muestra_id = ? AND leido_en IS NULL')
       ->execute([(int)$u['id'], (int)$s['id']]);
}

function e($v): string { return htmlspecialchars((string)$v); }

// Foto, línea y atributo (PP+D...) del estilo: del mismo catálogo del ERP que
// usa el PDF (datosCatalogoMuestraPdf). Si el ERP no responde, sale sin foto.
$cat = ['foto' => '', 'atributo' => '', 'grupo' => ''];
$itemCat = !empty($s['id_estilo_erp']) ? 'e:' . (int)$s['id_estilo_erp']
         : (!empty($s['id_modelo_legacy']) ? 'm:' . (int)$s['id_modelo_legacy'] : null);
if ($itemCat) {
    try {
        require_once __DIR__ . '/../includes/db_erp.php';
        $ec = catalogoParaMuestraErp($itemCat);
        if ($ec) {
            $cat['foto']     = basename((string)($ec['foto'] ?? ''));
            $cat['atributo'] = (string)($ec['atributo'] ?? '');
            $cat['grupo']    = ($ec['grupo'] ?? '') === 'Estilos del ERP' ? '' : (string)($ec['grupo'] ?? '');
        }
    } catch (Throwable $ex) {
        error_log('[VISITAS] Detalle muestra (catálogo): ' . $ex->getMessage());
    }
}
// "5006 — Elite Safety — Piel..." -> clave grande y el resto como descripción.
$partesEstilo = explode(' — ', (string)$s['estilo_nombre'], 2);
$clave = $partesEstilo[0];
$descEstilo = $partesEstilo[1] ?? '';

// Fechas en hora de México (la base guarda los timestamps en UTC).
$tzMx = new DateTimeZone('America/Mexico_City');
$hoyMx = new DateTime('today', $tzMx);
const MD_MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
function mdFechaCorta(DateTime $d, bool $anio = false): string {
    return $d->format('j') . ' ' . MD_MESES[(int)$d->format('n') - 1] . ($anio ? ' ' . $d->format('Y') : '');
}
function mdDesdeUtc(?string $v, DateTimeZone $tz): ?DateTime {
    if (!$v) return null;
    try { return (new DateTime($v, new DateTimeZone('UTC')))->setTimezone($tz); } catch (Throwable $e) { return null; }
}
function mdDia(?string $v, DateTimeZone $tz): ?DateTime {
    if (!$v) return null;
    try { return new DateTime(substr($v, 0, 10), $tz); } catch (Throwable $e) { return null; }
}
$creada = mdDesdeUtc($s['created_at'] ?? null, $tzMx);
$cerrada = mdDesdeUtc($s['actualizada_en'] ?? null, $tzMx);
$promesa = mdDia($s['fecha_promesa'] ?? null, $tzMx);

// Avisos del encabezado: mismas reglas que la bandeja (muestras/index.php).
$chips = [];
if ($abierta && $creada) {
    $dias = (int)floor((time() - $creada->getTimestamp()) / 86400);
    $chips[] = [$dias >= 3 ? 'rojo' : ($dias >= 1 ? 'ambar' : 'verde'), 'bi-hourglass-split',
                $dias === 0 ? 'Pedida hoy' : 'Esperando ' . ($dias === 1 ? '1 día' : $dias . ' días')];
}
if ($promesa) {
    $dp = (int)$hoyMx->diff($promesa)->format('%r%a');
    if ($abierta && $dp < 0)       $chips[] = ['rojo', 'bi-alarm', 'Promesa vencida · ' . mdFechaCorta($promesa)];
    elseif ($abierta && $dp <= 2)  $chips[] = ['ambar', 'bi-alarm', 'Promesa ' . ($dp === 0 ? 'hoy' : ($dp === 1 ? 'mañana' : 'en ' . $dp . ' días')) . ' · ' . mdFechaCorta($promesa)];
    elseif ($abierta)              $chips[] = ['', 'bi-calendar-event', 'Promesa ' . mdFechaCorta($promesa)];
}
if ($s['estado'] === 'embarcada' && $cerrada) $chips[] = ['verde', 'bi-check2-circle', 'Embarcada el ' . mdFechaCorta($cerrada)];
if ($s['estado'] === 'cancelada' && $cerrada) $chips[] = ['', 'bi-x-circle', 'Cancelada el ' . mdFechaCorta($cerrada)];
$chips[] = ['', $s['tipo'] === 'variante' ? 'bi-shuffle' : 'bi-check2-square', $s['tipo'] === 'variante' ? 'Variante' : 'Idéntico al estilo'];

$PILL = [
    'enviada'        => ['nueva', 'bi-inbox-fill', 'Nueva · sin atender'],
    'en_preparacion' => ['prep', 'bi-gear-fill', 'En preparación'],
    'embarcada'      => ['emb', 'bi-truck', 'Embarcada'],
    'cancelada'      => ['can', 'bi-x-circle-fill', 'Cancelada'],
][$s['estado']] ?? ['nueva', 'bi-inbox-fill', etiquetaEstadoMuestra($s['estado'])];

// Barra de avance: Enviada -> En preparación -> Embarcada (o Cancelada).
$pasoPorPreparacion = false;
foreach ($historial as $h) if ($h['estado'] === 'en_preparacion') $pasoPorPreparacion = true;
$detallePrep = null;
if (($s['preparacion'] ?? '') === 'pt') $detallePrep = 'En Producto Terminado';
elseif (($s['preparacion'] ?? '') === 'por_programar') {
    $fe = mdDia($s['fecha_estimada_pt'] ?? null, $tzMx);
    $detallePrep = 'Por programar' . ($fe ? ' · est. ' . mdFechaCorta($fe) : '');
}
$pasoPrep = '';
if ($s['estado'] === 'en_preparacion') $pasoPrep = 'actual';
elseif (in_array($s['estado'], ['embarcada', 'cancelada'], true)) $pasoPrep = $pasoPorPreparacion ? 'hecho' : 'omitido';

// Muestra
$COLOR_MUESTRA = ['NEGRO' => '#1F1F1F', 'CAFE' => '#6B4423', 'CHOCOLATE' => '#4A2C1A', 'MIEL' => '#C68E3F', 'CAMEL' => '#B5813F',
                  'GRIS' => '#8A8F98', 'AZUL' => '#1E3A8A', 'MARINO' => '#1E2A4A', 'ROJO' => '#B91C1C', 'BLANCO' => '#FFFFFF',
                  'AMARILLO' => '#E8A400', 'VERDE' => '#166534', 'BEIGE' => '#D8C3A5', 'ARENA' => '#D8C3A5', 'TAN' => '#C19A6B'];
$swatch = null;
if (!empty($s['color'])) {
    $colorPlano = strtr(mb_strtoupper((string)$s['color']), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
    foreach ($COLOR_MUESTRA as $nombre => $hex) if (strpos($colorPlano, $nombre) !== false) { $swatch = $hex; break; }
}
$ICONO_CAMBIO = ['casco' => 'bi-shield-shaded', 'suela' => 'bi-layers', 'piel' => 'bi-palette', 'forro' => 'bi-columns-gap'];

// Contactos
function mdIniciales(string $n): string {
    $p = preg_split('/\s+/', trim($n)) ?: [];
    $ini = '';
    foreach (array_slice($p, 0, 2) as $x) $ini .= mb_strtoupper(mb_substr($x, 0, 1));
    return $ini ?: '?';
}
function mdWhats(string $tel): string { return 'https://wa.me/' . (strlen($tel) === 10 ? '52' . $tel : $tel); }
$telLimpio = preg_replace('/\D/', '', (string)$s['vendedor_telefono']);
$telCliente = preg_replace('/\D/', '', (string)($s['cliente_telefono'] ?? ''));
$correoAsunto = 'Muestra ' . $s['folio'];
$correoTexto  = 'Hola ' . $s['vendedor_nombre'] . ', te escribo sobre tu solicitud de muestra ' . $s['folio']
              . ' (' . $s['estilo_nombre'] . (!empty($s['color']) ? ', ' . $s['color'] : '') . ') para ' . $s['cliente_nombre'] . ".\n\n";

// Historial
$TXT_HISTORIAL = [
    'enviada'        => 'Solicitud enviada',
    'en_preparacion' => 'En preparación',
    'embarcada'      => 'Embarcada',
    'cancelada'      => 'Cancelada',
];
$ICONO_HISTORIAL = [
    'enviada'        => ['', 'bi-send-fill'],
    'en_preparacion' => ['p', 'bi-gear-fill'],
    'embarcada'      => ['e', 'bi-truck'],
    'cancelada'      => ['c', 'bi-x-lg'],
];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($s['folio']) ?> — Solicitud de muestra</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<link rel="stylesheet" href="../assets/css/muestras.css<?= assetVer(__DIR__ . '/../assets/css/muestras.css') ?>">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back" aria-label="Volver a la bandeja"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Solicitudes de muestra</div>
          <div class="name"><?= e($s['folio']) ?></div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap md-wrap">
    <div id="msg"></div>

    <!-- Encabezado: folio, estado, avisos y avance -->
    <section class="md-hero">
      <div class="md-hero-row">
        <div>
          <div class="md-eyebrow"><i class="bi bi-box-seam"></i> Solicitud de muestra</div>
          <div class="md-folio"><?= e($s['folio']) ?>
            <span class="md-pill md-pill--<?= $PILL[0] ?>"><i class="bi <?= $PILL[1] ?>"></i> <?= e($PILL[2]) ?></span>
          </div>
          <div class="md-hero-sub"><?= e($s['vendedor_nombre']) ?> la pidió para <b><?= e($s['cliente_nombre']) ?></b><?php if ($creada): ?> · <?= e(mdFechaCorta($creada, true)) ?>, <?= e($creada->format('H:i')) ?><?php endif; ?></div>
          <div class="md-hero-chips">
            <?php foreach ($chips as [$clase, $icono, $texto]): ?>
              <span class="md-hchip<?= $clase ? ' md-hchip--' . $clase : '' ?>"><i class="bi <?= $icono ?>"></i> <?= e($texto) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <a class="md-hbtn" href="<?= e(urlPdfMuestra((int)$s['id'], '../')) ?>" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
      </div>
      <div class="md-track">
        <div class="md-paso hecho"><div class="md-ln"></div><div class="md-dot"><i class="bi bi-check-lg"></i></div><div class="md-t">Enviada</div></div>
        <div class="md-paso <?= $pasoPrep ?>"><div class="md-ln"></div>
          <div class="md-dot"><i class="bi <?= $pasoPrep === 'hecho' ? 'bi-check-lg' : ($pasoPrep === 'omitido' ? 'bi-dash-lg' : 'bi-box-seam') ?>"></i></div>
          <div class="md-t">En preparación</div>
          <?php if ($s['estado'] === 'en_preparacion' && $detallePrep): ?><div class="md-d"><?= e($detallePrep) ?></div><?php endif; ?>
          <?php if ($pasoPrep === 'omitido'): ?><div class="md-d md-d--gris">Sin este paso</div><?php endif; ?>
        </div>
        <?php if ($s['estado'] === 'cancelada'): ?>
          <div class="md-paso cancelada"><div class="md-ln"></div><div class="md-dot"><i class="bi bi-x-lg"></i></div><div class="md-t">Cancelada</div></div>
        <?php else: ?>
          <div class="md-paso <?= $s['estado'] === 'embarcada' ? 'hecho' : '' ?>"><div class="md-ln"></div>
            <div class="md-dot"><i class="bi <?= $s['estado'] === 'embarcada' ? 'bi-check-lg' : 'bi-truck' ?>"></i></div><div class="md-t">Embarcada</div>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <div class="md-grid">
      <!-- IZQUIERDA -->
      <div class="md-main">
        <div class="md-card">
          <div class="md-sec"><i class="bi bi-box-seam"></i> Qué hay que surtir</div>
          <div class="md-prod">
            <div class="md-foto">
              <?php if ($cat['foto'] !== ''): ?>
                <img src="/erp/assets/img/cotizador/<?= e(rawurlencode($cat['foto'])) ?>" alt="" loading="lazy" onerror="this.replaceWith(Object.assign(document.createElement('i'),{className:'bi bi-box-seam'}))">
              <?php else: ?>
                <i class="bi bi-box-seam"></i>
              <?php endif; ?>
            </div>
            <div class="md-prod-txt">
              <?php if ($cat['grupo'] !== ''): ?><div class="md-linea"><?= e($cat['grupo']) ?></div><?php endif; ?>
              <div class="md-clave"><?= e($clave) ?><?php if ($cat['atributo'] !== ''): ?> <span class="md-attr"><?= e($cat['atributo']) ?></span><?php endif; ?></div>
              <?php if ($descEstilo !== ''): ?><div class="md-desc"><?= e($descEstilo) ?></div><?php endif; ?>
              <div class="md-tipo-wrap">
                <?php if ($s['tipo'] === 'variante'): ?>
                  <span class="md-tipo"><i class="bi bi-shuffle"></i> Variante · con cambios</span>
                <?php else: ?>
                  <span class="md-tipo md-tipo--igual"><i class="bi bi-check2-square"></i> Idéntico al estilo</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="md-fichas">
            <?php if (!empty($s['color'])): ?>
              <div class="md-ficha"><div class="l">Color</div><div class="v"><?php if ($swatch): ?><span class="md-sw" style="background:<?= $swatch ?>"></span><?php endif; ?><?= e(mb_convert_case((string)$s['color'], MB_CASE_TITLE)) ?></div></div>
            <?php endif; ?>
            <div class="md-ficha"><div class="l">Talla</div><div class="v"><?= $s['talla'] ? e($s['talla']) : '<span class="md-vacio">No la indicó</span>' ?></div></div>
            <div class="md-ficha"><div class="l">Pares</div><div class="v"><?= max(1, (int)($s['cantidad'] ?? 1)) ?></div></div>          </div>
          <?php if ($s['cambios']): ?>
            <div class="md-sec md-sec--sub"><i class="bi bi-tools"></i> Cambios que pide el cliente</div>
            <div class="md-cambios">
              <?php foreach ($s['cambios'] as $c):
                $clv = $c['categoria'] ?? '';
                $catTxt = $clv === 'otro' ? ($c['categoria_otro'] ?? 'Otro') : (MUESTRA_CATEGORIAS_CAMBIO[$clv] ?? $clv); ?>
                <div class="md-cambio">
                  <div class="ic"><i class="bi <?= $ICONO_CAMBIO[$clv] ?? 'bi-pencil-square' ?>"></i></div>
                  <div><b><?= e($catTxt) ?></b><span><?= e($c['descripcion'] ?? '') ?></span></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="md-motivo">
          <div class="q"><i class="bi bi-chat-quote-fill"></i>
            <?= !empty($s['motivo']) ? nl2br(e($s['motivo'])) : '<span class="md-vacio">No indicó el motivo</span>' ?>
          </div>
          <?php if (!empty($s['notas_planta'])): ?>
            <div class="md-notas"><b>Notas para planta</b><?= nl2br(e($s['notas_planta'])) ?></div>
          <?php endif; ?>
          <div class="md-minis">
            <span class="md-mini"><i class="bi bi-calendar-event"></i> <?= $promesa ? 'Fecha promesa: ' . e(mdFechaCorta($promesa, true)) : 'Sin fecha promesa' ?></span>
            <?php if (!empty($s['tiempo_prueba_dias'])): ?>
              <span class="md-mini"><i class="bi bi-stopwatch"></i> Prueba de <?= e(textoTiempoPruebaMuestra($s['tiempo_prueba_dias'])) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="md-card md-hist-card">
          <div class="md-sec"><i class="bi bi-clock-history"></i> Historial</div>
          <ol class="md-hist">
            <?php foreach ($historial as $h):
              [$cls, $ico] = $ICONO_HISTORIAL[$h['estado']] ?? ['', 'bi-dot'];
              $esPasoPt = $h['estado'] === 'en_preparacion' && $h['nota'] === 'Ya está en Producto Terminado';
              if ($esPasoPt) $ico = 'bi-box-seam'; ?>
              <li>
                <div class="hd <?= $cls ?>"><i class="bi <?= $ico ?>"></i></div>
                <div>
                  <div class="q"><?= $esPasoPt ? 'Ya está en Producto Terminado' : e($TXT_HISTORIAL[$h['estado']] ?? $h['estado']) ?><?= $h['usuario_nombre'] ? ' · ' . e($h['usuario_nombre']) : '' ?></div>
                  <?php if ($h['nota'] && !$esPasoPt): ?><div class="n"><?= e($h['nota']) ?></div><?php endif; ?>
                  <div class="c fecha-utc" data-utc="<?= e($h['creado_en']) ?>"><?= e($h['creado_en']) ?></div>
                </div>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>

      <!-- DERECHA -->
      <aside class="md-side">
        <?php if ($conAcciones): ?>
        <div class="md-card md-acc">
          <div class="md-sec"><i class="bi bi-lightning-charge-fill"></i> ¿Qué sigue?</div>

          <?php if ($s['estado'] === 'enviada'): ?>
            <p class="md-next">Revisa si hay en almacén y márcala en preparación.</p>
            <button type="button" class="md-btn md-btn--pri" data-panel="panel-preparacion"><i class="bi bi-box-seam"></i> Marcar en preparación</button>
            <button type="button" class="md-btn" data-panel="panel-embarcada"><i class="bi bi-truck"></i> Marcar embarcada</button>
          <?php elseif (($s['preparacion'] ?? '') === 'por_programar'): ?>
            <p class="md-next">Se mandó a fabricar. Cuando esté lista, márcala en Producto Terminado.</p>
            <button type="button" class="md-btn md-btn--pri" data-accion="pasar_pt"><i class="bi bi-box-seam"></i> Ya está en Producto Terminado</button>
            <button type="button" class="md-btn" data-panel="panel-embarcada"><i class="bi bi-truck"></i> Marcar embarcada</button>
          <?php else: ?>
            <p class="md-next"><?= ($s['preparacion'] ?? '') === 'pt' ? 'Ya está en almacén. Prepara el envío y márcala embarcada.' : 'Cuando salga, márcala embarcada.' ?></p>
            <button type="button" class="md-btn md-btn--pri" data-panel="panel-embarcada"><i class="bi bi-truck"></i> Marcar embarcada</button>
          <?php endif; ?>

          <?php if (in_array('en_preparacion', $siguientes, true)): ?>
          <form id="panel-preparacion" class="md-panel"><div class="md-panel-in">
            <span class="md-lbl md-lbl--top">¿De dónde sale la muestra?</span>
            <div class="md-opc" id="op-preparacion">
              <button type="button" class="md-op on" data-valor="pt"><i class="bi bi-box-seam"></i><b>En Producto Terminado</b><small>Ya hay en almacén</small></button>
              <button type="button" class="md-op" data-valor="por_programar"><i class="bi bi-gear"></i><b>Por programar</b><small>Hay que fabricarla</small></button>
            </div>
            <div id="campo-fecha-estimada" class="d-none">
              <label class="md-lbl" for="fecha_estimada">Fecha estimada para tenerla lista (opcional)</label>
              <input type="date" id="fecha_estimada" class="md-inp">
            </div>
            <p class="md-hint">A <?= e($s['vendedor_nombre']) ?> le llega un aviso que explica si ya hay en almacén o si se va a fabricar.</p>
            <button type="submit" class="md-btn md-btn--pri">Guardar en preparación</button>
          </div></form>
          <?php endif; ?>

          <form id="panel-embarcada" class="md-panel"><div class="md-panel-in">
            <span class="md-lbl md-lbl--top">¿Cómo se envió?</span>
            <div class="md-opc" id="op-envio">
              <button type="button" class="md-op on" data-valor="paqueteria"><i class="bi bi-truck"></i><b>Por paquetería</b><small>Con link de rastreo</small></button>
              <button type="button" class="md-op" data-valor="en_persona"><i class="bi bi-person-check"></i><b>En persona</b><small>Se entregó en mano</small></button>
            </div>
            <div id="campos-paqueteria">
              <label class="md-lbl" for="guia_url">Link de la guía</label>
              <input type="url" id="guia_url" class="md-inp" maxlength="1000" inputmode="url" placeholder="Pega aquí el link de rastreo" autocomplete="off">
            </div>
            <p class="md-hint">A <?= e($s['vendedor_nombre']) ?> le llega un aviso con el link para rastrear su muestra.</p>
            <button type="submit" class="md-btn md-btn--pri">Guardar como embarcada</button>
          </div></form>

          <button type="button" class="md-cancel" data-panel="panel-cancelar"><i class="bi bi-x-circle"></i> Cancelar solicitud</button>
          <form id="panel-cancelar" class="md-panel"><div class="md-panel-in">
            <label class="md-lbl md-lbl--top" for="motivo">Motivo de la cancelación</label>
            <textarea id="motivo" class="md-inp" rows="2" maxlength="500" placeholder="Ej. No hay ese estilo en esa talla"></textarea>
            <p class="md-hint">El vendedor verá este motivo. Una solicitud cancelada ya no se puede reabrir.</p>
            <button type="submit" class="md-btn md-btn--rojo">Cancelar solicitud</button>
          </div></form>
        </div>
        <?php else: ?>
        <div class="md-card">
          <div class="md-sec"><i class="bi bi-flag-fill"></i> Estado</div>
          <?php if ($s['estado'] === 'embarcada'): ?>
            <div class="md-box md-box--ok"><i class="bi bi-check-circle-fill"></i> <span><?= e(textoEnvioMuestra($s)) ?></span>
              <?php if (!empty($s['guia_url'])): ?><a href="<?= e($s['guia_url']) ?>" target="_blank" rel="noopener">Rastrear <i class="bi bi-box-arrow-up-right"></i></a><?php endif; ?>
            </div>
          <?php elseif ($s['estado'] === 'cancelada'): ?>
            <div class="md-box md-box--can"><i class="bi bi-x-circle-fill"></i> <span><b>Cancelada</b><?= !empty($s['motivo_cancelacion']) ? e($s['motivo_cancelacion']) : '' ?></span></div>
          <?php else: ?>
            <div class="md-box"><i class="bi <?= $PILL[1] ?>"></i> <span><b><?= e($PILL[2]) ?></b><?= $detallePrep ? e($detallePrep) : 'La atiende la responsable de muestras.' ?></span></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="md-card">
          <div class="md-sec"><i class="bi bi-geo-alt-fill"></i> Entrega</div>
          <div class="md-fila"><div class="ic"><i class="bi bi-person-fill"></i></div><div><div class="l">Entregar a</div><div class="v"><?= e(textoEntregarAMuestra($s)) ?></div></div></div>
          <div class="md-fila"><div class="ic"><i class="bi bi-signpost-2-fill"></i></div><div><div class="l">Dirección</div><div class="v"><?= nl2br(e($s['destino_direccion'])) ?></div></div></div>
        </div>

        <div class="md-card">
          <div class="md-sec"><i class="bi bi-people-fill"></i> Contactos</div>
          <div class="md-persona">
            <div class="md-av md-av--cli"><?= e(mdIniciales((string)($s['cliente_contacto'] ?: $s['cliente_nombre']))) ?></div>
            <div class="md-persona-txt">
              <div class="rol">Cliente · <?= e($s['cliente_nombre']) ?></div>
              <div class="nm"><?= !empty($s['cliente_contacto']) ? e($s['cliente_contacto']) : '<span class="md-vacio">Sin contacto registrado</span>' ?></div>
              <?php if (!empty($s['cliente_telefono'])): ?><div class="sb"><?= e($s['cliente_telefono']) ?></div><?php endif; ?>
            </div>
            <?php if ($telCliente): ?>
            <div class="md-ibtns">
              <a class="md-ib md-ib--tel" href="tel:<?= e($telCliente) ?>" title="Llamar" aria-label="Llamar al cliente"><i class="bi bi-telephone-fill"></i></a>
              <a class="md-ib md-ib--wa" href="<?= e(mdWhats($telCliente)) ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp al cliente"><i class="bi bi-whatsapp"></i></a>
            </div>
            <?php endif; ?>
          </div>
          <div class="md-persona">
            <div class="md-av md-av--ven"><?= e(mdIniciales((string)$s['vendedor_nombre'])) ?></div>
            <div class="md-persona-txt">
              <div class="rol">Vendedor</div>
              <div class="nm"><?= e($s['vendedor_nombre']) ?></div>
              <?php if (!empty($s['vendedor_telefono'])): ?><div class="sb"><?= e($s['vendedor_telefono']) ?></div><?php endif; ?>
            </div>
            <div class="md-ibtns">
              <?php if ($telLimpio): ?>
                <a class="md-ib md-ib--tel" href="tel:<?= e($telLimpio) ?>" title="Llamar" aria-label="Llamar al vendedor"><i class="bi bi-telephone-fill"></i></a>
                <a class="md-ib md-ib--wa" href="<?= e(mdWhats($telLimpio)) ?>" target="_blank" rel="noopener" title="WhatsApp" aria-label="WhatsApp al vendedor"><i class="bi bi-whatsapp"></i></a>
              <?php endif; ?>
              <?php if ($s['vendedor_email']): $mailto = urlMailto($s['vendedor_email'], $correoAsunto, $correoTexto); ?>
                <a class="md-ib md-ib--mail correo-pc" href="<?= e(urlGmailRedactar($s['vendedor_email'], $correoAsunto, $correoTexto)) ?>" target="_blank" rel="noopener" title="Escribir por Gmail" aria-label="Escribir por Gmail"><i class="bi bi-google"></i></a>
                <a class="md-ib md-ib--mail correo-pc" href="<?= e($mailto) ?>" title="Otro correo" aria-label="Escribir con otro correo"><i class="bi bi-envelope-fill"></i></a>
                <a class="md-ib md-ib--mail correo-movil" href="<?= e($mailto) ?>" title="Correo" aria-label="Escribir correo"><i class="bi bi-envelope-fill"></i></a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </aside>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/fecha_utils.js<?= assetVer(__DIR__ . '/../assets/js/fecha_utils.js') ?>"></script>
<?php if ($puedeCambiar): ?>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<?php endif; ?>
<script>
aplicarFechasUTC();
<?php if ($conAcciones): ?>
const ID_SOLICITUD = <?= (int)$s['id'] ?>;
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function mostrarError(texto) {
  document.getElementById('msg').innerHTML = `<div class="alert alert-danger py-2">${escHtml(texto)}</div>`;
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function cambiarEstado(estado, extra, boton) {
  document.getElementById('msg').innerHTML = '';
  const fd = new FormData();
  fd.append('id', ID_SOLICITUD);
  fd.append('estado', estado);
  Object.entries(extra || {}).forEach(([k, v]) => fd.append(k, v));
  const textoOriginal = boton ? boton.innerHTML : '';
  if (boton) { boton.disabled = true; boton.textContent = 'Guardando...'; }
  try {
    const r = await fetch('../api/muestra_estado.php', { method: 'POST', body: fd });
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'No se pudo guardar.');
    window.location.reload();
  } catch (e) {
    mostrarError(e.message);
    if (boton) { boton.disabled = false; boton.innerHTML = textoOriginal; }
  }
}

// Paneles: uno abierto a la vez; otro clic en el mismo botón lo cierra.
document.querySelectorAll('[data-panel]').forEach(b => b.addEventListener('click', () => {
  const p = document.getElementById(b.dataset.panel), abrir = !p.classList.contains('open');
  document.querySelectorAll('.md-panel').forEach(x => x.classList.remove('open'));
  if (abrir) p.classList.add('open');
}));

// Grupos de opciones (de dónde sale / cómo se envió).
function grupoOpciones(id, alCambiar) {
  const g = document.getElementById(id);
  if (!g) return () => null;
  g.querySelectorAll('.md-op').forEach(o => o.addEventListener('click', () => {
    g.querySelectorAll('.md-op').forEach(x => x.classList.toggle('on', x === o));
    alCambiar && alCambiar(o.dataset.valor);
  }));
  return () => g.querySelector('.md-op.on')?.dataset.valor;
}

// "Por programar" que ya se fabricó: pasa a "En Producto Terminado".
document.querySelectorAll('[data-accion="pasar_pt"]').forEach(b =>
  b.addEventListener('click', () => cambiarEstado('en_preparacion', { preparacion: 'pt' }, b)));

// En preparación: En Producto Terminado o Por programar (con fecha estimada opcional).
const formPrep = document.getElementById('panel-preparacion');
if (formPrep) {
  document.getElementById('fecha_estimada').min = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });
  const prep = grupoOpciones('op-preparacion', v =>
    document.getElementById('campo-fecha-estimada').classList.toggle('d-none', v !== 'por_programar'));
  formPrep.addEventListener('submit', (e) => {
    e.preventDefault();
    const extra = { preparacion: prep() };
    if (extra.preparacion === 'por_programar') extra.fecha_estimada = document.getElementById('fecha_estimada').value;
    cambiarEstado('en_preparacion', extra, e.submitter);
  });
}

const modoEnvio = grupoOpciones('op-envio', v =>
  document.getElementById('campos-paqueteria').classList.toggle('d-none', v !== 'paqueteria'));
document.getElementById('panel-embarcada').addEventListener('submit', (e) => {
  e.preventDefault();
  const modo = modoEnvio();
  const guiaUrl = document.getElementById('guia_url').value.trim();
  if (modo === 'paqueteria' && !/^https?:\/\/\S+$/i.test(guiaUrl)) {
    mostrarError('Pega el link de rastreo de la guía (debe empezar con http:// o https://).');
    return;
  }
  cambiarEstado('embarcada', { envio_modo: modo, guia_url: modo === 'paqueteria' ? guiaUrl : '' }, e.submitter);
});

document.getElementById('panel-cancelar').addEventListener('submit', (e) => {
  e.preventDefault();
  const motivo = document.getElementById('motivo').value.trim();
  if (!motivo) { mostrarError('Escribe el motivo de la cancelación.'); return; }
  cambiarEstado('cancelada', { motivo }, e.submitter);
});
<?php endif; ?>
</script>
</body>
</html>
