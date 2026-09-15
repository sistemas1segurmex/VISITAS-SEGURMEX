// La base de datos (Postgres/Supabase) guarda los timestamps automáticos
// (tracking, check-ins, alertas) en UTC. Estas funciones los convierten a
// hora de Ciudad de México antes de mostrarlos -- SIEMPRE esa zona, fija,
// sin importar en qué zona horaria esté configurado el dispositivo de quien
// lo ve (el negocio opera en México; si se usara la zona del dispositivo,
// alguien viendo el panel desde un equipo mal configurado vería horas
// corridas, aunque los datos estén bien).
// OJO: esto NO aplica a citas.fecha_hora (esa la captura el vendedor
// directamente en su hora local, no pasa por UTC — no debe convertirse).

function formatearFechaUTC(fechaStr) {
  if (!fechaStr) return '';
  const iso = fechaStr.replace(' ', 'T') + (fechaStr.endsWith('Z') ? '' : 'Z');
  const d = new Date(iso);
  if (isNaN(d.getTime())) return fechaStr;
  return d.toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: 'America/Mexico_City' });
}

// Para páginas renderizadas en PHP: busca elementos con data-utc="..." y
// reemplaza su texto con la versión convertida a hora local.
function aplicarFechasUTC(selector = '.fecha-utc') {
  document.querySelectorAll(selector).forEach(el => {
    const raw = el.dataset.utc;
    if (raw) el.textContent = formatearFechaUTC(raw);
  });
}
