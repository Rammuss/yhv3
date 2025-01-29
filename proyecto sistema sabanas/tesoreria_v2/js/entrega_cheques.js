document.addEventListener('DOMContentLoaded', function () {
    cargarCheques(); // Cargar los cheques pendientes al cargar la página

    // Función para cargar los cheques pendientes desde el backend
    function cargarCheques() {
        fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/obtener_cheques.php')  // Reemplaza con la URL de tu servidor para obtener los cheques
            .then(response => response.json())
            .then(cheques => {
                const tableBody = document.getElementById('chequesPendientesTable');
                tableBody.innerHTML = '';  // Limpiar la tabla antes de agregar las filas

                cheques.forEach(cheque => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${cheque.id}</td>
                        <td>${cheque.beneficiario}</td>
                        <td>${cheque.monto_cheque}</td>
                        <td>${cheque.estado}</td>
                        <td>
                            <button class="button is-primary" onclick="entregarCheque(${cheque.id})">Entregar</button>
                            <button class="button is-danger" onclick="anularCheque(${cheque.id})">Anular</button>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            })
            .catch(error => console.error('Error al cargar los cheques:', error));
    }

    // Función para entregar un cheque
    window.entregarCheque = function(idCheque) {
        const fechaEntrega = prompt("Ingrese la fecha de entrega (YYYY-MM-DD):");
        const recibidoPor = prompt("Nombre de la persona que recibió el cheque:");
        const observaciones = prompt("Observaciones:");

        if (!fechaEntrega || !recibidoPor) {
            alert("La fecha y el nombre son obligatorios.");
            return;
        }

        const data = {
            id_cheque: idCheque,
            fecha_entrega: fechaEntrega,
            recibido_por: recibidoPor,
            observaciones: observaciones
        };

        // Enviar los datos al backend para actualizar el cheque
        fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/actualizar_cheque.php', {  // Reemplaza con la URL de tu backend
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                alert('Cheque entregado con éxito.');
                cargarCheques();  // Recargar la lista de cheques
            } else {
                alert('Error al registrar la entrega del cheque.');
            }
        })
        .catch(error => console.error('Error al registrar la entrega de cheque:', error));
    }

    // Función para anular un cheque
    window.anularCheque = function(idCheque) {
        const confirmacion = confirm("¿Está seguro de que desea anular este cheque?");
        
        if (!confirmacion) {
            return;  // Si el usuario no confirma, no se hace nada
        }

        const data = {
            id_cheque: idCheque,
            estado: 'Anulado'  // Actualizamos el estado a 'Anulado'
        };

        // Enviar los datos al backend para actualizar el estado del cheque
        fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/anular_cheque.php', {  
            // Reemplaza con la URL de tu backend
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.status === 'success') {
                alert('Cheque anulado con éxito.');
                cargarCheques();  // Recargar la lista de cheques
            } else {
                alert('Error al anular el cheque.');
            }
        })
        .catch(error => console.error('Error al anular el cheque:', error));
    }
});
