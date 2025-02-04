document.addEventListener("DOMContentLoaded", function() {
  cargarAsignaciones();
});

function cargarAsignaciones() {
  fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/get_asignaciones_lista.php")
    .then(response => response.json())
    .then(data => {
      const tbody = document.getElementById("tablaAsignaciones");
      tbody.innerHTML = ""; // Limpiar tabla
      if (data.length === 0) {
        tbody.innerHTML = "<tr><td colspan='6'>No se encontraron asignaciones.</td></tr>";
        return;
      }
      data.forEach(asignacion => {
        const tr = document.createElement("tr");
        tr.innerHTML = `
          <td>${asignacion.id}</td>
          <td>${asignacion.proveedor_nombre}</td>
          <td>${asignacion.monto}</td>
          <td>${asignacion.fecha_asignacion}</td>
          <td>${asignacion.estado}</td>
          <td>${asignacion.descripcion || ""}</td>
        `;
        tbody.appendChild(tr);
      });
    })
    .catch(error => console.error("Error al cargar asignaciones:", error));
}
