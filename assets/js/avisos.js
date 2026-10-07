// Campanita de avisos (tabla avisos, ver api/avisos.php). Se agrega sola a
// la barra de arriba (.v26-topbar-right) de las pantallas que incluyen este
// archivo -- vendedor/* y muestras/*. Revisa si hay avisos nuevos cada
// AVISOS_REVISAR_CADA_MS y cada que la pantalla vuelve a quedar visible
// (p. ej. al regresar a la app). Al tocar un aviso se marca como leído y se
// abre lo que trae (la solicitud de muestra, "Mis muestras", etc.).
(function () {
  const AVISOS_REVISAR_CADA_MS = 60000;
  const API = '../api/avisos.php';
  const barra = document.querySelector('.v26-topbar-right');
  if (!barra) return;

  const css = document.createElement('style');
  css.textContent = `
    .v26-avisos { position: relative; flex: none; }
    .v26-avisos-btn { position: relative; }
    .v26-avisos-n {
      position: absolute; top: -3px; right: -4px; min-width: 18px; height: 18px; padding: 0 5px;
      border-radius: 999px; background: #E11D48; color: #fff; font-size: .64rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center; border: 2px solid #fff; line-height: 1;
    }
    .v26-avisos-n[hidden] { display: none; }
    .v26-avisos-panel {
      position: absolute; right: 0; top: calc(100% + 10px); width: min(340px, calc(100vw - 24px));
      max-height: min(70vh, 460px); overflow-y: auto; z-index: 1050;
      background: #fff; border: 1px solid rgba(20,23,31,.08); border-radius: 16px;
      box-shadow: 0 20px 50px -20px rgba(20,23,31,.35);
    }
    .v26-avisos-panel[hidden] { display: none; }
    @media (max-width: 600px) {
      .v26-avisos-panel { position: fixed; left: 12px; right: 12px; top: 64px; width: auto; }
    }
    .v26-avisos-head { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px 8px; }
    .v26-avisos-head strong { font-size: .9rem; }
    .v26-avisos-head button { border: none; background: none; color: #C98800; font-size: .75rem; font-weight: 700; padding: 0; }
    .v26-aviso { display: block; padding: 10px 14px; border-top: 1px solid rgba(20,23,31,.06); color: #14171F; text-decoration: none; }
    .v26-aviso:hover { background: #FAFAF7; color: #14171F; }
    .v26-aviso.nuevo { background: #FFF8E6; }
    .v26-aviso .t { font-size: .82rem; font-weight: 800; display: flex; gap: 6px; align-items: baseline; }
    .v26-aviso.nuevo .t::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: #E8A400; flex: none; transform: translateY(-1px); }
    .v26-aviso .m { font-size: .76rem; color: #4B5563; margin-top: 2px; }
    .v26-aviso .f { font-size: .68rem; color: #9CA3AF; margin-top: 3px; }
    .v26-avisos-vacio { padding: 22px 14px; text-align: center; color: #6B7280; font-size: .82rem; }
  `;
  document.head.appendChild(css);

  const caja = document.createElement('div');
  caja.className = 'v26-avisos';
  caja.innerHTML = `
    <button type="button" class="v26-icon-btn v26-avisos-btn" aria-label="Avisos" title="Avisos">
      <i class="bi bi-bell"></i><span class="v26-avisos-n" hidden>0</span>
    </button>
    <div class="v26-avisos-panel" hidden>
      <div class="v26-avisos-head"><strong>Avisos</strong><button type="button" class="v26-avisos-todos">Marcar todos como leídos</button></div>
      <div class="v26-avisos-lista"><div class="v26-avisos-vacio">Cargando...</div></div>
    </div>`;
  barra.insertBefore(caja, barra.firstChild);

  const btn = caja.querySelector('.v26-avisos-btn');
  const contador = caja.querySelector('.v26-avisos-n');
  const panel = caja.querySelector('.v26-avisos-panel');
  const lista = caja.querySelector('.v26-avisos-lista');

  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  // Las fechas vienen en UTC (ver assets/js/fecha_utils.js).
  function hace(utc) {
    if (!utc) return '';
    const d = new Date(utc.replace(' ', 'T') + (utc.endsWith('Z') ? '' : 'Z'));
    const min = Math.round((Date.now() - d.getTime()) / 60000);
    if (min < 1) return 'Hace un momento';
    if (min < 60) return `Hace ${min} min`;
    if (min < 24 * 60) return `Hace ${Math.round(min / 60)} h`;
    return d.toLocaleString('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'America/Mexico_City' });
  }

  function pintar(data) {
    const n = data.no_leidos || 0;
    contador.hidden = n === 0;
    contador.textContent = n > 99 ? '99+' : n;
    btn.querySelector('i').className = n ? 'bi bi-bell-fill' : 'bi bi-bell';
    const avisos = data.avisos || [];
    lista.innerHTML = avisos.length
      ? avisos.map(a => `
          <a class="v26-aviso ${a.leido_en ? '' : 'nuevo'}" href="${a.enlace ? '../' + esc(a.enlace) : '#'}" data-id="${a.id}">
            <div class="t">${esc(a.titulo)}</div>
            <div class="m">${esc(a.mensaje)}</div>
            <div class="f">${hace(a.creado_en)}</div>
          </a>`).join('')
      : '<div class="v26-avisos-vacio">No tienes avisos todavía.</div>';
    document.dispatchEvent(new CustomEvent('v26:avisos', { detail: data }));
  }

  async function revisar() {
    try {
      const r = await fetch(API, { cache: 'no-store' });
      if (!r.ok) return;
      const data = await r.json();
      if (data.ok) pintar(data);
    } catch (e) { /* sin red: se reintenta en la siguiente vuelta */ }
  }

  function marcar(fd) {
    return fetch(API, { method: 'POST', body: fd }).catch(() => {});
  }

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    panel.hidden = !panel.hidden;
    if (!panel.hidden) revisar();
  });
  document.addEventListener('click', (e) => { if (!caja.contains(e.target)) panel.hidden = true; });

  caja.querySelector('.v26-avisos-todos').addEventListener('click', async () => {
    const fd = new FormData(); fd.append('accion', 'leer_todos');
    await marcar(fd);
    revisar();
  });

  lista.addEventListener('click', async (e) => {
    const a = e.target.closest('.v26-aviso');
    if (!a) return;
    e.preventDefault();
    const fd = new FormData(); fd.append('accion', 'leer'); fd.append('id', a.dataset.id);
    await marcar(fd);
    const href = a.getAttribute('href');
    if (href && href !== '#') window.location.href = href; else revisar();
  });

  document.addEventListener('visibilitychange', () => { if (!document.hidden) revisar(); });
  setInterval(() => { if (!document.hidden) revisar(); }, AVISOS_REVISAR_CADA_MS);
  revisar();
  window.V26Avisos = { revisar };
})();
