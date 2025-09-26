<?php
// cobros.php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  // header('Location: login.php'); exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Cobros</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link rel="stylesheet" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/css/styles_venta.css">
<style>
  body{font-family:system-ui,Arial,sans-serif;margin:20px;color:#222}
  h1{margin:0 0 12px}
  .tabs{display:flex;gap:8px;margin-bottom:12px}
  .tab{padding:8px 12px;border:1px solid #ddd;border-radius:8px;cursor:pointer;background:#fff}
  .tab.active{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .card{border:1px solid #ddd;border-radius:8px;padding:12px;margin-bottom:12px}
  .row{display:flex;gap:12px;flex-wrap:wrap;margin:8px 0}
  label{font-weight:600}
  input,select,button{padding:8px}
  .btn{cursor:pointer;border:1px solid #ddd;background:#fff;border-radius:6px}
  .btn.primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{border:1px solid #eee;padding:6px;text-align:left;vertical-align:middle}
  .right{text-align:right}
  .muted{color:#666}
  .list{line-height:1.8}
  .warn{color:#b45309}
  .mini{font-size:12px}
  .pill{display:inline-block;background:#f1f5f9;border:1px solid #e2e8f0;border-radius:999px;padding:2px 8px;font-size:12px;margin-left:8px}
  .pill.red{background:#fee2e2;border-color:#fecaca;color:#b91c1c}
  .pill.green{background:#dcfce7;border-color:#bbf7d0;color:#14532d}

  /* ===== Modal QR compacto, responsive y con efecto ===== */
  #qrModalBk{
    position:fixed; inset:0;
    background:rgba(0,0,0,.45);
    display:none; align-items:center; justify-content:center; z-index:9999;
  }
  #qrModal{
    position:relative;
    background:#151b33; color:#e7e7ef;
    border:1px solid #30395c; border-radius:16px;
    width:min(560px, 92vw); max-height:92vh;
    padding:0; display:flex; flex-direction:column; overflow:hidden;
    box-shadow:0 10px 35px rgba(0,0,0,.45);
  }
  .qrHeader{ padding:14px 16px 8px; text-align:center; border-bottom:1px solid #263055 }
  .qrBody{ padding:14px 16px; overflow:auto; }
  .qrFooter{ padding:12px 16px; border-top:1px solid #263055; display:flex; gap:10px; justify-content:center; flex-wrap:wrap; }

  #qrInfo{ font-size:26px; font-weight:800; margin:6px 0 2px }

  /* === QR: caja y resets para centrar de verdad === */
  #qrBox{
    display:inline-flex; align-items:center; justify-content:center;
    background:#fff; border-radius:12px; padding:10px; margin:8px auto;
    line-height:0;
  }
  /* Contenedor cuadrado que centra al hijo (img/canvas/table) */
  #qrCanvas{
    display:grid; place-items:center;
    width:var(--qr-size, 300px);
    height:var(--qr-size, 300px);
    line-height:0;
  }
  /* Cualquier salida ocupa el tamaño exacto */
  #qrBox canvas, #qrBox img, #qrBox table{
    width:var(--qr-size, 300px);
    height:var(--qr-size, 300px);
    display:block; margin:0 auto;
  }
  /* Si sale como <table>, quitamos espaciados */
  #qrBox table{ border-collapse:collapse; border-spacing:0; }
  #qrBox table, #qrBox table td{ padding:0; margin:0; border:0; line-height:0; }

  .breathe{ animation:breathe 1.8s ease-in-out infinite; box-shadow:0 0 0 0 rgba(76,156,255,.25) }
  @keyframes breathe{
    0%{ box-shadow:0 0 0 0 rgba(76,156,255,.25) }
    70%{ box-shadow:0 0 0 14px rgba(76,156,255,0) }
    100%{ box-shadow:0 0 0 0 rgba(76,156,255,0) }
  }
  #qrStatus{ opacity:.9; margin-top:8px }
  #qrLinks{ word-break:break-all; font-size:12px; opacity:.9; margin-top:8px }
  .qrBtn{ padding:10px 14px; border-radius:10px; border:0; cursor:pointer }
  .qrBtn.close{ background:#e5e7eb; color:#111 }
  .qrBtn.retry{ background:#ffe08a; color:#382b00; display:none }
  .qrBtn.done{  background:#22c55e; color:#04170b; display:none }

  /* efectos resultado en el MODAL */
  #qrModal.ok { animation: glowOk 900ms ease-out 1 }
  #qrModal.bad{ animation: glowBad 600ms ease-out 1 }
  @keyframes glowOk{
    0%{ box-shadow:0 0 0 rgba(34,197,94,0) }
    30%{ box-shadow:0 0 24px rgba(34,197,94,.45) }
    100%{ box-shadow:0 0 0 rgba(34,197,94,0) }
  }
  @keyframes glowBad{
    0%{ transform:translateX(0) }
    20%{ transform:translateX(-6px) }
    40%{ transform:translateX(6px) }
    60%{ transform:translateX(-4px) }
    80%{ transform:translateX(4px) }
    100%{ transform:translateX(0) }
  }

  /* confetti dentro del modal */
  #qrConfetti{ position:absolute; inset:0; pointer-events:none }
</style>
</head>
<body>
  <div id="navbar-container"></div>
<h1>Cobros</h1>

<!-- Aviso del banco por defecto -->
<div class="card" id="boxBancoDefault" style="display:none">
  <span id="badgeBanco" class="pill"></span>
</div>

<div class="tabs">
  <div id="tabFac" class="tab active">Cobro de Facturas</div>
  <div id="tabCuo" class="tab">Cobro de Cuotas (Crédito)</div>
</div>

<!-- ============ PESTAÑA FACTURAS ============ -->
<div id="paneFac">
  <div class="card">
    <div class="row">
      <div>
        <label>N° Factura</label><br/>
        <input id="qFactura" type="text" placeholder="001-001-0000123"/>
      </div>
      <div>
        <label>Cliente (RUC/CI o Nombre)</label><br/>
        <input id="qClienteFac" type="text" placeholder="1234567-8 o Pérez"/>
      </div>
      <div style="align-self:end">
        <button id="btnBuscarFac" class="btn">Buscar</button>
      </div>
    </div>
    <div id="resFacturas" class="list muted">Sin resultados</div>
  </div>

  <div class="card" id="boxCobroFac" style="display:none">
    <div class="row">
      <div style="flex:1">
        <div><strong>Factura:</strong> <span id="f_num"></span></div>
        <div><strong>Cliente:</strong> <span id="f_cli"></span></div>
        <div><strong>Total:</strong> Gs. <span id="f_tot"></span></div>
        <div><strong>Pendiente:</strong> Gs. <span id="f_pen"></span>
          <span class="pill" id="f_pill"></span>
        </div>
      </div>
      <div>
        <label>Fecha cobro</label><br/>
        <input id="fechaCobroFac" type="date"/>
      </div>
    </div>

    <h3>Pagos </h3>
    <table id="tabPagosFac">
      <thead>
        <tr>
          <th>Medio</th>
          <th>Importe</th>
          <th>Referencia</th>
          <th>Cuenta</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="5">
            <button id="addPagoFac" class="btn">+ Agregar medio</button>
            <span class="muted" style="margin-left:8px">Suma pagos: Gs. <span id="sumaPagosFac">0</span></span>
            <span class="muted" style="margin-left:16px">Falta: Gs. <span id="faltaPagosFac">0</span></span>
          </td>
        </tr>
      </tfoot>
    </table>

    <div class="row">
      <button id="btnCobrarFac" class="btn primary">Registrar cobro</button>
      <span id="statusFac" class="muted"></span>
    </div>
  </div>
</div>

<!-- ============ PESTAÑA CUOTAS ============ -->
<div id="paneCuo" style="display:none">
  <div class="card">
    <div class="row">
      <div>
        <label>Cliente (RUC/CI o Nombre)</label><br/>
        <input id="qClienteCuo" type="text" placeholder="1234567-8 o Pérez"/>
      </div>
      <div style="align-self:end">
        <button id="btnBuscarCuo" class="btn">Buscar cuotas pendientes</button>
      </div>
    </div>
    <div id="resCuotas" class="list muted">Sin resultados</div>
  </div>

  <div class="card" id="boxCobroCuo" style="display:none">
    <div class="row">
      <div style="flex:1">
        <div><strong>Cliente:</strong> <span id="c_cli"></span></div>
        <div class="muted">Seleccioná las cuotas a cobrar (podés parcializar).</div>
      </div>
      <div>
        <label>Fecha cobro</label><br/>
        <input id="fechaCobroCuo" type="date"/>
      </div>
    </div>

    <table id="tabCuotas">
      <thead>
        <tr>
          <th></th>
          <th>Factura</th>
          <th>Cuota</th>
          <th>Vencimiento</th>
          <th class="right">Total</th>
          <th class="right">Saldo</th>
          <th class="right">A pagar</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="7" class="right">
            A pagar total: Gs. <span id="sumaCuo">0</span>
            <span class="pill red">Falta: Gs. <span id="faltaPagosCuo">0</span></span>
          </td>
        </tr>
      </tfoot>
    </table>

    <h3>Pagos (podés combinar medios)</h3>
    <table id="tabPagosCuo">
      <thead>
        <tr>
          <th>Medio</th>
          <th>Importe</th>
          <th>Referencia</th>
          <th>Cuenta</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="5">
            <button id="addPagoCuo" class="btn">+ Agregar medio</button>
            <span class="muted" style="margin-left:8px">Suma pagos: Gs. <span id="sumaPagosCuo">0</span></span>
            <span class="muted" style="margin-left:16px">Falta: Gs. <span id="faltaPagosCuo2">0</span></span>
          </td>
        </tr>
      </tfoot>
    </table>

    <div class="row">
      <button id="btnCobrarCuo" class="btn primary">Registrar cobro de cuotas</button>
      <span id="statusCuo" class="muted"></span>
    </div>
    <div class="muted warn">La suma de pagos debe ser igual a la suma “A pagar total”.</div>
  </div>
</div>

<!-- ===== Modal QR Transferencia ===== -->
<div id="qrModalBk">
  <div id="qrModal">
    <div class="qrHeader">
      <h3 style="margin:0">Transferencia por QR</h3>
      <div id="qrInfo">Monto: Gs. —</div>
    </div>

    <div class="qrBody">
      <div id="qrBox" class="breathe">
        <div id="qrCanvas"></div>
      </div>
      <div id="qrStatus">Esperando pago…</div>
      <div id="qrLinks"></div>
    </div>

    <div class="qrFooter">
      <button class="qrBtn close" id="qrClose">Cerrar</button>
      <button class="qrBtn retry" id="qrRetry">Reintentar</button>
      <button class="qrBtn done"  id="qrDone">Aplicar y cerrar</button>
    </div>

    <div id="qrConfetti"></div>
  </div>
</div>

<script>
// ========= PRINT RECIBO =========
const PRINT_RECIBO_URL = '../ventas/recibo_print.php';
function abrirImpresionRecibo(idRecibo, auto=true){
  const url = `${PRINT_RECIBO_URL}?id=${encodeURIComponent(idRecibo)}${auto ? '&auto=1' : ''}`;
  let w = window.open('', '_blank');
  if (w && !w.closed) { try { w.opener = null; w.location.replace(url); } catch(e){ location.href = url; } }
  else { location.href = url; }
}
// ================================

// ===== Helpers =====
const $ = id => document.getElementById(id);
function formatGs(n){ const v = Number(n||0); return v.toLocaleString('es-PY', {maximumFractionDigits: 0}); }
const money = n => formatGs(n);
function hoyISO(){ const d=new Date(); return d.toISOString().slice(0,10); }
function setMoney(id,num){ $(id).textContent = money(num); }
function getSumPagos(tableId){
  let s=0; document.querySelectorAll('#'+tableId+' .importe').forEach(i=>s+=Number(i.value||0));
  return s;
}

// ===== Banco por defecto =====
let BANCO_DEFAULT = null; // { id, label }
async function cargarBancoDefault(){
  try{
    const r = await fetch('../banco/get_banco_cobro_default.php');
    const j = await r.json();
    const box = $('boxBancoDefault');
    const badge = $('badgeBanco');

    if(!j.success){
      box.style.display='block'; badge.className='pill red';
      badge.textContent='Error obteniendo banco por defecto'; BANCO_DEFAULT=null; return null;
    }
    if(!j.id_cuenta_bancaria){
      box.style.display='block'; badge.className='pill red';
      badge.textContent='Sin banco por defecto configurado (Transferencias deshabilitadas)';
      BANCO_DEFAULT=null; return null;
    }
    const c=j.cuenta||{};
    const label=`${c.banco||'Banco'} · ${c.numero_cuenta||'s/n'} (${c.moneda||''} ${c.tipo||''})`.trim();
    BANCO_DEFAULT={id:j.id_cuenta_bancaria,label};
    box.style.display='block'; badge.className='pill green';
    badge.textContent=`Banco por defecto: ${label}`;
    return BANCO_DEFAULT;
  }catch(e){
    const box=$('boxBancoDefault'), badge=$('badgeBanco');
    box.style.display='block'; badge.className='pill red';
    badge.textContent='No se pudo cargar el banco por defecto';
    BANCO_DEFAULT=null; return null;
  }
}

// ===== addPagoRow con botón QR =====
function addPagoRow(tbodyId, sumaLblId, onChangeCb){
  const tb=document.querySelector('#'+tbodyId+' tbody');
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td>
      <select class="medio">
        <option>Efectivo</option>
        <option>Transferencia</option>
        <option>Tarjeta</option>
        <option>Cheque</option>
        <option>Billetera</option>
      </select>
    </td>
    <td><input type="number" class="importe" min="0" step="0.01" value="0"/></td>
    <td>
      <div style="display:flex;gap:6px;align-items:center">
        <input type="text" class="ref" placeholder="N° cupón / transf" style="flex:1"/>
        <button class="btn genQR" style="display:none">QR</button>
      </div>
    </td>
    <td class="cuentaWrap"><span class="mini muted">—</span></td>
    <td><button class="btn del">x</button></td>`;

  const medioSel = tr.querySelector('.medio');
  const cuentaCell = tr.querySelector('.cuentaWrap');
  const importeInp = tr.querySelector('.importe');
  const refInp     = tr.querySelector('.ref');
  const btnQR      = tr.querySelector('.genQR');

  const onAnyChange=()=>{
    setMoney(sumaLblId, getSumPagos(tbodyId));
    if (typeof onChangeCb==='function') onChangeCb();
  };

  function toggleQR(){
    if(medioSel.value==='Transferencia'){
      btnQR.style.display='inline-block';
      if(BANCO_DEFAULT && BANCO_DEFAULT.id){
        cuentaCell.innerHTML = `<span class="mini">${BANCO_DEFAULT.label}</span>`;
      }else{
        cuentaCell.innerHTML = `<span class="mini muted">Configurar banco por defecto</span>`;
      }
    }else{
      btnQR.style.display='none';
      cuentaCell.innerHTML = '<span class="mini muted">—</span>';
    }
  }

  medioSel.addEventListener('change', ()=>{ toggleQR(); onAnyChange(); });
  tr.querySelector('.importe').oninput = onAnyChange;
  tr.querySelector('.del').onclick = ()=>{ tr.remove(); onAnyChange(); };

  // === Generar QR ===
  btnQR.onclick = async ()=>{
    const amount = Number(importeInp.value||0);
    if(amount<=0){ alert('Ingresá un importe > 0 antes de generar el QR.'); return; }
    if(!(BANCO_DEFAULT && BANCO_DEFAULT.id)){
      alert('No hay banco por defecto configurado para Transferencia.'); return;
    }
    try{
      const res = await openTransferQRModal(amount);
      if(res && res.status==='confirmed'){
        refInp.value = 'QR '+res.id;
        refInp.readOnly = true;
        importeInp.readOnly = true;
        medioSel.disabled = true;
        btnQR.disabled = true;
        onAnyChange();
      }
    }catch(e){ alert('Error: '+e.message); }
  };

  tb.appendChild(tr);
  toggleQR();
  onAnyChange();
}

// ===== Tabs =====
$('tabFac').onclick=()=>{ $('tabFac').classList.add('active'); $('tabCuo').classList.remove('active'); $('paneFac').style.display='block'; $('paneCuo').style.display='none'; }
$('tabCuo').onclick=()=>{ $('tabCuo').classList.add('active'); $('tabFac').classList.remove('active'); $('paneCuo').style.display='block'; $('paneFac').style.display='none'; }

// ===== FACTURAS =====
let facturaSel=null;
function updateFaltaFac(){
  const objetivo = Number(facturaSel?.pendiente || 0);
  const suma = getSumPagos('tabPagosFac');
  const falta = Math.max(0, objetivo - suma);
  setMoney('faltaPagosFac', falta);
  $('f_pill').textContent = `Objetivo: Gs. ${money(objetivo)} · Pagos: Gs. ${money(suma)} · Falta: Gs. ${money(falta)}`;
}
$('btnBuscarFac').onclick=async()=>{
  const nf=$('qFactura').value.trim(), qc=$('qClienteFac').value.trim();
  const r=await fetch('buscar_facturas.php?num='+encodeURIComponent(nf)+'&cli='+encodeURIComponent(qc));
  const j=await r.json();
  const box=$('resFacturas'); box.innerHTML='';
  if(!j.success || !j.facturas?.length){ box.textContent='Sin resultados'; return; }
  j.facturas.forEach(f=>{
    const a=document.createElement('a'); a.href='#';
    a.textContent = `${f.numero_documento} | ${f.cliente} | Pend.: ${Number(f.pendiente).toLocaleString()}`;
    a.onclick=(e)=>{ e.preventDefault(); selFactura(f); };
    box.appendChild(a); box.appendChild(document.createElement('br'));
  });
};
function selFactura(f){
  facturaSel=f;
  $('f_num').textContent=f.numero_documento;
  $('f_cli').textContent=f.cliente;
  $('f_tot').textContent=money(f.total);
  $('f_pen').textContent=money(f.pendiente);
  $('fechaCobroFac').value=hoyISO();
  $('boxCobroFac').style.display='block';
  document.querySelector('#tabPagosFac tbody').innerHTML='';
  addPagoRow('tabPagosFac','sumaPagosFac',updateFaltaFac);
  updateFaltaFac();
}
$('addPagoFac').onclick=()=>addPagoRow('tabPagosFac','sumaPagosFac',updateFaltaFac);

$('btnCobrarFac').onclick=async()=>{
  if(!facturaSel){ alert('Seleccioná una factura'); return; }
  const fecha=$('fechaCobroFac').value; if(!fecha){ alert('Fecha requerida'); return; }
  const pagos=[];
  document.querySelectorAll('#tabPagosFac tbody tr').forEach(tr=>{
    const medio=tr.querySelector('.medio').value;
    const importe=Number(tr.querySelector('.importe').value||0);
    const referencia=tr.querySelector('.ref').value||null;
    let id_cuenta_bancaria=null;
    if(medio==='Transferencia'){
      id_cuenta_bancaria = BANCO_DEFAULT && BANCO_DEFAULT.id ? BANCO_DEFAULT.id : null;
    }
    if(importe>0) pagos.push({medio,importe,referencia,id_cuenta_bancaria});
  });
  for(const p of pagos){
    if(p.medio==='Transferencia' && !p.id_cuenta_bancaria){
      alert('No hay banco por defecto configurado. Configuralo antes de registrar transferencias.'); return;
    }
    }
  const totalPagos = pagos.reduce((a,b)=>a+b.importe,0);
  if(totalPagos - Number(facturaSel.pendiente) > 0.01){
    alert('La suma de pagos supera el pendiente'); return;
  }
  $('btnCobrarFac').disabled=true; $('statusFac').textContent='Registrando cobro...';
  try{
    const r=await fetch('cobrar_factura_multi.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({id_factura:facturaSel.id_factura, fecha, pagos})});
    const j=await r.json(); if(!j.success) throw new Error(j.error||'No se pudo cobrar');
    alert('Cobro registrado. Recibo #'+j.id_recibo);
    $('statusFac').textContent='OK';
    if (j.id_recibo) abrirImpresionRecibo(j.id_recibo, true);
  }catch(e){ alert(e.message); $('statusFac').textContent='Error: '+e.message; }
  finally{ $('btnCobrarFac').disabled=false; }
};

// ===== CUOTAS =====
let clienteCuotas=null, cuotas=[];
function getSumaCuotas(){
  let s=0; document.querySelectorAll('#tabCuotas .apagar').forEach(i=>s+=Number(i.value||0));
  return s;
}
function pintaSumaCuotasYFalta(){
  const objetivo = getSumaCuotas();
  setMoney('sumaCuo', objetivo);
  const sumaPagos = getSumPagos('tabPagosCuo');
  const falta = Math.max(0, objetivo - sumaPagos);
  setMoney('faltaPagosCuo', falta);
  setMoney('faltaPagosCuo2', falta);
}
$('btnBuscarCuo').onclick=async()=>{
  const q=$('qClienteCuo').value.trim();
  if(q.length<2){ alert('Ingresá al menos 2 caracteres'); return; }
  const r=await fetch('buscar_cuotas.php?q='+encodeURIComponent(q));
  const j=await r.json();
  const box=$('resCuotas'); box.innerHTML='';
  if(!j.success || !j.cuotas?.length){ box.textContent='Sin resultados'; $('boxCobroCuo').style.display='none'; return; }
  clienteCuotas=j.cliente; cuotas=j.cuotas;
  $('c_cli').textContent = `${clienteCuotas.nombre} (${clienteCuotas.ruc_ci||'s/d'})`;
  $('fechaCobroCuo').value=hoyISO();
  renderCuotas(); $('boxCobroCuo').style.display='block';
  document.querySelector('#tabPagosCuo tbody').innerHTML='';
  addPagoRow('tabPagosCuo','sumaPagosCuo',pintaSumaCuotasYFalta);
  pintaSumaCuotasYFalta();
};
function renderCuotas(){
  const tb=$('tabCuotas').querySelector('tbody'); tb.innerHTML='';
  let lastFactura=null;
  cuotas.forEach(c=>{
    if(c.numero_documento!==lastFactura){
      const hdr=document.createElement('tr');
      hdr.innerHTML=`<td colspan="7" style="background:#f7f7f8;font-weight:700;border-top:2px solid #e5e7eb">Factura: ${c.numero_documento}</td>`;
      tb.appendChild(hdr); lastFactura=c.numero_documento;
    }
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td><input type="checkbox" class="pick"/></td>
      <td>${c.numero_documento}</td>
      <td>${c.nro_cuota}/${c.cant_cuotas}</td>
      <td>${c.vencimiento}</td>
      <td class="right">${Number(c.total).toLocaleString()}</td>
      <td class="right">${Number(c.saldo).toLocaleString()}</td>
      <td class="right"><input type="number" class="apagar" min="0" step="0.01" value="0"/></td>`;
    const chk=tr.querySelector('.pick'); const inp=tr.querySelector('.apagar');
    chk.onchange=()=>{ if(chk.checked && Number(inp.value)<=0) inp.value=c.saldo; pintaSumaCuotasYFalta(); };
    inp.oninput=()=>{
      if(Number(inp.value)>c.saldo) inp.value=c.saldo;
      if(Number(inp.value)<0) inp.value=0;
      chk.checked = Number(inp.value)>0;
      pintaSumaCuotasYFalta();
    };
    tr.dataset.idx = String(cuotas.indexOf(c));
    tb.appendChild(tr);
  });
  pintaSumaCuotasYFalta();
}
$('addPagoCuo').onclick=()=>addPagoRow('tabPagosCuo','sumaPagosCuo',pintaSumaCuotasYFalta);

$('btnCobrarCuo').onclick=async()=>{
  if(!clienteCuotas){ alert('Buscá un cliente con cuotas pendientes'); return; }
  const fecha=$('fechaCobroCuo').value; if(!fecha){ alert('Fecha requerida'); return; }

  const sel=[];
  $('tabCuotas').querySelectorAll('tbody tr').forEach(tr=>{
    const pick=tr.querySelector('.pick'); const inp=tr.querySelector('.apagar');
    if(!pick || !inp) return;
    const imp=Number(inp.value||0);
    if(pick.checked && imp>0){
      const idx = Number(tr.dataset.idx || -1);
      if (idx>=0) { const c=cuotas[idx]; sel.push({id_cuota:c.id_cuota, pagar: imp}); }
    }
  });
  if(!sel.length){ alert('Seleccioná al menos una cuota con importe > 0'); return; }
  const sumaSel=sel.reduce((a,b)=>a+b.pagar,0);

  const pagos=[];
  document.querySelectorAll('#tabPagosCuo tbody tr').forEach(tr=>{
    const medio=tr.querySelector('.medio').value;
    const importe=Number(tr.querySelector('.importe').value||0);
    const referencia=tr.querySelector('.ref').value||null;
    let id_cuenta_bancaria=null;
    if(medio==='Transferencia'){ id_cuenta_bancaria = BANCO_DEFAULT && BANCO_DEFAULT.id ? BANCO_DEFAULT.id : null; }
    if(importe>0) pagos.push({medio,importe,referencia,id_cuenta_bancaria});
  });
  for(const p of pagos){
    if(p.medio==='Transferencia' && !p.id_cuenta_bancaria){
      alert('No hay banco por defecto configurado.'); return;
    }
  }
  const sumaPag = pagos.reduce((a,b)=>a+b.importe,0);
  if(Math.abs(sumaPag - sumaSel) > 0.01){
    alert('La suma de pagos debe igualar la suma “A pagar total”'); return;
  }

  $('btnCobrarCuo').disabled=true; $('statusCuo').textContent='Registrando cobro...';
  try{
    const r=await fetch('cobrar_cuotas_multi.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({id_cliente:clienteCuotas.id_cliente, fecha, cuotas:sel, pagos})});
    const j=await r.json(); if(!j.success) throw new Error(j.error||'No se pudo cobrar cuotas');
    alert('Cobro registrado. Recibo #'+j.id_recibo);
    $('statusCuo').textContent='OK';
    if (j.id_recibo) abrirImpresionRecibo(j.id_recibo, true);
  }catch(e){ alert(e.message); $('statusCuo').textContent='Error: '+e.message; }
  finally{ $('btnCobrarCuo').disabled=false; }
};

// ====== MÓDULO TRANSFERENCIA QR ======
// AJUSTÁ la ruta si tu pos_qr_demo.php está en otra carpeta:
const POS_QR_URL = '../../venta_v3/qr_demo/pos_qr_demo.php';

/* Fuerza usar imagen para el QR (consistente y sin <table>) */
const FORCE_IMG_QR = true;

let _qrPoll = null, _qrData = null;
async function openTransferQRModal(amount){
  // crear intento
  const fd = new FormData();
  fd.append('action','create');
  fd.append('amount', amount);
  const r = await fetch(POS_QR_URL, {method:'POST', body:fd});
  const j = await r.json();
  if(j.error) throw new Error(j.error);

  // refs
  const bk = document.getElementById('qrModalBk');
  const modal = document.getElementById('qrModal');
  const info = document.getElementById('qrInfo');
  const status = document.getElementById('qrStatus');
  const links = document.getElementById('qrLinks');
  const box = document.getElementById('qrCanvas');
  const boxWrap = document.getElementById('qrBox');
  const btnClose = document.getElementById('qrClose');
  const btnRetry = document.getElementById('qrRetry');
  const btnDone  = document.getElementById('qrDone');
  const confetti = document.getElementById('qrConfetti');

  // datos
  _qrData = { id: j.id, amountText: formatGs(j.amount_raw), review_url: j.review_url };

  // UI inicial
  modal.classList.remove('ok','bad');
  info.textContent   = 'Monto: Gs. ' + _qrData.amountText;
  status.textContent = 'Esperando pago…';
  links.innerHTML    = `<div class="qrMut">Si el QR no se ve, usá este link:</div><div><a target="_blank" href="${j.review_url}">${j.review_url}</a></div>`;
  btnRetry.style.display='none'; btnDone.style.display='none';
  boxWrap.classList.add('breathe');
  box.innerHTML = '';
  confetti.innerHTML = '';

  // tamaño QR: clamp por viewport y ancho modal
  const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
  const mh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
  const modalW = Math.min(560, vw * 0.92);
  const maxByHeight = Math.floor(Math.max(220, (mh - 200) * 0.75)); // deja espacio para header+footer
  const px = Math.max(220, Math.min(360, Math.min(maxByHeight, Math.floor(modalW * 0.8))));
  boxWrap.style.setProperty('--qr-size', px + 'px');

  // render QR (forzado a imagen o con fallback)
  try{
    if (!FORCE_IMG_QR && window.QRCode) {
      new QRCode(box, { text: j.review_url, width: px, height: px, correctLevel: QRCode.CorrectLevel.L });
    } else {
      const img = new Image(); img.width=px; img.height=px; img.alt='QR';
      img.src = 'https://api.qrserver.com/v1/create-qr-code/?size='+px+'x'+px+'&data='+encodeURIComponent(j.review_url);
      box.innerHTML = '';
      box.appendChild(img);
    }
  }catch(e){
    const img = new Image(); img.width=px; img.height=px; img.alt='QR';
    img.src = 'https://api.qrserver.com/v1/create-qr-code/?size='+px+'x'+px+'&data='+encodeURIComponent(j.review_url);
    box.innerHTML = '';
    box.appendChild(img);
  }

  // --- FIX: si alguna vez QRCodeJS devuelve <table>, normalizamos ---
  (function fixQRSize(){
    const el = box.querySelector('img,canvas,table') || box.firstElementChild;
    if(!el) return;
    const size = getComputedStyle(box).getPropertyValue('--qr-size') || (px+'px');
    el.style.width  = size;
    el.style.height = size;
    el.style.display = 'block';
    el.style.margin  = '0 auto';
    if(el.tagName === 'TABLE'){ el.style.borderCollapse = 'collapse'; }
  })();

  // abrir modal y bloquear scroll de fondo
  bk.style.display='flex';
  const prevOverflow = document.body.style.overflow;
  document.body.style.overflow = 'hidden';

  // polling
  if(_qrPoll) clearInterval(_qrPoll);
  _qrPoll = setInterval(async ()=>{
    const rs = await fetch(POS_QR_URL+'?action=status&i='+encodeURIComponent(j.id), {cache:'no-store'});
    const sj = await rs.json();
    if(sj.error) return;
    status.textContent = 'Estado: '+sj.status;

    if(sj.status==='confirmed'){
      clearInterval(_qrPoll);
      boxWrap.classList.remove('breathe');
      modal.classList.add('ok');          // efecto glow verde
      splashConfetti(confetti);           // confetti en el modal
      btnDone.style.display='inline-block';
      status.textContent = '✅ Pago confirmado. Podés aplicar el pago.';
    }
    if(sj.status==='rejected'){
      clearInterval(_qrPoll);
      boxWrap.classList.remove('breathe');
      modal.classList.add('bad');         // shake corto
      btnRetry.style.display='inline-block';
      status.textContent = '❌ Pago rechazado. Podés reintentar.';
    }
    if(sj.status==='expired'){
      clearInterval(_qrPoll);
      boxWrap.classList.remove('breathe');
      btnRetry.style.display='inline-block';
      status.textContent = '⌛ Intento expirado. Reintentá.';
    }
  }, 1200);

  // botones
  return new Promise((resolve)=>{
    btnClose.onclick = ()=>{
      if(_qrPoll) clearInterval(_qrPoll);
      boxWrap.classList.remove('breathe');
      bk.style.display='none';
      document.body.style.overflow = prevOverflow;
      resolve({status:'closed'});
    };
    btnRetry.onclick = async ()=>{
      if(_qrPoll) clearInterval(_qrPoll);
      boxWrap.classList.remove('breathe');
      bk.style.display='none';
      document.body.style.overflow = prevOverflow;
      openTransferQRModal(amount).then(resolve);
    };
    btnDone.onclick = ()=>{
      if(_qrPoll) clearInterval(_qrPoll);
      boxWrap.classList.remove('breathe');
      bk.style.display='none';
      document.body.style.overflow = prevOverflow;
      resolve({status:'confirmed', id:_qrData.id});
    };
  });
}

// confetti dentro del modal
function splashConfetti(container){
  container.innerHTML = '';
  const colors = ['#ff7676','#ffd166','#4ade80','#60a5fa','#c084fc','#f472b6'];
  const n = 60, W = container.clientWidth || 560;
  for(let i=0;i<n;i++){
    const s = document.createElement('i');
    s.style.position='absolute';
    s.style.width = '10px';
    s.style.height= '14px';
    s.style.left = (Math.random()*W) + 'px';
    s.style.top  = '0px';
    s.style.opacity = '.9';
    s.style.background = colors[(Math.random()*colors.length)|0];
    s.style.transform = 'translateY(-10vh) rotate('+((Math.random()*240)|0)+'deg)';
    s.style.animation = 'fallModal 1200ms linear forwards';
    s.style.animationDelay = (Math.random()*0.5)+'s';
    container.appendChild(s);
  }
  const styleId = 'fallModalKeyframes';
  if(!document.getElementById(styleId)){
    const st = document.createElement('style'); st.id = styleId;
    st.textContent = '@keyframes fallModal{ to{ transform:translateY(80vh) rotate(360deg); opacity:1; } }';
    document.head.appendChild(st);
  }
  setTimeout(()=>{ container.innerHTML=''; }, 2000);
}

window.addEventListener('DOMContentLoaded', async ()=>{
  $('fechaCobroFac').value=hoyISO();
  $('fechaCobroCuo').value=hoyISO();
  await cargarBancoDefault();
});
</script>

<!-- QR lib (no se usa si FORCE_IMG_QR=true, pero la dejamos por si cambiás) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
