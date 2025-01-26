// Función para agregar un producto a la tabla
function agregarProducto() {
    const nombre = document.getElementById('nombre-producto').value;
    const precio = parseFloat(document.getElementById('precio-unitario').value);
    const cantidad = parseInt(document.getElementById('cantidad').value);
    const tipoIva = parseInt(document.getElementById('tipo-iva').value);
    const descuento = parseFloat(document.getElementById('descuento').value) || 0;

    // Validar campos
    if (!nombre || !precio || !cantidad) {
        alert("Por favor, complete todos los campos obligatorios.");
        return;
    }

    // Calcular subtotal y total por ítem
    const subtotal = (precio * cantidad) - descuento;
    const iva = (tipoIva === 5) ? subtotal * 0.05 : subtotal * 0.10;
    const totalItem = subtotal + iva;

    // Agregar fila a la tabla
    const tabla = document.getElementById('tabla-productos');
    const fila = tabla.insertRow();

    fila.innerHTML = `
        <td>${nombre}</td>
        <td>${precio.toFixed(2)}</td>
        <td>${cantidad}</td>
        <td>${tipoIva === 5 ? iva.toFixed(2) : '0.00'}</td>
        <td>${tipoIva === 10 ? iva.toFixed(2) : '0.00'}</td>
        <td>${descuento.toFixed(2)}</td>
        <td>${subtotal.toFixed(2)}</td>
        <td>${totalItem.toFixed(2)}</td>
        <td><button class="button is-danger" onclick="eliminarProducto(this)">Eliminar</button></td>
    `;

    // Calcular montos totales
    calcularMontos();

    // Limpiar campos del formulario
    document.getElementById('nombre-producto').value = '';
    document.getElementById('precio-unitario').value = '';
    document.getElementById('cantidad').value = '';
    document.getElementById('descuento').value = '';
}

// Función para eliminar un producto de la tabla
function eliminarProducto(boton) {
    const fila = boton.closest('tr');
    fila.remove();
    calcularMontos(); // Recalcular montos después de eliminar
}

// Función para calcular los montos totales
function calcularMontos() {
    const filas = document.querySelectorAll('#tabla-productos tr');
    let iva5 = 0;
    let iva10 = 0;
    let montoTotal = 0;

    filas.forEach(fila => {
        const iva5Valor = parseFloat(fila.cells[3].textContent) || 0;
        const iva10Valor = parseFloat(fila.cells[4].textContent) || 0;
        const totalItem = parseFloat(fila.cells[7].textContent) || 0;

        iva5 += iva5Valor;
        iva10 += iva10Valor;
        montoTotal += totalItem;
    });

    // Actualizar los campos de totales
    document.getElementById('iva-5').value = iva5.toFixed(2);
    document.getElementById('iva-10').value = iva10.toFixed(2);
    document.getElementById('monto-total').value = montoTotal.toFixed(2);
}


// Para buscar proveedor 


