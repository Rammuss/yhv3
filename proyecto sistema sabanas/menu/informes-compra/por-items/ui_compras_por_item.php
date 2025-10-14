<?php
// ui_compras_por_item.php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
$usuario = htmlspecialchars($_SESSION['nombre_usuario']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Informe: Compras por producto / categoría</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Estilo neutral (empresa) sin overlays sobre botones -->
<style>
  :root{
    --bg: #f5f7fb;
    --card: #ffffff;
    --text: #1f2937;
    --muted: #6b7280;
    --line: #e5e7eb;
    --brand: #0d47a1;          /* azul corporativo */
    --brand-2: #2563eb;        /* azul acción */
    --ok: #0f766e;
    --warn: #b45309;
    --danger:#b91c1c;
    --radius: 14px;
  }
  *{ box-sizing:border-box }
  body{
    margin:0; background:var(--bg); color:var(--text);
    font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;
  }
  header{
    position:sticky; top:0; z-index:10;
    background:var(--card); border-bottom:1px solid var(--line);
    padding:12px 16px;
  }
  .brandline{ display:flex; align-items:center; justify-content:space-between; gap:12px; max-width:1200px; margin:0 auto;}
  .brandline h1{ margin:0; font-size:1.1rem; font-weight:700; letter-spacing:.3px; color:var(--brand) }
  .brandline small{ color:var(--muted) }

  .page{
    max-width:1200px; margin:20px auto; padding:0 16px 40px;
  }
  .card{
    background:var(--card); border:1px solid var(--line); border-radius:var(--radius);
    box-shadow: 0 6px 16px rgba(0,0,0,.06);
  }
  .filters{ padding:16px; display:grid; gap:10px;
    grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
    align-items:end;
  }
  label{ font-weight:600; font-size:.9rem; color:#374151 }
  input, select{
    width:100%; padding:9px 10px; border:1px solid var(--line); border-radius:10px;
    background:#fff; color:var(--text); font-size:.95rem;
  }
  .actions{ display:flex; gap:10px; flex-wrap:wrap; }
  button, .btn{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    padding:10px 14px; border:none; border-radius:10px; cursor:pointer; font-weight:600;
    background:var(--brand-2); color:#fff; box-shadow:0 8px 16px rgba(37,99,235,.18);
    transition:transform .15s ease, box-shadow .15s ease, filter .15s ease;
  }
  button:hover, .btn:hover{ transform: translateY(-1px); box-shadow:0 12px 22px rgba(37,99,235,.22); }
  button.secondary{ background:#111827; box-shadow:0 8px 16px rgba(17,24,39,.18); }
  button.print{ background:#0f766e; }
  button.csv{ background:#6b21a8; }
  /* importante: sin ::after/::before semi-transparentes sobre botones */

  .table-wrap{ padding:8px 16px 16px; }
  table{ width:100%; border-collapse:collapse; }
  th, td{ padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; }
  th{ background:#f3f4f6; font-size:.85rem; text-transform:uppercase; letter-spacing:.3px; }
  td.right, th.right{ text-align:right; }
  .muted{ color:var(--muted) }

  .totals{
    display:flex; gap:16px; flex-wrap:wrap; padding:12px 16px; border-top:1px solid var(--line);
    background:#fafafa; border-radius:0 0 var(--radius) var(--radius);
  }
  .kpi{ background:#fff; border:1px solid var(--line); border-radius:10px; padding:10px 12px; min-width:180px; }
  .kpi b{ display:block; font-size:1.05rem }
  .kpi span{ color:var(--muted); font-size:.9rem }

  @media print{
    header, .filters .actions .csv, .filters .actions .secondary { display:none !important; }
    body{ background:#fff }
    .card{ border:none; box-shadow:none }
    .page{ margin:0; padding:0 }
    th{ background:#eee }
  }
</style>
</head>
<body>
<header>
  <div class="brandline">
    <h1>Informe de Compras por Producto</h1>
    <small>Usuario: <?= $usuario ?></small>
  </div>
</header>

<main class="page">
  <section class="card">
    <form id="filtros" class="filters" onsubmit="return false;">
      <div>
        <label>Desde</label>
        <input type="date" name="from" required>
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" name="to" required>
      </div>
      <div>
        <label>Agrupar por</label>
        <select name="agrupacion">
          <option value="producto">Producto</option>
          <option value="categoria">Categoría</option>
        </select>
      </div>
      <div>
        <label>Ordenar por</label>
        <select name="order_by">
          <option value="importe">Importe</option>
          <option value="cantidad">Cantidad</option>
        </select>
      </div>
      <div>
        <label>IVA</label>
        <select name="iva">
          <option value="">Todos</option>
          <option value="10%">10%</option>
          <option value="5%">5%</option>
          <option value="EXE">Exentas</option>
        </select>
      </div>
      <div>
        <label>ID Proveedor (opcional)</label>
        <input type="number" name="proveedor_id" min="1" placeholder="Ej. 12">
      </div>
      <div>
        <label>ID Sucursal (opcional)</label>
        <input type="number" name="sucursal_id" min="1" placeholder="Ej. 1">
      </div>
      <div>
        <label>Buscar (producto/categoría)</label>
        <input type="text" name="q" maxlength="100" placeholder="ej.: shampoo">
      </div>
      <div class="actions">
        <button class="secondary" type="button" onclick="window.history.back()">Volver</button>
        <button id="btnBuscar" type="button">Buscar</button>
        <button class="print" type="button" onclick="window.print()">Imprimir</button>
        <button class="csv" type="button" id="btnCSV">Exportar CSV</button>
        <button class="secondary" type="reset">Limpiar</button>
      </div>
    </form>

    <div class="table-wrap">
      <table id="tabla">
        <thead>
          <tr id="thead-row">
            <!-- se completa dinámicamente -->
          </tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="7" class="muted">Completa los filtros y presioná “Buscar”.</td></tr>
        </tbody>
      </table>
    </div>

    <div class="totals" id="totals" style="display:none">
      <div class="kpi"><b id="kpi-importe">0</b><span>Total importe</span></div>
      <div class="kpi"><b id="kpi-cantidad">0</b><span>Total cantidad</span></div>
      <div class="kpi"><b id="kpi-items">0</b><span>Ítems listados</span></div>
    </div>
  </section>
</main>

<script>
const $f = document.getElementById('filtros');
const $tbody = document.getElementById('tbody');
const $thead = document.getElementById('thead-row');
const $totals = document.getElementById('totals');
const $kImp = document.getElementById('kpi-importe');
const $kCant = document.getElementById('kpi-cantidad');
const $kItems = document.getElementById('kpi-items');

document.getElementById('btnBuscar').addEventListener('click', cargar);
document.getElementById('btnCSV').addEventListener('click', exportCSV);

function fmtN(n, d=0){
  return Number(n).toLocaleString('es-PY', {minimumFractionDigits:d, maximumFractionDigits:d});
}

function buildQuery(){
  const p = new URLSearchParams(new FormData($f));
  // limit razonable
  if (!p.get('limit')) p.set('limit','500');
  return p.toString();
}

async function cargar(){
  const qs = buildQuery();
  $tbody.innerHTML = '<tr><td colspan="8" class="muted">Cargando...</td></tr>';
  try{
    const r = await fetch('api_compras_por_item.php?' + qs, {cache:'no-store'});
    const j = await r.json();
    if(!j.success) throw new Error(j.error || 'Error desconocido');

    const agrup = new URLSearchParams(qs).get('agrupacion') || 'producto';

    // Cabecera
    if (agrup === 'categoria'){
      $thead.innerHTML = `
        <th>Categoría</th>
        <th class="right">Cantidad total</th>
        <th class="right">Importe total</th>
        <th class="right">% Part.</th>`;
    } else {
      $thead.innerHTML = `
        <th>ID</th>
        <th>Producto</th>
        <th>Categoría</th>
        <th>IVA</th>
        <th class="right">Cantidad total</th>
        <th class="right">Importe total</th>
        <th class="right">% Part.</th>`;
    }

    // Cuerpo
    if (!j.rows || j.rows.length === 0){
      $tbody.innerHTML = '<tr><td colspan="8" class="muted">Sin resultados.</td></tr>';
    } else {
      const rows = j.rows.map(r => {
        if (agrup === 'categoria'){
          return `<tr>
            <td>${r.categoria ?? '(Sin categoría)'}</td>
            <td class="right">${fmtN(r.cantidad_total, 2)}</td>
            <td class="right">${fmtN(r.importe_total, 0)}</td>
            <td class="right">${fmtN(r.participacion_pct, 2)}%</td>
          </tr>`;
        } else {
          return `<tr>
            <td>${r.id_producto ?? ''}</td>
            <td>${r.producto ?? ''}</td>
            <td>${r.categoria ?? '(Sin categoría)'}</td>
            <td>${r.tipo_iva ?? ''}</td>
            <td class="right">${fmtN(r.cantidad_total, 2)}</td>
            <td class="right">${fmtN(r.importe_total, 0)}</td>
            <td class="right">${fmtN(r.participacion_pct, 2)}%</td>
          </tr>`;
        }
      }).join('');
      $tbody.innerHTML = rows;
    }

    // Totales
    $kImp.textContent = fmtN(j.totals?.importe_total ?? 0, 0);
    $kCant.textContent = fmtN(j.totals?.cantidad_total ?? 0, 2);
    $kItems.textContent = fmtN(j.rows?.length ?? 0, 0);
    $totals.style.display = 'flex';

  }catch(err){
    console.error(err);
    $tbody.innerHTML = `<tr><td colspan="8" class="muted">Error: ${err.message}</td></tr>`;
    $totals.style.display = 'none';
  }
}

function exportCSV(){
  const table = document.getElementById('tabla');
  const rows = [...table.querySelectorAll('tr')].map(tr =>
    [...tr.children].map(td =>
      `"${(td.textContent || '').replace(/"/g,'""')}"`
    ).join(',')
  ).join('\r\n');

  const blob = new Blob([rows], {type:'text/csv;charset=utf-8;'});
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'compras_por_item.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
</script>
</body>
</html>
