document.addEventListener("DOMContentLoaded", function() {
    cargarRendiciones();
    document.getElementById("reposicionForm").addEventListener("submit", registrarReposicion);
    // Agregar listener para actualizar la diferencia cuando se cambie el select de rendición
    document.getElementById("rendicion").addEventListener("change", actualizarDiferencia);
  });
  
  // Función para cargar rendiciones disponibles para reposición
  function cargarRendiciones() {
    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/get_rendiciones_para_reposicion.php")
      .then(response => response.json())
      .then(data => {
        const select = document.getElementById("rendicion");
        select.innerHTML = '<option value="">Seleccione una rendición</option>';
        data.forEach(rendicion => {
          // Se asume que el endpoint devuelve: id, total_rendido, monto_asignado, y asignacion_info.
          const option = document.createElement("option");
          option.value = rendicion.id;
          // Muestra la información en el select
          option.textContent = `${rendicion.asignacion_info} | Total Rendido: ${rendicion.total_rendido} | Monto Asignado: ${rendicion.monto_asignado}`;
          // Guarda datos adicionales como atributos para usar en el cálculo
          option.dataset.totalRendido = rendicion.total_rendido;
          option.dataset.montoAsignado = rendicion.monto_asignado;
          select.appendChild(option);
        });
      })
      .catch(error => console.error("Error al cargar rendiciones:", error));
  }
  
  // Función para actualizar la etiqueta con la diferencia
  function actualizarDiferencia() {
    const select = document.getElementById("rendicion");
    const selectedOption = select.options[select.selectedIndex];
    const label = document.getElementById("diferenciaLabel");
    
    if (selectedOption && selectedOption.value !== "") {
      const totalRendido = parseFloat(selectedOption.dataset.totalRendido);
      const montoAsignado = parseFloat(selectedOption.dataset.montoAsignado);
      const diferencia = montoAsignado - totalRendido;
      
      if (diferencia > 0) {
        // Significa que no se usó todo el fondo: se debe reponer la diferencia.
        label.textContent = `Monto a reponer: $${diferencia.toFixed(2)}`;
      } else if (diferencia < 0) {
        // Significa que se excedió el fondo: se debe aportar el excedente.
        label.textContent = `Excedente utilizado: $${Math.abs(diferencia).toFixed(2)} (aportar adicional)`;
      } else {
        label.textContent = "El fondo se usó exactamente.";
      }
    } else {
      label.textContent = "";
    }
  }
  
  // Función para registrar la reposición
  function registrarReposicion(e) {
    e.preventDefault();
    const form = document.getElementById("reposicionForm");
    const formData = new FormData(form);
    
    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/reposicion_registrar.php", {
      method: "POST",
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      const mensajeDiv = document.getElementById("mensaje");
      if (data.success) {
        mensajeDiv.innerHTML = `<div class="notification is-success">${data.message}</div>`;
        form.reset();
        document.getElementById("diferenciaLabel").textContent = "";
      } else {
        mensajeDiv.innerHTML = `<div class="notification is-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error("Error en fetch:", error);
      document.getElementById("mensaje").innerHTML = `<div class="notification is-danger">Error en la conexión.</div>`;
    });
  }
  