<?php
// Bloque de dirección + ubicación del cliente, compartido por
// vendedor/nuevo_cliente.php y vendedor/editar_cliente.php. La lógica vive
// en assets/js/direccion-cliente.js.
//
// Tres pasos, de arriba abajo:
//   1. ¿En qué colonia está?  -> un solo campo: CP o nombre de colonia.
//   2. Calle y número         -> el campo que se guarda; sugiere direcciones
//                                para ubicarla en el mapa.
//   3. Ubicación en el mapa   -> tocar el mapa / arrastrar el pin, o botones
//                                "Me mandaron la ubicación" y "Estoy aquí".
//
// Variables opcionales antes del include:
//   $calleNumeroPrevio  calle y número ya guardados (editar)
$calleNumeroPrevio = $calleNumeroPrevio ?? '';
?>
<style>
  .v26-dir-paso { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 7px; font-size: .8rem; font-weight: 800; color: var(--v26-ink); margin: 6px 0 8px; }
  .v26-dir-paso .v26-ubicacion-badge { white-space: nowrap; }
  .v26-dir-paso b { display: inline-grid; place-items: center; width: 20px; height: 20px; border-radius: 50%; background: var(--v26-brand-1); color: #fff; font-size: .68rem; flex-shrink: 0; }
  .v26-dir-nota { font-size: .72rem; margin-top: 3px; }
  .v26-dir-nota:empty { display: none; }
  .v26-dir-nota.error { color: var(--v26-red); }
  .v26-dir-nota.ok { color: var(--v26-green); }
  .v26-dir-nota.info { color: var(--v26-ink-soft); }
  .v26-dir-separador { border-top: 1px dashed var(--v26-border); margin: 14px 0 6px; }
  .v26-buscar-destacado .v26-search input.v26-input { padding-left: 38px; } /* .v26-compacto pisa el padding del ícono */
  .v26-buscar-destacado .v26-search { margin-bottom: 6px; }
  .v26-dir-titulo-lista { padding: 7px 12px; font-size: .7rem; font-weight: 800; color: var(--v26-ink-soft); background: rgba(0,0,0,.03); border-bottom: 1px solid var(--v26-border); }

  /* Colonia ya elegida */
  .v26-dir-resumen { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--v26-surface-solid); border: 1px solid var(--v26-border); border-radius: var(--v26-r-md); }
  .v26-dir-resumen > i { color: var(--v26-green); font-size: 1.1rem; }
  .v26-dir-resumen .txt { flex: 1; min-width: 0; line-height: 1.25; }
  .v26-dir-resumen .txt b { display: block; font-size: .88rem; }
  .v26-dir-resumen .txt small { color: var(--v26-ink-soft); font-size: .74rem; }
  .v26-dir-link { border: none; background: none; padding: 0; color: var(--v26-brand-2); font-size: .74rem; font-weight: 700; text-decoration: underline; cursor: pointer; }
  #colonia-manual { margin-top: 8px; }
  #btn-colonia-manual { display: block; text-align: left; margin-top: 2px; font-weight: 600; color: var(--v26-ink-soft); }

  /* Botones bajo el mapa */
  .v26-dir-acciones { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; }
  .v26-dir-acciones .v26-btn { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 8px; font-size: .78rem; }
  #panel-link { margin-top: 8px; }
  #resultados-busqueda button.text-muted { font-weight: 600; }

  #mapa-cliente { height: 260px; }
</style>

<div class="v26-buscar-destacado">
  <div class="v26-buscar-eyebrow"><i class="bi bi-geo-alt"></i> Dirección y ubicación del cliente</div>

  <!-- 1. Colonia -->
  <div class="v26-dir-paso"><b>1</b> ¿En qué colonia está?</div>
  <div id="caja-buscar-colonia">
    <div class="v26-search">
      <i class="bi bi-signpost-split"></i>
      <input type="text" id="buscar-colonia" class="v26-input" autocomplete="off" placeholder="CP o nombre de la colonia">
    </div>
    <div id="resultados-colonia"></div>
    <div id="nota-cp" class="v26-dir-nota info"></div>
    <button type="button" class="v26-dir-link" id="btn-colonia-manual">No la encuentro: elegir estado, municipio y colonia de la lista</button>
  </div>
  <div id="resumen-colonia" class="v26-dir-resumen d-none">
    <i class="bi bi-check-circle-fill"></i>
    <div class="txt"><b id="resumen-colonia-nombre"></b><small id="resumen-colonia-detalle"></small></div>
    <button type="button" class="v26-dir-link" id="btn-cambiar-colonia">Cambiar</button>
  </div>
  <div id="colonia-manual" class="d-none">
    <div class="v26-grid-2">
      <div class="v26-field">
        <label>Estado</label>
        <select id="select-estado" class="v26-select">
          <option value="">Cargando...</option>
        </select>
      </div>
      <div class="v26-field">
        <label>Municipio</label>
        <select id="select-municipio" class="v26-select" disabled>
          <option value="">Elige el estado</option>
        </select>
      </div>
    </div>
    <div class="v26-field">
      <label>Colonia</label>
      <select id="select-colonia" class="v26-select" disabled>
        <option value="">Elige el municipio</option>
      </select>
    </div>
  </div>
  <input type="hidden" id="input-cp">

  <div class="v26-dir-separador"></div>

  <!-- 2. Calle y número (es lo que se guarda; también sirve para ubicarla) -->
  <div class="v26-dir-paso"><b>2</b> Calle y número</div>
  <div class="v26-search">
    <i class="bi bi-house-door"></i>
    <input type="text" name="calle_numero" id="calle-numero" class="v26-input" autocomplete="off" value="<?= htmlspecialchars($calleNumeroPrevio) ?>" placeholder="Ej. Blvd. Diamantes 116" required>
  </div>
  <div id="resultados-busqueda"></div>

  <div class="v26-dir-separador"></div>

  <!-- 3. Punto exacto en el mapa -->
  <div class="v26-dir-paso"><b>3</b> Ubicación en el mapa <span id="ubicacion-estado" class="v26-ubicacion-badge pendiente"><i class="bi bi-exclamation-circle"></i> obligatoria, aún sin marcar</span></div>
  <div id="mapa-cliente" class="v26-map"></div>
  <div class="v26-dir-nota info">Toca el mapa en el punto exacto o arrastra el pin. Usa <b>Satélite</b> (arriba a la derecha) para ver las construcciones.</div>
  <div class="v26-dir-acciones">
    <button type="button" class="v26-btn v26-btn-ghost" id="btn-link-ubicacion"><i class="bi bi-link-45deg"></i> Me mandaron la ubicación</button>
    <button type="button" class="v26-btn v26-btn-ghost" id="btn-mi-ubicacion"><i class="bi bi-crosshair"></i> Estoy aquí</button>
  </div>
  <div id="panel-link" class="d-none">
    <div class="v26-search">
      <i class="bi bi-link-45deg"></i>
      <input type="text" id="pegar-ubicacion" class="v26-input" autocomplete="off" placeholder="Pega el link de Google Maps o WhatsApp">
    </div>
  </div>
  <div id="nota-link" class="v26-dir-nota info"></div>
  <input type="hidden" name="lat" id="lat">
  <input type="hidden" name="lng" id="lng">
</div>
