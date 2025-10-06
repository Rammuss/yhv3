<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /login.php');
    exit;
}

function fetch_pairs($conn, string $sql, array $params = []): array {
    $result = pg_query_params($conn, $sql, $params);
    if (!$result) {
        return [];
    }
    $data = [];
    while ($row = pg_fetch_assoc($result)) {
        $data[] = $row;
    }
    return $data;
}

$proveedores = fetch_pairs(
    $conn,
    "SELECT id_proveedor, nombre FROM public.proveedores WHERE estado = 'Activo' ORDER BY nombre ASC LIMIT 500"
);
$sucursales = fetch_pairs(
    $conn,
    "SELECT id_sucursal, nombre FROM public.sucursales ORDER BY nombre ASC LIMIT 200"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Tablero de Cuentas a Pagar</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <style>
    :root{
      font-family: "Segoe UI", Arial, sans-serif;
      color-scheme: light;
      --bg:#f6f7fb; --card:#fff; --line:#d8dce6; --muted:#6b7280; --primary:#2563eb; --danger:#e11d48; --ok:#059669;
    }
    *{box-sizing:border-box;}
    body{margin:0;background:var(--bg);color:#1f2937;}
    header{background:#fff;border-bottom:1px solid var(--line);padding:14px 24px;display:flex;align-items:center;gap:12px;}
    header h1{margin:0;font-size:20px;}
    main{padding:20px 24px;max-width:1350px;margin:0 auto;}
    .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;background:var(--card);padding:16px;border-radius:12px;border:1px solid var(--line);}
    label{font-size:13px;font-weight:600;color:var(--muted);display:grid;gap:6px;}
    input,select,textarea{font:inherit;padding:8px 10px;border-radius:8px;border:1px solid var(--line);background:#fff;}
    textarea{resize:vertical;min-height:70px;}
    .filters-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:8px;}
    button{font:inherit;font-weight:600;padding:9px 14px;border-radius:8px;border:1px solid var(--line);cursor:pointer;background:#fff;transition:background .15s ease;}
    button.primary{background:var(--primary);color:#fff;border-color:var(--primary);}
    button.primary:disabled{background:rgba(37,99,235,.35);border-color:rgba(37,99,235,.35);}
    button.outline{background:#f9fafc;}
    button[disabled]{opacity:0.55;cursor:not-allowed;}
    .checkbox{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:var(--muted);}
    .selection-bar{margin:18px 0;background:#fff;border:1px solid var(--line);border-radius:12px;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;gap:16px;}
    .selection-bar .info{font-size:14px;color:#1f2937;}
    .selection-bar .info.error{color:var(--danger);}
    .selection-actions{display:flex;gap:10px;}
    table{width:100%;border-collapse:collapse;margin-top:18px;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.06);}
    thead{background:#eef2ff;color:#1e3aa8;}
    th,td{padding:10px 12px;font-size:14px;border-bottom:1px solid var(--line);vertical-align:top;}
    th:first-child,td:first-child{text-align:center;width:42px;}
    tbody tr:hover{background:rgba(37,99,235,.08);}
    .badge{padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;display:inline-block;}
    .badge.ok{background:#dcfce7;color:#065f46;}
    .badge.warn{background:#fef3c7;color:#92400e;}
    .badge.danger{background:#fee2e2;color:#b91c1c;}
    .summary{margin-top:16px;display:flex;flex-wrap:wrap;gap:14px;}
    .summary-item{background:#fff;border:1px solid var(--line);border-radius:10px;padding:12px 16px;min-width:200px;}
    .summary-item span{display:block;}
    .summary-item .label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.02em;}
    .summary-item .value{font-size:20px;font-weight:700;color:#111827;}
    .actions{display:flex;gap:8px;flex-direction:column;}
    .order-box{display:flex;flex-direction:column;gap:4px;font-size:12px;color:#475569;}
    .order-box strong{font-weight:700;font-size:13px;color:#1f2937;}
    .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;justify-content:center;align-items:flex-start;z-index:30;padding:40px 16px;}
    .modal-backdrop.active{display:flex;}
    .modal{background:#fff;border-radius:12px;max-width:900px;width:100%;box-shadow:0 24px 60px rgba(15,23,42,.25);border:1px solid var(--line);overflow:hidden;}
    .modal header{padding:16px 20px;border-bottom:1px solid var(--line);display:flex;justify-content:space-between;align-items:center;}
    .modal header h2{margin:0;font-size:18px;}
    .modal header button{border:none;background:none;font-size:22px;cursor:pointer;color:#6b7280;}
    .modal .content{padding:20px;display:grid;gap:18px;}
    .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;}
    .detail-card{background:#f9fafc;border:1px solid var(--line);border-radius:8px;padding:10px 12px;font-size:13px;}
    .detail-card strong{display:block;font-size:12px;color:#6b7280;margin-bottom:4px;}
    .section{background:#f8fafc;border:1px solid var(--line);border-radius:10px;padding:12px;}
    .section h3{margin:0 0 10px;font-size:16px;color:#1e293b;}
    table.subtable{width:100%;border-collapse:collapse;}
    table.subtable th,table.subtable td{border-bottom:1px solid var(--line);padding:6px 8px;font-size:13px;text-align:left;}
    table.subtable th{background:#eef2f6;color:#475569;}
    .info-box{background:#eef2ff;border:1px solid #cad4ff;border-radius:8px;padding:10px 12px;font-size:13px;color:#1e3aa8;}
    .info-box.warn{background:#fef3c7;border-color:#facc15;color:#92400e;}
    .info-box.error{background:#fee2e2;border-color:#fecdd3;color:#b91c1c;}
    .error-text{color:#b91c1c;font-size:13px;margin-top:6px;}
    .modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:12px 20px;border-top:1px solid var(--line);}
    .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;}
    .empty{padding:16px;text-align:center;color:#64748b;}
  </style>
</head>
<body>
<header>
  <h1>Cuentas a Pagar</h1>
  <div style="margin-left:auto;display:flex;gap:10px;">
    <button class="outline" id="btn-refresh">Actualizar</button>
    <button class="primary" id="btn-export">Exportar CSV</button>
  </div>
</header>

<main>
  <section class="filters" id="filters">
    <label>
      Buscar
      <input type="text" name="q" placeholder="Proveedor, RUC, Nº factura">
    </label>
    <label>
      Proveedor
      <select name="id_proveedor">
        <option value="">Todos</option>
        <?php foreach ($proveedores as $prov): ?>
          <option value="<?= (int)$prov['id_proveedor'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Sucursal
      <select name="id_sucursal">
        <option value="">Todas</option>
        <?php foreach ($sucursales as $suc): ?>
          <option value="<?= (int)$suc['id_sucursal'] ?>"><?= htmlspecialchars($suc['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      Estado
      <select name="estado">
        <option value="">Pendiente / Parcial</option>
        <option value="Pendiente">Pendiente</option>
        <option value="Parcial">Parcial</option>
        <option value="Cancelada">Cancelada</option>
        <option value="Anulada">Anulada</option>
      </select>
    </label>
    <label>
      Moneda
      <select name="moneda">
        <option value="">Cualquiera</option>
        <option value="PYG">Guaraníes</option>
        <option value="USD">Dólares</option>
      </select>
    </label>
    <label>
      Condición
      <select name="condicion">
        <option value="">Todas</option>
        <option value="Contado">Contado</option>
        <option value="Credito">Crédito</option>
      </select>
    </label>
    <label>
      Emisión desde
      <input type="date" name="fecha_desde">
    </label>
    <label>
      Emisión hasta
      <input type="date" name="fecha_hasta">
    </label>
    <div class="filters-actions">
      <span class="checkbox">
        <input type="checkbox" id="solo-vencidas" name="solo_vencidas">
        <label for="solo-vencidas">Solo vencidas</label>
      </span>
      <span class="checkbox">
        <input type="checkbox" id="con-total" name="with_totals">
        <label for="con-total">Mostrar totales</label>
      </span>
      <button class="primary" id="btn-aplicar">Aplicar filtros</button>
      <button id="btn-limpiar">Limpiar</button>
    </div>
  </section>

  <section class="selection-bar" id="selection-bar">
    <div class="info" id="selection-info">No hay facturas seleccionadas.</div>
    <div class="selection-actions">
      <button class="outline" id="btn-clear-selection" disabled>Limpiar selección</button>
      <button class="primary" id="btn-open-op" disabled>Generar OP</button>
    </div>
  </section>

  <section id="summary" class="summary" style="display:none;"></section>

  <section style="margin-top:18px;">
    <table id="table-cxp">
      <thead>
        <tr>
          <th><input type="checkbox" id="select-all"></th>
          <th>Proveedor</th>
          <th>Documento</th>
          <th>Emisión</th>
          <th>Vencimiento</th>
          <th>Días</th>
          <th>Condición</th>
          <th>Total</th>
          <th>Saldo</th>
          <th>Órdenes</th>
          <th>Estado</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="table-empty" class="empty" style="display:none;">Sin resultados para los filtros aplicados.</div>
  </section>
</main>

<div class="modal-backdrop" id="modal">
  <div class="modal">
    <header>
      <h2 id="modal-title">Detalle CXP</h2>
      <button type="button" id="modal-close">&times;</button>
    </header>
    <div class="content">
      <div class="detail-grid" id="detail-grid"></div>

      <div class="section" id="op-section">
        <h3>Órdenes de pago vinculadas</h3>
        <table class="subtable" id="op-table">
          <thead>
            <tr>
              <th>Orden</th>
              <th>Fecha</th>
              <th>Monto</th>
              <th>Estado</th>
              <th>Cuenta / Cheque</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div id="op-empty" class="empty" style="display:none;">Sin órdenes asociadas.</div>
      </div>

      <div>
        <h3 style="margin:0 0 8px;font-size:16px;color:#1e293b;">Movimientos</h3>
        <table class="subtable" id="mov-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Tipo</th>
              <th>Concepto</th>
              <th>Signo</th>
              <th>Monto</th>
              <th>Saldo</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div id="mov-empty" class="empty" style="display:none;">Sin movimientos registrados.</div>
      </div>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="op-modal">
  <div class="modal">
    <form id="op-form">
      <header>
        <h2>Generar orden de pago</h2>
        <button type="button" id="op-close">&times;</button>
      </header>
      <div class="content">
        <div class="detail-grid" id="op-meta">
          <div class="detail-card">
            <strong>Proveedor</strong>
            <span id="op-provider">-</span>
          </div>
          <div class="detail-card">
            <strong>RUC</strong>
            <span id="op-provider-ruc">-</span>
          </div>
          <div class="detail-card">
            <strong>Moneda</strong>
            <span id="op-moneda">-</span>
          </div>
          <div class="detail-card">
            <strong>Facturas</strong>
            <span id="op-count">0</span>
          </div>
        </div>

        <div class="section">
          <h3>Facturas seleccionadas</h3>
          <table class="subtable" id="op-facturas-table">
            <thead>
              <tr>
                <th>Documento</th>
                <th style="text-align:right;">Saldo pendiente</th>
                <th style="text-align:right;">Monto a pagar</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
          <div class="empty" id="op-facturas-empty" style="display:none;">No hay facturas para pagar.</div>
        </div>

        <div class="section">
          <h3>Datos del pago</h3>
          <div class="form-grid">
            <label>Cuenta bancaria
              <select id="op-account">
                <option value="">Seleccionar cuenta</option>
              </select>
            </label>
            <label>Fecha del cheque
              <input type="date" id="op-fecha">
            </label>
            <label>Número de cheque
              <input type="text" id="op-numero" maxlength="20">
            </label>
          </div>
          <div class="info-box" id="op-account-info">Seleccioná una cuenta para ver el saldo disponible.</div>
          <div class="info-box" id="op-total-info">Total a pagar: -</div>
          <label style="margin-top:12px;">Observación
            <textarea id="op-observacion" maxlength="250" placeholder="Comentario opcional"></textarea>
          </label>
          <div class="error-text" id="op-error"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="outline" id="op-cancel">Cancelar</button>
        <button type="submit" class="primary" id="op-submit" disabled>Generar orden</button>
      </div>
    </form>
  </div>
</div>

<script>
const apiUrl = '../tablero_cxp/api_tablero_cxp.php';
const bankApiUrl = '../bancos/cuentas_bancarias_api.php';
const orderApiUrl = '../orden_pago/ordenes_pago_api.php';

const tbody = document.querySelector('#table-cxp tbody');
const tableEmpty = document.getElementById('table-empty');
const summaryBox = document.getElementById('summary');

const modal = document.getElementById('modal');
const modalTitle = document.getElementById('modal-title');
const detailGrid = document.getElementById('detail-grid');
const opTableBody = document.querySelector('#op-table tbody');
const opEmpty = document.getElementById('op-empty');
const movTableBody = document.querySelector('#mov-table tbody');
const movEmpty = document.getElementById('mov-empty');

const opModal = document.getElementById('op-modal');
const opForm = document.getElementById('op-form');
const opProvider = document.getElementById('op-provider');
const opProviderRuc = document.getElementById('op-provider-ruc');
const opMoneda = document.getElementById('op-moneda');
const opCount = document.getElementById('op-count');
const opFacturasTableBody = document.querySelector('#op-facturas-table tbody');
const opFacturasEmpty = document.getElementById('op-facturas-empty');
const opAccountSelect = document.getElementById('op-account');
const opFecha = document.getElementById('op-fecha');
const opNumero = document.getElementById('op-numero');
const opObservacion = document.getElementById('op-observacion');
const opAccountInfo = document.getElementById('op-account-info');
const opTotalInfo = document.getElementById('op-total-info');
const opError = document.getElementById('op-error');
const opSubmit = document.getElementById('op-submit');

const selectionInfo = document.getElementById('selection-info');
const btnOpenOp = document.getElementById('btn-open-op');
const btnClearSelection = document.getElementById('btn-clear-selection');

const rowsCache = new Map();
const selectedRows = new Map();
let bankAccounts = [];
let selectAllListenerBound = false;
let currentOpSelection = [];

function serializeFilters() {
  const container = document.getElementById('filters');
  const params = new URLSearchParams();
  container.querySelectorAll('input, select').forEach(el => {
    if (!el.name) return;
    if (el.type === 'checkbox') {
      if (el.checked) params.set(el.name, 'true');
    } else if (el.value.trim() !== '') {
      params.set(el.name, el.value.trim());
    }
  });
  params.set('page', '1');
  params.set('page_size', '100');
  return params;
}

async function loadData() {
  const params = serializeFilters();
  try {
    const res = await fetch(`${apiUrl}?${params.toString()}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error al cargar datos');
    renderTable(json.data || []);
    renderSummary(json.summary || null, params.has('with_totals'));
  } catch (err) {
    alert(err.message);
  }
}

function renderTable(rows) {
  rowsCache.clear();
  tbody.innerHTML = '';
  const selectAll = document.getElementById('select-all');
  if (selectAll) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
  }

  if (!rows.length) {
    tableEmpty.style.display = 'block';
    clearMissingSelections();
    updateSelectionUI();
    return;
  }
  tableEmpty.style.display = 'none';

  rows.forEach(row => {
    rowsCache.set(row.id_cxp, row);
    if (selectedRows.has(row.id_cxp)) {
      selectedRows.set(row.id_cxp, row);
    }
  });

  rows.forEach(row => {
    const hasOpenOp = row.orden_pago && row.orden_pago.abiertas > 0;
    if (hasOpenOp && selectedRows.has(row.id_cxp)) {
      selectedRows.delete(row.id_cxp);
    }
    const tr = document.createElement('tr');
    const estadoBadge = estadoToBadge(row.estado, row.vencida);
    const ordenHtml = renderOrdenResumen(row.orden_pago, row.moneda);
    const isSelected = selectedRows.has(row.id_cxp);
    tr.innerHTML = `
      <td>
        <input type="checkbox" class="row-select" data-id="${row.id_cxp}" ${isSelected ? 'checked' : ''} ${hasOpenOp ? 'disabled' : ''}>
      </td>
      <td>
        <div style="font-weight:600;">${escapeHtml(row.proveedor.nombre)}</div>
        <div style="font-size:12px;color:#64748b;">RUC: ${escapeHtml(row.proveedor.ruc || '-')}</div>
      </td>
      <td>
        <div>${escapeHtml(row.documento.numero)}</div>
        ${row.documento.timbrado ? `<div style="font-size:12px;color:#64748b;">Timbrado ${escapeHtml(row.documento.timbrado)}</div>` : ''}
      </td>
      <td>${row.fecha_emision}</td>
      <td>${row.fecha_venc}</td>
      <td style="text-align:center;">${row.dias_al_venc}</td>
      <td>${escapeHtml(row.condicion || '-')}</td>
      <td style="text-align:right;">${formatCurrency(row.total_factura, row.moneda)}</td>
      <td style="text-align:right;font-weight:600;">${formatCurrency(row.saldo_actual, row.moneda)}</td>
      <td>${ordenHtml}</td>
      <td>${estadoBadge}</td>
      <td class="actions">
        <button data-id="${row.id_cxp}" class="outline js-view">Ver detalle</button>
        <button data-id="${row.id_cxp}" class="primary js-pay" ${hasOpenOp ? 'disabled title="Tiene órdenes abiertas"' : ''}>Generar OP</button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  clearMissingSelections();
  attachSelectAllListener();
  updateSelectAllState();
  updateSelectionUI();
}

function renderOrdenResumen(op, moneda) {
  if (!op || op.total === 0) {
    return '<span class="order-box">Sin órdenes</span>';
  }
  const partes = [];
  partes.push(`<strong>${op.total} orden${op.total > 1 ? 'es' : ''}</strong>`);
  if (op.monto_reservado > 0) {
    partes.push(`Reservado: ${formatCurrency(op.monto_reservado, moneda)}`);
  }
  if (op.monto_total > op.monto_reservado) {
    partes.push(`Aplicado: ${formatCurrency(op.monto_total - op.monto_reservado, moneda)}`);
  }
  if (op.ultima_op) {
    partes.push(`Última: #${op.ultima_op} (${escapeHtml(op.ultimo_estado || '-')})`);
  }
  return `<div class="order-box">${partes.join('<br>')}</div>`;
}

function updateSelectionUI() {
  const selection = Array.from(selectedRows.values());
  const selectionInfoEl = selectionInfo;
  const btnClear = btnClearSelection;
  const btnOpen = btnOpenOp;

  if (!selection.length) {
    selectionInfoEl.textContent = 'No hay facturas seleccionadas.';
    selectionInfoEl.classList.remove('error');
    btnClear.disabled = true;
    btnOpen.disabled = true;
    btnOpen.textContent = 'Generar OP';
    return;
  }

  btnClear.disabled = false;
  btnOpen.textContent = `Generar OP (${selection.length})`;

  const providerIds = new Set(selection.map(r => r.proveedor.id));
  const monedas = new Set(selection.map(r => r.moneda));
  const reservadas = selection.filter(r => r.orden_pago && r.orden_pago.abiertas > 0);

  const totalSaldo = selection.reduce((sum, r) => sum + (r.saldo_actual || 0), 0);
  const infoText = `Seleccionadas ${selection.length} factura${selection.length>1?'s':''} · Proveedor: ${escapeHtml(selection[0].proveedor.nombre)} · Moneda: ${selection[0].moneda} · Saldo total: ${formatCurrency(totalSaldo, selection[0].moneda)}`;
  selectionInfoEl.textContent = infoText;

  let valid = true;
  let errorMsg = '';
  if (providerIds.size > 1) {
    valid = false;
    errorMsg = 'Seleccioná facturas del mismo proveedor.';
  } else if (monedas.size > 1) {
    valid = false;
    errorMsg = 'Seleccioná facturas de una sola moneda.';
  } else if (reservadas.length) {
    valid = false;
    errorMsg = 'Hay facturas con órdenes abiertas en la selección.';
  }

  if (!valid) {
    selectionInfoEl.textContent = `${infoText} · ${errorMsg}`;
    selectionInfoEl.classList.add('error');
    btnOpen.disabled = true;
  } else {
    selectionInfoEl.classList.remove('error');
    btnOpen.disabled = false;
  }
}

function updateSelectAllState() {
  const selectAll = document.getElementById('select-all');
  if (!selectAll) return;
  const eligible = Array.from(document.querySelectorAll('.row-select:not(:disabled)'));
  if (!eligible.length) {
    selectAll.checked = false;
    selectAll.indeterminate = false;
    return;
  }
  const checked = eligible.filter(cb => cb.checked);
  selectAll.checked = checked.length === eligible.length && checked.length > 0;
  selectAll.indeterminate = checked.length > 0 && checked.length < eligible.length;
}

function attachSelectAllListener() {
  const selectAll = document.getElementById('select-all');
  if (!selectAll || selectAllListenerBound) return;
  selectAll.addEventListener('change', e => {
    const checked = e.target.checked;
    const eligible = Array.from(document.querySelectorAll('.row-select:not(:disabled)'));
    selectedRows.clear();
    eligible.forEach(cb => {
      cb.checked = checked;
      if (checked) {
        const id = Number(cb.dataset.id);
        const row = rowsCache.get(id);
        if (row) selectedRows.set(id, row);
      }
    });
    updateSelectionUI();
    updateSelectAllState();
  });
  selectAllListenerBound = true;
}

function clearMissingSelections() {
  for (const key of Array.from(selectedRows.keys())) {
    if (!rowsCache.has(key)) {
      selectedRows.delete(key);
    }
  }
}

function estadoToBadge(estado, vencida) {
  const cls = vencida ? 'danger' : (estado === 'Pendiente' ? 'warn' : 'ok');
  return `<span class="badge ${cls}">${escapeHtml(estado)}${vencida ? ' • Vencida' : ''}</span>`;
}

function renderSummary(summary, show) {
  if (!show || !summary) {
    summaryBox.style.display = 'none';
    summaryBox.innerHTML = '';
    return;
  }
  const items = [
    { label: 'Saldo por pagar', value: summary.saldo_por_pagar },
    { label: 'Saldo pendiente', value: summary.saldo_pendiente },
    { label: 'Saldo parcial', value: summary.saldo_parcial },
    { label: 'Saldo vencido', value: summary.saldo_vencido },
    { label: 'Total facturado', value: summary.total_facturado }
  ];
  if (summary.saldo_reservado_op !== undefined) {
    items.push({ label: 'Saldo reservado (OP)', value: summary.saldo_reservado_op });
  }
  summaryBox.innerHTML = items.map(it => `
    <div class="summary-item">
      <span class="label">${it.label}</span>
      <span class="value">${formatCurrency(it.value)}</span>
    </div>
  `).join('');
  summaryBox.style.display = 'flex';
}

function formatCurrency(amount, currency = '') {
  const value = Number(amount || 0).toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  return currency ? `${currency} ${value}` : value;
}

function escapeHtml(str) {
  return (str ?? '').toString().replace(/[&<>"']/g, ch => (
    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch]
  ));
}

async function openDetail(id) {
  try {
    const res = await fetch(`${apiUrl}?id_cxp=${id}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo cargar el detalle');
    const data = json.cxp;
    const ordenes = json.ordenes_pago || [];
    modalTitle.textContent = `Cuenta #${data.id_cxp} · ${data.proveedor.nombre}`;
    renderDetail(data, ordenes);
    renderOrdenes(ordenes, data.moneda);
    renderMovimientos(json.movimientos || []);
    modal.classList.add('active');
  } catch (err) {
    alert(err.message);
  }
}

function renderDetail(cxp, ordenes) {
  const abiertas = ordenes.filter(op => ['Reservada','Emitida'].includes(op.estado)).length;
  const reservado = ordenes.reduce((acc, op) => acc + (['Reservada','Emitida'].includes(op.estado) ? op.monto_aplicado : 0), 0);
  detailGrid.innerHTML = `
    <div class="detail-card"><strong>Proveedor</strong><span>${escapeHtml(cxp.proveedor.nombre)}</span></div>
    <div class="detail-card"><strong>RUC</strong><span>${escapeHtml(cxp.proveedor.ruc || '-')}</span></div>
    <div class="detail-card"><strong>Documento</strong><span>${escapeHtml(cxp.documento.numero)}</span></div>
    <div class="detail-card"><strong>Timbrado</strong><span>${escapeHtml(cxp.documento.timbrado || '-')}</span></div>
    <div class="detail-card"><strong>Emisión</strong><span>${cxp.fecha_emision}</span></div>
    <div class="detail-card"><strong>Vencimiento</strong><span>${cxp.fecha_venc}</span></div>
    <div class="detail-card"><strong>Total factura</strong><span>${formatCurrency(cxp.factura.total_factura, cxp.moneda)}</span></div>
    <div class="detail-card"><strong>Saldo actual</strong><span>${formatCurrency(cxp.saldo_actual, cxp.moneda)}</span></div>
    <div class="detail-card"><strong>Estado</strong><span>${escapeHtml(cxp.estado)}</span></div>
    <div class="detail-card"><strong>Condición</strong><span>${escapeHtml(cxp.factura.condicion || '-')}</span></div>
    <div class="detail-card"><strong>Sucursal</strong><span>${escapeHtml(cxp.sucursal.nombre || '-')}</span></div>
    <div class="detail-card"><strong>Observación</strong><span>${escapeHtml(cxp.observacion || '-')}</span></div>
    <div class="detail-card"><strong>Órdenes abiertas</strong><span>${abiertas}</span></div>
    <div class="detail-card"><strong>Monto reservado</strong><span>${formatCurrency(reservado, cxp.moneda)}</span></div>
  `;
}

function renderOrdenes(ordenes, moneda) {
  opTableBody.innerHTML = '';
  if (!ordenes.length) {
    opEmpty.style.display = 'block';
    return;
  }
  opEmpty.style.display = 'none';
  ordenes.forEach(op => {
    const cuentaTxt = `${escapeHtml(op.cuenta.banco)} · ${escapeHtml(op.cuenta.numero)}`;
    const chequeTxt = op.cheque
      ? `Cheque #${escapeHtml(op.cheque.numero || '')} (${escapeHtml(op.cheque.estado)})`
      : 'Sin cheque';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>#${op.id_orden_pago}</td>
      <td>${op.fecha}</td>
      <td>${formatCurrency(op.monto_aplicado, op.moneda || moneda)}</td>
      <td>${escapeHtml(op.estado)}</td>
      <td>${cuentaTxt}<br><span style="font-size:12px;color:#64748b;">${chequeTxt}</span></td>
    `;
    opTableBody.appendChild(tr);
  });
}

function renderMovimientos(movs) {
  movTableBody.innerHTML = '';
  if (!movs.length) {
    movEmpty.style.display = 'block';
    return;
  }
  movEmpty.style.display = 'none';
  movs.forEach(m => {
    const signo = m.signo === 1 ? '+' : '-';
    const tipo = ({ FACT: 'Factura', PAGO: 'Pago', NC: 'Nota de crédito', ND: 'Nota de débito' })[m.ref_tipo] || m.ref_tipo;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${m.fecha}</td>
      <td>${escapeHtml(tipo)}</td>
      <td>${escapeHtml(m.concepto || '')}</td>
      <td style="text-align:center;">${signo}</td>
      <td style="text-align:right;">${formatCurrency(m.monto)}</td>
      <td style="text-align:right;">${formatCurrency(m.saldo_parcial)}</td>
    `;
    movTableBody.appendChild(tr);
  });
}

function closeModal() {
  modal.classList.remove('active');
}

async function loadBankAccounts(force = false) {
  if (force) bankAccounts = [];
  if (bankAccounts.length) return bankAccounts;
  const res = await fetch(bankApiUrl, { credentials: 'same-origin' });
  const json = await res.json();
  if (!json.ok) throw new Error(json.error || 'Error al cargar cuentas bancarias');
  bankAccounts = json.data || [];
  return bankAccounts;
}

function getAccountById(id) {
  return bankAccounts.find(acc => acc.id_cuenta_bancaria === id);
}

function recalcOpTotals() {
  const inputs = Array.from(document.querySelectorAll('.op-amount'));
  let total = 0;
  inputs.forEach(input => {
    const value = parseFloat(input.value);
    if (!isNaN(value)) total += value;
  });

  const cuentaId = parseInt(opAccountSelect.value || '0', 10);
  const cuenta = cuentaId ? getAccountById(cuentaId) : null;
  let infoText = `Total a pagar: ${formatCurrency(total, opMoneda.textContent || '')}`;
  let warnClass = '';

  if (cuenta) {
    const disponible = Number(cuenta.saldo_disponible || 0);
    const diferencia = disponible - total;
    infoText += ` · Saldo disponible: ${formatCurrency(disponible, cuenta.moneda)} · Diferencia: ${formatCurrency(diferencia, cuenta.moneda)}`;
    if (diferencia < 0) warnClass = 'error';
  }

  opTotalInfo.textContent = infoText;
  opTotalInfo.className = warnClass ? `info-box ${warnClass}` : 'info-box';

  const hasZero = inputs.some(input => !input.value || parseFloat(input.value) <= 0);
  const cuentaOk = Boolean(cuenta);
  const differenceOk = !warnClass;
  const totalOk = total > 0;

  opSubmit.disabled = !(cuentaOk && totalOk && !hasZero && differenceOk);
}

function updateAccountInfo() {
  const cuentaId = parseInt(opAccountSelect.value || '0', 10);
  const cuenta = cuentaId ? getAccountById(cuentaId) : null;
  if (!cuenta) {
    opAccountInfo.textContent = 'Seleccioná una cuenta para ver el saldo disponible.';
    opAccountInfo.className = 'info-box';
    recalcOpTotals();
    return;
  }
  const sameCurrency = cuenta.moneda === opMoneda.textContent;
  const text = `Banco: ${cuenta.banco} · Nº ${cuenta.numero_cuenta} · Moneda: ${cuenta.moneda}
Saldo contable: ${formatCurrency(cuenta.saldo_contable, cuenta.moneda)} · Reservado: ${formatCurrency(cuenta.saldo_reservado, cuenta.moneda)} · Disponible: ${formatCurrency(cuenta.saldo_disponible, cuenta.moneda)}`;
  opAccountInfo.textContent = text;
  opAccountInfo.className = sameCurrency ? 'info-box' : 'info-box warn';
  if (!sameCurrency) {
    opAccountInfo.textContent += ' · Atención: moneda distinta a la de las facturas.';
  }
  recalcOpTotals();
}

function openOpModal(rows) {
  if (!rows.length) {
    alert('Seleccioná al menos una factura.');
    return;
  }

  const providerId = rows[0].proveedor.id;
  const moneda = rows[0].moneda;

  if (rows.some(r => r.proveedor.id !== providerId)) {
    alert('Seleccioná facturas del mismo proveedor para generar la orden.');
    return;
  }
  if (rows.some(r => r.moneda !== moneda)) {
    alert('Seleccioná facturas de una sola moneda.');
    return;
  }
  if (rows.some(r => r.orden_pago && r.orden_pago.abiertas > 0)) {
    alert('Hay facturas con órdenes abiertas en la selección.');
    return;
  }

  currentOpSelection = rows.slice();
  opProvider.textContent = rows[0].proveedor.nombre;
  opProviderRuc.textContent = rows[0].proveedor.ruc || '-';
  opMoneda.textContent = moneda;
  opCount.textContent = `${rows.length}`;
  opNumero.value = '';
  opObservacion.value = '';
  opFecha.value = new Date().toISOString().slice(0, 10);
  opError.textContent = '';
  opSubmit.disabled = true;

  opFacturasTableBody.innerHTML = '';
  opFacturasEmpty.style.display = 'none';

  currentOpSelection.forEach(row => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <div style="font-weight:600;">${escapeHtml(row.documento.numero)}</div>
        <div style="font-size:12px;color:#64748b;">Factura #${row.id_factura}</div>
      </td>
      <td style="text-align:right;">${formatCurrency(row.saldo_actual, row.moneda)}</td>
      <td style="text-align:right;">
        <input type="number" class="op-amount" data-id="${row.id_cxp}" min="0.01" step="0.01" max="${row.saldo_actual}" value="${row.saldo_actual.toFixed(2)}" style="width:120px;">
      </td>
    `;
    opFacturasTableBody.appendChild(tr);
  });

  Array.from(document.querySelectorAll('.op-amount')).forEach(input => {
    input.addEventListener('input', () => {
      const max = parseFloat(input.getAttribute('max')) || 0;
      let val = parseFloat(input.value);
      if (isNaN(val) || val < 0) val = 0;
      if (val > max) val = max;
      input.value = val ? val.toFixed(2) : '';
      recalcOpTotals();
    });
  });

  populateAccountSelect(moneda);
  updateAccountInfo();
  recalcOpTotals();

  opModal.classList.add('active');
}

function populateAccountSelect(moneda) {
  opAccountSelect.innerHTML = '<option value="">Seleccionar cuenta</option>';
  const matching = bankAccounts.filter(acc => acc.moneda === moneda);
  const others = bankAccounts.filter(acc => acc.moneda !== moneda);

  matching.concat(others).forEach(acc => {
    const option = document.createElement('option');
    option.value = acc.id_cuenta_bancaria;
    option.textContent = `${acc.banco} · ${acc.numero_cuenta} (${acc.moneda})`;
    if (matching.includes(acc)) {
      option.textContent += ' - Moneda coincidente';
    }
    opAccountSelect.appendChild(option);
  });

  if (matching.length) {
    opAccountSelect.value = matching[0].id_cuenta_bancaria;
  } else if (bankAccounts.length) {
    opAccountSelect.value = bankAccounts[0].id_cuenta_bancaria;
  } else {
    opAccountSelect.value = '';
  }
}

function closeOpModal() {
  opModal.classList.remove('active');
  currentOpSelection = [];
  opForm.reset();
  opAccountInfo.textContent = 'Seleccioná una cuenta para ver el saldo disponible.';
  opAccountInfo.className = 'info-box';
  opTotalInfo.textContent = 'Total a pagar: -';
  opTotalInfo.className = 'info-box';
  opError.textContent = '';
}

async function handleOpSubmit(e) {
  e.preventDefault();
  const facturas = Array.from(document.querySelectorAll('.op-amount'))
    .map(input => ({
      id_cxp: Number(input.dataset.id),
      monto: parseFloat(input.value || '0')
    }))
    .filter(item => item.monto > 0);

  if (!facturas.length) {
    opError.textContent = 'Ingresá montos mayores a cero.';
    return;
  }

  const cuentaId = parseInt(opAccountSelect.value || '0', 10);
  if (!cuentaId) {
    opError.textContent = 'Seleccioná la cuenta bancaria a utilizar.';
    return;
  }

  const cuenta = getAccountById(cuentaId);
  const total = facturas.reduce((sum, item) => sum + item.monto, 0);
  if (cuenta && total > (cuenta.saldo_disponible || 0)) {
    opError.textContent = 'El total supera el saldo disponible de la cuenta.';
    return;
  }

  const payload = {
    id_proveedor: currentOpSelection[0].proveedor.id,
    id_cuenta_bancaria: cuentaId,
    numero_cheque: opNumero.value.trim(),
    fecha_cheque: opFecha.value || new Date().toISOString().slice(0, 10),
    observacion: opObservacion.value.trim(),
    facturas,
    permitir_saldo_negativo: false
  };

  opSubmit.disabled = true;
  opSubmit.textContent = 'Generando...';
  opError.textContent = '';

  try {
    const res = await fetch(orderApiUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo crear la orden de pago');

    alert(`Orden de pago #${json.id_orden_pago} generada correctamente.`);
    closeOpModal();
    selectedRows.clear();
    updateSelectionUI();
    await loadBankAccounts(true);
    await loadData();
  } catch (err) {
    opError.textContent = err.message;
  } finally {
    opSubmit.textContent = 'Generar orden';
    opSubmit.disabled = false;
  }
}

function handleRowSelection(id, checked) {
  const row = rowsCache.get(id);
  if (!row) return;
  if (checked) {
    selectedRows.set(id, row);
  } else {
    selectedRows.delete(id);
  }
  updateSelectionUI();
  updateSelectAllState();
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
  params.set('page_size', '1000');
  params.set('with_totals', 'false');
  try {
    const res = await fetch(`${apiUrl}?${params.toString()}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo exportar');
    const rows = json.data || [];
    if (!rows.length) { alert('Sin datos para exportar.'); return; }
    const header = ['Proveedor','RUC','Documento','Emisión','Vencimiento','Condición','Total','Saldo','Estado','Órdenes abiertas','Monto reservado'];
    const csvRows = [header.join(';')];
    rows.forEach(r => {
      csvRows.push([
        `"${r.proveedor.nombre.replace(/"/g,'""')}"`,
        `"${(r.proveedor.ruc || '').replace(/"/g,'""')}"`,
        `"${r.documento.numero.replace(/"/g,'""')}"`,
        r.fecha_emision,
        r.fecha_venc,
        r.condicion || '',
        r.total_factura,
        r.saldo_actual,
        r.estado,
        r.orden_pago ? r.orden_pago.abiertas : 0,
        r.orden_pago ? r.orden_pago.monto_reservado : 0
      ].join(';'));
    });
    const blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'cuentas_por_pagar.csv';
    a.click();
    URL.revokeObjectURL(url);
  } catch (err) {
    alert(err.message);
  }
});

tbody.addEventListener('change', e => {
  const cb = e.target.closest('.row-select');
  if (cb) {
    if (cb.disabled) return;
    handleRowSelection(Number(cb.dataset.id), cb.checked);
  }
});

tbody.addEventListener('click', e => {
  const viewBtn = e.target.closest('.js-view');
  if (viewBtn) {
    openDetail(Number(viewBtn.dataset.id));
    return;
  }
  const payBtn = e.target.closest('.js-pay');
  if (payBtn) {
    if (payBtn.disabled) {
      alert(payBtn.title || 'No se puede generar una nueva OP mientras exista una orden abierta.');
      return;
    }
    const row = rowsCache.get(Number(payBtn.dataset.id));
    if (!row) {
      alert('No se encontró la cuenta seleccionada.');
      return;
    }
    selectedRows.clear();
    selectedRows.set(row.id_cxp, row);
    const checkbox = tbody.querySelector(`.row-select[data-id="${row.id_cxp}"]`);
    if (checkbox) checkbox.checked = true;
    updateSelectionUI();
    updateSelectAllState();
    openOpModal([row]);
  }
});

document.getElementById('modal-close').addEventListener('click', closeModal);
modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
document.querySelector('#modal .modal').addEventListener('click', e => e.stopPropagation());

btnOpenOp.addEventListener('click', () => {
  const rows = Array.from(selectedRows.values());
  openOpModal(rows);
});

btnClearSelection.addEventListener('click', () => {
  selectedRows.clear();
  document.querySelectorAll('.row-select').forEach(cb => { cb.checked = false; });
  updateSelectionUI();
  updateSelectAllState();
});

document.getElementById('op-close').addEventListener('click', closeOpModal);
document.getElementById('op-cancel').addEventListener('click', closeOpModal);
opModal.addEventListener('click', e => { if (e.target === opModal) closeOpModal(); });
document.querySelector('#op-modal .modal').addEventListener('click', e => e.stopPropagation());
opAccountSelect.addEventListener('change', updateAccountInfo);
opForm.addEventListener('submit', handleOpSubmit);

loadBankAccounts().catch(err => console.error(err));
loadData();
</script>
</body>
</html>
