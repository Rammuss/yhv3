<?php
// ui_cumplimiento_oc.php
session_start();
if (!isset($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
$usuario = $_SESSION['nombre_usuario'] ?? 'Usuario';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Cumplimiento de OCs | Sistema de Compras</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Estilo corporativo sobrio (sin overlays sobre botones) -->
  <style>
    :root{
      --bg: #f5f7fb;
      --surface:#ffffff;
      --ink:#111827;
      --muted:#6b7280;
      --brand:#0e6efd; /* azul corporativo */
      --brand-2:#495057;
      --ok:#0d9488;
      --warn:#b45309;
      --danger:#b91c1c;
      --border:#e5e7eb;
      --radius:14px;
      --shadow:0 14px 28px rgba(0,0,0,.08);
    }
    *{box-sizing:border-box}
    body{
      margin:0; background:var(--bg); color:var(--ink);
      font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Noto Sans,sans-serif;
    }
    .wrap{max-width:1200px; margin:0 auto; padding:24px}
    header.card{
      display:flex; justify-content:space-between; align-items:center; gap:16px;
      background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
      padding:16px 18px; box-shadow:var(--shadow);
    }
    .brand{
      display:flex; flex-direction:column; gap:4px;
    }
    .brand small{ color:var(--muted) }
    .brand h1{ margin:0; font-size:1.4rem; letter-spacing:.3px }
    .actions{ display:flex; gap:10px; flex-wrap:wrap }
    .btn{
      appearance:none; border:1px solid var(--border); background:#fff; color:var(--ink);
      border-radius:999px; padding:10px 14px; font-weight:600; cursor:pointer;
      box-shadow:0 6px 14px rgba(0,0,0,.06);
      transition:transform .15s ease, box-shadow .15s ease, background .15s ease;
    }
    .btn:hover{ transform:translateY(-1px); box-shadow:0 10px 18px rgba(0,0,0,.08); background:#fafafa }
    .btn.primary{ background:var(--brand); color:#fff; border-color:var(--brand) }
    .btn.primary:hover{ background:#0b5ed7 }
    /* Filtros */
    .filters.card{
      margin-top:18px; padding:16px; background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); box-shadow:var(--shadow);
      display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:12px;
      align-items:end;
    }
    label{ font-size:.9rem; color:var(--brand-2); display:block; margin-bottom:6px }
    input, select{
      width:100%; padding:10px 12px; border-radius:10px; border:1px solid var(--border); background:#fff; color:var(--ink);
      transition:border-color .15s ease, box-shadow .15s ease;
    }
    input:focus, select:focus{ outline:none; border-color:#cbd5e1; box-shadow:0 0 0 3px rgba(14,110,253,.12) }
    /* Tabla */
    .table.card{
      margin-top:18px; padding:0; overflow:auto; border-radius:var(--radius); border:1px solid var(--border); background:var(--surface); box-shadow:var(--shadow);
    }
    table{ width:100%; border-collapse:collapse; font-size:.95rem }
    thead th{ position:sticky; top:0; background:#f8fafc; color:#374151; text-align:left; padding:10px 12px; border-bottom:1px solid var(--border) }
    tbody td{ padding:10px 12px; border-bottom:1px solid #f1f5f9 }
    tbody tr:hover td{ background:#f9fafb }
    .right{text-align:right}
    .muted{ color:var(--muted) }
    tfoot td{ padding:12px; font-weight:700; background:#f8fafc }
    .badge{
      display:inline-block; padding:4px 8px; font-size:.8rem; border-radius:999px; border:1px solid var(--border); background:#fff; color:#374151
    }
    .badge.ok{ color:var(--ok); border-color:#99f6e4; background:#ecfeff }
    .badge.warn{ color:var(--warn); border-color:#fcd34d; background:#fffbeb }
    .badge.danger{ color:var(--danger); border-color:#fecaca; background:#fef2f2 }
    /* Impresión */
    @media print{
      header.card .actions .btn:not(.print){ display:none }
      .filters.card{ break-inside:avoid }
      .table.card{ box-shadow:none; border-color:#ddd }
      body{ background:#fff }
    }
  </style>
</head>
<body>
  <div class="wrap">
    <header class="card">
      <div class="brand">
        <small>Módulo de Compras</small>
        <h1>Cumplimiento de Órdenes de Compra</h1>
        <small class="muted">Usuario: <?= htmlspecialchars($usuario) ?></small>
      </div>
      <div class="actions">
        <button class="btn print" onclick="window.print()">Imprimir</button>
        <a class="btn" href="javascript:history.back()">Volver</a>
      </div>
    </header>

    <section class="filters card" id="form-filtros">
      <div>
        <label>OC · Desde</label>
        <input type="date" id="f_oc_desde">
      </div>
      <div>
        <label>OC · Hasta</label>
        <input type="date" id="f_oc_hasta">
      </div>
      <div>
        <label>Factura · Desde</label>
        <input type="date" id="f_fac_desde">
      </div>
      <div>
        <label>Factura · Hasta</label>
        <input type="date" id="f_fac_hasta">
      </div>
      <div>
        <label>ID Proveedor</label>
        <input type="number" id="f_proveedor" placeholder="Ej: 12">
      </div>
      <div>
        <label>ID Sucursal</label>
        <input type="number" id="f_sucursal" placeholder="Ej: 1">
      </div>
      <div>
        <label>Estado OC</label>
        <input type="text" id="f_estado" placeholder="Emitida / Aprobada / ...">
      </div>
      <div>
        <label>Condición</label>
        <select id="f_condicion">
          <option value="">(Todas)</option>
          <option value="CONTADO">CONTADO</option>
          <option value="CREDITO">CREDITO</option>
        </select>
      </div>
      <div>
        <button class="btn primary" id="btn-buscar">Buscar</button>
      </div>
    </section>

    <section class="table card">
      <table id="tabla-res">
        <thead>
          <tr>
            <th>ID OC</th>
            <th>N° Pedido</th>
            <th>Fecha OC</th>
            <th>Proveedor</th>
            <th>Sucursal</th>
            <th>Condición</th>
            <th>Estado</th>
            <th class="right">Ordenado</th>
            <th class="right">Facturado</th>
            <th class="right">Pendiente</th>
            <th class="right">% Cumpl.</th>
          </tr>
        </thead>
        <tbody>
          <tr><td colspan="11" class="muted">Usá los filtros y presioná <strong>Buscar</strong>.</td></tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="7" class="right">Totales</td>
            <td class="right" id="tot-ordenado">0</td>
            <td class="right" id="tot-facturado">0</td>
            <td class="right" id="tot-pendiente">0</td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </section>
  </div>

  <script>
    const $ = sel => document.querySelector(sel);

    function n(v){
      v = Number(v || 0);
      return v.toLocaleString('es-PY', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }
    function pct(v){
      v = Number(v || 0);
      return v.toLocaleString('es-PY', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' %';
    }

    async function cargar(){
      const p = new URLSearchParams();
      if ($('#f_oc_desde').value)  p.set('fecha_oc_desde',  $('#f_oc_desde').value);
      if ($('#f_oc_hasta').value)  p.set('fecha_oc_hasta',  $('#f_oc_hasta').value);
      if ($('#f_fac_desde').value) p.set('fecha_fac_desde', $('#f_fac_desde').value);
      if ($('#f_fac_hasta').value) p.set('fecha_fac_hasta', $('#f_fac_hasta').value);
      if ($('#f_proveedor').value) p.set('proveedor_id',     $('#f_proveedor').value);
      if ($('#f_sucursal').value)  p.set('sucursal_id',      $('#f_sucursal').value);
      if ($('#f_estado').value)    p.set('estado_oc',        $('#f_estado').value);
      if ($('#f_condicion').value) p.set('condicion_pago',   $('#f_condicion').value);

      const url = 'api_cumplimiento_oc.php?' + p.toString();
      const res = await fetch(url);
      const data = await res.json();

      const tbody = $('#tabla-res tbody');
      tbody.innerHTML = '';

      if (!data.success){
        tbody.innerHTML = `<tr><td colspan="11" class="muted">Error: ${data.error ?? 'desconocido'}</td></tr>`;
        return;
      }

      if (!data.rows || !data.rows.length){
        tbody.innerHTML = `<tr><td colspan="11" class="muted">Sin resultados para los filtros seleccionados.</td></tr>`;
        $('#tot-ordenado').textContent  = 0;
        $('#tot-facturado').textContent = 0;
        $('#tot-pendiente').textContent = 0;
        return;
      }

      const frag = document.createDocumentFragment();
      data.rows.forEach(r=>{
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${r.id_oc}</td>
          <td>${r.numero_pedido}</td>
          <td>${r.fecha_emision ?? ''}</td>
          <td>${r.proveedor ?? ''}</td>
          <td>${r.sucursal ?? ''}</td>
          <td>${r.condicion_pago ?? ''}</td>
          <td>${r.estado ?? ''}</td>
          <td class="right">${n(r.monto_ordenado)}</td>
          <td class="right">${n(r.monto_facturado)}</td>
          <td class="right">${n(r.pendiente)}</td>
          <td class="right">${pct(r.cumplimiento_pct)}</td>
        `;
        frag.appendChild(tr);
      });
      tbody.appendChild(frag);

      $('#tot-ordenado').textContent  = n(data.totals?.monto_ordenado);
      $('#tot-facturado').textContent = n(data.totals?.monto_facturado);
      $('#tot-pendiente').textContent = n(data.totals?.pendiente);
    }

    $('#btn-buscar').addEventListener('click', (e)=>{ e.preventDefault(); cargar(); });

    // Atajo: Enter en filtros dispara búsqueda
    document.getElementById('form-filtros').addEventListener('keydown', (e)=>{
      if (e.key === 'Enter') { e.preventDefault(); cargar(); }
    });
  </script>
</body>
</html>
