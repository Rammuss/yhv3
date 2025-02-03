document.addEventListener("DOMContentLoaded", function() {
    cargarRendicionesPendientes();
  });
  
  // Función para cargar las rendiciones pendientes
  function cargarRendicionesPendientes() {
    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/get_rendiciones.php")
      .then(response => response.json())
      .then(data => {
        const tbody = document.getElementById("rendicionesTable");
        tbody.innerHTML = "";
        if (data.length === 0) {
          tbody.innerHTML = "<tr><td colspan='6'>No hay rendiciones pendientes</td></tr>";
          return;
        }
        data.forEach(rendicion => {
          const tr = document.createElement("tr");
          tr.innerHTML = `
            <td>${rendicion.id}</td>
            <td>${rendicion.asignacion_info}</td>
            <td>${rendicion.fecha_rendicion}</td>
            <td>${rendicion.total_rendido}</td>
            <td>${rendicion.estado}</td>
            <td>
              <button class="button is-success is-small" onclick="actualizarEstado(${rendicion.id}, 'Aprobada')">Aprobar</button>
              <button class="button is-danger is-small" onclick="actualizarEstado(${rendicion.id}, 'Rechazada')">Rechazar</button>
            </td>
          `;
          tbody.appendChild(tr);
        });
      })
      .catch(error => console.error("Error al cargar rendiciones:", error));
  }
  
  // Función para actualizar el estado de una rendición
  function actualizarEstado(id, nuevoEstado) {
    if (!confirm(`¿Estás seguro de cambiar el estado a ${nuevoEstado}?`)) return;
  
    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/rendicion_actualizar_estado.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/json"
      },
      body: JSON.stringify({ id: id, estado: nuevoEstado })
    })
    .then(response => response.json())
    .then(data => {
      alert(data.message);
      // Recargar la lista
      cargarRendicionesPendientes();
    })
    .catch(error => console.error("Error al actualizar el estado:", error));
  }
  