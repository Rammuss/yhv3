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
</style>
</head>
<body>
  <div id="navbar-container"></div>
<h1>Cobros</h1>

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
        <div><strong>Pendiente:</strong> Gs. <span id="f_pen"></span></div>
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
          <th>Cuenta (si es transferencia)</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="5">
            <button id="addPagoFac" class="btn">+ Agregar medio</button>
            <span class="muted" style="margin-left:8px">Suma pagos: Gs. <span id="sumaPagosFac">0</span></span>
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
          <th>Cuenta (si es transferencia)</th>
          <th></th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr>
          <td colspan="5">
            <button id="addPagoCuo" class="btn">+ Agregar medio</button>
            <span class="muted" style="margin-left:8px">Suma pagos: Gs. <span id="sumaPagosCuo">0</span></span>
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
const $=id=>document.getElementById(id);
function hoyISO(){ const d=new Date(); return d.toISOString().slice(0,10); }

/* ===== Cuentas bancarias ===== */
let cuentasCache=null;
async function cargarCuentasBancarias(){
  if(cuentasCache) return cuentasCache;
  try{
    const r=await fetch('../banco/cuentas_bancarias_list.php');
    const j=await r.json();
    if(!j.success) throw new Error(j.error||'Error cargando cuentas');
    cuentasCache=j.cuentas||[];
  }catch(e){
    alert('No se pudieron cargar las cuentas bancarias: '+e.message);
    cuentasCache=[];
  }
  return cuentasCache;
}
async function buildCuentaSelect(){
  const cuentas=await cargarCuentasBancarias();
  const sel=document.createElement('select');
  sel.className='cuenta';
  sel.innerHTML='<option value="">-- Seleccioná cuenta --</option>';
  cuentas.forEach(c=>{
    const opt=document.createElement('option');
    opt.value=c.id_cuenta_bancaria;
    opt.textContent=c.label;
    sel.appendChild(opt);
  });
  return sel;
}

/* ===== Helpers pagos (fila editable) ===== */
function addPagoRow(tbodyId,sumaLblId){
  const tb=document.querySelector('#'+tbodyId+' tbody');
  const tr=document.createElement('tr');
  tr.innerHTML=`
    <td><select class="medio">
      <option>Efectivo</option>
      <option>Transferencia</option>
      <option>Tarjeta</option>
      <option>Cheque</option>
      <option>Billetera</option>
    </select></td>
    <td><input type="number" class="importe" min="0" step="0.01" value="0"/></td>
    <td><input type="text" class="ref" placeholder="N° cupón / transf"/></td>
    <td class="cuentaWrap"><span class="mini muted">—</span></td>
    <td><button class="btn del">x</button></td>`;
  tr.querySelector('.importe').oninput=()=>sumaPagos(tbodyId,sumaLblId);
  tr.querySelector('.del').onclick=()=>{ tr.remove(); sumaPagos(tbodyId,sumaLblId); };
  const medioSel=tr.querySelector('.medio');
  const cuentaCell=tr.querySelector('.cuentaWrap');
  medioSel.addEventListener('change',async()=>{
    if(medioSel.value==='Transferencia'){
      cuentaCell.innerHTML='Cargando...';
      const sel=await buildCuentaSelect();
      cuentaCell.innerHTML=''; cuentaCell.appendChild(sel);
    }else{
      cuentaCell.innerHTML='<span class="mini muted">—</span>';
    }
  });
  tb.appendChild(tr);
  sumaPagos(tbodyId,sumaLblId);
}
function sumaPagos(tbodyId,sumaLblId){
  let s=0; document.querySelectorAll('#'+tbodyId+' .importe').forEach(i=>s+=Number(i.value||0));
  $(sumaLblId).textContent=s.toLocaleString();
  return s;
}

