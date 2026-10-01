// Hoja "Cancelar cita" del vendedor (Inicio). Sube desde abajo igual que
// v26Sheet / reprogramar-cita.js (mismo backdrop y estilos .reprog-*).
// Todo es opcional: motivo rápido "El cliente canceló", texto libre y una
// imagen de evidencia (captura de WhatsApp, correo, foto...). La imagen se
// achica aquí antes de subirla; la última palabra la tiene
// api/cancelar_cita.php.
// Uso: cancelarCitaHoja(id, nombre, onCancelada)

const CANC_MAX_DIM = 1400;   // px; más que en el check-in para que se lean las capturas de pantalla
const CANC_CALIDAD = 0.8;
const CANC_MAX_MB = 8;       // = MAX_MB_EVIDENCIA_CANCELACION en helpers.php

function cancEsc(s) {
  return String(s ?? '').replace(/[&<>"]/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
}

// Achica la imagen a CANC_MAX_DIM y la pasa a JPEG. Si el navegador no
// puede (formato raro), regresa el archivo original tal cual.
function cancAchicarImagen(file) {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      let w = img.naturalWidth, h = img.naturalHeight;
      const escala = Math.min(1, CANC_MAX_DIM / Math.max(w, h));
      w = Math.round(w * escala); h = Math.round(h * escala);
      const canvas = document.createElement('canvas');
      canvas.width = w; canvas.height = h;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = '#fff'; // PNG con transparencia -> fondo blanco en JPEG
      ctx.fillRect(0, 0, w, h);
      ctx.drawImage(img, 0, 0, w, h);
      URL.revokeObjectURL(url);
      try {
        canvas.toBlob(blob => resolve(blob && blob.size < file.size ? blob : file), 'image/jpeg', CANC_CALIDAD);
      } catch (e) {
        resolve(file);
      }
    };
    img.onerror = () => { URL.revokeObjectURL(url); resolve(file); };
    img.src = url;
  });
}

