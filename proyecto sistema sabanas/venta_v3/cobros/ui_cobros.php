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

    <h3>Pagos (podés combinar medios)</h3>
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

<script>
/* ========= PRINT RECIBO (anti pop-up block) ========= */
// CAMBIAR si tu recibo_print.php está en otra carpeta
const PRINT_RECIBO_URL = '../ventas/recibo_print.php';
function abrirImpresionRecibo(idRecibo, auto=true){
  const url = `${PRINT_RECIBO_URL}?id=${encodeURIComponent(idRecibo)}${auto ? '&auto=1' : ''}`;
  let w = window.open('', '_blank');
  if (w && !w.closed) {
    try { w.opener = null; w.location.replace(url); }
    catch(e){ location.href = url; }
  } else {
    location.href = url;
  }
}
/* ===================================================== */

/* ===== Helpers básicos ===== */
const $ = id => document.getElementById(id);
const money = n => Number(n||0).toLocaleString();
function hoyISO(){ const d=new Date(); return d.toISOString().slice(0,10); }
function setMoney(id,num){ $(id).textContent = money(num); }
function getSumPagos(tableId){
  let s=0; document.querySelectorAll('#'+tableId+' .importe').forEach(i=>s+=Number(i.value||0));
  return s;
}

/* ===== Banco por defecto (nuevo) ===== */
let BANCO_DEFAULT = null; // { id, label }
async function cargarBancoDefault(){
  try{
    const r = await fetch('../banco/get_banco_cobro_default.php');
    const j = await r.json();
    const box = $('boxBancoDefault');
    const badge = $('badgeBanco');

    if(!j.success){
      box.style.display='block';
      badge.className = 'pill red';
      badge.textContent = 'Error obteniendo banco por defecto';
      BANCO_DEFAULT = null;
      return null;
    }

    if(!j.id_cuenta_bancaria){
      box.style.display='block';
      badge.className = 'pill red';
      badge.textContent = 'Sin banco por defecto configurado (Transferencias deshabilitadas)';
      BANCO_DEFAULT = null;
      return null;
    }

    const c = j.cuenta || {};
    const label = `${c.banco || 'Banco'} · ${c.numero_cuenta || 's/n'} (${c.moneda || ''} ${c.tipo || ''})`.trim();
    BANCO_DEFAULT = { id: j.id_cuenta_bancaria, label };
    box.style.display='block';
    badge.className = 'pill green';
    badge.textContent = `Banco por defecto: ${label}`;
    return BANCO_DEFAULT;
  }catch(e){
    const box = $('boxBancoDefault');
    const badge = $('badgeBanco');
    box.style.display='block';
    badge.className = 'pill red';
    badge.textContent = 'No se pudo cargar el banco por defecto';
    BANCO_DEFAULT = null;
    return null;
  }
}

