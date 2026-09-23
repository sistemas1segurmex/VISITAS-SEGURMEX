<?php
// Bloque de dirección + ubicación del cliente, compartido por
// vendedor/nuevo_cliente.php y vendedor/editar_cliente.php. La lógica vive
// en assets/js/direccion-cliente.js.
//
// Variables opcionales antes del include:
//   $calleNumeroPrevio  calle y número ya guardados (editar)
$calleNumeroPrevio = $calleNumeroPrevio ?? '';
?>
<style>
  .v26-dir-paso { display: flex; align-items: center; gap: 6px; font-size: .72rem; font-weight: 800; color: var(--v26-ink-soft); margin: 4px 0 4px; }
  .v26-dir-paso b { display: inline-grid; place-items: center; width: 18px; height: 18px; border-radius: 50%; background: var(--v26-brand-1); color: #fff; font-size: .66rem; }
  .v26-dir-nota { font-size: .7rem; margin-top: 3px; min-height: 1em; }
  .v26-dir-nota.error { color: var(--v26-red); }
  .v26-dir-nota.ok { color: var(--v26-green); }
  .v26-dir-nota.info { color: var(--v26-ink-soft); }
  #mapa-cliente { height: 230px; }
  .v26-buscar-destacado .v26-search input.v26-input { padding-left: 38px; } /* .v26-compacto pisa el padding del ícono */
  .v26-buscar-destacado .v26-search { margin-bottom: 8px; }
</style>

<div class="v26-buscar-destacado">
  <div class="v26-buscar-eyebrow"><i class="bi bi-geo-alt"></i> Dirección y ubicación del cliente</div>

  <div class="v26-dir-paso"><b>1</b> Código postal y colonia</div>
  <div class="v26-grid-2">
    <div class="v26-field">
      <label>Código postal</label>
      <input type="text" id="input-cp" class="v26-input" inputmode="numeric" maxlength="5" autocomplete="postal-code" placeholder="Ej. 20367">
      <div id="nota-cp" class="v26-dir-nota info"></div>
    </div>
    <div class="v26-field">
      <label>Colonia</label>
      <select id="select-colonia" class="v26-select" required disabled>
        <option value="">Escribe el CP</option>
      </select>
    </div>
  </div>
  <div class="v26-search">
    <i class="bi bi-signpost-split"></i>
    <input type="text" id="buscar-colonia" class="v26-input" autocomplete="off" placeholder="¿No sabes el CP? Escribe el nombre de la colonia">
  </div>
  <div id="resultados-colonia"></div>
  <div class="v26-grid-2">
    <div class="v26-field">
      <label>Estado</label>
      <select id="select-estado" class="v26-select" required>
        <option value="">Cargando...</option>
      </select>
    </div>
    <div class="v26-field">
      <label>Municipio</label>
      <select id="select-municipio" class="v26-select" required disabled>
        <option value="">Elige el estado</option>
      </select>
    </div>
  </div>

  <div class="v26-dir-paso"><b>2</b> Busca la calle o pega la ubicación que te compartieron</div>
  <div class="v26-search">
    <i class="bi bi-search"></i>
    <input type="text" id="buscar-direccion" class="v26-input" autocomplete="off" placeholder="Calle y número, ej. Blvd. Diamantes 116">
  </div>
  <div id="resultados-busqueda"></div>
  <div class="v26-search">
    <i class="bi bi-link-45deg"></i>
    <input type="text" id="pegar-ubicacion" class="v26-input" autocomplete="off" placeholder="Pega aquí el link de Google Maps o WhatsApp">
  </div>
  <div id="nota-link" class="v26-dir-nota info"></div>

  <div class="v26-field">
    <label>Ubicación en el mapa <span id="ubicacion-estado" class="v26-ubicacion-badge pendiente"><i class="bi bi-exclamation-circle"></i> obligatoria, aún sin marcar</span></label>
    <div id="mapa-cliente" class="v26-map"></div>
    <div class="v26-map-float v26-tip" id="btn-mi-ubicacion" data-tip="Detecta tu posición GPS y la marca en el mapa"><i class="bi bi-crosshair"></i> Usar mi ubicación</div>
    <div class="v26-dir-nota info"><b>3</b> Toca el mapa en el punto exacto del cliente o arrastra el pin para ajustarlo.</div>
    <input type="hidden" name="lat" id="lat">
    <input type="hidden" name="lng" id="lng">
  </div>
</div>

<div class="v26-field">
  <label>Calle y número <small class="text-muted fw-normal">(se llena solo, pero puedes escribirla o corregirla)</small></label>
  <input type="text" name="calle_numero" id="calle-numero" class="v26-input" value="<?= htmlspecialchars($calleNumeroPrevio) ?>" placeholder="Ej. Blvd. Diamantes 116" required>
</div>
