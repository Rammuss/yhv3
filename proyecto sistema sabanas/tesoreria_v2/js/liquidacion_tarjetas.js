document.addEventListener('DOMContentLoaded', () => {
    // Función para cargar las procesadoras dinámicamente en el select
    function cargarProcesadoras() {
      // Ajusta la URL a la ubicación de tu endpoint
      fetch('../controlador/procesadoras.php')
        .then(response => {
          if (!response.ok) {
            throw new Error('Error en la respuesta de la red');
          }
          return response.json();
        })
        .then(data => {
          const select = document.getElementById('procesadoraSelect');
          // Se asume que cada procesadora tiene 'id' y 'nombre'
          data.forEach(procesadora => {
            const option = document.createElement('option');
            option.value = procesadora.id;
            option.textContent = procesadora.nombre;
            select.appendChild(option);
          });
        })
        .catch(error => {
          console.error('Error al cargar las procesadoras:', error);
        });
    }
  
    // Llamada a la función para cargar las procesadoras al cargar la página
    cargarProcesadoras();
  
    // Interceptar el envío del formulario para enviarlo vía fetch (asincrónicamente)
    const reporteForm = document.getElementById('reporteForm');
    reporteForm.addEventListener('submit', function(event) {
      event.preventDefault();
  
      // Crear objeto FormData con los datos del formulario (incluye el archivo)
      const formData = new FormData(reporteForm);
  
      // Enviar los datos al backend
      fetch('../controlador/reporte_tarjeta_procesar.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Error en el envío del formulario');
        }
        return response.text();
      })
      .then(result => {
        // Mostrar mensaje de éxito
        document.getElementById('mensaje').innerHTML =
          `<div class="notification is-success">${result}</div>`;
        // Reiniciar el formulario si es necesario
        reporteForm.reset();
      })
      .catch(error => {
        console.error('Error al enviar el formulario:', error);
        document.getElementById('mensaje').innerHTML =
          `<div class="notification is-danger">Error al cargar el reporte</div>`;
      });
    });
  });
  