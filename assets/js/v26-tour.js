// Motor genérico de recorrido guiado tipo "spotlight" para las pantallas del
// vendedor -- ver la propuesta "Opción A" de onboarding. Se llama una vez
// por pantalla con V26Tour.iniciar(pasos, opciones):
//
//   V26Tour.iniciar([
//     { selector: '[data-tour="perfil"]', texto: 'Aquí ves tu perfil...' },
//     { selector: '[data-tour="tabbar"]', texto: 'Desde aquí te mueves...' },
//   ], {
//     storageKey: 'v26_tour_inicio',
//     saludoTitulo: '¡Bienvenida, Laura!',
//     saludoTexto: 'Te enseñamos en unos pasos rápidos dónde está todo.',
//     finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
//   });
//
// Se muestra solo la primera vez (localStorage[storageKey]); para volver a
// verlo a propósito, usar V26Tour.reiniciar(pasos, opciones) -- pensado para
// un botón "?" fijo en el header de cada pantalla.
const V26Tour = (function () {
  let raiz = null;
  let pasosActivos = [];
  let idx = 0;
  let opcionesActuales = {};

  function construirDOM() {
    if (raiz) return;
    raiz = document.createElement('div');
    raiz.innerHTML = `
      <div class="v26-tour-start" id="v26TourStart">
        <div class="box">
          <div class="ico"><i class="bi bi-question-lg"></i></div>
          <h3 id="v26TourStartTitulo"></h3>
          <p id="v26TourStartTexto"></p>
          <button type="button" class="go" id="v26TourBtnIniciar">Empezar recorrido</button>
          <button type="button" class="later" id="v26TourBtnOmitirInicio">Ahora no</button>
        </div>
      </div>
      <div class="v26-tour-cutout" id="v26TourCutout"></div>
      <div class="v26-tour-bubble" id="v26TourBubble">
        <div class="step-count" id="v26TourCount"></div>
        <p id="v26TourTexto"></p>
        <div class="row">
          <div class="dots" id="v26TourDots"></div>
          <div style="display:flex;gap:2px;align-items:center;">
            <button type="button" class="btn-skip" id="v26TourBtnOmitir">Omitir</button>
            <button type="button" class="btn-next" id="v26TourBtnSiguiente">Siguiente</button>
          </div>
        </div>
      </div>
      <div class="v26-tour-final" id="v26TourFinal">
        <div class="box">
          <div class="ico"><i class="bi bi-check-lg"></i></div>
          <h3>¡Listo!</h3>
          <p id="v26TourFinalTexto"></p>
          <button type="button" class="go" id="v26TourBtnCerrar">Entendido</button>
        </div>
      </div>`;
    document.body.appendChild(raiz);

    document.getElementById('v26TourBtnIniciar').addEventListener('click', () => mostrarPaso(0));
    document.getElementById('v26TourBtnOmitirInicio').addEventListener('click', () => { ocultar('v26TourStart'); marcarVisto(); });
    document.getElementById('v26TourBtnSiguiente').addEventListener('click', () => {
      if (idx < pasosActivos.length - 1) mostrarPaso(idx + 1); else mostrarFinal();
    });
    document.getElementById('v26TourBtnOmitir').addEventListener('click', mostrarFinal);
    document.getElementById('v26TourBtnCerrar').addEventListener('click', () => ocultar('v26TourFinal'));
    window.addEventListener('resize', () => {
      if (document.getElementById('v26TourBubble').classList.contains('show')) posicionar();
    });
  }

  function mostrar(id) { document.getElementById(id).classList.add('show'); }
  function ocultar(id) { document.getElementById(id).classList.remove('show'); }
  function marcarVisto() { if (opcionesActuales.storageKey) { try { localStorage.setItem(opcionesActuales.storageKey, '1'); } catch (e) {} } }

  function posicionar() {
    const target = document.querySelector(pasosActivos[idx].selector);
    if (!target) { mostrarFinal(); return; }
    const r = target.getBoundingClientRect();
    const pad = 8;
    const top = r.top - pad, left = r.left - pad, w = r.width + pad * 2, h = r.height + pad * 2;

    const cutout = document.getElementById('v26TourCutout');
    cutout.style.top = top + 'px'; cutout.style.left = left + 'px';
    cutout.style.width = w + 'px'; cutout.style.height = h + 'px';
    mostrar('v26TourCutout');

    const bubble = document.getElementById('v26TourBubble');
    const vh = window.innerHeight, vw = window.innerWidth;
    let bt = top + h + 10;
    if (bt + 150 > vh) bt = Math.max(10, top - 160);
    let bl = Math.min(Math.max(10, left), vw - 256);
    bubble.style.top = bt + 'px'; bubble.style.left = bl + 'px';
    mostrar('v26TourBubble');

    document.getElementById('v26TourCount').textContent = `Paso ${idx + 1} de ${pasosActivos.length}`;
    document.getElementById('v26TourTexto').textContent = pasosActivos[idx].texto;
    document.getElementById('v26TourDots').innerHTML = pasosActivos.map((_, i) => `<span class="${i === idx ? 'on' : ''}"></span>`).join('');
    document.getElementById('v26TourBtnSiguiente').textContent = idx === pasosActivos.length - 1 ? 'Terminar' : 'Siguiente';
  }

  function mostrarPaso(i) {
    idx = i;
    ocultar('v26TourStart');
    const target = document.querySelector(pasosActivos[idx].selector);
    if (!target) { mostrarFinal(); return; }
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
    setTimeout(posicionar, 260);
  }

  function mostrarFinal() {
    ocultar('v26TourCutout');
    ocultar('v26TourBubble');
    document.getElementById('v26TourFinalTexto').textContent = opcionesActuales.finalTexto || 'Repite este recorrido cuando quieras.';
    mostrar('v26TourFinal');
    marcarVisto();
    if (typeof opcionesActuales.alTerminar === 'function') opcionesActuales.alTerminar();
  }

  function iniciar(pasos, opciones, forzar) {
    pasosActivos = pasos.filter(p => document.querySelector(p.selector));
    opcionesActuales = opciones || {};
    if (pasosActivos.length === 0) return;

    construirDOM();

    let yaVisto = false;
    if (opcionesActuales.storageKey && !forzar) {
      try { yaVisto = !!localStorage.getItem(opcionesActuales.storageKey); } catch (e) {}
    }
    if (yaVisto) return;

    document.getElementById('v26TourStartTitulo').textContent = opcionesActuales.saludoTitulo || '¡Bienvenida!';
    document.getElementById('v26TourStartTexto').textContent = opcionesActuales.saludoTexto || 'Te enseñamos rápido dónde está todo.';
    mostrar('v26TourStart');
  }

  function reiniciar(pasos, opciones) { iniciar(pasos, opciones, true); }

  return { iniciar, reiniciar };
})();
