console.log("se cargó tabla_pedidos_interno.js");

// Variable para almacenar los datos cargados
let pedidosData = [];

// Función para cargar los datos desde PHP usando fetch
function cargarDatos() {
    fetch('consulta_presupuestos.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al cargar los datos.');
            }
            return response.json();
        })
        .then(pedidos => {
            pedidosData = pedidos; // Guardar los datos en la variable
            mostrarPedidosEnTabla(pedidos); // Mostrar todos los datos inicialmente
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Función para mostrar los pedidos en la tabla
function mostrarPedidosEnTabla(pedidos) {
    var tablaBody = document.getElementById('table-body');
    tablaBody.innerHTML = ''; // Limpiar la tabla antes de volver a llenarla

    pedidos.forEach(function (pedido) {
        var fila = document.createElement('tr');
        fila.innerHTML = `
            <td>${pedido.id_presupuesto}</td>
            <td>${pedido.nombre}</td>
            <td>${pedido.fecharegistro}</td>
            <td>${pedido.fechavencimiento}</td>
            <td>${pedido.estado}</td>
            <td>
                <button class="button-view" onclick="visualizarPresupuesto(${pedido.id_presupuesto})">Visualizar</button>
                <button class="button-approve" onclick="aprobarPresupuesto(${pedido.id_presupuesto})">Aprobar</button>
                <button class="button-cancel" onclick="anularPresupuesto(${pedido.id_presupuesto})">Anular</button>
            </td>
        `;
        tablaBody.appendChild(fila);
    });
}

// Función para manejar el cambio en el filtro
function handleFilterChange() {
    const estadoSeleccionado = document.getElementById('filter-status').value;
    
    // Filtrar los datos según el estado seleccionado
    const datosFiltrados = estadoSeleccionado === '' 
        ? pedidosData 
        : pedidosData.filter(pedido => pedido.estado === estadoSeleccionado);
    
    mostrarPedidosEnTabla(datosFiltrados);
}

// Añadir un listener para el filtro
document.getElementById('filter-status').addEventListener('change', handleFilterChange);

// Llamar a la función para cargar los datos al cargar la página
window.onload = function () {
    cargarDatos();
};



//visualizar reparte la cabecera y el detalles en dos variables

function visualizarPresupuesto(id_presupuesto) {
    fetch(`visualizar_presupuesto.php?id_presupuesto=${id_presupuesto}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la solicitud de datos.');
            }
            return response.json();
        })
        .then(data => {
            console.log(data)
            if (data.error) {
                throw new Error(data.error);
            }
            // Organizar los datos

            const cabecera = {
                id_presupuesto: data[0].id_presupuesto,
                nombre_proveedor: data[0].nombre_proveedor,
                fecharegistro: data[0].fecharegistro,
                fechavencimiento: data[0].fechavencimiento,
                estado: data[0].estado
            };

            const detalles = data.map(item => ({
                id_presupuesto_detalle: item.id_presupuesto_detalle,
                nombre_producto: item.nombre_producto,
                cantidad: item.cantidad,
                precio_unitario: item.precio_unitario,
                precio_total: item.precio_total
            })).filter(item => item.id_presupuesto_detalle); // Filtra detalles válidos

            mostrarModal({ cabecera, detalles });
        })
        .catch(error => {
            console.error('Error al obtener los detalles del presupuesto:', error);
            alert('No se pudieron obtener los detalles del presupuesto.');
        });
}

function mostrarModal(data) {
    const modal = document.getElementById('modal');
    const contenido = document.getElementById('modal-content');

    const { cabecera, detalles } = data;

    // Construir HTML para la cabecera del presupuesto
    let html = `
        <h2>Detalles del Presupuesto</h2>
        <p>ID Presupuesto: ${cabecera.id_presupuesto}</p>
        <p>Nombre del Proveedor: ${cabecera.nombre_proveedor}</p>
        <p>Fecha de Registro: ${cabecera.fecharegistro}</p>
        <p>Fecha de Vencimiento: ${cabecera.fechavencimiento}</p>
        <p>Estado: ${cabecera.estado}</p>
    `;

    // Construir HTML para los detalles del presupuesto
    html += '<h3>Detalles</h3>';
    html += '<table border="1">';
    html += '<thead><tr><th>ID Detalle</th><th>Producto</th><th>Cantidad</th><th>Precio Unitario</th><th>Precio Total</th></tr></thead>';
    html += '<tbody>';

    detalles.forEach(detalle => {
        if (detalle.id_presupuesto_detalle) {
            html += `
                <tr>
                    <td>${detalle.id_presupuesto_detalle}</td>
                    <td>${detalle.nombre_producto}</td>
                    <td>${detalle.cantidad}</td>
                    <td>${detalle.precio_unitario}</td>
                    <td>${detalle.precio_total}</td>
                </tr>
            `;
        }
    });

    html += '</tbody></table>';
    html += '<button onclick="cerrarModal()">Cerrar</button>';

    contenido.innerHTML = html;
    modal.style.display = 'block';
}

function cerrarModal() {
    document.getElementById('modal').style.display = 'none';
}


function aprobarPresupuesto(id_presupuesto) {
    // Lógica para aprobar el presupuesto
    // Llamada a la API para APROBAR el presupuesto
    fetch(`aprobar_presupuesto.php?id_presupuesto=${id_presupuesto}`)
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


function anularPresupuesto(id_presupuesto) {
    // Llamada a la API para anular el presupuesto
    fetch(`anular_presupuesto.php?id_presupuesto=${id_presupuesto}`)
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

