// Almacena las facturas seleccionadas
let facturasSeleccionadas = [];

// Función para agregar una factura seleccionada a la tabla y al array
function seleccionarFactura(id, numero, idproveedor, ruc, fecha, estado, total) {
    // Verificar si la factura ya fue seleccionada
    const existe = facturasSeleccionadas.find((factura) => factura.id === id);
    if (existe) {
        alert("Esta factura ya fue seleccionada.");
        return;
    }

    // Agregar la factura al array
    const nuevaFactura = { id, numero, idproveedor, ruc, fecha, estado, total };
    facturasSeleccionadas.push(nuevaFactura);

    // Actualizar la tabla de facturas seleccionadas
    actualizarTablaFacturasSeleccionadas();

    // Actualizar el campo oculto con el JSON
    document.getElementById("facturas_json").value = JSON.stringify(facturasSeleccionadas);
}

// Función para actualizar la tabla visible
function actualizarTablaFacturasSeleccionadas() {
    const tbody = document.getElementById("lista_facturas_seleccionadas");
    tbody.innerHTML = ""; // Limpiar contenido anterior

    facturasSeleccionadas.forEach((factura, index) => {
        const fila = document.createElement("tr");
        fila.innerHTML = `
            <td>${factura.id}</td>
            <td>${factura.numero}</td>
            <td>${factura.idproveedor}</td> <!-- Usar idproveedor correctamente -->
            <td>${factura.ruc}</td>
            <td>${factura.fecha}</td>
            <td>${factura.estado}</td>
            <td>${factura.total} Gs.</td>
            <td>
                <button 
                    class="button is-small is-danger" 
                    onclick="eliminarFacturaSeleccionada(${index})">
                    Eliminar
                </button>
            </td>
        `;
        tbody.appendChild(fila);
    });
}

// Función para eliminar una factura seleccionada
function eliminarFacturaSeleccionada(index) {
    facturasSeleccionadas.splice(index, 1); // Eliminar del array
    actualizarTablaFacturasSeleccionadas();
    document.getElementById("facturas_json").value = JSON.stringify(facturasSeleccionadas);
}

