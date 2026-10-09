// Utilidades compartidas de las pantallas de la responsable de muestras y
// la consulta del admin (muestras/index.php y muestras/ver.php).
const ESTADOS_MUESTRA = {
  enviada:        { txt: 'Nueva',          clase: 'mu-pill--enviada' },
  en_preparacion: { txt: 'En preparación', clase: 'mu-pill--en_preparacion' },
  embarcada:      { txt: 'Embarcada',      clase: 'mu-pill--embarcada' },
  cancelada:      { txt: 'Cancelada',      clase: 'mu-pill--cancelada' },
};

function escHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function pillEstadoMuestra(estado) {
  const e = ESTADOS_MUESTRA[estado] || { txt: estado, clase: '' };
  return `<span class="mu-pill ${e.clase}">${e.txt}</span>`;
}

// Timestamps de la BD vienen en UTC; fecha_promesa es solo fecha (sin hora).
function fechaDeMuestra(valor) {
  if (!valor) return null;
  if (/^\d{4}-\d{2}-\d{2}$/.test(valor)) return new Date(valor + 'T12:00:00');
  return new Date(valor.replace(' ', 'T') + (valor.endsWith('Z') ? '' : 'Z'));
}

function fechaMx(valor) {
  const d = fechaDeMuestra(valor);
  if (!d || isNaN(d)) return '';
  return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'America/Mexico_City' });
}

function haceMuestra(valor) {
  const d = fechaDeMuestra(valor);
  if (!d || isNaN(d)) return '';
  const min = Math.round((Date.now() - d.getTime()) / 60000);
  if (min < 1) return 'hace un momento';
  if (min < 60) return `hace ${min} min`;
  if (min < 24 * 60) return `hace ${Math.round(min / 60)} h`;
  const dias = Math.round(min / (24 * 60));
  return dias === 1 ? 'hace 1 día' : `hace ${dias} días`;
}

function promesaVencida(m) {
  if (!m.fecha_promesa || m.estado === 'embarcada' || m.estado === 'cancelada') return false;
  const hoy = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Mexico_City' });
  return m.fecha_promesa < hoy;
}

// Las nuevas traen solo el link de rastreo (guia_url); las embarcadas antes
// del 09-oct-2026, paquetería y número de guía.
function textoEnvio(m) {
  if (m.envio_modo === 'en_persona') return 'Entregada en persona';
  if (m.guia_url) return 'Por paquetería';
  return [m.paqueteria, m.guia ? 'guía ' + m.guia : ''].filter(Boolean).join(', ') || 'Por paquetería';
}
