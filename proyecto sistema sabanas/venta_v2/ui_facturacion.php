    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestión de Ventas</title>
        <link rel="stylesheet" href="styles_venta.css"> <!-- Enlace a tu archivo CSS -->
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f9f9f9;
              
            }

            .container {
                width: 90%;
                max-width: 1200px;
                margin: 20px auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            }

            h1,
            h2 {
                color: #333;
                text-align: center;
            }

            form {
                display: flex;
                flex-direction: column;
                gap: 20px;
                margin-bottom: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            table th,
            table td {
                border: 1px solid #ddd;
                padding: 10px;
                text-align: center;
            }

            button {
                padding: 10px 20px;
                background-color: #007bff;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
            }

            button:hover {
                background-color: #0056b3;
            }

            .modal {
                display: none;
                position: fixed;
                z-index: 1;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                overflow: auto;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .modal-content {
                background-color: #fff;
                margin: 10% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 40%;
                border-radius: 10px;
            }

            .close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
        </style>
        
        <script src="navbar.js"></script>
    </head>

    <body>
    <div id="navbar-container"></div>

        <div id="modalNuevoCliente" class="modal" style="display: none;">
            <div class="modal-content">
                <span class="close">&times;</span>
                <h2>Registrar Nuevo Cliente</h2>
                <form id="formNuevoCliente">
                    <label for="nombre_cliente">Nombre:</label>
                    <input type="text" id="nombre_cliente" name="nombre" required>

                    <label for="apellido_cliente">Apellido:</label>
                    <input type="text" id="apellido_cliente" name="apellido" required>

                    <label for="direccion_cliente">Dirección:</label>
                    <input type="text" id="direccion_cliente" name="direccion">

                    <label for="telefono_cliente">Teléfono:</label>
                    <input type="text" id="telefono_cliente" name="telefono">

                    <label for="ruc_ci_cliente">RUC/CI:</label>
                    <input type="text" id="ruc_ci_cliente" name="ruc_ci" required>

                    <button type="submit">Guardar</button>
                </form>
            </div>
        </div>

        <div class="container">
            <h1>Registro de Ventas</h1>

            <!-- Cabecera de la Venta -->
            <div class="venta-cabecera">
                <h2>Información de la Venta</h2>
                <form id="formVentaCabecera">
                    <div>
                        <label for="cliente">Cliente:</label>

                        <div class="autocomplete">
                            <input type="text" id="buscarCliente" placeholder="Buscar por nombre, apellido o RUC/CI...">
                            <ul id="listaSugerencias"></ul>

                        </div>


                        <button type="button" id="btnNuevoCliente">+ Nuevo Cliente</button>

                    </div>

                    <div>
                        <label for="fecha_venta">Fecha de Venta:</label>
                        <input type="date" id="fecha_venta" name="fecha_venta" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div>
                        <label for="forma_pago">Forma de Pago:</label>
                        <select id="forma_pago" name="forma_pago" required>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="cheque">Cheque</option>
                            <option value="cuotas">Cuotas</option>
                        </select>
                    </div>

                    <!-- Si se elige 'Cuotas', se habilita este campo -->
                    <div id="campoCuotas" style="display: none;">
                        <label for="cantidad_cuotas">Cantidad de Cuotas:</label>
                        <input type="number" id="cantidad_cuotas" name="cantidad_cuotas" min="1" value="1">
                    </div>
                </form>
            </div>

            <!-- Detalle de la Venta -->
            <div>
                <label for="buscarProducto">Buscar Producto</label>
                <select id="buscarProducto" style="width: 100%;"></select>
            </div>
            <div>
                <label for="cantidad">Cantidad</label>
                <input type="number" id="cantidad" min="1" value="1">
            </div>
            <button type="button" onclick="agregarProducto()">Agregar Producto</button>

            <table id="detalleVenta">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>IVA (%)</th>
                        <th>Precio Unitario</th>
                        <th>Cantidad</th>
                        <th>Total</th>

                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Aquí se agregarán las filas dinámicamente -->
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4" style="text-align: right;">Subtotal:</td>
                        <td id="subtotal">0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;">IVA Total:</td>
                        <td id="ivaTotal">0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="4" style="text-align: right;"><strong>Total Compra:</strong></td>
                        <td id="totalCompra"><strong>0.00</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <input type="hidden" id="idsProductosSeleccionados" name="idsProductosSeleccionados" value="[]">

            <!-- Botón para generar la venta -->
            <button type="button" id="btnGenerarVenta">Generar Venta</button>




            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const formaPago = document.getElementById('forma_pago');
                    const campoCuotas = document.getElementById('campoCuotas');
                    const clienteSelect = document.getElementById('cliente');

                    // Mostrar u ocultar el campo de cuotas según la forma de pago seleccionada
                    formaPago.addEventListener('change', function() {
                        if (this.value === 'cuotas') {
                            campoCuotas.style.display = 'block';
                        } else {
                            campoCuotas.style.display = 'none';
                        }
                    });




                });
            </script>

            <script>
                // MODAL ON OFF
                const modal = document.getElementById('modalNuevoCliente');
                const btnNuevoCliente = document.getElementById('btnNuevoCliente');
                const spanClose = document.querySelector('.close');

                // Abrir el modal
                btnNuevoCliente.onclick = function() {
                    modal.style.display = 'block';
                };

                // Cerrar el modal
                spanClose.onclick = function() {
                    modal.style.display = 'none';
                };

                // Cerrar al hacer clic fuera del modal
                window.onclick = function(event) {
                    if (event.target === modal) {
                        modal.style.display = 'none';
                    }
                };
            </script>

            <script>
                formNuevoCliente.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    // Obtener los datos del formulario
                    const formData = new FormData(formNuevoCliente);

                    // Enviar al backend
                    const response = await fetch('../venta_v2/insertar_cliente.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Cliente registrado exitosamente.');
                        // Aquí puedes agregar el nuevo cliente al dropdown si es necesario
                    } else {
                        alert('Error al registrar el cliente: ' + data.message);
                    }
                });
            </script>

            <!-- Campo oculto para guardar el id del cliente -->
            <input type="hidden" id="idClienteSeleccionado" name="idClienteSeleccionado">

            <script>
                // CARGAR LOS CLIENTES EN EL SELECT
                const buscarCliente = document.getElementById('buscarCliente');
                const listaSugerencias = document.getElementById('listaSugerencias');
                const idClienteSeleccionado = document.getElementById('idClienteSeleccionado');

                buscarCliente.addEventListener('input', async () => {
                    const termino = buscarCliente.value.trim();

                    if (termino.length > 2) { // Realiza la búsqueda solo si hay más de 2 caracteres
                        const response = await fetch(`/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v2/obtener_cliente.php?search=${termino}`);
                        const data = await response.json();

                        listaSugerencias.innerHTML = '';

                        if (data.success) {
                            data.clientes.forEach(cliente => {
                                const li = document.createElement('li');
                                li.textContent = `${cliente.nombre} ${cliente.apellido} - RUC/CI: ${cliente.ruc_ci}`;
                                li.dataset.id = cliente.id_cliente;
                                li.addEventListener('click', () => seleccionarCliente(cliente.id_cliente, cliente.nombre, cliente.apellido, cliente.ruc_ci));
                                listaSugerencias.appendChild(li);
                            });
                        }
                    }
                });

                function seleccionarCliente(id, nombre, apellido, ruc_ci) {
                    console.log(`Cliente seleccionado: ${nombre} ${apellido} (RUC/CI: ${ruc_ci})`);
                    buscarCliente.value = `${nombre} ${apellido} - ${ruc_ci}`;
                    listaSugerencias.innerHTML = ''; // Limpia las sugerencias
                    idClienteSeleccionado.value = id; // Guarda el id del cliente en el campo oculto
                }
            </script>





            <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
            <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />

            <script>
                $(document).ready(function() {
                    // Inicializa Select2 con la búsqueda dinámica
                    $('#buscarProducto').select2({
                        ajax: {
                            url: '../venta_v2/buscar_producto.php',
                            dataType: 'json',
                            delay: 250,
                            data: function(params) {
                                return {
                                    query: params.term // Término de búsqueda
                                };
                            },
                            processResults: function(data) {
                                return {
                                    results: data.map(producto => ({
                                        id: producto.id_producto,
                                        text: producto.nombre,
                                        precio: producto.precio_unitario,
                                        tipo_iva: producto.tipo_iva
                                    }))
                                };
                            },
                            cache: true
                        },
                        placeholder: 'Seleccione un producto',
                        minimumInputLength: 1
                    });
                });

                let subtotal = 0;
                let ivaTotal = 0;
                let productosSeleccionados = []; // Almacena los productos seleccionados

                function agregarProducto() {
                    const productoSeleccionado = $('#buscarProducto').select2('data')[0];
                    const cantidad = parseInt(document.getElementById('cantidad').value);

                    if (!productoSeleccionado || cantidad < 1) {
                        alert('Seleccione un producto y una cantidad válida');
                        return;
                    }

                    const precioUnitario = parseFloat(productoSeleccionado.precio);
                    const ivaPorcentaje = parseFloat(productoSeleccionado.tipo_iva);
                    const total = (precioUnitario * cantidad).toFixed(2);
                    const iva = (precioUnitario * cantidad * (ivaPorcentaje / 100)).toFixed(2);

                    // Actualizar subtotal e IVA total
                    subtotal += parseFloat(total);
                    ivaTotal += parseFloat(iva);
                    actualizarTotales();

                    // Agregar el producto al detalle de venta con clases específicas
                    const detalleVenta = document.getElementById('detalleVenta').getElementsByTagName('tbody')[0];
                    const fila = document.createElement('tr');
                    fila.innerHTML = `
            <td class="producto">${productoSeleccionado.text}</td>
            <td class="iva">${ivaPorcentaje}%</td>
            <td class="precio_unitario">${precioUnitario.toFixed(2)}</td>
            <td class="cantidad">${cantidad}</td>
            <td class="total">${total}</td>
            <td><button onclick="eliminarProducto(this, ${productoSeleccionado.id})">Eliminar</button></td>
        `;
                    detalleVenta.appendChild(fila);

                    // Agregar el producto al array de productos seleccionados
                    productosSeleccionados.push({
                        id_producto: productoSeleccionado.id,
                        cantidad: cantidad,
                        precio_unitario: precioUnitario,
                        total: parseFloat(total),
                    });

                    // Actualizar el campo oculto con los productos seleccionados
                    document.getElementById('idsProductosSeleccionados').value = JSON.stringify(productosSeleccionados);

                    // Resetear el formulario
                    $('#buscarProducto').val(null).trigger('change');
                    document.getElementById('cantidad').value = 1;
                }

                function eliminarProducto(btn, idProducto) {
                    const fila = btn.closest('tr');
                    const totalProducto = parseFloat(fila.querySelector('.total').textContent);
                    const ivaProducto = parseFloat(fila.querySelector('.iva').textContent) / 100 * totalProducto;

                    // Actualizar subtotal e IVA total
                    subtotal -= totalProducto;
                    ivaTotal -= ivaProducto;
                    actualizarTotales();

                    // Eliminar del array de productos seleccionados
                    productosSeleccionados = productosSeleccionados.filter(producto => producto.id !== idProducto);

                    // Actualizar el campo oculto con los productos restantes
                    document.getElementById('idsProductosSeleccionados').value = JSON.stringify(productosSeleccionados);

                    // Eliminar la fila de la tabla
                    fila.remove();
                }

                function actualizarTotales() {
                    document.getElementById('subtotal').textContent = subtotal.toFixed(2);
                    document.getElementById('ivaTotal').textContent = ivaTotal.toFixed(2);
                    document.getElementById('totalCompra').textContent = (subtotal + ivaTotal).toFixed(2);
                }
            </script>

            <script>
                function obtenerDatosFormulario() {
                    // Obtener los datos de la cabecera
                    const cabecera = {
                        cliente: document.getElementById('buscarCliente')?.value || "",
                        id_cliente: document.getElementById('idClienteSeleccionado')?.value || "",
                        fecha_venta: document.getElementById('fecha_venta')?.value || "",
                        forma_pago: document.getElementById('forma_pago')?.value || "",
                        cantidad_cuotas: document.getElementById('cantidad_cuotas')?.value || null,
                    };

                    // Obtener los datos del detalle desde el campo oculto
                    const detalle = JSON.parse(document.getElementById('idsProductosSeleccionados').value || "[]");

                    // Retornar el objeto combinado
                    return {
                        cabecera,
                        detalle
                    };
                }
            </script>

            <script>
                document.getElementById('btnGenerarVenta').addEventListener('click', function() {
                    // Llamar a la función que obtiene los datos del formulario
                    const datosVenta = obtenerDatosFormulario();

                    // Mostrar los datos en la consola (opcional, para depuración)
                    console.log('Datos de la cabecera:', datosVenta.cabecera);
                    console.log('Datos del detalle:', datosVenta.detalle);

                    // URL del backend donde se procesarán los datos
                    const url = '../venta_v2/factura_procesar.php'; // Cambia esto por la ruta a tu archivo PHP

                    // Enviar los datos al backend
                    fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(datosVenta) // Convertir el objeto a JSON para el backend
                        })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Error en la respuesta del servidor');
                            }
                            return response.json(); // Suponiendo que el backend devuelve una respuesta JSON
                        })
                        .then(data => {
                            // Manejar la respuesta del servidor
                            console.log('Respuesta del servidor:', data);

                            if (data.success) {
                                alert(data.message); // Muestra mensaje de éxito

                                // Redirige a la página del comprobante
                                if (data.venta_id) {
                                    window.location.href = `comprobante_factura.php?venta_id=${data.venta_id}`;
                                }
                            } else {
                                alert('Error al generar la venta: ' + data.message);
                            }
                        })
                        .catch(error => {
                            // Manejar errores en la solicitud
                            console.error('Error al enviar los datos:', error);
                            alert('Ocurrió un error al generar la venta.');
                        });
                });
            </script>

    </body>

    </html>