<?php
session_start();
require_once __DIR__ . '/../../conexion/configv2.php';

if (empty($_SESSION['nombre_usuario'])) {
    header('Location: /login.php');
    exit;
}

function fetch_pairs($conn, string $sql): array
{
    $res = pg_query($conn, $sql);
    if (!$res) return [];
    $out = [];
    while ($row = pg_fetch_assoc($res)) $out[] = $row;
    return $out;
}

$proveedores = fetch_pairs(
    $conn,
    "SELECT id_proveedor, nombre
     FROM public.proveedores
     WHERE estado = 'Activo'
     ORDER BY nombre"
);

$cuentas = fetch_pairs(
    $conn,
    "SELECT id_cuenta_bancaria, banco, numero_cuenta, moneda
     FROM public.cuenta_bancaria
     WHERE estado = 'Activa'
     ORDER BY banco, numero_cuenta"
);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Órdenes de Pago</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <style>
        :root {
            font-family: "Segoe UI", Arial, sans-serif;
            color-scheme: light;
            --bg: #f4f6fb;
            --card: #fff;
            --line: #d8dce6;
            --muted: #64748b;
            --primary: #2563eb;
            --danger: #dc2626;
            --ok: #059669;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--bg);
            color: #1f2937;
        }

        header {
            background: #fff;
            border-bottom: 1px solid var(--line);
            padding: 14px 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        header h1 { margin: 0; font-size: 20px; }

        main {
            padding: 20px 24px;
            max-width: 1350px;
            margin: 0 auto;
        }

        button {
            font: inherit;
            font-weight: 600;
            padding: 9px 14px;
            border-radius: 8px;
            border: 1px solid var(--line);
            cursor: pointer;
            background: #fff;
            transition: background .15s ease;
        }

        button.primary { background: var(--primary); color: #fff; border-color: var(--primary); }
        button.danger  { background: var(--danger);  color: #fff; border-color: var(--danger);  }
        button[disabled]{ opacity:.55; cursor:not-allowed; }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            display: grid;
            gap: 6px;
        }

        input, select {
            font: inherit;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid var(--line);
            background: #fff;
        }

        .filters-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--card);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .05);
        }

        thead { background: #eef2ff; color: #1d4ed8; }
        th, td { padding: 10px 12px; border-bottom: 1px solid var(--line); font-size: 14px; }
        tbody tr:hover { background: rgba(37, 99, 235, .08); }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge.reservada { background:#eef2ff; color:#1e3aa8; }
        .badge.entregada { background:#dcfce7; color:#166534; }
        .badge.anulada   { background:#fee2e2; color:#b91c1c; }
        .badge.pagada    { background:#cffafe; color:#0f766e; }

        .empty { padding: 16px; text-align: center; color: #64748b; }

        .modal-backdrop {
            position: fixed; inset: 0; background: rgba(15,23,42,.45);
            display: none; justify-content: center; align-items: flex-start;
            padding: 40px 16px; z-index: 40;
        }
        .modal-backdrop.active { display:flex; }

        .modal {
            background:#fff; border-radius:12px; max-width:900px; width:100%;
            border:1px solid var(--line); box-shadow:0 24px 60px rgba(15,23,42,.25);
            overflow:hidden;
        }
        .modal header {
            padding:16px 20px; border-bottom:1px solid var(--line);
            display:flex; justify-content:space-between; align-items:center;
        }
        .modal header h2 { margin:0; font-size:18px; }
        .modal header button { border:none; background:none; font-size:22px; color:#6b7280; cursor:pointer; }
        .modal .content { padding:20px; display:grid; gap:16px; }

        .detail-grid {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px;
        }
        .detail-card {
            background:#f8fafc; border:1px solid var(--line); border-radius:8px; padding:12px; font-size:13px;
        }
        .detail-card strong { display:block; font-size:12px; color:#475569; margin-bottom:4px; }

        .section { background:#f9fafc; border:1px solid var(--line); border-radius:10px; padding:12px; }
        .section h3 { margin:0 0 10px; font-size:16px; color:#1e293b; }

        table.subtable { width:100%; border-collapse:collapse; }
        table.subtable th, table.subtable td {
            border-bottom:1px solid var(--line); padding:6px 8px; font-size:13px; text-align:left;
        }
        table.subtable th { background:#eef2f6; color:#475569; }

        .info-box { background:#eef2ff; border:1px solid #cad4ff; border-radius:8px; padding:10px; color:#1e3aa8; font-size:13px; }
        .error-text { color:#b91c1c; font-size:13px; }

        .modal footer {
            padding:12px 20px; border-top:1px solid var(--line);
            display:flex; justify-content:flex-end; gap:10px;
        }

        #print-preview { background:#fff; padding:16px; border:1px solid #cbd5f5; border-radius:10px; font-family:"Segoe UI",Arial,sans-serif; }
        #print-preview h3 { margin:0 0 12px; font-size:18px; text-align:center; }
        #print-preview .field { display:flex; justify-content:space-between; margin-bottom:8px; font-size:14px; }
        #print-preview .amount { font-size:20px; font-weight:700; margin:16px 0; text-align:right; }
    </style>
</head>

<body>
<header>
  <h1>Órdenes de Pago</h1>
  <div style="margin-left:auto;display:flex;gap:10px;">
    <button id="btn-refresh">Actualizar</button>
  </div>
</header>

<main>
  <section class="filters" id="filters">
    <label>Proveedor
      <select name="id_proveedor">
        <option value="">Todos</option>
        <?php foreach ($proveedores as $prov): ?>
          <option value="<?= (int)$prov['id_proveedor'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Cuenta bancaria
      <select name="id_cuenta_bancaria">
        <option value="">Todas</option>
        <?php foreach ($cuentas as $cuenta): ?>
          <option value="<?= (int)$cuenta['id_cuenta_bancaria'] ?>"><?= htmlspecialchars($cuenta['banco'] . ' · ' . $cuenta['numero_cuenta'] . ' (' . $cuenta['moneda'] . ')') ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Estado
      <select name="estado">
        <option value="">Todos</option>
        <option value="Reservada">Reservada</option>
        <option value="Entregada">Entregada</option>
        <option value="Anulada">Anulada</option>
        <option value="Pagada">Pagada</option>
      </select>
    </label>
    <label>Fecha desde
      <input type="date" name="fecha_desde">
    </label>
    <label>Fecha hasta
      <input type="date" name="fecha_hasta">
    </label>
    <label>Buscar
      <input type="text" name="q" placeholder="Proveedor, cuenta, Nº cheque">
    </label>
    <div class="filters-actions">
      <button class="primary" id="btn-search">Aplicar filtros</button>
      <button id="btn-clear">Limpiar</button>
    </div>
  </section>

  <section style="margin-top:18px;">
    <table id="table-op">
      <thead>
        <tr>
          <th>OP</th>
          <th>Fecha</th>
          <th>Proveedor</th>
          <th>Total</th>
          <th>Estado</th>
          <th>Cuenta</th>
          <th>Cheque</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div id="table-empty" class="empty" style="display:none;">No hay órdenes según el filtro.</div>
  </section>
</main>

<!-- DETALLE -->
<div class="modal-backdrop" id="detail-modal">
  <div class="modal">
    <header>
      <h2 id="detail-title">Orden</h2>
      <button type="button" id="detail-close">&times;</button>
    </header>
    <div class="content">
      <div class="detail-grid" id="detail-grid"></div>
      <div class="section">
        <h3>Cheque</h3>
        <div id="detail-cheque" class="info-box">Sin información de cheque.</div>
      </div>
      <div class="section">
        <h3>Facturas</h3>
        <table class="subtable" id="detail-facturas">
          <thead>
          <tr>
            <th>Nº documento</th>
            <th>Vencimiento</th>
            <th>Saldo pend.</th>
            <th>Monto aplicado</th>
          </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div class="empty" id="detail-facturas-empty" style="display:none;">Sin facturas asociadas.</div>
      </div>
      <div class="section">
        <h3>Movimientos bancarios</h3>
        <table class="subtable" id="detail-movimientos">
          <thead>
          <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Monto</th>
            <th>Descripción</th>
          </tr>
          </thead>
          <tbody></tbody>
        </table>
        <div class="empty" id="detail-movimientos-empty" style="display:none;">Sin movimientos registrados.</div>
      </div>
    </div>
  </div>
</div>

<!-- ENTREGA -->
<div class="modal-backdrop" id="entrega-modal">
  <div class="modal">
    <form id="entrega-form">
      <header>
        <h2>Registrar entrega</h2>
        <button type="button" id="entrega-close">&times;</button>
      </header>
      <div class="content">
        <input type="hidden" name="id_orden_pago">
        <div class="info-box">
          Registrará la entrega del cheque al proveedor. El cheque debe estar reservado.
          Además, podés <strong>marcar cuotas</strong> de las CxP incluidas en la OP como
          <em>entregadas/comprometidas</em> (esto no descuenta saldos).
        </div>

        <label>Fecha de entrega
          <input type="date" name="fecha_entrega" required>
        </label>
        <label>Recibido por
          <input type="text" name="recibido_por" maxlength="100" required>
        </label>
        <label>Documento del receptor
          <input type="text" name="ci_recibido" maxlength="50">
        </label>
        <label>Observaciones
          <input type="text" name="observaciones" maxlength="250">
        </label>

        <div class="section" id="entrega-cuotas-section">
          <h3 style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
            Cuotas a comprometer
            <span style="display:flex;align-items:center;gap:10px;font-weight:600;">
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="checkbox" id="entrega-pay-all">
                Seleccionar todas
              </label>
            </span>
          </h3>

          <table class="subtable" id="entrega-cuotas-table">
            <thead>
            <tr>
              <th style="width:42px;text-align:center;">Sel</th>
              <th>Documento / CxP</th>
              <th>Cuota</th>
              <th>Vencimiento</th>
              <th style="text-align:right;">Saldo cuota</th>
              <th style="text-align:right;">Monto a marcar</th>
            </tr>
            </thead>
            <tbody></tbody>
          </table>
          <div class="empty" id="entrega-cuotas-empty" style="display:none;">
            No hay cuotas abiertas en las CxP de esta orden.
          </div>

          <div class="info-box" id="entrega-total-info">Total marcado: -</div>
        </div>

        <div class="error-text" id="entrega-error"></div>
      </div>
      <footer>
        <button type="button" id="entrega-cancel">Cancelar</button>
        <button type="submit" class="primary">Guardar</button>
      </footer>
    </form>
  </div>
</div>

<!-- ANULAR -->
<div class="modal-backdrop" id="anular-modal">
  <div class="modal">
    <form id="anular-form">
      <header>
        <h2>Anular orden de pago</h2>
        <button type="button" id="anular-close">&times;</button>
      </header>
      <div class="content">
        <input type="hidden" name="id_orden_pago">
        <div class="info-box">
          Esta acción anula la orden y el cheque asociado, <strong>liberando la reserva bancaria</strong>.
          Disponible cuando el cheque <strong>no está compensado</strong> (estados: <em>Reservado</em> o <em>Entregado</em>).
        </div>
        <label>Motivo
          <input type="text" name="motivo" maxlength="200" placeholder="Motivo (opcional)">
        </label>
        <div class="error-text" id="anular-error"></div>
      </div>
      <footer>
        <button type="button" id="anular-cancel">Cancelar</button>
        <button type="submit" class="danger">Anular</button>
      </footer>
    </form>
  </div>
</div>

<!-- IMPRIMIR -->
<div class="modal-backdrop" id="print-modal">
  <div class="modal">
    <header>
      <h2>Imprimir cheque</h2>
      <button type="button" id="print-close">&times;</button>
    </header>
    <div class="content">
      <div class="info-box">Revisá los datos antes de imprimir. Al confirmar se registrará la impresión.</div>
      <div id="print-preview">
        <h3>Cheque</h3>
        <div id="print-fields"></div>
      </div>
      <div class="error-text" id="print-error"></div>
    </div>
    <footer>
      <button type="button" id="print-cancel">Cancelar</button>
      <button type="button" class="primary" id="print-confirm">Imprimir</button>
    </footer>
  </div>
</div>

<script>
const apiUrl = 'ordenes_pago_gestion_api.php';
const cxpApiUrl = '../tablero_cxp/api_tablero_cxp.php';

const tbody = document.querySelector('#table-op tbody');
const emptyState = document.getElementById('table-empty');

const detailModal = document.getElementById('detail-modal');
const detailTitle = document.getElementById('detail-title');
const detailGrid = document.getElementById('detail-grid');
const detailChequeBox = document.getElementById('detail-cheque');
const detailFacturasBody = document.querySelector('#detail-facturas tbody');
const detailFacturasEmpty = document.getElementById('detail-facturas-empty');
const detailMovsBody = document.querySelector('#detail-movimientos tbody');
const detailMovsEmpty = document.getElementById('detail-movimientos-empty');

const entregaModal = document.getElementById('entrega-modal');
const entregaForm = document.getElementById('entrega-form');
const entregaError = document.getElementById('entrega-error');
const entregaCuotasTableBody = document.querySelector('#entrega-cuotas-table tbody');
const entregaCuotasEmpty = document.getElementById('entrega-cuotas-empty');
const entregaTotalInfo = document.getElementById('entrega-total-info');
const entregaPayAll = document.getElementById('entrega-pay-all');

const anularModal = document.getElementById('anular-modal');
const anularForm = document.getElementById('anular-form');
const anularError = document.getElementById('anular-error');

const printModal = document.getElementById('print-modal');
const printFields = document.getElementById('print-fields');
const printError = document.getElementById('print-error');
let currentPrintId = null;
let currentPrintData = null;

async function loadData() {
  const params = new URLSearchParams();
  document.querySelectorAll('#filters select, #filters input').forEach(el => {
    if (!el.name) return;
    if (el.value.trim() !== '') params.set(el.name, el.value.trim());
  });

  try {
    const res = await fetch(`${apiUrl}?${params.toString()}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'Error al cargar órdenes de pago');
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
    const estadoBadge = badgeEstado(row.estado);
    const chequeTxt = row.cheque_numero ? `${row.cheque_numero} (${row.cheque_estado || 'Sin estado'})` : 'Sin cheque';

    const opEstado = row.estado; // 'Reservada', 'Entregada', etc.
    const chequeEstado = (row.cheque_estado || '').toLowerCase();

    // Entregar: sólo OP Reservada + cheque Reservado
    const puedeEntregar = (opEstado === 'Reservada' && chequeEstado === 'reservado');

    // Anular: OP en Reservada/Entregada y cheque en reservado/entregado (no compensado)
    const puedeAnular = (['Reservada','Entregada'].includes(opEstado) &&
                         ['reservado','entregado'].includes(chequeEstado));

    // Imprimir: si hay cheque creado
    const puedeImprimir = !!row.cheque_numero;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>#${row.id_orden_pago}</td>
      <td>${row.fecha}</td>
      <td>${escapeHtml(row.proveedor)}</td>
      <td>${formatCurrency(row.total, row.moneda)}</td>
      <td>${estadoBadge}</td>
      <td>${escapeHtml(row.cuenta)}</td>
      <td>${escapeHtml(chequeTxt)}</td>
      <td style="display:flex;gap:6px;flex-wrap:wrap;">
        <button data-action="detalle" data-id="${row.id_orden_pago}" class="outline">Ver</button>
        <button data-action="imprimir" data-id="${row.id_orden_pago}" class="outline" ${!puedeImprimir ? 'disabled title="La orden no tiene cheque"' : ''}>Imprimir cheque</button>
        <button data-action="entrega" data-id="${row.id_orden_pago}" ${!puedeEntregar ? 'disabled title="Sólo cheques reservados"' : ''}>Registrar entrega</button>
        <button data-action="anular" data-id="${row.id_orden_pago}" class="danger" ${!puedeAnular ? 'disabled title="Sólo OP con cheque no compensado (Reservado o Entregado)"' : ''}>Anular</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function badgeEstado(estado) {
  const cls = {
    'Reservada': 'badge reservada',
    'Entregada': 'badge entregada',
    'Anulada':   'badge anulada',
    'Pagada':    'badge pagada'
  }[estado] || 'badge';
  return `<span class="${cls}">${escapeHtml(estado)}</span>`;
}
function escapeHtml(str) {
  return (str ?? '').toString().replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
}
function formatCurrency(value, currency = '') {
  const num = Number(value || 0).toLocaleString('es-PY', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  return currency ? `${currency} ${num}` : num;
}

document.getElementById('btn-search').addEventListener('click', loadData);
document.getElementById('btn-clear').addEventListener('click', () => {
  document.querySelectorAll('#filters select, #filters input').forEach(el => { el.value = ''; });
  loadData();
});
document.getElementById('btn-refresh').addEventListener('click', loadData);

tbody.addEventListener('click', async e => {
  const btn = e.target.closest('button[data-action]');
  if (!btn) return;
  const id = Number(btn.dataset.id);
  const action = btn.dataset.action;
  if (action === 'detalle') {
    openDetail(id);
  } else if (action === 'entrega') {
    openEntrega(id);
  } else if (action === 'anular') {
    openAnular(id);
  } else if (action === 'imprimir') {
    openPrint(id);
  }
});

/* ===== DETALLE ===== */
async function openDetail(id) {
  try {
    const res = await fetch(`${apiUrl}?id=${id}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo cargar la orden');
    const { orden, cheque, detalles, movimientos } = json;

    detailTitle.textContent = `Orden de pago #${orden.id_orden_pago}`;
    detailGrid.innerHTML = `
      <div class="detail-card"><strong>Proveedor</strong><span>${escapeHtml(orden.proveedor.nombre)} (RUC ${escapeHtml(orden.proveedor.ruc || '-')})</span></div>
      <div class="detail-card"><strong>Fecha</strong><span>${orden.fecha}</span></div>
      <div class="detail-card"><strong>Cuenta</strong><span>${escapeHtml(orden.cuenta.banco)} · ${escapeHtml(orden.cuenta.numero)} (${orden.cuenta.moneda})</span></div>
      <div class="detail-card"><strong>Total</strong><span>${formatCurrency(orden.total, orden.moneda)}</span></div>
      <div class="detail-card"><strong>Estado</strong><span>${orden.estado}</span></div>
      <div class="detail-card"><strong>Observación</strong><span>${escapeHtml(orden.observacion || '-')}</span></div>
    `;

    if (cheque) {
      const entrega = cheque.fecha_entrega
        ? `Entregado el ${cheque.fecha_entrega} a ${escapeHtml(cheque.recibido_por || '-')}`
        : 'Sin entrega registrada';
      const docLine = cheque.ci ? `<br>Documento: ${escapeHtml(cheque.ci)}` : '';
      const impresoLine = cheque.impreso_at ? `<br>Impreso: ${cheque.impreso_at} por ${escapeHtml(cheque.impreso_por || '-')}` : '';
      detailChequeBox.innerHTML = `
        <strong>Cheque #${escapeHtml(cheque.numero)}</strong><br>
        Estado: ${escapeHtml(cheque.estado)}<br>
        Fecha de cheque: ${cheque.fecha_cheque}<br>
        ${entrega}${docLine}${impresoLine}<br>
        Observaciones: ${escapeHtml(cheque.observaciones || '-')}
      `;
    } else {
      detailChequeBox.textContent = 'Sin información de cheque.';
    }

    detailFacturasBody.innerHTML = '';
    if (!detalles.length) {
      detailFacturasEmpty.style.display = 'block';
    } else {
      detailFacturasEmpty.style.display = 'none';
      detalles.forEach(f => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${escapeHtml(f.numero)}</td>
          <td>${f.fecha_venc}</td>
          <td>${formatCurrency(f.saldo_actual, f.moneda)}</td>
          <td>${formatCurrency(f.monto_aplicado, f.moneda)}</td>
        `;
        detailFacturasBody.appendChild(tr);
      });
    }

    detailMovsBody.innerHTML = '';
    if (!movimientos.length) {
      detailMovsEmpty.style.display = 'block';
    } else {
      detailMovsEmpty.style.display = 'none';
      movimientos.forEach(m => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${m.fecha}</td>
          <td>${escapeHtml(m.tipo)}</td>
          <td>${formatCurrency(m.monto)}</td>
          <td>${escapeHtml(m.descripcion || '')}</td>
        `;
        detailMovsBody.appendChild(tr);
      });
    }

    detailModal.classList.add('active');
  } catch (err) {
    alert(err.message);
  }
}

function closeDetail() { detailModal.classList.remove('active'); }
document.getElementById('detail-close').addEventListener('click', closeDetail);
detailModal.addEventListener('click', e => { if (e.target === detailModal) closeDetail(); });
detailModal.querySelector('.modal').addEventListener('click', e => e.stopPropagation());

/* ===== ENTREGA (con cuotas) ===== */
async function openEntrega(id) {
  entregaForm.reset();
  entregaForm.id_orden_pago.value = id;
  entregaForm.fecha_entrega.value = new Date().toISOString().slice(0,10);
  entregaError.textContent = '';

  entregaCuotasTableBody.innerHTML = '';
  entregaCuotasEmpty.style.display = 'none';
  entregaTotalInfo.textContent = 'Total marcado: -';
  entregaPayAll.checked = false;

  try {
    const res = await fetch(`${apiUrl}?id=${id}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo cargar la orden');
    const { detalles, orden } = json;

    const cxpIds = Array.from(new Set((detalles || []).map(d => d.id_cxp)));
    const responses = await Promise.all(
      cxpIds.map(cid => fetch(`${cxpApiUrl}?id_cxp=${cid}`, { credentials: 'same-origin' }).then(r => r.json()).catch(() => ({ok:false})))
    );

    const allRows = [];
    responses.forEach((r) => {
      if (!r || !r.ok || !r.cxp) return;
      const cxp = r.cxp;
      const cuotas = cxp.cuotas || [];
      const docLabel = `${cxp.documento.numero} (CxP #${cxp.id_cxp})`;
      cuotas.forEach(c => {
        if (!['Pendiente','Parcial'].includes(c.estado)) return;
        if ((c.saldo || 0) <= 0) return;
        allRows.push({
          id_cxp: cxp.id_cxp,
          doc: docLabel,
          id_cxp_det: c.id_cxp_det,
          nro: c.nro,
          venc: c.vencimiento,
          saldo: Number(c.saldo || 0),
          moneda: cxp.moneda || orden.moneda
        });
      });
    });

    if (!allRows.length) {
      entregaCuotasEmpty.style.display = 'block';
    } else {
      renderEntregaCuotas(allRows);
    }

    entregaModal.classList.add('active');
  } catch (err) {
    entregaError.textContent = err.message;
  }
}

function renderEntregaCuotas(items) {
  entregaCuotasTableBody.innerHTML = '';
  items.forEach(it => {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td style="text-align:center;">
        <input type="checkbox" class="entrega-check"
               data-id-cxp="${it.id_cxp}"
               data-id-cxp-det="${it.id_cxp_det}">
      </td>
      <td><div style="font-weight:600;">${escapeHtml(it.doc)}</div></td>
      <td>#${it.nro}</td>
      <td>${it.venc}</td>
      <td style="text-align:right;">${formatCurrency(it.saldo, it.moneda)}</td>
      <td style="text-align:right;">
        <input type="number"
               class="entrega-amount"
               data-id-cxp="${it.id_cxp}"
               data-id-cxp-det="${it.id_cxp_det}"
               min="0.01" step="0.01" max="${it.saldo}"
               value=""
               disabled
               style="width:120px;">
      </td>
    `;
    entregaCuotasTableBody.appendChild(tr);
  });

  entregaCuotasTableBody.addEventListener('change', entregaTableChangeHandler);
  entregaPayAll.addEventListener('change', toggleEntregaAll);

  recalcEntregaTotal();
}

function entregaTableChangeHandler(e) {
  const cb = e.target.closest('.entrega-check');
  if (cb) {
    const idDet = cb.dataset.idCxpDet;
    const amt = entregaCuotasTableBody.querySelector(`.entrega-amount[data-id-cxp-det="${idDet}"]`);
    if (!amt) return;
    if (cb.checked) {
      amt.disabled = false;
      const max = parseFloat(amt.getAttribute('max')) || 0;
      amt.value = max ? max.toFixed(2) : '';
    } else {
      amt.value = '';
      amt.disabled = true;
    }
    recalcEntregaTotal();
    return;
  }

  const input = e.target.closest('.entrega-amount');
  if (input) {
    let val = parseFloat(input.value);
    const max = parseFloat(input.getAttribute('max')) || 0;
    if (isNaN(val) || val < 0) val = 0;
    if (val > max) val = max;
    input.value = val ? val.toFixed(2) : '';
    const idDet = input.dataset.idCxpDet;
    const cb2 = entregaCuotasTableBody.querySelector(`.entrega-check[data-id-cxp-det="${idDet}"]`);
    if (cb2) cb2.checked = !!val;
    recalcEntregaTotal();
  }
}

function toggleEntregaAll(e) {
  const checked = e.target.checked;
  const checks = Array.from(entregaCuotasTableBody.querySelectorAll('.entrega-check'));
  checks.forEach(cb => {
    const idDet = cb.dataset.idCxpDet;
    const amt = entregaCuotasTableBody.querySelector(`.entrega-amount[data-id-cxp-det="${idDet}"]`);
    cb.checked = checked;
    if (amt) {
      if (checked) {
        amt.disabled = false;
        const max = parseFloat(amt.getAttribute('max')) || 0;
        amt.value = max ? max.toFixed(2) : '';
      } else {
        amt.value = '';
        amt.disabled = true;
      }
    }
  });
  recalcEntregaTotal();
}

function recalcEntregaTotal() {
  const inputs = Array.from(entregaCuotasTableBody.querySelectorAll('.entrega-amount'));
  let total = 0;
  inputs.forEach(i => {
    const v = parseFloat(i.value);
    if (!isNaN(v)) total += v;
  });
  entregaTotalInfo.textContent = `Total marcado: ${formatCurrency(total)}`;
}

function closeEntrega(){
  entregaCuotasTableBody.replaceWith(entregaCuotasTableBody.cloneNode(true));
  entregaModal.classList.remove('active');
}
document.getElementById('entrega-close').addEventListener('click', closeEntrega);
document.getElementById('entrega-cancel').addEventListener('click', closeEntrega);
entregaModal.addEventListener('click', e => { if (e.target === entregaModal) closeEntrega(); });
entregaModal.querySelector('.modal').addEventListener('click', e => e.stopPropagation());

entregaForm.addEventListener('submit', async e => {
  e.preventDefault();
  entregaError.textContent = '';
  const form = new FormData(entregaForm);
  const id = form.get('id_orden_pago');

  const seleccion = Array.from(entregaCuotasTableBody.querySelectorAll('.entrega-amount'))
    .map(input => ({
      id_cxp: Number(input.dataset.idCxp),
      id_cxp_det: Number(input.dataset.idCxpDet),
      monto: parseFloat(input.value || '0')
    }))
    .filter(x => x.id_cxp > 0 && x.id_cxp_det > 0 && x.monto > 0);

  const payload = {
    accion: 'entrega',
    fecha_entrega: form.get('fecha_entrega'),
    recibido_por: form.get('recibido_por'),
    ci_recibido: form.get('ci_recibido'),
    observaciones: form.get('observaciones'),
    cuotas: seleccion
  };

  try {
    const res = await fetch(`${apiUrl}?id=${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo registrar la entrega');
    closeEntrega();
    await loadData();
  } catch (err) {
    entregaError.textContent = err.message;
  }
});

/* ===== ANULAR ===== */
function openAnular(id) {
  anularForm.reset();
  anularForm.id_orden_pago.value = id;
  anularError.textContent = '';
  anularModal.classList.add('active');
}
function closeAnular(){ anularModal.classList.remove('active'); }
document.getElementById('anular-close').addEventListener('click', closeAnular);
document.getElementById('anular-cancel').addEventListener('click', closeAnular);
anularModal.addEventListener('click', e => { if (e.target === anularModal) closeAnular(); });
anularModal.querySelector('.modal').addEventListener('click', e => e.stopPropagation());

anularForm.addEventListener('submit', async e => {
  e.preventDefault();
  anularError.textContent = '';
  const form = new FormData(anularForm);
  const id = form.get('id_orden_pago');
  if (!confirm('¿Seguro que deseas anular esta orden de pago y su cheque asociado?')) return;

  const payload = { accion: 'anular', motivo: form.get('motivo') };

  try {
    const res = await fetch(`${apiUrl}?id=${id}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(payload)
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo anular la orden');
    closeAnular();
    await loadData();
  } catch (err) {
    anularError.textContent = err.message;
  }
});

/* ===== IMPRIMIR ===== */
async function openPrint(id) {
  try {
    const res = await fetch(`${apiUrl}?id=${id}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo cargar la orden');
    const { orden, cheque } = json;
    if (!cheque) {
      alert('La orden no tiene cheque asociado.');
      return;
    }
    currentPrintId = id;
    currentPrintData = { orden, cheque };

    const montoTexto = formatCurrency(orden.total, orden.moneda);
    const fecha = cheque.fecha_cheque || orden.fecha;
    printFields.innerHTML = `
      <div class="field"><span>Banco:</span><span>${escapeHtml(orden.cuenta.banco)}</span></div>
      <div class="field"><span>Nº cuenta:</span><span>${escapeHtml(orden.cuenta.numero)}</span></div>
      <div class="field"><span>Cheque Nº:</span><span>${escapeHtml(cheque.numero)}</span></div>
      <div class="field"><span>Fecha:</span><span>${fecha}</span></div>
      <div class="field"><span>Beneficiario:</span><span>${escapeHtml(orden.proveedor.nombre)}</span></div>
      <div class="field"><span>Documento:</span><span>${escapeHtml(orden.proveedor.ruc || '-')}</span></div>
      <div class="amount">${montoTexto}</div>
      <div class="field"><span>Observaciones:</span><span>${escapeHtml(orden.observacion || '')}</span></div>
    `;
    printError.textContent = '';
    printModal.classList.add('active');
  } catch (err) {
    alert(err.message);
  }
}

function closePrint() {
  printModal.classList.remove('active');
  currentPrintId = null;
  currentPrintData = null;
}
document.getElementById('print-close').addEventListener('click', closePrint);
document.getElementById('print-cancel').addEventListener('click', closePrint);
printModal.addEventListener('click', e => { if (e.target === printModal) closePrint(); });
printModal.querySelector('.modal').addEventListener('click', e => e.stopPropagation());

document.getElementById('print-confirm').addEventListener('click', async () => {
  if (!currentPrintId || !currentPrintData) return;
  printError.textContent = '';
  try {
    const res = await fetch(`${apiUrl}?id=${currentPrintId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({ accion: 'imprimir' })
    });
    const json = await res.json();
    if (!json.ok) throw new Error(json.error || 'No se pudo registrar la impresión');
  } catch (err) {
    printError.textContent = err.message;
    return;
  }

  const html = document.getElementById('print-preview').innerHTML;
  const printWindow = window.open('', 'PRINT', 'height=600,width=800');
  printWindow.document.write('<html><head><title>Cheque</title>');
  printWindow.document.write('<style>body{font-family:"Segoe UI",Arial,sans-serif;padding:24px;}h3{text-align:center;} .field{display:flex;justify-content:space-between;margin-bottom:8px;font-size:14px;} .amount{font-size:20px;font-weight:700;margin:16px 0;text-align:right;border-top:1px solid #ccc;padding-top:10px;}</style>');
  printWindow.document.write('</head><body>');
  printWindow.document.write(html);
  printWindow.document.write('</body></html>');
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
  printWindow.close();

  closePrint();
  loadData();
});

/* init */
loadData();
</script>

</body>
</html>
