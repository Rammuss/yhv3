console.log("se cargo tabla_pedidos_interno.js")
// Función para cargar los datos desde PHP usando fetch
function cargarDatos() {
    fetch('consulta_pedidos.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al cargar los datos.');
            }
            return response.json();
        })
        .then(pedidos => {
            mostrarPedidosEnTabla(pedidos);
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// Función para mostrar los pedidos en la tabla

function mostrarPedidosEnTabla(pedidos) {
    var tablaBody = document.querySelector('#tablaPedidos tbody');
    pedidos.forEach(function (pedido) {
        var fila = document.createElement('tr');
        fila.innerHTML = `
            <td>${pedido.numero_pedido}</td>
            <td>${pedido.departamento_solicitante}</td>
            <td>${pedido.telefono}</td>
            <td>${pedido.correo}</td>
            <td>${pedido.fecha_pedido}</td>
            <td>${pedido.fecha_entrega_solicitada}</td>
            <td><button class="button-delete" onclick="modificarPedido(${pedido.numero_pedido})">Modificar</button>
            <button class="button-delete" onclick="eliminarPedido(${pedido.numero_pedido})">Eliminar</button></td>
        `;
        tablaBody.appendChild(fila);
    });
}




 // Función para eliminar un pedido
 function eliminarPedido(numero_pedido) {
    if (confirm('¿Estás seguro de que deseas eliminar este pedido?')) {
        fetch(`eliminar_pedido_interno.php?numero_pedido=${numero_pedido}`, {
            method: 'GET'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al eliminar el pedido.');
            }
            return response.text();
        })
        .then(response => {
            console.log(response);
            window.location.reload(); // Recargar los datos después de la eliminación
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }
}



// Llamar a la función para cargar los datos al cargar la página
window.onload = function () {
    cargarDatos();
};

