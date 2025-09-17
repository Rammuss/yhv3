<?php
// ventas/remision/remisiones.php
session_start();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Nota de Remisión</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root { --gap:12px; --pad:8px; }
  body{font-family:system-ui,Arial,sans-serif;margin:20px;color:#222}
  h1{margin:0 0 10px}
  .row{display:flex;gap:var(--gap);flex-wrap:wrap;margin:8px 0}
  label{font-weight:600}
  input,select,button,textarea{padding:var(--pad)}
  textarea{width:100%;min-height:64px}
  .card{border:1px solid #ddd;border-radius:8px;padding:12px;margin-bottom:12px}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{border:1px solid #eee;padding:6px;text-align:left}
  .right{text-align:right}
  .muted{color:#666}
  .btn{cursor:pointer;border:1px solid #ddd;background:#fff;border-radius:6px}
  .btn.primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .mini{font-size:12px}
  input[readonly]{background:#f7f7f7}
  .pill{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:2px 8px;font-size:12px}
  /* ayuda visual: hacer “anclas” fáciles de encontrar al hacer scroll */
  #boxCab, #boxItems { scroll-margin-top: 16px; }
</style>
</head>
<body>
<div id="navbar-container"></div>
<h1>Emitir Nota de Remisión</h1>

<!-- BUSCADOR DE FACTURAS -->
<div class="card">
  <h3 style="margin-top:0">Buscar factura</h3>
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
      <label>Nº Factura</label><br/>
      <input id="qNum" type="text" placeholder="001-001-0000123">
    </div>
  </div>
  <div class="row" style="align-items:center">
    <label><input id="soloPend" type="checkbox" checked> Sólo con pendiente de remisión</label>
    <div style="flex:1"></div>
    <button id="btnBuscar" class="btn">Buscar</button>
  </div>

  <div class="mini muted">Tip: podés combinar filtros; si ponés el nº exacto, ignora lo demás.</div>

  <table id="gridFac" style="margin-top:10px">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Nº</th>
        <th>Cliente</th>
        <th>RUC/CI</th>
        <th class="right">Facturado</th>
        <th class="right">Remitido</th>
        <th class="right">Pendiente</th>
        <th></th>
      </tr>
    </thead>
    <tbody><tr><td colspan="8" class="muted">Sin resultados</td></tr></tbody>
  </table>
</div>

<!-- Atajo: cargar por ID -->
<div class="card">
  <div class="row">
    <div>
      <label>ID Factura</label><br/>
      <input id="idFactura" type="number" placeholder="Ej: 55" value="<?= isset($_GET['id_factura'])?(int)$_GET['id_factura']:'' ?>">
    </div>
    <div style="align-self:end">
      <button id="btnCargar" class="btn">Cargar por ID</button>
    </div>
    <div class="muted mini" style="align-self:end">Atajo si ya sabés el ID exacto.</div>
  </div>
  <div id="facInfo" class="muted">—</div>
</div>

<!-- CLIENTE -->
<div class="card" id="boxCliente" style="display:none">
  <h3>Cliente</h3>
  <div class="row">
    <div style="flex:1">
      <label>Nombre</label><br/>
      <input id="cliNombre" type="text" readonly>
    </div>
    <div>
      <label>RUC/CI</label><br/>
      <input id="cliRuc" type="text" readonly>
    </div>
    <div style="flex:1">
      <label>Dirección</label><br/>
      <input id="cliDir" type="text" readonly>
    </div>
  </div>
</div>

<!-- CABECERA REMISIÓN -->
<div class="card" id="boxCab" style="display:none">
  <h3>Datos de Remisión</h3>
  <div class="row">
    <div>
      <label>Motivo</label><br/>
      <select id="motivo">
        <option>Venta</option>
        <option>Traslado</option>
        <option>Devolucion</option>
      </select>
    </div>
    <div>
      <label>Fecha salida</label><br/>
      <input id="fecha" type="date">
    </div>
    <!-- Eliminado: HORA de salida (el endpoint no lo necesita) -->
  </div>
  <div class="row">
    <div style="flex:1">
      <label>Origen</label><br/>
      <input id="origen" type="text" placeholder="Depósito central">
    </div>
    <div style="flex:1">
      <label>Destino</label><br/>
      <input id="destino" type="text" placeholder="Dirección del cliente">
    </div>
  </div>
  <div class="row">
    <div style="flex:1">
      <label>Transportista</label><br/>
      <input id="transportista" type="text" placeholder="Propio / Empresa x">
    </div>
    <div>
      <label>RUC Transp.</label><br/>
      <input id="transpRuc" type="text">
    </div>
    <div style="flex:1">
      <label>Chofer</label><br/>
      <input id="chofer" type="text">
    </div>
    <div>
      <label>CI Chofer</label><br/>
      <input id="choferCi" type="text">
    </div>
    <div>
      <label>Chapa</label><br/>
      <input id="chapa" type="text">
    </div>
    <div>
      <label>Marca</label><br/>
      <input id="marca" type="text">
    </div>
  </div>
  <div class="row">
    <div style="flex:1">
      <label>Observación</label><br/>
      <textarea id="obs" placeholder="(opcional)"></textarea>
    </div>
  </div>
</div>

<!-- ITEMS -->
<div class="card" id="boxItems" style="display:none">
  <h3>Ítems (desde la factura)</h3>
  <div class="mini muted">Editá “A remitir”. No puede superar el “Pendiente”.</div>
  <table id="grid">
    <thead>
      <tr>
        <th>Descripción</th>
        <th>Uni.</th>
        <th class="right">Facturado</th>
        <th class="right">Remitido</th>
        <th class="right">Pendiente</th>
        <th class="right">A remitir</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
</div>

<div class="row" id="boxEmitir" style="display:none">
  <button id="btnEmitir" class="btn primary">Emitir Nota de Remisión</button>
  <span id="status" class="muted" style="align-self:center"></span>
</div>

<script>
const $=id=>document.getElementById(id);
const money=n=>Number(n||0).toLocaleString('es-PY');
const PRINT_NR_URL = 'nota_remision_print.php';

function hoyISO(){ const d=new Date(); return d.toISOString().slice(0,10); }

let ctx = { factura:null, cliente:null, items:[] };

/* ====== BUSCAR FACTURAS ====== */
$('btnBuscar').onclick = async ()=>{
  const params = new URLSearchParams({
    cli: $('qCli').value.trim(),
    num: $('qNum').value.trim(),
    desde: $('fDesde').value || '',
    hasta: $('fHasta').value || '',
    pendiente: $('soloPend').checked ? 1 : 0,
    page: 1, page_size: 20
  });
  const tb = $('gridFac').querySelector('tbody');
  tb.innerHTML = '<tr><td colspan="8" class="muted">Buscando...</td></tr>';

  try{
    const r = await fetch('buscar_facturas_para_remision.php?'+params.toString());
    const j = await r.json();

    let rows = [];
    if (j && j.success) {
      if (Array.isArray(j.data)) rows = j.data;
      else if (Array.isArray(j.facturas)) rows = j.facturas;
    }

    tb.innerHTML='';
    if(!rows.length){
      tb.innerHTML = '<tr><td colspan="8" class="muted">Sin resultados</td></tr>';
      return;
    }

    // Si el backend no aplica "pendiente", filtramos acá
    if ($('soloPend').checked) {
      rows = rows.filter(x => Number(x.cant_pendiente||0) > 0.0001);
    }

    rows.forEach(f=>{
      const tr=document.createElement('tr');
      tr.innerHTML=`
        <td>${f.fecha_emision}</td>
        <td>${f.numero_documento}</td>
        <td>${f.cliente}</td>
        <td>${f.ruc_ci || '-'}</td>
        <td class="right">${money(f.cant_facturada)}</td>
        <td class="right">${money(f.cant_remitida)}</td>
        <td class="right"><span class="pill">${money(f.cant_pendiente)}</span></td>
        <td><button class="btn mini sel" data-id="${f.id_factura}">Seleccionar</button></td>
      `;
      tb.appendChild(tr);
    });

    tb.querySelectorAll('.sel').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        $('idFactura').value = btn.dataset.id;
        cargarFacturaPorId();
      });
    });
  }catch(e){
    tb.innerHTML = '<tr><td colspan="8" class="muted">Error al buscar</td></tr>';
    console.error(e);
  }
};

/* ====== CARGAR FACTURA (por ID o desde lista) ====== */
$('btnCargar').onclick = ()=> cargarFacturaPorId();

async function cargarFacturaPorId(){
  const id = parseInt($('idFactura').value||'0',10);
  if(!id){ alert('Ingresá un ID de factura'); return; }
  try{
    const r=await fetch('get_factura_para_remision.php?id_factura='+id);
    const j=await r.json();
    if(!j.success) throw new Error(j.error||'No se pudo cargar');

    const F = j.factura || {};
    const rawItems = j.items || [];
    const C = j.cliente || null;

    ctx.factura = F;
    ctx.cliente = {
      id_cliente:  (F.id_cliente ?? (C ? C.id_cliente : null)),
      nombre:      (F.cliente    ?? (C ? (C.nombre||'') : '')),
      ruc_ci:      (F.ruc_ci     ?? (C ? (C.ruc_ci||'') : '')),
      direccion:   (F.direccion  ?? (C ? (C.direccion||'') : ''))
    };

    $('facInfo').textContent = `Factura ${F.numero_documento} · Fecha ${F.fecha_emision} · Estado ${F.estado || ''}`;
    $('cliNombre').value = ctx.cliente.nombre || '';
    $('cliRuc').value    = ctx.cliente.ruc_ci || '';
    $('cliDir').value    = ctx.cliente.direccion || '';
    $('boxCliente').style.display='block';

    $('fecha').value = hoyISO();
    $('destino').value = ctx.cliente.direccion || '';
    $('boxCab').style.display='block';

    ctx.items = rawItems.map(it => ({
      id_producto: it.id_producto ?? null,
      descripcion: it.descripcion,
      unidad: it.unidad || 'UNI',
      facturado: Number(it.cant_facturada || it.facturado || 0),
      remitido:  Number(it.cant_remitida  || it.remitido  || 0),
      pendiente: Number(it.cant_pendiente || it.pendiente || 0)
    }));

    pintarItems(ctx.items);
    $('boxItems').style.display='block';
    $('boxEmitir').style.display='flex';

    // ===== Scroll automático a la cabecera (evita scrollear manualmente) =====
    $('boxCab').scrollIntoView({ behavior: 'smooth', block: 'start' });
    // Opcional: foco en el primer campo útil
    setTimeout(()=> $('origen').focus(), 250);

  }catch(e){
    alert(e.message);
    console.error(e);
  }
}

function pintarItems(items){
  const tb=$('grid').querySelector('tbody'); tb.innerHTML='';
  let pendientes = 0;
  items.forEach((it,idx)=>{
    pendientes += Number(it.pendiente||0);
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td>${it.descripcion}</td>
      <td>${it.unidad||'UNI'}</td>
      <td class="right">${money(it.facturado)}</td>
      <td class="right">${money(it.remitido)}</td>
      <td class="right">${money(it.pendiente)}</td>
      <td class="right"><input data-idx="${idx}" class="aRem" type="number" step="0.001" min="0" value="${Number(it.pendiente||0).toFixed(3)}" style="width:110px;text-align:right"></td>
    `;
    tb.appendChild(tr);
  });
  tb.querySelectorAll('.aRem').forEach(inp=>{
    inp.addEventListener('input', ()=>{
      const i = Number(inp.dataset.idx);
      const pend = Number(ctx.items[i].pendiente||0);
      let v = Number(inp.value||0);
      if (v>pend) v=pend;
      if (v<0) v=0;
      inp.value = v.toFixed(3);
    });
  });
  if (pendientes<=0) {
    alert('La factura no tiene pendientes para remitir.');
  }
}

/* ====== EMITIR ====== */
$('btnEmitir').onclick = async ()=>{
  if(!ctx.factura) { alert('Cargá una factura primero'); return; }

  const aRem = [];
  document.querySelectorAll('.aRem').forEach((inp)=>{
    const i = Number(inp.dataset.idx);
    const v = Number(inp.value||0);
    if (v>0){
      const it = ctx.items[i];
      aRem.push({
        id_producto: it.id_producto,
        descripcion: it.descripcion,
        unidad: it.unidad || 'UNI',
        cantidad: Number(v.toFixed(3))
      });
    }
  });
  if (!aRem.length){ alert('Indicá cantidades a remitir (>0)'); return; }

  const idCliente = ctx.factura.id_cliente ?? ctx.cliente?.id_cliente ?? null;
  if (!idCliente){ alert('No se pudo determinar el cliente de la factura'); return; }

  const payload = {
    motivo: $('motivo').value,
    fecha_salida: $('fecha').value || hoyISO(),
    id_cliente:   idCliente,
    id_factura:   ctx.factura.id_factura,
    origen_dir:   $('origen').value||'',
    destino_dir:  $('destino').value||'',
    transportista: $('transportista').value||'',
    transportista_ruc: $('transpRuc').value||'',
    chofer_nombre: $('chofer').value||'',
    chofer_ci:     $('choferCi').value||'',
    vehiculo_chapa: $('chapa').value||'',
    vehiculo_marca: $('marca').value||'',
    observacion: $('obs').value||'',
    items: aRem
  };

  $('btnEmitir').disabled=true; $('status').textContent='Emitiendo...';
  try{
    const r=await fetch('emitir_remision.php',{
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify(payload)
    });
    const j=await r.json();
    if(!j.success) throw new Error(j.error||'No se pudo emitir la remisión');
    alert('Nota de Remisión emitida: '+j.numero_documento);

    const url = `${PRINT_NR_URL}?id=${encodeURIComponent(j.id_remision)}&auto=1`;
    let w = window.open('', '_blank');
    if (w && !w.closed) { try { w.opener=null; w.location.replace(url); } catch(e){ location.href=url; } }
    else { location.href=url; }

    $('status').textContent='OK';
  }catch(e){
    alert(e.message); $('status').textContent='Error: '+e.message;
    console.error(e);
  }finally{
    $('btnEmitir').disabled=false;
  }
};

/* init */
function hoyMenosDias(d){ const x=new Date(); x.setDate(x.getDate()-d); return x.toISOString().slice(0,10); }
window.addEventListener('DOMContentLoaded', ()=>{
  $('fHasta').value = hoyISO();
  $('fDesde').value = hoyMenosDias(30);
  const pre = $('idFactura').value;
  if (pre) cargarFacturaPorId(); else $('btnBuscar').click();
});
</script>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
