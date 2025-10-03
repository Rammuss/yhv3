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
<title>Reclamos de Servicios</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/css/styles_servicios.css" />
<style>
  :root{
    --bg:#f1f5f9; --surface:#fff; --border:#dbeafe; --shadow:0 12px 28px rgba(15,23,42,.12);
    --text:#0f172a; --muted:#64748b; --accent:#2563eb; --accent-dark:#1d4ed8; --danger:#dc2626;
  }
  *{box-sizing:border-box;}
  body{margin:0;padding:24px;font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto;background:var(--bg);color:var(--text);}
  h1{margin:0 0 16px;font-size:24px;}
  .layout{display:grid;grid-template-columns:2.2fr 1fr;gap:18px;}
  .column{display:flex;flex-direction:column;gap:16px;}
  .card{background:var(--surface);border-radius:14px;padding:18px;box-shadow:var(--shadow);border:1px solid var(--border);}
  .section-title{margin:0 0 12px;font-size:16px;font-weight:600;display:flex;align-items:center;justify-content:space-between;}
  label{display:block;margin:10px 0 4px;text-transform:uppercase;font-size:11px;color:var(--muted);letter-spacing:.04em;}
  input,select,textarea,button{width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:14px;}
  button{cursor:pointer;font-weight:600;background:var(--accent);color:#fff;border:none;}
  button:hover{background:var(--accent-dark);}
  button.sec{background:#e2e8f0;color:var(--text);}
  button.danger{background:var(--danger);}
  button.small{padding:6px 10px;font-size:12px;}
  textarea{resize:vertical;min-height:100px;}
  .filters{display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:12px;}
  .list{display:flex;flex-direction:column;gap:12px;max-height:70vh;overflow:auto;padding-right:4px;}
  .card-item{border:1px solid var(--border);border-radius:12px;padding:14px;display:flex;flex-direction:column;gap:8px;background:#fff;box-shadow:0 8px 18px rgba(15,23,42,.08);}
  .card-item h3{margin:0;font-size:15px;}
  .muted{color:var(--muted);font-size:12px;}
  .tag{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;}
  .badge-estado{display:inline-block;padding:4px 10px;border-radius:999px;font-size:12px;font-weight:600;}
  .Abierto{background:#fef9c3;color:#854d0e;}
  .En\ gestión{background:#dbeafe;color:#1d4ed8;}
  .Resuelto{background:#dcfce7;color:#166534;}
  .Cerrado{background:#e2e8f0;color:#475569;}
  .timeline{border-left:2px solid #e2e8f0;margin-top:12px;padding-left:16px;}
  .timeline-item{margin-bottom:12px;position:relative;}
  .timeline-item::before{content:'';position:absolute;left:-18px;top:4px;width:10px;height:10px;border-radius:50%;background:#2563eb;}
  .timeline-item .time{color:#94a3b8;font-size:12px;}
  .empty{padding:20px;text-align:center;color:var(--muted);border:1px dashed var(--border);border-radius:12px;}
  .toast{position:fixed;top:18px;right:18px;padding:12px 16px;background:var(--accent);color:#fff;border-radius:10px;box-shadow:var(--shadow);display:none;min-width:240px;font-weight:600;}
  .toast.error{background:#dc2626;}
  .info-box{margin-top:6px;font-size:12px;color:#475569;background:#f8fafc;border-radius:8px;padding:8px;border:1px solid #e2e8f0;}
  .modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:none;align-items:flex-start;justify-content:center;padding:40px 20px;z-index:1000;}
  .modal .inner{background:#fff;border-radius:16px;max-width:850px;width:100%;padding:24px;box-shadow:0 20px 46px rgba(15,23,42,.2);max-height:92vh;overflow:auto;}
  .modal h2{margin:0 0 12px;}
  .detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;margin-bottom:16px;}
</style>
</head>
<body>
     <div id="navbar-container"></div>
<script src="/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/servicios/navbar/navbar.js"></script>
  
<h1>Reclamos de Servicios</h1>

<div class="layout">
  <div class="column">
    <div class="card">
      <div class="section-title">
        <span>Filtros</span>
        <button class="sec small" onclick="loadReclamos()">Actualizar</button>
      </div>
      <div class="filters">
        <div>
          <label>Estado</label>
          <select id="f_estado" onchange="loadReclamos()">
            <option value="Todos">Todos</option>
            <option>Abierto</option>
            <option>En gestión</option>
            <option>Resuelto</option>
            <option>Cerrado</option>
          </select>
        </div>
        <div>
          <label>Cliente</label>
          <input id="f_cliente" placeholder="Nombre / CI">
          <input type="hidden" id="f_id_cliente">
        </div>
        <div>
          <label>Profesional</label>
          <select id="f_profesional" onchange="loadReclamos()">
            <option value="">Todos</option>
          </select>
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
      <div id="reclamos_list" class="list" style="margin-top:14px;"></div>
    </div>
  </div>

  <div class="column">
    <div class="card">
      <div class="section-title">Nuevo reclamo</div>
      <form id="formReclamo" autocomplete="off">
        <label>Cliente</label>
        <input id="nuevo_cliente" placeholder="Buscar cliente">
        <input type="hidden" id="nuevo_id_cliente">
        <div class="info-box" id="info_cliente">Seleccioná un cliente para ver sus OT y reservas.</div>

        <label>OT del cliente</label>
        <select id="nuevo_ot" onchange="handleSeleccionOT(this.value)">
          <option value="">(Sin seleccionar)</option>
        </select>
        <div class="info-box" id="info_ot"></div>

        <label>Reserva del cliente</label>
        <select id="nuevo_reserva" onchange="handleSeleccionReserva(this.value)">
          <option value="">(Sin seleccionar)</option>
        </select>
        <div class="info-box" id="info_reserva"></div>

        <label>Canal</label>
        <select id="nuevo_canal">
          <option>Teléfono</option>
          <option>WhatsApp</option>
          <option>Email</option>
          <option>Presencial</option>
          <option>Otro</option>
        </select>

        <label>Tipo</label>
        <select id="nuevo_tipo">
          <option>Calidad</option>
          <option>Tiempo</option>
          <option>Facturación</option>
          <option>Trato</option>
          <option>General</option>
        </select>

        <label>Prioridad</label>
        <select id="nuevo_prioridad">
          <option>Media</option>
          <option>Baja</option>
          <option>Alta</option>
        </select>

        <label>Descripción</label>
        <textarea id="nuevo_descripcion" required></textarea>

        <label>Responsable (seguimiento interno)</label>
        <input id="nuevo_responsable" placeholder="Opcional">

        <button type="submit" style="margin-top:12px;">Registrar reclamo</button>
      </form>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<div id="modal_detalle" class="modal">
  <div class="inner">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
      <h2 id="modal_title">Reclamo</h2>
      <button class="sec small" onclick="cerrarModal()">Cerrar</button>
    </div>
    <div class="detail-grid">
      <div>
        <span class="muted">Cliente</span>
        <div id="modal_cliente" style="font-weight:600;"></div>
      </div>
      <div>
        <span class="muted">Fecha reclamo</span>
        <div id="modal_fecha"></div>
      </div>
      <div>
        <span class="muted">Estado</span>
        <div id="modal_estado"></div>
      </div>
      <div>
        <span class="muted">Canal</span>
        <div id="modal_canal"></div>
      </div>
      <div>
        <span class="muted">Tipo</span>
        <div id="modal_tipo"></div>
      </div>
      <div>
        <span class="muted">Prioridad</span>
        <div id="modal_prioridad"></div>
      </div>
      <div>
        <span class="muted">Responsable</span>
        <div id="modal_responsable"></div>
      </div>
      <div>
        <span class="muted">Vínculos</span>
        <div id="modal_links"></div>
      </div>
    </div>

    <div>
      <h3 style="margin:12px 0 6px;">Descripción</h3>
      <div id="modal_descripcion" style="white-space:pre-wrap;background:#f8fafc;border-radius:10px;padding:12px;border:1px solid #e2e8f0;"></div>
    </div>

    <div class="timeline" id="modal_historial"></div>

    <div style="margin-top:20px;">
      <h3 style="margin:0 0 6px;">Actualizar</h3>
      <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));">
        <div>
          <label>Estado</label>
          <select id="modal_estado_update">
            <option>Abierto</option>
            <option>En gestión</option>
            <option>Resuelto</option>
            <option>Cerrado</option>
          </select>
        </div>
        <div>
          <label>Responsable</label>
          <input id="modal_responsable_update">
        </div>
      </div>
      <label>Resolución / Comentarios</label>
      <textarea id="modal_resolucion_update" style="min-height:80px;"></textarea>
      <button style="margin-top:10px;" onclick="guardarActualizacion()">Guardar cambios</button>
    </div>

    <div style="margin-top:22px;">
      <h3 style="margin:0 0 6px;">Agregar nota al historial</h3>
      <div style="display:grid;gap:10px;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
        <div>
          <label>Evento</label>
          <select id="modal_hist_evento">
            <option>Cambio estado</option>
            <option>Nota</option>
            <option>Contacto cliente</option>
            <option>Asignación</option>
          </select>
        </div>
      </div>
      <textarea id="modal_hist_detalle" style="min-height:80px;margin-top:6px;"></textarea>
      <button style="margin-top:10px;" onclick="agregarHistorial()">Agregar nota</button>
    </div>
  </div>
</div>

<script>
const API = '../reclamo/api_reclamo.php';
const CLIENTE_API = '/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v3/cliente/clientes_buscar.php';
const $ = id => document.getElementById(id);

let reclamos = [];
let reclamoSeleccionado = null;
let profesionales = [];
let otsCliente = [];
let reservasCliente = [];
let clienteTimer = null;
let clienteNuevoTimer = null;

function showToast(msg,type='ok'){
  const t = $('toast');
  t.textContent = msg;
  t.className = 'toast' + (type==='error' ? ' error':'');
  t.style.display = 'block';
  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(()=>{ t.style.display='none'; }, 2600);
}

async function api(op, payload={}){
  const res = await fetch(API,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({op, ...payload})
  });
  const data = await res.json();
  if(!data.success) throw new Error(data.error || 'Error en API');
  return data;
}

function formatDateTime(d){
  return d ? new Date(d).toLocaleString('es-PY') : '-';
}
function formatDate(d){
  return d ? new Date(d).toLocaleDateString('es-PY') : '-';
}

/* ---------- Profesionales ---------- */
async function loadProfesionales(){
  try{
    const data = await api('list_profesionales');
    profesionales = data.rows || [];
    const filtroSel = $('f_profesional');
    const nuevoSel = $('nuevo_responsable'); // mantenemos campo texto, pero usaremos info.
    // Filtro
    filtroSel.innerHTML = '<option value="">Todos</option>';
    profesionales.forEach(p=>{
      const opt = document.createElement('option');
      opt.value = p.id_profesional;
      opt.textContent = p.nombre;
      filtroSel.appendChild(opt);
    });
  }catch(e){
    showToast(e.message,'error');
  }
}

/* ---------- Lista ---------- */
async function loadReclamos(){
  try{
    const payload = {
      estado: $('f_estado').value,
      id_cliente: parseInt($('f_id_cliente').value||0,10),
      id_profesional: parseInt($('f_profesional').value||0,10),
      fecha_desde: $('f_desde').value || null,
      fecha_hasta: $('f_hasta').value || null
    };
    const data = await api('list',payload);
    reclamos = data.rows || [];
    renderReclamos();
  }catch(e){
    showToast(e.message,'error');
  }
}
function renderReclamos(){
  const cont = $('reclamos_list');
  cont.innerHTML = '';
  if(!reclamos.length){
    cont.innerHTML = '<div class="empty">No se encontraron reclamos con estos filtros.</div>';
    return;
  }
  reclamos.forEach(r=>{
    const card = document.createElement('div');
    card.className = 'card-item';
    card.innerHTML = `
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
        <div>
          <h3>Reclamo #${r.id_reclamo}</h3>
          <div class="muted">${formatDateTime(r.fecha_reclamo)}</div>
          <div class="muted">Cliente: ${r.cliente_nombre||''} ${r.cliente_apellido||''}</div>
          ${r.profesional_nombre ? `<div class="muted">Profesional: ${r.profesional_nombre}</div>` : ''}
          <div class="muted">Tipo: ${r.tipo} · Canal: ${r.canal}</div>
        </div>
        <span class="badge-estado ${r.estado}">${r.estado}</span>
      </div>
      <div class="actions-row">
        <button class="small" onclick="abrirDetalle(${r.id_reclamo})">Ver detalle</button>
      </div>
    `;
    cont.appendChild(card);
  });
}

/* ---------- Autocompletado cliente (filtros) ---------- */
$('f_cliente').addEventListener('keyup',()=>{
  const q = $('f_cliente').value.trim();
  clearTimeout(clienteTimer);
  if(q.length<2){ $('f_id_cliente').value=''; return; }
  clienteTimer = setTimeout(async ()=>{
    try{
      const res = await fetch(CLIENTE_API+'?q='+encodeURIComponent(q));
      const js = await res.json();
      if(js.ok && js.data.length){
        const c = js.data[0];
        $('f_cliente').value = c.nombre_completo + (c.ruc_ci?` (${c.ruc_ci})`:'');
        $('f_id_cliente').value = c.id_cliente;
      }
    }catch(e){}
  },250);
});

/* ---------- Cliente nuevo reclamo ---------- */
$('nuevo_cliente').addEventListener('keyup',()=>{
  const q = $('nuevo_cliente').value.trim();
  clearTimeout(clienteNuevoTimer);
  if(q.length<2){ $('nuevo_id_cliente').value=''; limpiarOpcionesCliente(); return; }
  clienteNuevoTimer = setTimeout(async ()=>{
    try{
      const res = await fetch(CLIENTE_API+'?q='+encodeURIComponent(q));
      const js = await res.json();
      if(js.ok && js.data.length){
        const c = js.data[0];
        $('nuevo_cliente').value = c.nombre_completo + (c.ruc_ci?` (${c.ruc_ci})`:'');
        $('nuevo_id_cliente').value = c.id_cliente;
        cargarOpcionesCliente(c.id_cliente);
      }
    }catch(e){}
  },250);
});

function limpiarOpcionesCliente(){
  $('info_cliente').textContent = 'Seleccioná un cliente para ver sus OT y reservas.';
  $('nuevo_ot').innerHTML = '<option value="">(Sin seleccionar)</option>';
  $('info_ot').textContent = '';
  $('nuevo_reserva').innerHTML = '<option value="">(Sin seleccionar)</option>';
  $('info_reserva').textContent = '';
  otsCliente = [];
  reservasCliente = [];
}

async function cargarOpcionesCliente(id_cliente){
  try{
    const [ots, reservas] = await Promise.all([
      api('list_ots_cliente',{id_cliente}),
      api('list_reservas_cliente',{id_cliente})
    ]);
    otsCliente = ots.rows || [];
    reservasCliente = reservas.rows || [];
    renderOTsCliente();
    renderReservasCliente();
    $('info_cliente').textContent = `Cliente seleccionado con ${otsCliente.length} OT y ${reservasCliente.length} reserva(s).`;
  }catch(e){
    showToast(e.message,'error');
  }
}

function renderOTsCliente(){
  const sel = $('nuevo_ot');
  sel.innerHTML = '<option value="">(Sin seleccionar)</option>';
  otsCliente.forEach(ot=>{
    const opt = document.createElement('option');
    opt.value = ot.id_ot;
    const fecha = ot.fecha_programada ? formatDate(ot.fecha_programada) : 's/fecha';
    const hora  = ot.hora_programada ? ot.hora_programada.substring(0,5) : '';
    opt.textContent = `OT #${ot.id_ot} · ${fecha} ${hora} · ${ot.profesional_nombre || 'Sin profesional'}`;
    opt.dataset.prof = ot.id_profesional || '';
    opt.dataset.profNombre = ot.profesional_nombre || '';
    opt.dataset.fecha = ot.fecha_programada || '';
    opt.dataset.hora = ot.hora_programada || '';
    sel.appendChild(opt);
  });
  $('info_ot').textContent = '';
}

function renderReservasCliente(){
  const sel = $('nuevo_reserva');
  sel.innerHTML = '<option value="">(Sin seleccionar)</option>';
  reservasCliente.forEach(r=>{
    const fecha = r.fecha_reserva ? formatDate(r.fecha_reserva) : 's/fecha';
    const inicio = r.inicio_ts ? r.inicio_ts.substring(11,16) : '';
    const opt = document.createElement('option');
    opt.value = r.id_reserva;
    opt.textContent = `Reserva #${r.id_reserva} · ${fecha} ${inicio} · ${r.profesional_nombre || 'Sin profesional'}`;
    opt.dataset.prof = r.id_profesional || '';
    opt.dataset.profNombre = r.profesional_nombre || '';
    opt.dataset.fecha = r.fecha_reserva || '';
    opt.dataset_inicio = r.inicio_ts || '';
    opt.dataset_fin = r.fin_ts || '';
    sel.appendChild(opt);
  });
  $('info_reserva').textContent = '';
}

function handleSeleccionOT(id){
  const selOT = $('nuevo_ot');
  const selReserva = $('nuevo_reserva');
  if(id){
    selReserva.value = '';
    const opt = selOT.options[selOT.selectedIndex];
    const profNombre = opt.dataset.profNombre || 'Sin profesional';
    const fecha = opt.dataset.fecha ? formatDate(opt.dataset.fecha) : 's/fecha';
    const hora = opt.dataset.hora ? opt.dataset.hora.substring(0,5) : '';
    $('info_ot').textContent = `Seleccionada OT #${id} · ${fecha} ${hora} · Profesional: ${profNombre}`;
  }else{
    $('info_ot').textContent = '';
  }
  $('info_reserva').textContent = '';
}

function handleSeleccionReserva(id){
  const selReserva = $('nuevo_reserva');
  const selOT = $('nuevo_ot');
  if(id){
    selOT.value = '';
    const opt = selReserva.options[selReserva.selectedIndex];
    const profNombre = opt.dataset.profNombre || 'Sin profesional';
    const fecha = opt.dataset.fecha ? formatDate(opt.dataset.fecha) : 's/fecha';
    const inicio = opt.dataset_inicio ? opt.dataset_inicio.substring(11,16) : '';
    const fin = opt.dataset_fin ? opt.dataset_fin.substring(11,16) : '';
    $('info_reserva').textContent = `Seleccionada Reserva #${id} · ${fecha} ${inicio}-${fin || ''} · Profesional: ${profNombre}`;
  }else{
    $('info_reserva').textContent = '';
  }
  $('info_ot').textContent = '';
}

/* ---------- Crear reclamo ---------- */
$('formReclamo').addEventListener('submit', async (e)=>{
  e.preventDefault();
  const id_cliente = parseInt($('nuevo_id_cliente').value||0,10);
  if(id_cliente<=0){
    showToast('Seleccioná un cliente','error');
    return;
  }
  const descripcion = $('nuevo_descripcion').value.trim();
  if(descripcion===''){
    showToast('Ingresá una descripción','error');
    return;
  }
  const payload = {
    id_cliente,
    id_ot: $('nuevo_ot').value || null,
    id_reserva: $('nuevo_reserva').value || null,
    canal: $('nuevo_canal').value,
    tipo: $('nuevo_tipo').value,
    prioridad: $('nuevo_prioridad').value,
    descripcion,
    responsable: $('nuevo_responsable').value || null
  };
  try{
    await api('create',payload);
    showToast('Reclamo registrado');
    $('formReclamo').reset();
    $('nuevo_id_cliente').value='';
    limpiarOpcionesCliente();
    await loadReclamos();
  }catch(e){
    showToast(e.message,'error');
  }
});

/* ---------- Modal ---------- */
function cerrarModal(){
  $('modal_detalle').style.display='none';
  reclamoSeleccionado = null;
}
async function abrirDetalle(id){
  try{
    const data = await api('get',{id_reclamo:id});
    reclamoSeleccionado = data.reclamo;

    $('modal_title').textContent = `Reclamo #${reclamoSeleccionado.id_reclamo}`;
    $('modal_cliente').textContent = `${reclamoSeleccionado.nombre || ''} ${reclamoSeleccionado.apellido || ''} (${reclamoSeleccionado.ruc_ci || '-'})`;
    $('modal_fecha').textContent = formatDateTime(reclamoSeleccionado.fecha_reclamo);
    $('modal_estado').innerHTML = `<span class="badge-estado ${reclamoSeleccionado.estado}">${reclamoSeleccionado.estado}</span>`;
    $('modal_canal').textContent = reclamoSeleccionado.canal || '-';
    $('modal_tipo').textContent = reclamoSeleccionado.tipo || '-';
    $('modal_prioridad').textContent = reclamoSeleccionado.prioridad || '-';
    $('modal_responsable').textContent = reclamoSeleccionado.responsable || '-';
    $('modal_descripcion').textContent = reclamoSeleccionado.descripcion || '';

    const links = [];
    if(reclamoSeleccionado.id_ot){
      links.push(`OT: #${reclamoSeleccionado.id_ot}`);
    }
    if(reclamoSeleccionado.id_reserva){
      links.push(`Reserva: #${reclamoSeleccionado.id_reserva}`);
    }
    $('modal_links').textContent = links.length ? links.join(' · ') : 'Sin vínculos';

    $('modal_estado_update').value = reclamoSeleccionado.estado;
    $('modal_responsable_update').value = reclamoSeleccionado.responsable || '';
    $('modal_resolucion_update').value = reclamoSeleccionado.resolucion || '';

    const timeline = $('modal_historial');
    timeline.innerHTML = '<strong>Historial</strong>';
    if((data.historial||[]).length===0){
      timeline.innerHTML += '<div class="muted" style="margin-top:6px;">Sin registros en el historial.</div>';
    }else{
      data.historial.forEach(h=>{
        const div = document.createElement('div');
        div.className = 'timeline-item';
        div.innerHTML = `
          <div class="time">${formatDateTime(h.registrado_en)} · ${h.registrado_por || 'Sistema'}</div>
          <div><strong>${h.evento}:</strong> ${h.detalle}</div>
        `;
        timeline.appendChild(div);
      });
    }

    $('modal_detalle').style.display='flex';
  }catch(e){
    showToast(e.message,'error');
  }
}

async function guardarActualizacion(){
  if(!reclamoSeleccionado) return;
  try{
    const estado = $('modal_estado_update').value;
    const responsable = $('modal_responsable_update').value || null;
    const resolucion = $('modal_resolucion_update').value || null;

    await api('change_state',{id_reclamo:reclamoSeleccionado.id_reclamo, estado});
    await api('update',{
      id_reclamo: reclamoSeleccionado.id_reclamo,
      responsable,
      resolucion
    });
    showToast('Reclamo actualizado');
    cerrarModal();
    await loadReclamos();
  }catch(e){
    showToast(e.message,'error');
  }
}

async function agregarHistorial(){
  if(!reclamoSeleccionado) return;
  const evento = $('modal_hist_evento').value;
  const detalle = $('modal_hist_detalle').value.trim();
  if(detalle===''){
    showToast('Ingresá un detalle','error');
    return;
  }
  try{
    await api('add_historial',{
      id_reclamo: reclamoSeleccionado.id_reclamo,
      evento,
      detalle
    });
    $('modal_hist_detalle').value='';
    showToast('Nota agregada');
    await abrirDetalle(reclamoSeleccionado.id_reclamo); // recarga modal
  }catch(e){
    showToast(e.message,'error');
  }
}

/* ---------- Inicialización ---------- */
window.addEventListener('DOMContentLoaded', ()=>{
  loadProfesionales().then(loadReclamos);
});
</script>
</body>
</html>
