// tabla_pedidos_interno.js (versión con filtros + anular seguro)
console.log("se cargo tabla_pedidos_interno.js");

async function cargarDatos(){
  const qp = new URLSearchParams();
  const estado = document.getElementById('filter-status')?.value || '';
  const pedido = document.getElementById('filter-pedido')?.value || '';
  const depto  = document.getElementById('filter-depto')?.value || '';
  const desde  = document.getElementById('filter-desde')?.value || '';
  const hasta  = document.getElementById('filter-hasta')?.value || '';

  if (estado) qp.set('estado', estado);
  if (pedido) qp.set('numero_pedido', pedido);
  if (depto)  qp.set('departamento', depto);
  if (desde)  qp.set('desde', desde);
  if (hasta)  qp.set('hasta', hasta);

  try{
    const r = await fetch('pedidos_listar.php' + (qp.toString() ? ('?'+qp.toString()) : ''));
    const json = await r.json();
    if (!json.ok) throw new Error(json.error || 'Error listando');
    mostrarPedidosEnTabla(json.data || []);
  }catch(e){
    console.error(e);
    alert('Error al cargar pedidos');
  }
}

function mostrarPedidosEnTabla(pedidos){
  const tbody = document.querySelector('#tablaPedidos tbody');
  tbody.innerHTML = '';
  if (!pedidos.length){
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="9" class="muted">Sin resultados</td>`;
    tbody.appendChild(tr);
    return;
  }

  pedidos.forEach(p => {
    const tr = document.createElement('tr');
    const puedeAnular = (p.estado !== 'Anulado') && (Number(p.ocs_activas) === 0);
    tr.innerHTML = `
      <td>${p.numero_pedido}</td>
      <td>${p.departamento_solicitante}</td>
      <td>${p.telefono || ''}</td>
      <td>${p.correo || ''}</td>
      <td>${p.fecha_pedido || ''}</td>
      <td>${p.fecha_entrega_solicitada || ''}</td>
      <td>${p.estado}</td>
      <td>Pres: ${p.presup_activos}/${p.presup_total} • OCs: ${p.ocs_activas}/${p.ocs_total}</td>
      <td>
        <button class="button-delete" onclick="modificarPedido(${p.numero_pedido})">Modificar</button>
        <button class="button-delete" ${puedeAnular ? '' : 'disabled title="No se puede anular: hay OCs activas o ya está anulado"'}
          onclick="anularPedidoSeguro(${p.numero_pedido})">Anular</button>
      </td>
    `;
    tbody.appendChild(tr);
  });
}

// Anular con validación + motivo + anulación en cascada de presupuestos (seguro porque no hay OCs activas)
async function anularPedidoSeguro(numero_pedido){
  if (!confirm(`¿Anular el pedido #${numero_pedido}?`)) return;
  const motivo = prompt('Motivo de la anulación (opcional):') || '';
  const fd = new FormData();
  fd.append('numero_pedido', numero_pedido);
  fd.append('motivo', motivo);
  fd.append('anular_presupuestos', '1'); // cascada (seguro: sin OCs activas)

  try{
    const r = await fetch('pedido_anular.php', { method:'POST', body: fd });
    const json = await r.json();
    if (!r.ok || !json.ok) throw new Error(json.error || 'No se pudo anular');
    alert(json.msg || 'Pedido anulado');
    cargarDatos();
  }catch(e){
    alert(e.message || 'Error al anular');
  }
}

// hooks
window.onload = cargarDatos;
// si tenés selects/inputs de filtro, agregales change -> cargarDatos();
