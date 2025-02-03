// script.js
///ENVIAR EL POST 
document.getElementById("proveedorForm").addEventListener("submit", function(e) {
    console.log("Evento submit detectado"); // Verifica si el evento ocurre
    e.preventDefault(); // Evita el envío tradicional del formulario

    const form = e.target;
    
    // Crear un objeto con los datos del formulario
    const data = {
        nombre: form.nombre.value.trim(),
        direccion: form.direccion.value.trim(),
        telefono: form.telefono.value.trim(),
        email: form.email.value.trim(),
        ruc: form.ruc.value.trim(),
        id_pais: form.paisSelect.value ? parseInt(form.paisSelect.value) : null,
        id_ciudad: form.selectCiudad.value ? parseInt(form.selectCiudad.value) : null,
        tipo: form.tipoProveedor.value.trim() ? form.tipoProveedor.value.trim() : null
    };

    // Enviar los datos al backend mediante fetch
    fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/referencialesT/controladorT/proveedor_registrar_t.php', {
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
        } else {
            mensajeDiv.innerHTML = `<div class="notification is-danger">${result.message}</div>`;
        }
    })
    .catch(error => {
        console.error("Error en fetch:", error);
        document.getElementById("mensaje").innerHTML = `<div class="notification is-danger">Error en la conexión.</div>`;
    });
});
//////////

//TOMAR PAIS Y CIUDAD////
// Función para cargar la lista de países y llenar el <select>
function cargarPaises() {
    fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/referencialesT/controladorT/get_paises.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const selectPais = document.getElementById("paisSelect");
                // Limpiar el select y agregar la opción por defecto
                selectPais.innerHTML = '<option value="">Seleccione un país</option>';
                data.paises.forEach(function(pais) {
                    const option = document.createElement("option");
                    option.value = pais.id_pais;
                    option.textContent = pais.nombre;
                    selectPais.appendChild(option);
                });
            } else {
                console.error("Error al cargar países:", data.message);
            }
        })
        .catch(error => console.error("Error en fetch (paises):", error));
}


// Función para cargar las ciudades cuando se selecciona un país
document.getElementById('paisSelect').addEventListener('change', function() {
    const idPais = this.value;
    const selectCiudad = document.getElementById('selectCiudad');

    // Limpiar el select de ciudades
    selectCiudad.innerHTML = '<option value="">Seleccione una ciudad</option>';

    if (idPais) {
        // Usar fetch para obtener las ciudades
        fetch(`/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/referencialesT/controladorT/get_ciudades.php?id_pais=${idPais}`)
            .then(response => response.json())
            .then(data => {
                // Si hay ciudades, agregarlas al select
                if (data.length > 0) {
                    data.forEach(ciudad => {
                        const option = document.createElement('option');
                        option.value = ciudad.id_ciudad;
                        option.textContent = ciudad.nombre;
                        selectCiudad.appendChild(option);
                    });
                } else {
                    alert("No se encontraron ciudades para este país.");
                }
            })
            .catch(error => console.error('Error al cargar ciudades:', error));
    }
});

// Cargar los select de países y ciudades cuando el DOM esté listo
document.addEventListener("DOMContentLoaded", function() {
    cargarPaises();
});