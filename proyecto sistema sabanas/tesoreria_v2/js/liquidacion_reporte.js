document.addEventListener('DOMContentLoaded', () => {
    function cargarProcesadoras() {
        fetch('../controlador/procesadoras.php')
            .then(response => response.json())
            .then(data => {
                const select = document.getElementById('procesadoraSelect');
                select.innerHTML = '<option value="">Seleccione una procesadora</option>';
                data.forEach(procesadora => {
                    const option = document.createElement('option');
                    option.value = procesadora.id;
                    option.textContent = procesadora.nombre;
                    select.appendChild(option);
                });
            })
            .catch(error => console.error('Error al cargar las procesadoras:', error));
    }

    cargarProcesadoras();

    document.getElementById('liquidacionForm').addEventListener('submit', function(event) {
        event.preventDefault();
        
        const id_procesadora = document.getElementById('procesadoraSelect').value;
        const fecha_desde = document.getElementById('fechaDesde').value;
        const fecha_hasta = document.getElementById('fechaHasta').value;

        if (!id_procesadora || !fecha_desde || !fecha_hasta) {
            document.getElementById('mensaje').innerHTML =
                `<div class="notification is-danger">Todos los campos son obligatorios.</div>`;
            return;
        }

        // Redirigir a la página que generará la liquidación en HTML
        window.location.href = `../controlador/reporte_liquidacion.php?id_procesadora=${id_procesadora}&fecha_desde=${fecha_desde}&fecha_hasta=${fecha_hasta}`;
    });
});
