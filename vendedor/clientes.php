<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
$u = requireRole('vendedor');
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis clientes</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/style.css<?= assetVer(__DIR__ . '/../assets/css/style.css') ?>">
<link rel="stylesheet" href="../assets/css/vendedor-2026.css<?= assetVer(__DIR__ . '/../assets/css/vendedor-2026.css') ?>">
</head>
<body class="v26">
  <div class="v26-header">
    <div class="v26-topbar">
      <div class="v26-topbar-left">
        <a href="index.php" class="v26-back v26-tip v26-tip--bottom" data-tip="Volver a mis visitas" aria-label="Volver"><i class="bi bi-arrow-left"></i></a>
        <div class="v26-greeting">
          <div class="hi">Cartera</div>
          <div class="name">Mis clientes</div>
        </div>
      </div>
      <div class="v26-topbar-right">
        <img src="../logo.png" alt="Segurmex" class="v26-logo">
        <button type="button" class="v26-icon-btn v26-tip v26-tip--bottom" data-tip="Ver el recorrido de nuevo" aria-label="Ayuda" id="btn-tour-ayuda"><i class="bi bi-question-lg"></i></button>
      </div>
    </div>
    <div class="v26-tabbar">
      <a href="index.php"><i class="bi bi-house-fill"></i>Inicio</a>
      <a href="calendario.php"><i class="bi bi-calendar3"></i>Calendario</a>
      <a href="clientes.php" class="active"><i class="bi bi-people-fill"></i>Clientes</a>
      <a href="cotizaciones.php"><i class="bi bi-file-earmark-text-fill"></i>Cotizar</a>
      <a href="reporte.php"><i class="bi bi-bar-chart-fill"></i>Reporte</a>
    </div>
  </div>

  <div class="v26-wrap">
    <a href="nuevo_cliente.php" class="v26-cta" data-tour="cta-cliente">
      <span class="v26-cta-icon"><i class="bi bi-person-plus"></i></span>
      <span class="v26-cta-text">
        <strong>Nuevo cliente</strong>
        <small>Agrega un cliente a tu cartera</small>
      </span>
      <i class="bi bi-chevron-right chev"></i>
    </a>

    <div class="v26-stats-row" id="stats-row"></div>

    <!-- Embudo de ventas: oculto por mientras (quitar "d-none" para reactivarlo). -->
    <div class="v26-funnel-filtro d-none" id="filtro-etapa"></div>

    <div class="v26-search" data-tour="buscar">
      <i class="bi bi-search"></i>
      <input type="text" id="buscar-cliente" class="v26-input" placeholder="Buscar por nombre o dirección...">
    </div>

    <button type="button" class="v26-cerca-btn" id="btn-cerca" data-tour="cerca">
      <i class="bi bi-signpost-2"></i> Ordenar por cercanía a mí
    </button>

    <div id="lista-clientes">
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
      <div class="v26-skel"></div>
    </div>

    <button type="button" class="v26-btn v26-btn-ghost v26-btn-block mt-3" id="btn-tour-guiado"><i class="bi bi-signpost-split"></i> Tour guiado</button>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/v26-tour.js<?= assetVer(__DIR__ . '/../assets/js/v26-tour.js') ?>"></script>
<script src="../assets/js/vendedor.js<?= assetVer(__DIR__ . '/../assets/js/vendedor.js') ?>"></script>
<script>
iniciarTrackingPeriodico();
let todosLosClientes = [];

const PASOS_TOUR_CLIENTES = [
  { selector: '[data-tour="cta-cliente"]', texto: 'Agrega un cliente o prospecto nuevo a tu cartera aquí.' },
  { selector: '[data-tour="buscar"]', texto: 'Busca por nombre o dirección en cualquier momento.' },
  { selector: '[data-tour="cerca"]', texto: 'Ordena tu cartera por cercanía a ti para planear tu ruta.' },
  { selector: '#lista-clientes .v26-cliente-card', texto: 'Desde cada cliente puedes llamar, mandar WhatsApp, cotizar o ver su historial.' },
];
const OPCIONES_TOUR_CLIENTES = {
  storageKey: 'v26_tour_clientes_visto',
  saludoTitulo: 'Así se organiza tu cartera',
  saludoTexto: 'Un par de cosas rápidas antes de que la uses.',
  finalTexto: 'Repite este recorrido cuando quieras tocando el ícono ? de arriba.',
};
document.getElementById('btn-tour-ayuda').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_CLIENTES, OPCIONES_TOUR_CLIENTES));
document.getElementById('btn-tour-guiado').addEventListener('click', () => V26Tour.reiniciar(PASOS_TOUR_CLIENTES, OPCIONES_TOUR_CLIENTES));
let miPosicion = null; // {lat, lng}, solo si el vendedor aceptó compartirla en esta pantalla
let etapaFiltro = 'todos';