/* ===== Tabs ===== */
$('tabFac').onclick=()=>{ $('tabFac').classList.add('active'); $('tabCuo').classList.remove('active'); $('paneFac').style.display='block'; $('paneCuo').style.display='none'; }
$('tabCuo').onclick=()=>{ $('tabCuo').classList.add('active'); $('tabFac').classList.remove('active'); $('paneCuo').style.display='block'; $('paneFac').style.display='none'; }

/* ===== FACTURAS ===== */
let facturaSel=null;
$('btnBuscarFac').onclick=async()=>{
  const nf=$('qFactura').value.trim(),qc=$('qClienteFac').value.trim();
  const r=await fetch('buscar_facturas.php?num='+encodeURIComponent(nf)+'&cli='+encodeURIComponent(qc));
  const j=await r.json();
  const box=$('resFacturas'); box.innerHTML='';
  if(!j.success||!j.facturas?.length){ box.textContent='Sin resultados'; return; }
  j.facturas.forEach(f=>{
    const a=document.createElement('a'); a.href='#';
    a.textContent=`${f.numero_documento} | ${f.cliente} | Pend.: ${Number(f.pendiente).toLocaleString()}`;
    a.onclick=(e)=>{e.preventDefault(); selFactura(f);};
    box.appendChild(a); box.appendChild(document.createElement('br'));
  });
};
function selFactura(f){
  facturaSel=f;
  $('f_num').textContent=f.numero_documento;
  $('f_cli').textContent=f.cliente;
  $('f_tot').textContent=Number(f.total).toLocaleString();
  $('f_pen').textContent=Number(f.pendiente).toLocaleString();
  $('fechaCobroFac').value=hoyISO();
  $('boxCobroFac').style.display='block';
  document.querySelector('#tabPagosFac tbody').innerHTML='';
  addPagoRow('tabPagosFac','sumaPagosFac');
}
$('addPagoFac').onclick=()=>addPagoRow('tabPagosFac','sumaPagosFac');
$('btnCobrarFac').onclick=async()=>{
  if(!facturaSel){alert('Seleccioná una factura');return;}
  const fecha=$('fechaCobroFac').value; if(!fecha){alert('Fecha requerida');return;}
  const pagos=[];
  document.querySelectorAll('#tabPagosFac tbody tr').forEach(tr=>{
    const medio=tr.querySelector('.medio').value;
    const importe=Number(tr.querySelector('.importe').value||0);
    const referencia=tr.querySelector('.ref').value||null;
    let id_cuenta_bancaria=null;
    if(medio==='Transferencia'){
      const sel=tr.querySelector('.cuentaWrap .cuenta');
      id_cuenta_bancaria=sel&&sel.value?parseInt(sel.value,10):null;
    }
    if(importe>0) pagos.push({medio,importe,referencia,id_cuenta_bancaria});
  });
  for(const p of pagos){
    if(p.medio==='Transferencia'&&!p.id_cuenta_bancaria){
      alert('Seleccioná la cuenta bancaria para la transferencia.'); return;
    }
  }
  const totalPagos=pagos.reduce((a,b)=>a+b.importe,0);
  if(totalPagos-Number(facturaSel.pendiente)>0.01){
    alert('La suma de pagos supera el pendiente'); return;
  }
  $('btnCobrarFac').disabled=true; $('statusFac').textContent='Registrando cobro...';
  try{
    const r=await fetch('cobrar_factura_multi.php',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({id_factura:facturaSel.id_factura,fecha,pagos})});
    const j=await r.json(); if(!j.success) throw new Error(j.error||'No se pudo cobrar');
    alert('Cobro registrado. Recibo #'+j.id_recibo);
    $('statusFac').textContent='OK';
  }catch(e){alert(e.message);$('statusFac').textContent='Error: '+e.message;}
  finally{$('btnCobrarFac').disabled=false;}
};

