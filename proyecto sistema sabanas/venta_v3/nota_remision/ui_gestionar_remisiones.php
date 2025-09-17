<?php
// ventas/remision/gestionar_remisiones.php
session_start();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Gestión de Notas de Remisión</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root { --gap:12px; --pad:8px; }
  body{font-family:system-ui,Arial,sans-serif;margin:20px;color:#222}
  h1{margin:0 0 10px}
  .row{display:flex;gap:var(--gap);flex-wrap:wrap;margin:8px 0}
  label{font-weight:600}
  input,select,button{padding:var(--pad)}
  .card{border:1px solid #ddd;border-radius:8px;padding:12px;margin-bottom:12px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{border:1px solid #eee;padding:6px;text-align:left}
  .right{text-align:right}
  .muted{color:#666}
  .btn{cursor:pointer;border:1px solid #ddd;background:#fff;border-radius:6px}
  .btn.primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .mini{font-size:12px}
  .pill{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:2px 8px;font-size:12px}
  .pill.red{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
  .pill.green{background:#dcfce7;border-color:#bbf7d0;color:#14532d}
</style>
</head>
<body>
<div id="navbar-container"></div>
<h1>Gestión de Notas de Remisión</h1>

<div class="card">
  <div class="row">
    <div>
      <label>Fecha desde</label><br/>
      <input id="fDesde" type="date">
    </div>
    <div>
      <label>Fecha hasta</label><br/>
      <input id="fHasta" type="date">
    </div>
    <div style="flex:1">
      <label>Cliente (RUC/CI o Nombre)</label><br/>
      <input id="qCli" type="text" placeholder="1234567-8 o Pérez" style="width:100%">
    </div>
    <div>
      <label>Nº Remisión</label><br/>
      <input id="qNum" type="text" placeholder="001-001-0000123">
    </div>
    <div>
      <label>Estado</label><br/>
      <select id="qEst">
        <option value="">Todos</option>
        <option value="Emitida" selected>Emitida</option>
        <option value="Anulada">Anulada</option>
      </select>
    </div>
  </div>
  <div class="row" style="align-items:center">
    <div style="flex:1"></div>
    <button id="btnBuscar" class="btn">Buscar</button>
  </div>

  <table id="grid" style="margin-top:10px">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Nº Remisión</th>
        <th>Factura</th>
        <th>Cliente</th>
        <th>RUC/CI</th>
        <th class="right">Ítems</th>
        <th class="right">Cant. total</th>
        <th>Estado</th>
        <th style="width:180px">Acciones</th>
      </tr>
    </thead>
    <tbody><tr><td colspan="9" class="muted">Sin resultados</td></tr></tbody>
  </table>
</div>

<script>
const $=id=>document.getElementById(id);
const money=n=>Number(n||0).toLocaleString('es-PY');
const PRINT_URL = '../nota_remision/nota_remision_print.php';

function hoyISO(){ const d=new Date(); return d.toISOString().slice(0,10); }
function hoyMenosDias(d){ const x=new Date(); x.setDate(x.getDate()-d); return x.toISOString().slice(0,10); }

$('btnBuscar').onclick = async ()=>{
  const params = new URLSearchParams({
    cli:  $('qCli').value.trim(),
    num:  $('qNum').value.trim(),
    est:  $('qEst').value,
    desde: $('fDesde').value || '',
    hasta: $('fHasta').value || '',
    page: 1, page_size: 50
  });
  const tb = $('grid').querySelector('tbody');
  tb.innerHTML = '<tr><td colspan="9" class="muted">Buscando...</td></tr>';
  try{
    const r=await fetch('buscar_remisiones.php?'+params.toString());
    const j=await r.json();
    let rows = (j.success && Array.isArray(j.remisiones)) ? j.remisiones : [];
    tb.innerHTML='';
    if(!rows.length){
      tb.innerHTML = '<tr><td colspan="9" class="muted">Sin resultados</td></tr>';
      return;
    }
    rows.forEach(x=>{
      const tr=document.createElement('tr');
      const pill = x.estado==='Anulada' ? '<span class="pill red">Anulada</span>' : '<span class="pill green">Emitida</span>';
      tr.innerHTML = `
        <td>${x.fecha}</td>
        <td>${x.numero_documento||'-'}</td>
        <td>${x.factura_numero||'-'}</td>
        <td>${x.cliente||'-'}</td>
        <td>${x.ruc_ci||'-'}</td>
        <td class="right">${money(x.cant_items||0)}</td>
        <td class="right">${money(x.cant_total||0)}</td>
        <td>${pill}</td>
        <td>
          <button class="btn mini imprimir" data-id="${x.id_remision}">Imprimir</button>
          <button class="btn mini anular" data-id="${x.id_remision}" ${x.estado==='Anulada'?'disabled':''}>Anular</button>
        </td>
      `;
      tb.appendChild(tr);
    });

    // acciones
    tb.querySelectorAll('.imprimir').forEach(b=>{
      b.onclick = ()=>{
        const id = b.dataset.id;
        const url = `${PRINT_URL}?id=${encodeURIComponent(id)}&auto=1`;
        let w = window.open('', '_blank');
        if (w && !w.closed) { try { w.opener=null; w.location.replace(url); } catch(e){ location.href=url; } }
        else { location.href=url; }
      };
    });
    tb.querySelectorAll('.anular').forEach(b=>{
      b.onclick = async ()=>{
        const id = b.dataset.id;
        if(!confirm('¿Seguro que querés ANULAR esta remisión?')) return;
        b.disabled = true;
        try{
          const r = await fetch('anular_remision.php', {
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({ id_remision: Number(id), motivo: 'Anulación desde gestión' })
          });
          const j = await r.json();
          if(!j.success) throw new Error(j.error||'No se pudo anular');
          alert('Remisión anulada.');
          $('btnBuscar').click(); // refrescar
        }catch(e){
          alert(e.message);
        }finally{
          b.disabled = false;
        }
      };
    });

  }catch(e){
    console.error(e);
    $('grid').querySelector('tbody').innerHTML = '<tr><td colspan="9" class="muted">Error al buscar</td></tr>';
  }
};

// init
window.addEventListener('DOMContentLoaded', ()=>{
  $('fHasta').value = hoyISO();
  $('fDesde').value = hoyMenosDias(30);
  $('btnBuscar').click();
});
</script>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
