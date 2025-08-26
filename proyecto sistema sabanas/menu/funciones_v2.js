// funciones_v2.js
console.log("se cargo funciones.js");

/* ========== HELPERS DE STOCK ========== */

// Trae stock de UN producto y actualiza la celda correspondiente de la fila
async function cargarStockFila(tr, idProducto) {
  const celdaStock = tr.querySelector('.celda-stock');
  if (!celdaStock) return;

  celdaStock.textContent = '…'; // indicador de carga
  try {
    const res = await fetch(`stock.php?product_id=${encodeURIComponent(idProducto)}`);
    const json = await res.json();
    if (json.ok && json.data) {
      const val = Number(json.data.stock_actual ?? 0);
      celdaStock.textContent = val;
      // Estilo suave cuando hay stock
      celdaStock.style.background = val > 0 ? '#fff3cd' : '';
      celdaStock.style.padding = '2px 6px';
      celdaStock.style.borderRadius = '6px';
    } else {
      celdaStock.textContent = '0';
    }
  } catch (e) {
    console.error('Error stock fila', e);
    celdaStock.textContent = '0';
  }
}

// Refresca stock para TODAS las filas visibles (llamada batch)
async function refrescarStocksVisibles() {
  const filas = document.querySelectorAll('#productos tr, #tbodyModificar tr');
  const ids = [];
  filas.forEach(tr => {
    const id = tr.dataset?.id;
    if (id) ids.push(id);
  });
  if (ids.length === 0) return;

  try {
    const res = await fetch(`stock.php?ids=${ids.join(',')}`);
    const json = await res.json();
    if (!json.ok) return;

    const mapa = {};
    json.data.forEach(p => {
      mapa[String(p.id_producto)] = Number(p.stock_actual || 0);
    });

    filas.forEach(tr => {
      const id = tr.dataset?.id;
      const celdaStock = tr.querySelector('.celda-stock');
      if (id && celdaStock) {
        const val = mapa[id] ?? 0;
        celdaStock.textContent = val;
        celdaStock.style.background = val > 0 ? '#fff3cd' : '';
        celdaStock.style.padding = '2px 6px';
        celdaStock.style.borderRadius = '6px';
      }
    });
  } catch (e) {
    console.error('Error stock batch', e);
  }
}

/* ========== MODAL DE PRODUCTOS ========== */

// Carga productos en el modal (sin onclick inline; usamos addEventListener)
function cargarProductos() {
  fetch('tabla_producto.php')
    .then(response => response.json())
    .then(data => {
      const tabla = document
        .getElementById('tablaSeleccionarProducto')
        .getElementsByTagName('tbody')[0];
      tabla.innerHTML = '';  // limpiar

      data.forEach(producto => {
        const fila = tabla.insertRow();
        fila.insertCell(0).textContent = producto.id_producto;
        fila.insertCell(1).textContent = producto.nombre;

        const celdaAccion = fila.insertCell(2);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = 'Seleccionar';
        btn.addEventListener('click', () => agregarProducto(producto.id_producto, producto.nombre));
        celdaAccion.appendChild(btn);
      });
    })
    .catch(error => console.error('Error al cargar productos:', error));
}

/* ========== TABLA PRINCIPAL ========== */

// Agrega una fila de producto a la tabla principal y carga el stock
function agregarProducto(id_producto, nombre) {
  const tablaProductos = document.getElementById('productos');
  const tablaModificar = document.getElementById('tbodyModificar');
  const tabla = tablaProductos || tablaModificar;

  const fila = tabla.insertRow();
  fila.dataset.id = id_producto; // clave para refresh/batch

  const celdaId          = fila.insertCell(0);
  const celdaNombre      = fila.insertCell(1);
  const celdaCantidad    = fila.insertCell(2);
  const celdaStockActual = fila.insertCell(3); // NUEVA
  const celdaEliminar    = fila.insertCell(4);

  celdaId.textContent = id_producto;
  celdaNombre.textContent = nombre;
  celdaCantidad.innerHTML = '<input type="number" name="cantidad[]" min="1" value="1" required>';
  celdaStockActual.innerHTML = '<span class="celda-stock">—</span>';
  celdaEliminar.innerHTML = '<button type="button" class="btn-eliminar-fila">Eliminar</button>';

  // Campos ocultos para enviar en el form
  const inputIdProducto = document.createElement('input');
  inputIdProducto.type = 'hidden';
  inputIdProducto.name = 'id_producto[]';
  inputIdProducto.value = id_producto;
  celdaId.appendChild(inputIdProducto);

  const inputNombre = document.createElement('input');
  inputNombre.type = 'hidden';
  inputNombre.name = 'nombre_producto[]';
  inputNombre.value = nombre;
  celdaNombre.appendChild(inputNombre);

  // Listener para eliminar (evita inline y no se rompe si cambian clases)
  celdaEliminar.querySelector('.btn-eliminar-fila')
    .addEventListener('click', () => eliminarFila(celdaEliminar.querySelector('.btn-eliminar-fila')));

  // Cerrar modal si existe
  const modal = document.getElementById('modalProductos');
  if (modal) modal.style.display = 'none';

  // Cargar stock de la fila recién agregada
  cargarStockFila(fila, id_producto);
}

// Elimina una fila de la tabla principal
function eliminarFila(botonEliminar) {
  const fila = botonEliminar.closest('tr');
  fila?.remove();
}

// Muestra el modal y carga los productos
function setupAgregarProductoButtons() {
  document.querySelectorAll('.btnAgregarProducto').forEach(button => {
    button.onclick = function () {
      const modal = document.getElementById('modalProductos');
      if (modal) {
        modal.style.display = 'block';
        cargarProductos();
      }
    };
  });

  // Cierra el modal al hacer clic fuera
  window.onclick = function (event) {
    const modal = document.getElementById('modalProductos');
    if (modal && event.target === modal) {
      modal.style.display = 'none';
    }
  };

  // Cierra con la X si existe
  const btnCerrar = document.getElementById('cerrarModal');
  const modal = document.getElementById('modalProductos');
  if (btnCerrar && modal) btnCerrar.onclick = () => modal.style.display = 'none';
}

// SALIR A TABLA PEDIDOS INTERNOS (lo tuyo)
function salir_tabla_pedido_interno() {
  window.location.href = 'tabla_pedidos.html';
}

/* ========== INICIALIZACIÓN ========== */

document.addEventListener('DOMContentLoaded', () => {
  setupAgregarProductoButtons();

  // (Opcional) Auto-refresh de stocks cada X segundos:
  // setInterval(refrescarStocksVisibles, 15000); // 15s recomendado
  // refrescarStocksVisibles(); // primer refresh al cargar
});

// Obtener número de pedido (tu flujo)
document.addEventListener("DOMContentLoaded", function () {
  fetch('obtener_numero_pedido_interno.php')
    .then(response => response.json())
    .then(data => {
      const siguienteNumeroPedido = data.siguiente_numero_pedido;
      const input = document.getElementById('numeroPedido');
      if (input) input.value = siguienteNumeroPedido;
    })
    .catch(error => {
      console.error('Error:', error);
    });
});
