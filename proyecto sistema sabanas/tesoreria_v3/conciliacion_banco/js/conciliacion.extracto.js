(() => {
  const store = window.ConcStore;
  if (!store) return;

  const API = { extracto: 'extracto_api.php' };

  const els = {
    tblMov: document.getElementById('tbl-movimientos'),
    tblExt: document.getElementById('tbl-extracto'),
    btnCarga: document.getElementById('btn-cargar-extracto'),
    modalExtr: document.getElementById('modal-extracto'),
    formExtr: document.getElementById('form-extracto'),
    btnSubir: document.getElementById('btn-subir-extracto'),
    errExtr: document.getElementById('extracto-error')
  };

  const selection = { mov: null, ext: null };

  if (!els.tblMov || !els.tblExt) return;

  store.on('detalle', detail => renderDetalle(detail));

  document.addEventListener('DOMContentLoaded', () => {
    els.btnCarga?.addEventListener('click', () => toggleModal(els.modalExtr, true));
    els.btnSubir?.addEventListener('click', subirExtracto);
    els.tblMov.addEventListener('click', onClickMov);
    els.tblExt.addEventListener('click', onClickExt);
  });

  function renderDetalle(detail) {
    if (!detail || !detail.conciliacion) {
      renderEmpty(els.tblMov, 5, 'Seleccione una conciliacion para ver movimientos.');
      renderEmpty(els.tblExt, 5, 'Sin datos de extracto.');
      selection.mov = null;
      selection.ext = null;
      store.set('selected_mov', null);
      store.set('selected_extracto', null);
      return;
    }

    const moneda = detail.conciliacion.moneda || 'PYG';
    const movPend = detail.movimientos_pendientes || [];
    const movConc = detail.movimientos_conciliados?.map(m => ({ ...m, estado: 'Conciliado' })) || [];
    const movRows = movPend.map(m => ({ ...m, estado: 'Pendiente' })).concat(movConc);
    renderMovimientos(movRows, moneda);

    const extPend = detail.extracto_pendiente || [];
    const extConc = detail.extracto_conciliado?.map(e => ({ ...e, estado: 'Conciliado' })) || [];
    const extRows = extPend.map(e => ({ ...e, estado: 'Pendiente' })).concat(extConc);
    renderExtracto(extRows, moneda);
  }

  function renderMovimientos(rows, moneda) {
    if (!rows.length) {
      renderEmpty(els.tblMov, 5, 'Sin movimientos en el periodo.');
      return;
    }
    els.tblMov.innerHTML = rows.map(row => `
      <tr data-id="${row.id_mov}" data-estado="${row.estado}">
        <td><input type="radio" name="mov-select" value="${row.id_mov}"></td>
        <td>${row.fecha ?? ''}</td>
        <td>${escapeHtml(row.descripcion ?? '')}</td>
        <td class="right">${formatMoneda(row.monto * (row.signo || 1), moneda)}</td>
        <td><span class="badge ${row.estado === 'Conciliado' ? 'ok' : 'warn'}">${row.estado}</span></td>
      </tr>
    `).join('');
  }

  function renderExtracto(rows, moneda) {
    if (!rows.length) {
      renderEmpty(els.tblExt, 5, 'Sin registros de extracto.');
      return;
    }
    els.tblExt.innerHTML = rows.map(row => `
      <tr data-id="${row.id_extracto}" data-estado="${row.estado}">
        <td><input type="radio" name="ext-select" value="${row.id_extracto}"></td>
        <td>${row.fecha ?? ''}</td>
        <td>${escapeHtml(row.descripcion ?? '')}</td>
        <td class="right">${formatMoneda(row.monto * (row.signo || 1), moneda)}</td>
        <td><span class="badge ${row.estado === 'Conciliado' ? 'ok' : 'warn'}">${row.estado}</span></td>
      </tr>
    `).join('');
  }

  function onClickMov(e) {
    const row = e.target.closest('tr[data-id]');
    if (!row) return;
    const id = parseInt(row.dataset.id, 10);
    const estado = row.dataset.estado;
    selection.mov = { id };
    updateSelection(els.tblMov, row);
    store.set('selected_mov', estado === 'Conciliado' ? null : id);
  }

  function onClickExt(e) {
    const row = e.target.closest('tr[data-id]');
    if (!row) return;
    const id = parseInt(row.dataset.id, 10);
    const estado = row.dataset.estado;
    selection.ext = { id };
    updateSelection(els.tblExt, row);
    store.set('selected_extracto', estado === 'Conciliado' ? null : id);
  }

  async function subirExtracto() {
    if (!els.formExtr) return;
    els.errExtr.style.display = 'none';
    const concId = store.state.conciliacion?.id_conciliacion;
    if (!concId) {
      els.errExtr.textContent = 'Seleccione una conciliacion primero.';
      els.errExtr.style.display = 'inline-flex';
      return;
    }
    const formData = new FormData(els.formExtr);
    formData.set('id_conciliacion', concId);
    try {
      const res = await fetch(API.extracto, { method: 'POST', body: formData });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || data.ok === false) throw new Error(data.error || 'No se pudo cargar el extracto');
      toggleModal(els.modalExtr, false);
      els.formExtr.reset();
      els.errExtr.style.display = 'none';
      window.ConciliacionActions?.refreshDetalle();
    } catch (err) {
      console.error(err);
      els.errExtr.textContent = err.message || 'Error al subir archivo.';
      els.errExtr.style.display = 'inline-flex';
    }
  }

  function updateSelection(tbody, row) {
    tbody.querySelectorAll('tr').forEach(tr => tr.classList.remove('selected'));
    row.classList.add('selected');
  }

  function renderEmpty(tbody, cols, message) {
    tbody.innerHTML = `<tr><td colspan="${cols}" class="empty">${message}</td></tr>`;
  }

  function toggleModal(modal, show) {
    if (!modal) return;
    modal.classList.toggle('active', show);
    if (!show) {
      const err = modal.querySelector('.badge.danger');
      if (err) err.style.display = 'none';
    }
  }

  function formatMoneda(value, moneda) {
    const num = Number(value || 0);
    return `${moneda} ${num.toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
  }

  function escapeHtml(str) {
    return str.replace(/[&<>"']/g, s => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    }[s]));
  }
})();