// ---------- Utilidades propias de esta pantalla ----------
function distanciaKm(lat1, lng1, lat2, lng2) {
  const R = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2 + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

function hechaRelativa(fechaHora) {
  if (!fechaHora) return 'Aún sin visitar';
  const d = new Date(fechaHora.replace(' ', 'T'));
  if (isNaN(d)) return 'Aún sin visitar';
  const dias = Math.floor((new Date() - d) / 86400000);
  if (dias <= 0) return 'Última visita: hoy';
  if (dias === 1) return 'Última visita: ayer';
  if (dias < 30) return `Última visita: hace ${dias} días`;
  const meses = Math.floor(dias / 30);
  return `Última visita: hace ${meses} ${meses === 1 ? 'mes' : 'meses'}`;
}

function soloDigitos(tel) { return (tel || '').replace(/\D/g, ''); }

// ---------- Estadísticas de la cartera ----------
function renderStats(lista) {
  const cont = document.getElementById('stats-row');
  const conGps = lista.filter(c => c.lat).length;
  const inicioMes = new Date(); inicioMes.setDate(1); inicioMes.setHours(0, 0, 0, 0);
  const visitadosEsteMes = lista.filter(c => c.ultima_visita && new Date(c.ultima_visita.replace(' ', 'T')) >= inicioMes).length;
  cont.innerHTML = `
    <div class="v26-stat-card"><div class="v26-stat-num" data-cuenta="${lista.length}">0</div><div class="v26-stat-label">Clientes</div></div>
    <div class="v26-stat-card v26-stat-card--verde"><div class="v26-stat-num" data-cuenta="${conGps}">0</div><div class="v26-stat-label">Con GPS</div></div>
    <div class="v26-stat-card v26-stat-card--ambar"><div class="v26-stat-num" data-cuenta="${visitadosEsteMes}">0</div><div class="v26-stat-label">Visitados este mes</div></div>
  `;
  cont.querySelectorAll('[data-cuenta]').forEach(el => animarContador(el, parseInt(el.dataset.cuenta, 10)));
}

// ---------- Filtro por etapa del embudo (con conteos) ----------
function renderFunnelFiltro(lista) {
  const cont = document.getElementById('filtro-etapa');
  const grupos = [{ valor: 'todos', etiqueta: 'Todos' }, ...ETAPAS_CLIENTE, ETAPA_PERDIDO];
  cont.innerHTML = grupos.map(g => {
    const n = g.valor === 'todos' ? lista.length : lista.filter(c => (c.etapa || 'prospecto_agregado') === g.valor).length;
    return `<button type="button" class="v26-funnel-chip ${etapaFiltro === g.valor ? 'active' : ''}" data-etapa="${g.valor}">${g.etiqueta} <span class="n">${n}</span></button>`;
  }).join('');
  cont.querySelectorAll('[data-etapa]').forEach(btn => {
    btn.addEventListener('click', () => {
      etapaFiltro = btn.dataset.etapa;
      aplicarFiltros();
    });
  });
}

// ---------- Cambiar etapa/interés desde la tarjeta ----------
async function abrirCambioEtapa(clienteId) {
  const cliente = todosLosClientes.find(c => c.id == clienteId);
  if (!cliente) return;
  const resultado = await v26SheetEtapa(cliente);
  if (!resultado) return;
  const data = await actualizarEtapaCliente(clienteId, resultado);
  if (data.ok) {
    cargarClientes();
  } else {
    alert(data.error || 'No se pudo actualizar.');
  }
}

document.getElementById('lista-clientes').addEventListener('click', (e) => {
  const fila = e.target.closest('[data-etapa-cliente]');
  if (fila) abrirCambioEtapa(fila.dataset.etapaCliente);
});

// ---------- Tarjetas de cliente ----------
function renderClientes(lista) {
  const cont = document.getElementById('lista-clientes');
  if (lista.length === 0) {
    cont.innerHTML = `
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-search"></i></div>
        <p>No se encontraron clientes.</p>
      </div>`;
    return;
  }
  cont.innerHTML = lista.map(c => {
    const inicial = (c.nombre || '?').trim().charAt(0).toUpperCase();
    const tieneGps = !!c.lat;
    const digitos = soloDigitos(c.telefono);
    const distancia = (miPosicion && tieneGps) ? distanciaKm(miPosicion.lat, miPosicion.lng, parseFloat(c.lat), parseFloat(c.lng)) : null;
    return `
    <div class="v26-cliente-card">
      <div class="top">
        <div class="v26-avatar-ring ${tieneGps ? '' : 'sin-gps'}"><div class="inner">${inicial}</div></div>
        <div class="info">
          <div class="nombre"><i class="bi ${c.tipo_cliente === 'persona' ? 'bi-person' : 'bi-building'} text-muted"></i> ${c.nombre}</div>
          ${c.nombre_contacto ? `<div class="direccion"><i class="bi bi-person-badge"></i> ${c.nombre_contacto}</div>` : ''}
          <div class="direccion"><i class="bi bi-geo-alt"></i> ${c.direccion}</div>
        </div>
        ${tieneGps
          ? '<span class="v26-pill v26-pill--verificado">GPS ok</span>'
          : `<a href="editar_cliente.php?id=${c.id}" class="v26-pill v26-pill--noverificado" style="text-decoration:none">Sin ubicación · corregir</a>`}
      </div>
      <div class="meta d-none" data-etapa-cliente="${c.id}" style="cursor:pointer;">
        ${pillEtapa(c.etapa)}
        <span class="meta-item" style="text-decoration:underline;"><i class="bi bi-pencil"></i> Actualizar etapa</span>
      </div>
      <div class="meta">
        <span class="meta-item"><i class="bi bi-clock-history"></i> ${hechaRelativa(c.ultima_visita)}</span>
        ${c.total_visitas > 0 ? `<span class="meta-item"><i class="bi bi-check2-circle"></i> ${c.total_visitas} visita${c.total_visitas == 1 ? '' : 's'}</span>` : ''}
        ${pillInteres(c.ultimo_interes)}
        ${distancia !== null ? `<span class="meta-item destacado"><i class="bi bi-signpost-2"></i> a ${distancia < 1 ? Math.round(distancia * 1000) + ' m' : distancia.toFixed(1) + ' km'}</span>` : ''}
      </div>
      <div class="acciones">
        ${digitos ? `<a href="tel:${digitos}" class="v26-mini-btn primario"><i class="bi bi-telephone-fill"></i>Llamar</a>` : ''}
        ${digitos ? `<a href="https://wa.me/52${digitos}" target="_blank" rel="noopener" class="v26-mini-btn whatsapp"><i class="bi bi-whatsapp"></i>WhatsApp</a>` : ''}
        <a href="nueva_cotizacion.php?cliente_id=${c.id}" class="v26-mini-btn"><i class="bi bi-file-earmark-plus"></i>Cotizar</a>
        <a href="historial_cliente.php?id=${c.id}" class="v26-mini-btn"><i class="bi bi-clock-history"></i>Historial</a>
        <a href="editar_cliente.php?id=${c.id}" class="v26-mini-btn"><i class="bi bi-pencil"></i>Editar</a>
      </div>
    </div>
  `;
  }).join('');
}

function aplicarFiltros() {
  const q = document.getElementById('buscar-cliente').value.toLowerCase();
  const baseBusqueda = todosLosClientes.filter(c =>
    c.nombre.toLowerCase().includes(q) || c.direccion.toLowerCase().includes(q)
  );
  renderFunnelFiltro(baseBusqueda);
  let lista = etapaFiltro === 'todos'
    ? baseBusqueda
    : baseBusqueda.filter(c => (c.etapa || 'prospecto_agregado') === etapaFiltro);
  if (miPosicion) {
    lista = [...lista].sort((a, b) => {
      const da = a.lat ? distanciaKm(miPosicion.lat, miPosicion.lng, parseFloat(a.lat), parseFloat(a.lng)) : Infinity;
      const db = b.lat ? distanciaKm(miPosicion.lat, miPosicion.lng, parseFloat(b.lat), parseFloat(b.lng)) : Infinity;
      return da - db;
    });
  }
  renderClientes(lista);
}

async function cargarClientes() {
  const res = await fetch('../api/clientes.php');
  const data = await res.json();
  if (!data.ok) {
    document.getElementById('lista-clientes').innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
    return;
  }
  if (data.clientes.length === 0) {
    document.getElementById('stats-row').innerHTML = '';
    document.getElementById('lista-clientes').innerHTML = `
      <div class="v26-empty">
        <div class="icon"><i class="bi bi-person-plus"></i></div>
        <p>Aún no tienes clientes registrados.<br>Usa "Nuevo cliente" arriba para agregar el primero.</p>
      </div>`;
    return;
  }
  todosLosClientes = data.clientes;
  renderStats(todosLosClientes);
  aplicarFiltros();
}

document.getElementById('buscar-cliente').addEventListener('input', aplicarFiltros);

document.getElementById('btn-cerca').addEventListener('click', function () {
  if (miPosicion) { miPosicion = null; this.classList.remove('activo'); this.innerHTML = '<i class="bi bi-signpost-2"></i> Ordenar por cercanía a mí'; aplicarFiltros(); return; }
  this.classList.add('cargando');
  this.innerHTML = '<i class="bi bi-arrow-repeat"></i> Obteniendo tu ubicación...';
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      miPosicion = { lat: pos.coords.latitude, lng: pos.coords.longitude };
      this.classList.remove('cargando');
      this.classList.add('activo');
      this.innerHTML = '<i class="bi bi-check2"></i> Ordenado por cercanía';
      aplicarFiltros();
    },
    () => {
      this.classList.remove('cargando');
      this.innerHTML = '<i class="bi bi-exclamation-triangle"></i> No se pudo obtener tu ubicación';
      setTimeout(() => { this.innerHTML = '<i class="bi bi-signpost-2"></i> Ordenar por cercanía a mí'; }, 2500);
    },
    { enableHighAccuracy: true, timeout: 10000 }
  );
});

cargarClientes().then(() => V26Tour.iniciar(PASOS_TOUR_CLIENTES, OPCIONES_TOUR_CLIENTES));
</script>
</body>
</html>
