// Función para cargar las órdenes de pago desde el servidor
function cargarOrdenes() {
    fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/obtener_ordenes_pago.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        },
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error al obtener las órdenes de pago');
        }
        return response.json();
    })
    .then(ordenes => {
        const tableBody = document.getElementById('ordenesPagoTable');

        if (!tableBody) {
            console.error('Elemento con ID "ordenesPagoTable" no encontrado.');
            return;
        }

        tableBody.innerHTML = ''; // Limpiar la tabla antes de agregar nuevas filas

        if (ordenes.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="8" style="text-align: center;">No hay órdenes de pago disponibles</td></tr>';
            return;
        }

        ordenes.forEach(orden => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${orden.id}</td>
                <td>${orden.proveedor}</td>
                <td>${orden.numero_cheque ? orden.numero_cheque : 'N/A'}</td>
                <td>${orden.monto_total}</td>
                <td>${orden.fecha_orden}</td>
                <td>${orden.estado}</td>
                <td>
                    <button class="button is-info" onclick="imprimirCheque('${orden.id_cheque}')">
                        Imprimir Cheque
                    </button>
               
                
                    <button class="button is-danger" onclick="anularOrden('${orden.id}')">
                        Anular
                    </button>
                </td>
            `;
            tableBody.appendChild(row);
        });
    })
    .catch(error => {
        console.error('Error al cargar las órdenes:', error);
        alert('Hubo un error al obtener las órdenes de pago. Revisa la consola para más detalles.');
    });
}

// Función para redirigir a la página de impresión del cheque
function imprimirCheque(id_cheque) {
    if (!id_cheque || isNaN(id_cheque)) {
        alert("Error: ID del cheque no válido.");
        return;
    }

    window.location.href = `/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/imprimir_cheque.php?id_cheque=${id_cheque}`;
}

// Función para anular la orden de pago
function anularOrden(id_orden) {
    if (!id_orden || isNaN(id_orden)) {
        alert("Error: ID de la orden no válido.");
        return;
    }

    // Confirmar acción de anulación
    const confirmAnular = confirm("¿Estás seguro de que deseas anular esta orden de pago?");
    if (confirmAnular) {
        // Llamada para anular la orden
        fetch(`/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/anular_orden_pago.php?id_orden=${id_orden}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al anular la orden de pago');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert("Orden de pago anulada exitosamente.");
                cargarOrdenes(); // Recargar las órdenes después de la anulación
            } else {
                alert("No se pudo anular la orden de pago. Intenta nuevamente.");
            }
        })
        .catch(error => {
            console.error('Error al anular la orden:', error);
            alert('Hubo un error al anular la orden de pago. Revisa la consola para más detalles.');
        });
    }
}

// Llamar a cargarOrdenes cuando la página se haya cargado
document.addEventListener('DOMContentLoaded', cargarOrdenes);