/* ===== Fila de pago reutilizable (usa banco por defecto en Transferencia) ===== */
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
    <td><input type="text" class="ref" placeholder="N° cupón / transf"/></td>
    <td class="cuentaWrap"><span class="mini muted">—</span></td>
    <td><button class="btn del">x</button></td>`;

  const onAnyChange=()=>{
    setMoney(sumaLblId, getSumPagos(tbodyId));
    if (typeof onChangeCb==='function') onChangeCb();
  };

  // eventos
  tr.querySelector('.importe').oninput = onAnyChange;
  tr.querySelector('.del').onclick = ()=>{ tr.remove(); onAnyChange(); };

  const medioSel = tr.querySelector('.medio');
  const cuentaCell = tr.querySelector('.cuentaWrap');

  const pintarCuentaDefault = () => {
    if(medioSel.value==='Transferencia'){
      if(BANCO_DEFAULT && BANCO_DEFAULT.id){
        cuentaCell.innerHTML = `<span class="mini">${BANCO_DEFAULT.label}</span>`;
      }else{
        cuentaCell.innerHTML = `<span class="mini muted">Configurar banco por defecto</span>`;
      }
    }else{
      cuentaCell.innerHTML = '<span class="mini muted">—</span>';
    }
  };

  medioSel.addEventListener('change', ()=>{ pintarCuentaDefault(); onAnyChange(); });

  // inicial
  tb.appendChild(tr);
  pintarCuentaDefault();
  onAnyChange();
}

/* ===== Tabs ===== */
$('tabFac').onclick=()=>{ $('tabFac').classList.add('active'); $('tabCuo').classList.remove('active'); $('paneFac').style.display='block'; $('paneCuo').style.display='none'; }
$('tabCuo').onclick=()=>{ $('tabCuo').classList.add('active'); $('tabFac').classList.remove('active'); $('paneCuo').style.display='block'; $('paneFac').style.display='none'; }

/* ===== FACTURAS ===== */
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
  // Validación de banco por defecto cuando hay transferencias
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
    // Abrir impresión del recibo
    if (j.id_recibo) abrirImpresionRecibo(j.id_recibo, true);
  }catch(e){ alert(e.message); $('statusFac').textContent='Error: '+e.message; }
  finally{ $('btnCobrarFac').disabled=false; }
};

/* ===== CUOTAS ===== */
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
    // Guardar índice de la cuota en dataset para mapear luego
    tr.dataset.idx = String(cuotas.indexOf(c));
    tb.appendChild(tr);
  });
  pintaSumaCuotasYFalta();
}
$('addPagoCuo').onclick=()=>addPagoRow('tabPagosCuo','sumaPagosCuo',pintaSumaCuotasYFalta);

$('btnCobrarCuo').onclick=async()=>{
  if(!clienteCuotas){ alert('Buscá un cliente con cuotas pendientes'); return; }
  const fecha=$('fechaCobroCuo').value; if(!fecha){ alert('Fecha requerida'); return; }

  // cuotas seleccionadas (saltando headers) — FIX: usar dataset.idx para evitar desalineo
  const sel=[];
  const rows=$('tabCuotas').querySelectorAll('tbody tr');
  rows.forEach(tr=>{
    const pick=tr.querySelector('.pick');
    const inp=tr.querySelector('.apagar');
    if(!pick || !inp) return; // header
    const imp=Number(inp.value||0);
    if(pick.checked && imp>0){
      const idx = Number(tr.dataset.idx || -1);
      if (idx>=0) {
        const c=cuotas[idx];
        sel.push({id_cuota:c.id_cuota, pagar: imp});
      }
    }
  });
  if(!sel.length){ alert('Seleccioná al menos una cuota con importe > 0'); return; }
  const sumaSel=sel.reduce((a,b)=>a+b.pagar,0);

  // medios de pago
  const pagos=[];
  document.querySelectorAll('#tabPagosCuo tbody tr').forEach(tr=>{
    const medio=tr.querySelector('.medio').value;
    const importe=Number(tr.querySelector('.importe').value||0);
    const referencia=tr.querySelector('.ref').value||null;
    let id_cuenta_bancaria=null;
    if(medio==='Transferencia'){
      id_cuenta_bancaria = BANCO_DEFAULT && BANCO_DEFAULT.id ? BANCO_DEFAULT.id : null;
    }
    if(importe>0) pagos.push({medio,importe,referencia,id_cuenta_bancaria});
  });
  // Validación de banco por defecto cuando hay transferencias
  for(const p of pagos){
    if(p.medio==='Transferencia' && !p.id_cuenta_bancaria){
      alert('No hay banco por defecto configurado. Configuralo antes de registrar transferencias.'); return;
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
    // Abrir impresión del recibo
    if (j.id_recibo) abrirImpresionRecibo(j.id_recibo, true);
  }catch(e){ alert(e.message); $('statusCuo').textContent='Error: '+e.message; }
  finally{ $('btnCobrarCuo').disabled=false; }
};

/* init */
window.addEventListener('DOMContentLoaded', async ()=>{
  $('fechaCobroFac').value=hoyISO();
  $('fechaCobroCuo').value=hoyISO();
  await cargarBancoDefault(); // <- carga y pinta el banco por defecto
});
</script>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
