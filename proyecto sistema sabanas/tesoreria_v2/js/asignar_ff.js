// script.js

// Función para cargar los proveedores y llenar el <select>
function cargarProveedores() {
    fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/get_proveedores.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById("proveedorSelect");
                // Limpiar el select (opcional, si se recarga la lista)
                select.innerHTML = '<option value="">Seleccione un proveedor</option>';
                data.proveedores.forEach(function(proveedor) {
                    const option = document.createElement("option");
                    option.value = proveedor.id_proveedor;
                    option.textContent = proveedor.nombre;
                    select.appendChild(option);
                });
            } else {
                console.error("Error al cargar proveedores:", data.message);
            }
        })
        .catch(error => console.error("Error en fetch (proveedores):", error));
}

// Función para enviar el formulario mediante fetch
document.getElementById("registroForm").addEventListener("submit", function(e) {
    e.preventDefault(); // Evitar el envío tradicional

    const form = e.target;

    // Crear objeto con los datos del formulario
    const data = {
        proveedor_id: parseInt(form.proveedor_id.value),
        monto: parseFloat(form.monto.value),
        fecha_asignacion: form.fecha_asignacion.value,
        estado: form.estado.value,
        descripcion: form.descripcion.value.trim() === "" ? null : form.descripcion.value.trim()
    };

    // Enviar los datos al backend
    fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/asignar_ff.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        const mensajeDiv = document.getElementById("mensaje");
        if (result.success) {
            mensajeDiv.innerHTML = `<div class="notification is-success">${result.message}</div>`;
            form.reset();
            // Recargar los proveedores por si hubiera cambios
            cargarProveedores();
        } else {
            mensajeDiv.innerHTML = `<div class="notification is-danger">${result.message}</div>`;
        }
    })
    .catch(error => {
        console.error("Error en fetch (registro):", error);
        document.getElementById("mensaje").innerHTML = `<div class="notification is-danger">Error en la conexión.</div>`;
    });
});

// Cargar proveedores cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", cargarProveedores);
