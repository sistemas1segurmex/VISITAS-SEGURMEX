<?php
/**
 * Foto del modelo en grande (Nueva cotización, Solicitar muestra y detalle de
 * la muestra). Adaptado de erp/cotizacion/_foto_zoom.php (22-oct-2026).
 *
 *   <img data-foto-zoom data-titulo="…">   tocar/clic la abre en grande; con
 *                                          mouse, además, vista grande al pasar.
 *   <img data-foto-pop>                    solo la vista al pasar el mouse
 *                                          (fotos de listas donde tocar la fila
 *                                          hace otra cosa, p. ej. agregar).
 *   <button data-foto-zoom-btn data-src="url" data-titulo="…">   la lupa de
 *                                          esas listas: abre en grande SIN que
 *                                          la fila reciba el toque.
 * Helpers JS: fotoZoomHTML(src, titulo, claseImg) y fotoLupaHTML(src, titulo).
 * Se incluye una sola vez por página (antes de los scripts que lo usen).
 */
if (!empty($GLOBALS['__fotoZoom'])) return;
$GLOBALS['__fotoZoom'] = true;
?>
<style>
img[data-foto-zoom] { cursor: zoom-in; }
.fz-wrap { position: relative; display: inline-flex; flex: none; }
.fz-lupa {
  position: absolute; right: -10px; bottom: -10px; width: 26px; height: 26px; border-radius: 50%;
  border: 1.5px solid #fff; background: #14171F; color: #fff; font-size: .8rem;
  display: inline-flex; align-items: center; justify-content: center; padding: 0;
  box-shadow: 0 3px 8px -2px rgba(20,23,31,.45); z-index: 2;
}
.fz-lupa::after { content: ''; position: absolute; inset: -5px; } /* área de toque ~36 px, sin tapar la foto */
.fz-lupa:focus-visible { outline: 2px solid #E8A400; outline-offset: 2px; }
@media (hover: hover) { .fz-lupa:hover { background: #C98800; } }
.fz-pop { position: fixed; z-index: 3000; width: 300px; height: 300px; padding: 10px; background: #fff; border: 1px solid #E5E7EB; border-radius: 14px; box-shadow: 0 18px 40px -12px rgba(17,24,39,.35); pointer-events: none; display: none; }
.fz-pop img { width: 100%; height: 100%; object-fit: contain; }
.fz-modal { position: fixed; inset: 0; z-index: 3050; background: rgba(17,24,39,.78); display: none; align-items: center; justify-content: center; flex-direction: column; gap: 12px; padding: 20px; cursor: zoom-out; }
.fz-modal img { max-width: min(92vw, 640px); max-height: 74vh; object-fit: contain; background: #fff; border-radius: 16px; padding: 14px; }
.fz-modal .fz-tit { color: #fff; font-weight: 700; font-size: .95rem; text-align: center; max-width: 640px; }
.fz-modal .fz-cerrar { position: absolute; top: max(14px, env(safe-area-inset-top)); right: 16px; width: 44px; height: 44px; border-radius: 50%; border: none; background: rgba(255,255,255,.18); color: #fff; font-size: 1.2rem; }
</style>
<div class="fz-pop" id="fzPop"><img alt=""></div>
<div class="fz-modal" id="fzModal" role="dialog" aria-modal="true" aria-label="Foto del modelo">
  <button type="button" class="fz-cerrar" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
  <img alt=""><div class="fz-tit"></div>
</div>
<script>
(function () {
  const pop = document.getElementById('fzPop'), popImg = pop.querySelector('img');
  const modal = document.getElementById('fzModal'), modalImg = modal.querySelector('img'), modalTit = modal.querySelector('.fz-tit');
  const conMouse = window.matchMedia('(hover: hover) and (pointer: fine)');
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  // Helpers para las páginas que arman su HTML en JS.
  window.fotoLupaHTML = (src, titulo) =>
    `<button type="button" class="fz-lupa" data-foto-zoom-btn data-src="${esc(src)}" data-titulo="${esc(titulo)}" aria-label="Ver foto en grande${titulo ? ': ' + esc(titulo) : ''}"><i class="bi bi-zoom-in"></i></button>`;

  function mostrarPop(img) {
    if (!conMouse.matches || modal.style.display === 'flex') return;
    popImg.src = img.dataset.src || img.currentSrc || img.src;
    const r = img.getBoundingClientRect(), W = 300, M = 12;
    let x = r.right + M; if (x + W > innerWidth - M) x = r.left - W - M; if (x < M) x = M;
    let y = r.top + r.height / 2 - W / 2; y = Math.max(M, Math.min(y, innerHeight - W - M));
    pop.style.left = x + 'px'; pop.style.top = y + 'px'; pop.style.display = 'block';
  }
  function ocultarPop() { pop.style.display = 'none'; }
  function abrir(src, titulo) {
    ocultarPop();
    modalImg.src = src;
    modalTit.textContent = titulo || '';
    modal.style.display = 'flex';
    modal.querySelector('.fz-cerrar').focus({ preventScroll: true });
  }
  function cerrar() { modal.style.display = 'none'; }

  const CON_POP = 'img[data-foto-zoom], img[data-foto-pop], [data-foto-zoom-btn]';
  document.addEventListener('mouseover', (e) => { const i = e.target.closest && e.target.closest(CON_POP); if (i) mostrarPop(i); });
  document.addEventListener('mouseout', (e) => { if (e.target.closest && e.target.closest(CON_POP)) ocultarPop(); });
  // En fase de captura: la lupa y la foto no deben llegar al contenedor
  // (que agregaría el modelo o elegiría el estilo).
  document.addEventListener('click', (e) => {
    if (!e.target.closest) return;
    const b = e.target.closest('[data-foto-zoom-btn]');
    const i = !b && e.target.closest('img[data-foto-zoom]');
    if (!b && !i) return;
    e.preventDefault(); e.stopPropagation();
    if (b) abrir(b.dataset.src, b.dataset.titulo);
    else abrir(i.currentSrc || i.src, i.dataset.titulo);
  }, true);
  // Enter/espacio en la lupa: que no lo tome la fila como "agregar".
  document.addEventListener('keydown', (e) => {
    if (modal.style.display === 'flex' && e.key === 'Escape') { e.stopPropagation(); cerrar(); return; }
    if ((e.key === 'Enter' || e.key === ' ') && e.target.closest && e.target.closest('[data-foto-zoom-btn]')) e.stopPropagation();
  }, true);
  modal.addEventListener('click', cerrar);
  window.addEventListener('scroll', ocultarPop, { passive: true, capture: true });
})();
</script>