/* ===== CUOTAS ===== */
let clienteCuotas=null, cuotas=[];
$('btnBuscarCuo').onclick=async()=>{
  const q=$('qClienteCuo').value.trim();
  if(q.length<2){alert('Ingresá al menos 2 caracteres');return;}
  const r=await fetch('buscar_cuotas.php?q='+encodeURIComponent(q));
  const j=await r.json();
  const box=$('resCuotas'); box.innerHTML='';
  if(!j.success||!j.cuotas?.length){ box.textContent='Sin resultados'; $('boxCobroCuo').style.display='none'; return;}
  clienteCuotas=j.cliente; cuotas=j.cuotas;
  $('c_cli').textContent=`${clienteCuotas.nombre} (${clienteCuotas.ruc_ci||'s/d'})`;
  $('fechaCobroCuo').value=hoyISO();
  renderCuotas(); $('boxCobroCuo').style.display='block';
  document.querySelector('#tabPagosCuo tbody').innerHTML='';
  addPagoRow('tabPagosCuo','sumaPagosCuo');
};
function renderCuotas(){
  const tb=$('tabCuotas').querySelector('tbody'); tb.innerHTML='';
  let lastFactura=null;
  cuotas.forEach(c=>{
    if(c.numero_documento!==lastFactura){
      const hdr=document.createElement('tr');
      hdr.innerHTML=`<td colspan="7" style="background:#f7f7f8;font-weight:700;border-top:2px solid #e5e7eb">
        Factura: ${c.numero_documento}</td>`;
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
    chk.onchange=()=>{ if(chk.checked && Number(inp.value)<=0) inp.value=c.saldo; sumaCuotas(); };
    inp.oninput=()=>{ if(Number(inp.value)>c.saldo) inp.value=c.saldo; if(Number(inp.value)<0) inp.value=0;
      chk.checked = Number(inp.value)>0; sumaCuotas(); };
    tb.appendChild(tr);
  });
  sumaCuotas();
}
function sumaCuotas(){
  let s=0; document.querySelectorAll('#tabCuotas .apagar').forEach(i=>s+=Number(i.value||0));
  $('sumaCuo').textContent=s.toLocaleString(); return s;
}
$('addPagoCuo').onclick=()=>addPagoRow('tabPagosCuo','sumaPagosCuo');
$('btnCobrarCuo').onclick=async()=>{
  if(!clienteCuotas){ alert('Buscá un cliente con cuotas pendientes'); return; }
  const fecha=$('fechaCobroCuo').value; if(!fecha){ alert('Fecha requerida'); return; }

  // cuotas seleccionadas
  const sel=[]; const rows=$('tabCuotas').querySelectorAll('tbody tr');
  // saltar filas de encabezado (las que tienen colspan) y mapear por índice de cuotas[]
  let idxCuota=0;
  rows.forEach(tr=>{
    const pick=tr.querySelector('.pick');
    const inp=tr.querySelector('.apagar');
    if(!pick || !inp) return; // es un header
    const checked=pick.checked; const imp=Number(inp.value||0);
    if(checked && imp>0){
      const c=cuotas[idxCuota];
      sel.push({id_cuota:c.id_cuota, pagar: imp});
    }
    idxCuota++;
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
      const sel=tr.querySelector('.cuentaWrap .cuenta');
      id_cuenta_bancaria=sel&&sel.value?parseInt(sel.value,10):null;
    }
    if(importe>0) pagos.push({medio,importe,referencia,id_cuenta_bancaria});
  });
  for(const p of pagos){
    if(p.medio==='Transferencia'&&!p.id_cuenta_bancaria){
      alert('Seleccioná la cuenta bancaria para la transferencia.'); return;
    }
  }
  const sumaPag=pagos.reduce((a,b)=>a+b.importe,0);
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
  }catch(e){ alert(e.message); $('statusCuo').textContent='Error: '+e.message; }
  finally{ $('btnCobrarCuo').disabled=false; }
};

/* init */
window.addEventListener('DOMContentLoaded', async ()=>{
  $('fechaCobroFac').value=hoyISO();
  $('fechaCobroCuo').value=hoyISO();
  // precargar cuentas para que el primer cambio a "Transferencia" sea rápido
  await cargarCuentasBancarias();
});
</script>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/navbar/navbar.js"></script>
</body>
</html>
