console.log("se cargo tabla_pedidos_interno.js");

// Carga datos con filtro opcional de estado
function cargarDatos() {
  const estado = document.getElementById('filter-status')
                ? document.getElementById('filter-status').value
                : '';

  const params = new URLSearchParams();
  if (estado) params.set('estado', estado);

  const url = 'consulta_pedidos.php' + (params.toString() ? `?${params.toString()}` : '');

  fetch(url)
    .then(response => {
      if (!response.ok) throw new Error('Error al cargar los datos.');
      return response.json();
    })
    .then(pedidos => {
      mostrarPedidosEnTabla(pedidos);
    })
    .catch(error => {
      console.error('Error:', error);
    });
}

// Pinta la tabla (limpiando antes)
function mostrarPedidosEnTabla(pedidos) {
  const tablaBody = document.querySelector('#tablaPedidos tbody');
  tablaBody.innerHTML = ''; // LIMPIAR

  pedidos.forEach(pedido => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${pedido.numero_pedido}</td>
      <td>${pedido.departamento_solicitante}</td>
      <td>${pedido.telefono ?? ''}</td>
      <td>${pedido.correo ?? ''}</td>
      <td>${pedido.fecha_pedido ?? ''}</td>
      <td>${pedido.fecha_entrega_solicitada ?? ''}</td>
      <td>${pedido.estado ?? ''}</td>
      <td>
        <button class="button-delete" onclick="modificarPedido(${pedido.numero_pedido})">Modificar</button>
        <button class="button-delete" onclick="anularPedido(${pedido.numero_pedido})">Eliminar</button>
      </td>
    `;
    tablaBody.appendChild(fila);
  });
}

// Soft delete con motivo (POST)
function anularPedido(numero_pedido) {
  if (!confirm('¿Estás seguro de que deseas ANULAR este pedido?')) return;
  const motivo = prompt('Motivo de la anulación:');
  if (motivo === null) return;

  const fd = new FormData();
  fd.append('numero_pedido', numero_pedido);
  fd.append('motivo', motivo);

  fetch('../menu/eliminar_pedido_interno.php', { // <- tu endpoint de anulación (POST)
    method: 'POST',
    body: fd
  })
  .then(r => { if (!r.ok) throw new Error('No se pudo anular el pedido'); return r.text(); })
  .then(msg => { alert(msg); cargarDatos(); })  // recarga la tabla sin refrescar toda la página
  .catch(err => { console.error(err); alert('Error al anular el pedido'); });
}

// Inicialización
window.onload = function () {
  // 1) Cargar al abrir
  cargarDatos();

  // 2) Si existe el select de estado, escuchar cambio
  const sel = document.getElementById('filter-status');
  if (sel) sel.addEventListener('change', cargarDatos);
};
