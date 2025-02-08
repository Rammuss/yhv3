document.addEventListener('DOMContentLoaded', function () {
    const buscarButton = document.getElementById('buscar');
    if (buscarButton) {
        buscarButton.addEventListener('click', buscarProvisiones);
    } else {
        console.error('Botón "buscar" no encontrado.');
    }
});

function buscarProvisiones() {
    const fecha = document.getElementById('fecha').value;
    const ruc = document.getElementById('ruc').value;
    const estado = document.getElementById('estado').value;

    // Construir el cuerpo de la solicitud
    const body = {
        fecha: fecha,
        ruc: ruc,
        estado: estado
    };

    console.log('Body de la solicitud:', body);

    // Configurar la solicitud fetch
    fetch('../controlador/buscar_provisiones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
    })
    .then(response => {
        console.log('Respuesta del servidor:', response);
        return response.json();
    })
    .then(data => {
        console.log('Datos recibidos:', data);
        // Manipular los datos recibidos y mostrarlos en la tabla
        const resultadosBody = document.getElementById('resultados');
        resultadosBody.innerHTML = ''; // Limpiar resultados anteriores

        data.forEach(provision => {
            const estadoClass = provision.estado_provision === 'pendiente' ? 'has-background-warning-light' : 
                                provision.estado_provision === 'pagado' ? 'has-background-success-light' : 
                                provision.estado_provision === 'anulado' ? 'has-background-danger-light' : '';
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${provision.id_provision}</td>
                <td>${provision.id_factura}</td>
                <td>${provision.id_proveedor}</td>
                <td>${provision.monto_provisionado}</td>
                <td>${provision.tipo_provision}</td>
                <td class="${estadoClass}">${provision.estado_provision}</td>
                <td>${provision.fecha_creacion}</td>
                <td>
                    <button class="button btn-anular" 
                        data-id="${provision.id_provision}" 
                        ${provision.estado_provision === 'anulado' ? 'disabled' : ''}
                    >
                        Anular
                    </button>
                </td>
            `;
            resultadosBody.appendChild(row);
        });

        // Agregar evento a los botones de anular
        document.querySelectorAll('.btn-anular').forEach(button => {
            button.addEventListener('click', function () {
                const idProvision = this.getAttribute('data-id');
                if (confirm('¿Estás seguro de que deseas anular esta provisión?')) {
                    anularProvision(idProvision, this);
                }
            });
        });

    })
    .catch(error => {
        console.error('Error al buscar provisiones:', error);
    });
}

function anularProvision(idProvision, button) {
    fetch('../controlador/anular_provisiones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id_provision: idProvision })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.disabled = true; // Deshabilitar el botón
            button.closest('tr').querySelector('td:nth-child(6)').textContent = 'anulado'; // Actualizar estado en la tabla
            button.closest('tr').querySelector('td:nth-child(6)').classList.add('has-background-danger-light'); // Actualizar color de la celda
        } else {
            alert(data.message || 'Error al anular la provisión');
        }
    })
    .catch(error => {
        console.error('Error al anular la provisión:', error);
    });
}
    