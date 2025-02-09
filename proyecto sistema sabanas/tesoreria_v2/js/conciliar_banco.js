document.addEventListener('DOMContentLoaded', function() {
    const formConciliacion = document.getElementById('formConciliacion');
    const resultadosConciliacion = document.getElementById('resultadosConciliacion');
    const transaccionesConciliadas = document.getElementById('transaccionesConciliadas');
    const transaccionesNoConciliadas = document.getElementById('transaccionesNoConciliadas');

    formConciliacion.addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('../controlador/conciliar_banco.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.exito) {
                resultadosConciliacion.classList.remove('is-hidden');
                transaccionesConciliadas.innerHTML = '';
                transaccionesNoConciliadas.innerHTML = '';

                data.transacciones_conciliadas.forEach(transaccion => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td>${transaccion.fecha_transaccion}</td><td>${transaccion.descripcion}</td><td>${transaccion.monto}</td>`;
                    transaccionesConciliadas.appendChild(row);
                });

                data.transacciones_no_conciliadas.forEach(transaccion => {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td>${transaccion.fecha_transaccion}</td><td>${transaccion.descripcion}</td><td>${transaccion.monto}</td>`;
                    transaccionesNoConciliadas.appendChild(row);
                });
            } else {
                alert(data.mensaje);
            }
        })
        .catch(error => {
            console.error('Error al conciliar transacciones:', error);
        });
    });
});
