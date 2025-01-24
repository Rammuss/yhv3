// Datos simulados de facturas pendientes de pago
const facturasPendientes = [
    { id: 1, proveedor: "Proveedor A", monto: 1000, fecha: "2023-10-01" },
    { id: 2, proveedor: "Proveedor B", monto: 2000, fecha: "2023-10-05" },
    { id: 3, proveedor: "Proveedor C", monto: 1500, fecha: "2023-10-10" },
];

// Función para cargar las facturas pendientes en el formulario
function cargarFacturasPendientes() {
    const select = document.getElementById('facturas-pendientes');
    facturasPendientes.forEach(factura => {
        const option = document.createElement('option');
        option.value = factura.id;
        option.textContent = `Factura #${factura.id} - ${factura.proveedor} - $${factura.monto} - ${factura.fecha}`;
        select.appendChild(option);
    });
}

// Función para generar la orden de pago
function generarOrdenPago() {
    const select = document.getElementById('facturas-pendientes');
    const facturasSeleccionadas = Array.from(select.selectedOptions).map(option => option.value);

    if (facturasSeleccionadas.length === 0) {
        alert("Selecciona al menos una factura para generar la orden de pago.");
        return;
    }

    // Simular el guardado de la orden de pago
    const ordenPago = {
        id: Date.now(), // ID único para la orden de pago
        facturas: facturasSeleccionadas,
        fecha: new Date().toISOString().split('T')[0], // Fecha actual
    };

    alert(`Orden de pago generada:\n
           ID: ${ordenPago.id}\n
           Facturas: ${ordenPago.facturas.join(', ')}\n
           Fecha: ${ordenPago.fecha}`);

    // Aquí podrías enviar los datos a un backend para guardar en la base de datos
    // enviarDatosAlBackend(ordenPago);
}

// Función para simular la generación de un cheque en PDF
function generarChequePDF() {
    alert("Generando cheque en PDF... (esto es una simulación)");
    // En un entorno real, usarías una biblioteca como FPDF o jsPDF para generar el PDF.
}

// Cargar las facturas pendientes al iniciar la página
document.addEventListener('DOMContentLoaded', cargarFacturasPendientes);