// Buscar facturas al presionar el botón de búsqueda
document.getElementById("buscar").addEventListener("click", async (event) => {
    event.preventDefault();
    const numeroFactura = document.getElementById("numero_factura").value.trim();
    const ruc = document.getElementById("ruc").value.trim();
    const fecha = document.getElementById("fecha").value.trim();
    const estado = document.getElementById("estado").value.trim();

    const resultados = document.getElementById("resultados_facturas");
    resultados.innerHTML = "";

    if (numeroFactura || ruc || fecha || estado) {
        try {
            const response = await fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/buscar_factura.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ numero_factura: numeroFactura, ruc, fecha_emision: fecha, estado_pago: estado }),
            });

            if (!response.ok) throw new Error("Error al buscar las facturas");
            const data = await response.json();

            if (data.length > 0) {
                resultados.innerHTML = `
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Número de Factura</th>
                                <th>Id Proveedor</th>
                                <th>RUC</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map((factura) => `
                                <tr>
                                    <td>${factura.id_factura}</td>
                                    <td>${factura.numero_factura}</td>
                                    <td>${factura.id_proveedor}</td>
                                    <td>${factura.ruc}</td>
                                    <td>${factura.fecha_emision}</td>
                                    <td>${factura.estado_pago}</td>
                                    <td>${factura.total} Gs.</td>
                                    <td>
                                        <button 
                                            class="button is-small is-info seleccionar-factura" 
                                            data-id="${factura.id_factura}"
                                            data-numero="${factura.numero_factura}"
                                            data-idproveedor="${factura.id_proveedor}"
                                            data-ruc="${factura.ruc}"
                                            data-fecha="${factura.fecha_emision}"
                                            data-estado="${factura.estado_pago}"
                                            data-total="${factura.total}">
                                            Seleccionar
                                        </button>
                                    </td>
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                `;

                document.querySelectorAll(".seleccionar-factura").forEach((boton) => {
                    boton.addEventListener("click", (event) => {
                        event.preventDefault();
                        seleccionarFactura(
                            event.target.dataset.id,
                            event.target.dataset.numero,
                            event.target.dataset.idproveedor,
                            event.target.dataset.ruc,
                            event.target.dataset.fecha,
                            event.target.dataset.estado,
                            event.target.dataset.total
                        );
                    });
                });
            } else {
                resultados.innerHTML = `<p class="has-text-centered has-text-danger">No se encontraron facturas.</p>`;
            }
        } catch (error) {
            resultados.innerHTML = `<p class="has-text-centered has-text-danger">Error al buscar: ${error.message}</p>`;
        }
    } else {
        resultados.innerHTML = `<p class="has-text-centered has-text-danger">Ingresa al menos un criterio de búsqueda.</p>`;
    }
});

// Delegación de eventos para detectar el cambio en el select de método de pago
document.body.addEventListener("change", function (event) {
    // Verificar si el evento es del select con id "metodo_pago"
    if (event.target && event.target.id === "metodo_pago") {
        const metodoPago = event.target.value;
        const camposCheque = document.getElementById("campos_cheque");

        // Si se selecciona "Cheque", mostrar los campos relacionados
        if (metodoPago === "cheque") {
            camposCheque.style.display = "block"; // Mostrar los campos de cheque
        } else {
            camposCheque.style.display = "none"; // Ocultar los campos de cheque
        }
    }
});

// Enviar el formulario
// Enviar el formulario
// Enviar el formulario
function enviarFormulario(event) {
    // Prevenir el envío por defecto del formulario
    event.preventDefault();

    // Obtener los valores de los campos que deseas enviar
    const numeroCheque = document.getElementById("numero_cheque").value.trim();
    const beneficiario = document.getElementById("beneficiario").value.trim();
    const montoCheque = document.getElementById("monto_cheque").value.trim();
    const fechaCheque = document.getElementById("fecha_cheque").value.trim();

    // Asumimos que facturasSeleccionadas contiene los datos de las facturas seleccionadas
    const facturas = facturasSeleccionadas;

    // Crear un objeto solo con los campos necesarios y que no estén vacíos
    const datosFormulario = {};

    if (numeroCheque) datosFormulario.numero_cheque = numeroCheque;
    if (beneficiario) datosFormulario.beneficiario = beneficiario;
    if (montoCheque) datosFormulario.monto_cheque = montoCheque;
    if (fechaCheque) datosFormulario.fecha_cheque = fechaCheque;
    if (facturas.length > 0) datosFormulario.facturas = facturas;

    // Llamada a la API para enviar los datos
    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/generar_o_p.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify(datosFormulario), // Enviar solo los campos que contienen datos
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Error al enviar el formulario: No se pudo procesar la solicitud.');
        }
        return response.json();
    })
    .then(data => {
        // Verificamos si la respuesta del servidor tiene el error específico
        if (data.error) {
            mostrarMensaje(data.error, "error");  // Mostrar mensaje de error
        } else {
            // Si no hay error, mostramos el mensaje de éxito
            mostrarMensaje("La orden de pago se generó correctamente.", "success");
        }
    })
    .catch(error => {
        // Aquí atrapamos el error de la solicitud fallida
        console.error("Error en la solicitud:", error);
        mostrarMensaje("Hubo un error al enviar los datos. Revisa la pestaña Network.", "error");
    });
}

// Función para mostrar mensajes en la página (puede ser un contenedor de notificaciones)
function mostrarMensaje(mensaje, tipo) {
    // Crear un contenedor de mensaje si no existe
    let contenedorMensaje = document.getElementById("mensaje-contenedor");
    if (!contenedorMensaje) {
        contenedorMensaje = document.createElement("div");
        contenedorMensaje.id = "mensaje-contenedor";
        document.body.appendChild(contenedorMensaje);
    }

    // Establecer el mensaje y el estilo según el tipo (éxito o error)
    contenedorMensaje.textContent = mensaje;
    if (tipo === "success") {
        contenedorMensaje.style.backgroundColor = "#4CAF50"; // Verde para éxito
        contenedorMensaje.style.color = "white";
    } else if (tipo === "error") {
        contenedorMensaje.style.backgroundColor = "#f44336"; // Rojo para error
        contenedorMensaje.style.color = "white";
    }

    // Establecer estilos CSS para mostrar el mensaje correctamente
    contenedorMensaje.style.position = "fixed";
    contenedorMensaje.style.top = "20px"; // Mostrar el mensaje en la parte superior
    contenedorMensaje.style.left = "50%";
    contenedorMensaje.style.transform = "translateX(-50%)"; // Centrar el mensaje
    contenedorMensaje.style.padding = "15px 25px";
    contenedorMensaje.style.borderRadius = "5px";
    contenedorMensaje.style.fontSize = "16px";
    contenedorMensaje.style.zIndex = "1000"; // Asegurarnos de que se muestre por encima de otros elementos

    // Mostrar el mensaje por 3 segundos y luego ocultarlo
    contenedorMensaje.style.display = "block";
    setTimeout(() => {
        contenedorMensaje.style.display = "none";
    }, 3000);
}

// Asociar el evento al botón
document.getElementById("generarOrdenPago").addEventListener("click", enviarFormulario);
