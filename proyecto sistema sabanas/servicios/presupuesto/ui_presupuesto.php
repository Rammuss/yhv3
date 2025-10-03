<?php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Presupuestos de Servicios</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css" />
<style>
  :root{
    --bg:#f1f5f9; --surface:#fff; --border:#dbeafe; --shadow:0 12px 28px rgba(15,23,42,.12);
    --text:#0f172a; --muted:#64748b; --accent:#2563eb; --accent-dark:#1d4ed8;
  }
  *{box-sizing:border-box;}
  body{margin:0;padding:24px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto;background:var(--bg);color:var(--text);}
  h1{margin:0 0 16px;font-size:24px;}
  .layout{display:grid;grid-template-columns:2fr 1.2fr;gap:18px;}
  .column{display:flex;flex-direction:column;gap:16px;}
  .card{background:var(--surface);border-radius:14px;padding:18px;box-shadow:var(--shadow);border:1px solid var(--border);}
  .section-title{margin:0 0 12px;font-size:16px;font-weight:600;display:flex;align-items:center;justify-content:space-between;}
  label{display:block;margin:10px 0 4px;text-transform:uppercase;font-size:11px;color:var(--muted);letter-spacing:.04em;}
  input,select,button{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:14px;}
  button{cursor:pointer;font-weight:600;background:var(--accent);color:#fff;border:none;}
  button:hover{background:var(--accent-dark);}
  button.sec{background:#e2e8f0;color:var(--text);}
  button.small{padding:6px 10px;font-size:12px;}
  .filters{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));margin-bottom:12px;}
  .chips{display:flex;gap:8px;flex-wrap:wrap;}
  .chip{padding:6px 10px;border-radius:999px;border:1px solid var(--border);background:#fff;color:var(--muted);cursor:pointer;font-size:12px;}
  .chip.active{background:var(--accent);color:#fff;border-color:var(--accent);}
  .list{display:flex;flex-direction:column;gap:12px;max-height:70vh;overflow:auto;padding-right:4px;}
  .card-item{border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:8px;background:#fff;box-shadow:0 8px 18px rgba(15,23,42,.08);}
  .card-item h3{margin:0;font-size:15px;}
  .muted{color:var(--muted);font-size:12px;}
  .tag{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;}
  .tag.estado{background:#e2e8f0;color:#0f172a;}
  .tag.badge{background:#dcfce7;color:#166534;}
  .services{display:flex;flex-wrap:wrap;gap:6px;font-size:12px;}
  .service-pill{padding:4px 8px;border-radius:999px;background:#eff6ff;color:#1d4ed8;}
  .actions-row{display:flex;gap:8px;flex-wrap:wrap;}
  .empty{padding:20px;text-align:center;color:var(--muted);border:1px dashed var(--border);border-radius:12px;}
  .toast{position:fixed;top:18px;right:18px;padding:12px 16px;background:var(--accent);color:#fff;border-radius:10px;box-shadow:var(--shadow);display:none;min-width:240px;font-weight:600;}
  .toast.error{background:#dc2626;}
  .modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:flex-start;justify-content:center;padding:40px 20px;z-index:1000;}
  .modal .inner{background:#fff;border-radius:16px;max-width:820px;width:100%;padding:22px;box-shadow:0 20px 46px rgba(15,23,42,.2);max-height:90vh;overflow:auto;}
  .modal h2{margin:0 0 12px;}
  .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px;}
  table{width:100%;border-collapse:collapse;}
  th,td{padding:10px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:13px;}
  th{background:#f8fafc;color:#1e3a8a;}
  .badge-estado{display:inline-block;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;}
  .Borrador{background:#fef9c3;color:#854d0e;}
  .Enviado{background:#dbeafe;color:#1d4ed8;}
  .Aprobado{background:#dcfce7;color:#166534;}
  .Rechazado{background:#fee2e2;color:#b91c1c;}
  .Vencido{background:#e2e8f0;color:#475569;}
</style>
</head>
<body>
    
<div id="navbar-container"></div>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/navbar/navbar.js"></script>
<h1>Presupuestos de Servicios</h1>

<div class="layout">
  <div class="column">
    <div class="card">
      <div class="section-title">
        <span>Buscar reservas</span>
        <button class="sec small" onclick="buscarReservas()">Actualizar</button>
      </div>
      <div class="filters">
        <div>
          <label>Cliente</label>
          <input id="f_cliente" placeholder="Buscar cliente (nombre / CI)">
          <input type="hidden" id="f_id_cliente">
        </div>
        <div>
          <label>Desde</label>
          <input type="date" id="f_desde">
        </div>
        <div>
          <label>Hasta</label>
          <input type="date" id="f_hasta">
        </div>
      </div>
      <div class="chips">
        <div class="chip" data-range="hoy" onclick="setRangeChip(this)">Hoy</div>
        <div class="chip" data-range="ayer" onclick="setRangeChip(this)">Ayer</div>
        <div class="chip" data-range="ultimos7" onclick="setRangeChip(this)">Últimos 7 días</div>
        <div class="chip" data-range="mes" onclick="setRangeChip(this)">Este mes</div>
        <div class="chip" data-range="limpiar" onclick="setRangeChip(this)">Limpiar</div>
      </div>
      <div id="reservas_list" class="list" style="margin-top:14px;"></div>
    </div>
  </div>

  <div class="column">
    <div class="card">
      <div class="section-title">
        <span>Presupuestos recientes</span>
        <button class="sec small" onclick="loadPresupuestos()">Actualizar</button>
      </div>
      <div class="filters">
        <div>
          <label>Estado</label>
          <select id="p_estado" onchange="loadPresupuestos()">
            <option value="Todos">Todos</option>
            <option>Borrador</option>
            <option>Enviado</option>
            <option>Aprobado</option>
            <option>Rechazado</option>
            <option>Vencido</option>
          </select>
        </div>
        <div>
          <label>Cliente</label>
          <input id="p_cliente" placeholder="Filtrar por cliente">
          <input type="hidden" id="p_id_cliente">
        </div>
        <div>
          <label>Desde</label>
          <input type="date" id="p_desde">
        </div>
        <div>
          <label>Hasta</label>
          <input type="date" id="p_hasta">
        </div>
      </div>
      <div id="presupuestos_list" class="list" style="margin-top:14px;"></div>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<div id="modal_detalle" class="modal">
  <div class="inner">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <h2 id="modal_title">Presupuesto</h2>
      <button class="sec small" onclick="cerrarModal()">Cerrar</button>
    </div>
    <div class="detail-grid">
      <div>
        <span class="muted">Cliente</span>
        <div id="modal_cliente" style="font-weight:600;"></div>
      </div>
      <div>
        <span class="muted">Fecha</span>
        <div id="modal_fecha"></div>
      </div>
      <div>
        <span class="muted">Estado</span>
        <div id="modal_estado"></div>
      </div>
      <div>
        <span class="muted">Validez</span>
        <div id="modal_validez"></div>
      </div>
    </div>
    <table id="modal_items">
      <thead>
        <tr>
          <th>#</th>
          <th>Descripción</th>
          <th>Tipo</th>
          <th>Cant.</th>
          <th>Precio</th>
          <th>Desc.</th>
          <th>IVA</th>
          <th>Subtotal</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <div style="display:flex;justify-content:flex-end;margin-top:12px;gap:18px;color:#1e293b;font-size:13px;">
      <div>
        <div><strong>Gravadas 10%:</strong> <span id="modal_grav10"></span></div>
        <div><strong>IVA 10%:</strong> <span id="modal_iva10"></span></div>
      </div>
      <div>
        <div><strong>Gravadas 5%:</strong> <span id="modal_grav5"></span></div>
        <div><strong>IVA 5%:</strong> <span id="modal_iva5"></span></div>
      </div>
      <div>
        <div><strong>Exentas:</strong> <span id="modal_exentas"></span></div>
        <div><strong>Total:</strong> <span id="modal_total"></span></div>
      </div>
    </div>
    <div class="actions-row" style="margin-top:18px;">
      <button onclick="imprimirPresupuesto()">Imprimir</button>
    </div>
  </div>
</div>

<script>
const API = '../presupuesto/api_presupuesto.php';
const CLIENTE_API = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/cliente/clientes_buscar.php';
const $ = id => document.getElementById(id);
let reservas = [];
let presupuestos = [];
let selectedPresupuesto = null;
let clienteTimer = null;
let clientePTimer = null;

function showToast(msg,type='ok'){
  const toast = $('toast');
  toast.textContent = msg;
  toast.className = 'toast' + (type==='error' ? ' error':'');
  toast.style.display = 'block';
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(()=> toast.style.display='none', 2600);
}

async function api(op, payload={}){
  const res = await fetch(API,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({op, ...payload})
  });
  const data = await res.json();
  if(!data.success) throw new Error(data.error || 'Error');
  return data;
}

/* ----- Clientes autocompletar ----- */
$('f_cliente').addEventListener('keyup',()=>{
  const q = $('f_cliente').value.trim();
  clearTimeout(clienteTimer);
  if(q.length<2){ $('f_id_cliente').value=''; return; }
  clienteTimer = setTimeout(async ()=>{
    try{
      const res = await fetch(CLIENTE_API + '?q=' + encodeURIComponent(q));
      const js = await res.json();
      if(js.ok && js.data.length){
        const c = js.data[0];
        $('f_cliente').value = c.nombre_completo + (c.ruc_ci?` (${c.ruc_ci})`:'');
        $('f_id_cliente').value = c.id_cliente;
      }
    }catch(e){}
  },250);
});
$('p_cliente').addEventListener('keyup',()=>{
  const q = $('p_cliente').value.trim();
  clearTimeout(clientePTimer);
  if(q.length<2){ $('p_id_cliente').value=''; return; }
  clientePTimer = setTimeout(async ()=>{
    try{
      const res = await fetch(CLIENTE_API + '?q=' + encodeURIComponent(q));
      const js = await res.json();
      if(js.ok && js.data.length){
        const c = js.data[0];
        $('p_cliente').value = c.nombre_completo + (c.ruc_ci?` (${c.ruc_ci})`:'');
        $('p_id_cliente').value = c.id_cliente;
      }
    }catch(e){}
  },250);
});

function formatDate(d){
  return d ? new Date(d).toLocaleDateString('es-PY') : '-';
}
function formatMoney(n){
  return Number(n||0).toLocaleString('es-PY',{minimumFractionDigits:0});
}

/* ---- Chips de fecha ---- */
function setRangeChip(el){
  document.querySelectorAll('.chips .chip').forEach(c=>c.classList.remove('active'));
  el.classList.add('active');
  const hoy = new Date();
  let desde = '', hasta = '';
  switch(el.dataset.range){
    case 'hoy':
      desde = hasta = hoy.toISOString().substring(0,10);
      break;
    case 'ayer':
      const ayer = new Date(hoy); ayer.setDate(hoy.getDate()-1);
      desde = hasta = ayer.toISOString().substring(0,10);
      break;
    case 'ultimos7':
      const ult7 = new Date(hoy); ult7.setDate(hoy.getDate()-7);
      desde = ult7.toISOString().substring(0,10);
      hasta = hoy.toISOString().substring(0,10);
      break;
    case 'mes':
      const first = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
      desde = first.toISOString().substring(0,10);
      hasta = hoy.toISOString().substring(0,10);
      break;
    case 'limpiar':
      desde = ''; hasta = '';
      break;
  }
  $('f_desde').value = desde;
  $('f_hasta').value = hasta;
}

/* ---- Reservas ---- */
async function buscarReservas(){
  try{
    const payload = {
      id_cliente: parseInt($('f_id_cliente').value||0,10),
      fecha_desde: $('f_desde').value || null,
      fecha_hasta: $('f_hasta').value || null,
      estado: 'Confirmada'
    };
    const data = await api('list_reservas',payload);
    reservas = data.rows || [];
    renderReservas();
  }catch(e){
    showToast(e.message,'error');
  }
}
function renderReservas(){
  const cont = $('reservas_list');
  cont.innerHTML = '';
  if(!reservas.length){
    cont.innerHTML = '<div class="empty">No se encontraron reservas con estos filtros.</div>';
    return;
  }
  reservas.forEach(res=>{
    const servicios = (res.servicios || []).slice(0,3).map(s=>`<span class="service-pill">${s.descripcion} (${s.cantidad})</span>`).join('');
    const extras = (res.servicios||[]).length > 3 ? `<span class="service-pill">+${(res.servicios.length-3)} más</span>` : '';
    const badge = res.presupuestos_count>0 ? `<span class="tag badge">${res.presupuestos_count} presupuesto(s)</span>` : '';
    const card = document.createElement('div');
    card.className = 'card-item';
    card.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;">
        <div>
          <h3>Reserva #${res.id_reserva}</h3>
          <div class="muted">${formatDate(res.fecha_reserva)} · ${res.inicio_ts?res.inicio_ts.substring(11,16):''} - ${res.fin_ts?res.fin_ts.substring(11,16):''}</div>
          <div class="muted">Cliente: ${res.cliente_nombre||''} ${res.cliente_apellido||''}</div>
          ${res.profesional_nombre ? `<div class="muted">Profesional: ${res.profesional_nombre}</div>` : ''}
        </div>
        <span class="tag estado">${res.estado}</span>
      </div>
      <div class="services">${servicios}${extras}</div>
      <div class="actions-row">
        <button class="small" onclick="generarPresupuesto(${res.id_reserva})">Generar presupuesto</button>
        <button class="sec small" onclick="verReserva(${res.id_reserva})">Ver reserva</button>
        ${badge}
      </div>
    `;
    cont.appendChild(card);
  });
}
function verReserva(id){
  const url = `/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/orden_reserva/ver_reserva.php?id=${id}`;
  window.open(url,'_blank');
}
async function generarPresupuesto(id_reserva){
  if(!confirm('¿Crear borrador de presupuesto desde la reserva seleccionada?')) return;
  try{
    const datos = await api('create_from_reserva',{id_reserva});
    showToast('Presupuesto creado (#'+datos.id_presupuesto+')');
    await loadPresupuestos();
    await buscarReservas();
  }catch(e){
    showToast(e.message,'error');
  }
}

/* ---- Presupuestos ---- */
async function loadPresupuestos(){
  try{
    const payload = {
      estado: $('p_estado').value,
      id_cliente: parseInt($('p_id_cliente').value||0,10),
      fecha_desde: $('p_desde').value || null,
      fecha_hasta: $('p_hasta').value || null
    };
    const data = await api('list',payload);
    presupuestos = data.rows || [];
    renderPresupuestos();
  }catch(e){
    showToast(e.message,'error');
  }
}
function renderPresupuestos(){
  const cont = $('presupuestos_list');
  cont.innerHTML = '';
  if(!presupuestos.length){
    cont.innerHTML = '<div class="empty">Aún no hay presupuestos para mostrar.</div>';
    return;
  }
  presupuestos.forEach(p=>{
    const card = document.createElement('div');
    card.className = 'card-item';
    card.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
        <div>
          <h3>Presupuesto #${p.id_presupuesto}</h3>
          <div class="muted">Fecha: ${formatDate(p.fecha_presupuesto)}</div>
          <div class="muted">Cliente: ${p.nombre||''} ${p.apellido||''}</div>
          ${p.id_reserva ? `<div class="muted">Reserva asociada: #${p.id_reserva}</div>` : ''}
          ${p.validez_hasta ? `<div class="muted">Válido hasta: ${formatDate(p.validez_hasta)}</div>` : ''}
        </div>
        <span class="badge-estado ${p.estado}">${p.estado}</span>
      </div>
      <div style="font-size:13px;"><strong>Total:</strong> Gs ${formatMoney(p.total_neto)}</div>
      <div class="actions-row">
        <button class="small" onclick="abrirDetalle(${p.id_presupuesto})">Ver detalle</button>
        <button class="sec small" onclick="imprimirPresupuestoDirecto(${p.id_presupuesto})">Imprimir</button>
      </div>
    `;
    cont.appendChild(card);
  });
}
function imprimirPresupuestoDirecto(id){
  window.open(`presupuesto_print.php?id=${id}`, '_blank');
}

/* ---- Modal detalle ---- */
function cerrarModal(){
  $('modal_detalle').style.display='none';
  selectedPresupuesto = null;
}
async function abrirDetalle(id){
  try{
    const data = await api('get',{id_presupuesto:id});
    selectedPresupuesto = data.cab;
    $('modal_title').textContent = `Presupuesto #${data.cab.id_presupuesto}`;
    $('modal_cliente').textContent = `${data.cab.nombre || ''} ${data.cab.apellido || ''}`;
    $('modal_fecha').textContent = formatDate(data.cab.fecha_presupuesto);
    $('modal_validez').textContent = data.cab.validez_hasta ? formatDate(data.cab.validez_hasta) : 'No indicado';
    $('modal_estado').innerHTML = `<span class="badge-estado ${data.cab.estado}">${data.cab.estado}</span>`;

    const tbody = $('modal_items').querySelector('tbody');
    tbody.innerHTML = '';
    let grav10=0, iva10=0, grav5=0, iva5=0, exentas=0, total=0;
    (data.items||[]).forEach((it, idx)=>{
      const subtotal = Number(it.subtotal_neto || 0);
      const iva = Number(it.iva_monto || 0);
      total += subtotal;
      if(it.tipo_iva === 'IVA10'){
        grav10 += subtotal - iva;
        iva10  += iva;
      }else if(it.tipo_iva === 'IVA5'){
        grav5  += subtotal - iva;
        iva5   += iva;
      }else{
        exentas += subtotal;
      }
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${idx+1}</td>
        <td>${it.descripcion}</td>
        <td>${it.tipo_item}</td>
        <td>${it.cantidad}</td>
        <td>${formatMoney(it.precio_unitario)}</td>
        <td>${formatMoney(it.descuento)}</td>
        <td>${it.tipo_iva}</td>
        <td>${formatMoney(subtotal)}</td>
      `;
      tbody.appendChild(tr);
    });
    $('modal_grav10').textContent = formatMoney(grav10);
    $('modal_iva10').textContent  = formatMoney(iva10);
    $('modal_grav5').textContent  = formatMoney(grav5);
    $('modal_iva5').textContent   = formatMoney(iva5);
    $('modal_exentas').textContent= formatMoney(exentas);
    $('modal_total').textContent  = formatMoney(total);

    $('modal_detalle').style.display='flex';
  }catch(e){
    showToast(e.message,'error');
  }
}
function imprimirPresupuesto(){
  if(!selectedPresupuesto) return;
  const url = `presupuesto_print.php?id=${selectedPresupuesto.id_presupuesto}`;
  window.open(url,'_blank');
}

/* Inicialización */
window.addEventListener('DOMContentLoaded', ()=>{
  buscarReservas();
  loadPresupuestos();
});
</script>
</body>
</html>
