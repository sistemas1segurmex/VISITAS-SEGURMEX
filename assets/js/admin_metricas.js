// Métricas del equipo (admin/metricas.php). Datos: api/admin_metricas.php.
// HOY (YYYY-MM-DD, hora de México) lo define la página.

const est = { p: 'semana', desde: '', hasta: '', vendedor: 0, tab: 'visitas', orden: { col: null, asc: false } };
let datos = null;
let grafica = null;

// ── Utilidades ───────────────────────────────────────────────────────────
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const num = n => Number(n || 0).toLocaleString('es-MX');
const money = n => '$' + Number(n || 0).toLocaleString('es-MX', { maximumFractionDigits: 0 });
const pct = v => v == null ? '—' : Math.round(v * 100) + ' %';
const div = (a, b) => b > 0 ? a / b : null;
const css = v => getComputedStyle(document.querySelector('.mt-wrap')).getPropertyValue(v).trim();

function fechaIso(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; }
function fechaDe(iso) { return new Date(iso + 'T12:00:00'); }
function fechaCorta(iso) { return fechaDe(iso).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' }).replace('.', ''); }
function horaDeMin(min) {
  if (min == null) return '—';
  const h = Math.floor(min / 60), m = Math.round(min % 60);
  return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'a.m.' : 'p.m.'}`;
}
function duracion(min) {
  if (min == null) return '—';
  min = Math.round(min);
  return min >= 60 ? `${Math.floor(min / 60)} h ${min % 60 ? (min % 60) + ' min' : ''}`.trim() : `${min} min`;
}
function iniciales(n) { const p = String(n || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p[1] ? p[1][0] : '')).toUpperCase(); }

function calcularPeriodo(p) {
  const hoy = fechaDe(HOY);
  const lunes = new Date(hoy); lunes.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
  if (p === 'semana') return [fechaIso(lunes), HOY];
  if (p === 'semana_ant') {
    const ini = new Date(lunes); ini.setDate(lunes.getDate() - 7);
    const fin = new Date(lunes); fin.setDate(lunes.getDate() - 1);
    return [fechaIso(ini), fechaIso(fin)];
  }
  if (p === 'mes') return [HOY.slice(0, 8) + '01', HOY];
  if (p === 'mes_ant') {
    const ini = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
    const fin = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
    return [fechaIso(ini), fechaIso(fin)];
  }
  if (p === '30') { const ini = new Date(hoy); ini.setDate(hoy.getDate() - 29); return [fechaIso(ini), HOY]; }
  return [est.desde || HOY, est.hasta || HOY];
}

// Métricas derivadas de una fila (vendedor o totales).
function deriv(m) {
  const cotVivas = m.cotizaciones - m.cot_canceladas;
  const alertas = m.alertas ? Object.values(m.alertas).reduce((a, b) => a + b, 0) : 0;
  return {
    ...m,
    cumplimiento: div(m.completadas, m.completadas + m.no_realizadas + m.canceladas),
    pct_verif: div(m.verificadas, m.completadas),
    dur_prom: div(m.min_visita_total, m.visitas_con_duracion),
    conversion: div(m.aceptadas, cotVivas),
    ticket: div(m.monto, cotVivas),
    pct_muy_cot: div(m.muy_interesados_cotizados, m.muy_interesados),
    visitas_dia: div(m.completadas, m.dias_activos),
    jornada_prom: div(m.min_jornada_total, m.dias_activos),
    pct_jornada_visita: m.min_jornada_total > 0 ? Math.min(1, m.min_visita_total / m.min_jornada_total) : null,
    alertas_total: alertas,
  };
}

// ── Carga ────────────────────────────────────────────────────────────────
function leerUrl() {
  const q = new URLSearchParams(location.search);
  if (q.get('p')) est.p = q.get('p');
  if (/^\d{4}-\d{2}-\d{2}$/.test(q.get('desde') || '')) est.desde = q.get('desde');
  if (/^\d{4}-\d{2}-\d{2}$/.test(q.get('hasta') || '')) est.hasta = q.get('hasta');
  est.vendedor = parseInt(q.get('vendedor')) || 0;
}
function guardarUrl() {
  const q = new URLSearchParams();
  if (est.p !== 'semana') q.set('p', est.p);
  if (est.p === 'rango') { q.set('desde', est.desde); q.set('hasta', est.hasta); }
  if (est.vendedor) q.set('vendedor', est.vendedor);
  history.replaceState(null, '', location.pathname + (q.toString() ? '?' + q : ''));
}

async function cargar() {
  [est.desde, est.hasta] = calcularPeriodo(est.p);
  document.querySelectorAll('.mt-periodos button').forEach(b => b.classList.toggle('active', b.dataset.p === est.p));
  document.getElementById('rango').hidden = est.p !== 'rango';
  document.getElementById('desde').value = est.desde;
  document.getElementById('hasta').value = est.hasta;
  guardarUrl();
  document.getElementById('periodo-txt').textContent = 'Calculando...';
  try {
    const q = new URLSearchParams({ desde: est.desde, hasta: est.hasta, vendedor: est.vendedor });
    const res = await fetch('../api/admin_metricas.php?' + q);
    datos = await res.json();
    if (!datos.ok) throw new Error(datos.error);
    pintarTodo();
  } catch (e) {
    document.getElementById('periodo-txt').innerHTML = `<span class="text-danger">${esc(e.message || 'No se pudieron cargar las métricas.')}</span>`;
  }
}

function pintarTodo() {
  // Ya no hay selector: el vendedor se elige tocándolo en el ranking.
  const elegido = est.vendedor ? datos.por_vendedor.find(v => v.id === est.vendedor) : null;
  if (est.vendedor && !elegido) est.vendedor = 0;

  const p = datos.periodo, a = datos.anterior;
  document.getElementById('periodo-txt').textContent =
    `Del ${fechaCorta(p.desde)} al ${fechaCorta(p.hasta)} (${p.dias} día${p.dias === 1 ? '' : 's'}). Se compara contra ${fechaCorta(a.desde)} – ${fechaCorta(a.hasta)}.`;

  pintarTabla(); // primero: calcula el lugar de cada vendedor
  pintarCabResumen(elegido);
  pintarKpis();
  pintarSerie();
  pintarInteres();
  pintarMotivos();
  pintarCalor();
  pintarVenta();
  pintarEstados();
  pintarEmbudo();
}

// ── Números clave ────────────────────────────────────────────────────────
function delta(actual, anterior, tipo) {
  if (actual == null || anterior == null) return '<span class="mt-delta igual">sin comparación</span>';
  let txt, dir;
  if (tipo === 'pct') {
    const d = Math.round((actual - anterior) * 100);
    dir = d > 0 ? 'sube' : d < 0 ? 'baja' : 'igual';
    txt = d === 0 ? 'igual' : `${Math.abs(d)} pts`;
  } else {
    if (anterior === 0) return actual > 0 ? '<span class="mt-delta sube"><i class="bi bi-arrow-up-short"></i>antes 0</span>' : '<span class="mt-delta igual">igual</span>';
    const d = Math.round((actual - anterior) / anterior * 100);
    dir = d > 0 ? 'sube' : d < 0 ? 'baja' : 'igual';
    txt = d === 0 ? 'igual' : `${Math.abs(d)} %`;
  }
  const ico = dir === 'sube' ? 'bi-arrow-up-short' : dir === 'baja' ? 'bi-arrow-down-short' : 'bi-dash';
  return `<span class="mt-delta ${dir}"><i class="bi ${ico}"></i>${txt}</span>`;
}

// Promedio del equipo para comparar ("equipo 14.7"): mismo criterio que el
// promedio del ranking -- vendedores activos con visitas realizadas.
function promedioEquipo(campo) {
  const vals = datos.por_vendedor.filter(v => v.activo && v.completadas > 0).map(deriv).map(v => v[campo]).filter(v => v != null);
  return vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
}

function pintarKpis() {
  const t = deriv(datos.totales), ta = deriv(datos.totales_ant);
  const eq = (campo, fmt) => est.vendedor ? ` · promedio del equipo ${fmt(promedioEquipo(campo))}` : '';
  const kpis = [
    { ico: 'bi-geo-alt', l: 'Visitas realizadas', v: num(t.completadas), d: delta(t.completadas, ta.completadas), s: `de ${num(t.citas)} citas${eq('completadas', v => v == null ? '—' : v.toLocaleString('es-MX', { maximumFractionDigits: 1 }))}`, tip: 'Citas del periodo que se completaron.' },
    { ico: 'bi-calendar-check', l: 'Citas cumplidas', v: pct(t.cumplimiento), d: delta(t.cumplimiento, ta.cumplimiento, 'pct'), s: `${num(t.no_realizadas)} no realizadas · ${num(t.canceladas)} canceladas${eq('cumplimiento', pct)}`, tip: 'Realizadas ÷ (realizadas + no realizadas + canceladas). Las pendientes no cuentan.' },
    { ico: 'bi-shield-check', l: 'Verificadas por GPS', v: pct(t.pct_verif), d: delta(t.pct_verif, ta.pct_verif, 'pct'), s: `${num(t.verificadas)} de ${num(t.completadas)}${eq('pct_verif', pct)}`, tip: 'Visitas cuya entrada se registró dentro del radio del cliente.' },
    { ico: 'bi-stopwatch', l: 'Duración promedio', v: duracion(t.dur_prom), d: delta(t.dur_prom, ta.dur_prom), s: `por visita${eq('dur_prom', duracion)}`, tip: 'De la foto de entrada a la de salida.' },
    { ico: 'bi-person-plus', l: 'Prospectos nuevos', v: num(t.prospectos_nuevos), d: delta(t.prospectos_nuevos, ta.prospectos_nuevos), s: 'registrados como «Prospecto nuevo»', tip: 'Empresas que el vendedor dio de alta como «Prospecto nuevo» en el periodo. Todavía no compran.' },
    { ico: 'bi-file-earmark-text', l: 'Cotizaciones', v: num(t.cotizaciones), d: delta(t.cotizaciones, ta.cotizaciones), s: `${money(t.monto)} cotizado`, tip: 'Cotizaciones generadas; el monto no incluye canceladas.' },
    { ico: 'bi-trophy', l: 'Cotizaciones aceptadas', v: pct(t.conversion), d: delta(t.conversion, ta.conversion, 'pct'), s: `${num(t.aceptadas)} aceptadas · ${money(t.monto_aceptado)}`, tip: 'Aceptadas ÷ cotizaciones no canceladas del periodo.' },
    { ico: 'bi-fire', l: 'Muy interesados con cotización', v: pct(t.pct_muy_cot), d: delta(t.pct_muy_cot, ta.pct_muy_cot, 'pct'), s: `${num(t.muy_interesados_cotizados)} de ${num(t.muy_interesados)} visitas`, tip: 'De las visitas donde el cliente quedó «Muy interesado», a cuántos ya se les hizo cotización.' },
  ];
  // Agrupados en 3 tarjetas: Visitas / Clientes / Ventas (índices de "kpis")
  // Clientes: lo que el vendedor dio de alta, separado por cómo lo registró.
  kpis.push(
    { ico: 'bi-trophy', l: 'Prospectos ganados', v: num(t.prospectos_ganados), d: delta(t.prospectos_ganados, ta.prospectos_ganados), s: 'prospectos que pasaron a «Convertido»', tip: 'Prospectos que el vendedor movió a la etapa «Convertido» en el periodo. Son clientes nuevos de verdad.' },
    { ico: 'bi-building-check', l: 'Clientes existentes capturados', v: num(t.clientes_capturados), d: delta(t.clientes_capturados, ta.clientes_capturados), s: 'ya eran clientes; se dieron de alta en la app', tip: 'Registrados como «Cliente ya ganado»: clientes que Segurmex ya tenía y que el vendedor apenas capturó en la app. No son clientes nuevos.' },
  );
  const grupos = [
    { cls: 'visitas',  ico: 'bi-geo-alt-fill',        t: 'Visitas',  ks: [0, 1, 2, 3] },
    { cls: 'clientes', ico: 'bi-people-fill',         t: 'Clientes', ks: [4, 8, 9, 7] },
    { cls: 'ventas',   ico: 'bi-file-earmark-text-fill', t: 'Ventas', ks: [5, 6] },
  ];
  document.getElementById('kpis').innerHTML = grupos.map(g => `
    <section class="mt-grupo mt-grupo--${g.cls}">
      <div class="mt-grupo-cab"><span class="ico"><i class="bi ${g.ico}"></i></span>${g.t}</div>
      ${g.ks.map(i => kpis[i]).map(k => `
        <div class="mt-kpi" data-tip="${esc(k.tip)}">
          <div class="fila">
            <div class="l"><i class="bi ${k.ico}"></i>${k.l}</div>
            <div class="v">${k.v}</div>
          </div>
          <div class="s">${k.d} · ${k.s}</div>
        </div>`).join('')}
    </section>`).join('');
}

// ── Tendencia diaria ─────────────────────────────────────────────────────
function pintarSerie() {
  const s = datos.serie;
  const etiquetas = s.map(r => fechaDe(r.dia).toLocaleDateString('es-MX', s.length > 14 ? { day: 'numeric', month: 'short' } : { weekday: 'short', day: 'numeric' }).replace('.', ''));
  const ds = (label, campo, color) => ({
    label, data: s.map(r => r[campo]), borderColor: color, backgroundColor: color,
    borderWidth: 2, pointRadius: s.length > 20 ? 0 : 3, pointHoverRadius: 5, tension: .25,
  });
  const cfg = {
    type: 'line',
    data: { labels: etiquetas, datasets: [ds('Visitas realizadas', 'visitas', css('--mt-s1')), ds('Cotizaciones', 'cotizaciones', css('--mt-s2'))] },
    options: {
      responsive: true, maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { display: false }, tooltip: { backgroundColor: '#14171F', padding: 10, boxPadding: 4 } },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#6B7280', maxRotation: 0, autoSkipPadding: 12 } },
        y: { beginAtZero: true, ticks: { precision: 0, color: '#6B7280' }, grid: { color: 'rgba(20,23,31,.07)' }, border: { display: false } },
      },
    },
  };
  if (grafica) grafica.destroy();
  grafica = new Chart(document.getElementById('ch-serie'), cfg);
  document.getElementById('tabla-serie').innerHTML = `<table><thead><tr><th>Día</th><th>Visitas</th><th>Cotizaciones</th></tr></thead><tbody>${
    s.map(r => `<tr><td>${fechaCorta(r.dia)}</td><td>${r.visitas}</td><td>${r.cotizaciones}</td></tr>`).join('')}</tbody></table>`;
}

// ── Tabla comparativa ────────────────────────────────────────────────────
// sem: 'alto' (más es mejor) o 'bajo' (menos es mejor) para el semáforo.
const COLUMNAS = {
  visitas: [
    { k: 'citas', t: 'Citas', f: num },
    { k: 'completadas', t: 'Realizadas', f: num, sem: 'alto' },
    { k: 'no_realizadas', t: 'No realizadas', f: num },
    { k: 'canceladas', t: 'Canceladas', f: num },
    { k: 'pendientes', t: 'Pendientes', f: num },
    { k: 'cumplimiento', t: 'Cumplidas', f: pct, sem: 'alto' },
    { k: 'pct_verif', t: 'Verif. GPS', f: pct, sem: 'alto' },
    { k: 'dur_prom', t: 'Duración prom.', f: duracion },
  ],
  ventas: [
    { k: 'prospectos_nuevos', t: 'Prospectos nuevos', f: num, sem: 'alto' },
    { k: 'prospectos_ganados', t: 'Prospectos ganados', f: num, sem: 'alto' },
    { k: 'clientes_capturados', t: 'Clientes capturados', f: num },
    { k: 'cotizaciones', t: 'Cotizaciones', f: num, sem: 'alto' },
    { k: 'monto', t: 'Monto', f: money },
    { k: 'aceptadas', t: 'Aceptadas', f: num },
    { k: 'conversion', t: '% aceptadas', f: pct },
    { k: 'ticket', t: 'Ticket prom.', f: v => v == null ? '—' : money(v) },
    { k: 'pct_muy_cot', t: 'Muy int. cotizados', f: pct },
  ],
  campo: [
    { k: 'dias_activos', t: 'Días con GPS', f: num },
    { k: 'visitas_dia', t: 'Visitas por día', f: v => v == null ? '—' : v.toFixed(1), sem: 'alto' },
    { k: 'primera_min_prom', t: 'Empieza', f: horaDeMin },
    { k: 'ultima_min_prom', t: 'Termina', f: horaDeMin },
    { k: 'jornada_prom', t: 'Jornada prom.', f: duracion },
    { k: 'pct_jornada_visita', t: '% en visitas', f: pct },
    { k: 'km', t: 'Km aprox.', f: v => num(Math.round(v)) },
    { k: 'paradas', t: 'Paradas', f: num },
  ],
  calidad: [
    { k: 'a_visita_corta', t: 'Visitas muy cortas', f: num },
    { k: 'a_visita_sin_cerrar', t: 'Sin cerrar', f: num },
    { k: 'a_reprogramaciones', t: 'Reprogramadas', f: num },
    { k: 'a_cita_perdida', t: 'Citas perdidas', f: num },
    { k: 'a_ubicacion_por_revisar', t: 'Pin movido', f: num },
    { k: 'a_sin_gps', t: 'Sin GPS', f: num },
    { k: 'alertas_total', t: 'Total alertas', f: num, sem: 'bajo' },
  ],
};

function semaforo(valor, prom, mejor) {
  if (valor == null || prom == null || prom <= 0) return '';
  let r = valor / prom;
  if (mejor === 'bajo') r = valor === 0 ? 2 : prom / valor;
  const [cls, ico, txt] = r >= 1 ? ['ok', 'bi-check-circle-fill', 'Arriba del promedio'] : r >= .7 ? ['warn', 'bi-dash-circle-fill', 'Cerca del promedio'] : ['mal', 'bi-exclamation-circle-fill', 'Abajo del promedio'];
  return ` <span class="mt-sem ${cls}" data-tip="${txt} del equipo"><i class="bi ${ico}"></i></span>`;
}

// Ranking (oct-2026): por defecto la tabla se ordena por quién cumplió más:
//   1) más visitas realizadas, 2) mayor % de citas cumplidas, 3) mayor % verificadas por GPS.
// Quien no tuvo visitas realizadas en el periodo no recibe lugar: va al final
// en gris, con la razón ("1 cita pendiente", "sin citas"), y no entra al promedio
// ni lleva semáforo (antes salía en rojo como si hubiera fallado).
function compararRanking(a, b) {
  const n = v => (v == null ? -1 : v);
  return (b.completadas - a.completadas) || (n(b.cumplimiento) - n(a.cumplimiento)) || (n(b.pct_verif) - n(a.pct_verif));
}
function motivoSinLugar(f) {
  if (!f.citas) return 'Sin citas en el periodo';
  const partes = [];
  if (f.pendientes) partes.push(`${f.pendientes} pendiente${f.pendientes === 1 ? '' : 's'}`);
  if (f.no_realizadas) partes.push(`${f.no_realizadas} no realizada${f.no_realizadas === 1 ? '' : 's'}`);
  if (f.canceladas) partes.push(`${f.canceladas} cancelada${f.canceladas === 1 ? '' : 's'}`);
  return `${f.citas} cita${f.citas === 1 ? '' : 's'}, ninguna realizada${partes.length ? ' (' + partes.join(', ') + ')' : ''}`;
}

// Encabezado del bloque "Resumen": de quién son los números de abajo.
const lugarDe = {}; // id -> lugar en el ranking (lo llena pintarTabla)
function pintarCabResumen(elegido) {
  const titulo = document.getElementById('resumen-titulo');
  const cab = document.getElementById('resumen-cab');
  if (!elegido) {
    titulo.textContent = 'Resumen de todo el equipo';
    cab.hidden = true;
    return;
  }
  const lugar = lugarDe[elegido.id];
  titulo.textContent = `Resumen de ${elegido.nombre}${lugar ? ` · ${lugar}° lugar` : ' · sin lugar en el ranking'}`;
  document.getElementById('resumen-nota').innerHTML = `Todo lo de aquí abajo es <b>solo de ${esc(elegido.nombre)}</b>. Para ver el resumen del equipo completo, toca «Ver todo el equipo».`;
  cab.hidden = false;
}
// Promedios con un decimal ("18.7", no "18.667", que se lee como dieciocho mil).
const fmtProm = (c, v) => (c.f === num && !Number.isInteger(v)) ? v.toLocaleString('es-MX', { maximumFractionDigits: 1 }) : c.f(v);

function pintarTabla() {
  const cols = COLUMNAS[est.tab];
  const filas = datos.por_vendedor.map(v => {
    const d = deriv(v);
    Object.entries(v.alertas || {}).forEach(([k, n]) => { d['a_' + k] = n; });
    cols.forEach(c => { if (c.k.startsWith('a_') && d[c.k] == null) d[c.k] = 0; });
    return d;
  });
  const conLugar = filas.filter(f => f.completadas > 0).sort(compararRanking);
  const sinLugar = filas.filter(f => !(f.completadas > 0)).sort((a, b) => (b.citas - a.citas) || a.nombre.localeCompare(b.nombre));
  // Lugar: empates exactos en los 3 criterios comparten lugar.
  conLugar.forEach((f, i) => { f.lugar = i && compararRanking(conLugar[i - 1], f) === 0 ? conLugar[i - 1].lugar : i + 1; });
  Object.keys(lugarDe).forEach(k => delete lugarDe[k]);
  conLugar.forEach(f => { lugarDe[f.id] = f.lugar; });

  // Promedio y semáforo: solo vendedores activos con visitas realizadas
  // (igual que los números de arriba, que tampoco cuentan a los inactivos).
  const prom = {};
  cols.forEach(c => {
    const vals = conLugar.filter(f => f.activo).map(f => f[c.k]).filter(v => v != null);
    prom[c.k] = vals.length ? vals.reduce((a, b) => a + b, 0) / vals.length : null;
  });

  const ordenManual = est.orden.col && cols.some(c => c.k === est.orden.col);
  let cuerpo;
  const avatar = v => v.foto_path ? `<img class="v26-al-avatar" src="../${esc(v.foto_path)}" alt="">` : `<span class="v26-al-avatar">${esc(iniciales(v.nombre))}</span>`;
  const lugarHtml = f => f.lugar ? `<span class="mt-lugar mt-lugar--${Math.min(f.lugar, 4)}">${f.lugar}°</span>` : '';
  const fila = (f, gris) => `
      <tr data-v="${f.id}" class="${f.id === est.vendedor ? 'sel' : ''} ${gris ? 'mt-sin-lugar' : ''} ${f.lugar === 1 && !ordenManual ? 'mt-primero' : ''}">
        <td>${lugarHtml(f)}</td>
        <td><span class="mt-quien">${avatar(f)}<span><b class="mt-nombre">${esc(f.nombre)}</b><a class="mt-ir" href="vendedor_detalle.php?id=${f.id}" title="Abrir el detalle de ${esc(f.nombre)}" aria-label="Abrir detalle"><i class="bi bi-box-arrow-up-right"></i></a>${f.id === est.vendedor ? '<span class="mt-viendo">Viendo</span>' : ''}${gris ? `<span class="mt-motivo">${esc(motivoSinLugar(f))}</span>` : ''}</span></span></td>
        ${cols.map(c => `<td>${c.f(f[c.k])}${c.sem && !gris ? semaforo(f[c.k], prom[c.k], c.sem) : ''}</td>`).join('')}
      </tr>`;
  if (ordenManual) {
    const k = est.orden.col;
    const todas = [...conLugar, ...sinLugar].sort((a, b) => ((a[k] ?? -Infinity) - (b[k] ?? -Infinity)) * (est.orden.asc ? 1 : -1));
    cuerpo = todas.map(f => fila(f, !f.lugar)).join('');
  } else {
    cuerpo = conLugar.map(f => fila(f, false)).join('')
      + (sinLugar.length ? `<tr class="mt-grupo-sep"><td colspan="${cols.length + 2}">Sin visitas realizadas en el periodo · no entran al ranking ni al promedio</td></tr>` + sinLugar.map(f => fila(f, true)).join('') : '');
  }
  document.getElementById('btn-ranking').hidden = !ordenManual;
  document.getElementById('tabla-vend').innerHTML = `
    <thead><tr><th data-col="ranking">Lugar</th><th data-col="nombre">Vendedor</th>${cols.map(c => `<th data-col="${c.k}" class="${est.orden.col === c.k ? 'orden' + (est.orden.asc ? ' asc' : '') : ''}">${c.t}</th>`).join('')}</tr></thead>
    <tbody>${cuerpo || `<tr><td colspan="${cols.length + 2}" class="mt-muted">Sin vendedores.</td></tr>`}</tbody>
    <tfoot><tr><td></td><td>Promedio (vendedores activos con visitas)</td>${cols.map(c => `<td>${prom[c.k] == null ? '—' : fmtProm(c, prom[c.k])}</td>`).join('')}</tr></tfoot>`;
}

// ── Barras horizontales (HTML) ───────────────────────────────────────────
function barras(id, items, vacio, sufijo = '') {
  const cont = document.getElementById(id);
  const max = Math.max(0, ...items.map(i => i.n));
  if (!items.length || max === 0) { cont.innerHTML = `<div class="mt-vacio">${vacio}</div>`; return; }
  const total = items.reduce((a, i) => a + i.n, 0);
  cont.innerHTML = items.map(i => `
    <div class="mt-barra" data-tip="${esc(i.et)}: ${num(i.n)}${sufijo}${i.extra ? ' · ' + esc(i.extra) : ''} (${Math.round(i.n / total * 100)} %)">
      <span class="et">${esc(i.et)}</span>
      <span class="pista"><span class="relleno" style="width:${(i.n / max * 100).toFixed(1)}%;background:${i.color || 'var(--mt-seq-4)'}"></span></span>
      <span class="n">${num(i.n)}${sufijo}</span>
    </div>`).join('');
}

function pintarInteres() {
  const orden = [
    ['muy_interesado', 'Muy interesado', 'var(--mt-seq-5)'],
    ['interesado', 'Interesado', 'var(--mt-seq-4)'],
    ['medio', 'Interés medio', 'var(--mt-seq-3)'],
    ['bajo', 'Poco interesado', 'var(--mt-seq-2)'],
    ['sin_dato', 'Sin marcar', '#C9C6BD'],
  ];
  barras('interes', orden.map(([k, et, color]) => ({ et, n: +(datos.interes[k] || 0), color })).filter(i => i.n > 0), 'No hay visitas realizadas en el periodo.');
}
function pintarMotivos() {
  barras('motivos', datos.motivos.map(m => ({ et: m.motivo, n: m.n })), 'Todas las citas se realizaron o siguen pendientes.');
}
function pintarEstados() {
  barras('estados', datos.estados.map(e => ({ et: e.estado, n: e.n })), 'No hay visitas realizadas en el periodo.');
}

// ── Mapa de calor día × hora ─────────────────────────────────────────────
function pintarCalor() {
  const cont = document.getElementById('calor');
  const c = datos.calor;
  if (!c.length) { cont.style.gridTemplateColumns = '1fr'; cont.style.minWidth = '0'; cont.innerHTML = '<div class="mt-vacio">No hay visitas realizadas en el periodo.</div>'; return; }
  cont.style.minWidth = '';
  const horas = c.map(x => x.hora);
  const hIni = Math.min(8, ...horas), hFin = Math.max(19, ...horas);
  const diasSem = c.some(x => x.dow === 7) ? 7 : 6;
  const nombres = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
  const mapa = {}; c.forEach(x => { mapa[x.dow + '-' + x.hora] = x.n; });
  const max = Math.max(...c.map(x => x.n));
  const pasos = ['--mt-seq-1', '--mt-seq-2', '--mt-seq-3', '--mt-seq-4', '--mt-seq-5'];
  const hora = h => `${h % 12 || 12} ${h < 12 ? 'a.m.' : 'p.m.'}`;
  cont.style.gridTemplateColumns = `38px repeat(${hFin - hIni + 1}, minmax(26px, 1fr))`;
  let html = '<span></span>';
  for (let h = hIni; h <= hFin; h++) html += `<span class="h">${h % 12 || 12}${h < 12 ? 'a' : 'p'}</span>`;
  for (let d = 1; d <= diasSem; d++) {
    html += `<span class="d">${nombres[d - 1]}</span>`;
    for (let h = hIni; h <= hFin; h++) {
      const n = mapa[d + '-' + h] || 0;
      if (!n) { html += `<span class="c" data-tip="${nombres[d - 1]} ${hora(h)}: sin visitas"></span>`; continue; }
      const i = Math.min(4, Math.floor(n / max * 4.999));
      html += `<span class="c" style="background:var(${pasos[i]});color:${i >= 3 ? '#fff' : '#14171F'}" data-tip="${nombres[d - 1]} ${hora(h)}: ${n} visita${n === 1 ? '' : 's'}">${n}</span>`;
    }
  }
  cont.innerHTML = html;
}

// ── De la visita a la venta ──────────────────────────────────────────────
function pintarVenta() {
  const t = deriv(datos.totales), dv = datos.de_visita;
  const mini = (v, l) => `<div class="mt-mini"><div class="v">${v}</div><div class="l">${l}</div></div>`;
  document.getElementById('venta').innerHTML =
    mini(t.ticket == null ? '—' : money(t.ticket), 'Ticket promedio') +
    mini(num(dv.n), 'Cotizaciones que salieron de una visita') +
    mini(dv.dias_prom == null ? '—' : `${dv.dias_prom} días`, 'De la visita a la cotización (promedio)') +
    mini(pct(t.pct_muy_cot), '«Muy interesados» que ya tienen cotización');
  barras('modelos', datos.top_modelos.map(m => ({ et: m.clave + (m.nombre ? ' · ' + m.nombre : ''), n: m.pares, extra: `en ${m.cotizaciones} cotización${m.cotizaciones === 1 ? '' : 'es'}` })), 'No hay cotizaciones en el periodo.', ' pares');
}

// ── Embudo por vendedor ──────────────────────────────────────────────────
const ETAPAS = [
  ['prospecto_agregado', 'Prospecto', 'var(--mt-seq-2)'],
  ['contacto_establecido', 'Contacto', 'var(--mt-seq-3)'],
  ['reunion_presentacion', 'Reunión', 'var(--mt-seq-4)'],
  ['propuesta_enviada', 'Propuesta', 'var(--mt-seq-5)'],
  ['convertido', 'Convertido', 'var(--mt-ok)'],
  ['perdido', 'Perdido', '#B4B1A8'],
];
function pintarEmbudo() {
  document.getElementById('embudo-leyenda').innerHTML = ETAPAS.map(([, et, c]) => `<span><i style="background:${c}"></i>${et}</span>`).join('');
  const vend = datos.por_vendedor; // siempre todo el equipo (sección "Todo el equipo")
  const filas = vend.map(v => {
    const e = datos.embudo[v.id] || {};
    const total = ETAPAS.reduce((a, [k]) => a + (e[k] || 0), 0);
    if (!total) return '';
    const seg = ETAPAS.filter(([k]) => e[k]).map(([k, et, c]) =>
      `<span style="flex:${e[k]};background:${c}" data-tip="${esc(v.nombre)} · ${et}: ${e[k]} (${Math.round(e[k] / total * 100)} %)"></span>`).join('');
    return `<div class="mt-emb-fila"><span class="nom">${esc(v.nombre)}</span><span class="mt-emb-barra">${seg}</span><span class="mt-muted">${total}</span></div>`;
  }).join('');
  document.getElementById('embudo').innerHTML = filas || '<div class="mt-vacio">Sin prospectos ni clientes registrados.</div>';
}

// ── Tooltip propio (barras, celdas, segmentos, números clave) ───────────
const tip = document.createElement('div');
tip.className = 'mt-tip'; tip.hidden = true;
document.body.appendChild(tip);
function moverTip(e) {
  const el = e.target.closest && e.target.closest('[data-tip]');
  if (!el) { tip.hidden = true; return; }
  tip.textContent = el.dataset.tip;
  tip.hidden = false;
  const x = Math.max(8, Math.min(e.clientX + 12, window.innerWidth - tip.offsetWidth - 8));
  const y = e.clientY + 16 + tip.offsetHeight > window.innerHeight ? e.clientY - tip.offsetHeight - 10 : e.clientY + 16;
  tip.style.left = x + 'px'; tip.style.top = y + 'px';
}
document.addEventListener('mousemove', moverTip);
document.addEventListener('touchstart', e => { const t = e.touches[0]; moverTip({ target: e.target, clientX: t.clientX, clientY: t.clientY }); }, { passive: true });
document.addEventListener('scroll', () => { tip.hidden = true; }, { passive: true });

// ── Eventos ──────────────────────────────────────────────────────────────
document.querySelector('.mt-periodos').addEventListener('click', e => {
  const b = e.target.closest('[data-p]'); if (!b) return;
  if (b.dataset.p === 'rango') {
    document.getElementById('rango').hidden = false;
    document.querySelectorAll('.mt-periodos button').forEach(x => x.classList.toggle('active', x === b));
    return;
  }
  est.p = b.dataset.p;
  cargar();
});
['desde', 'hasta'].forEach(id => document.getElementById(id).addEventListener('change', () => {
  const d = document.getElementById('desde').value, h = document.getElementById('hasta').value;
  if (!d || !h) return;
  est.p = 'rango'; est.desde = d <= h ? d : h; est.hasta = d <= h ? h : d;
  cargar();
}));
document.getElementById('btn-equipo').addEventListener('click', () => { est.vendedor = 0; cargar(); });
document.getElementById('btn-ranking').addEventListener('click', () => { est.orden = { col: null, asc: false }; pintarTabla(); });
document.getElementById('tabs-tabla').addEventListener('click', e => {
  const b = e.target.closest('[data-t]'); if (!b) return;
  est.tab = b.dataset.t;
  document.querySelectorAll('#tabs-tabla button').forEach(x => x.classList.toggle('active', x === b));
  pintarTabla();
});
document.getElementById('tabla-vend').addEventListener('click', e => {
  const th = e.target.closest('th[data-col]');
  if (th) {
    const col = th.dataset.col;
    // "Lugar" o "Vendedor" regresan al ranking; las demás columnas ordenan por esa columna.
    if (col === 'ranking' || col === 'nombre') est.orden = { col: null, asc: false };
    else est.orden = { col, asc: est.orden.col === col ? !est.orden.asc : false };
    pintarTabla();
    return;
  }
  if (e.target.closest('a')) return;
  // Tocar la fila filtra todo por ese vendedor (otra vez = quitar filtro).
  const tr = e.target.closest('tr[data-v]');
  if (tr) {
    const id = parseInt(tr.dataset.v);
    est.vendedor = id === est.vendedor ? 0 : id;
    // Al elegir a alguien, bajar a su resumen para que se vea qué cambió.
    cargar().then(() => { if (est.vendedor) document.getElementById('resumen-titulo').closest('.mt-divisor').scrollIntoView({ behavior: 'smooth', block: 'start' }); });
  }
});
document.querySelector('[data-tabla="serie"]').addEventListener('click', e => {
  const t = document.getElementById('tabla-serie');
  t.hidden = !t.hidden;
  e.currentTarget.innerHTML = t.hidden ? '<i class="bi bi-table"></i> Ver tabla' : '<i class="bi bi-graph-up"></i> Ocultar tabla';
});

leerUrl();
cargar();
