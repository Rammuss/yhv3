console.log("se cargo tabla_pedidos_interno.js")

// Variable para almacenar los datos cargados
let pedidosData = [];
// Función para cargar los datos desde PHP usando fetch
function cargarDatos() {
    fetch('consulta_orden_compra.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al cargar los datos.');
            }
            return response.json();
        })
        .then(pedidos => {
            pedidosData = pedidos; // Guardar los datos en la variable
            mostrarPedidosEnTabla(pedidos);
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Función para mostrar los pedidos en la tabla

function mostrarPedidosEnTabla(pedidos) {
    var tablaBody = document.querySelector('#tablaPedidos tbody');
    tablaBody.innerHTML = ''; // Limpiar la tabla antes de volver a llenarla
    pedidos.forEach(function (pedido) {
        var fila = document.createElement('tr');
        fila.innerHTML = `
            <td>${pedido.id_orden_compra}</td>
            <td>${pedido.fecha_emision}</td>
            <td>${pedido.fecha_entrega}</td>
            <td>${pedido.condiciones_entrega}</td>
            <td>${pedido.metodo_pago}</td>
            <td>${pedido.cuotas}</td>
            <td>${pedido.estado_orden}</td>
            <td>${pedido.id_presupuesto}</td>
            <td>${pedido.nombre_proveedor}</td>
            <td>
                <button class="button-approve" onclick="aprobarPresupuesto(${pedido.id_orden_compra})">Aprobar</button>
                <button class="button-cancel" onclick="anularPresupuesto(${pedido.id_orden_compra})">Anular</button>
                <button class="button-pdf" onclick="generarPDF(${pedido.id_orden_compra})">PDF</button>
            </td>
        `;
        tablaBody.appendChild(fila);
    });
}

// Función para generar el PDF
function generarPDF(idOrdenCompra) {
    // Redirigir a la página PHP que genera el PDF
    window.open(`generar_orden_compraV2pdf.php?id=${idOrdenCompra}`, '_blank');
}

// Función para manejar el cambio en el filtro
function handleFilterChange() {
    const estadoSeleccionado = document.getElementById('filter-status').value;
    console.log("activadoS")
    // Filtrar los datos según el estado seleccionado
    const datosFiltrados = estadoSeleccionado === ''
        ? pedidosData
        : pedidosData.filter(pedido => pedido.estado_orden === estadoSeleccionado);

    mostrarPedidosEnTabla(datosFiltrados);
}
// Añadir un listener para el filtro
document.getElementById('filter-status').addEventListener('change', handleFilterChange);


// Llamar a la función para cargar los datos al cargar la página
window.onload = function () {
    cargarDatos();
};
//FUNCION PARA APROBAR 
function aprobarPresupuesto(id_orden_compra) {
    // Lógica para aprobar el presupuesto
    // Llamada a la API para APROBAR el presupuesto
    fetch(`aprobar_orden_compra.php?id_orden_compra=${id_orden_compra}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la solicitud de datos.');
            }
            return response.json(); // Leer la respuesta como JSON
        })
        .then(data => {
            if (data.success) {
                console.log("Presupuesto aprobado exitosamente.");
                // Actualiza la interfaz de usuario si es necesario
                // Ejemplo: eliminar la fila correspondiente de la tabla
                // o actualizar el estado en la interfaz
                location.reload(); // Recarga la página actual
            } else {
                console.error('Error al aprobar el presupuesto.');
                alert('No se pudo aprobar el presupuesto.');
            }
        })
        .catch(error => {
            console.error('Error al procesar la solicitud:', error);
            alert('No se pudo aprobar el presupuesto.');
        });
    console.log("Aprobando presupuesto:", id_presupuesto);
}


function anularPresupuesto(id_orden_compra) {
    // Llamada a la API para anular el presupuesto
    fetch(`anular_orden_compra.php?id_orden_compra=${id_orden_compra}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la solicitud de datos.');
            }
            return response.json(); // Leer la respuesta como JSON
        })
        .then(data => {
            if (data.success) {
                console.log("Presupuesto anulado exitosamente.");
                // Actualiza la interfaz de usuario si es necesario
                // Ejemplo: eliminar la fila correspondiente de la tabla
                // o actualizar el estado en la interfaz
                location.reload(); // Recarga la página actual
            } else {
                console.error('Error al anular el presupuesto.');
                alert('No se pudo anular el presupuesto.');
            }
        })
        .catch(error => {
            console.error('Error al procesar la solicitud:', error);
            alert('No se pudo anular el presupuesto.');
        });
}