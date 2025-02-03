document.addEventListener("DOMContentLoaded", function() {
    cargarAsignaciones();
    
    document.getElementById("agregarDetalle").addEventListener("click", agregarDetalle);
    document.getElementById("rendicionForm").addEventListener("submit", registrarRendicion);
  });
  
  // Función para cargar las asignaciones (con proveedor y RUC)
  function cargarAsignaciones() {
    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/get_asignaciones.php")
      .then(response => response.json())
      .then(data => {
        const select = document.getElementById("asignacion");
        select.innerHTML = '<option value="">Seleccione una asignación</option>';
        data.forEach(asignacion => {
          const option = document.createElement("option");
          option.value = asignacion.asignacion_id; // ID de la asignación
          // Construir la etiqueta: "Proveedor: [Nombre] - RUC: [RUC] | Fecha: [fecha] | Monto: [monto]"
          option.textContent = `Proveedor: ${asignacion.proveedor_nombre} - RUC: ${asignacion.ruc} | Fecha: ${asignacion.fecha_asignacion} | Monto: ${asignacion.monto}`;
          select.appendChild(option);
        });
      })
      .catch(error => console.error("Error al cargar asignaciones:", error));
  }
  
  // Función para recalcular el total rendido sumando todos los montos
  function actualizarTotalRendido() {
    let total = 0;
    document.querySelectorAll('input[name="monto[]"]').forEach(input => {
      const value = parseFloat(input.value);
      if (!isNaN(value)) {
        total += value;
      }
    });
    document.getElementById("total_rendido").value = total.toFixed(2);
  }
  
  // Función para agregar una fila de detalle de gasto
  function agregarDetalle() {
    const tbody = document.getElementById("detalleTable").querySelector("tbody");
    const row = document.createElement("tr");
    row.innerHTML = `
      <td><input class="input" type="text" name="descripcion[]" required></td>
      <td><input class="input" type="number" name="monto[]" step="0.01" required></td>
      <td><input class="input" type="date" name="fecha_gasto[]" required></td>
      <td><input class="input" type="text" name="documento_asociado[]" placeholder="Número de factura/boleta"></td>
      <td><button type="button" class="button is-danger is-small" onclick="eliminarFila(this)">X</button></td>
    `;
    tbody.appendChild(row);
  
    // Asigna el evento "input" al nuevo campo de monto para actualizar el total cuando cambie
    const montoInput = row.querySelector('input[name="monto[]"]');
    montoInput.addEventListener("input", actualizarTotalRendido);
  
    // Actualizar total por si se carga algún valor ya presente
    actualizarTotalRendido();
  }
  
  // Función para eliminar una fila de detalle
  function eliminarFila(btn) {
    btn.closest("tr").remove();
    actualizarTotalRendido();
  }
  
  // Función para registrar la rendición
  function registrarRendicion(e) {
    e.preventDefault();
    const form = document.getElementById("rendicionForm");
    const formData = new FormData(form);
  
    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/rendicion_registrar.php", {
      method: "POST",
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      const mensajeDiv = document.getElementById("mensaje");
      if (data.success) {
        mensajeDiv.innerHTML = `<div class="notification is-success">${data.message}</div>`;
        form.reset();
        // Reiniciar detalles: vaciar el tbody
        document.getElementById("detalleTable").querySelector("tbody").innerHTML = "";
        actualizarTotalRendido();
      } else {
        mensajeDiv.innerHTML = `<div class="notification is-danger">${data.message}</div>`;
      }
    })
    .catch(error => {
      console.error("Error en fetch:", error);
      document.getElementById("mensaje").innerHTML = `<div class="notification is-danger">Error en la conexión.</div>`;
    });
  }
  