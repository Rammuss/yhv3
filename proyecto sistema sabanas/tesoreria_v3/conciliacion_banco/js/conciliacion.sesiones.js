(() => {
  const API = {
    sesiones: 'sesiones_api.php',
    reportes: 'reportes_api.php',
    cuentas: '../bancos/cuentas_bancarias_api.php',
    saldo: 'saldo_libro_api.php'
  };

  const store = window.ConcStore = {
    state: {
      cuentas: [],
      sesiones: [],
      conciliacion: null,
      detalle: null
    },
    listeners: {},
    set(key, value) {
      this.state[key] = value;
      this.emit(key, value);
    },
    emit(event, payload) {
      (this.listeners[event] || []).forEach(cb => cb(payload));
    },
    on(event, cb) {
      (this.listeners[event] ||= []).push(cb);
      return () => {
        this.listeners[event] = (this.listeners[event] || []).filter(fn => fn !== cb);
      };
    }
  };

  const els = {};
  const state = { loading: false, selectedId: null };

  document.addEventListener('DOMContentLoaded', init);

  function init() {
    cacheElements();
    attachEvents();
    loadCuentas().then(loadSesiones).catch(handleError);
  }

  function cacheElements() {
    els.fCuenta = document.getElementById('f-cuenta');
    els.fEstado = document.getElementById('f-estado');
    els.fDesde = document.getElementById('f-desde');
    els.fHasta = document.getElementById('f-hasta');
    els.btnFiltrar = document.getElementById('btn-filtrar');
    els.btnReset = document.getElementById('btn-reset');
    els.btnNueva = document.getElementById('btn-nueva');
    els.btnCrearConc = document.getElementById('btn-crear-conc');
    els.btnDescargar = document.getElementById('btn-descargar');
    els.btnCerrar = document.getElementById('btn-accion-cerrar');
    els.btnReabrir = document.getElementById('btn-accion-reabrir');
    els.listSesiones = document.getElementById('sesiones-list');
    els.kpiId = document.getElementById('kpi-id');
    els.kpiPeriodo = document.getElementById('kpi-periodo');
    els.kpiDif = document.getElementById('kpi-diferencia');
    els.badgeEstado = document.getElementById('badge-estado');
    els.kpiPendMov = document.getElementById('kpi-pend-mov');
    els.kpiPendMovDesc = document.getElementById('kpi-pend-mov-desc');
    els.kpiPendExt = document.getElementById('kpi-pend-ext');
    els.kpiPendExtDesc = document.getElementById('kpi-pend-ext-desc');
    els.modalNueva = document.getElementById('modal-nueva');
    els.formNueva = document.getElementById('form-nueva');
    els.nuevaError = document.getElementById('nueva-error');
  }

  function attachEvents() {
    els.btnFiltrar.addEventListener('click', () => loadSesiones().catch(handleError));
    els.btnReset.addEventListener('click', resetFiltros);
    els.btnNueva.addEventListener('click', () => toggleModal(els.modalNueva, true));
    els.btnCrearConc.addEventListener('click', crearConciliacion);
    els.btnCerrar?.addEventListener('click', () => cambiarEstado('cerrar'));
    els.btnReabrir?.addEventListener('click', () => cambiarEstado('reabrir'));
    document.querySelectorAll('[data-close]').forEach(btn => {
      btn.addEventListener('click', () => {
        const modal = btn.closest('.modal-backdrop');
        if (modal) toggleModal(modal, false);
      });
    });
    if (els.formNueva) {
      ['id_cuenta_bancaria','fecha_desde','fecha_hasta'].forEach(name => {
        const input = els.formNueva.elements[name];
        if (input) input.addEventListener('change', debounce(precalcularSaldos, 250));
      });
    }
  }

  async function loadCuentas() {
    const data = await fetchJSON(`${API.cuentas}?withTotals=false`);
    const cuentas = data.data || [];
    store.set('cuentas', cuentas);
    renderCuentas(cuentas);
  }

  function renderCuentas(cuentas) {
    const opts = ['<option value="">Todas</option>']
      .concat(cuentas.map(c => `<option value="${c.id_cuenta_bancaria}">${c.banco} - ${c.numero_cuenta}</option>`));
    els.fCuenta.innerHTML = opts.join('');

    const selectModal = els.formNueva?.querySelector('select[name="id_cuenta_bancaria"]');
    if (selectModal) {
      selectModal.innerHTML = cuentas.map(c => `<option value="${c.id_cuenta_bancaria}">${c.banco} - ${c.numero_cuenta}</option>`).join('');
    }
  }

  async function loadSesiones() {
    state.loading = true;
    const params = new URLSearchParams();
    if (els.fCuenta.value) params.set('id_cuenta_bancaria', els.fCuenta.value);
    if (els.fEstado.value) params.set('estado', els.fEstado.value);
    if (els.fDesde.value) params.set('desde', els.fDesde.value);
    if (els.fHasta.value) params.set('hasta', els.fHasta.value);

    const data = await fetchJSON(`${API.sesiones}?${params.toString()}`);
    const sesiones = data.data || [];
    store.set('sesiones', sesiones);
    renderSesiones(sesiones);
    if (sesiones.length) {
      const target = sesiones.find(s => s.id_conciliacion === state.selectedId) || sesiones[0];
      selectConciliacion(target.id_conciliacion);
    } else {
      selectConciliacion(null);
    }
    state.loading = false;
  }

  function renderSesiones(sesiones) {
    if (!sesiones.length) {
      els.listSesiones.innerHTML = '<div class="empty">No se encontraron conciliaciones con los filtros seleccionados.</div>';
      return;
    }
    els.listSesiones.innerHTML = sesiones.map(s => {
      const periodo = `${s.fecha_desde} -> ${s.fecha_hasta}`;
      const estado = `<span class="badge ${badgeClass(s.estado)}">${s.estado}</span>`;
      return `
        <div class="session-card${s.id_conciliacion === state.selectedId ? ' active' : ''}" data-id="${s.id_conciliacion}">
          <div class="meta">
            <strong>Conciliacion #${s.id_conciliacion}</strong>
            <span>${s.banco} - ${s.numero_cuenta} (${s.moneda})</span>
            <span>Periodo: ${periodo}</span>
          </div>
          <div style="display:flex; gap:8px; align-items:center;">
            ${estado}
            <button class="primary" data-select="${s.id_conciliacion}">Ver</button>
          </div>
        </div>
      `;
    }).join('');

    els.listSesiones.querySelectorAll('[data-select]').forEach(btn => {
      btn.addEventListener('click', () => selectConciliacion(parseInt(btn.dataset.select, 10)));
    });
  }

  function badgeClass(estado) {
    if (estado === 'Abierta') return 'warn';
    if (estado === 'Cerrada') return 'ok';
    if (estado === 'Anulada') return 'danger';
    return '';
  }

  function updateSelectionCard() {
    els.listSesiones.querySelectorAll('.session-card').forEach(card => {
      const id = parseInt(card.dataset.id, 10);
      card.classList.toggle('active', id === state.selectedId);
    });
  }

  async function selectConciliacion(id) {
    state.selectedId = id;
    updateSelectionCard();
    if (!id) {
      store.set('conciliacion', null);
      store.set('detalle', null);
      renderKpis(null, null);
      return;
    }
    const conc = store.state.sesiones.find(s => s.id_conciliacion === id);
    if (conc) store.set('conciliacion', conc);
    const detail = await fetchJSON(`${API.reportes}?id_conciliacion=${id}`);
    store.set('detalle', detail);
    renderKpis(detail.conciliacion, detail);
    window.dispatchEvent(new CustomEvent('conciliacion:detalle', { detail }));
  }

  function renderKpis(conc, detail) {
    if (!conc) {
      els.kpiId.textContent = '--';
      els.kpiPeriodo.textContent = 'Sin seleccion';
      els.kpiDif.textContent = '--';
      els.badgeEstado.textContent = 'Sin estado';
      els.badgeEstado.className = 'badge';
      els.kpiPendMov.textContent = '--';
      els.kpiPendMovDesc.textContent = '';
    els.kpiPendExt.textContent = '--';
    els.kpiPendExtDesc.textContent = '';
    if (els.btnCerrar) els.btnCerrar.disabled = true;
    if (els.btnReabrir) els.btnReabrir.disabled = true;
    if (els.btnCerrar) els.btnCerrar.dataset.idConciliacion = '';
    if (els.btnReabrir) els.btnReabrir.dataset.idConciliacion = '';
    if (els.btnDescargar) els.btnDescargar.dataset.idConciliacion = '';
    return;
  }
    els.kpiId.textContent = `#${conc.id_conciliacion}`;
    els.kpiPeriodo.textContent = `${conc.fecha_desde} -> ${conc.fecha_hasta}`;
    els.kpiDif.textContent = formatMoneda(conc.diferencia_final, conc.moneda || 'PYG');
    els.badgeEstado.textContent = conc.estado;
    els.badgeEstado.className = `badge ${badgeClass(conc.estado)}`;

    const det = detail || {};
    const mPend = det.movimientos_pendientes || [];
    const ePend = det.extracto_pendiente || [];
    const movTotal = mPend.reduce((acc, row) => acc + (parseFloat(row.monto) * parseInt(row.signo, 10)), 0);
    const extTotal = ePend.reduce((acc, row) => acc + (parseFloat(row.monto) * parseInt(row.signo, 10)), 0);

    els.kpiPendMov.textContent = `${mPend.length} movimientos`;
    els.kpiPendMovDesc.textContent = `Saldo: ${formatMoneda(movTotal, conc.moneda || 'PYG')}`;
    els.kpiPendExt.textContent = `${ePend.length} registros`;
    els.kpiPendExtDesc.textContent = `Saldo: ${formatMoneda(extTotal, conc.moneda || 'PYG')}`;

    if (els.btnCerrar) {
      const habilitarCerrar = conc.estado === 'Abierta' && Math.abs(Number(conc.diferencia_final || 0)) < 0.01 && !mPend.length && !ePend.length;
      els.btnCerrar.disabled = !habilitarCerrar;
      els.btnCerrar.dataset.idConciliacion = conc.id_conciliacion;
    }
    if (els.btnReabrir) {
      els.btnReabrir.disabled = conc.estado !== 'Cerrada';
      els.btnReabrir.dataset.idConciliacion = conc.id_conciliacion;
    }
    if (els.btnDescargar) {
      els.btnDescargar.dataset.idConciliacion = conc.id_conciliacion;
      els.btnDescargar.disabled = false;
    }
  }

  function resetFiltros() {
    els.fCuenta.value = '';
    els.fEstado.value = '';
    els.fDesde.value = '';
    els.fHasta.value = '';
    loadSesiones().catch(handleError);
  }

  async function crearConciliacion() {
    if (!els.formNueva) return;
    els.nuevaError.style.display = 'none';
    const formData = new FormData(els.formNueva);
    const payload = Object.fromEntries(formData.entries());
    payload.saldo_libro_inicial = parseFloat(payload.saldo_libro_inicial || 0);
    payload.saldo_libro_final = parseFloat(payload.saldo_libro_final || 0);
    payload.saldo_banco_inicial = parseFloat(payload.saldo_banco_inicial || 0);
    payload.saldo_banco_final = parseFloat(payload.saldo_banco_final || 0);

    try {
      await fetchJSON(API.sesiones, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      toggleModal(els.modalNueva, false);
      els.formNueva.reset();
      await loadSesiones();
    } catch (err) {
      els.nuevaError.textContent = err.message;
      els.nuevaError.style.display = 'inline-flex';
    }
  }

  function toggleModal(modal, show) {
    if (!modal) return;
    modal.classList.toggle('active', show);
    if (!show) {
      const err = modal.querySelector('.badge.danger');
      if (err) err.style.display = 'none';
    } else if (modal === els.modalNueva) {
      precalcularSaldos();
    }
  }

  async function fetchJSON(url, options = {}) {
    const res = await fetch(url, options);
    const data = await res.json().catch(() => ({}));
    if (!res.ok || data.ok === false) {
      throw new Error(data.error || 'Error de red');
    }
    return data;
  }

  function formatMoneda(value, moneda) {
    const num = Number(value || 0);
    return `${moneda} ${num.toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
  }

  function handleError(err) {
    console.error(err);
    alert(err.message || 'Ocurrio un error inesperado.');
  }

  function precalcularSaldos() {
    if (!els.formNueva) return;
    const form = els.formNueva;
    const idCuenta = form.elements['id_cuenta_bancaria']?.value;
    const desde = form.elements['fecha_desde']?.value;
    const hasta = form.elements['fecha_hasta']?.value;
    if (!idCuenta || !desde || !hasta) return;
    if (els.nuevaError) els.nuevaError.style.display = 'none';

    fetchJSON(`${API.saldo}?id_cuenta_bancaria=${idCuenta}&fecha_desde=${desde}&fecha_hasta=${hasta}`)
      .then(data => {
        form.elements['saldo_libro_inicial'].value = Number(data.saldo_libro_inicial || 0).toFixed(2);
        form.elements['saldo_libro_final'].value = Number(data.saldo_libro_final || 0).toFixed(2);
        if (els.nuevaError) els.nuevaError.style.display = 'none';
      })
      .catch(err => {
        if (els.nuevaError) {
          els.nuevaError.textContent = err.message;
          els.nuevaError.style.display = 'inline-flex';
        } else {
          console.error(err);
        }
      });
  }

  function debounce(fn, wait) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(null, args), wait);
    };
  }

  window.ConciliacionActions = {
    refreshDetalle() {
      if (state.selectedId) selectConciliacion(state.selectedId);
    },
    getSeleccion() {
      return state.selectedId;
    }
  };

  async function cambiarEstado(accion) {
    const btn = accion === 'cerrar' ? els.btnCerrar : els.btnReabrir;
    const idAttr = btn?.dataset.idConciliacion;
    const idConc = Number(idAttr || state.selectedId || 0);
    if (!idConc) return alert('Seleccione una conciliacion.');
    try {
      await fetchJSON(API.sesiones, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_conciliacion: idConc, accion })
      });
      await loadSesiones();
    } catch (err) {
      handleError(err);
    }
  }
})();
