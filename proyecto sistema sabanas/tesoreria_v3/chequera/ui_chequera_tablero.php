<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /login.php');
    exit;
}

function fetch_pairs($conn, string $sql): array {
    $res = pg_query($conn, $sql);
    if (!$res) return [];
    $rows = [];
    while ($row = pg_fetch_assoc($res)) $rows[] = $row;
    return $rows;
}

$cuentas = fetch_pairs(
    $conn,
    "SELECT id_cuenta_bancaria, banco, numero_cuenta, moneda
     FROM public.cuenta_bancaria
     WHERE estado='Activa'
     ORDER BY banco, numero_cuenta"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Chequeras</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{
      font-family: "Segoe UI", Arial, sans-serif;
      color-scheme: light;
      --bg:#f4f6fb; --card:#fff; --line:#d8dce6; --muted:#6b7280; --primary:#2563eb; --danger:#dc2626; --ok:#059669;
    }
    *{box-sizing:border-box;}
    body{margin:0;background:var(--bg);color:#1f2937;}
    header{background:#fff;border-bottom:1px solid var(--line);padding:14px 24px;display:flex;align-items:center;gap:12px;}
    header h1{margin:0;font-size:20px;}
    main{padding:20px 24px;max-width:1200px;margin:0 auto;}
    button{font:inherit;font-weight:600;padding:9px 14px;border-radius:8px;border:1px solid var(--line);cursor:pointer;background:#fff;}
    button.primary{background:var(--primary);color:#fff;border-color:var(--primary);}
    button.danger{background:var(--danger);color:#fff;border-color:var(--danger);}
    button[disabled]{opacity:.55;cursor:not-allowed;}
    .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px;}
    label{font-size:13px;font-weight:600;color:var(--muted);display:grid;gap:6px;}
    input,select{font:inherit;padding:8px 10px;border-radius:8px;border:1px solid var(--line);background:#fff;}
    .filters-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px;}
    table{width:100%;border-collapse:collapse;margin-top:20px;background:var(--card);border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.05);}
    thead{background:#eef2ff;color:#1d4ed8;}
    th,td{padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px;}
    tbody tr:hover{background:rgba(37,99,235,.08);}
    .badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;}
    .badge.ok{background:#dcfce7;color:#166534;}
    .badge.muted{background:#e2e8f0;color:#475569;}
    .badge.warn{background:#fef3c7;color:#92400e;}
    .empty{padding:16px;text-align:center;color:#6b7280;}
    .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;justify-content:center;align-items:flex-start;padding:40px 16px;z-index:30;}
    .modal-backdrop.active{display:flex;}
    .modal{background:#fff;border-radius:12px;max-width:640px;width:100%;border:1px solid var(--line);box-shadow:0 24px 60px rgba(15,23,42,.25);overflow:hidden;}
    .modal header{padding:16px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;}
    .modal .content{padding:20px;display:grid;gap:14px;}
    .modal footer{padding:12px 20px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;gap:10px;}
    .error-text{color:var(--danger);font-size:13px;}
    .info-box{background:#eef2ff;border:1px solid #cad4ff;border-radius:8px;padding:10px;color:#1e40af;font-size:13px;}
  </style>
</head>
<body>
<header>
  <h1>Chequeras</h1>
  <div style="margin-left:auto;display:flex;gap:10px;">
    <button class="primary" id="btn-new">Nueva chequera</button>
  </div>
</header>

<main>
  <section class="filters" id="filters">
    <label>
      Cuenta bancaria
      <select name="id_cuenta_bancaria">
        <option value="">Todas</option>
        <?php foreach ($cuentas as $cuenta): ?>
          <option value="<?= (int)$cuenta['id_cuenta_bancaria'] ?>">
            <?= htmlspecialchars($cuenta['banco'].' · '.$cuenta['numero_cuenta'].' ('.$cuenta['moneda'].')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Estado
      <select name="activa">
        <option value="">Todas</option>
        <option value="true">Activas</option>
        <option value="false">Inactivas</option>
      </select>
    </label>
    <label>
      Buscar
      <input type="text" name="q" placeholder="Banco, cuenta o descripción">
    </label>
    <div class="filters-actions">
      <button class="primary" id="btn-search">Aplicar filtros</button>
      <button id="btn-clear">Limpiar</button>
    </div>
  </section>

  <section style="margin-top:18px;">
    <table id="table-chequeras">
      <thead>
        <tr>
          <th>ID</th>
          <th>Cuenta</th>
          <th>Rango</th>
          <th>Próximo</th>
          <th>Prefijo/Sufijo</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="table-empty" class="empty" style="display:none;">No hay chequeras que coincidan con el filtro.</div>
  </section>
</main>

<div class="modal-backdrop" id="modal">
  <div class="modal">
    <header>
      <h2 id="modal-title">Nueva chequera</h2>
      <button type="button" id="modal-close">&times;</button>
    </header>
    <form id="chequera-form">
      <div class="content">
        <div class="info-box" id="modal-info">La cuenta seleccionada no debe tener otra chequera activa si marcás esta como activa.</div>
        <label>
          Cuenta bancaria
          <select name="id_cuenta_bancaria" required>
            <option value="">Seleccionar cuenta</option>
            <?php foreach ($cuentas as $cuenta): ?>
              <option value="<?= (int)$cuenta['id_cuenta_bancaria'] ?>">
                <?= htmlspecialchars($cuenta['banco'].' · '.$cuenta['numero_cuenta'].' ('.$cuenta['moneda'].')') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          Descripción
          <input type="text" name="descripcion" maxlength="100" placeholder="Ej: Talonario 2025">
        </label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
          <label>Prefijo
            <input type="text" name="prefijo" maxlength="10">
          </label>
          <label>Sufijo
            <input type="text" name="sufijo" maxlength="10">
          </label>
          <label>Padding
            <input type="number" name="pad_length" min="1" value="6" required>
          </label>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
          <label>Número inicio
            <input type="number" name="numero_inicio" min="1" required>
          </label>
          <label>Número fin
            <input type="number" name="numero_fin" min="1">
          </label>
          <label>Próximo número
            <input type="number" name="proximo_numero" min="1">
          </label>
        </div>
        <label style="display:flex;align-items:center;gap:8px;">
          <input type="checkbox" name="activa" checked>
          Marcar como activa
        </label>
        <div class="error-text" id="modal-error"></div>
      </div>
      <footer>
        <button type="button" id="modal-cancel">Cancelar</button>
        <button type="submit" class="primary" id="modal-submit">Guardar</button>
      </footer>
    </form>
  </div>
</div>

<div class="modal-backdrop" id="edit-modal">
  <div class="modal">
    <header>
      <h2 id="edit-title">Editar chequera</h2>
      <button type="button" id="edit-close">&times;</button>
    </header>
    <form id="edit-form">
      <div class="content">
        <div class="info-box" id="edit-info">Actualizá los parámetros necesarios. El próximo número debe respetar el rango.</div>
        <input type="hidden" name="id_chequera">
        <label>Descripción
          <input type="text" name="descripcion" maxlength="100">
        </label>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
          <label>Prefijo
            <input type="text" name="prefijo" maxlength="10">
          </label>
          <label>Sufijo
            <input type="text" name="sufijo" maxlength="10">
          </label>
          <label>Padding
            <input type="number" name="pad_length" min="1" required>
          </label>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
          <label>Número inicio
            <input type="number" name="numero_inicio" min="1" required>
          </label>
          <label>Número fin
            <input type="number" name="numero_fin" min="1">
          </label>
          <label>Próximo número
            <input type="number" name="proximo_numero" min="1" required>
          </label>
        </div>
        <div class="error-text" id="edit-error"></div>
      </div>
      <footer>
        <button type="button" id="edit-cancel">Cancelar</button>
        <button type="submit" class="primary" id="edit-submit">Actualizar</button>
      </footer>
    </form>
  </div>
</div>

<script>
const apiUrl = '../chequera/chequera_api.php';
const tbody = document.querySelector('#table-chequeras tbody');
const emptyState = document.getElementById('table-empty');
const modal = document.getElementById('modal');
const editModal = document.getElementById('edit-modal');
const editTitle = document.getElementById('edit-title');

const modalForm = document.getElementById('chequera-form');
const editForm = document.getElementById('edit-form');
const modalError = document.getElementById('modal-error');
const editError = document.getElementById('edit-error');
const modalTitle = document.getElementById('modal-title');
const btnNew = document.getElementById('btn-new');

async function loadData() {
  const params = new URLSearchParams();
  document.querySelectorAll('#filters select, #filters input').forEach(el => {
    if (!el.name) return;
    if (el.value.trim() !== '') params.set(el.name, el.value.trim());
  });

  try {
    const res = await fetch(`${apiUrl}?${params.toString()}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error al cargar chequeras');
    renderTable(json.data || []);
  } catch (err) {
    alert(err.message);
  }
}

function renderTable(rows) {
  tbody.innerHTML = '';
  if (!rows.length) {
    emptyState.style.display = 'block';
    return;
  }
  emptyState.style.display = 'none';

  rows.forEach(row => {
    const proximo = formatCheque(row);
    const estadoBadge = row.activa ? '<span class="badge ok">Activa</span>' : '<span class="badge muted">Inactiva</span>';
    const finTxt = row.numero_fin !== null ? row.numero_fin : '—';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${row.id_chequera}</td>
      <td>
        <div style="font-weight:600;">${escapeHtml(row.banco)}</div>
        <div style="font-size:12px;color:#64748b;">${escapeHtml(row.numero_cuenta)} · ${row.moneda}</div>
      </td>
      <td>${row.numero_inicio} – ${finTxt}</td>
      <td>${proximo ? proximo : '<span class="badge warn">Fuera de rango</span>'}</td>
      <td>${escapeHtml(row.prefijo || '')}/${escapeHtml(row.sufijo || '')} · pad ${row.pad_length}</td>
      <td>${estadoBadge}</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap;">
        <button class="outline" data-id="${row.id_chequera}" data-action="edit">Editar</button>
        ${row.activa
          ? `<button class="danger" data-id="${row.id_chequera}" data-action="toggle">Inactivar</button>`
          : `<button class="primary" data-id="${row.id_chequera}" data-action="activate">Activar</button>`}
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function formatCheque(row) {
  if (!row.proximo_numero) return null;
  const pad = row.pad_length && row.pad_length > 0 ? row.pad_length : 6;
  const pref = row.prefijo || '';
  const suf = row.sufijo || '';
  if (row.numero_fin !== null && row.proximo_numero > row.numero_fin) return null;
  if (row.proximo_numero < row.numero_inicio) return null;
  return pref + String(row.proximo_numero).padStart(pad, '0') + suf;
}
function escapeHtml(str) {
  return (str ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}

document.getElementById('btn-search').addEventListener('click', loadData);
document.getElementById('btn-clear').addEventListener('click', () => {
  document.querySelectorAll('#filters select, #filters input').forEach(el => {
    el.value = '';
  });
  loadData();
});

const btnRefresh = document.getElementById('btn-refresh');
if (btnRefresh) {
  btnRefresh.addEventListener('click', loadData);
}

btnNew.addEventListener('click', () => {
  modalTitle.textContent = 'Nueva chequera';
  modalForm.reset();
  modalError.textContent = '';
  modal.classList.add('active');
});

document.getElementById('modal-close').addEventListener('click', () => modal.classList.remove('active'));
document.getElementById('modal-cancel').addEventListener('click', () => modal.classList.remove('active'));
modal.addEventListener('click', e => { if (e.target === modal) modal.classList.remove('active'); });
modal.querySelector('.modal').addEventListener('click', e => e.stopPropagation());

document.getElementById('edit-close').addEventListener('click', () => editModal.classList.remove('active'));
document.getElementById('edit-cancel').addEventListener('click', () => editModal.classList.remove('active'));
editModal.addEventListener('click', e => { if (e.target === editModal) editModal.classList.remove('active'); });
editModal.querySelector('.modal').addEventListener('click', e => e.stopPropagation());

modalForm.addEventListener('submit', async e => {
  e.preventDefault();
  modalError.textContent = '';
  const formData = new FormData(modalForm);
  const payload = {
    id_cuenta_bancaria: formData.get('id_cuenta_bancaria'),
    descripcion: formData.get('descripcion'),
    prefijo: formData.get('prefijo'),
    sufijo: formData.get('sufijo'),
    pad_length: formData.get('pad_length'),
    numero_inicio: formData.get('numero_inicio'),
    numero_fin: formData.get('numero_fin'),
    proximo_numero: formData.get('proximo_numero'),
    activa: formData.get('activa') === 'on'
  };

  try {
    const res = await fetch(apiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo crear la chequera');
    modal.classList.remove('active');
    await loadData();
  } catch (err) {
    modalError.textContent = err.message;
  }
});

editForm.addEventListener('submit', async e => {
  e.preventDefault();
  editError.textContent = '';
  const formData = new FormData(editForm);
  const id = formData.get('id_chequera');
  const payload = {
    descripcion: formData.get('descripcion'),
    prefijo: formData.get('prefijo'),
    sufijo: formData.get('sufijo'),
    pad_length: formData.get('pad_length'),
    numero_inicio: formData.get('numero_inicio'),
    numero_fin: formData.get('numero_fin') !== '' ? formData.get('numero_fin') : null,
    proximo_numero: formData.get('proximo_numero')
  };

  try {
    const res = await fetch(`${apiUrl}?id=${encodeURIComponent(id)}`, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo actualizar la chequera');
    editModal.classList.remove('active');
    await loadData();
  } catch (err) {
    editError.textContent = err.message;
  }
});

tbody.addEventListener('click', async e => {
  const btn = e.target.closest('button[data-action]');
  if (!btn) return;
  const id = btn.dataset.id;
  const action = btn.dataset.action;

  if (action === 'edit') {
    try {
      const res = await fetch(`${apiUrl}?id=${encodeURIComponent(id)}`, { credentials: 'same-origin' });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'No se pudo obtener la chequera');
      const ch = json.chequera;
      editTitle.textContent = `Editar chequera #${ch.id_chequera}`;
      editForm.reset();
      editForm.querySelector('[name="id_chequera"]').value = ch.id_chequera;
      editForm.querySelector('[name="descripcion"]').value = ch.descripcion || '';
      editForm.querySelector('[name="prefijo"]').value = ch.prefijo || '';
      editForm.querySelector('[name="sufijo"]').value = ch.sufijo || '';
      editForm.querySelector('[name="pad_length"]').value = ch.pad_length;
      editForm.querySelector('[name="numero_inicio"]').value = ch.numero_inicio;
      editForm.querySelector('[name="numero_fin"]').value = ch.numero_fin !== null ? ch.numero_fin : '';
      editForm.querySelector('[name="proximo_numero"]').value = ch.proximo_numero;
      editError.textContent = '';
      editModal.classList.add('active');
    } catch (err) {
      alert(err.message);
    }
  }

  if (action === 'toggle' || action === 'activate') {
    const activar = action === 'activate';
    if (activar && !confirm('Al activar esta chequera inactivarás cualquier otra activa en la cuenta. ¿Continuar?')) return;
    if (!activar && !confirm('¿Seguro que querés inactivar esta chequera?')) return;

    try {
      const res = await fetch(`${apiUrl}?id=${encodeURIComponent(id)}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ activa: activar })
      });
      const json = await res.json();
      if (!json.ok) throw new Error(json.error || 'No se pudo actualizar el estado');
      await loadData();
    } catch (err) {
      alert(err.message);
    }
  }
});

loadData();
</script>

</body>
</html>
