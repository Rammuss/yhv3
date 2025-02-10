document.addEventListener("DOMContentLoaded", () => {
    // Cargar los bancos en el select
    cargarBancos();

    // Manejador del formulario
    document.getElementById("formOtrosMovimientos").addEventListener("submit", function(e) {
        e.preventDefault();

        // Recoger los datos del formulario usando FormData
        let formData = new FormData(this);
        let data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });

        // Enviar la data al backend con fetch
        fetch("../controlador/otros_deditos_creditos_registrar.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            mostrarMensaje(result.mensaje, result.exito);
            if(result.exito){
                // Reiniciar el formulario si se registró correctamente
                document.getElementById("formOtrosMovimientos").reset();
                // Volver a cargar los bancos en caso de que se actualice algún dato
                cargarBancos();
            }
        })
        .catch(error => {
            console.error("Error:", error);
            mostrarMensaje("Error en la solicitud", false);
        });
    });
});

// Función para cargar los bancos desde el backend
function cargarBancos() {
    fetch("../controlador/obtener_bancos.php")
        .then(response => response.json())
        .then(data => {
            const selectBanco = document.getElementById("selectBanco");
            // Se establece la opción por defecto
            selectBanco.innerHTML = '<option value="">Seleccione un banco</option>';
            
            // Recorrer el array de bancos y agregarlos al select
            data.forEach(banco => {
                selectBanco.innerHTML += `<option value="${banco.id_cuenta_bancaria}">${banco.nombre_banco}</option>`;
            });
        })
        .catch(error => {
            console.error("Error al cargar bancos:", error);
            document.getElementById("selectBanco").innerHTML = '<option value="">Error al cargar bancos</option>';
        });
}

// Ejecutar la función cuando se carga el DOM
document.addEventListener("DOMContentLoaded", cargarBancos);


function mostrarMensaje(mensaje, exito) {
    let mensajeDiv = document.getElementById("mensaje");
    mensajeDiv.textContent = mensaje;
    mensajeDiv.className = "notification " + (exito ? "is-success" : "is-danger");
    mensajeDiv.classList.remove("is-hidden");

    setTimeout(() => {
        mensajeDiv.classList.add("is-hidden");
    }, 3000);
}
