<?php
// facturacion.php
session_start();


// session_start();
// header('Content-Type: text/plain; charset=utf-8');
// print_r($_SESSION);
// if (empty($_SESSION['nombre_usuario'])) { /* header('Location: login.php'); exit; */ }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Facturación</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  :root { --gap:12px; --pad:8px; }
  body{font-family:system-ui,Arial,sans-serif;margin:20px;color:#222}
  h1{margin:0 0 10px 0}
  .row{display:flex;gap:var(--gap);flex-wrap:wrap;margin:8px 0}
  label{font-weight:600}
  input,select,button{padding:var(--pad)}
  input[readonly]{background:#f7f7f7}
  .card{border:1px solid #ddd;border-radius:8px;padding:12px}
  table{width:100%;border-collapse:collapse;margin-top:10px}
  th,td{border:1px solid #e6e6e6;padding:6px;text-align:left}
  tfoot td{font-weight:700}
  .right{text-align:right}
  .muted{color:#666}
  .btn{cursor:pointer;border:1px solid #ddd;background:#fff;border-radius:6px}
  .btn.primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .btn:disabled{opacity:.6;cursor:not-allowed}
  .btn.small{padding:6px 10px;font-size:12px}
  .list{border:1px solid #ddd;border-radius:6px;max-height:200px;overflow:auto;margin-top:6px}
  .list-item{padding:8px;border-bottom:1px solid #eee;cursor:pointer}
  .list-item:hover{background:#f5f8ff}
  .pill{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:2px 8px;font-size:12px}
  .badge{display:inline-block;font-size:12px;border-radius:999px;padding:2px 8px;margin-left:6px}
  .badge.ok{background:#e8f5e9;border:1px solid #c8e6c9;color:#256029}
  .badge.warn{background:#fff3e0;border:1px solid #ffe0b2;color:#9a5a00}
  .badge.danger{background:#ffebee;border:1px solid #ffcdd2;color:#b71c1c}
  .hint{font-size:12px;color:#666}
  .mini{font-size:12px}
  .total-box{font-weight:700;padding:6px 10px;border:1px dashed #ddd;border-radius:6px}
  .sum-ok{color:#256029}
  .sum-bad{color:#b71c1c}
</style>
</head>
<body>
  <div id="navbar-container"></div>
<h1>Facturación</h1>

<!-- CLIENTE -->
<div class="card">
  <div class="row">
    <div style="flex:1 1 360px">
      <label>Buscar cliente (Nombre, Apellido o RUC/CI)</label><br/>
      <input id="qCliente" type="text" placeholder="Ej: rodriguez o 1234567-8" style="width:100%"/>
      <div id="resClientes" class="list" style="display:none"></div>
    </div>
    <div>
      <label>Cliente</label><br/>
      <input id="cliente" type="text" readonly placeholder="(sin seleccionar)"/>
    </div>
    <div>
      <label>RUC/CI</label><br/>
      <input id="ruc" type="text" readonly/>
    </div>
  </div>

  <div class="row">
    <div>
      <label>Pedidos del cliente</label><br/>
      <select id="ddlPedidos" disabled>
        <option value="">— Seleccioná un cliente primero —</option>
      </select>
    </div>
    <div style="align-self:end">
      <button id="btnEstirar" class="btn">Estirar pedido</button>
    </div>
  </div>
</div>

<!-- TIMBRADO -->
<div class="card">
  <div class="row" style="align-items:flex-end">
    <div style="flex:1">
      <label>Timbrado vigente</label><br/>
      <input id="timbradoInfo" type="text" readonly style="width:100%" placeholder="(Cargando timbrado...)"/>
      <input id="timbradoHiddenId" type="hidden" />
    </div>
    <div>
      <button id="btnRefrescarTimbrado" class="btn small">Actualizar</button><br/>
      <span id="timbradoBadge" class="badge ok" style="display:none">OK</span>
    </div>
  </div>
  <div class="row hint" id="timbradoAyuda">
    Mostramos el próximo número a emitir. El correlativo definitivo se asigna al confirmar la factura.
  </div>
</div>

<!-- FACTURA -->
<div class="card">
  <div class="row">
    <div>
      <label>Fecha de emisión</label><br/>
      <input id="fecha" type="date"/>
      <div id="fechaWarn" class="hint"></div>
    </div>

    <div>
      <label>Condición</label><br/>
      <select id="condicion">
        <option>Contado</option>
        <option>Credito</option>
      </select>
    </div>

    <div style="flex:1">
      <label>Observación</label><br/>
      <input id="obs" type="text" style="width:100%" placeholder="(opcional)"/>
    </div>
  </div>

  <!-- Parámetros de CRÉDITO -->
  <div id="boxCredito" class="row" style="display:none;border-top:1px dashed #e5e7eb;padding-top:8px">
    <div>
      <label>Nº de cuotas</label><br/>
      <input id="cuotas" type="number" min="1" max="60" step="1" value="3" style="width:120px"/>
    </div>
    <div>
      <label>Frecuencia</label><br/>
      <select id="frecuencia">
        <option value="mensual">Mensual</option>
        <option value="quincenal">Quincenal</option>
        <option value="semanal">Semanal</option>
      </select>
    </div>
    <div>
      <label>Primer vencimiento</label><br/>
      <input id="primerVto" type="date"/>
      <div class="mini muted">Si NO calculás plan, se toma como único vencimiento.</div>
    </div>
    <div>
      <label>Anticipo (seña)</label><br/>
      <input id="anticipo" type="number" min="0" step="0.01" value="0.00" style="width:140px"/>
    </div>
    <div>
      <label>Tasa interés mensual (%)</label><br/>
      <input id="tasa" type="number" min="0" step="0.01" value="0.00" style="width:140px"/>
    </div>
    <div style="align-self:end">
      <button id="btnCalcularPlan" class="btn small">Calcular plan</button>
      <button id="btnLimpiarPlan" class="btn small" type="button">Usar 1 vencimiento</button>
    </div>
  </div>

  <!-- GRID ITEMS -->
  <table id="grid">
    <thead>
      <tr>
        <th>Descripción</th>
        <th>Tipo</th>
        <th class="right">Cantidad</th>
        <th class="right">Precio</th>
        <th>IVA</th>
        <th class="right">Subtotal</th>
      </tr>
    </thead>
    <tbody></tbody>
    <tfoot>
      <tr><td colspan="5" class="right">Grav. 10%</td><td class="right" id="grav10">0</td></tr>
      <tr><td colspan="5" class="right">IVA 10%</td><td class="right" id="iva10">0</td></tr>
      <tr><td colspan="5" class="right">Grav. 5%</td><td class="right" id="grav5">0</td></tr>
      <tr><td colspan="5" class="right">IVA 5%</td><td class="right" id="iva5">0</td></tr>
      <tr><td colspan="5" class="right">Exentas</td><td class="right" id="exentas">0</td></tr>
      <tr><td colspan="5" class="right">TOTAL</td><td class="right" id="total">0</td></tr>
    </tfoot>
  </table>

  <!-- PREVIEW PLAN DE CUOTAS -->
  <div id="planWrap" style="display:none">
    <h3 style="margin-top:14px;margin-bottom:6px">Plan de cuotas</h3>
    <div class="mini muted" id="planMeta"></div>
    <table id="plan">
      <thead>
        <tr>
          <th>#</th>
          <th>Vencimiento</th>
          <th class="right">Capital</th>
          <th class="right">Interés</th>
          <th class="right">Total cuota</th>
          <th class="right">Saldo</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr><td colspan="2" class="right">Totales</td>
            <td class="right" id="sumCap">0</td>
            <td class="right" id="sumInt">0</td>
            <td class="right" id="sumTot">0</td>
            <td></td></tr>
      </tfoot>
    </table>
  </div>

  <!-- CONTADO: COBRAR AHORA -->
  <div id="contadoWrap" style="display:none; border-top:1px dashed #e5e7eb; margin-top:8px; padding-top:8px">
    <div class="row" style="align-items:center">
      <label><input type="checkbox" id="cobrarAhora" /> Cobrar ahora (Contado)</label>
      <span class="muted mini">Si lo activás, se emitirá la factura y se cobrará en la misma operación.</span>
    </div>

    <div id="pagosWrap" style="display:none">
      <div class="row" style="justify-content:space-between; align-items:center">
        <strong>Medios de pago</strong>
        <div>
          <button type="button" class="btn small" id="btnAddPago">Agregar medio</button>
          <button type="button" class="btn small" id="btnCompletarTotal">Completar total</button>
        </div>
      </div>
      <table id="tablaPagos">
        <thead>
          <tr>
            <th style="width:160px">Medio</th>
            <th>Referencia</th>
            <th style="width:140px" class="right">Importe</th>
            <th style="width:60px"></th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <td colspan="2" class="right">Suma pagos</td>
            <td class="right"><span id="sumaPagos" class="total-box">0</span></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
      <div class="hint">La suma de pagos debe ser igual al TOTAL para cobrar ahora.</div>
    </div>
  </div>

  <div class="row">
    <button id="btnEmitir" class="btn primary">Emitir Factura</button>
    <span id="status" class="muted" style="align-self:center"></span>
  </div>
</div>

<script>
const $ = (id)=>document.getElementById(id);

/* ========= PRINT SEGURO ========= */
const FACTURA_PRINT_URL = 'factura_print.php';  // ajustá rutas si es necesario
const RECIBO_PRINT_URL  = 'recibo_print.php';

function abrirImpresion(url, auto=true){
  const final = `${url}${auto ? (url.includes('?') ? '&auto=1' : '?auto=1') : ''}`;
  let w = window.open('', '_blank');
  if (w && !w.closed) {
    try { w.opener = null; w.location.replace(final); }
    catch(e){ location.href = final; }
  } else {
    location.href = final;
  }
}
function abrirImpresionFactura(id, auto=true){
  abrirImpresion(`${FACTURA_PRINT_URL}?id=${encodeURIComponent(id)}`, auto);
}
function abrirImpresionRecibo(id, auto=true){
  abrirImpresion(`${RECIBO_PRINT_URL}?id=${encodeURIComponent(id)}`, auto);
}
/* ================================= */

// ===== Estado =====
let clienteSel = null;
let pedidoSel  = null;
let totales = {grav10:0,iva10:0,grav5:0,iva5:0,exentas:0,total:0};
let timbrado = null;
let planCuotas = []; // para enviar al backend en crédito

// ===== Helpers =====
function daysDiff(a, b){ const MS=24*60*60*1000; return Math.floor((b.getTime()-a.getTime())/MS); }
function pad7(n){ return String(n).padStart(7,'0'); }
function hoyISO(){ const d=new Date(); const m=String(d.getMonth()+1).padStart(2,'0'); const day=String(d.getDate()).padStart(2,'0'); return `${d.getFullYear()}-${m}-${day}`; }
function parseISO(s){ const [Y,M,D]=s.split('-').map(Number); return new Date(Y, M-1, D); }
function addDays(date, days){ const d=new Date(date); d.setDate(d.getDate()+days); return d; }
function addMonths(date, months){ const d=new Date(date); d.setMonth(d.getMonth()+months); return d; }
function fmtISO(d){ const m=String(d.getMonth()+1).padStart(2,'0'); const day=String(d.getDate()).padStart(2,'0'); return `${d.getFullYear()}-${m}-${day}`; }
function toNum(v){ return Number(v||0); }
function money(v,dec=2){ return Number(v||0).toFixed(dec); }

// ===== Timbrado =====
async function cargarTimbrado(){
  const info=$('timbradoInfo'), badge=$('timbradoBadge');
  info.value='(Cargando timbrado...)'; badge.style.display='none';
  try{
    const r = await fetch('../timbrado/get_timbrado_vigente.php');
    const j = await r.json();
    if(!j.success){
      info.value='No hay timbrado vigente para Factura';
      badge.textContent='Sin timbrado'; badge.className='badge danger'; badge.style.display='inline-block';
      timbrado=null; return;
    }
    timbrado=j.timbrado;
    const numFmt = timbrado.establecimiento+'-'+timbrado.punto_expedicion+'-'+pad7(timbrado.numero_proximo);
    info.value = `Timbrado ${timbrado.numero_timbrado} | Próximo: ${numFmt} | Vigente hasta ${timbrado.fecha_fin}`;
    const hoy=new Date(); const fin=parseISO(timbrado.fecha_fin);
    const dias=daysDiff(hoy, fin);
    let state='ok', text='OK';
    if (dias<=0){ state='danger'; text='Vencido'; }
    else if (dias<=30){ state='warn'; text=`Vence en ${dias} días`; }
    badge.textContent=text; badge.className='badge '+state; badge.style.display='inline-block';
    validarFechaContraTimbrado();
  }catch(e){
    info.value='Error al cargar timbrado'; timbrado=null;
    const b=$('timbradoBadge'); b.textContent='Error'; b.className='badge danger'; b.style.display='inline-block';
  }
}
$('btnRefrescarTimbrado').addEventListener('click', cargarTimbrado);

// ===== Condición =====
$('condicion').addEventListener('change', onCondicionChange);
function onCondicionChange(){
  const c = $('condicion').value;
  const isCredito = (c==='Credito');
  $('boxCredito').style.display = isCredito ? 'flex' : 'none';
  $('planWrap').style.display = (isCredito && planCuotas.length) ? 'block' : 'none';
  $('contadoWrap').style.display = (!isCredito) ? 'block' : 'none';
  $('pagosWrap').style.display   = (!isCredito && $('cobrarAhora').checked) ? 'block' : 'none';
}
$('cobrarAhora').addEventListener('change', ()=>{
  const show = $('cobrarAhora').checked && $('condicion').value==='Contado';
  $('pagosWrap').style.display = show ? 'block' : 'none';
});

// ===== Fecha =====
function setFechaHoyPorDefecto(){ $('fecha').value = hoyISO(); }
function validarFechaContraTimbrado(){
  const f=$('fecha').value; const warn=$('fechaWarn'); warn.textContent='';
  if (!timbrado || !f) return;
  const d=parseISO(f), ini=parseISO(timbrado.fecha_inicio), fin=parseISO(timbrado.fecha_fin);
  if (d<ini || d>fin){
    warn.textContent=`⚠ La fecha está fuera de la vigencia del timbrado (${timbrado.fecha_inicio} a ${timbrado.fecha_fin}).`;
  }
}
$('fecha').addEventListener('change', validarFechaContraTimbrado);

// ===== Clientes / pedidos =====
let findTimer=null;
$('qCliente').addEventListener('input', ()=>{
  clearTimeout(findTimer);
  const q=$('qCliente').value.trim();
  if(q.length<2){ $('resClientes').style.display='none'; return; }
  findTimer=setTimeout(()=>buscarClientes(q),300);
});
async function buscarClientes(q){
  try{
    const r=await fetch('../cliente/clientes_buscar.php?q='+encodeURIComponent(q)+'&page=1&page_size=10');
    const j=await r.json(); if(!j.ok) throw new Error(j.error||'Error');
    const box=$('resClientes'); box.innerHTML='';
    (j.data||[]).forEach(c=>{
      const div=document.createElement('div'); div.className='list-item';
      div.innerHTML=`<div><strong>${c.nombre_completo}</strong> <span class="pill">${c.ruc_ci||'s/d'}</span></div>
                     <div class="muted">${c.telefono||''} · ${c.direccion||''}</div>`;
      div.onclick=()=>{ clienteSel=c; $('cliente').value=c.nombre_completo; $('ruc').value=c.ruc_ci||''; box.style.display='none'; cargarPedidosCliente(c.id_cliente); };
      box.appendChild(div);
    });
    box.style.display = (j.data||[]).length ? 'block' : 'none';
  }catch(e){ console.error(e); }
}
async function cargarPedidosCliente(idCliente){
  const ddl=$('ddlPedidos'); ddl.innerHTML='<option value="">Cargando...</option>'; ddl.disabled=true;
  try{
    const r=await fetch('../cliente/pedidos_por_cliente.php?id_cliente='+idCliente);
    const j=await r.json(); if(!j.ok) throw new Error(j.error||'Error');
    ddl.innerHTML='<option value="">— Seleccioná un pedido —</option>';
    (j.data||[]).forEach(p=>{
      const opt=document.createElement('option'); opt.value=p.id_pedido; opt.textContent=p.resumen || ('#'+p.id_pedido+' | '+p.fecha_pedido);
      ddl.appendChild(opt);
    });
    ddl.disabled=false;
  }catch(e){ alert(e.message); }
}
$('ddlPedidos').addEventListener('change', ()=>{ pedidoSel=parseInt($('ddlPedidos').value||'0',10) || null; });

// ===== Estirar pedido =====
$('btnEstirar').addEventListener('click', async ()=>{
  if(!pedidoSel){ alert('Elegí un pedido del cliente'); return; }
  try{
    const r=await fetch('get_pedido_para_facturar.php?id_pedido='+pedidoSel);
    const j=await r.json(); if(!j.success) throw new Error(j.error||'No se pudo estirar el pedido');
    if(!clienteSel){
      clienteSel={ id_cliente:j.pedido.id_cliente, nombre_completo:(j.pedido.nombre||'')+' '+(j.pedido.apellido||''), ruc_ci:j.pedido.ruc_ci };
      $('cliente').value=clienteSel.nombre_completo; $('ruc').value=clienteSel.ruc_ci||''; }
    pintarGrilla(j.items||[]); $('status').textContent='Pedido '+j.pedido.id_pedido+' listo para facturar';
    // Resetear plan si cambia total
    planCuotas=[]; $('planWrap').style.display='none';
    // Preparar pagos (reset)
    resetPagosTabla();
  }catch(e){ alert(e.message); }
});

// ===== Grilla =====
function pintarGrilla(items){
  const tb=$('grid').querySelector('tbody'); tb.innerHTML='';
  let g10=0,i10=0,g5=0,i5=0,ex=0,total=0;
  items.forEach(it=>{
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td>${it.descripcion}</td>
      <td>${it.tipo_item==='S'?'Servicio':'Producto'}</td>
      <td class="right">${Number(it.cantidad).toFixed(3)}</td>
      <td class="right">${Number(it.precio_unitario).toFixed(2)}</td>
      <td>${it.tipo_iva}</td>
      <td class="right">${Number(it.subtotal_neto).toFixed(2)}</td>`;
    tb.appendChild(tr);
    if(it.tipo_iva==='10%'){ g10+=+it.subtotal_neto; i10+=+it.iva_monto; }
    else if(it.tipo_iva==='5%'){ g5+=+it.subtotal_neto; i5+=+it.iva_monto; }
    else { ex+=+it.subtotal_neto; }
    total += (+it.subtotal_neto) + (+it.iva_monto);
  });
  $('grav10').textContent=g10.toFixed(2);
  $('iva10').textContent=i10.toFixed(2);
  $('grav5').textContent=g5.toFixed(2);
  $('iva5').textContent=i5.toFixed(2);
  $('exentas').textContent=ex.toFixed(2);
  $('total').textContent=total.toFixed(2);
  totales={grav10:g10,iva10:i10,grav5:g5,iva5:i5,exentas:ex,total};
  onCondicionChange();
}

// ===== Crédito: cálculo plan =====
$('btnCalcularPlan').addEventListener('click', ()=>{
  const n = Math.max(1, parseInt(($('cuotas').value||'1'),10));
  const tasaMensual = Math.max(0, toNum($('tasa').value))/100.0;
  const anticipo = Math.max(0, toNum($('anticipo').value));
  const total = totales.total || 0;
  let principal = Math.max(0, total - anticipo);

  if (principal <= 0){ alert('El anticipo no puede ser mayor o igual al total.'); return; }

  const fechaEmi = $('fecha').value || hoyISO();
  const defPrimerVto = fmtISO(addMonths(parseISO(fechaEmi),1));
  const primerVto = $('primerVto').value || defPrimerVto;
  if (!$('primerVto').value) $('primerVto').value = defPrimerVto;

  const frec = $('frecuencia').value;

  const i = tasaMensual;
  const cuota = i>0 ? (principal * (i / (1 - Math.pow(1+i, -n)))) : (principal / n);

  planCuotas=[]; let saldo=principal;
  for (let k=1;k<=n;k++){
    const interes = i>0 ? (saldo * i) : 0;
    const capital = Math.max(0, cuota - interes);
    saldo = Math.max(0, saldo - capital);
    let vtoDate = parseISO(primerVto);
    if (frec==='mensual') vtoDate = addMonths(vtoDate, k-1);
    else if (frec==='quincenal') vtoDate = addDays(vtoDate, 15*(k-1));
    else if (frec==='semanal') vtoDate = addDays(vtoDate, 7*(k-1));

    planCuotas.push({
      nro: k,
      vencimiento: fmtISO(vtoDate),
      capital: +capital.toFixed(2),
      interes: +interes.toFixed(2),
      total: +(capital+interes).toFixed(2)
    });
  }
  renderPlan(planCuotas, principal, anticipo, i, n, frec, primerVto);
});
$('btnLimpiarPlan').addEventListener('click', ()=>{
  planCuotas=[]; $('plan').querySelector('tbody').innerHTML='';
  $('sumCap').textContent='0'; $('sumInt').textContent='0'; $('sumTot').textContent='0';
  $('planMeta').textContent=''; $('planWrap').style.display='none';
});

function renderPlan(plan, principal, anticipo, i, n, frec, primerVto){
  const tb=$('plan').querySelector('tbody'); tb.innerHTML='';
  let sumCap=0,sumInt=0,sumTot=0,saldo=principal;
  plan.forEach(c=>{
    sumCap+=c.capital; sumInt+=c.interes; sumTot+=c.total; saldo=Math.max(0, saldo - c.capital);
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td>${c.nro}</td>
      <td>${c.vencimiento}</td>
      <td class="right">${c.capital.toFixed(2)}</td>
      <td class="right">${c.interes.toFixed(2)}</td>
      <td class="right">${c.total.toFixed(2)}</td>
      <td class="right">${saldo.toFixed(2)}</td>`;
    tb.appendChild(tr);
  });
  $('sumCap').textContent=sumCap.toFixed(2);
  $('sumInt').textContent=sumInt.toFixed(2);
  $('sumTot').textContent=sumTot.toFixed(2);
  $('planMeta').textContent = `Principal: ${principal.toFixed(2)} | Anticipo: ${anticipo.toFixed(2)} | Tasa mensual: ${(i*100).toFixed(2)}% | Cuotas: ${n} | Frecuencia: ${frec} | Primer vto: ${primerVto}`;
  $('planWrap').style.display='block';
}

// ===== Contado: medios de pago =====
const medios = ['Efectivo','Tarjeta','Transferencia','Cheque'];
$('btnAddPago').addEventListener('click', ()=> addPagoRow());
$('btnCompletarTotal').addEventListener('click', completarTotal);

function resetPagosTabla(){
  const tb = $('tablaPagos').querySelector('tbody');
  tb.innerHTML='';
  addPagoRow('Efectivo', totales.total);
  actualizarSumaPagos();
}

function addPagoRow(medioInit='', importeInit=0, refInit=''){
  const tb = $('tablaPagos').querySelector('tbody');
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td>
      <select class="medio" style="width:100%">${medios.map(m=>`<option ${m===medioInit?'selected':''}>${m}</option>`).join('')}</select>
    </td>
    <td><input class="ref" type="text" placeholder="Ref. / N° voucher / nota" style="width:100%"/></td>
    <td class="right"><input class="imp" type="number" step="0.01" min="0" value="${Number(importeInit||0).toFixed(2)}" style="width:120px; text-align:right"/></td>
    <td class="right"><button type="button" class="btn small btnDel">✕</button></td>
  `;
  tb.appendChild(tr);
  tr.querySelector('.ref').value = refInit;
  tr.querySelector('.imp').addEventListener('input', actualizarSumaPagos);
  tr.querySelector('.btnDel').addEventListener('click', ()=>{ tr.remove(); actualizarSumaPagos(); });
  actualizarSumaPagos();
}

function getPagos(){
  const rows = Array.from($('tablaPagos').querySelectorAll('tbody tr'));
  return rows.map(tr=>{
    const medio = tr.querySelector('.medio').value;
    const importe = Number(tr.querySelector('.imp').value || 0);
    const referencia = tr.querySelector('.ref').value.trim();
    return { medio, importe, referencia };
  }).filter(p=>p.importe>0);
}

function actualizarSumaPagos(){
  const suma = getPagos().reduce((acc,p)=>acc+p.importe, 0);
  const el = $('sumaPagos');
  el.textContent = money(suma);
  const total = Number(totales.total||0);
  const ok = Math.abs(suma-total) <= 0.01;
  el.classList.toggle('sum-ok', ok);
  el.classList.toggle('sum-bad', !ok);
}

function completarTotal(){
  const total = Number(totales.total||0);
  const pagos = getPagos();
  if (!pagos.length){ addPagoRow('Efectivo', total); return; }
  const ya = pagos.slice(1).reduce((acc,p)=>acc+p.importe, 0);
  const restante = Math.max(0, total - ya);
  const firstImp = $('tablaPagos').querySelector('tbody tr .imp');
  firstImp.value = money(restante);
  actualizarSumaPagos();
}

// ===== Emitir =====
$('btnEmitir').addEventListener('click', async ()=>{
  if(!pedidoSel){ alert('Seleccioná un pedido y estiralo a la grilla'); return; }
  if(!timbrado){ alert('No hay timbrado vigente. No se puede emitir.'); return; }
  const fecha = $('fecha').value; if(!fecha){ alert('Ingresá la fecha de emisión'); return; }

  // Validación vigencia timbrado
  if (timbrado){
    const d=parseISO(fecha), ini=parseISO(timbrado.fecha_inicio), fin=parseISO(timbrado.fecha_fin);
    if (d<ini || d>fin){ if(!confirm('La fecha está fuera de la vigencia del timbrado. ¿Continuar igualmente?')) return; }
  }

  const condicion=$('condicion').value;
  const cobrarAhora = $('cobrarAhora').checked && condicion==='Contado';

  $('btnEmitir').disabled=true; $('status').textContent= cobrarAhora ? 'Emitiendo y cobrando...' : 'Emitiendo factura...';

  try{
    if (condicion==='Credito' || !cobrarAhora){
      // Emitir solo factura
      const payload={
        id_pedido: pedidoSel,
        condicion_venta: condicion,
        fecha_emision: fecha,
        observacion: $('obs').value||''
      };
      if (condicion==='Credito'){
        if (planCuotas.length){
          payload.plan_cuotas = planCuotas;
        } else {
          const vto = $('primerVto').value;
          if(!vto){ throw new Error('Ingresá el primer vencimiento o calculá el plan.'); }
          payload.fecha_vencimiento = vto;
        }
      }
      const r=await fetch('facturar_pedido.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
      const j=await r.json();
      if(!j.success) throw new Error(j.error||'No se pudo emitir');
      alert('Factura emitida: '+j.numero_documento+' | Total: '+Number(j.total).toFixed(2));
      $('status').textContent='Factura emitida: '+j.numero_documento;
      cargarTimbrado();
      // Imprimir FACTURA
      abrirImpresionFactura(j.id_factura, true);
    } else {
      // Contado + cobrar ahora
      const pagos = getPagos();
      if (!pagos.length) throw new Error('Agregá al menos un medio de pago.');
      const suma = pagos.reduce((acc,p)=>acc+p.importe,0);
      const total = Number(totales.total||0);
      if (Math.abs(suma-total) > 0.01) throw new Error('La suma de pagos debe ser igual al TOTAL.');

      const payload = {
        id_pedido: pedidoSel,
        fecha_emision: fecha,
        observacion: $('obs').value||'',
        pagos
      };
      const r=await fetch('facturar_y_cobrar_contado.php',{
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(payload)
      });
      const j=await r.json();
      if(!j.success) throw new Error(j.error||'No se pudo emitir y cobrar');

      alert('Factura emitida y cobrada: '+j.numero_documento);
      $('status').textContent='Factura cobrada: '+j.numero_documento;

      // 1) Imprimir FACTURA
      abrirImpresionFactura(j.id_factura, true);
      // 2) Imprimir RECIBO (si backend lo devolvió)
      if (j.id_recibo) {
        setTimeout(()=>abrirImpresionRecibo(j.id_recibo, true), 600);
      }

      cargarTimbrado();
    }
  }catch(e){
    alert(e.message); $('status').textContent='Error: '+e.message;
  }finally{
    $('btnEmitir').disabled=false;
  }
});

// ===== Init =====
window.addEventListener('DOMContentLoaded', ()=>{
  setFechaHoyPorDefecto();
  const f=$('fecha').value || hoyISO();
  $('primerVto').value = fmtISO(addMonths(parseISO(f),1));
  cargarTimbrado();
  onCondicionChange();
  resetPagosTabla();
});
</script>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
