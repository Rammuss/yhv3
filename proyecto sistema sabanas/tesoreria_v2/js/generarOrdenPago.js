document.addEventListener('DOMContentLoaded', async () => {
    await cargarProvisiones();
    await cargarCuentasBancarias();
});

// Cargar provisiones desde el backend (Versión Corregida)
async function cargarProvisiones() {
    const select = document.getElementById('selectProvision');
    try {
        const response = await fetch('../controlador/listar_provisiones.php');
        const data = await response.json();

        select.innerHTML =
            `<option value="" disabled selected>Selecciona una opción</option>` +
            data.map(prov => `
        <option value="${prov.id_provision}" 
                data-id-proveedor="${prov.id_proveedor}"
                data-nombre="${prov.nombre_proveedor}" 
                data-monto="${prov.monto_provisionado}">
            ${prov.nombre_proveedor} - ${parseFloat(prov.monto_provisionado).toFixed(2)}
        </option>
    `).join('');


        // Actualizar campos al seleccionar
        select.addEventListener('change', () => {
            const opcion = select.options[select.selectedIndex];
            // Mostrar nombre del proveedor
            document.getElementById('inputProveedor').value = opcion.dataset.nombre;
            // Guardar ID en campo oculto
            document.getElementById('hiddenIdProveedor').value = opcion.dataset.idProveedor;
            // Formatear monto a 2 decimales
            document.getElementById('inputMonto').value = parseFloat(opcion.dataset.monto).toFixed(2);
        });

    } catch (error) {
        console.error('Error:', error);
    }
}

// Cargar cuentas bancarias (Sin cambios)
async function cargarCuentasBancarias() {
    const select = document.getElementById('selectCuenta');
    const response = await fetch('../controlador/cuentas_bancarias.php');
    const cuentas = await response.json();

    select.innerHTML = cuentas.map(cuenta => `
        <option value="${cuenta.id_cuenta_bancaria}">
            ${cuenta.nombre_banco} (${cuenta.numero_cuenta})
        </option>
    `).join('');
}

// Guardar orden (Versión Corregida)
async function guardarOrden() {
    const datos = {
        id_provision: document.getElementById('selectProvision').value,
        id_proveedor: document.getElementById('hiddenIdProveedor').value, // Usar campo oculto
        monto: document.getElementById('inputMonto').value,
        metodo_pago: document.getElementById('selectMetodo').value,
        id_cuenta_bancaria: document.getElementById('selectCuenta').value,
        referencia: document.getElementById('inputReferencia').value,
        id_usuario_creacion: 1
    };

    try {
        const response = await fetch('../controlador/generar_o_p.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(datos)
        });

        const resultado = await response.json();
        mostrarNotificacion(resultado.success ? 'Orden registrada' : `Error: ${resultado.error}`, resultado.success);

    } catch (error) {
        mostrarNotificacion('Error de conexión', false);
    }
}

// Mostrar mensajes (Sin cambios)
function mostrarNotificacion(mensaje, esExito) {
    const notificacion = document.getElementById('notificacion');
    notificacion.textContent = mensaje;
    notificacion.classList.remove('is-hidden', 'is-danger', 'is-success');
    notificacion.classList.add(esExito ? 'is-success' : 'is-danger');
    notificacion.classList.remove('is-hidden');
}