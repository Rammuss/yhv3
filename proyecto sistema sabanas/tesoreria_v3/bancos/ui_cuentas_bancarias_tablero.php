<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /login.php');
    exit;
}

function fetchDistinct($conn, string $sql): array {
    $res = pg_query($conn, $sql);
    if (!$res) return [];
    $data = [];
    while ($row = pg_fetch_assoc($res)) $data[] = $row;
    return $data;
}

$monedas = fetchDistinct($conn, "SELECT DISTINCT moneda FROM public.cuenta_bancaria ORDER BY moneda");
$estados = fetchDistinct($conn, "SELECT DISTINCT estado FROM public.cuenta_bancaria ORDER BY estado");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Tablero Bancos</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{
      font-family: "Segoe UI", Arial, sans-serif;
      color-scheme: light;
      --bg:#f4f6fb; --card:#fff; --line:#d9dbe8; --muted:#64748b; --primary:#2563eb; --ok:#16a34a; --warn:#eab308; --danger:#dc2626;
    }
    *{box-sizing:border-box;}
    body{margin:0;background:var(--bg);color:#111827;}
    header{background:#fff;border-bottom:1px solid var(--line);padding:16px 24px;display:flex;align-items:center;gap:16px;}
    header h1{margin:0;font-size:22px;}
    main{max-width:1200px;margin:0 auto;padding:20px 24px;display:grid;gap:20px;}

    .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;background:var(--card);padding:18px;border-radius:14px;border:1px solid var(--line);}
    label{display:grid;gap:6px;font-size:13px;font-weight:600;color:var(--muted);}
    input,select{font:inherit;padding:8px 10px;border-radius:8px;border:1px solid var(--line);background:#fff;}
    .filters-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px;}
    button{cursor:pointer;font:inherit;font-weight:600;border-radius:8px;padding:9px 14px;border:1px solid var(--line);background:#fff;}
    button.primary{background:var(--primary);border-color:var(--primary);color:#fff;}
    button.ghost{background:#f9fafc;}

    .summary{display:flex;flex-wrap:wrap;gap:16px;}
    .summary-card{flex:1 1 180px;background:var(--card);border-radius:12px;border:1px solid var(--line);padding:16px;}
    .summary-card span{display:block;}
    .summary-card .label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;}
    .summary-card .value{font-size:22px;font-weight:700;color:#111827;}

    table{width:100%;border-collapse:collapse;background:var(--card);border-radius:12px;overflow:hidden;box-shadow:0 12px 32px rgba(15,23,42,.06);}
    thead{background:#e0ecff;color:#1d4ed8;}
    th,td{padding:12px;font-size:14px;border-bottom:1px solid var(--line);}
    tbody tr:hover{background:rgba(37,99,235,.08);}
    .badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;padding:2px 8px;border-radius:999px;background:#e2e8f0;color:#1e293b;}
    .badge.ok{background:#dcfce7;color:#166534;}
    .badge.warn{background:#fef3c7;color:#92400e;}
    .badge.danger{background:#fee2e2;color:#b91c1c;}
    .actions{display:flex;gap:8px;}
    .empty{padding:16px;text-align:center;color:var(--muted);}

    .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;justify-content:center;align-items:flex-start;padding:40px 16px;z-index:40;}
    .modal-backdrop.active{display:flex;}
    .modal{max-width:900px;width:100%;background:#fff;border-radius:16px;border:1px solid var(--line);box-shadow:0 24px 60px rgba(15,23,42,.25);overflow:hidden;}
    .modal header{padding:16px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;}
    .modal header h2{margin:0;font-size:18px;}
    .modal header button{border:none;background:none;font-size:22px;cursor:pointer;color:var(--muted);}
    .modal .content{padding:20px;display:grid;gap:18px;}
    .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;}
    .detail-card{background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:12px;font-size:13px;}
    .detail-card strong{display:block;font-size:12px;color:var(--muted);margin-bottom:4px;}
    .section{background:#f9fafc;border:1px solid var(--line);border-radius:10px;padding:12px;}
    .section h3{margin:0 0 8px;font-size:16px;color:#1e293b;}
    .subtable{width:100%;border-collapse:collapse;}
    .subtable th,.subtable td{border-bottom:1px solid #dce1eb;padding:6px 8px;font-size:13px;text-align:left;}
    .subtable th{background:#eef2f6;color:#475569;}
  </style>
</head>
<body>
<header>
  <h1>Tablero Bancos</h1>
  <div style="margin-left:auto;display:flex;gap:10px;">
    <button class="ghost" id="btn-refresh">Actualizar</button>
    <button class="primary" id="btn-export">Exportar</button>
  </div>
</header>

<main>
  <section class="filters" id="filters">
    <label>
      Buscar
      <input type="text" name="q" placeholder="Banco o número de cuenta">
    </label>
    <label>
      Moneda
      <select name="moneda">
        <option value="">Todas</option>
        <?php foreach ($monedas as $m): ?>
          <option value="<?= htmlspecialchars($m['moneda']) ?>"><?= htmlspecialchars($m['moneda']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Estado
      <select name="estado">
        <option value="">Todos</option>
        <?php foreach ($estados as $e): ?>
          <option value="<?= htmlspecialchars($e['estado']) ?>"><?= htmlspecialchars($e['estado']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="filters-actions">
      <button class="primary" id="btn-aplicar">Aplicar filtros</button>
      <button id="btn-limpiar">Limpiar</button>
      <label style="display:flex;align-items:center;gap:8px;font-weight:600;color:var(--muted);">
        <input type="checkbox" name="with_totals" id="chk-totals">
        Mostrar totales
      </label>
    </div>
  </section>

  <section id="summary" class="summary" style="display:none;"></section>

  <section>
    <table>
      <thead>
        <tr>
          <th>Banco</th>
          <th>Cuenta</th>
          <th>Moneda</th>
          <th>Saldo contable</th>
          <th>Saldo reservado</th>
          <th>Saldo disponible</th>
          <th>Reservas</th>
          <th>Cheques</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody id="tbody"></tbody>
    </table>
    <div id="empty" class="empty" style="display:none;">Sin cuentas que cumplan los filtros.</div>
  </section>
</main>

<div class="modal-backdrop" id="modal">
  <div class="modal">
    <header>
      <h2 id="modal-title">Detalle cuenta</h2>
      <button type="button" id="modal-close">&times;</button>
    </header>
    <div class="content">
      <div class="detail-grid" id="detail-grid"></div>

      <div class="section" id="reservas-section" style="display:none;">
        <h3>Reservas abiertas</h3>
        <table class="subtable" id="reservas-table">
          <thead>
            <tr>
              <th>Referencia</th>
              <th>Fecha</th>
              <th>Monto</th>
              <th>Descripción</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div id="reservas-empty" class="empty" style="margin:8px 0 0;display:none;">No hay reservas pendientes.</div>
      </div>

      <div class="section">
        <h3>Movimientos recientes</h3>
        <table class="subtable" id="mov-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Signo</th>
              <th>Monto</th>
              <th>Referencia</th>
              <th>Descripción</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div id="mov-empty" class="empty" style="margin:8px 0 0;display:none;">Sin movimientos cargados.</div>
      </div>

      <div class="section">
        <h3>Cheques recientes</h3>
        <table class="subtable" id="cheques-table">
          <thead>
            <tr>
              <th>Número</th>
              <th>Beneficiario</th>
              <th>Monto</th>
              <th>Estado</th>
              <th>Fecha</th>
              <th>OP</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div id="cheques-empty" class="empty" style="margin:8px 0 0;display:none;">Sin cheques registrados.</div>
      </div>
    </div>
  </div>
</div>

<script>
const apiUrl = '../bancos/cuentas_bancarias_api.php';
const tbody = document.getElementById('tbody');
const emptyState = document.getElementById('empty');
const summary = document.getElementById('summary');
const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modal-title');
const detailGrid = document.getElementById('detail-grid');
const reservasSection = document.getElementById('reservas-section');
const reservasTableBody = document.querySelector('#reservas-table tbody');
const reservasEmpty = document.getElementById('reservas-empty');
const movTableBody = document.querySelector('#mov-table tbody');
const movEmpty = document.getElementById('mov-empty');
const chequesTableBody = document.querySelector('#cheques-table tbody');
const chequesEmpty = document.getElementById('cheques-empty');

function serializeFilters() {
  const params = new URLSearchParams();
  document.querySelectorAll('#filters input, #filters select').forEach(el => {
    if (!el.name) return;
    if (el.type === 'checkbox') {
      if (el.checked) params.set(el.name, 'true');
    } else if (el.value.trim() !== '') {
      params.set(el.name, el.value.trim());
    }
  });
  return params;
}

async function loadData() {
  const params = serializeFilters();
  try {
    const res = await fetch(`${apiUrl}?${params.toString()}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error al cargar cuentas');
    renderTable(json.data || []);
    renderSummary(json.totals || null, params.has('with_totals'));
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
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <div style="font-weight:600;">${escapeHtml(row.banco)}</div>
        <div style="font-size:12px;color:#64748b;">${escapeHtml(row.estado)}</div>
      </td>
      <td>${escapeHtml(row.numero_cuenta)}</td>
      <td>${escapeHtml(row.moneda)}</td>
      <td style="text-align:right;">${formatCurrency(row.saldo_contable, row.moneda)}</td>
      <td style="text-align:right;">${formatCurrency(row.saldo_reservado, row.moneda)}</td>
      <td style="text-align:right;font-weight:600;">${formatCurrency(row.saldo_disponible, row.moneda)}</td>
      <td>
        ${badge(row.reservas_activas > 0 ? 'warn' : 'ok', `${row.reservas_activas} / ${formatCurrency(row.reservas_monto, row.moneda)}`)}
      </td>
      <td>
        <div>${badge(row.cheques_reservados > 0 ? 'warn' : 'ok', `Reservados: ${row.cheques_reservados}`)}</div>
        <div style="margin-top:4px;">${badge('ok', `Cobrado: ${row.cheques_cobrados}`)}</div>
      </td>
      <td class="actions">
        <button class="ghost js-view" data-id="${row.id_cuenta_bancaria}">Ver detalle</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function renderSummary(totals, show) {
  if (!show || !totals) {
    summary.style.display = 'none';
    summary.innerHTML = '';
    return;
  }
  summary.innerHTML = `
    ${summaryCard('Saldo contable', totals.saldo_contable)}
    ${summaryCard('Saldo reservado', totals.saldo_reservado)}
    ${summaryCard('Saldo disponible', totals.saldo_disponible)}
    ${summaryCard('Reservas activas', totals.reservas_activas)}
    ${summaryCard('Cheques reservados', totals.cheques_reservados)}
    ${summaryCard('Cheques emitidos', totals.cheques_emitidos)}
  `;
  summary.style.display = 'flex';
}

function summaryCard(label, value) {
  const formatted = typeof value === 'number'
    ? value.toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
    : value;
  return `<div class="summary-card"><span class="label">${label}</span><span class="value">${formatted}</span></div>`;
}

function badge(type, text) {
  const cls = ['badge'];
  if (type === 'ok') cls.push('ok');
  if (type === 'warn') cls.push('warn');
  if (type === 'danger') cls.push('danger');
  return `<span class="${cls.join(' ')}">${escapeHtml(String(text))}</span>`;
}

function escapeHtml(str) {
  return (str ?? '').toString().replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[ch]));
}

function formatCurrency(amount, currency = '') {
  const value = Number(amount || 0).toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  return currency ? `${currency} ${value}` : value;
}

async function openDetail(id) {
  try {
    const res = await fetch(`${apiUrl}?id=${encodeURIComponent(id)}&mov_limit=30&cheque_limit=30`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error al cargar detalle');
    const c = json.cuenta;
    modalTitle.textContent = `${c.banco} · ${c.numero_cuenta}`;
    renderDetail(c);
    renderReservas(json.reservas_abiertas || [], c.moneda);
    renderMovimientos(json.movimientos || [], c.moneda);
    renderCheques(json.cheques || [], c.moneda);
    modal.classList.add('active');
  } catch (err) {
    alert(err.message);
  }
}

function renderDetail(c) {
  detailGrid.innerHTML = `
    <div class="detail-card"><strong>Banco</strong><span>${escapeHtml(c.banco)}</span></div>
    <div class="detail-card"><strong>Nº cuenta</strong><span>${escapeHtml(c.numero_cuenta)}</span></div>
    <div class="detail-card"><strong>Moneda</strong><span>${escapeHtml(c.moneda)}</span></div>
    <div class="detail-card"><strong>Tipo</strong><span>${escapeHtml(c.tipo)}</span></div>
    <div class="detail-card"><strong>Estado</strong><span>${escapeHtml(c.estado)}</span></div>
    <div class="detail-card"><strong>Saldo contable</strong><span>${formatCurrency(c.saldo_contable, c.moneda)}</span></div>
    <div class="detail-card"><strong>Saldo reservado</strong><span>${formatCurrency(c.saldo_reservado, c.moneda)}</span></div>
    <div class="detail-card"><strong>Saldo disponible</strong><span>${formatCurrency(c.saldo_disponible, c.moneda)}</span></div>
    <div class="detail-card"><strong>Reservas activas</strong><span>${c.reservas_activas}</span></div>
    <div class="detail-card"><strong>Monto reservado</strong><span>${formatCurrency(c.reservas_monto, c.moneda)}</span></div>
    <div class="detail-card"><strong>Cheques reservados</strong><span>${c.cheques_reservados}</span></div>
    <div class="detail-card"><strong>Cheques emitidos</strong><span>${c.cheques_emitidos}</span></div>
  `;
}

function renderReservas(reservas, moneda) {
  reservasTableBody.innerHTML = '';
  if (!reservas.length) {
    reservasSection.style.display = 'none';
    reservasEmpty.style.display = 'block';
    return;
  }
  reservasSection.style.display = 'block';
  reservasEmpty.style.display = 'none';
  reservas.forEach(r => {
    const ref = r.ref_tabla ? `${r.ref_tabla} #${r.ref_id}` : 'Sin referencia';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(ref)}</td>
      <td>${r.fecha_inicio}${r.fecha_ult && r.fecha_ult !== r.fecha_inicio ? ' → ' + r.fecha_ult : ''}</td>
      <td>${formatCurrency(r.monto, moneda)}</td>
      <td>${escapeHtml(r.descripcion || '')}</td>
    `;
    reservasTableBody.appendChild(tr);
  });
}

function renderMovimientos(movs, moneda) {
  movTableBody.innerHTML = '';
  if (!movs.length) {
    movEmpty.style.display = 'block';
    return;
  }
  movEmpty.style.display = 'none';
  movs.forEach(m => {
    const signo = m.signo === 1 ? '+' : '-';
    const ref = m.ref_tabla ? `${m.ref_tabla} ${m.ref_id ?? ''}` : '-';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${m.fecha}</td>
      <td>${escapeHtml(m.tipo)}</td>
      <td style="text-align:center;">${signo}</td>
      <td style="text-align:right;">${formatCurrency(m.monto, moneda)}</td>
      <td>${escapeHtml(ref)}</td>
      <td>${escapeHtml(m.descripcion || '')}</td>
    `;
    movTableBody.appendChild(tr);
  });
}

function renderCheques(cheques, moneda) {
  chequesTableBody.innerHTML = '';
  if (!cheques.length) {
    chequesEmpty.style.display = 'block';
    return;
  }
  chequesEmpty.style.display = 'none';
  cheques.forEach(ch => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${escapeHtml(ch.numero || '')}</td>
      <td>${escapeHtml(ch.beneficiario)}</td>
      <td>${formatCurrency(ch.monto, ch.moneda || moneda)}</td>
      <td>${escapeHtml(ch.estado)}</td>
      <td>${ch.fecha}</td>
      <td>${ch.id_orden_pago ? '#' + ch.id_orden_pago : '-'}</td>
    `;
    chequesTableBody.appendChild(tr);
  });
}

function closeModal() {
  modal.classList.remove('active');
}

document.getElementById('btn-aplicar').addEventListener('click', loadData);
document.getElementById('btn-refresh').addEventListener('click', loadData);
document.getElementById('btn-limpiar').addEventListener('click', () => {
  document.querySelectorAll('#filters input, #filters select').forEach(el => {
    if (el.type === 'checkbox') el.checked = false;
    else el.value = '';
  });
  loadData();
});

document.getElementById('btn-export').addEventListener('click', async () => {
  const params = serializeFilters();
  try {
    const res = await fetch(`${apiUrl}?${params.toString()}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo exportar');
    const rows = json.data || [];
    if (!rows.length) {
      alert('Sin datos para exportar.');
      return;
    }
    const header = ['Banco','Cuenta','Moneda','Saldo contable','Saldo reservado','Saldo disponible','Reservas activas','Monto reservado','Cheques reservados','Cheques emitidos','Cheques cobrados'];
    const csv = [header.join(';')];
    rows.forEach(r => {
      csv.push([
        `"${r.banco.replace(/"/g,'""')}"`,
        `"${r.numero_cuenta.replace(/"/g,'""')}"`,
        r.moneda,
        r.saldo_contable,
        r.saldo_reservado,
        r.saldo_disponible,
        r.reservas_activas,
        r.reservas_monto,
        r.cheques_reservados,
        r.cheques_emitidos,
        r.cheques_cobrados
      ].join(';'));
    });
    const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'bancos_resumen.csv';
    a.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    alert(err.message);
  }
});

tbody.addEventListener('click', e => {
  const btn = e.target.closest('.js-view');
  if (btn) openDetail(btn.dataset.id);
});

document.getElementById('modal-close').addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

loadData();
</script>
</body>
</html>
