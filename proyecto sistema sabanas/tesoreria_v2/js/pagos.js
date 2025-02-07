document.addEventListener('DOMContentLoaded', () => {
    const ordenPagoSelect = document.getElementById('ordenPagoSelect');
    const idCuentaBancariaInput = document.getElementById('idCuentaBancaria');
    const montoInput = document.getElementById('monto');
    const referenciaBancariaInput = document.getElementById('referenciaBancaria');
    const nombreBeneficiarioInput = document.getElementById('nombreBeneficiario');
    const metodoPagoInput = document.getElementById('metodoPago'); // Nuevo campo para método de pago
    const form = document.getElementById('registroPagoForm');

    // Función para cargar las órdenes de pago desde la API
    function cargarOrdenesPago() {
        fetch('../controlador/get_ordenes_pago.php')
            .then(response => response.json())
            .then(data => {
                // Limpiar opciones existentes (excepto la opción "Seleccione...")
                ordenPagoSelect.innerHTML = '<option value="">Seleccione una orden de pago</option>';

                // Guardamos las órdenes en el elemento select para poder acceder a sus datos
                data.forEach(orden => {
                    const option = document.createElement('option');
                    option.value = orden.id_orden_pago;
                    option.textContent = `Orden ${orden.id_orden_pago} - Monto: ${orden.monto}`;

                    // Guardamos los datos adicionales en atributos dataset
                    option.dataset.idCuentaBancaria = orden.id_cuenta_bancaria;
                    option.dataset.monto = orden.monto;
                    option.dataset.referenciaBancaria = orden.referencia_bancaria;
                    option.dataset.nombreBeneficiario = orden.nombre_beneficiario;
                    option.dataset.metodoPago = orden.metodo_pago; // Nuevo dato agregado

                    ordenPagoSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error al cargar las órdenes de pago:', error);
            });
    }

    // Evento para cuando se seleccione una orden de pago
    ordenPagoSelect.addEventListener('change', (e) => {
        const selectedOption = e.target.options[e.target.selectedIndex];
        if (selectedOption && selectedOption.value !== '') {
            // Rellenar los campos automáticamente usando los datos almacenados en dataset
            idCuentaBancariaInput.value = selectedOption.dataset.idCuentaBancaria || '';
            montoInput.value = selectedOption.dataset.monto || '';
            referenciaBancariaInput.value = selectedOption.dataset.referenciaBancaria || '';
            nombreBeneficiarioInput.value = selectedOption.dataset.nombreBeneficiario || '';
            metodoPagoInput.value = selectedOption.dataset.metodoPago || ''; // Rellenar el campo método de pago
        } else {
            // Si no se selecciona nada, limpiar los campos
            idCuentaBancariaInput.value = '';
            montoInput.value = '';
            referenciaBancariaInput.value = '';
            nombreBeneficiarioInput.value = '';
            metodoPagoInput.value = '';
        }
    });

    // Manejo del envío del formulario
form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Verificar que se haya seleccionado una orden de pago
    if (ordenPagoSelect.value === '') {
        alert("Por favor, seleccione una orden de pago.");
        return;
    }

    // Preparar los datos a enviar
    const formData = {
        id_orden_pago: parseInt(ordenPagoSelect.value),
        id_cuenta_bancaria: parseInt(idCuentaBancariaInput.value),
        monto: parseFloat(montoInput.value),
        referencia_bancaria: referenciaBancariaInput.value,
        nombre_beneficiario: nombreBeneficiarioInput.value,
        metodo_pago: metodoPagoInput.value // Incluir método de pago ("Cheque", "Transferencia" o "Efectivo")
    };

    try {
        // Realizar la petición a la API para registrar el pago
        const response = await fetch('../controlador/pagos_registrar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (data.success) {
            alert("Pago registrado con éxito.");
            form.reset();

            // Reiniciar los campos de solo lectura
            idCuentaBancariaInput.value = '';
            montoInput.value = '';
            referenciaBancariaInput.value = '';
            nombreBeneficiarioInput.value = '';
            metodoPagoInput.value = ''; // Limpiar el campo método de pago

            // Si se generó un cheque, preguntar por impresión
            if (data.id_cheque) {
                const confirmarImpresion = confirm("El cheque ha sido registrado. ¿Desea imprimirlo?");
                if (confirmarImpresion) {
                    window.location.href = `../controlador/cheque_imprimir.php?id_cheque=${data.id_cheque}`;
                }
            }
            
            // Para Transferencia y Efectivo, preguntar si desea imprimir el comprobante
            if (data.imprimir_comprobante) {
                if (formData.metodo_pago === "Transferencia") {
                    const confirmarComprobante = confirm("El pago por transferencia ha sido registrado. ¿Desea imprimir el comprobante?");
                    if (confirmarComprobante) {
                        window.location.href = `../controlador/transfer_banco_imprimir.php?id_pago=${data.id_pago}`;
                    }
                } else if (formData.metodo_pago === "Efectivo") {
                    const confirmarComprobante = confirm("El pago en efectivo ha sido registrado. ¿Desea imprimir el comprobante?");
                    if (confirmarComprobante) {
                        window.location.href = `../controlador/efectivo_comprobante.php?id_pago=${data.id_pago}`;
                    }
                }
            }
        } else {
            alert("Error al registrar el pago: " + data.message);
        }
    } catch (error) {
        console.error("Error en la petición:", error);
        alert("Error en la petición.");
    }
});



    // Cargar las órdenes de pago al iniciar
    cargarOrdenesPago();
});
