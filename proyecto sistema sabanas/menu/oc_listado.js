// oc_listado.js
const $ = s => document.querySelector(s);

document.addEventListener('DOMContentLoaded', () => {
  setFechasPorDefecto();
  $('#btnBuscar').addEventListener('click', buscar);
  $('#btnLimpiar').addEventListener('click', limpiar);
  $('#md_close').addEventListener('click', cerrarModal);
  document.getElementById('modalDetalle').addEventListener('click', (e)=>{
    if (e.target.id === 'modalDetalle') cerrarModal();
  });

  buscar(); // primera carga
});

function setFechasPorDefecto(){
  // opcional: setear el mes actual
  const hoy = new Date();
  const primero = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().slice(0,10);
  const ultimo  = new Date(hoy.getFullYear(), hoy.getMonth()+1, 0).toISOString().slice(0,10);
  const d = $('#f_desde'); if (d) d.value = primero;
  const h = $('#f_hasta'); if (h) h.value = ultimo;
}

function limpiar(){
  $('#f_pedido').value = '';
  $('#f_proveedor').value = '';
  $('#f_estado').value = '';
  $('#f_desde').value = '';
  $('#f_hasta').value = '';
  buscar();
}

async function buscar(){
  const qp = new URLSearchParams();
  const np = $('#f_pedido').value.trim();
  const ip = $('#f_proveedor').value.trim();
  const es = $('#f_estado').value.trim();
  const de = $('#f_desde').value.trim();
  const ha = $('#f_hasta').value.trim();

  if (np) qp.set('numero_pedido', np);
  if (ip) qp.set('id_proveedor', ip);
  if (es) qp.set('estado', es);
  if (de) qp.set('desde', de);
  if (ha) qp.set('hasta', ha);

  const url = 'oc_listar_lista.php' + (qp.toString() ? ('?' + qp.toString()) : '');
  try{
    const r = await fetch(url);
    const json = await r.json();
    if (!json.ok) throw new Error(json.error || 'Error');

    renderTabla(json.data || []);
  }catch(e){
    console.error(e);
    alert('Error al listar OCs');
  }
}

function renderTabla(rows){
  const tb = $('#tabla tbody');
  tb.innerHTML = '';
  if (!rows.length){
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="7" class="muted">Sin resultados</td>`;
    tb.appendChild(tr);
    return;
  }

  rows.forEach(oc => {
    const tr = document.createElement('tr');
    const total = Number(oc.total_oc || 0);
    tr.innerHTML = `
      <td>${oc.id_oc}</td>
      <td>${oc.numero_pedido}</td>
      <td>${escapeHtml(oc.proveedor || oc.id_proveedor)}</td>
      <td>${oc.fecha_emision || ''}</td>
      <td>${oc.estado}</td>
      <td class="right">${total.toLocaleString('es-PY', {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
      <td class="actions">
        <button class="btn" onclick="verOC(${oc.id_oc})">Ver</button>
        ${oc.estado !== 'Anulada' ? `<button class="btn" onclick="anularOC(${oc.id_oc})">Anular</button>` : ''}
        <button class="btn" onclick="imprimirOC(${oc.id_oc})">Imprimir</button>
      </td>
    `;
    tb.appendChild(tr);
  });
}

async function verOC(id_oc){
  try{
    const r = await fetch('oc_get.php?id_oc='+encodeURIComponent(id_oc));
    const json = await r.json();
    if (!json.ok) throw new Error(json.error || 'Error');

    const oc = json.data;

    const detRows = (oc.det || []).map((d,i)=>{
      const lt = Number(d.cantidad)*Number(d.precio_unit);
      return `
        <tr>
          <td>${i+1}</td>
          <td>${d.id_producto}</td>
          <td>${escapeHtml(d.producto)}</td>
          <td class="right">${Number(d.cantidad)}</td>
          <td class="right">${Number(d.precio_unit).toFixed(2)}</td>
          <td class="right">${lt.toFixed(2)}</td>
        </tr>
      `;
    }).join('');

    $('#md_title').textContent = `OC #${oc.id_oc} — ${oc.proveedor || oc.id_proveedor}`;
    $('#md_body').innerHTML = `
      <div style="display:flex; gap:10px; margin-bottom:10px">
        <div style="flex:1">
          <div><b>Pedido:</b> #${oc.numero_pedido}</div>
          <div><b>Fecha:</b> ${oc.fecha_emision || ''}</div>
          <div><b>Estado:</b> ${oc.estado}</div>
        </div>
        <div style="flex:1">
          ${oc.observacion ? `<div><b>Obs:</b> ${escapeHtml(oc.observacion)}</div>` : ''}
          <div style="text-align:right; font-weight:bold; margin-top:6px">Total: ${Number(oc.total_oc).toLocaleString('es-PY',{minimumFractionDigits:2, maximumFractionDigits:2})}</div>
        </div>
      </div>
      <table style="width:100%">
        <thead>
          <tr><th>#</th><th>ID Prod</th><th>Producto</th><th class="right">Cant</th><th class="right">P.Unit</th><th class="right">Total</th></tr>
        </thead>
        <tbody>${detRows}</tbody>
      </table>
    `;
    abrirModal();
  }catch(e){
    console.error(e);
    alert('Error al obtener la OC');
  }
}

function imprimirOC(id_oc){
  window.open('oc_print.php?id_oc='+encodeURIComponent(id_oc), '_blank');
}

async function anularOC(id_oc){
  if (!confirm(`¿Anular la OC #${id_oc}?`)) return;
  const motivo = prompt('Motivo de anulación (opcional):') || '';
  const devolver = confirm('¿Devolver aprobaciones usadas por esta OC para poder reutilizarlas? Aceptar = Sí / Cancelar = No') ? 1 : 0;

  const fd = new FormData();
  fd.append('id_oc', id_oc);
  fd.append('motivo', motivo);
  fd.append('devolver_aprobaciones', devolver);

  try{
    const r = await fetch('oc_anular.php', { method:'POST', body: fd });
    const json = await r.json();
    if (!r.ok || !json.ok) throw new Error(json.error || 'Error al anular');
    alert('OC anulada correctamente');
    buscar(); // refrescar lista
  }catch(e){
    console.error(e);
    alert(e.message || 'No se pudo anular la OC');
  }
}

/* Modal helpers */
function abrirModal(){ $('#modalDetalle').style.display = 'flex'; }
function cerrarModal(){ $('#modalDetalle').style.display = 'none'; }

/* Escapes */
function escapeHtml(s){
  if (s == null) return '';
  return String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
