// Captura de dirección + ubicación del cliente, compartida por
// vendedor/nuevo_cliente.php y vendedor/editar_cliente.php (el HTML está en
// includes/form_direccion_cliente.php).
//
// Paso 1, colonia: un solo campo que acepta CP (5 dígitos) o nombre de
//   colonia; sale del catálogo SEPOMEX y el mapa se centra en la colonia.
//   Estado/municipio/colonia quedan en selects ocultos (hay un "elegir de la
//   lista" como último recurso).
// Paso 2, calle y número: es el campo que se guarda y, mientras no haya pin,
//   sugiere direcciones para ubicarla (Google Places si hay clave en
//   window.DIRECCION_CFG.googleKey; si no o si falla, Nominatim).
// Paso 3, ubicación en el mapa: tocar el mapa (con vista satélite) o
//   arrastrar el pin, o los botones "Me mandaron la ubicación" (link de
//   Google Maps / WhatsApp / Apple Maps o "lat, lng") y "Estoy aquí" (GPS).
//
// Uso: DireccionCliente.init() -> promesa; .precargar({...}); .valores().
window.DireccionCliente = (function () {
  const CFG = window.DIRECCION_CFG || {};
  const $ = (id) => document.getElementById(id);

  let mapa, marcador;
  let selectEstado, selectMunicipio, selectColonia, inputCp, campoCalle;
  let estadosListos;
  let calleEditadaAMano = false;

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
    const opciones = Array.from(select.options).map(o => o.value).filter(Boolean);
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
    // Cualquier pin puesto a mano/por búsqueda es 'por confirmar'; solo
    // usarMiUbicacion() la llena (ver api/clientes.php).
    $('ubicacion-precision').value = '';
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
          elegir: async () => ({
            lat: parseFloat(r.lat), lng: parseFloat(r.lon),
            calle: (r.address && (r.address.road || r.address.pedestrian)) || '',
            numero: (r.address && r.address.house_number) || '',
          }),
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
  async function aplicarComponentesGoogle(comps, llenarCalle = true) {
    const calle = componente(comps, 'route');
    const numero = componente(comps, 'street_number');
    if (llenarCalle && calle && !calleEditadaAMano) campoCalle.value = numero ? `${calle} ${numero}` : calle;
    const cp = componente(comps, 'postal_code');
    const coloniaTexto = componente(comps, 'sublocality_level_1') || componente(comps, 'sublocality') || componente(comps, 'neighborhood');
    if (/^\d{5}$/.test(cp) && !valores().colonia) await coloniaDesdeCp(cp, coloniaTexto);
  }

  // ------------------------------------------------------------------
  // Buscador de calle
  // ------------------------------------------------------------------
  let temporizadorBusqueda = null;
  let busquedaActual = 0;

  function pintarResultados(html) {
    $('resultados-busqueda').innerHTML = html ? `<div class="v26-resultados-busqueda">${html}</div>` : '';
  }

  // El número que escribió el vendedor ("116", "#116", "No. 116", "Km 16"),
  // sin confundirlo con el CP ni con calles como "5 de Mayo".
  function numeroEscrito(texto) {
    const t = (texto || '').replace(/\bc\.?\s?p\.?\s*\d{5}\b/gi, ' ').replace(/\b\d{5}\b/g, ' ');
    const km = t.match(/\bkm\.?\s*(\d+(?:[.,]\d+)?)/i);
    if (km) return 'Km ' + km[1];
    const m = t.match(/(?:#|\bno\.?|\bnum\.?|\bnúmero)?\s*\b(\d{1,4}[a-z]?)\b(?!\s+de\b)/i);
    return m ? m[1] : '';
  }

  // Busca lo escrito en "Calle y número" (o el nombre que trae un link) y
  // ofrece resultados para ubicarlo en el mapa.
  async function buscarDireccion(textoForzado) {
    const q = (typeof textoForzado === 'string' ? textoForzado : campoCalle.value).trim();
    if (q.length < 4) { pintarResultados(''); return null; }
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

    // Buscar es solo una ayuda para ubicarla: lo que escribió en "Calle y
    // número" se guarda igual aunque no aparezca.
    const desdeCampoCalle = typeof textoForzado !== 'string';
    const seGuardaIgual = desdeCampoCalle ? ' La calle se guarda tal como la escribiste; solo marca el punto en el mapa (paso 3).' : '';
    if (error) {
      pintarResultados(`<button type="button" disabled>No se pudo buscar en el mapa (revisa tu conexión).${seGuardaIgual}</button>`);
      return 0;
    }
    if (!resultados.length) {
      pintarResultados(`<button type="button" disabled>No aparece en el mapa, no pasa nada.${seGuardaIgual || ' Toca el punto directo en el mapa.'}</button>`);
      return 0;
    }
    pintarResultados('<div class="v26-dir-titulo-lista"><i class="bi bi-geo-alt"></i> ¿Es alguna de estas? Tócala para ubicarla en el mapa</div>' +
      resultados.map((r, i) => `<button type="button" data-i="${i}">${escaparHtml(r.texto)}</button>`).join('') +
      '<button type="button" data-cerrar="1" class="text-muted">Ninguna: la ubico yo en el mapa</button>');
    $('resultados-busqueda').querySelector('button[data-cerrar]').addEventListener('click', () => {
      pintarResultados('');
      $('mapa-cliente').scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    $('resultados-busqueda').querySelectorAll('button[data-i]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const escrito = campoCalle.value;
        const r = await resultados[btn.dataset.i].elegir();
        pintarResultados('');
        ponerMarcador(r.lat, r.lng, false);
        let calle = r.calle, numero = r.numero;
        if (r.comps) {
          calle = componente(r.comps, 'route');
          numero = componente(r.comps, 'street_number');
          await aplicarComponentesGoogle(r.comps, false);
        }
        // Queda la calle bien escrita con el número que puso el vendedor.
        numero = numero || numeroEscrito(escrito);
        if (calle) {
          campoCalle.value = numero ? `${calle} ${numero}` : calle;
          calleEditadaAMano = true;
        }
        if (!valores().colonia || !calle) autocompletarDesdeCoordenadas(r.lat, r.lng);
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
      const encontrados = await buscarDireccion(nombre);
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
  // Paso 1: colonia (un solo campo: CP o nombre)
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
    selectColonia.innerHTML = '<option value="">Selecciona una colonia</option>' +
      data.colonias.map(c => `<option value="${escaparHtml(c.asentamiento)}" data-cp="${escaparHtml(c.cp)}">${escaparHtml(c.asentamiento)} (CP ${escaparHtml(c.cp)})</option>`).join('');
    selectColonia.disabled = false;
  }

  // Filas SEPOMEX { estado, municipio, asentamiento, cp } para un CP o un nombre.
  async function consultarColonias(texto) {
    const url = /^\d{5}$/.test(texto)
      ? '../api/sepomex.php?tipo=cp&cp=' + encodeURIComponent(texto)
      : '../api/sepomex.php?tipo=buscar_colonia&q=' + encodeURIComponent(texto);
    const res = await fetch(url);
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error al consultar');
    return data.colonias;
  }

  function pintarResultadosColonia(html) {
    $('resultados-colonia').innerHTML = html ? `<div class="v26-resultados-busqueda">${html}</div>` : '';
  }

  function ofrecerColonias(filas, titulo) {
    pintarResultadosColonia(
      (titulo ? `<div class="v26-dir-titulo-lista">${escaparHtml(titulo)}</div>` : '') +
      filas.map((c, i) =>
        `<button type="button" data-i="${i}"><b>${escaparHtml(c.asentamiento)}</b> — ${escaparHtml(c.municipio)}, ${escaparHtml(c.estado)} (CP ${escaparHtml(c.cp)})</button>`
      ).join('')
    );
    $('resultados-colonia').querySelectorAll('button[data-i]').forEach(btn => {
      btn.addEventListener('click', () => fijarColonia(filas[btn.dataset.i], true));
    });
  }

  // Deja elegida una colonia del catálogo (llena los selects ocultos, el CP
  // y muestra el resumen).
  async function fijarColonia(c, centrar = true) {
    pintarResultadosColonia('');
    $('buscar-colonia').value = '';
    nota('nota-cp', '', 'info');
    await estadosListos;
    if (selectEstado.value !== c.estado || selectMunicipio.disabled) {
      selectEstado.value = c.estado;
      await cargarMunicipios(c.estado);
    }
    const tieneColonia = Array.from(selectColonia.options).some(o => o.value === c.asentamiento);
    if (selectMunicipio.value !== c.municipio || !tieneColonia) {
      selectMunicipio.value = c.municipio;
      await cargarColonias(c.estado, c.municipio);
    }
    selectColonia.value = c.asentamiento;
    inputCp.value = c.cp;
    mostrarResumenColonia();
    if (centrar) centrarEnColonia();
  }

  function mostrarResumenColonia() {
    const v = valores();
    const hay = !!v.colonia;
    $('resumen-colonia').classList.toggle('d-none', !hay);
    $('caja-buscar-colonia').classList.toggle('d-none', hay);
    if (hay) {
      $('colonia-manual').classList.add('d-none');
      $('resumen-colonia-nombre').textContent = v.colonia;
      $('resumen-colonia-detalle').textContent = `${v.municipio}, ${v.estado}${v.cp ? ' · CP ' + v.cp : ''}`;
    }
  }

  let temporizadorColonia = null;
  let busquedaColoniaActual = 0;

  async function buscarColonia() {
    const texto = $('buscar-colonia').value.trim();
    const miBusqueda = ++busquedaColoniaActual;
    nota('nota-cp', '', 'info');
    if (/^\d+$/.test(texto) && texto.length < 5) {
      pintarResultadosColonia('');
      nota('nota-cp', 'Escribe los 5 dígitos del código postal.', 'info');
      return;
    }
    if (texto.length < 3) { pintarResultadosColonia(''); return; }
    pintarResultadosColonia('<button type="button" disabled>Buscando...</button>');
    let filas;
    try {
      filas = await consultarColonias(texto);
    } catch (e) {
      if (miBusqueda === busquedaColoniaActual) {
        pintarResultadosColonia(`<button type="button" disabled>${escaparHtml(e.message || 'No se pudo buscar (revisa tu conexión).')}</button>`);
      }
      return;
    }
    if (miBusqueda !== busquedaColoniaActual) return;
    const esCp = /^\d{5}$/.test(texto);
    if (!filas.length) {
      pintarResultadosColonia(`<button type="button" disabled>${esCp
        ? 'Ese código postal no está en el catálogo. Prueba escribiendo el nombre de la colonia.'
        : 'No hay colonias con ese nombre. Prueba con una sola palabra (ej. "Tlalchichilpan").'}</button>`);
      return;
    }
    if (esCp && filas.length === 1) { fijarColonia(filas[0], true); return; }
    ofrecerColonias(filas, esCp ? `Colonias con CP ${texto}` : 'Elige la colonia');
  }

  function cambiarColonia() {
    $('resumen-colonia').classList.add('d-none');
    $('caja-buscar-colonia').classList.remove('d-none');
    $('buscar-colonia').focus();
  }

  // Desde Google llega un CP y quizá el nombre de la colonia: si coincide
  // con el catálogo se elige sola; si no, se ofrecen las del CP.
  async function coloniaDesdeCp(cp, coloniaTexto) {
    let filas;
    try { filas = await consultarColonias(cp); } catch (e) { return; }
    if (!filas.length || valores().colonia) return;
    const objetivo = normalizar(coloniaTexto);
    const match = objetivo && (filas.find(c => normalizar(c.asentamiento) === objetivo) ||
      filas.find(c => normalizar(c.asentamiento).includes(objetivo) || objetivo.includes(normalizar(c.asentamiento))));
    if (match || filas.length === 1) { fijarColonia(match || filas[0], false); return; }
    ofrecerColonias(filas, `Elige la colonia (CP ${cp})`);
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

    // Si ya eligió colonia no se le cambia: SEPOMEX es más confiable que
    // lo que diga OpenStreetMap.
    if (valores().colonia) return;

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
    if (selectMunicipio.value !== municipioMatch || selectColonia.disabled) {
      selectMunicipio.value = municipioMatch;
      await cargarColonias(estadoMatch, municipioMatch);
    }
    const coloniaTexto = a.suburb || a.neighbourhood || a.quarter || a.residential || '';
    const coloniaMatch = buscarCoincidencia(selectColonia, coloniaTexto);
    if (coloniaMatch) {
      selectColonia.value = coloniaMatch;
      const opt = selectColonia.selectedOptions[0];
      if (opt && opt.dataset.cp) inputCp.value = opt.dataset.cp;
      mostrarResumenColonia();
    }
  }

  // ------------------------------------------------------------------
  // Paso 2: pestañas Buscar / Me mandaron la ubicación / Estoy aquí
  // ------------------------------------------------------------------
  function mostrarPanelLink() {
    const panel = $('panel-link');
    panel.classList.toggle('d-none');
    if (!panel.classList.contains('d-none')) $('pegar-ubicacion').focus();
  }

  function usarMiUbicacion() {
    if (!('geolocation' in navigator)) return alert('Tu navegador no soporta geolocalización.');
    const btn = $('btn-mi-ubicacion');
    const textoOriginal = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Obteniendo tu ubicación...';
    const restaurar = () => { btn.disabled = false; btn.innerHTML = textoOriginal; };
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        restaurar();
        // Ubicación aproximada (±1 km o más): el pin quedaría lejos del cliente
        // -- así nacen los pines mal puestos que luego salen 'Fuera de zona'.
        // No se pone; se explica cómo activar la ubicación exacta.
        if (typeof UMBRAL_UBICACION_APROXIMADA_M !== 'undefined' && pos.coords.accuracy > UMBRAL_UBICACION_APROXIMADA_M) {
          alert(textoUbicacionAproximada(pos.coords.accuracy));
          return;
        }
        ponerMarcador(pos.coords.latitude, pos.coords.longitude);
        // Estando en el lugar con buen GPS el pin queda confirmado desde ya
        // (el servidor decide con CORRECCION_PRECISION_MAX_M).
        $('ubicacion-precision').value = pos.coords.accuracy;
      },
      (err) => {
        restaurar();
        // Bloqueada en el teléfono: pasos según su teléfono (vendedor.js,
        // cargado antes en nuevo_cliente.php / editar_cliente.php).
        alert(err.code === 1 && typeof textoUbicacionBloqueada === 'function'
          ? textoUbicacionBloqueada()
          : (typeof textoUbicacionNoDisponible === 'function' ? textoUbicacionNoDisponible() : 'No se pudo obtener tu ubicación. Sal a espacio abierto o acércate a una ventana e intenta de nuevo.'));
      },
      { enableHighAccuracy: true, timeout: 15000 }
    );
  }

  // ------------------------------------------------------------------
  // API pública
  // ------------------------------------------------------------------
  function valores() {
    const opt = selectColonia.selectedOptions[0];
    const colonia = selectColonia.value;
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
    mostrarResumenColonia();
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
    // Precisión del GPS cuando el pin vino de "Estoy aquí" (se manda con el
    // formulario, junto a lat/lng).
    if (!$('ubicacion-precision')) {
      const oculto = document.createElement('input');
      oculto.type = 'hidden'; oculto.id = 'ubicacion-precision'; oculto.name = 'ubicacion_precision';
      $('lat').insertAdjacentElement('afterend', oculto);
    }
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

    campoCalle.addEventListener('input', () => { calleEditadaAMano = campoCalle.value.trim() !== ''; });

    // Paso 1
    $('buscar-colonia').addEventListener('input', () => {
      clearTimeout(temporizadorColonia);
      temporizadorColonia = setTimeout(buscarColonia, 350);
    });
    $('btn-cambiar-colonia').addEventListener('click', cambiarColonia);
    $('btn-colonia-manual').addEventListener('click', () => $('colonia-manual').classList.toggle('d-none'));
    selectEstado.addEventListener('change', () => cargarMunicipios(selectEstado.value));
    selectMunicipio.addEventListener('change', () => cargarColonias(selectEstado.value, selectMunicipio.value));
    selectColonia.addEventListener('change', () => {
      const opt = selectColonia.selectedOptions[0];
      if (opt && opt.dataset.cp) inputCp.value = opt.dataset.cp;
      mostrarResumenColonia();
      centrarEnColonia();
    });

    // Paso 2
    $('btn-link-ubicacion').addEventListener('click', mostrarPanelLink);
    $('btn-mi-ubicacion').addEventListener('click', usarMiUbicacion);

    // Mientras escribe la calle se sugieren direcciones para ubicarla, solo
    // si todavía no hay pin (después ya no se le mueve el punto).
    campoCalle.addEventListener('input', () => {
      clearTimeout(temporizadorBusqueda);
      if ($('lat').value || campoCalle.value.trim().length < 4) { busquedaActual++; pintarResultados(''); return; }
      temporizadorBusqueda = setTimeout(buscarDireccion, CFG.googleKey ? 350 : 800);
    });

    const campoLink = $('pegar-ubicacion');
    campoLink.addEventListener('paste', () => setTimeout(procesarLink, 0));
    campoLink.addEventListener('change', procesarLink);

    // Enter en estos campos no debe mandar el formulario a medio llenar.
    [['buscar-colonia', buscarColonia], ['calle-numero', buscarDireccion], ['pegar-ubicacion', procesarLink]].forEach(([id, fn]) => {
      $(id).addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); fn(); } });
    });

    // Con clave de Google se precarga la librería para que la primera
    // búsqueda no tarde.
    if (CFG.googleKey) cargarGoogle().catch(e => { googleFallo = true; console.error(e); });

    estadosListos = cargarEstados();
    return estadosListos;
  }

  return { init, precargar, valores, extraerCoordenadas, limpiarConsulta };
})();
