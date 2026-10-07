<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');

$clientePrellenado      = (int)($_GET['cliente_id'] ?? 0);
$citaPrellenada         = (int)($_GET['cita_id'] ?? 0);
$prospeccionPrellenada  = (int)($_GET['prospeccion_id'] ?? 0);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nueva cotización</title>
<link rel="icon" type="image/png" href="../assets/img/Favv-Segu.png">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
<style>
/* ============================================================
   Nueva cotización — pensada primero para celular (navegador y APK,
   que carga esta misma página en un WebView) y extendida a escritorio.
   - Celular: secciones apiladas, catálogo y clientes en hoja a pantalla
     completa, barra inferior fija con total + Guardar.
   - Escritorio (>= 992px): dos columnas, resumen fijo a la derecha.
   ============================================================ */
:root {
  /* Zona de gestos / barra de navegación de Android (APK edge-to-edge)
     y del iPhone. Capacitor puede exponerla como variable CSS. */
  --nc-safe-b: max(env(safe-area-inset-bottom, 0px), var(--safe-area-inset-bottom, 0px));
  --nc-safe-t: max(env(safe-area-inset-top, 0px), var(--safe-area-inset-top, 0px));
}
body.v26 { padding-bottom: calc(96px + var(--nc-safe-b)); }

.nc-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; }
.nc-grid > .v26-card { padding: 16px 14px; }
.nc-grid .v26-field:last-child { margin-bottom: 0; }
.nc-titulo { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin: 0 0 12px; font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--v26-ink-soft); }
.nc-titulo .v26-pill::before { display: none; }
.nc-sub { font-size: .76rem; color: var(--v26-ink-soft); margin-top: 6px; }

