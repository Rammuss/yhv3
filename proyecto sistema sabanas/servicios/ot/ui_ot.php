<?php
// ui_ot.php — Interfaz de Órdenes de Trabajo (HTML + JS)
// Requiere api_ot.php en el mismo directorio
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Órdenes de Trabajo</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  :root{ --g:#10b981; --r:#ef4444; --b:#111; --shadow:0 10px 24px rgba(15,23,42,.12); }
  body{ font-family:system-ui,Segoe UI,Roboto,Arial; margin:20px; color:#111; background:#fff; }
  h1{ margin:0 0 20px; }
  .layout{ display:grid; grid-template-columns: minmax(320px,2.4fr) minmax(360px,3fr); gap:16px; align-items:start; }
  .card{ border:1px solid #e5e7eb; border-radius:12px; padding:16px; background:#fff; box-shadow:0 6px 18px rgba(15,23,42,.06); }
  .card h2{ margin:0 0 12px; font-size:18px; }
  .row{ display:flex; gap:10px; flex-wrap:wrap; }
  label{ display:block; font-size:12px; text-transform:uppercase; letter-spacing:.05em; color:#4b5563; margin-bottom:4px; }
  input, select, button, textarea{ padding:9px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; }
  textarea{ width:100%; min-height:80px; resize:vertical; }
  input:focus, select:focus, textarea:focus{ outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.2); }
  button{ cursor:pointer; background:#111; color:#fff; border:none; }
  button.sec{ background:#fff; color:#111; border:1px solid #d1d5db; }
  button.danger{ background:#dc2626; }
  button:disabled{ opacity:.6; cursor:not-allowed; }
  table{ width:100%; border-collapse:collapse; margin-top:10px; }
  th,td{ border-bottom:1px solid #e5e7eb; padding:8px; text-align:left; font-size:13px; }
  th{ background:#f3f4f6; text-transform:uppercase; letter-spacing:.04em; font-size:12px; color:#4b5563; }
  tr:hover td{ background:#f9fafb; }
  .badge{ display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:999px; font-size:12px; }
  .badge.estado{ background:#e0f2fe; color:#075985; }
  .muted{ color:#6b7280; font-size:12px; }
  .list-actions{ display:flex; gap:8px; align-items:flex-end; margin-bottom:10px; flex-wrap:wrap; }
  .toast-layer{ position:fixed; inset:0; pointer-events:none; display:grid; place-items:flex-end center; padding:24px; z-index:9999; }
  .toast{ pointer-events:auto; min-width:300px; padding:14px 16px; background:#111; color:#fff; border-radius:12px; margin-top:12px; box-shadow:var(--shadow); display:flex; justify-content:space-between; gap:12px; animation:toast-in .18s ease-out both; }
  .toast.error{ background:#b91c1c; }
  .toast button{ background:transparent; color:#fff; border:none; font-size:18px; cursor:pointer; padding:0; margin-left:12px; }
  @keyframes toast-in{ from{ transform:translateY(12px); opacity:0 } to{ transform:translateY(0); opacity:1 } }
  .section-title{ font-size:15px; font-weight:600; margin:16px 0 8px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
  .inline-form{ display:flex; gap:8px; flex-wrap:wrap; align-items:flex-end; margin-top:8px; }
  .inline-form > div{ flex:1; min-width:160px; }
  .tag{ display:inline-flex; padding:2px 6px; font-size:11px; border-radius:6px; background:#f1f5f9; color:#0f172a; text-transform:uppercase; letter-spacing:.05em; }
  .solicitud-list{ display:flex; flex-direction:column; gap:8px; margin-top:6px; }
  .solicitud-card{ border:1px solid #e5e7eb; border-radius:10px; padding:10px 12px; background:#f8fafc; display:flex; gap:10px; align-items:flex-start; cursor:pointer; }
  .solicitud-card input{ margin-top:4px; }
  .solicitud-card strong{ display:block; margin-bottom:4px; font-size:13px; color:#0f172a; }
  .solicitud-items{ font-size:12px; color:#475569; }
  .solicitud-items span{ display:inline-block; margin-right:8px; margin-bottom:4px; padding:2px 6px; background:#e2e8f0; border-radius:6px; }
</style>
</head>
<body>
  <h1>Órdenes de Trabajo</h1>
  <div class="layout">
    <div class="card" id="panel_lista">
      <h2>Listado OT</h2>
      <div class="list-actions">
        <div>
          <label>Estado</label>
          <select id="f_estado">
            <option>Todos</option>
            <option>Programada</option>
            <option>En ejecución</option>
            <option>Completada</option>
            <option>Cancelada</option>
          </select>
        </div>
        <div>
          <label>Fecha</label>
          <input type="date" id="f_fecha">
        </div>
        <div style="display:flex; gap:8px; align-items:flex-end">
          <button onclick="loadOTs()">Buscar</button>
          <button class="sec" onclick="resetFiltros()">Limpiar</button>
        </div>
      </div>

      <div class="muted" style="margin:4px 0 12px">Seleccioná una OT para ver sus detalles.</div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Estado</th>
            <th>Reserva</th>
          </tr>
        </thead>
        <tbody id="tbody_ot"></tbody>
      </table>

      <div class="section-title" style="margin-top:22px">
        <span>Buscar reservas</span>
        <span class="muted">Generá OT sin memorizar IDs</span>
      </div>
      <div class="inline-form">
        <div>
          <label>Cliente / ID reserva</label>
          <input id="sr_q" placeholder="Nombre, apellido o ID">
        </div>
        <div>
          <label>Fecha</label>
          <input type="date" id="sr_fecha">
        </div>
        <div style="flex:0">
          <button onclick="buscarReservas()">Buscar reservas</button>
        </div>
      </div>
      <div class="muted" style="margin-top:6px">Se muestran reservas confirmadas (y pendientes si no filtrás).</div>
      <table style="margin-top:10px">
        <thead>
          <tr>
            <th>ID</th>
            <th>Inicio</th>
            <th>Cliente</th>
            <th>Profesional</th>
            <th>Estado</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="tbody_reservas"></tbody>
      </table>

      <div class="section-title" style="margin-top:20px">
        <span>Crear desde ID (opcional)</span>
      </div>
      <div class="inline-form">
        <div>
          <label>ID Reserva</label>
          <input type="number" id="new_id_reserva" placeholder="Ej: 120">
        </div>
        <div style="flex:0">
          <button onclick="crearFromReserva()">Crear OT</button>
        </div>
      </div>
    </div>
    <div class="card" id="panel_detalle">
      <h2>Detalle de OT</h2>
      <div id="detalle_vacio" class="muted">Elegí una OT en la lista para ver o editar sus datos.</div>
      <div id="detalle_contenido" style="display:none">
        <div class="row" style="margin-bottom:12px">
          <div>
            <label>ID OT</label>
            <input id="d_id_ot" readonly>
          </div>
          <div>
            <label>Cliente</label>
            <input id="d_cliente" readonly>
          </div>
          <div>
            <label>Profesional</label>
            <select id="d_profesional" onchange="changeProfesional()">
              <option value="">(Asignar más tarde)</option>
            </select>
          </div>
        </div>
        <div class="row">
          <div>
            <label>Fecha programada</label>
            <input id="d_fecha_prog" readonly>
          </div>
          <div>
            <label>Hora programada</label>
            <input id="d_hora_prog" readonly>
          </div>
          <div>
            <label>Estado actual</label>
            <div><span class="badge estado" id="d_estado_badge">-</span></div>
          </div>
        </div>
        <div class="row" style="margin-top:10px">
          <div>
            <label>Inicio real</label>
            <input id="d_inicio_real" readonly>
          </div>
          <div>
            <label>Fin real</label>
            <input id="d_fin_real" readonly>
          </div>
          <div>
            <label>Pedido asociado</label>
            <input id="d_id_pedido" readonly>
          </div>
        </div>
        <div class="row" style="margin-top:10px">
          <div>
            <label>Acciones</label>
            <div style="display:flex; gap:8px;">
              <button onclick="marcarEnEjecucion()">Iniciar</button>
              <button onclick="marcarCompletada()">Finalizar</button>
              <button class="sec" onclick="marcarCancelada()">Cancelar</button>
            </div>
          </div>
        </div>

        <div class="section-title">
          <span>Servicios y descuentos</span>
          <span class="tag" id="total_ot_servicios">Gs 0</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>#</th><th>Item</th><th>Tipo</th><th>Cant.</th><th>Precio</th><th>Subtotal</th><th>IVA</th><th>Dur.</th><th></th>
            </tr>
          </thead>
          <tbody id="tbody_items"></tbody>
        </table>

        <div class="inline-form" style="margin-top:8px">
          <div>
            <label>Producto</label>
            <select id="new_item_prod"></select>
          </div>
          <div>
            <label>Cantidad</label>
            <input type="number" step="0.1" id="new_item_cant" value="1">
          </div>
          <div>
            <label>Precio (Gs)</label>
            <input type="number" step="100" id="new_item_precio">
          </div>
          <div>
            <label>Duración (min)</label>
            <input type="number" id="new_item_duracion">
          </div>
          <div style="flex:0">
            <button onclick="addItem()">Agregar</button>
          </div>
        </div>
        <div class="muted" id="hint_item"></div>

        <div class="section-title">
          <span>Insumos utilizados</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Producto</th>
              <th>Stock</th>
              <th>Cant.</th>
              <th>Depósito</th>
              <th>Lote</th>
              <th></th>
            </tr>
          </thead>
          <tbody id="tbody_insumos"></tbody>
        </table>

        <div class="inline-form" style="margin-top:8px">
          <div>
            <label>Producto</label>
            <select id="new_ins_prod" onchange="updateInsumoStockHint()"></select>
            <div class="muted" id="new_ins_stock">Stock: -</div>
          </div>
          <div>
            <label>Cantidad</label>
            <input type="number" step="0.01" id="new_ins_cant" value="1">
          </div>
          <div>
            <label>Depósito</label>
            <input id="new_ins_dep">
          </div>
          <div>
            <label>Lote</label>
            <input id="new_ins_lote">
          </div>
          <div style="flex:0">
            <button onclick="addInsumo()">Agregar</button>
          </div>
        </div>

        <div class="section-title">
          <span>Pedido y solicitudes</span>
          <button id="btn_generar_pedido" onclick="generarPedidoDesdeOT()">Generar pedido</button>
        </div>
        <div id="pedido_resumen" class="muted"></div>
        <div id="solicitudes_wrapper"></div>

        <div class="section-title">
          <span>Notas</span>
        </div>
        <textarea id="d_notas" placeholder="Observaciones internas..."></textarea>
        <div style="margin-top:8px">
          <button onclick="guardarNotas()" class="sec">Guardar notas</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-layer"><div id="toasts"></div></div>

<script>
const API = '../../servicios/ot/api_ot_.php';
const $ = (id)=>document.getElementById(id);

let catalogoServicios = [];
let catalogoInsumos = [];
let profesionales = [];
let otSeleccionada = null;
let items = [];
let insumos = [];
let reservasEncontradas = [];
let solicitudesCliente = [];
let solicitudSeleccionada = '';

function showToast(msg,type='ok'){
  const layer = $('toasts');
  const el = document.createElement('div');
  el.className = 'toast' + (type==='error'?' error':'');
  el.innerHTML = `<span>${msg}</span><button onclick="this.parentElement.remove()">×</button>`;
  layer.appendChild(el);
  setTimeout(()=>{ el.remove(); },3000);
}

async function api(op, payload={}){
  const res = await fetch(API,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({op, ...payload})
  });
  const data = await res.json();
  if(!data.success) throw new Error(data.error||'Error inesperado');
  return data;
}

async function loadCatalogos(){
  try{
    const data = await api('list_catalogos');
    catalogoServicios = data.servicios || [];
    catalogoInsumos   = data.insumos || [];
    renderSelectServicios();
    renderSelectInsumos();
  }catch(e){
    showToast(e.message,'error');
  }
}

function renderSelectServicios(){
  const sel = $('new_item_prod');
  sel.innerHTML = '';
  catalogoServicios.forEach(p=>{
    const opt = document.createElement('option');
    opt.value = p.id_producto;
    opt.textContent = `${p.tipo_item==='S'?'[Servicio]':'[Promo]'} ${p.nombre}`;
    opt.dataset.tipo = p.tipo_item;
    opt.dataset.precio = p.precio_unitario;
    opt.dataset.duracion = p.duracion_min;
    opt.dataset.iva = p.tipo_iva || 'EXE';
    sel.appendChild(opt);
  });

  if(sel.options.length>0){
    sel.selectedIndex = 0;
    const opt = sel.options[0];
    $('new_item_precio').value   = opt.dataset.precio;
    $('new_item_duracion').value = opt.dataset.duracion;
    $('new_item_cant').value     = 1;
    updateItemHint();
  }else{
    $('new_item_precio').value   = '';
    $('new_item_duracion').value = '';
    $('new_item_cant').value     = 1;
    $('hint_item').textContent   = '';
  }
}

function updateItemHint(){
  const hint = $('hint_item');
  const sel = $('new_item_prod');
  if(!hint || !sel || sel.options.length===0){ if(hint) hint.textContent=''; return; }
  const tipo = sel.selectedOptions[0].dataset.tipo;
  hint.textContent = tipo==='D' ? 'Este ítem es una promoción/descuento (precio negativo).' : '';
}
function renderSelectInsumos(){
  const sel = $('new_ins_prod');
  sel.innerHTML = '';
  catalogoInsumos.forEach(p=>{
    const opt = document.createElement('option');
    opt.value = p.id_producto;
    opt.textContent = `${p.nombre} (stock ${p.stock_actual})`;
    opt.dataset.stock = p.stock_actual;
    sel.appendChild(opt);
  });

  if(sel.options.length>0){
    sel.selectedIndex = 0;
  }
  updateInsumoStockHint();
}

function updateInsumoStockHint(){
  const info = $('new_ins_stock');
  const sel = $('new_ins_prod');
  if(!info || !sel || sel.options.length===0){
    if(info) info.textContent = 'Stock: -';
    return;
  }
  const stock = sel.selectedOptions[0].dataset.stock ?? '-';
  info.textContent = `Stock: ${stock}`;
}

async function loadProfesionales(){
  try{
    const r = await api('list_profesionales');
    profesionales = r.rows||[];
    const sel = $('d_profesional');
    sel.innerHTML = '<option value="">(Asignar más tarde)</option>';
    profesionales.forEach(p=>{
      const opt = document.createElement('option');
      opt.value = p.id_profesional;
      opt.textContent = p.nombre;
      sel.appendChild(opt);
    });
  }catch(e){
    showToast(e.message,'error');
  }
}

async function loadOTs(){
  try{
    const estado = $('f_estado').value;
    const fecha  = $('f_fecha').value;
    const data = await api('list_ot',{estado,fecha});
    const tbody = $('tbody_ot');
    tbody.innerHTML = '';
    data.rows.forEach(row=>{
      const tr = document.createElement('tr');
      tr.onclick = ()=>verDetalle(row.id_ot);
      tr.innerHTML = `
        <td>${row.id_ot}</td>
        <td>${row.fecha_programada || ''} ${row.hora_programada||''}</td>
        <td>${row.cliente||''}</td>
        <td>${row.estado||''}</td>
        <td>${row.id_reserva || '-'}</td>
      `;
      tbody.appendChild(tr);
    });
  }catch(e){
    showToast(e.message,'error');
  }
}

function resetFiltros(){
  $('f_estado').value='Todos';
  $('f_fecha').value='';
  loadOTs();
}

async function verDetalle(id_ot){
  try{
    const data = await api('get_ot',{id_ot});
    otSeleccionada = data.cab;
    items = data.det || [];
    insumos = data.insumos || [];
    pintarDetalle();
    await loadSolicitudesCliente();
    updatePedidoControls();
  }catch(e){
    showToast(e.message,'error');
  }
}

function pintarDetalle(){
  if(!otSeleccionada){
    $('detalle_vacio').style.display='';
    $('detalle_contenido').style.display='none';
    return;
  }
  $('detalle_vacio').style.display='none';
  $('detalle_contenido').style.display='';

  $('d_id_ot').value = otSeleccionada.id_ot;
  $('d_cliente').value = otSeleccionada.cliente_nombre || '';
  $('d_fecha_prog').value = otSeleccionada.fecha_programada || '';
  $('d_hora_prog').value = otSeleccionada.hora_programada || '';
  $('d_estado_badge').textContent = otSeleccionada.estado;
  $('d_inicio_real').value = otSeleccionada.inicio_real || '';
  $('d_fin_real').value = otSeleccionada.fin_real || '';
  $('d_notas').value = otSeleccionada.notas || '';
  $('d_id_pedido').value = otSeleccionada.id_pedido ? `#${otSeleccionada.id_pedido}` : '';

  const selProf = $('d_profesional');
  const actual = otSeleccionada.id_profesional;
  if(actual===null || actual===''){
    selProf.value='';
  }else{
    selProf.value = actual;
    if(selProf.value !== String(actual)){
      const opt = document.createElement('option');
      opt.value = actual;
      opt.textContent = otSeleccionada.profesional_nombre || `#${actual}`;
      selProf.appendChild(opt);
      selProf.value = actual;
    }
  }

  renderItems();
  renderInsumos();
  renderSolicitudes();
}

function renderItems(){
  const tbody = $('tbody_items');
  tbody.innerHTML = '';
  let total = 0;
  items.forEach(it=>{
    const subtotal = it.precio_unitario * it.cantidad;
    total += subtotal;
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${it.item_nro}</td>
      <td>${it.descripcion}</td>
      <td>${it.tipo_item==='S'?'Servicio':'Promo'}</td>
      <td><input type="number" step="0.1" value="${it.cantidad}" onchange="editItem(${it.item_nro},'cantidad',this.value)"></td>
      <td><input type="number" step="100" value="${it.precio_unitario}" onchange="editItem(${it.item_nro},'precio_unitario',this.value)"></td>
      <td>${subtotal.toLocaleString('es-PY')}</td>
      <td><input value="${it.tipo_iva}" maxlength="3" style="width:60px" onchange="editItem(${it.item_nro},'tipo_iva',this.value)"></td>
      <td><input type="number" value="${it.duracion_min}" style="width:70px" onchange="editItem(${it.item_nro},'duracion_min',this.value)"></td>
      <td>
        <button class="sec" onclick="guardarItem(${it.item_nro})">Guardar</button>
        <button class="danger" onclick="deleteItem(${it.item_nro})">×</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
  $('total_ot_servicios').textContent = `Gs ${total.toLocaleString('es-PY')}`;
  updatePedidoControls();
}

function renderInsumos(){
  const tbody = $('tbody_insumos');
  tbody.innerHTML = '';
  insumos.forEach(ins=>{
    const prod = catalogoInsumos.find(p=>p.id_producto===ins.id_producto);
    const stock = prod ? prod.stock_actual : '-';
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${ins.item_nro}</td>
      <td>${ins.id_producto}</td>
      <td>${stock}</td>
      <td><input type="number" step="0.01" value="${ins.cantidad}" onchange="editInsumo(${ins.item_nro},'cantidad',this.value)"></td>
      <td><input value="${ins.deposito||''}" onchange="editInsumo(${ins.item_nro},'deposito',this.value)"></td>
      <td><input value="${ins.lote||''}" onchange="editInsumo(${ins.item_nro},'lote',this.value)"></td>
      <td>
        <button class="sec" onclick="guardarInsumo(${ins.item_nro})">Guardar</button>
        <button class="danger" onclick="deleteInsumo(${ins.item_nro})">×</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

function editItem(item_nro, campo, valor){
  const item = items.find(x=>x.item_nro===item_nro);
  if(!item) return;
  if(campo==='cantidad' || campo==='precio_unitario' || campo==='duracion_min'){
    item[campo] = Number(valor);
  }else{
    item[campo] = valor;
  }
}

async function guardarItem(item_nro){
  const item = items.find(x=>x.item_nro===item_nro);
  if(!item){ showToast('Ítem no encontrado','error'); return; }
  try{
    await api('save_ot_item',{
      id_ot: otSeleccionada.id_ot,
      item_nro: item.item_nro,
      id_producto: item.id_producto,
      descripcion: item.descripcion,
      tipo_item: item.tipo_item,
      cantidad: item.cantidad,
      precio_unitario: item.precio_unitario,
      tipo_iva: item.tipo_iva,
      duracion_min: item.duracion_min,
      observaciones: item.observaciones
    });
    showToast('Ítem guardado');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

async function deleteItem(item_nro){
  if(!confirm('¿Eliminar ítem?')) return;
  try{
    await api('delete_ot_item',{id_ot: otSeleccionada.id_ot, item_nro});
    showToast('Ítem eliminado');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

function addItem(){
  if(!otSeleccionada){ showToast('Seleccioná una OT primero','error'); return; }
  const sel = $('new_item_prod');
  if(!sel || sel.options.length===0){ showToast('No hay productos cargados','error'); return; }

  const opt = sel.selectedOptions?.[0] ?? sel.options[sel.selectedIndex] ?? sel.options[0];
  if(!opt){ showToast('Seleccioná un servicio o promoción','error'); return; }

  const id = Number(opt.value);
  const prod = catalogoServicios.find(x=>x.id_producto===id);
  if(!prod){ showToast('Producto no encontrado','error'); return; }

  const cant   = Number($('new_item_cant').value   || 1);
  const precio = Number($('new_item_precio').value || opt.dataset.precio || 0);
  const dur    = Number($('new_item_duracion').value || opt.dataset.duracion || 0);
  const iva    = prod.tipo_iva || opt.dataset.iva || 'EXE';

  api('save_ot_item',{
    id_ot: otSeleccionada.id_ot,
    id_producto: prod.id_producto,
    descripcion: prod.nombre,
    tipo_item: prod.tipo_item,
    cantidad: cant,
    precio_unitario: precio,
    tipo_iva: iva,
    duracion_min: prod.tipo_item === 'S' ? dur : 0
  }).then(()=>{
    showToast('Ítem agregado');
    $('new_item_cant').value     = 1;
    $('new_item_precio').value   = opt.dataset.precio || '';
    $('new_item_duracion').value = opt.dataset.duracion || '';
    updateItemHint();
    verDetalle(otSeleccionada.id_ot);
  }).catch(e=>showToast(e.message,'error'));
}

function editInsumo(item_nro, campo, valor){
  const item = insumos.find(x=>x.item_nro===item_nro);
  if(!item) return;
  if(campo==='cantidad'){
    item[campo] = Number(valor);
  }else{
    item[campo] = valor;
  }
}

async function guardarInsumo(item_nro){
  const item = insumos.find(x=>x.item_nro===item_nro);
  if(!item){ showToast('Insumo no encontrado','error'); return; }
  try{
    await api('save_ot_insumo',{
      id_ot: otSeleccionada.id_ot,
      item_nro: item.item_nro,
      id_producto: item.id_producto,
      cantidad: item.cantidad,
      deposito: item.deposito,
      lote: item.lote,
      comentario: item.comentario
    });
    showToast('Insumo guardado');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

async function deleteInsumo(item_nro){
  if(!confirm('¿Eliminar insumo?')) return;
  try{
    await api('delete_ot_insumo',{id_ot: otSeleccionada.id_ot, item_nro});
    showToast('Insumo eliminado');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

function addInsumo(){
  if(!otSeleccionada){ showToast('Seleccioná una OT primero','error'); return; }
  const sel = $('new_ins_prod');
  if(sel.options.length===0){ showToast('No hay insumos cargados','error'); return; }
  const id = Number(sel.value);
  const cant = Number($('new_ins_cant').value||1);
  api('save_ot_insumo',{
    id_ot: otSeleccionada.id_ot,
    id_producto: id,
    cantidad: cant,
    deposito: $('new_ins_dep').value || null,
    lote: $('new_ins_lote').value || null
  }).then(()=>{
    showToast('Insumo agregado');
    $('new_ins_cant').value=1;
    $('new_ins_dep').value='';
    $('new_ins_lote').value='';
    updateInsumoStockHint();
    verDetalle(otSeleccionada.id_ot);
  }).catch(e=>showToast(e.message,'error'));
}

async function crearFromReserva(idManual){
  let id_reserva = idManual !== undefined ? idManual : Number($('new_id_reserva').value||0);
  if(id_reserva<=0){ showToast('Indicá el ID de reserva','error'); return; }
  try{
    const res = await api('create_from_reserva',{id_reserva});
    showToast(`OT creada (ID ${res.id_ot})`);
    $('new_id_reserva').value='';
    loadOTs();
    setTimeout(()=>verDetalle(res.id_ot),300);
  }catch(e){ showToast(e.message,'error'); }
}

async function buscarReservas(){
  try{
    const q = $('sr_q').value.trim();
    const fecha = $('sr_fecha').value;
    const estado = 'Confirmada';
    const data = await api('search_reservas',{q,fecha,estado});
    reservasEncontradas = data.rows||[];
    renderReservas();
  }catch(e){
    showToast(e.message,'error');
  }
}

function renderReservas(){
  const tbody = $('tbody_reservas');
  tbody.innerHTML = '';
  reservasEncontradas.forEach(res=>{
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${res.id_reserva}</td>
      <td>${res.inicio_ts ? res.inicio_ts.substring(0,16) : ''}</td>
      <td>${res.cliente||''}</td>
      <td>${res.profesional||'-'}</td>
      <td>${res.estado||''}</td>
      <td><button onclick="crearFromReserva(${res.id_reserva})">Crear OT</button></td>
    `;
    tbody.appendChild(tr);
  });
  if(reservasEncontradas.length===0){
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="6" class="muted">Sin resultados, probá con otro filtro.</td>`;
    tbody.appendChild(tr);
  }
}

async function guardarNotas(){
  if(!otSeleccionada) return;
  try{
    await api('update_ot_notas',{id_ot: otSeleccionada.id_ot, notas: $('d_notas').value});
    showToast('Notas actualizadas');
  }catch(e){ showToast(e.message,'error'); }
}

async function changeProfesional(){
  if(!otSeleccionada) return;
  const id_prof = $('d_profesional').value || null;
  try{
    await api('update_ot_prof',{id_ot: otSeleccionada.id_ot, id_profesional: id_prof ? Number(id_prof) : null});
    showToast('Profesional actualizado');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

async function marcarEnEjecucion(){
  if(!otSeleccionada) return;
  try{
    await api('update_ot_state',{id_ot: otSeleccionada.id_ot, estado:'En ejecución'});
    showToast('OT en ejecución');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

async function marcarCompletada(){
  if(!otSeleccionada) return;
  try{
    await api('update_ot_state',{id_ot: otSeleccionada.id_ot, estado:'Completada', finalizar_con_fecha:true});
    showToast('OT completada');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

async function marcarCancelada(){
  if(!otSeleccionada) return;
  if(!confirm('¿Cancelar la OT?')) return;
  try{
    await api('update_ot_state',{id_ot: otSeleccionada.id_ot, estado:'Cancelada'});
    showToast('OT cancelada');
    verDetalle(otSeleccionada.id_ot);
  }catch(e){ showToast(e.message,'error'); }
}

async function loadSolicitudesCliente(){
  solicitudesCliente = [];
  solicitudSeleccionada = '';
  if(!otSeleccionada) return;
  try{
    const data = await api('list_solicitudes_cliente',{id_ot: otSeleccionada.id_ot});
    solicitudesCliente = data.rows || [];
    renderSolicitudes();
  }catch(e){
    showToast(e.message,'error');
  }
}

function renderSolicitudes(){
  const cont = $('solicitudes_wrapper');
  if(!cont) return;
  cont.innerHTML = '';
  if(!otSeleccionada){
    updatePedidoControls();
    return;
  }

  if(!Array.isArray(solicitudesCliente)){
    solicitudesCliente = [];
  }

  if(otSeleccionada.id_pedido){
    cont.innerHTML = '<div class="muted">La OT ya cuenta con un pedido asociado.</div>';
    updatePedidoControls();
    return;
  }

  const list = document.createElement('div');
  list.className = 'solicitud-list';

  const noneLabel = document.createElement('label');
  noneLabel.className = 'solicitud-card';
  noneLabel.innerHTML = `
    <input type="radio" name="solicitud_sel" value="" ${solicitudSeleccionada===''?'checked':''}>
    <div>
      <strong>Sin solicitud</strong>
      <div class="solicitud-items">Generar pedido solo con los ítems de la OT.</div>
    </div>
  `;
  noneLabel.querySelector('input').addEventListener('change',()=>{
    solicitudSeleccionada = '';
    updatePedidoControls();
  });
  list.appendChild(noneLabel);

  solicitudesCliente.forEach(sol=>{
    const descripcion = sol.items && sol.items.length
      ? sol.items.map(it=>`<span>${it.cantidad}× ${it.nombre||('#'+it.id_producto)}</span>`).join('')
      : '<span>Sin ítems cargados</span>';
    const card = document.createElement('label');
    card.className = 'solicitud-card';
    card.innerHTML = `
      <input type="radio" name="solicitud_sel" value="${sol.id_solicitud}" ${solicitudSeleccionada===String(sol.id_solicitud)?'checked':''}>
      <div>
        <strong>Solicitud #${sol.id_solicitud} • ${sol.estado}</strong>
        <div class="solicitud-items">${descripcion}</div>
        ${sol.notas ? `<div class="muted" style="margin-top:4px">${sol.notas}</div>` : ''}
      </div>
    `;
    card.querySelector('input').addEventListener('change',()=>{
      solicitudSeleccionada = String(sol.id_solicitud);
      updatePedidoControls();
    });
    list.appendChild(card);
  });

  cont.appendChild(list);
  updatePedidoControls();
}

function updatePedidoControls(){
  const resumen = $('pedido_resumen');
  const btn = $('btn_generar_pedido');
  if(!otSeleccionada || !resumen || !btn){
    return;
  }

  const hasPedido = !!otSeleccionada.id_pedido;
  const hasItemsConProducto = items.some(it=>it.id_producto);
  const haySolicitudSeleccionada = solicitudSeleccionada !== '';

  if(hasPedido){
    resumen.textContent = `Pedido asociado: #${otSeleccionada.id_pedido}.`;
    btn.disabled = true;
    btn.textContent = 'Pedido generado';
    return;
  }

  resumen.textContent = 'Sin pedido asociado. Seleccioná una solicitud si querés anexarla o generá solo con los ítems de la OT.';
  btn.textContent = 'Generar pedido';
  btn.disabled = !(hasItemsConProducto || haySolicitudSeleccionada);
}

async function generarPedidoDesdeOT(){
  if(!otSeleccionada) return;
  const btn = $('btn_generar_pedido');
  if(btn.disabled) return;

  const payload = { id_ot: otSeleccionada.id_ot };
  if(solicitudSeleccionada !== ''){
    payload.id_solicitud = Number(solicitudSeleccionada);
  }

  try{
    btn.disabled = true;
    btn.textContent = 'Generando...';
    const res = await api('create_pedido_from_ot', payload);
    showToast(`Pedido generado #${res.id_pedido}`);
    await loadOTs();
    await verDetalle(otSeleccionada.id_ot);
  }catch(e){
    showToast(e.message,'error');
    btn.disabled = false;
    btn.textContent = 'Generar pedido';
  }
}

window.addEventListener('DOMContentLoaded', async ()=>{
  await loadCatalogos();
  await loadProfesionales();
  await loadOTs();
});
</script>
</body>
</html>
