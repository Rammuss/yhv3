// oc_generar.js
const $ = s => document.querySelector(s);

let lineas = []; // [{id_presupuesto_detalle, id_producto, producto, precio_unitario, cantidad_aprobada, cantidad_oc}]

document.addEventListener('DOMContentLoaded', () => {
  cargarPedidos();

  $('#pedido').addEventListener('change', onPedidoChange);
  $('#proveedor').addEventListener('change', onProveedorChange);
  $('#btnCargar').addEventListener('click', cargarLineasAprobadas);
  $('#btnGenerar').addEventListener('click', generarOC);
});

async function cargarPedidos(){
  try{
    // 👇 Agregamos el scope=presupuesto para filtrar
    const res = await fetch('pedidos_options.php?scope=presupuesto');
    const data = await res.json();
    const sel = $('#pedido');
    sel.innerHTML = '<option value="">Selecciona un pedido</option>';
    data.forEach(r=>{
      const opt = document.createElement('option');
      opt.value = r.numero_pedido;
      opt.textContent = `#${r.numero_pedido} - ${r.departamento_solicitante} (${r.estado})`;
      sel.appendChild(opt);
    });
  }catch(e){
    console.error(e);
    alert('Error cargando pedidos');
  }
}


async function onPedidoChange(){
  $('#proveedor').innerHTML = '<option value="">Cargando proveedores...</option>';
  $('#proveedor').disabled = true;
  $('#btnCargar').disabled = true;
  $('#panelLineas').innerHTML = '<div class="muted">Seleccioná un proveedor…</div>';
  $('#btnGenerar').disabled = true;
  lineas = [];
  actualizarTotal();

  const pedido = $('#pedido').value;
  if(!pedido){ 
    $('#proveedor').innerHTML = '<option value="">Seleccioná un pedido</option>';
    return;
  }

  // Detectar proveedores con líneas APROBADAS leyendo listar_presupuestos.php
  try{
    const url = `listar_presupuestos.php?numero_pedido=${encodeURIComponent(pedido)}`;
    const res = await fetch(url);
    const json = await res.json();
    if(!json.ok){ throw new Error('listar_presupuestos fallo'); }

    const proveedores = new Map(); // id_proveedor -> nombre
    (json.data || []).forEach(p => {
      const tieneAprobadas = (p.lineas || []).some(l => l.estado_detalle === 'Aprobado' && Number(l.cantidad_aprobada) > 0);
      if (tieneAprobadas) proveedores.set(p.id_proveedor, p.proveedor || p.id_proveedor);
    });

    const selProv = $('#proveedor');
    selProv.innerHTML = '<option value="">Selecciona un proveedor</option>';
    proveedores.forEach((nombre, id) => {
      const opt = document.createElement('option');
      opt.value = id;
      opt.textContent = nombre;
      selProv.appendChild(opt);
    });

    selProv.disabled = proveedores.size === 0;
    $('#btnCargar').disabled = true;
    if (proveedores.size === 0) {
      $('#panelLineas').innerHTML = '<div class="muted">Este pedido no tiene líneas aprobadas aún.</div>';
    }
  }catch(e){
    console.error(e);
    alert('Error cargando proveedores del pedido');
  }
}

function onProveedorChange(){
  const ok = $('#proveedor').value !== '';
  $('#btnCargar').disabled = !ok;
  $('#panelLineas').innerHTML = ok ? '<div class="muted">Hacé clic en "Cargar líneas aprobadas".</div>' : '<div class="muted">Seleccioná un proveedor…</div>';
  $('#btnGenerar').disabled = true;
  lineas = [];
  actualizarTotal();
}

async function cargarLineasAprobadas(){
  const pedido = $('#pedido').value;
  const prov   = $('#proveedor').value;
  if(!pedido || !prov){ alert('Elegí pedido y proveedor'); return; }

  $('#panelLineas').innerHTML = 'Cargando…';
  try{
    const url = `oc_lineas_aprobadas.php?numero_pedido=${encodeURIComponent(pedido)}&id_proveedor=${encodeURIComponent(prov)}`;
    const res = await fetch(url);
    const json = await res.json();
    if(!json.ok){ throw new Error('oc_lineas_aprobadas fallo'); }

    lineas = (json.data || []).map(x => ({
      id_presupuesto_detalle: Number(x.id_presupuesto_detalle),
      id_producto: Number(x.id_producto),
      producto: x.producto,
      precio_unitario: Number(x.precio_unitario) || 0,
      cantidad_aprobada: Number(x.cantidad_aprobada) || 0,
      cantidad_oc: Number(x.cantidad_aprobada) || 0 // por defecto, todo
    }));

    renderTabla();
    $('#btnGenerar').disabled = lineas.length === 0;
  }catch(e){
    console.error(e);
    $('#panelLineas').innerHTML = '<div class="muted">Error al cargar líneas aprobadas.</div>';
  }
}

