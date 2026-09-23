// Captura de dirección + ubicación del cliente, compartida por
// vendedor/nuevo_cliente.php y vendedor/editar_cliente.php (el HTML está en
// includes/form_direccion_cliente.php).
//
// Tres formas de llegar a la ubicación exacta:
//   1. Código postal -> estado/municipio/colonia salen del catálogo SEPOMEX y
//      el mapa se centra en la colonia para que el vendedor toque el punto.
//   2. Buscador de calle: Google Places si hay clave (window.DIRECCION_CFG.
//      googleKey), si no o si falla, Nominatim (OpenStreetMap).
//   3. Pegar un link de Google Maps / WhatsApp / Apple Maps (o "lat, lng").
//
// Uso: DireccionCliente.init() -> promesa; .precargar({...}); .valores().
window.DireccionCliente = (function () {
  const CFG = window.DIRECCION_CFG || {};
  const $ = (id) => document.getElementById(id);

  let mapa, marcador;
  let selectEstado, selectMunicipio, selectColonia, inputCp, campoCalle;
  let estadosListos;
  let calleEditadaAMano = false;
  let coloniasDelCp = null; // filas de SEPOMEX del CP escrito (null = sin filtro por CP)

  // ------------------------------------------------------------------
  // Utilidades
  // ------------------------------------------------------------------
  function normalizar(s) {
    const marcasDiacriticas = new RegExp('[̀-ͯ]', 'g');
    const sinAcentos = (s || '').toString().toLowerCase().normalize('NFD').replace(marcasDiacriticas, '');
    return sinAcentos.replace(/[^a-z0-9]+/g, ' ').trim();
  }

  function buscarCoincidencia(select, texto) {
    const objetivo = normalizar(texto);
    if (!objetivo) return null;
    const opciones = Array.from(select.options).map(o => o.value).filter(v => v && v !== '__todas__');
    let match = opciones.find(o => normalizar(o) === objetivo);
    if (!match) match = opciones.find(o => normalizar(o).includes(objetivo) || objetivo.includes(normalizar(o)));
    return match || null;
  }

  function escaparHtml(s) {
    return (s || '').toString().replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  }

  function nota(id, texto, tipo = 'info') {
    const el = $(id);
    if (!el) return;
    el.className = 'v26-dir-nota ' + tipo;
    el.textContent = texto || '';
  }

  const esperar = (ms) => new Promise(r => setTimeout(r, ms));

  // ------------------------------------------------------------------
  // Mapa
  // ------------------------------------------------------------------
  function ponerMarcador(lat, lng, autocompletar = true) {
    $('lat').value = lat;
    $('lng').value = lng;
    if (marcador) {
      marcador.setLatLng([lat, lng]);
    } else {
      marcador = L.marker([lat, lng], { draggable: true }).addTo(mapa);
      marcador.on('dragend', () => {
        const p = marcador.getLatLng();
        ponerMarcador(p.lat, p.lng, true);
      });
    }
    mapa.setView([lat, lng], Math.max(mapa.getZoom(), 17));
    const badge = $('ubicacion-estado');
    badge.className = 'v26-ubicacion-badge lista';
    badge.innerHTML = '<i class="bi bi-check-circle-fill"></i> marcada';
    if (autocompletar) autocompletarDesdeCoordenadas(lat, lng);
  }

  // Centra el mapa en la colonia elegida (sin poner pin: el vendedor toca
  // el punto exacto). Solo si todavía no hay ubicación marcada.
  async function centrarEnColonia() {
    if ($('lat').value) return;
    const colonia = selectColonia.value, municipio = selectMunicipio.value, estado = selectEstado.value;
    const cp = valores().cp;
    const intentos = [];
    if (colonia) intentos.push([{ q: `${colonia}, ${municipio}, ${estado}` }, 16]);
    if (colonia) intentos.push([{ q: `${colonia}, ${estado}` }, 16]);
    if (cp) intentos.push([{ postalcode: cp, country: 'mx' }, 15]);
    if (municipio) intentos.push([{ q: `${municipio}, ${estado}` }, 13]);
    for (let i = 0; i < intentos.length; i++) {
      const [params, zoom] = intentos[i];
      if (i > 0) await esperar(1000); // Nominatim: máximo 1 petición por segundo
      if ($('lat').value) return;
      const r = await nominatim('search', { ...params, limit: 1 });
      if (r && r.length) {
        if (!$('lat').value) mapa.setView([parseFloat(r[0].lat), parseFloat(r[0].lon)], zoom);
        return;
      }
    }
  }

  // ------------------------------------------------------------------
  // Nominatim (OpenStreetMap)
  // ------------------------------------------------------------------
  async function nominatim(tipo, params) {
    const qs = new URLSearchParams({ format: 'json', addressdetails: '1', 'accept-language': 'es', ...params });
    if (tipo === 'search' && !params.country) qs.set('countrycodes', 'mx');
    try {
      const res = await fetch(`https://nominatim.openstreetmap.org/${tipo}?${qs}`);
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return await res.json();
    } catch (e) {
      console.error('Nominatim:', e);
      return null;
    }
  }

  // Deja la dirección como la entiende OpenStreetMap: sin "cp 20367",
  // "col.", "#", ni número de casa (casi no hay números en OSM México), y
  // con las abreviaturas escritas completas.
  function limpiarConsulta(q) {
    let s = ' ' + q.toLowerCase() + ' ';
    s = s.replace(/\bc\.?\s?p\.?\s*\d{5}\b/g, ' ')
         .replace(/\b\d{5}\b/g, ' ')
         .replace(/\b(col|colonia|fracc|fraccionamiento)\b\.?/g, ' ')
         .replace(/\b(no|num|numero|número)\b\.?/g, ' ')
         .replace(/#\s*\d+[a-z]?\b/g, ' ')
         .replace(/\b\d+[a-z]?\b(?!\s+de\b)/g, ' ') // conserva "5 de Mayo", "20 de Noviembre"
         .replace(/\b(blvd|blvr|bvd|boul)\b\.?/g, ' boulevard ')
         .replace(/\b(av|ave|avda)\b\.?/g, ' avenida ')
         .replace(/\b(calz)\b\.?/g, ' calzada ')
         .replace(/\b(prol)\b\.?/g, ' prolongación ')
         .replace(/\b(carr)\b\.?/g, ' carretera ')
         .replace(/[,;]+/g, ' ');
    return s.replace(/\s+/g, ' ').trim();
  }

  async function buscarEnNominatim(q) {
    const palabras = limpiarConsulta(q).split(' ').filter(Boolean);
    if (!palabras.length) return [];
    const contexto = [selectMunicipio.value, selectEstado.value].filter(Boolean).join(', ');
    const sinTipo = palabras.filter(p => !['boulevard', 'avenida', 'calzada', 'calle', 'prolongación', 'carretera'].includes(p));

    // De lo más completo a lo más corto, hasta que algo aparezca. Nominatim
    // pide máximo 1 petición por segundo.
    const intentos = [];
    const agregar = (ps) => {
      const t = ps.join(' ');
      if (t && !intentos.includes(t)) intentos.push(t);
    };
    agregar(palabras);
    agregar(sinTipo);
    if (sinTipo.length > 2) agregar(sinTipo.slice(0, 2));
    if (sinTipo.length > 1) agregar(sinTipo.slice(0, 1));

    for (let i = 0; i < intentos.length && i < 4; i++) {
      if (i > 0) await esperar(1000);
      const texto = contexto ? `${intentos[i]}, ${contexto}` : intentos[i];
      const data = await nominatim('search', { q: texto, limit: 6 });
      if (data === null) throw new Error('sin conexión');
      if (data.length) {
        return data.map(r => ({
          texto: r.display_name,
          elegir: async () => ({ lat: parseFloat(r.lat), lng: parseFloat(r.lon) }),
        }));
      }
    }
    return [];
  }

  // ------------------------------------------------------------------
  // Google Places (API nueva: AutocompleteSuggestion)
  // ------------------------------------------------------------------
  let googlePromesa = null;
  let googleFallo = false;
  let sessionToken = null;

  function cargarGoogle() {
    if (googlePromesa) return googlePromesa;
    googlePromesa = new Promise((ok, fallo) => {
      window.__direccionGoogleListo = ok;
      window.gm_authFailure = () => { googleFallo = true; };
      const s = document.createElement('script');
      s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(CFG.googleKey) +
        '&v=weekly&loading=async&language=es&region=MX&callback=__direccionGoogleListo';
      s.async = true;
      s.onerror = () => fallo(new Error('No cargó Google Maps'));
      document.head.appendChild(s);
    }).then(() => google.maps.importLibrary('places'));
    return googlePromesa;
  }

  function componente(comps, tipo) {
    const c = (comps || []).find(x => x.types.includes(tipo));
    return c ? c.longText : '';
  }

  async function buscarEnGoogle(q) {
    const { AutocompleteSuggestion, AutocompleteSessionToken } = await cargarGoogle();
    if (googleFallo) throw new Error('clave de Google rechazada');
    if (!sessionToken) sessionToken = new AutocompleteSessionToken();
    const contexto = [selectColonia.value, selectMunicipio.value, selectEstado.value].filter(Boolean);
    const yaTraeContexto = contexto.some(c => normalizar(q).includes(normalizar(c)));
    const req = {
      input: (!yaTraeContexto && contexto.length) ? `${q}, ${contexto.join(', ')}` : q,
      includedRegionCodes: ['mx'],
      language: 'es-MX',
      region: 'mx',
      sessionToken,
    };
    if (mapa.getZoom() >= 12) {
      const c = mapa.getCenter();
      req.locationBias = { center: { lat: c.lat, lng: c.lng }, radius: 20000 };
    }
    const { suggestions } = await AutocompleteSuggestion.fetchAutocompleteSuggestions(req);
    return suggestions.filter(s => s.placePrediction).map(s => ({
      texto: s.placePrediction.text.toString(),
      elegir: async () => {
        const place = s.placePrediction.toPlace();
        await place.fetchFields({ fields: ['location', 'addressComponents'] });
        sessionToken = null; // la sesión de Google termina al elegir un lugar
        return { lat: place.location.lat(), lng: place.location.lng(), comps: place.addressComponents };
      },
    }));
  }

  // Llena calle/CP/colonia con lo que devuelve Google (más confiable que
  // la geocodificación inversa de OSM).
  async function aplicarComponentesGoogle(comps) {
    const calle = componente(comps, 'route');
    const numero = componente(comps, 'street_number');
    if (calle && !calleEditadaAMano) campoCalle.value = numero ? `${calle} ${numero}` : calle;
    const cp = componente(comps, 'postal_code');
    const coloniaTexto = componente(comps, 'sublocality_level_1') || componente(comps, 'sublocality') || componente(comps, 'neighborhood');
    if (cp && !selectColonia.value) {
      inputCp.value = cp;
      await aplicarCP(cp, coloniaTexto, false);
    }
  }

  // ------------------------------------------------------------------
  // Buscador de calle
  // ------------------------------------------------------------------
  let temporizadorBusqueda = null;
  let busquedaActual = 0;

  function pintarResultados(html) {
    $('resultados-busqueda').innerHTML = html ? `<div class="v26-resultados-busqueda">${html}</div>` : '';
  }

  async function buscarDireccion() {
    const campo = $('buscar-direccion');
    const q = campo.value.trim();
    if (q.length < 4) { pintarResultados(''); return; }
    const miBusqueda = ++busquedaActual;
    pintarResultados('<button type="button" disabled>Buscando...</button>');

    let resultados = null;
    let error = false;
    if (CFG.googleKey && !googleFallo) {
      try { resultados = await buscarEnGoogle(q); } catch (e) { console.error('Google Places:', e); }
    }
    if (!resultados || !resultados.length) {
      try { resultados = await buscarEnNominatim(q); } catch (e) { error = true; }
    }
    if (miBusqueda !== busquedaActual) return null; // ya escribió otra cosa

    if (error) {
      pintarResultados('<button type="button" disabled>No se pudo buscar (revisa tu conexión). Puedes tocar el punto directo en el mapa.</button>');
      return 0;
    }
    if (!resultados.length) {
      pintarResultados('<button type="button" disabled>No se encontró. Toca el punto directo en el mapa (ya está centrado en la colonia) o pega el link de la ubicación.</button>');
      return 0;
    }
    pintarResultados(resultados.map((r, i) => `<button type="button" data-i="${i}">${escaparHtml(r.texto)}</button>`).join(''));
    $('resultados-busqueda').querySelectorAll('button[data-i]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const r = await resultados[btn.dataset.i].elegir();
        pintarResultados('');
        campo.value = '';
        if (r.comps) {
          ponerMarcador(r.lat, r.lng, false);
          await aplicarComponentesGoogle(r.comps);
          if (!campoCalle.value) autocompletarDesdeCoordenadas(r.lat, r.lng);
        } else {
          ponerMarcador(r.lat, r.lng, true);
        }
      });
    });
    return resultados.length;
  }

  // ------------------------------------------------------------------
  // Pegar link de ubicación
  // ------------------------------------------------------------------
  // Mismos patrones que coordenadasDe() en api/resolver_ubicacion.php.
  function extraerCoordenadas(texto) {
    let t = texto.trim();
    try { t = decodeURIComponent(t); } catch (e) { /* se usa tal cual */ }
    const patrones = [
      /!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/,
      /[?&](?:q|query|ll|sll|destination|daddr|center|markers)=(?:loc:)?(-?\d+\.\d+)\s*,\s*(-?\d+\.\d+)/,
      /@(-?\d+\.\d+),(-?\d+\.\d+)/,
      /\/search\/(-?\d+\.\d+),\+?(-?\d+\.\d+)/,
      /geo:(-?\d+\.\d+),(-?\d+\.\d+)/,
      /^(-?\d{1,2}\.\d+)\s*,\s*(-?\d{1,3}\.\d+)$/,
    ];
    for (const p of patrones) {
      const m = t.match(p);
      if (m) {
        const lat = parseFloat(m[1]), lng = parseFloat(m[2]);
        if (Math.abs(lat) <= 90 && Math.abs(lng) <= 180) return { lat, lng };
      }
    }
    return null;
  }

  let ultimoLink = '';
  async function procesarLink() {
    const campo = $('pegar-ubicacion');
    const texto = campo.value.trim();
    if (!texto || texto === ultimoLink) return;
    ultimoLink = texto;

    // Del mensaje de WhatsApp solo interesa el link (a veces viene con texto).
    const link = (texto.match(/https?:\/\/\S+/) || [texto])[0];
    let coords = extraerCoordenadas(link);
    let nombre = null;

    if (!coords && /^https?:\/\//i.test(link)) {
      nota('nota-link', 'Abriendo el link...', 'info');
      try {
        const res = await fetch('../api/resolver_ubicacion.php?url=' + encodeURIComponent(link));
        const data = await res.json();
        if (!data.ok) { nota('nota-link', data.error, 'error'); return; }
        if (data.lat !== null) coords = { lat: data.lat, lng: data.lng };
        nombre = data.nombre;
      } catch (e) {
        nota('nota-link', 'No se pudo abrir el link (revisa tu conexión).', 'error');
        return;
      }
    }

    if (coords) {
      ponerMarcador(coords.lat, coords.lng, true);
      nota('nota-link', 'Ubicación tomada del link. Revisa que el pin quede en el lugar correcto.', 'ok');
      campo.value = '';
      ultimoLink = '';
      return;
    }
    if (nombre) {
      // Típico de "Compartir" desde la búsqueda de Google (share.google):
      // solo trae el nombre del negocio, no dónde está.
      nota('nota-link', `El link solo trae el nombre ("${nombre}"), no la ubicación. Lo busqué por nombre...`, 'info');
      $('buscar-direccion').value = nombre;
      const encontrados = await buscarDireccion();
      if (encontrados) {
        nota('nota-link', `El link solo trae el nombre ("${nombre}"). Elige el resultado correcto de la lista.`, 'info');
      } else if (encontrados === 0) {
        nota('nota-link', 'Ese link solo trae el nombre del negocio y no se encontró en el mapa. Pide la ubicación desde la app de Google Maps (abrir el lugar > Compartir) o por WhatsApp (Adjuntar > Ubicación), o toca el punto en el mapa.', 'error');
      }
      return;
    }
    nota('nota-link', 'Ese link no trae la ubicación. Pide al cliente que la comparta desde Google Maps o WhatsApp (Adjuntar > Ubicación).', 'error');
  }

  // ------------------------------------------------------------------
  // Catálogo SEPOMEX
  // ------------------------------------------------------------------
  async function cargarEstados() {
    try {
      const res = await fetch('../api/sepomex.php?tipo=estados');
      const data = await res.json();
      if (!data.ok || data.estados.length === 0) {
        selectEstado.innerHTML = '<option value="">Catálogo no disponible</option>';
        return;
      }
      selectEstado.innerHTML = '<option value="">Selecciona un estado</option>' +
        data.estados.map(e => `<option value="${escaparHtml(e)}">${escaparHtml(e)}</option>`).join('');
    } catch (e) {
      selectEstado.innerHTML = '<option value="">Error al cargar</option>';
    }
  }

  async function cargarMunicipios(estado) {
    selectMunicipio.disabled = true;
    selectColonia.disabled = true;
    selectColonia.innerHTML = '<option value="">Elige el municipio</option>';
    if (!estado) {
      selectMunicipio.innerHTML = '<option value="">Elige el estado</option>';
      return;
    }
    selectMunicipio.innerHTML = '<option value="">Cargando...</option>';
    const res = await fetch('../api/sepomex.php?tipo=municipios&estado=' + encodeURIComponent(estado));
    const data = await res.json();
    if (!data.ok) { selectMunicipio.innerHTML = '<option value="">Error al cargar</option>'; return; }
    selectMunicipio.innerHTML = '<option value="">Selecciona un municipio</option>' +
      data.municipios.map(m => `<option value="${escaparHtml(m)}">${escaparHtml(m)}</option>`).join('');
    selectMunicipio.disabled = false;
  }

  function pintarColonias(colonias, conOpcionTodas) {
    selectColonia.innerHTML = '<option value="">Selecciona una colonia</option>' +
      colonias.map(c => `<option value="${escaparHtml(c.asentamiento)}" data-cp="${escaparHtml(c.cp)}">${escaparHtml(c.asentamiento)} (CP ${escaparHtml(c.cp)})</option>`).join('') +
      (conOpcionTodas ? '<option value="__todas__">¿No aparece? Ver todas las del municipio</option>' : '');
    selectColonia.disabled = false;
  }

  async function cargarColonias(estado, municipio) {
    selectColonia.disabled = true;
    if (!municipio) {
      selectColonia.innerHTML = '<option value="">Elige el municipio</option>';
      return;
    }
    selectColonia.innerHTML = '<option value="">Cargando...</option>';
    const url = `../api/sepomex.php?tipo=colonias&estado=${encodeURIComponent(estado)}&municipio=${encodeURIComponent(municipio)}`;
    const res = await fetch(url);
    const data = await res.json();
    if (!data.ok) { selectColonia.innerHTML = '<option value="">Error al cargar</option>'; return; }
    pintarColonias(data.colonias, false);
  }

  // Escribió un CP: se fija estado y municipio y la lista de colonias se
  // reduce a las de ese CP. Si solo hay una, se elige sola.
  // preferido = { estado, municipio } cuando se eligió una colonia por nombre
  // (un mismo CP puede abarcar más de un municipio).
  async function aplicarCP(cp, coloniaSugerida = '', centrar = true, preferido = null) {
    coloniasDelCp = null;
    nota('nota-cp', 'Buscando...', 'info');
    let data;
    try {
      const res = await fetch('../api/sepomex.php?tipo=cp&cp=' + encodeURIComponent(cp));
      data = await res.json();
    } catch (e) {
      nota('nota-cp', 'No se pudo consultar el CP.', 'error');
      return;
    }
    if (!data.ok) { nota('nota-cp', data.error, 'error'); return; }
    if (!data.colonias.length) {
      nota('nota-cp', 'Ese CP no está en el catálogo. Elige estado, municipio y colonia a mano.', 'error');
      return;
    }
    const base = (preferido && data.colonias.find(c => c.estado === preferido.estado && c.municipio === preferido.municipio)) || data.colonias[0];
    const { estado, municipio } = base;
    const colonias = data.colonias.filter(c => c.estado === estado && c.municipio === municipio);
    coloniasDelCp = colonias;

    await estadosListos;
    selectEstado.value = estado;
    await cargarMunicipios(estado);
    selectMunicipio.value = municipio;
    pintarColonias(colonias, true);
    nota('nota-cp', `${municipio}, ${estado}`, 'ok');

    const elegida = (coloniaSugerida && buscarCoincidencia(selectColonia, coloniaSugerida)) ||
      (colonias.length === 1 ? colonias[0].asentamiento : null);
    if (elegida) {
      selectColonia.value = elegida;
      if (centrar) centrarEnColonia();
    } else if (centrar) {
      centrarEnColonia();
    }
  }

  // ------------------------------------------------------------------
  // Geocodificación inversa: al marcar el mapa se llenan los campos vacíos
  // ------------------------------------------------------------------
  async function autocompletarDesdeCoordenadas(lat, lng) {
    const data = await nominatim('reverse', { lat, lon: lng });
    if (!data) return;
    const a = data.address || {};

    const calle = a.road || a.pedestrian || '';
    const numero = a.house_number || '';
    if (calle && !calleEditadaAMano) campoCalle.value = numero ? `${calle} ${numero}` : calle;

    // Si ya eligió colonia (por CP o a mano) no se le cambia: SEPOMEX es
    // más confiable que lo que diga OpenStreetMap.
    if (selectColonia.value && selectColonia.value !== '__todas__') return;

    await estadosListos;
    const estadoMatch = buscarCoincidencia(selectEstado, a.state);
    if (!estadoMatch) return;
    if (selectEstado.value !== estadoMatch || selectMunicipio.disabled) {
      selectEstado.value = estadoMatch;
      await cargarMunicipios(estadoMatch);
    }

    const municipioTexto = a.county || a.city_district || a.municipality || a.town || a.city || '';
    const municipioMatch = buscarCoincidencia(selectMunicipio, municipioTexto);
    if (!municipioMatch) return;
    if (selectMunicipio.value !== municipioMatch || !coloniasDelCp) {
      selectMunicipio.value = municipioMatch;
      coloniasDelCp = null;
      await cargarColonias(estadoMatch, municipioMatch);
    }

    const coloniaTexto = a.suburb || a.neighbourhood || a.quarter || a.residential || '';
    const coloniaMatch = buscarCoincidencia(selectColonia, coloniaTexto);
    if (coloniaMatch) {
      selectColonia.value = coloniaMatch;
      selectColonia.dispatchEvent(new Event('change'));
    }
  }

  // Colonia por nombre, para cuando no saben el CP o el que tienen no
  // coincide con SEPOMEX (p. ej. Google dice 50900 y SEPOMEX 50904).
  let temporizadorColonia = null;
  let busquedaColoniaActual = 0;

  function pintarResultadosColonia(html) {
    $('resultados-colonia').innerHTML = html ? `<div class="v26-resultados-busqueda">${html}</div>` : '';
  }

  async function buscarColoniaPorNombre() {
    const campo = $('buscar-colonia');
    const q = campo.value.trim();
    if (q.length < 3) { pintarResultadosColonia(''); return; }
    const miBusqueda = ++busquedaColoniaActual;
    pintarResultadosColonia('<button type="button" disabled>Buscando...</button>');
    let data;
    try {
      const res = await fetch('../api/sepomex.php?tipo=buscar_colonia&q=' + encodeURIComponent(q));
      data = await res.json();
    } catch (e) {
      data = { ok: false, error: 'No se pudo buscar (revisa tu conexión).' };
    }
    if (miBusqueda !== busquedaColoniaActual) return;
    if (!data.ok) { pintarResultadosColonia(`<button type="button" disabled>${escaparHtml(data.error)}</button>`); return; }
    if (!data.colonias.length) {
      pintarResultadosColonia('<button type="button" disabled>No hay colonias con ese nombre. Prueba con una sola palabra (ej. "Tlalchichilpan").</button>');
      return;
    }
    const filas = data.colonias;
    pintarResultadosColonia(filas.map((c, i) =>
      `<button type="button" data-i="${i}"><b>${escaparHtml(c.asentamiento)}</b> — ${escaparHtml(c.municipio)}, ${escaparHtml(c.estado)} (CP ${escaparHtml(c.cp)})</button>`
    ).join(''));
    $('resultados-colonia').querySelectorAll('button[data-i]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const c = filas[btn.dataset.i];
        pintarResultadosColonia('');
        campo.value = '';
        inputCp.value = c.cp;
        await aplicarCP(c.cp, c.asentamiento, true, { estado: c.estado, municipio: c.municipio });
      });
    });
  }

  // ------------------------------------------------------------------
  // API pública
  // ------------------------------------------------------------------
  function valores() {
    const opt = selectColonia.selectedOptions[0];
    const colonia = selectColonia.value === '__todas__' ? '' : selectColonia.value;
    return {
      estado: selectEstado.value,
      municipio: selectMunicipio.value,
      colonia,
      cp: (colonia && opt && opt.dataset.cp) || inputCp.value.trim(),
      lat: $('lat').value,
      lng: $('lng').value,
      calleNumero: campoCalle.value.trim(),
    };
  }

  async function precargar(p) {
    await estadosListos;
    if (p.estado) {
      selectEstado.value = p.estado;
      await cargarMunicipios(p.estado);
    }
    if (p.municipio) {
      selectMunicipio.value = p.municipio;
      await cargarColonias(p.estado, p.municipio);
    }
    if (p.colonia) selectColonia.value = p.colonia;
    if (p.codigo_postal) inputCp.value = p.codigo_postal;
    if (p.lat && p.lng) {
      // false = no reconsultar para no pisar lo que ya tiene capturado.
      ponerMarcador(parseFloat(p.lat), parseFloat(p.lng), false);
    } else if (p.colonia) {
      centrarEnColonia();
    }
  }

  function init() {
    selectEstado = $('select-estado');
    selectMunicipio = $('select-municipio');
    selectColonia = $('select-colonia');
    inputCp = $('input-cp');
    campoCalle = $('calle-numero');
    calleEditadaAMano = campoCalle.value.trim() !== '';

    mapa = L.map('mapa-cliente').setView([23.6345, -102.5528], 5); // centro de México por defecto
    // Mapa normal (OpenStreetMap) y satélite (Esri, gratis y sin clave). El
    // satélite sirve para ubicar la nave/local cuando la calle no tiene
    // nombre en el mapa (carreteras, parques industriales).
    const capaMapa = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 });
    const esri = (servicio) => `https://server.arcgisonline.com/ArcGIS/rest/services/${servicio}/MapServer/tile/{z}/{y}/{x}`;
    const capaSatelite = L.layerGroup([
      L.tileLayer(esri('World_Imagery'), { attribution: 'Imágenes © Esri, Maxar, Earthstar Geographics', maxZoom: 19 }),
      L.tileLayer(esri('Reference/World_Transportation'), { maxZoom: 19, opacity: .8 }),
      L.tileLayer(esri('Reference/World_Boundaries_and_Places'), { maxZoom: 19 }),
    ]);
    capaMapa.addTo(mapa);
    L.control.layers({ 'Mapa': capaMapa, 'Satélite': capaSatelite }, null, { position: 'topright', collapsed: false }).addTo(mapa);
    mapa.on('click', (e) => ponerMarcador(e.latlng.lat, e.latlng.lng));

    $('btn-mi-ubicacion').addEventListener('click', () => {
      if (!('geolocation' in navigator)) return alert('Tu navegador no soporta geolocalización.');
      navigator.geolocation.getCurrentPosition(
        (pos) => ponerMarcador(pos.coords.latitude, pos.coords.longitude),
        () => alert('No se pudo obtener tu ubicación. Revisa los permisos del navegador.'),
        { enableHighAccuracy: true, timeout: 15000 }
      );
    });

    campoCalle.addEventListener('input', () => { calleEditadaAMano = campoCalle.value.trim() !== ''; });

    $('buscar-direccion').addEventListener('input', () => {
      clearTimeout(temporizadorBusqueda);
      if ($('buscar-direccion').value.trim().length < 4) { busquedaActual++; pintarResultados(''); return; }
      temporizadorBusqueda = setTimeout(buscarDireccion, CFG.googleKey ? 350 : 700);
    });

    // Enter en estos campos no debe mandar el formulario a medio llenar.
    ['buscar-direccion', 'input-cp'].forEach(id => $(id).addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); if (id === 'buscar-direccion') buscarDireccion(); }
    }));

    $('buscar-colonia').addEventListener('input', () => {
      clearTimeout(temporizadorColonia);
      if ($('buscar-colonia').value.trim().length < 3) { busquedaColoniaActual++; pintarResultadosColonia(''); return; }
      temporizadorColonia = setTimeout(buscarColoniaPorNombre, 350);
    });
    $('buscar-colonia').addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); buscarColoniaPorNombre(); }
    });

    const campoLink = $('pegar-ubicacion');
    campoLink.addEventListener('paste', () => setTimeout(procesarLink, 0));
    campoLink.addEventListener('change', procesarLink);
    campoLink.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); procesarLink(); } });

    inputCp.addEventListener('input', () => {
      inputCp.value = inputCp.value.replace(/\D/g, '').slice(0, 5);
      if (inputCp.value.length === 5) aplicarCP(inputCp.value);
      else nota('nota-cp', '', 'info');
    });

    selectEstado.addEventListener('change', () => { coloniasDelCp = null; cargarMunicipios(selectEstado.value); });
    selectMunicipio.addEventListener('change', () => { coloniasDelCp = null; cargarColonias(selectEstado.value, selectMunicipio.value); });
    selectColonia.addEventListener('change', async () => {
      if (selectColonia.value === '__todas__') {
        coloniasDelCp = null;
        await cargarColonias(selectEstado.value, selectMunicipio.value);
        return;
      }
      const opt = selectColonia.selectedOptions[0];
      if (opt && opt.dataset.cp) inputCp.value = opt.dataset.cp;
      centrarEnColonia();
    });

    // Con clave de Google se precarga la librería para que la primera
    // búsqueda no tarde.
    if (CFG.googleKey) cargarGoogle().catch(e => { googleFallo = true; console.error(e); });

    estadosListos = cargarEstados();
    return estadosListos;
  }

  return { init, precargar, valores, extraerCoordenadas, limpiarConsulta };
})();
