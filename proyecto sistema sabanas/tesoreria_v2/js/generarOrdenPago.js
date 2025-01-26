// Datos simulados de facturas pendientes de pago
const facturasPendientes = [
    { id: 1, numero: "F-0001", proveedor: "Proveedor A", monto: 1000, fecha: "2023-10-01" },
    { id: 2, numero: "F-0002", proveedor: "Proveedor B", monto: 2000, fecha: "2023-10-05" },
    { id: 3, numero: "F-0003", proveedor: "Proveedor C", monto: 1500, fecha: "2023-10-10" },
];

// Datos simulados de órdenes de pago
let ordenesPago = [];

// Función para cargar las facturas pendientes en el formulario
function cargarFacturasPendientes() {
    const select = document.getElementById('facturas-pendientes');
    facturasPendientes.forEach(factura => {
        const option = document.createElement('option');
        option.value = factura.id;
        option.textContent = `Factura #${factura.numero} - ${factura.proveedor} - $${factura.monto}`;
        select.appendChild(option);
    });
}

// Función para mostrar u ocultar los campos de cheque
function mostrarCamposCheque() {
    const metodoPago = document.getElementById('metodo-pago').value;
    const camposCheque = document.getElementById('campos-cheque');

    if (metodoPago === "cheque") {
        camposCheque.style.display = "block";
    } else {
        camposCheque.style.display = "none";
    }
}

// Función para generar la orden de pago
function generarOrdenPago() {
    const select = document.getElementById('facturas-pendientes');
    const metodoPago = document.getElementById('metodo-pago').value;

    const facturasSeleccionadas = Array.from(select.selectedOptions).map(option => option.value);

    if (facturasSeleccionadas.length === 0) {
        alert("Selecciona al menos una factura para generar la orden de pago.");
        return;
    }

    // Datos adicionales para cheques
    let datosCheque = {};
    if (metodoPago === "cheque") {
        const fechaCheque = document.getElementById('fecha-cheque').value;
        const montoCheque = document.getElementById('monto-cheque').value;
        const numeroCheque = document.getElementById('numero-cheque').value;

        if (!fechaCheque || !montoCheque || !numeroCheque) {
            alert("Completa todos los campos del cheque.");
            return;
        }

        datosCheque = {
            fechaCheque,
            montoCheque,
            numeroCheque,
        };
    }

    // Crear la orden de pago
    const ordenPago = {
        id: Date.now(), // ID único para la orden de pago
        facturas: facturasSeleccionadas,
        metodoPago: metodoPago,
        fecha: new Date().toISOString().split('T')[0], // Fecha actual
        datosCheque: metodoPago === "cheque" ? datosCheque : null, // Datos del cheque (si aplica)
    };

    // Agregar la orden de pago a la lista
    ordenesPago.push(ordenPago);

    alert(`Orden de pago generada:\n
           ID: ${ordenPago.id}\n
           Facturas: ${ordenPago.facturas.join(', ')}\n
           Método de Pago: ${ordenPago.metodoPago}\n
           Fecha: ${ordenPago.fecha}\n
           ${ordenPago.metodoPago === "cheque" ? `Datos del Cheque:\n
           Fecha: ${ordenPago.datosCheque.fechaCheque}\n
           Monto: $${ordenPago.datosCheque.montoCheque}\n
           Número: ${ordenPago.datosCheque.numeroCheque}` : ''}`);

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