document.addEventListener("DOMContentLoaded", function() {
    fetch("../controlador/get_bancos.php")
        .then(response => response.json())
        .then(data => {
            const bancoSelect = document.getElementById("bancoSelect");
            data.forEach(banco => {
                const option = document.createElement("option");
                option.value = banco.id;
                option.textContent = banco.nombre;
                bancoSelect.appendChild(option);
            });
        })
        .catch(error => console.error("Error al obtener los bancos:", error));

    document.getElementById("formExtracto").addEventListener("submit", function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        fetch("../controlador/subir_extracto_banco.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const mensaje = document.getElementById("mensaje");
            mensaje.textContent = data.mensaje;
            mensaje.classList.remove("is-hidden");
            mensaje.classList.add(data.exito ? "is-success" : "is-danger");
        })
        .catch(error => console.error("Error al subir el extracto:", error));
    });
});