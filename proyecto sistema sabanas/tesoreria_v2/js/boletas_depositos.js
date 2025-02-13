document.addEventListener('DOMContentLoaded', () => {
    /**
     * Función para cargar las cuentas bancarias desde la BD
     * a través del endpoint y agregarlas al select.
     */
    function cargarCuentasBancarias() {
        // Ajusta la URL al endpoint que retorna las cuentas bancarias (excluyendo las de tipo 'Interno')
        fetch('../controlador/get_bancos_boleta.php')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta de la red');
                }
                return response.json();
            })
            .then(data => {
                const select = document.getElementById('cuentaBancariaSelect');
                // Se asume que cada objeto "cuenta" tiene propiedades "id" y "nombre"
                data.forEach(cuenta => {
                    const option = document.createElement('option');
                    option.value = cuenta.id;
                    option.textContent = cuenta.nombre;
                    select.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error al cargar las cuentas bancarias:', error);
            });
    }

    // Llamamos a la función para cargar las cuentas al cargar la página
    cargarCuentasBancarias();

    // Interceptar el envío del formulario para hacerlo vía fetch (asincrónicamente)
    const depositoForm = document.getElementById('depositoForm');
    depositoForm.addEventListener('submit', function(event) {
        event.preventDefault(); // Prevenir el envío tradicional del formulario

        // Crear un objeto FormData con los datos del formulario
        const formData = new FormData(depositoForm);

        // Enviar los datos al endpoint procesar_deposito.php
        fetch('../controlador/banco_boleta_registrar.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Puedes ajustar la respuesta según lo que retorne el servidor (texto, JSON, etc.)
            return response.text();
        })
        .then(result => {
            // Mostrar mensaje de éxito
            document.getElementById('mensaje').innerHTML =
                `<div class="notification is-success">${result}</div>`;
            // Opcional: Reiniciar el formulario
            depositoForm.reset();

            // Opcional: Restablecer la fecha actual después del reset
            const fechaInput = document.getElementById('fecha');
            const today = new Date().toISOString().split('T')[0];
            fechaInput.value = today;
        })
        .catch(error => {
            console.error('Error al enviar el formulario:', error);
            document.getElementById('mensaje').innerHTML =
                `<div class="notification is-danger">Error al registrar el depósito</div>`;
        });
    });
});
