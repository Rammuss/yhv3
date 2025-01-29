// Almacena las facturas seleccionadas
let facturasSeleccionadas = [];

// Función para agregar una factura seleccionada a la tabla y al array
function seleccionarFactura(numero, ruc, fecha, estado, total) {
    // Verificar si la factura ya fue seleccionada
    const existe = facturasSeleccionadas.find((factura) => factura.numero === numero);
    if (existe) {
        alert("Esta factura ya fue seleccionada.");
        return;
    }

    // Agregar la factura al array
    const nuevaFactura = { numero, ruc, fecha, estado, total };
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
            <td>${factura.numero}</td>
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
    // Eliminar del array
    facturasSeleccionadas.splice(index, 1);

    // Actualizar la tabla visible
    actualizarTablaFacturasSeleccionadas();

    // Actualizar el campo oculto con el JSON
    document.getElementById("facturas_json").value = JSON.stringify(facturasSeleccionadas);
}

// Buscar facturas al presionar el botón de búsqueda
document.getElementById("buscar").addEventListener("click", async (event) => {
    event.preventDefault(); // Evitar el comportamiento predeterminado del botón

    const numeroFactura = document.getElementById("numero_factura").value.trim();
    const ruc = document.getElementById("ruc").value.trim();
    const fecha = document.getElementById("fecha").value.trim();
    const estado = document.getElementById("estado").value.trim();

    const resultados = document.getElementById("resultados_facturas");
    resultados.innerHTML = ""; // Limpiar resultados previos

    if (numeroFactura || ruc || fecha || estado) {
        try {
            const payload = {
                numero_factura: numeroFactura,
                ruc: ruc,
                fecha_emision: fecha,
                estado_pago: estado,
            };

            const response = await fetch(
                "/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/buscar_factura.php",
                {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(payload),
                }
            );

            if (!response.ok) throw new Error("Error al buscar las facturas");

            const data = await response.json();

            if (data.length > 0) {
                resultados.innerHTML = `
                    <table class="table is-fullwidth is-striped">
                        <thead>
                            <tr>
                                <th>Número de Factura</th>
                                <th>RUC</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data
                        .map(
                            (factura) => ` 
                                <tr>
                                    <td>${factura.numero_factura}</td>
                                    <td>${factura.id_proveedor}</td>
                                    <td>${factura.fecha_emision}</td>
                                    <td>${factura.estado_pago}</td>
                                    <td>${factura.total} Gs.</td>
                                    <td>
                                        <button 
                                            class="button is-small is-info seleccionar-factura" 
                                            data-numero="${factura.numero_factura}"
                                            data-ruc="${factura.id_proveedor}"
                                            data-fecha="${factura.fecha_emision}"
                                            data-estado="${factura.estado_pago}"
                                            data-total="${factura.total}">
                                            Seleccionar
                                        </button>
                                    </td>
                                </tr>
                            `
                        )
                        .join("")}
                        </tbody>
                    </table>
                `;

                // Agregar eventos a los botones "Seleccionar"
                document.querySelectorAll(".seleccionar-factura").forEach((boton) => {
                    boton.addEventListener("click", (event) => {
                        event.preventDefault(); // Prevenir cualquier comportamiento predeterminado
                        const numero = event.target.dataset.numero;
                        const ruc = event.target.dataset.ruc;
                        const fecha = event.target.dataset.fecha;
                        const estado = event.target.dataset.estado;
                        const total = event.target.dataset.total;

                        seleccionarFactura(numero, ruc, fecha, estado, total);
                    });
                });
            } else {
                resultados.innerHTML = `<p class="has-text-centered has-text-danger">No se encontraron facturas con los datos ingresados.</p>`;
            }
        } catch (error) {
            resultados.innerHTML = `<p class="has-text-centered has-text-danger">Error al buscar las facturas: ${error.message}</p>`;
        }
    } else {
        resultados.innerHTML = `<p class="has-text-centered has-text-danger">Por favor, ingresa al menos un criterio de búsqueda.</p>`;
    }
});

// Delegación de eventos para detectar el cambio en el select de método de pago
document.body.addEventListener("change", function(event) {
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
document.addEventListener("DOMContentLoaded", function () {
    const formulario = document.getElementById('ordenPagoForm');

    formulario.addEventListener('submit', function (e) {
        e.preventDefault();  // Prevenir el comportamiento por defecto de envío de formulario

        // Obtener los datos del formulario
        const formData = new FormData(formulario);

        // Asegúrate de que las facturas seleccionadas estén incluidas
        formData.append("facturas", JSON.stringify(facturasSeleccionadas));

        // Enviar los datos al backend con fetch
        fetch('/ruta/del/backend', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())  // Suponiendo que el backend responde con JSON
        .then(data => {
            if (data.success) {
                alert('Orden de pago generada exitosamente');
                // Aquí puedes redirigir o limpiar el formulario si es necesario
            } else {
                alert('Hubo un error al generar la orden de pago');
            }
        })
        .catch(error => {
            console.error('Error en la solicitud:', error);
            alert('Hubo un error en la conexión con el servidor');
        });
    });
});
