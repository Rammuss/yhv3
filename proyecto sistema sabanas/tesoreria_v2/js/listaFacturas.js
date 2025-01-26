// Función para buscar facturas
async function buscarFacturas() {
    // Obtener los criterios de búsqueda
    const fecha = document.getElementById("fecha-busqueda").value;
    const estado = document.getElementById("estado-busqueda").value;
    const numeroFactura = document.getElementById("numero-factura-busqueda").value;
    const proveedor = document.getElementById("proveedor-busqueda").value;

    // Construir la URL con los parámetros de búsqueda
    const url = new URL("/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/listar_facturas.php", window.location.origin);
    url.searchParams.append("fecha", fecha);
    url.searchParams.append("estado", estado);
    url.searchParams.append("numero_factura", numeroFactura);
    url.searchParams.append("proveedor", proveedor);

    try {
        // Realizar la solicitud al backend
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error("Error al buscar facturas");
        }

        // Obtener los datos en formato JSON
        const facturas = await response.json();

        // Mostrar las facturas en la tabla
        mostrarFacturas(facturas);
    } catch (error) {
        console.error("Error:", error);
        alert("Hubo un error al buscar facturas. Inténtalo de nuevo.");
    }
}

// Función para mostrar las facturas en la tabla
function mostrarFacturas(facturas) {
    const tabla = document.getElementById("tabla-facturas");
    tabla.innerHTML = ""; // Limpiar la tabla

    // Recorrer las facturas y agregarlas a la tabla
    facturas.forEach(factura => {
        const fila = document.createElement("tr");

        // Determinar el estado del botón "Generar Provisión"
        const botonProvision = factura.provision_generada === 't'
            ? `<button class="button is-light" disabled>Provisionada</button>`
            : `<button class="button is-success generar-provision" data-id="${factura.id_factura}">Generar Provisión</button>`;

        // Determinar el estado del botón "Generar IVA"
        const botonIva = factura.iva_generado === 't'
            ? `<button class="button is-light" disabled>IVA Generado</button>`
            : `<button class="button is-warning generar-iva" data-id="${factura.id_factura}">Generar IVA</button>`;

        // Mostrar el estado de la provisión y el IVA en nuevas columnas
        const estadoProvision = factura.provision_generada === 't' ? "Sí" : "No";
        const estadoIva = factura.iva_generado === 't' ? "Sí" : "No";

        fila.innerHTML = `
            <td>${factura.numero_factura}</td>
            <td>${factura.proveedor}</td>
            <td>${factura.fecha_emision}</td>
            <td>${factura.total.toLocaleString()}</td>
            <td>${factura.estado_pago}</td>
            <td>${estadoProvision}</td> <!-- Columna para el estado de la provisión -->
            <td>${estadoIva}</td> <!-- Columna para el estado del IVA -->
            <td>
                <button class="button is-info" onclick="verDetalle(${factura.id_factura})">Ver Detalle</button>
                <button class="button is-danger" onclick="eliminarFactura(${factura.id_factura})">Eliminar</button>
                ${botonProvision}
                ${botonIva}
            </td>
        `;

        tabla.appendChild(fila);
    });
}

// Función para ver el detalle de una factura (placeholder)
function verDetalle(idFactura) {
    alert(`Ver detalle de la factura con ID: ${idFactura}`);
}

// Función para eliminar una factura (placeholder)
function eliminarFactura(idFactura) {
    if (confirm("¿Estás seguro de eliminar esta factura?")) {
        alert(`Eliminar factura con ID: ${idFactura}`);
    }
}

// Asignar la función al botón de búsqueda
document.getElementById("formulario-busqueda").addEventListener("submit", function (event) {
    event.preventDefault(); // Evitar que el formulario se envíe de forma tradicional
    buscarFacturas(); // Llamar a la función para buscar facturas
});

//////

// Función para generar una provisión
async function generarProvision(idFactura) {
    try {
        // Enviar la solicitud al backend
        const response = await fetch(`/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/generar_provision.php?id_factura=${idFactura}`);
        const data = await response.json();

        // Mostrar la respuesta del backend
        if (data.message) {
            alert(data.message); // Puedes usar un modal o un toast en lugar de un alert
            console.log("Provisión generada:", data.provision);
        } else {
            alert("Error al generar la provisión.");
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Ocurrió un error al generar la provisión.");
    }
}



// Asignar el evento a la tabla (delegación de eventos)
document.addEventListener("DOMContentLoaded", function () {
    const tabla = document.getElementById("tabla-facturas");

    tabla.addEventListener("click", function (event) {
        // Verificar si el clic provino de un botón con la clase "generar-provision"
        if (event.target.classList.contains("generar-provision")) {
            const idFactura = event.target.getAttribute("data-id"); // Obtener el id_factura del atributo data-id
            if (idFactura) {
                generarProvision(idFactura); // Llamar a la función para generar la provisión
            } else {
                alert("No se pudo obtener el ID de la factura.");
            }
        }
    });
});


// Función para generar IVA
async function generarIva(idFactura, boton) {
    try {
        // Enviar la solicitud al backend
        const response = await fetch(`/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/controlador/generar_iva.php?id_factura=${idFactura}`);
        const data = await response.json();

        // Mostrar la respuesta del backend
        if (data.message) {
            alert(data.message); // Puedes usar un modal o un toast en lugar de un alert
            console.log("IVA generado:", data.iva_generado);

            // Deshabilitar el botón después de generar el IVA
            boton.disabled = true;
            boton.textContent = "IVA Generado"; // Cambiar el texto del botón
            boton.classList.remove("is-warning"); // Cambiar el estilo del botón
            boton.classList.add("is-light"); // Cambiar el estilo del botón
        } else {
            alert("Error al generar el IVA.");
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Ocurrió un error al generar el IVA.");
    }
}

// Asignar el evento a la tabla (delegación de eventos)
document.addEventListener("DOMContentLoaded", function () {
    const tabla = document.getElementById("tabla-facturas");

    tabla.addEventListener("click", function (event) {
        // Verificar si el clic provino de un botón con la clase "generar-iva"
        if (event.target.classList.contains("generar-iva")) {
            const idFactura = event.target.getAttribute("data-id"); // Obtener el id_factura del atributo data-id
            const boton = event.target; // Obtener el botón que se hizo clic

            if (idFactura) {
                generarIva(idFactura, boton); // Llamar a la función para generar el IVA
            } else {
                alert("No se pudo obtener el ID de la factura.");
            }
        }
    });
});