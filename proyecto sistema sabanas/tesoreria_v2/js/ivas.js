document.getElementById("buscar").addEventListener("click", () => {
    const numeroFactura = document.getElementById("numero_factura").value.trim();
    const fecha = document.getElementById("fecha").value.trim();

    // Cuerpo de la solicitud
    const payload = {
        numero_factura: numeroFactura,
        fecha: fecha
    };

    fetch("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/buscar_ivas.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(payload)
    })
        .then(response => response.json())
        .then(data => {
            const resultados = document.getElementById("resultados");
            resultados.innerHTML = ""; // Limpiar resultados previos

            let totalIva5 = 0;
            let totalIva10 = 0;

            if (data.length > 0) {
                data.forEach(iva => {
                    // Convertir cadenas a números y manejar posibles valores no numéricos
                    const iva5 = parseFloat(iva.iva_5) || 0;
                    const iva10 = parseFloat(iva.iva_10) || 0;

                    totalIva5 += iva5;
                    totalIva10 += iva10;

                    const row = `
                        <tr>
                            <td>${iva.id_iva}</td>
                            <td>${iva.numero_factura}</td>
                            <td>${iva5.toFixed(2)}</td>
                            <td>${iva10.toFixed(2)}</td>
                            <td>${iva.fecha_generacion}</td>
                        </tr>
                    `;
                    resultados.insertAdjacentHTML("beforeend", row);
                });
                // Agregar una fila al final para mostrar los totales
                const totalRow = `
                    <tr>
                        <td colspan="2"><strong>Totales</strong></td>
                        <td><strong>${totalIva5.toFixed(2)}</strong></td>
                        <td><strong>${totalIva10.toFixed(2)}</strong></td>
                        <td></td>
                    </tr>
                `;
                resultados.insertAdjacentHTML("beforeend", totalRow);
            } else {
                resultados.innerHTML = `<tr><td colspan="5">No se encontraron resultados.</td></tr>`;
            }
        })
        .catch(error => {
            console.error("Error al buscar IVAs:", error);
        });
});