/* ---------- Cliente (botón que abre el buscador) ---------- */
.nc-cliente-btn {
  width: 100%; min-height: 50px; display: flex; align-items: center; gap: 10px; text-align: left;
  border: 1px solid var(--v26-border); background: var(--v26-surface-solid); border-radius: var(--v26-r-md);
  padding: 10px 14px; font-family: inherit; font-size: .95rem; color: var(--v26-ink); cursor: pointer;
  transition: border-color .15s, box-shadow .15s;
}
.nc-cliente-btn .nom { flex: 1; min-width: 0; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.nc-cliente-btn.vacio .nom { font-weight: 500; color: var(--v26-ink-soft); }
.nc-cliente-btn .bi-chevron-down { color: var(--v26-ink-soft); flex: none; }
.nc-cliente-btn.falta { border-color: var(--v26-red); box-shadow: 0 0 0 3px rgba(225,29,72,.12); }
.nc-cliente-btn .v26-pill { flex: none; }

/* ---------- Segmentados (lista / pronto pago) ---------- */
.nc-seg { display: flex; width: 100%; margin: 0; }
.nc-seg .v26-seg-btn { flex: 1; padding: 10px 8px; min-height: 40px; }
@media (max-width: 420px) { .nc-precios.v26-fila-2 { grid-template-columns: 1fr; gap: 0; } .nc-precios .v26-field { margin-bottom: 14px; } .nc-precios .v26-field:last-child { margin-bottom: 0; } }

/* ---------- Hojas (catálogo y clientes) ----------
   Cerradas no ocupan nada. Abiertas en celular cubren la pantalla y tienen
   un solo scroll (el de su lista), así no se "pelea" con el de la página. */
.nc-sheet { display: none; }
.nc-sheet.abierta {
  display: flex; flex-direction: column; position: fixed; inset: 0; z-index: 90;
  background: var(--v26-bg); animation: nc-sube .22s var(--v26-ease);
}
@keyframes nc-sube { from { transform: translateY(24px); opacity: .6; } to { transform: none; opacity: 1; } }
.nc-sheet-head {
  display: flex; align-items: center; gap: 10px; flex: none;
  padding: calc(10px + var(--nc-safe-t)) 12px 10px 16px;
  background: var(--v26-surface-solid); border-bottom: 1px solid var(--v26-border);
}
.nc-sheet-head h2 { flex: 1; margin: 0; font-size: 1rem; font-weight: 800; }
.nc-cerrar { width: 42px; height: 42px; border-radius: 50%; border: none; background: rgba(20,23,31,.05); color: var(--v26-ink); font-size: 1.2rem; display: flex; align-items: center; justify-content: center; flex: none; }
.nc-sheet-search { flex: none; padding: 12px 16px 8px; }
.nc-sheet-search .v26-search { margin: 0; }
.nc-sheet-search input { font-size: 16px; }
.nc-sheet-body { flex: 1; min-height: 0; overflow-y: auto; overscroll-behavior: contain; -webkit-overflow-scrolling: touch; padding: 0 16px 16px; }
.nc-sheet-foot { flex: none; padding: 10px 16px calc(10px + var(--nc-safe-b)); background: var(--v26-surface-solid); border-top: 1px solid var(--v26-border); }
.nc-lista { background: var(--v26-surface-solid); border: 1px solid var(--v26-border); border-radius: 14px; overflow: hidden; }
body.nc-sheet-abierta { overflow: hidden; }
.nc-backdrop { display: none; }

/* Opciones de cliente */
.nc-op { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 52px; padding: 10px 14px; border-bottom: 1px solid var(--v26-border); cursor: pointer; font-size: .9rem; font-weight: 600; }
.nc-op:last-child { border-bottom: none; }
.nc-op:active { background: rgba(20,23,31,.04); }
.nc-op.sel { background: rgba(232,164,0,.10); }
.nc-op .v26-pill { flex: none; }
.nc-op .contacto { display: block; font-size: .74rem; font-weight: 500; color: var(--v26-ink-soft); }

/* Catálogo de modelos */
.nc-grupo { position: sticky; top: 0; z-index: 1; background: #F6F4F0; padding: 7px 14px; font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .03em; color: var(--v26-ink-soft); border-bottom: 1px solid var(--v26-border); }
.nc-estilo-item { display: flex; align-items: center; justify-content: space-between; gap: 10px; min-height: 64px; padding: 10px 14px; cursor: pointer; border-bottom: 1px solid var(--v26-border); transition: background .15s; }
.nc-estilo-item:last-child { border-bottom: none; }
.nc-estilo-item:active { background: rgba(20,23,31,.05); }
.nc-estilo-item .info { display: flex; align-items: center; gap: 12px; min-width: 0; }
.nc-estilo-item .clave { font-weight: 800; font-size: .92rem; }
.nc-estilo-item .nombre { color: var(--v26-ink-soft); font-size: .75rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.nc-estilo-item .lado { display: flex; align-items: center; gap: 10px; flex: none; }
.nc-estilo-item .precio { font-size: .82rem; font-weight: 700; white-space: nowrap; }
.nc-estilo-item .agregar { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; color: var(--v26-brand-2); }
.nc-estilo-item.en-carrito { background: rgba(22,163,74,.06); }
.nc-cuenta { min-width: 34px; height: 34px; padding: 0 8px; border-radius: 999px; background: var(--v26-green); color: #fff; font-weight: 800; font-size: .85rem; display: flex; align-items: center; justify-content: center; }
.nc-estilo-item.pulso .agregar { animation: nc-pulso .35s var(--v26-ease); }
@keyframes nc-pulso { 50% { transform: scale(1.25); } }
.nc-foto { width: 46px; height: 46px; object-fit: contain; border: 1px solid var(--v26-border); border-radius: 10px; background: #fff; flex: none; }
.nc-foto--vacia { display: flex; align-items: center; justify-content: center; color: var(--v26-ink-soft); font-size: 1rem; }
.nc-sin { padding: 28px 12px; text-align: center; color: var(--v26-ink-soft); font-size: .85rem; }

/* ---------- Renglones de la cotización (tarjetas) ---------- */
.nc-renglones { display: flex; flex-direction: column; gap: 10px; }
.nc-renglon { background: var(--v26-surface-solid); border: 1px solid var(--v26-border); border-radius: 14px; padding: 12px; }
.nc-r-top { display: flex; gap: 10px; align-items: flex-start; }
.nc-r-top .nc-foto { width: 42px; height: 42px; }
.nc-r-info { flex: 1; min-width: 0; }
.nc-r-clave { font-weight: 800; font-size: .95rem; line-height: 1.2; }
.nc-r-nombre { font-size: .74rem; color: var(--v26-ink-soft); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.nc-r-meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 6px; }
.nc-quitar { width: 40px; height: 40px; margin: -6px -6px 0 0; border-radius: 50%; border: none; background: none; color: var(--v26-ink-soft); font-size: 1.15rem; display: flex; align-items: center; justify-content: center; flex: none; }
.nc-quitar:active { color: var(--v26-red); background: rgba(225,29,72,.08); }
.nc-r-bottom { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
.nc-stepper { display: flex; align-items: center; border: 1px solid var(--v26-border); border-radius: 12px; overflow: hidden; background: var(--v26-surface-solid); flex: none; }
.nc-stepper button { width: 40px; height: 42px; border: none; background: transparent; font-size: 1.2rem; font-weight: 800; line-height: 1; color: var(--v26-ink); }
.nc-stepper button:active { background: rgba(20,23,31,.06); }
.nc-stepper input { width: 50px; height: 42px; border: none; border-left: 1px solid var(--v26-border); border-right: 1px solid var(--v26-border); border-radius: 0; text-align: center; font-weight: 800; font-size: 16px; padding: 0; font-family: inherit; color: var(--v26-ink); }
.nc-precio-wrap { position: relative; flex: 1; min-width: 0; }
.nc-precio-wrap > span { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--v26-ink-soft); font-weight: 700; pointer-events: none; }
.nc-precio { width: 100%; height: 44px; border: 1px solid var(--v26-border); border-radius: 12px; padding: 0 12px 0 26px; font-size: 16px; font-weight: 700; text-align: right; font-family: inherit; color: var(--v26-ink); background: var(--v26-surface-solid); }
.nc-stepper input:focus, .nc-precio:focus { outline: none; box-shadow: inset 0 0 0 2px var(--v26-brand-1); }
.nc-precio.bajo { border-color: var(--v26-red); box-shadow: 0 0 0 3px rgba(225,29,72,.12); }
.nc-r-ref { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; margin-top: 8px; font-size: .74rem; color: var(--v26-ink-soft); }
.nc-r-ref .importe { font-size: 1rem; font-weight: 800; color: var(--v26-ink); white-space: nowrap; }
.nc-r-ref .bajo-txt { color: var(--v26-red); font-weight: 700; }
.nc-color { height: 34px; font-size: .85rem; font-family: inherit; border: 1px solid var(--v26-border); border-radius: 10px; padding: 0 8px; max-width: 100%; background: var(--v26-surface-solid); }
.nc-color.falta { border-color: var(--v26-red); background: rgba(225,29,72,.05); }
.nc-tag { display: inline-flex; align-items: center; gap: 4px; font-size: .68rem; font-weight: 700; padding: 3px 9px; border-radius: 999px; background: rgba(20,23,31,.06); color: var(--v26-ink-soft); }
.nc-tag--dickies { background: #FEF9C3; color: #854D0E; }
.nc-tag--entrega { background: #E0F2FE; color: #075985; }
.nc-tag--attr { background: transparent; border: 1px solid #F5A623; color: #92400E; }
.nc-attr { display: inline-block; margin-left: 4px; font-size: .62rem; font-weight: 800; padding: 1px 6px; border-radius: 999px; border: 1px solid #F5A623; color: #92400E; vertical-align: 2px; }
.nc-aviso a.nc-usar-entrega { color: inherit; font-weight: 800; white-space: nowrap; }
.nc-aviso-guardar { display: flex; flex-direction: column; gap: 10px; font-size: .85rem; font-weight: 600; padding: 12px; border-radius: 12px; background: #FFFBEB; color: #B45309; }
.nc-aviso-guardar .botones { display: flex; flex-wrap: wrap; gap: 8px; }
.nc-aviso-guardar button { min-height: 40px; border-radius: var(--v26-r-pill); padding: 8px 14px; font-weight: 700; font-size: .82rem; font-family: inherit; cursor: pointer; border: 1px solid #F59E0B; background: #fff; color: #92400E; }
.nc-aviso-guardar button.principal { background: #F59E0B; color: #fff; }
.nc-tag--desc { background: rgba(22,163,74,.1); color: var(--v26-green); }

.nc-vacio { border: 1.5px dashed rgba(20,23,31,.15); border-radius: 14px; padding: 22px 14px; text-align: center; color: var(--v26-ink-soft); font-size: .85rem; }
.nc-vacio i { display: block; font-size: 1.8rem; margin-bottom: 6px; color: var(--v26-brand-2); }
.nc-agregar-btn { margin-top: 10px; }

/* ---------- Avisos ---------- */
.nc-aviso { display: flex; gap: 8px; align-items: flex-start; font-size: .78rem; font-weight: 600; margin-top: 10px; padding: 10px 12px; border-radius: 12px; background: rgba(20,23,31,.04); color: var(--v26-ink-soft); }
.nc-aviso:empty { display: none; }
.nc-aviso.ok { background: rgba(22,163,74,.08); color: var(--v26-green); }
.nc-aviso.dickies { background: #FFFBEB; color: #B45309; }
.nc-progreso { height: 6px; border-radius: 999px; background: rgba(20,23,31,.08); margin-top: 6px; overflow: hidden; }
.nc-progreso > div { height: 100%; background: var(--v26-brand-grad); border-radius: 999px; }

/* ---------- Totales ---------- */
.nc-totales { margin-top: 12px; border-top: 1px solid var(--v26-border); padding-top: 10px; font-size: .88rem; }
.nc-totales .fila { display: flex; justify-content: space-between; padding: 3px 0; color: var(--v26-ink-soft); }
.nc-totales .fila span:last-child { color: var(--v26-ink); font-weight: 600; }
.nc-totales .total { font-weight: 800; font-size: 1.1rem; color: var(--v26-ink); margin-top: 4px; }
.nc-totales .total span:last-child { font-weight: 800; }

/* ---------- Chips (vigencia, entrega, forma de pago) ---------- */
.nc-chips { display: flex; flex-wrap: wrap; gap: 8px; }
.nc-chip { min-height: 40px; border: 1px solid var(--v26-border); background: var(--v26-surface-solid); color: var(--v26-ink); border-radius: var(--v26-r-pill); padding: 8px 14px; font-size: .82rem; font-weight: 700; cursor: pointer; box-shadow: var(--v26-shadow-sm); font-family: inherit; }
.nc-chip.active { background: var(--v26-brand-grad); color: #fff; border-color: transparent; box-shadow: var(--v26-shadow-brand); }
.nc-otro-wrap { margin-top: 8px; }
.nc-otro-wrap.oculto { display: none; }
.v26-input, .v26-textarea { font-size: 16px; }

/* ---------- Mensajes / guardar ---------- */
#msg-cotizacion .alert { margin: 12px 0 0; font-size: .85rem; border-radius: 12px; }
.nc-guardar-panel { margin-top: 14px; }

/* ---------- Barra inferior fija (celular y APK) ---------- */
.nc-barra {
  position: fixed; left: 0; right: 0; bottom: 0; z-index: 50;
  background: rgba(255,255,255,.95); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  border-top: 1px solid var(--v26-border); box-shadow: 0 -10px 28px -14px rgba(20,23,31,.25);
  padding: 10px 16px calc(10px + var(--nc-safe-b));
  transition: transform .2s var(--v26-ease);
}
.nc-barra-in { max-width: 640px; margin: 0 auto; display: flex; align-items: center; gap: 12px; }
.nc-barra .tot { flex: 1; min-width: 0; line-height: 1.15; }
.nc-barra .tot small { display: block; font-size: .72rem; font-weight: 600; color: var(--v26-ink-soft); }
.nc-barra .tot strong { font-size: 1.2rem; font-weight: 800; }
.nc-barra .v26-btn { flex: none; padding: 13px 22px; min-height: 48px; }
/* Con el teclado abierto la barra estorba: se esconde. */
body.nc-teclado .nc-barra, body.nc-sheet-abierta .nc-barra { transform: translateY(110%); }

.nc-solo-escritorio { display: none; }

/* ============ Escritorio ============ */
@media (hover: hover) {
  .nc-estilo-item:hover, .nc-op:hover { background: rgba(232,164,0,.07); }
  .nc-estilo-item.en-carrito:hover { background: rgba(22,163,74,.1); }
  .nc-quitar:hover { color: var(--v26-red); background: rgba(225,29,72,.08); }
  .nc-stepper button:hover { background: rgba(20,23,31,.05); }
}
@media (min-width: 992px) {
  body.v26 { padding-bottom: 32px; }
  .v26-wrap.nc-wrap { max-width: 1140px; padding: 24px 24px 8px; }
  .nc-grid {
    grid-template-columns: minmax(0, 1fr) 420px;
    grid-template-rows: auto auto auto 1fr;
    grid-template-areas: "cliente resumen" "precios resumen" "modelos resumen" "condic resumen";
    gap: 16px 22px; align-items: start;
  }
  .nc-grid > .v26-card { padding: 20px; }
  #sec-cliente { grid-area: cliente; }
  #sec-precios { grid-area: precios; }
  #sec-modelos { grid-area: modelos; }
  #sec-condiciones { grid-area: condic; }
  #sec-resumen { grid-area: resumen; position: sticky; top: 92px; max-height: calc(100vh - 110px); display: flex; flex-direction: column; }
  #sec-resumen .nc-resumen-scroll { flex: 1; min-height: 0; overflow-y: auto; margin: 0 -6px; padding: 0 6px; }
  .nc-cliente-extra.v26-fila-2 { grid-template-columns: 1fr 1fr; }

  /* El catálogo vive fijo en la columna izquierda. */
  #sheet-catalogo, #sheet-catalogo.abierta { display: flex; flex-direction: column; position: static; background: none; animation: none; }
  #sheet-catalogo .nc-sheet-head, #sheet-catalogo .nc-sheet-foot { display: none; }
  #sheet-catalogo .nc-sheet-search { padding: 0 0 10px; }
  #sheet-catalogo .nc-sheet-body { padding: 0; max-height: 440px; border-radius: 14px; }

  /* Clientes: ventana centrada. */
  #sheet-cliente.abierta { inset: auto; top: 9vh; left: 50%; transform: translateX(-50%); width: min(540px, 92vw); max-height: 78vh; border-radius: 20px; box-shadow: var(--v26-shadow-md); overflow: hidden; animation: none; }
  #sheet-cliente .nc-sheet-head { padding-top: 12px; }
  body.nc-sheet-abierta .nc-backdrop { display: block; position: fixed; inset: 0; z-index: 85; background: rgba(20,23,31,.4); }

  .nc-barra, .nc-solo-movil { display: none !important; }
  .nc-solo-escritorio { display: block; }
}
@media (max-width: 991.98px) {
  /* En celular el bloque "Modelos" no se ve: su hoja se abre desde el
     resumen. display:contents evita que la tarjeta (con backdrop-filter)
     encierre a la hoja fija. */
  #sec-modelos { display: contents; }
  #sec-modelos > .nc-titulo { display: none; }
  .nc-guardar-panel { display: none; }
}
@media (min-width: 560px) and (max-width: 991.98px) {
  .nc-cliente-extra.v26-fila-2 { grid-template-columns: 1fr 1fr; }
}
@media (max-width: 559.98px) {
  .nc-cliente-extra.v26-fila-2 { grid-template-columns: 1fr; gap: 0; }
}
</style>
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="cotizaciones.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis cotizaciones" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Ventas</div>
          <div class="name">Nueva cotización</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <button type="button" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Tour guiado: cómo hacer una cotización" aria-label="Tour guiado" id="btn-tour-ayuda"><i class="bi bi-question-lg"></i></button>
        <a href="../logout.php" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Cerrar sesión" aria-label="Salir"><i class="bi bi-box-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="v26-wrap nc-wrap">
    <form id="form-cotizacion" novalidate>
      <div class="nc-grid">

        <!-- Cliente -->
        <section class="v26-card" id="sec-cliente">
          <h2 class="nc-titulo">Cliente</h2>
          <div class="v26-field">
            <button type="button" class="nc-cliente-btn vacio" id="btn-cliente" aria-haspopup="dialog">
              <i class="bi bi-person"></i>
              <span class="nom" id="cliente-nombre">Cargando clientes…</span>
              <span class="v26-pill" id="badge-etapa-cliente" style="display:none;"></span>
              <i class="bi bi-chevron-down"></i>
            </button>
            <input type="hidden" name="cliente_id" id="input-cliente" value="">
            <div class="nc-sub">¿No está en la lista? <a href="nuevo_cliente.php">Regístralo primero</a>.</div>
          </div>
          <div class="v26-fila-2 nc-cliente-extra">
            <div class="v26-field">
              <label for="input-contacto">Contacto</label>
              <input type="text" name="cliente_contacto" id="input-contacto" class="v26-input" autocomplete="off" placeholder="Nombre de quien recibe">
            </div>
            <div class="v26-field">
              <label for="input-email">Correo</label>
              <input type="email" name="cliente_email" id="input-email" class="v26-input" inputmode="email" autocomplete="off" placeholder="correo@empresa.com">
            </div>
          </div>
        </section>

        <!-- Lista de precios -->
        <section class="v26-card" id="sec-precios">
          <div class="v26-fila-2 nc-precios">
            <div class="v26-field">
              <label>Tipo de lista</label>
              <div class="v26-seg nc-seg" id="seg-tipo-lista">
                <button type="button" class="v26-seg-btn active" data-valor="industria">Industria</button>
                <button type="button" class="v26-seg-btn" data-valor="distribuidor">Distribuidor</button>
              </div>
            </div>
            <div class="v26-field">
              <label>Pronto pago</label>
              <div class="v26-seg nc-seg" id="seg-pronto-pago">
                <button type="button" class="v26-seg-btn active" data-valor="0">No</button>
                <button type="button" class="v26-seg-btn" data-valor="1">Sí aplica</button>
              </div>
            </div>
          </div>
        </section>

        <!-- Catálogo: hoja en celular, panel fijo en escritorio -->
        <section class="v26-card" id="sec-modelos">
          <h2 class="nc-titulo">Catálogo de modelos</h2>
          <div class="nc-sheet" id="sheet-catalogo" role="dialog" aria-label="Agregar modelos">
            <div class="nc-sheet-head">
              <h2>Agregar modelos</h2>
              <button type="button" class="nc-cerrar" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="nc-sheet-search">
              <div class="v26-search">
                <i class="bi bi-search"></i>
                <input type="search" id="input-buscar-estilo" class="v26-input" placeholder="Buscar modelo, línea o marca…" autocomplete="off" enterkeyhint="search">
              </div>
            </div>
            <div class="nc-sheet-body">
              <div id="nc-lista-estilos" class="nc-lista">
                <div class="v26-skel" style="margin:12px"></div>
                <div class="v26-skel" style="margin:12px"></div>
              </div>
            </div>
            <div class="nc-sheet-foot">
              <button type="button" class="v26-btn v26-btn-primary v26-btn-block" data-cerrar id="btn-listo-catalogo">Listo</button>
            </div>
          </div>
        </section>

        <!-- Resumen: renglones + totales -->
        <section class="v26-card" id="sec-resumen">
          <h2 class="nc-titulo">
            <span>Cotización</span>
            <span class="v26-pill v26-pill--pendiente" id="resumen-cuenta" style="display:none;"></span>
          </h2>
          <div class="nc-resumen-scroll">
            <div id="nc-vacio" class="nc-vacio">
              <i class="bi bi-bag-plus"></i>
              <span class="nc-solo-movil">Aún no agregas modelos.</span>
              <span class="nc-solo-escritorio">Toca un modelo del catálogo para agregarlo.</span>
            </div>
            <div class="nc-renglones" id="lista-renglones"></div>
            <button type="button" class="v26-btn v26-btn-ghost v26-btn-block nc-agregar-btn nc-solo-movil" id="btn-abrir-catalogo">
              <i class="bi bi-plus-lg"></i> <span>Agregar modelos</span>
            </button>
            <div class="nc-aviso" id="aviso-mayoreo"></div>
            <div class="nc-aviso dickies" id="aviso-dickies"></div>
          </div>
          <div class="nc-totales">
            <div class="fila"><span>Pares</span><span id="tot-pares">0</span></div>
            <div class="fila"><span>Subtotal</span><span id="tot-subtotal">$0.00</span></div>
            <div class="fila"><span>IVA</span><span id="tot-iva">$0.00</span></div>
            <div class="fila total"><span>Total</span><span id="tot-total">$0.00</span></div>
          </div>
          <div id="msg-cotizacion" role="alert"></div>
          <div class="nc-guardar-panel">
            <button type="submit" class="v26-btn v26-btn-primary v26-btn-block" id="btn-guardar">Guardar cotización</button>
          </div>
        </section>

        <!-- Condiciones comerciales -->
        <section class="v26-card" id="sec-condiciones">
          <h2 class="nc-titulo">Condiciones</h2>
          <div class="v26-field">
            <label>Vigencia</label>
            <div class="nc-chips" id="chips-vigencia">
              <button type="button" class="nc-chip" data-valor="7">7 días</button>
              <button type="button" class="nc-chip" data-valor="15">15 días</button>
              <button type="button" class="nc-chip" data-valor="30">30 días</button>
              <button type="button" class="nc-chip" data-valor="45">45 días</button>
            </div>
            <input type="hidden" name="vigencia_dias" id="input-vigencia" value="15">
          </div>

          <div class="v26-field">
            <label>Tiempo de entrega</label>
            <div class="nc-chips" id="chips-entrega">
              <button type="button" class="nc-chip" data-valor="5 días hábiles">5 días hábiles</button>
              <button type="button" class="nc-chip" data-valor="10 días hábiles">10 días hábiles</button>
              <button type="button" class="nc-chip" data-valor="15 días hábiles">15 días hábiles</button>
              <button type="button" class="nc-chip" data-valor="20 días hábiles">20 días hábiles</button>
              <button type="button" class="nc-chip" data-valor="30 días hábiles">30 días hábiles</button>
              <!-- Solo aparece si la cotización lleva un modelo de 75 días (DK-700/702/800/801) -->
              <button type="button" class="nc-chip" data-valor="75 días hábiles" style="display:none">75 días hábiles</button>
              <button type="button" class="nc-chip" data-valor="A convenir">A convenir</button>
              <button type="button" class="nc-chip" data-otro="1">Otro…</button>
            </div>
            <div class="nc-otro-wrap oculto" id="otro-entrega-wrap">
              <input type="text" id="input-entrega-otro" class="v26-input" placeholder="Escribe el tiempo de entrega…">
            </div>
            <input type="hidden" name="tiempo_entrega" id="input-tiempo-entrega" value="">
            <div class="nc-aviso" id="aviso-entrega"></div>
          </div>

          <div class="v26-field">
            <label>Forma de pago</label>
            <div class="nc-chips" id="chips-forma-pago">
              <button type="button" class="nc-chip" data-valor="Contado">Contado</button>
              <button type="button" class="nc-chip" data-valor="50% anticipo, 50% contra entrega">50% anticipo, 50% resto</button>
              <button type="button" class="nc-chip" data-valor="30 días de crédito">30 días de crédito</button>
              <button type="button" class="nc-chip" data-valor="Transferencia bancaria">Transferencia bancaria</button>
              <button type="button" class="nc-chip" data-otro="1">Otro…</button>
            </div>
            <div class="nc-otro-wrap oculto" id="otro-forma-wrap">
              <input type="text" id="input-forma-otro" class="v26-input" placeholder="Escribe la forma de pago…">
            </div>
            <input type="hidden" name="forma_pago" id="input-forma-pago" value="">
          </div>

          <div class="v26-field">
            <label for="input-notas">Notas</label>
            <textarea name="notas" id="input-notas" class="v26-textarea" rows="2"></textarea>
          </div>
        </section>
      </div>

      <input type="hidden" name="visitas_cita_id" value="<?= $citaPrellenada ?: '' ?>">
      <input type="hidden" name="visitas_prospeccion_id" value="<?= $prospeccionPrellenada ?: '' ?>">
      <input type="hidden" name="renglones" id="input-renglones" value="[]">
    </form>
  </div>

  <!-- Hoja de clientes (fuera de las tarjetas para que el position:fixed funcione) -->
  <div class="nc-sheet" id="sheet-cliente" role="dialog" aria-label="Elegir cliente">
    <div class="nc-sheet-head">
      <h2>Elegir cliente</h2>
      <button type="button" class="nc-cerrar" data-cerrar aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="nc-sheet-search">
      <div class="v26-search">
        <i class="bi bi-search"></i>
        <input type="search" id="input-buscar-cliente" class="v26-input" placeholder="Buscar por nombre o contacto…" autocomplete="off" enterkeyhint="search">
      </div>
    </div>
    <div class="nc-sheet-body">
      <div class="nc-lista" id="lista-clientes"></div>
    </div>
  </div>
  <div class="nc-backdrop" id="nc-backdrop"></div>

  <!-- Barra inferior (celular / APK) -->
  <div class="nc-barra" id="nc-barra">
    <div class="nc-barra-in">
      <div class="tot">
        <small id="barra-pares">Sin modelos</small>
        <strong id="barra-total">$0.00</strong>
      </div>
      <button type="submit" form="form-cotizacion" class="v26-btn v26-btn-primary" id="barra-guardar">Guardar</button>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/v26-tour.js<?= assetVer(__DIR__ . '/../assets/js/v26-tour.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script src="../assets/js/avisos.js<?= assetVer(__DIR__ . '/../assets/js/avisos.js') ?>"></script>
<script>
iniciarTrackingPeriodico();

const CLIENTE_PRELLENADO = <?= (int)$clientePrellenado ?>;
let CFG = { descuento_distribuidor: 0.15, descuento_pronto_pago: 0.05, descuento_mayoreo: 0.10, minimo_pares_mayoreo: 16, minimo_pares_dickies: 16, tasa_iva: 0.16, vigencia_default_dias: 15 };
// Fotos del catálogo: viven en el ERP (mismo servidor), no se duplican aquí.
const FOTOS_URL = '/erp/assets/img/cotizador/';
// Renglón: {it (item del catálogo), cantidad, precio_final, auto, color}.
// auto = el precio lo pone la lista (true) o lo tecleó el vendedor (false).
let renglones = [];
let tipoLista = 'industria';
let prontoPago = false;
let guardando = false;
let guardada = false;

const $ = id => document.getElementById(id);
const esEscritorio = () => window.matchMedia('(min-width: 992px)').matches;
function money(n) { return '$' + Number(n || 0).toLocaleString('es-MX', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
function escHtml(s) { return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function norm(s) { return String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
function pares(n) { return n === 1 ? '1 par' : `${n} pares`; }

// Mismas reglas que erp/cotizacion/_renglones_js.php; el servidor vuelve a
// validar todo al guardar (resolverRenglonesCotizacionErp).
function esDickies(it) { return !!it && it.marca === 'DICKIES'; }
function precioLista(it, tl) { return tl === 'distribuidor' ? it.precio_distribuidor : it.precio_industria; }
function paresPorMarca() {
  const p = { total: 0, segurmex: 0, dickies: 0 };
  renglones.forEach(r => {
    const n = parseInt(r.cantidad) || 0;
    p.total += n;
    if (esDickies(r.it)) p.dickies += n; else p.segurmex += n;
  });
  return p;
}
function aplicaMayoreo() { return paresPorMarca().segurmex >= CFG.minimo_pares_mayoreo; }
function calcularMinimo(it, base, pp) {
  if (esDickies(it)) return base; // Dickies: precio fijo, sin descuentos
  let f = 1;
  if (pp) f *= (1 - CFG.descuento_pronto_pago);
  if (aplicaMayoreo()) f *= (1 - CFG.descuento_mayoreo);
  return Math.round(base * f * 100) / 100;
}
function fotoHTML(it) {
  return it.foto
    ? `<img src="${FOTOS_URL}${encodeURIComponent(it.foto)}" alt="" loading="lazy" class="nc-foto">`
    : '<span class="nc-foto nc-foto--vacia"><i class="bi bi-image"></i></span>';
}

async function cargarConfig() {
  try {
    const res = await fetch('../api/config_cotizador.php');
    const data = await res.json();
    if (data.ok) CFG = { ...CFG, ...data.config };
  } catch (e) {}
  const vDefault = CFG.vigencia_default_dias || 15;
  $('input-vigencia').value = vDefault;
  document.querySelectorAll('#chips-vigencia .nc-chip').forEach(b => {
    b.classList.toggle('active', b.dataset.valor === String(vDefault));
  });
  recalcularTodo();
}

// ================= Hojas (catálogo / clientes) =================
// Cada hoja abierta agrega una entrada al historial: el botón "atrás" de
// Android (navegador o APK) la cierra en lugar de salirse de la página y
// perder la cotización capturada.
let sheetAbierta = null;
function abrirSheet(id, conFoco) {
  const el = $(id);
  if (id === 'sheet-catalogo' && esEscritorio()) { $('input-buscar-estilo').focus(); return; }
  if (sheetAbierta) {
    sheetAbierta.classList.remove('abierta');
    history.replaceState({ ncSheet: id }, '');
  } else {
    history.pushState({ ncSheet: id }, '');
  }
  sheetAbierta = el;
  el.classList.add('abierta');
  document.body.classList.add('nc-sheet-abierta');
  el.querySelector('.nc-sheet-body').scrollTop = 0;
  if (conFoco) setTimeout(() => el.querySelector('input[type="search"]').focus(), 60);
}
function cerrarSheet(desdeHistorial) {
  if (!sheetAbierta) return;
  sheetAbierta.classList.remove('abierta');
  sheetAbierta = null;
  document.body.classList.remove('nc-sheet-abierta');
  if (document.activeElement) document.activeElement.blur();
  if (!desdeHistorial && history.state && history.state.ncSheet) history.back();
}
window.addEventListener('popstate', () => { if (sheetAbierta) cerrarSheet(true); });
document.querySelectorAll('[data-cerrar]').forEach(b => b.addEventListener('click', () => cerrarSheet(false)));
$('nc-backdrop').addEventListener('click', () => cerrarSheet(false));
document.addEventListener('keydown', e => { if (e.key === 'Escape' && sheetAbierta) cerrarSheet(false); });
// Si la ventana pasa a escritorio con el catálogo abierto, se cierra la hoja.
window.matchMedia('(min-width: 992px)').addEventListener('change', e => {
  if (e.matches && sheetAbierta && sheetAbierta.id === 'sheet-catalogo') cerrarSheet(false);
});

// ================= Cliente =================
let clientes = [];
let contactoAuto = '';

function pintarCliente() {
  const id = parseInt($('input-cliente').value) || 0;
  const c = clientes.find(x => x.id === id);
  const btn = $('btn-cliente');
  const badge = $('badge-etapa-cliente');
  btn.classList.toggle('vacio', !c);
  if (c) btn.classList.remove('falta');
  $('cliente-nombre').textContent = c ? c.nombre : (clientes.length ? 'Selecciona un cliente' : 'No tienes clientes registrados aún');
  if (!c || !c.etapa) { badge.style.display = 'none'; return; }
  const esCliente = c.etapa === 'convertido';
  badge.className = 'v26-pill ' + (esCliente ? 'v26-pill--etapa-convertido' : 'v26-pill--etapa-prospecto_agregado');
  badge.textContent = esCliente ? 'Cliente' : 'Prospecto';
  badge.style.display = 'inline-flex';
}

function elegirCliente(id) {
  $('input-cliente').value = id || '';
  const c = clientes.find(x => x.id === id);
  // Prellena el contacto con el del cliente, sin pisar lo que ya escribió el vendedor.
  const inp = $('input-contacto');
  if (c && (inp.value.trim() === '' || inp.value === contactoAuto)) {
    contactoAuto = c.nombre_contacto || '';
    inp.value = contactoAuto;
  }
  pintarCliente();
}

function renderClientes(q) {
  const cont = $('lista-clientes');
  const nq = norm(q);
  const sel = parseInt($('input-cliente').value) || 0;
  const lista = !nq ? clientes : clientes.filter(c => norm(c.nombre + ' ' + (c.nombre_contacto || '')).includes(nq));
  if (!lista.length) {
    cont.innerHTML = `<div class="nc-sin">${clientes.length ? 'Sin resultados.' : 'No tienes clientes registrados aún.'}<br><a href="nuevo_cliente.php">Registrar cliente</a></div>`;
    return;
  }
  cont.innerHTML = lista.map(c => {
    const esCliente = c.etapa === 'convertido';
    const pill = c.etapa ? `<span class="v26-pill ${esCliente ? 'v26-pill--etapa-convertido' : 'v26-pill--etapa-prospecto_agregado'}">${esCliente ? 'Cliente' : 'Prospecto'}</span>` : '';
    return `<div class="nc-op ${c.id === sel ? 'sel' : ''}" data-id="${c.id}" role="option">
      <span style="min-width:0;">${escHtml(c.nombre)}${c.nombre_contacto ? `<span class="contacto">${escHtml(c.nombre_contacto)}</span>` : ''}</span>${pill}
    </div>`;
  }).join('');
}
$('lista-clientes').addEventListener('click', e => {
  const op = e.target.closest('.nc-op');
  if (!op) return;
  elegirCliente(+op.dataset.id);
  cerrarSheet(false);
});
$('input-buscar-cliente').addEventListener('input', e => renderClientes(e.target.value.trim()));
$('btn-cliente').addEventListener('click', () => {
  $('input-buscar-cliente').value = '';
  renderClientes('');
  // En escritorio el teclado no estorba: se enfoca el buscador de una vez.
  abrirSheet('sheet-cliente', esEscritorio());
});

async function cargarClientes() {
  try {
    const res = await fetch('../api/clientes.php');
    const data = await res.json();
    clientes = data.ok ? data.clientes.map(c => ({ ...c, id: +c.id })) : [];
  } catch (e) { clientes = []; }
  if (CLIENTE_PRELLENADO && clientes.some(c => c.id === CLIENTE_PRELLENADO)) elegirCliente(CLIENTE_PRELLENADO);
  else pintarCliente();
}

// ================= Renglones =================
// Recalcula lista, mínimo y precio de cada renglón (como recalcTodo() del
// ERP): el precio arranca en el de lista; si el vendedor lo tecleó y quedó
// por debajo del mínimo (p. ej. al cambiar de lista), se sube al mínimo.
function recalcularTodo() {
  renglones.forEach(r => {
    r.cantidad = Math.max(1, parseInt(r.cantidad) || 1);
    r.base = precioLista(r.it, tipoLista);
    r.minimo = calcularMinimo(r.it, r.base, prontoPago);
    if (r.auto || r.precio_final == null) r.precio_final = r.base;
    else if (r.precio_final < r.minimo) r.precio_final = r.minimo;
  });
  renderRenglones();
  recalcularSoloTotales();
}

function refHTML(r) {
  if (esDickies(r.it)) return `Precio fijo ${money(r.base)}`;
  let t = `Lista ${money(r.base)}`;
  if (r.minimo < r.base) t += ` · Mín ${money(r.minimo)}`;
  return t;
}
function tagsDescuento(r) {
  if (esDickies(r.it) || r.minimo >= r.base) return '';
  const d = [];
  if (prontoPago) d.push('pronto pago');
  if (aplicaMayoreo()) d.push('mayoreo');
  return d.length ? `<span class="nc-tag nc-tag--desc" title="El precio puede bajar hasta el mínimo"><i class="bi bi-tag"></i>Mín. con ${d.join(' + ')}</span>` : '';
}

function renderRenglones() {
  const cont = $('lista-renglones');
  $('nc-vacio').style.display = renglones.length ? 'none' : 'block';
  $('btn-abrir-catalogo').querySelector('span').textContent = renglones.length ? 'Agregar más modelos' : 'Agregar modelos';
  cont.innerHTML = renglones.map((r, i) => {
    const it = r.it;
    let color = '';
    if (it.colores.length > 1) {
      color = `<select class="nc-color sel-color ${r.color ? '' : 'falta'}" data-i="${i}" aria-label="Color"><option value="">Elige color…</option>`
            + it.colores.map(c => `<option value="${escHtml(c)}" ${c === r.color ? 'selected' : ''}>${escHtml(c)}</option>`).join('') + '</select>';
    } else if (it.colores.length === 1) {
      color = `<span class="nc-tag">${escHtml(it.colores[0])}</span>`;
    }
    const attr = it.atributo ? `<span class="nc-tag nc-tag--attr">${escHtml(it.atributo)}${ATRIBUTOS[it.atributo] ? ' · ' + escHtml(ATRIBUTOS[it.atributo]) : ''}</span>` : '';
    const dickies = attr + (esDickies(it) ? '<span class="nc-tag nc-tag--dickies">Dickies · precio fijo</span>' : '')
                  + (it.entrega_dias ? `<span class="nc-tag nc-tag--entrega"><i class="bi bi-truck"></i>Entrega: ${parseInt(it.entrega_dias)} días hábiles</span>` : '');
    return `
    <div class="nc-renglon" data-i="${i}">
      <div class="nc-r-top">
        ${fotoHTML(it)}
        <div class="nc-r-info">
          <div class="nc-r-clave">${escHtml(it.clave)}</div>
          <div class="nc-r-nombre">${escHtml(it.nombre)}</div>
          <div class="nc-r-meta">${color}${dickies}${tagsDescuento(r)}</div>
        </div>
        <button type="button" class="nc-quitar" data-i="${i}" aria-label="Quitar ${escHtml(it.clave)}"><i class="bi bi-trash3"></i></button>
      </div>
      <div class="nc-r-bottom">
        <div class="nc-stepper">
          <button type="button" class="nc-menos" data-i="${i}" aria-label="Un par menos">−</button>
          <input type="text" inputmode="numeric" pattern="[0-9]*" class="input-cant" data-i="${i}" value="${r.cantidad}" aria-label="Pares" enterkeyhint="done">
          <button type="button" class="nc-mas" data-i="${i}" aria-label="Un par más">+</button>
        </div>
        <div class="nc-precio-wrap">
          <span>$</span>
          <input type="text" inputmode="decimal" class="nc-precio input-precio" data-i="${i}" value="${Number(r.precio_final).toFixed(2)}" aria-label="Precio por par" enterkeyhint="done" ${esDickies(it) ? 'readonly' : ''}>
        </div>
      </div>
      <div class="nc-r-ref">
        <span class="ref" data-i="${i}">${refHTML(r)}</span>
        <span class="importe" data-i="${i}">${money(r.cantidad * r.precio_final)}</span>
      </div>
    </div>`;
  }).join('');
}

// Eventos de los renglones (delegados: la lista se vuelve a pintar seguido).
const contR = $('lista-renglones');
contR.addEventListener('click', e => {
  const b = e.target.closest('.nc-menos, .nc-mas, .nc-quitar');
  if (!b) return;
  const i = +b.dataset.i;
  if (b.classList.contains('nc-quitar')) renglones.splice(i, 1);
  else if (b.classList.contains('nc-menos')) renglones[i].cantidad = Math.max(1, (parseInt(renglones[i].cantidad) || 1) - 1);
  else renglones[i].cantidad = (parseInt(renglones[i].cantidad) || 0) + 1;
  recalcularTodo();
});
contR.addEventListener('input', e => {
  const t = e.target;
  const r = renglones[+t.dataset.i];
  if (!r) return;
  if (t.classList.contains('input-cant')) {
    t.value = t.value.replace(/\D/g, '').slice(0, 5);
    r.cantidad = parseInt(t.value) || 0;
    recalcularSoloTotales();
  } else if (t.classList.contains('input-precio')) {
    const v = t.value.replace(',', '.').replace(/[^\d.]/g, '');
    r.auto = v.trim() === '';
    r.precio_final = Math.max(0, parseFloat(v) || 0);
    const bajo = !r.auto && r.precio_final < r.minimo;
    t.classList.toggle('bajo', bajo);
    const ref = contR.querySelector(`.ref[data-i="${t.dataset.i}"]`);
    if (ref) ref.innerHTML = bajo ? `<span class="bajo-txt">Mínimo ${money(r.minimo)}</span>` : refHTML(r);
    recalcularSoloTotales();
  }
});
// Al salir del campo: cantidad vacía = 1; precio vacío = el de lista;
// abajo del mínimo = sube al mínimo.
contR.addEventListener('change', e => {
  const t = e.target;
  if (t.classList.contains('sel-color')) {
    renglones[+t.dataset.i].color = t.value;
    t.classList.toggle('falta', !t.value);
    recalcularSoloTotales();
  } else if (t.classList.contains('input-cant') || t.classList.contains('input-precio')) {
    recalcularTodo();
  }
});
// Al entrar a cantidad o precio se selecciona todo para teclear encima. El
// toque en Android coloca el cursor después del focus, así que se repite en
// el primer click.
contR.addEventListener('focusin', e => {
  const t = e.target;
  if (!t.matches('.input-cant, .input-precio')) return;
  t._seleccionar = true;
  const valor = t.value;
  setTimeout(() => { if (t.value === valor) t.select(); }, 0);
});
contR.addEventListener('click', e => {
  const t = e.target;
  if (t._seleccionar && t.matches('.input-cant, .input-precio')) { t._seleccionar = false; t.select(); }
});
contR.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('input')) { e.preventDefault(); e.target.blur(); } });

// Totales, avisos y lo que se manda al guardar. No toca los precios: el
// vendedor puede ir tecleando sin que se le muevan a media escritura.
function recalcularSoloTotales() {
  let subtotal = 0;
  renglones.forEach((r, i) => {
    r.importe = Math.round((parseInt(r.cantidad) || 0) * r.precio_final * 100) / 100;
    subtotal += r.importe;
    const celda = contR.querySelector(`.importe[data-i="${i}"]`);
    if (celda) celda.textContent = money(r.importe);
  });
  const iva = Math.round(subtotal * CFG.tasa_iva * 100) / 100;
  const total = Math.round((subtotal + iva) * 100) / 100;
  const p = paresPorMarca();
  $('tot-pares').textContent = p.total;
  $('tot-subtotal').textContent = money(subtotal);
  $('tot-iva').textContent = money(iva);
  $('tot-total').textContent = money(total);
  $('barra-total').textContent = money(total);
  $('barra-pares').textContent = renglones.length ? `${pares(p.total)} · IVA incluido` : 'Sin modelos';
  const cuenta = $('resumen-cuenta');
  cuenta.style.display = renglones.length ? 'inline-flex' : 'none';
  cuenta.textContent = `${renglones.length} ${renglones.length === 1 ? 'modelo' : 'modelos'} · ${pares(p.total)}`;
  $('btn-listo-catalogo').textContent = renglones.length ? `Listo · ${pares(p.total)} · ${money(total)}` : 'Listo';
  pintarAvisos();
  marcarEstilosEnCarrito();
  $('input-renglones').value = JSON.stringify(
    renglones.map(r => ({ item: r.it.item, cantidad: Math.max(1, parseInt(r.cantidad) || 1), precio_final: r.precio_final, color: r.color || '' }))
  );
}

function pintarAvisos() {
  const p = paresPorMarca();
  const avM = $('aviso-mayoreo');
  const avD = $('aviso-dickies');
  const min = CFG.minimo_pares_mayoreo;
  // Solo Dickies (sin pares SEGURMEX): el mayoreo no aplica, el aviso sobra.
  if (!renglones.length || (p.segurmex === 0 && p.dickies > 0)) {
    avM.innerHTML = '';
  } else if (p.segurmex >= min) {
    avM.innerHTML = `<i class="bi bi-check-circle-fill"></i><span>Mayoreo aplicado (${p.segurmex} pares SEGURMEX, mínimo ${min}).</span>`;
    avM.classList.add('ok');
  } else {
    const pct = Math.min(100, Math.round(p.segurmex / min * 100));
    avM.innerHTML = `<i class="bi bi-info-circle"></i><div style="flex:1;">Faltan ${pares(min - p.segurmex)} SEGURMEX para el mayoreo (mínimo ${min}; Dickies no cuenta).<div class="nc-progreso"><div style="width:${pct}%"></div></div></div>`;
    avM.classList.remove('ok');
  }
  pintarEntrega();
  avD.innerHTML = (p.dickies > 0 && p.dickies < CFG.minimo_pares_dickies)
    ? `<i class="bi bi-exclamation-triangle"></i><span>Dickies: pedido mínimo de ${CFG.minimo_pares_dickies} pares (llevas ${p.dickies}). Puedes guardar la cotización, pero avísale al cliente.</span>`
    : '';
}

// ================= Catálogo =================
// El mismo del cotizador del ERP, completo y agrupado por marca · línea.
let catalogo = [];
let ATRIBUTOS = {}; // significado de PP, PP+D… (lo manda api/estilos_erp.php)

function paresDeItem(item) {
  return renglones.reduce((s, r) => s + (r.it.item === item ? (parseInt(r.cantidad) || 0) : 0), 0);
}
function iconoAgregar(n) {
  return n > 0 ? `<span class="nc-cuenta">${n}</span>` : '<i class="bi bi-plus-circle"></i>';
}

function renderListaEstilos(lista) {
  const cont = $('nc-lista-estilos');
  if (!lista.length) {
    cont.innerHTML = `<div class="nc-sin">${catalogo.length ? 'Sin resultados.' : 'No se pudo cargar el catálogo.'}</div>`;
    return;
  }
  let grupo = null;
  cont.innerHTML = lista.map(it => {
    const n = paresDeItem(it.item);
    let html = '';
    if (it.grupo !== grupo) { grupo = it.grupo; html += `<div class="nc-grupo">${escHtml(grupo)}</div>`; }
    return html + `
    <div class="nc-estilo-item ${n ? 'en-carrito' : ''}" data-item="${escHtml(it.item)}" role="button" tabindex="0">
      <div class="info">
        ${fotoHTML(it)}
        <div style="min-width:0;">
          <div class="clave">${escHtml(it.clave)}${it.atributo ? `<span class="nc-attr">${escHtml(it.atributo)}</span>` : ''}</div>
          <div class="nombre">${escHtml(it.nombre)}</div>
        </div>
      </div>
      <div class="lado">
        <span class="precio">${money(precioLista(it, tipoLista))}</span>
        <div class="agregar">${iconoAgregar(n)}</div>
      </div>
    </div>`;
  }).join('');
}

function agregarItem(el) {
  const it = catalogo.find(c => c.item === el.dataset.item);
  if (!it) return;
  // Un modelo con varios colores agrega otro renglón (puede ir en otro color);
  // los demás solo suman un par al renglón que ya está.
  const existente = it.colores.length > 1 ? null : renglones.find(r => r.it.item === it.item);
  if (existente) {
    existente.cantidad = (parseInt(existente.cantidad) || 0) + 1;
  } else {
    renglones.push({ it, cantidad: 1, precio_final: null, auto: true, color: it.colores.length === 1 ? it.colores[0] : '' });
  }
  recalcularTodo();
  el.classList.remove('pulso'); void el.offsetWidth; el.classList.add('pulso');
}
$('nc-lista-estilos').addEventListener('click', e => {
  const el = e.target.closest('.nc-estilo-item');
  if (el) agregarItem(el);
});
$('nc-lista-estilos').addEventListener('keydown', e => {
  const el = e.target.closest('.nc-estilo-item');
  if (el && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); agregarItem(el); }
});

function marcarEstilosEnCarrito() {
  document.querySelectorAll('#nc-lista-estilos .nc-estilo-item').forEach(el => {
    const n = paresDeItem(el.dataset.item);
    el.classList.toggle('en-carrito', n > 0);
    const ag = el.querySelector('.agregar');
    const nuevo = iconoAgregar(n);
    if (ag && ag.innerHTML !== nuevo) ag.innerHTML = nuevo;
  });
}

function filtrarCatalogo() {
  const q = norm($('input-buscar-estilo').value.trim());
  renderListaEstilos(!q ? catalogo : catalogo.filter(it => norm(it.clave + ' ' + it.nombre + ' ' + it.grupo).includes(q)));
}

async function cargarEstilos() {
  try {
    const res = await fetch('../api/estilos_erp.php');
    const data = await res.json();
    catalogo = data.ok ? data.catalogo : [];
    ATRIBUTOS = (data.ok && data.atributos) || {};
  } catch (e) { catalogo = []; }
  filtrarCatalogo();
}

$('input-buscar-estilo').addEventListener('input', filtrarCatalogo);
$('btn-abrir-catalogo').addEventListener('click', () => abrirSheet('sheet-catalogo', false));

// ================= Segmentados y chips =================
function wireSeg(id, alCambiar) {
  document.querySelectorAll(`#${id} .v26-seg-btn`).forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll(`#${id} .v26-seg-btn`).forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    alCambiar(btn.dataset.valor);
  }));
}
wireSeg('seg-tipo-lista', v => { tipoLista = v; recalcularTodo(); filtrarCatalogo(); });
wireSeg('seg-pronto-pago', v => { prontoPago = v === '1'; recalcularTodo(); });

document.querySelectorAll('#chips-vigencia .nc-chip').forEach(chip => chip.addEventListener('click', () => {
  document.querySelectorAll('#chips-vigencia .nc-chip').forEach(c => c.classList.remove('active'));
  chip.classList.add('active');
  $('input-vigencia').value = chip.dataset.valor;
}));

// Chips con opción "Otro" (entrega, forma de pago)
function wireChipsConOtro(gridId, otroWrapId, otroInputId, hiddenId) {
  const grid = $(gridId), otroWrap = $(otroWrapId), otroInput = $(otroInputId), hidden = $(hiddenId);
  grid.querySelectorAll('.nc-chip').forEach(chip => chip.addEventListener('click', () => {
    grid.querySelectorAll('.nc-chip').forEach(c => c.classList.remove('active'));
    chip.classList.add('active');
    if (chip.dataset.otro) {
      otroWrap.classList.remove('oculto');
      hidden.value = otroInput.value.trim();
      otroInput.focus();
    } else {
      otroWrap.classList.add('oculto');
      hidden.value = chip.dataset.valor;
    }
  }));
  otroInput.addEventListener('input', () => { hidden.value = otroInput.value.trim(); });
}
wireChipsConOtro('chips-entrega', 'otro-entrega-wrap', 'input-entrega-otro', 'input-tiempo-entrega');
wireChipsConOtro('chips-forma-pago', 'otro-forma-wrap', 'input-forma-otro', 'input-forma-pago');

// ================= Tiempo de entrega vs. plazo de los modelos =================
// Mismas reglas que erp/cotizacion/_renglones_js.php (y avisoTiempoEntregaErp()
// en includes/cotizador_helpers.php): los Dickies traen su plazo (30 o 75 días
// hábiles). Solo se AVISA; el vendedor puede guardar con otro tiempo.
var DIAS_PRESET_SIEMPRE = 30;  // chips de más días (75) solo salen si algún modelo los pide
var entregaAceptada = '';       // aviso que el vendedor ya decidió ignorar ("Guardar así")
function diasDeTiempoEntrega(txt) {
  const m = String(txt || '').match(/(\d+)\s*d[ií]as?/i);
  return m ? parseInt(m[1], 10) : null;
}
function entregaRequerida() {
  let dias = null, modelos = [];
  renglones.forEach(r => {
    const d = r.it && r.it.entrega_dias ? parseInt(r.it.entrega_dias) : null;
    if (!d) return;
    if (dias === null || d > dias) { dias = d; modelos = []; }
    if (d === dias && !modelos.includes(r.it.clave)) modelos.push(r.it.clave);
  });
  return { dias, modelos };
}
function avisoEntregaTxt(req, txt) {
  if (!req.dias) return null;
  const d = diasDeTiempoEntrega(txt);
  if (d === null || d >= req.dias) return null;
  return `${req.modelos.join(', ')} se entrega${req.modelos.length > 1 ? 'n' : ''} en ${req.dias} días hábiles, y el tiempo de entrega elegido es "${txt}".`;
}
function pintarEntrega() {
  const av = $('aviso-entrega');
  if (!av) return;
  const req = entregaRequerida();
  document.querySelectorAll('#chips-entrega .nc-chip[data-valor]').forEach(b => {
    const d = diasDeTiempoEntrega(b.dataset.valor);
    b.style.display = (d === null || d <= DIAS_PRESET_SIEMPRE || (req.dias !== null && d <= req.dias)) ? '' : 'none';
  });
  const txt = $('input-tiempo-entrega').value.trim();
  const aviso = avisoEntregaTxt(req, txt);
  const usar = req.dias ? ` <a href="#" class="nc-usar-entrega">Usar ${req.dias} días hábiles</a>` : '';
  if (aviso) {
    av.className = 'nc-aviso dickies';
    av.innerHTML = `<i class="bi bi-exclamation-triangle"></i><span>${escHtml(aviso)}${usar}</span>`;
  } else if (req.dias && !txt) {
    av.className = 'nc-aviso';
    av.innerHTML = `<i class="bi bi-truck"></i><span>${escHtml(req.modelos.join(', '))}: entrega en ${req.dias} días hábiles.${usar}</span>`;
  } else {
    av.innerHTML = '';
  }
}
function usarEntregaRequerida() {
  const req = entregaRequerida();
  if (!req.dias) return;
  const valor = `${req.dias} días hábiles`;
  const chip = document.querySelector(`#chips-entrega .nc-chip[data-valor="${valor}"]`);
  if (chip) { chip.style.display = ''; chip.click(); }
  else {
    document.querySelector('#chips-entrega .nc-chip[data-otro]').click();
    $('input-entrega-otro').value = valor;
    $('input-tiempo-entrega').value = valor;
  }
  pintarEntrega();
}
$('chips-entrega').addEventListener('click', () => setTimeout(pintarEntrega, 0));
$('input-entrega-otro').addEventListener('input', pintarEntrega);
$('aviso-entrega').addEventListener('click', e => {
  if (!e.target.closest('.nc-usar-entrega')) return;
  e.preventDefault();
  usarEntregaRequerida();
});
// Al guardar: si el tiempo es menor al del modelo más tardado, se avisa en la
// pantalla (sin ventanas del navegador, que en la APK se ven mal) y se deja
// elegir entre corregirlo o guardar así.
function reenviarFormulario() {
  const f = $('form-cotizacion');
  if (f.requestSubmit) f.requestSubmit(); else f.dispatchEvent(new Event('submit', { cancelable: true }));
}
function avisarEntregaAntesDeGuardar() {
  const aviso = avisoEntregaTxt(entregaRequerida(), $('input-tiempo-entrega').value.trim());
  if (!aviso || aviso === entregaAceptada) return false;
  const req = entregaRequerida();
  const msg = $('msg-cotizacion');
  msg.innerHTML = `<div class="nc-aviso-guardar"><div><i class="bi bi-exclamation-triangle"></i> ${escHtml(aviso)}</div>
    <div class="botones"><button type="button" class="principal" id="btn-entrega-usar">Usar ${req.dias} días hábiles</button>
    <button type="button" id="btn-entrega-guardar-asi">Guardar así</button></div></div>`;
  msg.scrollIntoView({ behavior: 'smooth', block: 'center' });
  $('btn-entrega-usar').addEventListener('click', () => { usarEntregaRequerida(); msg.innerHTML = ''; reenviarFormulario(); });
  $('btn-entrega-guardar-asi').addEventListener('click', () => { entregaAceptada = aviso; msg.innerHTML = ''; reenviarFormulario(); });
  return true;
}

// ================= Teclado (celular / APK) =================
// Con el teclado abierto se esconde la barra inferior para no tapar el campo.
// Se detecta por foco en un campo de texto + la pantalla visible encogida
// (WebView de la APK y Chrome de Android).
const TIPOS_SIN_TECLADO = ['checkbox', 'radio', 'button', 'submit', 'hidden', 'range', 'color', 'file'];
function esCampoTexto(el) {
  return !!el && !el.readOnly && (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && !TIPOS_SIN_TECLADO.includes(el.type)));
}
let altoMax = 0, anchoBase = 0;
function revisarTeclado() {
  const vv = window.visualViewport;
  const h = vv ? vv.height : window.innerHeight;
  const w = window.innerWidth;
  if (w !== anchoBase) { anchoBase = w; altoMax = 0; } // rotó la pantalla
  if (h > altoMax) altoMax = h;
  const enfocado = esCampoTexto(document.activeElement);
  document.body.classList.toggle('nc-teclado', enfocado && (!vv || h < altoMax * 0.8));
}
if (window.visualViewport) window.visualViewport.addEventListener('resize', revisarTeclado);
window.addEventListener('resize', revisarTeclado);
document.addEventListener('focusin', () => setTimeout(revisarTeclado, 250));
document.addEventListener('focusout', () => setTimeout(revisarTeclado, 100));
revisarTeclado();

// ================= Guardar =================
function mostrarError(texto, enfocar) {
  const msg = $('msg-cotizacion');
  msg.innerHTML = `<div class="alert alert-danger py-2">${escHtml(texto)}</div>`;
  (enfocar || msg).scrollIntoView({ behavior: 'smooth', block: 'center' });
}
function setGuardando(v) {
  guardando = v;
  ['btn-guardar', 'barra-guardar'].forEach(id => {
    const b = $(id);
    b.disabled = v;
    b.innerHTML = v ? '<span class="spinner-border spinner-border-sm"></span> Guardando…' : (id === 'barra-guardar' ? 'Guardar' : 'Guardar cotización');
  });
}

$('form-cotizacion').addEventListener('submit', async (e) => {
  e.preventDefault();
  if (guardando) return;
  if (document.activeElement) document.activeElement.blur();
  $('msg-cotizacion').innerHTML = '';
  if (!$('input-cliente').value) {
    $('btn-cliente').classList.add('falta');
    mostrarError('Elige el cliente de la cotización.', $('sec-cliente'));
    return;
  }
  if (!renglones.length) {
    mostrarError('Agrega al menos un modelo.');
    return;
  }
  const sinColor = renglones.filter(r => r.it.colores.length > 1 && !r.color).map(r => r.it.clave);
  if (sinColor.length) {
    mostrarError(`Elige el color de: ${sinColor.join(', ')}.`, contR.querySelector('.sel-color.falta'));
    return;
  }
  const email = $('input-email').value.trim();
  if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
    mostrarError('Revisa el correo del cliente.', $('input-email'));
    return;
  }
  if (avisarEntregaAntesDeGuardar()) return;
  recalcularTodo();
  const fd = new FormData(e.target);
  fd.set('tipo_lista', tipoLista);
  fd.set('pronto_pago', prontoPago ? '1' : '0');
  setGuardando(true);
  try {
    const res = await fetch('../api/cotizaciones.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ok) {
      guardada = true;
      // Directo a la cotización: ahí se avisa que quedó generada y se envía.
      window.location.href = data.id ? `ver_cotizacion.php?id=${data.id}&nueva=1` : 'cotizaciones.php';
      return;
    }
    mostrarError(data.error || 'No se pudo guardar la cotización.');
  } catch (err) {
    mostrarError('No se pudo guardar: revisa tu conexión e inténtalo de nuevo.');
  }
  setGuardando(false);
});

// Aviso del navegador si se sale con modelos capturados sin guardar.
window.addEventListener('beforeunload', e => {
  if (renglones.length && !guardada) { e.preventDefault(); e.returnValue = ''; }
});

// ================= Tour guiado =================
// Mismo motor que las demás pantallas (assets/js/v26-tour.js). Sale solo la
// primera vez; después se repite con el botón ? de arriba. Textos cortos y
// sencillos: es para quien apenas empieza a usar el sistema. En celular el
// catálogo es una hoja aparte, por eso ese paso señala el botón que la abre.
function pasosTourNuevaCotizacion() {
  const pp = Math.round((CFG.descuento_pronto_pago || 0) * 100);
  return [
    { selector: '#btn-cliente', texto: 'Primero elige a quién le vas a cotizar. Si no aparece en la lista, regístralo primero.' },
    { selector: '#seg-tipo-lista', texto: 'Elige la lista de precios que le toca a este cliente: Industria o Distribuidor.' },
    { selector: '#seg-pronto-pago', texto: `Toca «Sí aplica» solo si el cliente va a pagar de contado o por adelantado. Le baja ${pp}% al precio.` },
    esEscritorio()
      ? { selector: '#sheet-catalogo .nc-sheet-search', texto: 'Busca el modelo y tócalo para agregarlo. La etiqueta junto a la clave (PP+D, O…) dice qué protección tiene.' }
      : { selector: '#btn-abrir-catalogo', texto: 'Toca aquí para abrir el catálogo. Luego toca cada modelo que quieras agregar.' },
    { selector: '#sec-resumen', texto: 'Aquí ves lo que llevas. Puedes cambiar los pares y el precio; el precio nunca puede quedar abajo del mínimo.' },
    esEscritorio()
      ? { selector: '#sec-condiciones', texto: 'Elige vigencia, tiempo de entrega y forma de pago. Si llevas Dickies, aquí te avisa en cuántos días se entregan.' }
      : { selector: '#chips-entrega', texto: 'Aquí eliges el tiempo de entrega (arriba la vigencia y abajo la forma de pago). Si llevas Dickies, aquí te avisa en cuántos días se entregan.' },
    { selector: esEscritorio() ? '#btn-guardar' : '#barra-guardar', texto: 'Cuando esté todo, toca Guardar. Después la puedes mandar al cliente.' },
  ];
}
const OPCIONES_TOUR_NUEVA_COTIZACION = {
  storageKey: 'v26_tour_nueva_cotizacion_visto',
  saludoTitulo: 'Cómo hacer una cotización',
  saludoTexto: 'Te enseñamos en 7 pasos rápidos.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
$('btn-tour-ayuda').addEventListener('click', () => {
  if (sheetAbierta) cerrarSheet(false);
  V26Tour.reiniciar(pasosTourNuevaCotizacion(), OPCIONES_TOUR_NUEVA_COTIZACION);
});

recalcularTodo();
// La primera vez, el tour arranca ya con parámetros, clientes y catálogo cargados.
Promise.all([cargarConfig(), cargarClientes(), cargarEstilos()])
  .then(() => V26Tour.iniciar(pasosTourNuevaCotizacion(), OPCIONES_TOUR_NUEVA_COTIZACION));
</script>
</body>
</html>
