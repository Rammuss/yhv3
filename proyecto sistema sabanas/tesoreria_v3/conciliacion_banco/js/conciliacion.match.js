(() => {
  const store = window.ConcStore;
  if (!store) return;

  const API = 'coincidencias_api.php';

  const els = {
    btnAuto: document.getElementById('btn-match-auto'),
    btnManual: document.getElementById('btn-match-manual'),
    btnMatchModal: document.getElementById('btn-match-ok'),
    modalMatch: document.getElementById('modal-match'),
    matchError: document.getElementById('match-error'),
    btnAjusteLibro: document.getElementById('btn-ajuste-libro'),
    btnAjusteBanco: document.getElementById('btn-ajuste-banco'),
    modalAjuste: document.getElementById('modal-ajuste'),
    btnAjusteOk: document.getElementById('btn-ajuste-ok'),
    ajusteError: document.getElementById('ajuste-error'),
    formAjuste: document.getElementById('form-ajuste')
  };

  const selection = { mov: null, ext: null };

  store.on('selected_mov', id => { selection.mov = id; });
  store.on('selected_extracto', id => { selection.ext = id; });

  document.addEventListener('DOMContentLoaded', () => {
    els.btnAuto?.addEventListener('click', autoMatch);
    els.btnManual?.addEventListener('click', openManualModal);
    els.btnMatchModal?.addEventListener('click', matchManual);
    document.querySelectorAll('#modal-match [data-close]').forEach(btn => {
      btn.addEventListener('click', () => toggleModal(els.modalMatch, false));
    });
    document.querySelectorAll('#modal-ajuste [data-close]').forEach(btn => {
      btn.addEventListener('click', () => toggleModal(els.modalAjuste, false));
    });
    els.btnAjusteLibro?.addEventListener('click', () => abrirAjuste('ajuste_libro'));
    els.btnAjusteBanco?.addEventListener('click', () => abrirAjuste('ajuste_banco'));
    els.btnAjusteOk?.addEventListener('click', registrarAjuste);
  });

  async function autoMatch() {
    const conc = store.state.conciliacion;
    if (!conc) return alert('Seleccione una conciliacion.');
    try {
      await postJSON({ accion: 'auto_match', id_conciliacion: conc.id_conciliacion });
      window.ConciliacionActions?.refreshDetalle();
      alert('Auto-match ejecutado.');
    } catch (err) {
      alert(err.message);
    }
  }

  async function matchManual() {
    if (!selection.mov || !selection.ext) {
      els.matchError.textContent = 'Seleccione un movimiento y un registro de extracto pendientes.';
      els.matchError.style.display = 'inline-flex';
      return;
    }
    const conc = store.state.conciliacion;
    if (!conc) return alert('Seleccione una conciliacion.');
    try {
      await postJSON({
        accion: 'match_manual',
        id_conciliacion: conc.id_conciliacion,
        id_movimiento: selection.mov,
        id_extracto: selection.ext
      });
      toggleModal(els.modalMatch, false);
      window.ConciliacionActions?.refreshDetalle();
    } catch (err) {
      els.matchError.textContent = err.message;
      els.matchError.style.display = 'inline-flex';
    }
  }

  function abrirAjuste(tipo) {
    const conc = store.state.conciliacion;
    if (!conc) return alert('Seleccione una conciliacion.');
    els.formAjuste.reset();
    els.formAjuste.elements['accion'].value = tipo;
    els.formAjuste.elements['id_conciliacion'].value = conc.id_conciliacion;
    els.ajusteError.style.display = 'none';
    const titulo = tipo === 'ajuste_libro' ? 'Ajuste a movimientos del sistema' : 'Ajuste al extracto bancario';
    document.getElementById('titulo-ajuste').textContent = titulo;
    toggleModal(els.modalAjuste, true);
  }

  async function registrarAjuste() {
    const formData = new FormData(els.formAjuste);
    const payload = Object.fromEntries(formData.entries());
    payload.monto = parseFloat(payload.monto || 0);
    payload.signo = parseInt(payload.signo || 1, 10);
    if (isNaN(payload.monto) || payload.monto <= 0) {
      els.ajusteError.textContent = 'Ingrese un monto valido.';
      els.ajusteError.style.display = 'inline-flex';
      return;
    }
    try {
      await postJSON(payload);
      toggleModal(els.modalAjuste, false);
      window.ConciliacionActions?.refreshDetalle();
    } catch (err) {
      els.ajusteError.textContent = err.message;
      els.ajusteError.style.display = 'inline-flex';
    }
  }

  function openManualModal() {
    els.matchError.style.display = 'none';
    if (!selection.mov || !selection.ext) {
      alert('Seleccione un movimiento pendiente y un registro de extracto pendiente para conciliar.');
      return;
    }
    toggleModal(els.modalMatch, true);
  }

  function toggleModal(modal, show) {
    if (!modal) return;
    modal.classList.toggle('active', show);
    if (!show) {
      const err = modal.querySelector('.badge.danger');
      if (err) err.style.display = 'none';
    }
  }

  async function postJSON(body) {
    const res = await fetch(API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body)
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
      throw new Error(data.error || 'Operacion no completada');
    }
    return data;
  }
})();
