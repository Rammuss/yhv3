// Mostrar u ocultar campos de cheque según el método de pago
document.getElementById("metodo_pago").addEventListener("change", (event) => {
    const metodoPago = event.target.value;
    const camposCheque = document.getElementById("campos_cheque");

    if (metodoPago === "cheque") {
        camposCheque.style.display = "block";
    } else {
        camposCheque.style.display = "none";
    }
});

// Buscar facturas al presionar el botón de búsqueda
document.getElementById("buscar").addEventListener("click", async () => {
    const numeroFactura = document.getElementById("numero_factura").value.trim();
    const ruc = document.getElementById("ruc").value.trim();
    const fecha = document.getElementById("fecha").value.trim();
    const estado = document.getElementById("estado").value.trim();

    const resultados = document.getElementById("resultados_facturas");
    resultados.innerHTML = ""; // Limpiar resultados previos

    if (numeroFactura || ruc || fecha || estado) {
        try {
            // Crear el payload para enviar como cuerpo de la solicitud POST
            const payload = {
                numero_factura: numeroFactura,
                ruc: ruc,
                fecha_emision: fecha,
                estado_pago: estado
            };

            // Realizar la solicitud POST con el payload
            const response = await fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/buscar_factura.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                throw new Error("Error al buscar las facturas");
            }

            const data = await response.json();

            if (data.length > 0) {
                // Construir la tabla con los resultados
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
                                    <td><button class="button is-small is-info seleccionar-factura" data-id="${factura.id_factura}">Seleccionar</button></td>
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
                        const facturaSeleccionada = data.find(
                            (factura) => factura.id_factura === parseInt(event.target.dataset.id)
                        );
                        seleccionarFactura(
                            facturaSeleccionada.numero_factura,
                            facturaSeleccionada.id_proveedor,
                            facturaSeleccionada.fecha_emision,
                            facturaSeleccionada.estado_pago,
                            facturaSeleccionada.total
                        );
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

// Mostrar la factura seleccionada
function seleccionarFactura(numero, ruc, fecha, estado, total) {
    document.getElementById("factura_seleccionada").style.display = "block";
    document.getElementById("factura_numero").textContent = numero;
    document.getElementById("factura_ruc").textContent = ruc;
    document.getElementById("factura_fecha").textContent = fecha;
    document.getElementById("factura_estado").textContent = estado;
    document.getElementById("factura_total").textContent = total;
}
