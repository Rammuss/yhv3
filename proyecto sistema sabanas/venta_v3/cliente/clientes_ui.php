<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestión de Clientes</title>
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">

  <style>
    :root {
      color-scheme: light;
      font-family: "Segoe UI", Roboto, sans-serif;
    }
    body {
      margin: 0;
      background: #f6f8fb;
      color: #1f2933;
    }
    header {
      background: #1d4ed8;
      color: #fff;
      padding: 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
    }
    header h1 {
      margin: 0;
      font-size: 1.5rem;
    }
    header button {
      background: #2563eb;
      color: #fff;
      border: none;
      padding: 0.6rem 1.2rem;
      border-radius: 0.5rem;
      font-size: 0.95rem;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.2s ease;
    }
    header button:hover { background: #1e3aa8; }

    main {
      padding: 1.5rem 2rem 3rem;
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .card {
      background: #fff;
      border-radius: 0.8rem;
      padding: 1.2rem 1.5rem;
      box-shadow: 0 10px 30px rgba(17, 24, 39, 0.07);
    }

    form.search {
      display: flex;
      align-items: flex-end;
      gap: 1rem;
      flex-wrap: wrap;
    }
    form.search label {
      display: flex;
      flex-direction: column;
      font-size: 0.85rem;
      font-weight: 600;
      color: #475569;
    }
    form.search input,
    form.search select {
      margin-top: 0.3rem;
      border-radius: 0.5rem;
      border: 1px solid #cbd5f5;
      padding: 0.5rem 0.6rem;
      min-width: 220px;
      font: inherit;
      background: #f8fafc;
    }
    form.search button {
      margin-top: 0.3rem;
      padding: 0.55rem 1rem;
      border: none;
      border-radius: 0.5rem;
      font-weight: 600;
      cursor: pointer;
    }
    form.search button.primary {
      background: #2563eb;
      color: #fff;
    }
    form.search button.secondary {
      background: #e0e7ff;
      color: #1e3aa8;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
      overflow: hidden;
      border-radius: 0.8rem;
      box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
    }
    thead {
      background: #eef2ff;
      color: #1e3aa8;
    }
    th, td {
      padding: 0.75rem 1rem;
      text-align: left;
      font-size: 0.9rem;
      border-bottom: 1px solid #e2e8f0;
    }
    tbody tr:hover { background: rgba(59, 130, 246, 0.08); }
    .badge {
      display: inline-flex;
      align-items: center;
      gap: 0.3rem;
      padding: 0.2rem 0.6rem;
      border-radius: 999px;
      font-size: 0.75rem;
      font-weight: 600;
    }
    .badge.success { background: #dcfce7; color: #166534; }
    .badge.muted { background: #fee2e2; color: #b91c1c; }

    .table-actions {
      display: flex;
      gap: 0.5rem;
      flex-wrap: wrap;
    }
    .action-btn {
      padding: 0.35rem 0.75rem;
      border-radius: 0.45rem;
      border: none;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s ease, transform 0.1s ease;
    }
    .action-btn.edit { background: #2563eb; color: #fff; }
    .action-btn.toggle { background: #e0f2fe; color: #0369a1; }
    .action-btn:hover { transform: translateY(-1px); }

    .pagination {
      margin-top: 1rem;
      display: flex;
      align-items: center;
      gap: 0.6rem;
      flex-wrap: wrap;
      color: #475569;
      font-size: 0.9rem;
    }
    .pagination button {
      border: none;
      background: #e0e7ff;
      color: #1e3aa8;
      border-radius: 0.5rem;
      padding: 0.45rem 0.9rem;
      font-weight: 600;
      cursor: pointer;
    }
    .pagination button:disabled {
      opacity: 0.45;
      cursor: not-allowed;
    }

    .feedback {
      position: fixed;
      top: 1rem;
      right: 1rem;
      max-width: 320px;
      padding: 0.9rem 1rem;
      border-radius: 0.7rem;
      box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
      display: none;
      font-weight: 600;
    }
    .feedback.success { background: #dcfce7; color: #166534; }
    .feedback.error { background: #fee2e2; color: #991b1b; }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.45);
      backdrop-filter: blur(2px);
      display: none;
      place-items: center;
      padding: 1.5rem;
      z-index: 1000;
    }
    .modal-backdrop.active { display: grid; }

    .modal {
      background: #fff;
      border-radius: 1rem;
      width: min(560px, 100%);
      max-height: 90vh;
      overflow-y: auto;
      padding: 1.5rem 1.75rem;
      box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
      display: flex;
      flex-direction: column;
      gap: 1rem;
      position: relative;
    }
    .modal header {
      background: none;
      color: inherit;
      padding: 0;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }
    .modal header h2 {
      margin: 0;
      font-size: 1.3rem;
    }
    .close-modal {
      background: none;
      border: none;
      font-size: 1.5rem;
      cursor: pointer;
      line-height: 1;
      color: #475569;
    }
    .modal form {
      display: grid;
      gap: 1rem;
    }
    .modal form .field-group {
      display: grid;
      gap: 0.8rem;
    }
    .modal label {
      display: flex;
      flex-direction: column;
      font-size: 0.85rem;
      font-weight: 600;
      color: #475569;
      gap: 0.35rem;
    }
    .modal input,
    .modal select,
    .modal textarea {
      font: inherit;
      border-radius: 0.55rem;
      border: 1px solid #cbd5f5;
      padding: 0.55rem 0.6rem;
      background: #f8fafc;
    }
    .modal footer {
      display: flex;
      justify-content: flex-end;
      gap: 0.75rem;
    }
    .modal footer button {
      padding: 0.55rem 1.1rem;
      border-radius: 0.6rem;
      border: none;
      font-weight: 600;
      cursor: pointer;
    }
    footer button.secondary { background: #e2e8f0; color: #334155; }
    footer button.primary { background: #2563eb; color: #fff; }
    .form-error {
      color: #b91c1c;
      font-size: 0.85rem;
      min-height: 1em;
    }

    @media (max-width: 720px) {
      form.search label { min-width: 100%; }
      header { flex-direction: column; align-items: flex-start; }
      header button { width: 100%; }
      .table-actions { flex-direction: column; align-items: stretch; }
    }
  </style>
</head>
<body>
    <div id="navbar-container"></div>
    <script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>


  <div id="feedback" class="feedback"></div>

  <header>
    <h1>Gestión de Clientes</h1>
    <button id="new-client-btn">+ Nuevo cliente</button>
  </header>

  <main>
    <section class="card">
      <form id="search-form" class="search">
        <label>
          Búsqueda
          <input type="text" name="q" placeholder="Nombre, apellido, RUC/CI o teléfono">
        </label>
        <label>
          Estado
          <select name="activo">
            <option value="">Todos</option>
            <option value="true">Activos</option>
            <option value="false">Inactivos</option>
          </select>
        </label>
        <label>
          Tamaño de página
          <input type="number" name="page_size" value="10" min="1" max="100">
        </label>
        <div style="display:flex; gap:0.6rem;">
          <button type="submit" class="primary">Buscar</button>
          <button type="button" id="clear-filters" class="secondary">Limpiar</button>
        </div>
        <span id="search-status" style="color:#475569;"></span>
      </form>
    </section>

    <section class="card">
      <table id="clientes-table" aria-live="polite">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Apellido</th>
            <th>Dirección</th>
            <th>Teléfono</th>
            <th>RUC/CI</th>
            <th>Activo</th>
            <th style="width:170px;">Acciones</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
      <div id="pagination" class="pagination"></div>
    </section>
  </main>

  <div class="modal-backdrop" id="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal">
      <header>
        <h2 id="modal-title"></h2>
        <button class="close-modal" aria-label="Cerrar" id="close-modal">&times;</button>
      </header>
      <form id="cliente-form" novalidate>
        <input type="hidden" name="id">
        <div class="field-group">
          <label>Nombre
            <input name="nombre" maxlength="50" required>
          </label>
          <label>Apellido
            <input name="apellido" maxlength="50" required>
          </label>
          <label>Dirección
            <input name="direccion" maxlength="100">
          </label>
          <label>Teléfono
            <input name="telefono" maxlength="20">
          </label>
          <label>RUC/CI
            <input name="ruc_ci" maxlength="15">
          </label>
          <label>Activo
            <select name="activo">
              <option value="true">Sí</option>
              <option value="false">No</option>
            </select>
          </label>
        </div>
        <div class="form-error" id="form-error"></div>
        <footer>
          <button type="button" class="secondary" id="cancel-modal">Cancelar</button>
          <button type="submit" class="primary" id="submit-modal"></button>
        </footer>
      </form>
    </div>
  </div>

  <template id="row-template">
    <tr>
      <td data-field="id_cliente"></td>
      <td data-field="nombre"></td>
      <td data-field="apellido"></td>
      <td data-field="direccion"></td>
      <td data-field="telefono"></td>
      <td data-field="ruc_ci"></td>
      <td data-field="activo"></td>
      <td class="table-actions"></td>
    </tr>
  </template>

  <script>
  const API_BASE = './clientes_api.php';
  const tableBody = document.querySelector('#clientes-table tbody');
  const pagination = document.querySelector('#pagination');
  const rowTemplate = document.querySelector('#row-template');
  const searchForm = document.querySelector('#search-form');
  const searchStatus = document.querySelector('#search-status');
  const clearFiltersBtn = document.querySelector('#clear-filters');
  const feedback = document.getElementById('feedback');

  const modalBackdrop = document.getElementById('modal-backdrop');
  const modalTitle = document.getElementById('modal-title');
  const clienteForm = document.getElementById('cliente-form');
  const modalSubmitBtn = document.getElementById('submit-modal');
  const modalError = document.getElementById('form-error');
  const closeModalBtn = document.getElementById('close-modal');
  const cancelModalBtn = document.getElementById('cancel-modal');
  const newClientBtn = document.getElementById('new-client-btn');
  let currentPage = 1;

  function showFeedback(type, message, timeout = 3200) {
    feedback.textContent = message;
    feedback.className = `feedback ${type}`;
    feedback.style.display = 'block';

    if (timeout) {
      setTimeout(() => {
        feedback.style.display = 'none';
      }, timeout);
    }
  }

  async function request(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        ...(options.body && !(options.body instanceof FormData)
          ? { 'Content-Type': options.headers?.['Content-Type'] || 'application/json' }
          : {})
      },
      ...options
    });

    let payload = null;
    try { payload = await response.json(); } catch (_) {}
    if (!response.ok || !payload?.ok) {
      const message = payload?.error || `Error HTTP ${response.status}`;
      throw new Error(message);
    }
    return payload;
  }

  function serializeForm(form) {
    const data = {};
    for (const element of form.elements) {
      if (!element.name || element.disabled) continue;
      if (element.type === 'submit' || element.type === 'button') continue;
      data[element.name] = element.value.trim();
    }
    return data;
  }

  async function loadClientes(page = 1) {
    currentPage = page;
    const params = new URLSearchParams(serializeForm(searchForm));
    params.set('page', page);
    searchStatus.textContent = 'Cargando...';

    try {
      const res = await request(`${API_BASE}?${params.toString()}`);
      renderTable(res.data);
      renderPagination(res.page, res.page_size, res.total);
      searchStatus.textContent = `${res.total} clientes encontrados`;
    } catch (err) {
      searchStatus.textContent = err.message;
      renderTable([]);
      renderPagination(1, 1, 0);
    }
  }

  function renderTable(rows) {
    tableBody.innerHTML = '';
    if (!rows.length) {
      const emptyRow = document.createElement('tr');
      const cell = document.createElement('td');
      cell.colSpan = 8;
      cell.textContent = 'No se encontraron clientes con los filtros actuales.';
      cell.style.textAlign = 'center';
      cell.style.color = '#64748b';
      cell.style.padding = '1.5rem';
      emptyRow.appendChild(cell);
      tableBody.appendChild(emptyRow);
      return;
    }

    rows.forEach(row => {
      const tr = rowTemplate.content.firstElementChild.cloneNode(true);
      for (const [field, value] of Object.entries(row)) {
        const cell = tr.querySelector(`[data-field="${field}"]`);
        if (!cell) continue;
        if (field === 'activo') {
          cell.innerHTML = value
            ? '<span class="badge success">Activo</span>'
            : '<span class="badge muted">Inactivo</span>';
        } else {
          cell.textContent = value ?? '';
        }
      }

      const actionsContainer = tr.querySelector('.table-actions');

      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'action-btn edit';
      editBtn.textContent = 'Editar';
      editBtn.addEventListener('click', () => openModal('edit', row));
      actionsContainer.appendChild(editBtn);

      const toggleBtn = document.createElement('button');
      toggleBtn.type = 'button';
      toggleBtn.className = 'action-btn toggle';
      toggleBtn.textContent = row.activo ? 'Inactivar' : 'Activar';
      toggleBtn.addEventListener('click', async () => {
        try {
          await request(API_BASE, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              _action: 'toggle',
              id: row.id_cliente,
              activo: row.activo ? 'false' : 'true'
            })
          });
          showFeedback('success', `Cliente ${row.activo ? 'inactivado' : 'activado'} correctamente`);
          await loadClientes(currentPage);
        } catch (err) {
          showFeedback('error', err.message);
        }
      });
      actionsContainer.appendChild(toggleBtn);

      tableBody.appendChild(tr);
    });
  }

  function renderPagination(page, pageSize, total) {
    if (total <= pageSize) {
      pagination.innerHTML = '';
      return;
    }
    const totalPages = Math.ceil(total / pageSize);
    pagination.innerHTML = '';

    const createButton = (label, targetPage, disabled = false) => {
      const btn = document.createElement('button');
      btn.textContent = label;
      btn.disabled = disabled;
      btn.addEventListener('click', () => loadClientes(targetPage));
      pagination.appendChild(btn);
    };

    createButton('«', Math.max(1, page - 1), page === 1);
    const info = document.createElement('span');
    info.textContent = `Página ${page} de ${totalPages}`;
    pagination.appendChild(info);
    createButton('»', Math.min(totalPages, page + 1), page === totalPages);
  }

  function openModal(mode, cliente = null) {
    clienteForm.reset();
    modalError.textContent = '';
    clienteForm.dataset.mode = mode;

    if (mode === 'create') {
      modalTitle.textContent = 'Nuevo cliente';
      modalSubmitBtn.textContent = 'Crear';
      clienteForm.activo.value = 'true';
      clienteForm.id.value = '';
    } else {
      modalTitle.textContent = `Editar cliente #${cliente.id_cliente}`;
      modalSubmitBtn.textContent = 'Guardar cambios';
      clienteForm.id.value = cliente.id_cliente;
      clienteForm.nombre.value = cliente.nombre ?? '';
      clienteForm.apellido.value = cliente.apellido ?? '';
      clienteForm.direccion.value = cliente.direccion ?? '';
      clienteForm.telefono.value = cliente.telefono ?? '';
      clienteForm.ruc_ci.value = cliente.ruc_ci ?? '';
      clienteForm.activo.value = cliente.activo ? 'true' : 'false';
    }

    modalBackdrop.classList.add('active');
    modalBackdrop.setAttribute('aria-hidden', 'false');
    clienteForm.nombre.focus();
  }

  function closeModal() {
    modalBackdrop.classList.remove('active');
    modalBackdrop.setAttribute('aria-hidden', 'true');
  }

  searchForm.addEventListener('submit', e => {
    e.preventDefault();
    loadClientes(1);
  });

  clearFiltersBtn.addEventListener('click', () => {
    searchForm.reset();
    loadClientes(1);
  });

  newClientBtn.addEventListener('click', () => openModal('create'));
  closeModalBtn.addEventListener('click', closeModal);
  cancelModalBtn.addEventListener('click', closeModal);
  modalBackdrop.addEventListener('click', e => {
    if (e.target === modalBackdrop) closeModal();
  });

  clienteForm.addEventListener('submit', async e => {
    e.preventDefault();
    modalError.textContent = '';

    const data = serializeForm(clienteForm);
    const mode = clienteForm.dataset.mode;
    const payload = { ...data };

    if ((payload.nombre ?? '').trim() === '' || (payload.apellido ?? '').trim() === '') {
      modalError.textContent = 'Nombre y Apellido son obligatorios.';
      return;
    }

    payload.activo = payload.activo === 'true';

    try {
      if (mode === 'create') {
        await request(API_BASE, {
          method: 'POST',
          body: JSON.stringify({
            nombre: payload.nombre,
            apellido: payload.apellido,
            direccion: payload.direccion,
            telefono: payload.telefono,
            ruc_ci: payload.ruc_ci,
            activo: payload.activo
          })
        });
        showFeedback('success', 'Cliente creado correctamente');
      } else {
        const id = payload.id;
        if (!id) {
          modalError.textContent = 'No se pudo determinar el cliente a editar.';
          return;
        }
        delete payload.id;
        await request(`${API_BASE}?id=${encodeURIComponent(id)}`, {
          method: 'PUT',
          body: JSON.stringify(payload)
        });
        showFeedback('success', 'Cambios guardados');
      }
      closeModal();
      await loadClientes(currentPage);
    } catch (err) {
      modalError.textContent = err.message;
    }
  });

  loadClientes();
  </script>
</body>
</html>
