// Función para calcular el IVA según la categoría del proveedor
function calcularIVA(monto, iva, categoriaProveedor) {
    switch (categoriaProveedor) {
        case 'general':
            return monto * (iva / 100);
        case 'reducido':
            return monto * (10.5 / 100); // IVA reducido del 10.5%
        case 'exento':
            return 0; // Exento de IVA
        default:
            return 0; // Por defecto, no se aplica IVA
    }
}

// Función para validar los datos del formulario
function validarDatos(proveedor, monto, fecha, iva, categoriaProveedor) {
    if (!proveedor || !monto || !fecha || !iva || !categoriaProveedor) {
        alert("Todos los campos son obligatorios.");
        return false;
    }
    if (isNaN(monto) || monto <= 0) {
        alert("El monto debe ser un número positivo.");
        return false;
    }
    return true;
}

// Función para manejar el envío del formulario
function manejarEnvioFormulario(event) {
    event.preventDefault();

    // Obtener los valores del formulario
    const proveedor = document.getElementById('proveedor').value;
    const monto = parseFloat(document.getElementById('monto').value);
    const fecha = document.getElementById('fecha').value;
    const iva = parseFloat(document.getElementById('iva').value);
    const categoriaProveedor = document.getElementById('categoria-proveedor').value;

    // Validar los datos
    if (!validarDatos(proveedor, monto, fecha, iva, categoriaProveedor)) {
        return;
    }

    // Calcular el IVA
    const ivaCalculado = calcularIVA(monto, iva, categoriaProveedor);

    // Mostrar los resultados (simulación de guardado en base de datos)
    alert(`Factura guardada:\n
           Proveedor: ${proveedor}\n
           Monto: ${monto}\n
           Fecha: ${fecha}\n
           IVA Calculado: ${ivaCalculado}\n
           Categoría: ${categoriaProveedor}`);

    // Aquí podrías enviar los datos a un backend para guardarlos en la base de datos
    // enviarDatosAlBackend({ proveedor, monto, fecha, ivaCalculado, categoriaProveedor });
}

// Función para inicializar el formulario
function inicializarFormulario() {
    const formulario = document.getElementById('formulario-factura');
    if (formulario) {
        formulario.addEventListener('submit', manejarEnvioFormulario);
    }
}

// Datos simulados de productos (podrían venir de una base de datos)
const productos = [
    { id: 1, nombre: "Producto A", precio: 1000, iva: 21 },
    { id: 2, nombre: "Producto B", precio: 2000, iva: 10.5 },
    { id: 3, nombre: "Producto C", precio: 1500, iva: 0 },
];

// Variables globales
let productosAgregados = [];

// Función para cargar los detalles del producto seleccionado
function cargarProducto() {
    const productoSelect = document.getElementById('producto');
    const producto = productoSelect.options[productoSelect.selectedIndex];
    const precio = producto.getAttribute('data-precio');
    const iva = producto.getAttribute('data-iva');

    // Autocompletar campos en el formulario
    document.getElementById('precio').value = precio;
    document.getElementById('iva').value = iva;
}

// Función para agregar un producto a la tabla
function agregarProducto() {
    const productoSelect = document.getElementById('producto');
    const cantidadInput = document.getElementById('cantidad');
    const descuentoInput = document.getElementById('descuento');

    const productoId = productoSelect.value;
    const cantidad = parseInt(cantidadInput.value);
    const descuento = parseFloat(descuentoInput.value) || 0;

    if (!productoId || cantidad <= 0) {
        alert("Selecciona un producto y una cantidad válida.");
        return;
    }

    // Buscar el producto en la lista de productos
    const producto = productos.find(p => p.id == productoId);

    // Calcular subtotal, IVA y total por ítem
    const subtotal = producto.precio * cantidad;
    const iva = (subtotal * producto.iva) / 100;
    const totalItem = subtotal - descuento + iva;

    // Agregar el producto a la lista
    productosAgregados.push({
        ...producto,
        cantidad,
        descuento,
        subtotal,
        iva,
        totalItem,
    });

    // Actualizar la tabla y los totales
    actualizarTablaProductos();
    calcularTotales();

    // Limpiar campos
    productoSelect.selectedIndex = 0;
    cantidadInput.value = "";
    descuentoInput.value = "";
}

// Función para actualizar la tabla de productos
function actualizarTablaProductos() {
    const tabla = document.getElementById('tabla-productos');
    tabla.innerHTML = "";

    productosAgregados.forEach((producto, index) => {
        const fila = document.createElement('tr');
        fila.innerHTML = `
            <td>${producto.nombre}</td>
            <td>${producto.precio}</td>
            <td>${producto.cantidad}</td>
            <td>${producto.iva}%</td>
            <td>${producto.descuento}</td>
            <td>${producto.subtotal}</td>
            <td>${producto.totalItem}</td>
            <td><button class="button is-danger" onclick="eliminarProducto(${index})">Eliminar</button></td>
        `;
        tabla.appendChild(fila);
    });
}

// Función para eliminar un producto de la lista
function eliminarProducto(index) {
    productosAgregados.splice(index, 1);
    actualizarTablaProductos();
    calcularTotales();
}

// Función para calcular los totales
function calcularTotales() {
    const montoNeto = productosAgregados.reduce((sum, p) => sum + p.subtotal, 0);
    const ivaTotal = productosAgregados.reduce((sum, p) => sum + p.iva, 0);
    const montoTotal = productosAgregados.reduce((sum, p) => sum + p.totalItem, 0);

    document.getElementById('monto-neto').value = montoNeto;
    document.getElementById('iva-total').value = ivaTotal;
    document.getElementById('monto-total').value = montoTotal;
}

// Función para manejar el envío del formulario
document.getElementById('formulario-factura').addEventListener('submit', function (event) {
    event.preventDefault();

    // Obtener datos del formulario
    const proveedor = document.getElementById('proveedor').value;
    const numeroFactura = document.getElementById('numero-factura').value;
    const fechaEmision = document.getElementById('fecha-emision').value;
    const tipoFactura = document.getElementById('tipo-factura').value;
    const moneda = document.getElementById('moneda').value;
    const condicionPago = document.getElementById('condicion-pago').value;
    const ordenCompra = document.getElementById('orden-compra').value;
    const referencia = document.getElementById('referencia').value;
    const formaPago = document.getElementById('forma-pago').value;
    const cuentaContable = document.getElementById('cuenta-contable').value;
    const centroCostos = document.getElementById('centro-costos').value;

    // Mostrar resumen (simulación de guardado)
    alert(`Factura guardada:\n
           Proveedor: ${proveedor}\n
           Número de Factura: ${numeroFactura}\n
           Fecha de Emisión: ${fechaEmision}\n
           Tipo de Factura: ${tipoFactura}\n
           Moneda: ${moneda}\n
           Condición de Pago: ${condicionPago}\n
           Orden de Compra: ${ordenCompra}\n
           Referencia: ${referencia}\n
           Forma de Pago: ${formaPago}\n
           Cuenta Contable: ${cuentaContable}\n
           Centro de Costos: ${centroCostos}\n
           Monto Neto: ${document.getElementById('monto-neto').value}\n
           IVA: ${document.getElementById('iva-total').value}\n
           Monto Total: ${document.getElementById('monto-total').value}`);
});

// Inicializar el formulario cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', inicializarFormulario);