function renderTabla(){
  if (lineas.length === 0){
    $('#panelLineas').innerHTML = '<div class="muted">Sin líneas aprobadas para este proveedor.</div>';
    actualizarTotal();
    return;
  }
  const rows = lineas.map((l, idx) => `
    <tr>
      <td>${l.id_producto}</td>
      <td>${escapeHtml(l.producto)}</td>
      <td class="right">${l.cantidad_aprobada}</td>
      <td class="right"><input type="number" min="0" max="${l.cantidad_aprobada}" value="${l.cantidad_oc}" style="width:90px" oninput="cambiarCant(${idx}, this.value)"></td>
      <td class="right">${l.precio_unitario.toFixed(2)}</td>
      <td class="right" id="lineTotal-${idx}">${(l.cantidad_oc * l.precio_unitario).toFixed(2)}</td>
    </tr>
  `).join('');

  $('#panelLineas').innerHTML = `
    <table>
      <thead>
        <tr>
          <th>ID Prod</th>
          <th>Producto</th>
          <th>Aprobada</th>
          <th>A comprar</th>
          <th>P. Unit</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;
  actualizarTotal();
}

function cambiarCant(idx, val){
  const n = Math.max(0, Number(val) || 0);
  const max = lineas[idx].cantidad_aprobada;
  lineas[idx].cantidad_oc = Math.min(n, max);
  const total = lineas[idx].cantidad_oc * lineas[idx].precio_unitario;
  const cell = document.getElementById(`lineTotal-${idx}`);
  if (cell) cell.textContent = total.toFixed(2);
  actualizarTotal();
}

function actualizarTotal(){
  const t = lineas.reduce((acc,l) => acc + (l.cantidad_oc*l.precio_unitario), 0);
  $('#totalOC').textContent = t.toLocaleString('es-PY', {minimumFractionDigits:2, maximumFractionDigits:2});
  $('#btnGenerar').disabled = !(lineas.some(l => l.cantidad_oc > 0));
}

async function generarOC(){
  const pedido = $('#pedido').value;
  const prov   = $('#proveedor').value;
  const obs    = $('#observacion').value.trim();

  const seleccionadas = lineas.filter(l => l.cantidad_oc > 0);
  if (seleccionadas.length === 0){
    alert('No hay cantidades a comprar.');
    return;
  }

  const fd = new FormData();
  fd.append('numero_pedido', pedido);
  fd.append('id_proveedor', prov);
  if (obs) fd.append('observacion', obs);
  seleccionadas.forEach(l => {
    fd.append('id_presupuesto_detalle[]', l.id_presupuesto_detalle);
    fd.append('cantidad[]', l.cantidad_oc);
  });

  try{
    const res = await fetch('generar_oc.php', { method:'POST', body: fd });
    const json = await res.json();
    if (!res.ok || !json.ok){
      alert('No se pudo generar la OC: ' + (json.error || ''));
      return;
    }
    const idoc = json.id_oc;
    alert('OC generada. ID: ' + idoc);

    // mostrar un pequeño resumen de OCs del pedido
    await mostrarOCsDelPedido(pedido);
    // limpiar selección de líneas
    lineas = [];
    renderTabla();
  }catch(e){
    console.error(e);
    alert('Error al generar OC');
  }
}

async function mostrarOCsDelPedido(pedido){
  try{
    const r = await fetch(`oc_listar.php?numero_pedido=${encodeURIComponent(pedido)}`);
    const json = await r.json();
    if (!json.ok) return;
    const arr = json.data || [];
    if (arr.length === 0){
      $('#resultado').innerHTML = '';
      return;
    }
    const html = arr.map(oc => {
      const det = (oc.det||[]).map(d => `
        <tr>
          <td>${d.id_producto}</td>
          <td>${escapeHtml(d.producto)}</td>
          <td class="right">${d.cantidad}</td>
          <td class="right">${Number(d.precio_unit).toFixed(2)}</td>
          <td class="right">${(Number(d.cantidad)*Number(d.precio_unit)).toFixed(2)}</td>
        </tr>
      `).join('');
      const total = (oc.det||[]).reduce((acc,d)=> acc + Number(d.cantidad)*Number(d.precio_unit), 0);
      return `
        <div class="card">
          <div class="card-header">
            <div><b>OC #${oc.id_oc}</b> — ${oc.proveedor || oc.id_proveedor}</div>
            <div class="muted">Fecha: ${oc.fecha_emision || ''} • Estado: ${oc.estado}</div>
          </div>
          <div class="card-body">
            <table>
              <thead><tr><th>ID Prod</th><th>Producto</th><th class="right">Cant</th><th class="right">P.Unit</th><th class="right">Total</th></tr></thead>
              <tbody>${det}</tbody>
            </table>
            <div class="total">Total OC: ${total.toLocaleString('es-PY',{minimumFractionDigits:2, maximumFractionDigits:2})}</div>
            ${oc.observacion ? `<div class="muted" style="margin-top:6px">Obs: ${escapeHtml(oc.observacion)}</div>` : ''}
          </div>
        </div>
      `;
    }).join('');
    $('#resultado').innerHTML = html;
  }catch(e){
    console.error(e);
  }
}

function escapeHtml(s){
  if (s == null) return '';
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
