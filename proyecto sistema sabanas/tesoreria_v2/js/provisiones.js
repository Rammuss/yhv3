
document.addEventListener('DOMContentLoaded', function () {
    const buscarButton = document.getElementById('buscar');
    buscarButton.addEventListener('click', buscarProvisiones);
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

    // Configurar la solicitud fetch
    fetch('../controlador/buscar_provisiones.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(body)
    })
    .then(response => response.json())
    .then(data => {
        // Manipular los datos recibidos y mostrarlos en la tabla
        const resultadosBody = document.getElementById('resultados');
        resultadosBody.innerHTML = ''; // Limpiar resultados anteriores

        data.forEach(provision => {
            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${provision.id_provision}</td>
                <td>${provision.id_factura}</td>
                <td>${provision.id_proveedor}</td>
                <td>${provision.monto_provisionado}</td>
                <td>${provision.tipo_provision}</td>
                <td>${provision.estado_provision}</td>
                <td>${provision.fecha_creacion}</td>
            `;
            resultadosBody.appendChild(row);
        });
    })
    .catch(error => {
        console.error('Error al buscar provisiones:', error);
    });
}
