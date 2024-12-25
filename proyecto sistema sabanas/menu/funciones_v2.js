// funciones.js
console.log("se cargo funciones.js")
// Función para cargar productos usando fetch
function cargarProductos() {
    fetch('tabla_producto.php')
        .then(response => response.json())
        .then(data => {
            const tabla = document.getElementById('tablaSeleccionarProducto').getElementsByTagName('tbody')[0];
            tabla.innerHTML = '';  // Limpiar tabla antes de agregar datos
            data.forEach(producto => {
                const fila = tabla.insertRow();
                const celdaId = fila.insertCell(0);
                const celdaNombre = fila.insertCell(1);
                const celdaAccion = fila.insertCell(2);

                celdaId.innerHTML = producto.id_producto;
                celdaNombre.innerHTML = producto.nombre;
                celdaAccion.innerHTML = `<button type="button" onclick="agregarProducto(${producto.id_producto}, '${producto.nombre}')">Seleccionar</button>`;
            });
        })
        .catch(error => console.error('Error al cargar productos:', error));
}

// Función para agregar producto a la tabla
function agregarProducto(id_producto, nombre) {

    const tablaProductos = document.getElementById('productos');
    const tablaModificar = document.getElementById('tbodyModificar');

    // Elegir la tabla a la que se agregarán los productos
    const tabla = tablaProductos || tablaModificar;

    const fila = tabla.insertRow();

    const celdaId = fila.insertCell(0);
    const celdaNombre = fila.insertCell(1);
    const celdaCantidad = fila.insertCell(2);
    const celdaEliminar = fila.insertCell(3);

    celdaId.innerHTML = id_producto;
    celdaNombre.innerHTML = nombre;
    celdaCantidad.innerHTML = '<input type="number" name="cantidad[]" required>';
    celdaEliminar.innerHTML = '<button type="button" onclick="eliminarFila(this)">Eliminar</button>';

    // Agregar campos ocultos para id_producto y nombre
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

    // Cierra el modal
    document.getElementById('modalProductos').style.display = 'none';
}

// Función para eliminar una fila de la tabla principal
function eliminarFila(botonEliminar) {
    const fila = botonEliminar.closest('tr'); // Obtener la fila actual
    fila.remove(); // Eliminar la fila de la tabla
}

// Mostrar el modal y cargar productos
function setupAgregarProductoButtons() {
    document.querySelectorAll('.btnAgregarProducto').forEach(button => {
        button.onclick = function () {
            const modal = document.getElementById('modalProductos');
            modal.style.display = 'block';
            cargarProductos();
        };
    });

    

    // Cierra el modal al hacer clic fuera de él
    window.onclick = function (event) {
        if (event.target == document.getElementById('modalProductos')) {
            document.getElementById('modalProductos').style.display = 'none';
        }
    };
}
// SALIR A TABLA PEDIDOS INTERNOS
function salir_tabla_pedido_interno() {
    // Lógica de salida, por ejemplo redireccionar a otra página
    window.location.href = 'tabla_pedidos.html'; // Cambia a la página de destino deseada
  }

// Inicializar
document.addEventListener('DOMContentLoaded', () => {
    setupAgregarProductoButtons();
});
// Para obtener numero de pedido ultimo
document.addEventListener("DOMContentLoaded", function () {
    fetch('obtener_numero_pedido_interno.php')
        .then(response => response.json())
        .then(data => {
            const siguienteNumeroPedido = data.siguiente_numero_pedido;
            document.getElementById('numeroPedido').value = siguienteNumeroPedido;
        })
        .catch(error => {
            console.error('Error:', error);
        });
});



