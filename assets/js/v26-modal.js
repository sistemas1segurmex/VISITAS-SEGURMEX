// Hoja/modal ligero y reutilizable para pedir confirmación + un motivo de
// texto (usado para cancelar una cita y para reportar "cliente no llegó").
// v26Sheet(opciones) regresa una Promise que resuelve con { motivo } si el
// usuario confirma, o null si cancela.

function v26Sheet(opciones) {
  const {
    titulo,
    desc = '',
    pedirMotivo = true,
    motivoRequerido = false,
    placeholderMotivo = 'Motivo (opcional)',
    textoConfirmar = 'Confirmar',
    textoCancelar = 'Volver',
  } = opciones;

  return new Promise((resolve) => {
    let backdrop = document.getElementById('v26-modal-backdrop');
    if (!backdrop) {
      backdrop = document.createElement('div');
      backdrop.id = 'v26-modal-backdrop';
      backdrop.className = 'v26-modal-backdrop d-none';
      backdrop.innerHTML = `
        <div class="v26-modal">
          <h3 id="v26-modal-title"></h3>
          <p id="v26-modal-desc" class="text-muted small mb-2"></p>
          <textarea id="v26-modal-motivo" class="v26-textarea" rows="2"></textarea>
          <div id="v26-modal-error" class="text-danger small mt-2 d-none"></div>
          <div class="d-flex gap-2 mt-3">
            <button type="button" id="v26-modal-cancel" class="v26-btn v26-btn-ghost v26-btn-block"></button>
            <button type="button" id="v26-modal-confirm" class="v26-btn v26-btn-primary v26-btn-block"></button>
          </div>
        </div>`;
      document.body.appendChild(backdrop);
    }

    const elTitle   = backdrop.querySelector('#v26-modal-title');
    const elDesc    = backdrop.querySelector('#v26-modal-desc');
    const elMotivo  = backdrop.querySelector('#v26-modal-motivo');
    const elError   = backdrop.querySelector('#v26-modal-error');
    const btnCancel = backdrop.querySelector('#v26-modal-cancel');
    const btnConfirm = backdrop.querySelector('#v26-modal-confirm');

    elTitle.textContent = titulo;
    elDesc.textContent = desc;
    elDesc.classList.toggle('d-none', !desc);
    elMotivo.classList.toggle('d-none', !pedirMotivo);
    elMotivo.value = '';
    elMotivo.placeholder = placeholderMotivo;
    elError.classList.add('d-none');
    btnCancel.textContent = textoCancelar;
    btnConfirm.textContent = textoConfirmar;

    backdrop.classList.remove('d-none');
    requestAnimationFrame(() => backdrop.classList.add('show'));

    function limpiar() {
      btnCancel.removeEventListener('click', onCancel);
      btnConfirm.removeEventListener('click', onConfirm);
      backdrop.removeEventListener('click', onBackdropClick);
    }

    function cerrar(resultado) {
      backdrop.classList.remove('show');
      limpiar();
      setTimeout(() => backdrop.classList.add('d-none'), 200);
      resolve(resultado);
    }

    function onCancel() { cerrar(null); }

    function onConfirm() {
      const motivo = elMotivo.value.trim();
      if (pedirMotivo && motivoRequerido && !motivo) {
        elError.textContent = 'Cuéntanos brevemente qué pasó.';
        elError.classList.remove('d-none');
        return;
      }
      cerrar({ motivo });
    }

    function onBackdropClick(e) {
      if (e.target === backdrop) onCancel();
    }

    btnCancel.addEventListener('click', onCancel);
    btnConfirm.addEventListener('click', onConfirm);
    backdrop.addEventListener('click', onBackdropClick);
  });
}
