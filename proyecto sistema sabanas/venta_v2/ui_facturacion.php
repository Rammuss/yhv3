    <!DOCTYPE html>
    <html lang="es">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Gestión de Venta</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
        <script src="navbar.js"></script>
        <link rel="stylesheet" href="../venta_v2/styles_venta.css">
    </head>

    <body>
        <div id="navbar-container"></div>

        <!-- Modal Nuevo Cliente -->
        <div id="modalNuevoCliente" class="modal">
            <div class="modal-background"></div>
            <div class="modal-content">
                <div class="box">
                    <span class="modal-close is-large" aria-label="close">&times;</span>
                    <h2 class="title is-4">Registrar Nuevo Cliente</h2>
                    <form id="formNuevoCliente">
                        <div class="field">
                            <label class="label" for="nombre_cliente">Nombre:</label>
                            <div class="control">
                                <input class="input" type="text" id="nombre_cliente" name="nombre" required>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="apellido_cliente">Apellido:</label>
                            <div class="control">
                                <input class="input" type="text" id="apellido_cliente" name="apellido" required>
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="direccion_cliente">Dirección:</label>
                            <div class="control">
                                <input class="input" type="text" id="direccion_cliente" name="direccion">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="telefono_cliente">Teléfono:</label>
                            <div class="control">
                                <input class="input" type="text" id="telefono_cliente" name="telefono">
                            </div>
                        </div>

                        <div class="field">
                            <label class="label" for="ruc_ci_cliente">RUC/CI:</label>
                            <div class="control">
                                <input class="input" type="text" id="ruc_ci_cliente" name="ruc_ci" required>
                            </div>
                        </div>

                        <div class="field">
                            <div class="control">
                                <button class="button is-primary" type="submit">Guardar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Contenedor principal -->
        <div class="container">
            <h1 class="title is-3 has-text-centered">Registro de Ventas</h1>

            <!-- Cabecera de la Venta -->
            <div class="box">
                <h2 class="title is-4">Información de la Venta</h2>
                <form id="formVentaCabecera">
                    <div class="field">
                        <label class="label" for="cliente">Cliente:</label>
                        <div class="control">
                            <input class="input" type="text" id="buscarCliente" placeholder="Buscar por nombre, apellido o RUC/CI...">
                        </div>
                        <button type="button" class="button is-link" id="btnNuevoCliente">+ Nuevo Cliente</button>

                        <!-- Contenedor para las sugerencias -->
                        <ul id="listaSugerencias" class="sugerencias-lista" style="list-style-type: none; padding-left: 0;"></ul>
                    </div>

                    <div class="field">
                        <label class="label" for="nota_credito_id">Número de Nota de Crédito:</label>
                        <div class="control">
                            <input class="input" type="text" id="nota_credito_id" name="nota_credito_id" placeholder="Ingrese el número de la nota de crédito">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="fecha_venta">Fecha de Venta:</label>
                        <div class="control">
                            <input class="input" type="date" id="fecha_venta" name="fecha_venta" value="<?= date('Y-m-d') ?>" required>
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="forma_pago">Forma de Pago:</label>
                        <div class="control">
                            <div class="select">
                                <select id="forma_pago" name="forma_pago" required>
                                    <option value="contado">Contado</option>
                                    <option value="cuotas">Cuotas</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="field" id="campoCuotas" style="display: none;">
                        <label class="label" for="cantidad_cuotas">Cantidad de Cuotas:</label>
                        <div class="control">
                            <input class="input" type="number" id="cantidad_cuotas" name="cantidad_cuotas" min="1" value="1">
                        </div>
                    </div>

                    <div class="field">
                        <label class="label" for="metodo_pago">Método de Pago:</label>
                        <div class="control">
                            <div class="select">
                                <select id="metodo_pago" name="metodo_pago" required>
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="cheque">Cheque</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>


            <!-- Detalle de la Venta -->
            <div class="field">
                <label class="label" for="buscarProducto">Buscar Producto:</label>
                <div class="control">
                    <select id="buscarProducto" class="select is-fullwidth"></select>
                </div>
            </div>

            <div class="field">
                <label class="label" for="cantidad">Cantidad:</label>
                <div class="control">
                    <input class="input" type="number" id="cantidad" min="1" value="1">
                </div>
            </div>

            <button class="button is-info" type="button" onclick="agregarProducto()">Agregar Producto</button>

            <!-- Tabla de Detalle de Venta -->
            <table class="table is-fullwidth is-bordered is-striped is-hoverable" id="detalleVenta">
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
                        <td colspan="4" class="has-text-right">Subtotal:</td>
                        <td id="subtotal">0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="has-text-right">IVA Total:</td>
                        <td id="ivaTotal">0.00</td>
                        <td></td>
                    </tr>
                    <tr>
                        <td colspan="4" class="has-text-right"><strong>Total Compra:</strong></td>
                        <td id="totalCompra"><strong>0.00</strong></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>

            <input type="hidden" id="idsProductosSeleccionados" name="idsProductosSeleccionados" value="[]">

            <button class="button is-primary" type="button" id="btnGenerarVenta">Generar Venta</button>
        </div>
    </body>





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
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.getElementById('modalNuevoCliente');
            const btnNuevoCliente = document.getElementById('btnNuevoCliente');
            const spanClose = document.querySelector('.modal-close'); // Asegúrate de que la clase sea 'modal-close'

            // Abrir el modal
            btnNuevoCliente.onclick = function() {
                modal.classList.add('is-active'); // Usamos 'is-active' en lugar de manipular display directamente
            };

            // Cerrar el modal
            spanClose.onclick = function() {
                modal.classList.remove('is-active'); // Eliminamos 'is-active' para cerrar el modal
            };

            // Cerrar al hacer clic fuera del modal
            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.classList.remove('is-active'); // Eliminamos 'is-active' para cerrar el modal
                }
            };
        });
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
                const response = await fetch(`../venta_v2/obtener_cliente.php?search=${termino}`);
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
                metodo_pago: document.getElementById('metodo_pago')?.value || "", // Capturar el valor del campo método de pago
                cantidad_cuotas: document.getElementById('cantidad_cuotas')?.value || null,
                nota_credito_id: document.getElementById('nota_credito_id')?.value || null, // Capturar el valor del campo nota de crédito
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
                        // Si la respuesta no es exitosa, lanzamos un error con el mensaje del backend
                        return response.json().then(errorData => {
                            throw new Error(errorData.message || 'Error en la respuesta del servidor');
                        });
                    }
                    return response.json(); // Convertir la respuesta a JSON
                })
                .then(data => {
                    console.log('Respuesta del servidor (JSON):', data);

                    if (data.success) {
                        alert(data.message); // Muestra mensaje de éxito

                        // Redirige a la página del comprobante si tiene venta_id
                        if (data.venta_id) {
                            window.location.href = `comprobante_factura.php?venta_id=${data.venta_id}`;
                        }
                    } else {
                        // Si el 'success' es false, manejar el error aquí
                        alert('Error al generar la venta: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error al procesar la respuesta:', error);
                    alert('Ocurrió un error al generar la venta: ' + error.message);
                });
        });
    </script>


    </body>

    </html>