function cancelarCitaHoja(id, nombre, onCancelada) {
  const st = { delCliente: false, foto: null, previewUrl: null, procesando: false, enviando: false, error: '' };

  document.getElementById('canc-backdrop')?.remove();
  const backdrop = document.createElement('div');
  backdrop.id = 'canc-backdrop';
  backdrop.className = 'v26-modal-backdrop';
  backdrop.innerHTML = `
    <div class="v26-modal reprog" role="dialog" aria-modal="true" aria-labelledby="canc-titulo">
      <h3 id="canc-titulo">¿Cancelar la cita con ${cancEsc(nombre)}?</h3>
      <p class="text-muted small mb-0">Esta acción no se puede deshacer.</p>
      <div class="reprog-lbl">Motivo <span class="fw-normal text-lowercase">(opcional)</span></div>
      <div class="reprog-motivos">
        <button type="button" class="reprog-motivo" id="canc-cliente" aria-pressed="false"><i class="bi bi-telephone-x"></i>El cliente canceló</button>
      </div>
      <textarea id="canc-texto" class="v26-textarea mt-2" rows="2" maxlength="200" placeholder="Detalle (opcional)"></textarea>
      <div class="reprog-lbl">Evidencia <span class="fw-normal text-lowercase">(opcional)</span></div>
      <input type="file" id="canc-archivo" accept="image/*" class="d-none">
      <button type="button" id="canc-adjuntar" class="canc-adjuntar"><i class="bi bi-paperclip"></i> Adjuntar imagen<small>Captura de WhatsApp, correo o foto</small></button>
      <div id="canc-preview" class="canc-preview d-none">
        <img id="canc-preview-img" alt="Evidencia">
        <div class="canc-preview-info"><b>Evidencia lista</b><span id="canc-preview-peso"></span></div>
        <button type="button" id="canc-quitar" class="canc-quitar" aria-label="Quitar evidencia"><i class="bi bi-x-lg"></i></button>
      </div>
      <div id="canc-alerta"></div>
      <div class="d-flex gap-2 mt-3">
        <button type="button" id="canc-volver" class="v26-btn v26-btn-ghost v26-btn-block">No, volver</button>
        <button type="button" id="canc-confirmar" class="v26-btn v26-btn-primary v26-btn-block">Sí, cancelar</button>
      </div>
    </div>`;
  document.body.appendChild(backdrop);
  requestAnimationFrame(() => backdrop.classList.add('show'));

  const $ = sel => backdrop.querySelector('#' + sel);
  const elArchivo = $('canc-archivo'), btnConfirmar = $('canc-confirmar');

  function cerrar() {
    backdrop.classList.remove('show');
    if (st.previewUrl) URL.revokeObjectURL(st.previewUrl);
    setTimeout(() => backdrop.remove(), 200);
  }

  function pintar() {
    const chip = $('canc-cliente');
    chip.classList.toggle('on', st.delCliente);
    chip.setAttribute('aria-pressed', String(st.delCliente));
    $('canc-adjuntar').classList.toggle('d-none', !!st.foto || st.procesando);
    $('canc-preview').classList.toggle('d-none', !st.foto);
    if (st.foto) {
      $('canc-preview-img').src = st.previewUrl;
      $('canc-preview-peso').textContent = `${Math.max(1, Math.round(st.foto.size / 1024))} KB`;
    }
    const alerta = st.procesando ? '<div class="reprog-hint">Preparando imagen…</div>'
      : (st.error ? `<div class="reprog-error"><i class="bi bi-exclamation-triangle"></i> ${cancEsc(st.error)}</div>` : '');
    $('canc-alerta').innerHTML = alerta;
    btnConfirmar.disabled = st.enviando || st.procesando;
    btnConfirmar.textContent = st.enviando ? 'Cancelando…' : 'Sí, cancelar';
  }

  async function alElegirArchivo() {
    const file = elArchivo.files && elArchivo.files[0];
    elArchivo.value = '';
    if (!file) return;
    st.error = '';
    if (file.type && !/^image\//.test(file.type)) { // algunos selectores de Android mandan type vacío
      st.error = 'Solo se pueden adjuntar imágenes.';
      return pintar();
    }
    st.procesando = true;
    pintar();
    const blob = await cancAchicarImagen(file);
    st.procesando = false;
    if (blob.size > CANC_MAX_MB * 1024 * 1024) {
      st.error = `La imagen pesa más de ${CANC_MAX_MB} MB. Elige otra o toma una captura.`;
      return pintar();
    }
    if (st.previewUrl) URL.revokeObjectURL(st.previewUrl);
    st.foto = blob;
    st.previewUrl = URL.createObjectURL(blob);
    pintar();
  }

  async function confirmar() {
    st.enviando = true;
    st.error = '';
    pintar();
    const fd = new FormData();
    fd.append('cita_id', id);
    fd.append('motivo', $('canc-texto').value.trim());
    if (st.delCliente) fd.append('motivo_tipo', 'cliente');
    if (st.foto) fd.append('foto', st.foto, st.foto.type === 'image/jpeg' ? 'evidencia.jpg' : (st.foto.name || 'evidencia'));
    try {
      const res = await fetch('../api/cancelar_cita.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        cerrar();
        if (onCancelada) onCancelada();
        return;
      }
      st.error = data.error || 'No se pudo cancelar la cita.';
    } catch (e) {
      st.error = 'Error de conexión. Intenta de nuevo.';
    }
    st.enviando = false;
    pintar();
  }

  backdrop.addEventListener('click', e => { if (e.target === backdrop && !st.enviando) cerrar(); });
  $('canc-cliente').addEventListener('click', () => { st.delCliente = !st.delCliente; pintar(); });
  $('canc-adjuntar').addEventListener('click', () => elArchivo.click());
  elArchivo.addEventListener('change', alElegirArchivo);
  $('canc-quitar').addEventListener('click', () => {
    if (st.previewUrl) URL.revokeObjectURL(st.previewUrl);
    st.foto = null; st.previewUrl = null; st.error = '';
    pintar();
  });
  $('canc-volver').addEventListener('click', () => { if (!st.enviando) cerrar(); });
  btnConfirmar.addEventListener('click', confirmar);
  pintar();
}