// Función para buscar proveedores en el backend
async function buscarProveedor() {
    const input = document.getElementById("buscar-proveedor").value.trim(); // Obtener el valor del campo de búsqueda
    const lista = document.getElementById("lista-proveedores");
    lista.innerHTML = ""; // Limpiar la lista de resultados

    if (input.length === 0) {
        lista.style.display = "none"; // Ocultar la lista si no hay texto en la búsqueda
        return;
    }

    try {
        // Hacer una solicitud GET al backend para buscar proveedores
        const response = await fetch(`../controlador/buscar_proveedores.php?q=${encodeURIComponent(input)}`);
        if (!response.ok) {
            throw new Error("Error al buscar proveedores");
        }

        const proveedores = await response.json(); // Obtener los resultados en formato JSON

        // Mostrar los resultados en la lista
        if (proveedores.length > 0) {
            proveedores.forEach(proveedor => {
                const item = document.createElement("div");
                item.textContent = `${proveedor.nombre} (${proveedor.ruc})`;
                item.onclick = () => seleccionarProveedor(proveedor); // Seleccionar proveedor al hacer clic
                lista.appendChild(item);
            });
            lista.style.display = "block"; // Mostrar la lista
        } else {
            lista.style.display = "none"; // Ocultar la lista si no hay resultados
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Hubo un error al buscar proveedores. Inténtalo de nuevo.");
    }
}

// Función para seleccionar un proveedor y autocompletar los campos
function seleccionarProveedor(proveedor) {
    document.getElementById("proveedor").value = proveedor.nombre; // Autocompletar nombre
    document.getElementById("ruc-proveedor").value = proveedor.ruc; // Autocompletar RUC
    document.getElementById("id_proveedor").value = proveedor.id_proveedor; // asignar el id 
    document.getElementById("lista-proveedores").style.display = "none"; // Ocultar la lista
}

// Ocultar la lista de resultados al hacer clic fuera de ella
document.addEventListener("click", (event) => {
    const lista = document.getElementById("lista-proveedores");
    if (event.target.id !== "buscar-proveedor") {
        lista.style.display = "none";
    }
});


// ENVIAR FORM



// Función para enviar los datos al backend
async function enviarFactura() {
    // Obtener los datos de la cabecera de la factura
    const cabecera = {
        numero_factura: document.getElementById("numero-factura").value,
        id_proveedor: document.getElementById("id_proveedor").value, // Asumo que el RUC es el ID del proveedor
        fecha_emision: document.getElementById("fecha-emision").value,
        iva_5: parseFloat(document.getElementById("iva-5").value || 0),
        iva_10: parseFloat(document.getElementById("iva-10").value || 0),
        descuento: parseFloat(document.getElementById("descuento").value || 0),
        total: parseFloat(document.getElementById("monto-total").value || 0),
        estado_pago: "Pendiente", // Estado por defecto
        id_usuario_creacion: 1, // Aquí debes obtener el ID del usuario logueado
    };

    // Obtener los detalles de los productos agregados
    const detalles = [];
    const filas = document.querySelectorAll("#tabla-productos tr");
    filas.forEach(fila => {
        const celdas = fila.querySelectorAll("td");
        if (celdas.length > 0) {
            detalles.push({
                descripcion: celdas[0].textContent, // Nombre del producto
                cantidad: parseFloat(celdas[2].textContent), // Cantidad
                precio_unitario: parseFloat(celdas[1].textContent), // Precio unitario
            });
        }
    });

    // Crear el objeto final para enviar al backend
    const factura = {
        ...cabecera,
        detalles: detalles,
    };

    try {
        // Enviar los datos al backend usando fetch
        const response = await fetch("../controlador/registrar_factura.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(factura),
        });

        if (!response.ok) {
            throw new Error("Error al enviar la factura");
        }

        const data = await response.json();
        if (data.success) {
            mostrarToast("Factura guardada correctamente", true); // Toast de éxito
            // Limpiar el formulario después de guardar
            document.getElementById("formulario-factura").reset();
            document.getElementById("tabla-productos").innerHTML = "";
        } else {
            mostrarToast("Error: " + data.error, false); // Toast de error
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Hubo un error al guardar la factura. Inténtalo de nuevo.");
    }
}

// Asignar la función al evento submit del formulario
document.getElementById("formulario-factura").addEventListener("submit", function (event) {
    event.preventDefault(); // Evitar que el formulario se envíe de forma tradicional
    enviarFactura(); // Llamar a la función para enviar los datos
});



// PARA EL TOAST MENSAJITO 
// Función para mostrar el toast
function mostrarToast(mensaje, esExito = true) {
    const toast = document.getElementById("toast");
    const mensajeToast = document.getElementById("mensaje-toast");
    const notificacion = toast.querySelector(".notification");

    // Configurar el mensaje y el estilo del toast
    mensajeToast.textContent = mensaje;
    toast.classList.remove("is-hidden");

    if (esExito) {
        notificacion.classList.remove("is-danger");
        notificacion.classList.add("is-success");
    } else {
        notificacion.classList.remove("is-success");
        notificacion.classList.add("is-danger");
    }

    // Ocultar el toast después de 5 segundos
    setTimeout(() => {
        toast.classList.add("is-hidden");
    }, 5000);
}

// Función para cerrar el toast manualmente
function cerrarToast() {
    const toast = document.getElementById("toast");
    toast.classList.add("is-hidden");
}