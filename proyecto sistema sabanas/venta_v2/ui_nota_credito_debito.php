<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Notas</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma/css/bulma.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <script src="../venta_v2/navbar.js"></script>
    <link rel="stylesheet" href="../venta_v2/styles_venta.css">
    <style>
        .tab-content {
            display: none;
        }

        .tab-content.is-active {
            display: block;
        }
    </style>
</head>

<body>
    <div id="navbar-container"></div>
    <section class="section">
        <div class="container">
            <h1 class="title">Gestión de Notas de Crédito y Débito</h1>

            <!-- Tabs -->
            <div class="tabs is-boxed">
                <ul>
                    <li id="tabGenerar" class="is-active" onclick="mostrarSeccion('generar')">
                        <a>Generar Nota</a>
                    </li>
                    <li id="tabConsultar" onclick="mostrarSeccion('consultar')">
                        <a>Consultar Notas</a>
                    </li>
                </ul>
            </div>

            <!-- Contenido de las secciones -->

            <!-- Sección: Generar Nota -->
            <div id="generar" class="tab-content is-active">
                <div class="box">
                    <h2 class="subtitle">Buscar Factura y Generar Nota</h2>

                    <!-- Filtros de búsqueda -->
                    <div class="columns">
                        <div class="column">
                            <div class="field">
                                <label class="label">Número de Factura</label>
                                <div class="control">
                                    <input class="input" type="text" id="numero-factura" placeholder="Ingrese número de factura">
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">RUC</label>
                                <div class="control">
                                    <input class="input" type="text" id="ruc-cliente" placeholder="Ingrese RUC del cliente">
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">Fecha</label>
                                <div class="control">
                                    <input class="input" type="date" id="fecha-factura">
                                </div>
                            </div>
                        </div>
                        <div class="column">
                            <div class="field">
                                <label class="label">Estado</label>
                                <div class="control">
                                    <div class="select">
                                        <select id="estado-factura">
                                            <option value="">Todos</option>
                                            <option value="Emitida">Emitida</option>
                                            <option value="Anulada">Anulada</option>
                                            <option value="Ajustada">Ajustada</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de búsqueda -->
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-info" onclick="buscarFacturas()">Buscar Facturas</button>
                        </div>
                    </div>

                    <!-- Tabla de Facturas -->
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>ID Factura</th>
                                <th>Cliente</th>
                                <th>RUC</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="facturas-table-body">
                            <!-- Datos cargados dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>


            <!-- Sección: Consultar Notas -->
            <div id="consultar" class="tab-content">
                <div class="box">
                    <h2 class="subtitle">Consultar Notas</h2>

                    <!-- Filtros de búsqueda -->
                    <div class="columns is-multiline">
                        <div class="column is-half">
                            <div class="field">
                                <label class="label">Número de Nota</label>
                                <div class="control">
                                    <input class="input" type="text" id="numero-nota" placeholder="Ingrese número de nota">
                                </div>
                            </div>
                        </div>
                        <div class="column is-half">
                            <div class="field">
                                <label class="label">Número de Factura</label>
                                <div class="control">
                                    <input class="input" type="text" id="numero-factura-nota" placeholder="Ingrese número de factura">
                                </div>
                            </div>
                        </div>
                        <div class="column is-half">
                            <div class="field">
                                <label class="label">Fecha</label>
                                <div class="control">
                                    <input class="input" type="date" id="fecha-nota">
                                </div>
                            </div>
                        </div>
                        <div class="column is-half">
                            <div class="field">
                                <label class="label">CI/RUC</label>
                                <div class="control">
                                    <input class="input" type="text" id="ci-ruc-nota" placeholder="Ingrese CI o RUC del cliente">
                                </div>
                            </div>
                        </div>
                        <div class="column is-half">
                            <div class="field">
                                <label class="label">Tipo de Nota</label>
                                <div class="control">
                                    <div class="select">
                                        <select id="tipo-nota">
                                            <option value="">Seleccione tipo de nota</option>
                                            <option value="credito">Crédito</option>
                                            <option value="debito">Débito</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de búsqueda -->
                    <div class="field is-grouped">
                        <div class="control">
                            <button class="button is-info" onclick="buscarNotas()">Buscar Notas</button>
                        </div>
                    </div>

                    <!-- Indicador de mensajes -->
                    <div id="mensajes" class="has-text-centered"></div>

                    <!-- Tabla de Notas -->
                    <table class="table is-fullwidth is-hoverable">
                        <thead>
                            <tr>
                                <th>ID Nota</th>
                                <th>ID Factura</th>
                                <th>Cliente</th>
                                <th>CI/RUC</th>
                                <th>Monto</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Tipo</th> <!-- Nueva columna para Tipo de Nota -->
                                <th>Accion</th> <!-- Nueva columna para Tipo de Nota -->
                            </tr>
                        </thead>
                        <tbody id="notas-table-body">
                            <!-- Datos cargados dinámicamente -->
                        </tbody>
                    </table>
                </div>
            </div>


        </div>
    </section>


    <!-- Modal para Generar Nota -->
    <div class="modal" id="modalGenerarNota">
        <div class="modal-background"></div>
        <div class="modal-content">
            <input type="hidden" id="venta-id">

            <div class="box">
                <h3 class="title">Generar Nota de Crédito/Débito</h3>

                <!-- Formulario para la cabecera -->
                <div class="field">
                    <label class="label">Cliente</label>
                    <input id="cliente-nombre" class="input" type="text" readonly>
                    <input id="cliente-id" class="input" type="hidden"> <!-- Campo oculto para el id del cliente -->
                </div>

                <div class="field">
                    <label class="label">Tipo de Nota</label>
                    <div class="control">
                        <select id="tipo-nota-modal" required>
                            <option value="" disabled selected>Selecciona un tipo de nota</option>
                            <option value="debito">Débito</option>
                            <option value="credito">Crédito</option>
                        </select>


                    </div>
                </div>


                <div class="field">
                    <label class="label">Monto</label>
                    <input id="monto" class="input" type="number" step="0.01" readonly>
                </div>

                <div class="field">
                    <label class="label">Motivo</label>
                    <textarea id="motivo" class="textarea"></textarea>
                </div>

                <!-- Detalles de los productos -->
                <h4 class="title is-5">Detalles de la Venta</h4>
                <table class="table is-fullwidth is-hoverable">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Monto</th>
                            <th>Seleccionar para Crédito</th> <!-- Nueva columna para checkbox -->
                        </tr>
                    </thead>
                    <tbody id="detalle-productos">
                        <!-- Aquí se cargarán los productos -->
                    </tbody>
                </table>

                <!-- Checkbox global para aplicar descuento -->
                <div class="field">
                    <label class="checkbox">
                        <input type="checkbox" id="aplicar-descuento">
                        Aplicar descuento
                    </label>
                </div>

                <div class="field">
                    <button class="button is-primary" id="registrarNota">Registrar Nota</button>
                    <button class="button" id="cerrarModal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Declarar la función en el contexto global
        function verDetalles(id_venta) {
            fetch(`../venta_v2/obtener_venta.php?id_venta=${id_venta}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log("Valor de tipoNota al abrir modal:", document.getElementById("tipo-nota-modal").value);
                        const venta = data.data.venta;
                        const detalles = data.data.detalles;

                        if (venta && detalles) {
                            document.getElementById("cliente-nombre").value = `${venta.nombre_cliente} ${venta.apellido_cliente}`;
                            document.getElementById("cliente-id").value = venta.cliente_id;
                            document.getElementById("monto").value = venta.monto_total;
                            document.getElementById("monto").readOnly = true; // Campo monto no editable por defecto
                            const tipoNota = document.getElementById("tipo-nota-modal");
                            document.getElementById("venta-id").value = id_venta;
                            tipoNota.value = "credito";
                            const detalleProductosTable = document.getElementById("detalle-productos");
                            detalleProductosTable.innerHTML = '';

                            detalles.forEach(producto => {
                                const row = document.createElement('tr');
                                row.setAttribute('data-producto-id', producto.producto_id);
                                row.innerHTML = `
                                <td>${producto.nombre_producto}</td>
                                <td><input type="number" class="input cantidad" value="${producto.cantidad}" min="0" data-precio="${producto.precio_unitario}" data-monto="${producto.monto}"></td>
                                <td><input type="number" class="input precio-unitario" value="${producto.precio_unitario}" step="0.01" min="0" readonly></td>
                                <td><input type="number" class="input monto" value="${producto.monto}" step="0.01" min="0" readonly></td>
                                <td><input type="checkbox" class="checkbox-item"></td>
                            `;
                                detalleProductosTable.appendChild(row);
                            });

                            document.querySelectorAll('.cantidad').forEach(input => {
                                input.addEventListener('input', (e) => {
                                    recalcularMonto(e.target);
                                });
                            });

                            document.getElementById("modalGenerarNota").classList.add("is-active");
                        } else {
                            alert("No se pudieron obtener los detalles de la venta.");
                        }
                    } else {
                        alert("No se pudo cargar la venta.");
                    }
                })
                .catch(error => {
                    console.error('Error al obtener los detalles de la venta:', error);
                    alert("Hubo un error al cargar los datos.");
                });
        }

        function recalcularMonto(input) {
            const cantidad = parseFloat(input.value);
            const precioUnitario = parseFloat(input.dataset.precio);
            const monto = cantidad * precioUnitario;
            const montoInput = input.closest('tr').querySelector('.monto');
            montoInput.value = monto.toFixed(2);
            actualizarMontoTotal();
        }

        function actualizarMontoTotal() {
            const montos = document.querySelectorAll('.monto');
            let total = 0;
            montos.forEach(monto => {
                total += parseFloat(monto.value);
            });
            document.getElementById("monto").value = total.toFixed(2);
        }

        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById("cerrarModal").addEventListener("click", () => {
                document.getElementById("modalGenerarNota").classList.remove("is-active");
            });

            document.getElementById("registrarNota").addEventListener("click", () => {
                const tipoNota = document.getElementById("tipo-nota-modal").value;
                console.log("Valor de tipoNota antes del fetch:", tipoNota);
                const monto = document.getElementById("monto").value;
                const motivo = document.getElementById("motivo").value;
                const clienteId = document.getElementById("cliente-id").value;
                const idVenta = document.getElementById("venta-id").value;

                let detalles = [];
                if (tipoNota === 'credito') {
                    const filasDetalle = document.getElementById("detalle-productos").getElementsByTagName("tr");

                    for (const fila of filasDetalle) {
                        const checkbox = fila.querySelector(".checkbox-item");
                        if (checkbox && checkbox.checked) {
                            const cantidadInput = fila.querySelector(".cantidad");
                            const precioUnitarioInput = fila.querySelector(".precio-unitario");
                            const productoId = fila.getAttribute("data-producto-id");

                            if (cantidadInput && precioUnitarioInput) {
                                const cantidad = parseFloat(cantidadInput.value) || 0;
                                const precioUnitario = parseFloat(precioUnitarioInput.value) || 0;

                                detalles.push({
                                    producto_id: productoId,
                                    cantidad: cantidad.toFixed(2),
                                    precio_unitario: precioUnitario.toFixed(2)
                                });
                            }
                        }
                    }

                    if (detalles.length === 0) {
                        alert("Debe seleccionar al menos un ítem para incluir en la nota.");
                        return;
                    }
                }

                const datosNota = {
                    tipo: tipoNota,
                    monto: monto,
                    motivo: motivo,
                    cliente_id: clienteId,
                    id_venta: idVenta,
                    detalles: detalles
                };
                console.log("Valor de tipoNota antes del fetch:", tipoNota);

                fetch('../venta_v2/registrar_nota_deb_cred.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(datosNota)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert("Nota registrada correctamente");
                            document.getElementById("modalGenerarNota").classList.remove("is-active");
                            window.location.href = `../venta_v2/genera_pdf_notadebcred.php?nota_id=${data.nota_id}`;
                        } else {
                            alert("Error al registrar la nota.");
                        }
                    })
                    .catch(error => {
                        console.error('Error al registrar la nota:', error);
                        alert("Hubo un error al registrar la nota.");
                    });
            });

            // Delegación de eventos
            document.addEventListener('change', (e) => {
                if (e.target && e.target.id === 'tipo-nota-modal') {
                    const tipoNota = e.target.value;
                    console.log(`Tipo de Nota seleccionado: ${tipoNota}`);
                    const montoInput = document.getElementById('monto');

                    if (tipoNota === 'debito') {
                        montoInput.readOnly = false;
                        montoInput.value = '';
                    } else if (tipoNota === 'credito') {
                        montoInput.readOnly = true;
                        actualizarMontoTotal();
                    }
                }
            });

        });
    </script>













    <script>
        function mostrarSeccion(seccion) {
            // Ocultar todas las secciones
            const contenidos = document.querySelectorAll('.tab-content');
            contenidos.forEach(contenido => contenido.classList.remove('is-active'));

            // Mostrar la sección seleccionada
            document.getElementById(seccion).classList.add('is-active');

            // Actualizar la pestaña activa
            const tabs = document.querySelectorAll('.tabs ul li');
            tabs.forEach(tab => tab.classList.remove('is-active'));

            // Marcar la pestaña seleccionada como activa
            document.getElementById('tab' + seccion.charAt(0).toUpperCase() + seccion.slice(1)).classList.add('is-active');
        }



        function buscarNotas() {
            const numeroNota = document.getElementById('numero-nota').value.trim();
            const numeroFactura = document.getElementById('numero-factura-nota').value.trim();
            const fechaNota = document.getElementById('fecha-nota').value.trim();
            const ciRucNota = document.getElementById('ci-ruc-nota').value.trim();
            const tipo = document.getElementById('tipo-nota').value.trim(); // Nuevo campo tipo

            // Validar que al menos un campo tenga un valor
            if (!numeroNota && !numeroFactura && !fechaNota && !ciRucNota && !tipo) {
                alert('Por favor, ingrese al menos un criterio de búsqueda.');
                return;
            }

            // Mostrar un indicador de carga (opcional)
            const tableBody = document.getElementById('notas-table-body');
            tableBody.innerHTML = '<tr><td colspan="8">Cargando...</td></tr>';

            // Realizar la solicitud al servidor
            fetch('../venta_v2/buscar_nota_deb_cred_venta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        numeroNota,
                        numeroFactura,
                        fechaNota,
                        ciRucNota,
                        tipo // Incluir tipo en la solicitud
                    }),
                })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Error en la solicitud al servidor.');
                    }
                    return response.json();
                })
                .then((data) => {
                    tableBody.innerHTML = '';

                    if (data.length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="8">No se encontraron resultados.</td></tr>';
                    } else {
                        data.forEach((nota) => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                        <td>${nota.id_nota}</td>
                        <td>${nota.numero_factura}</td>
                        <td>${nota.cliente_nombre}</td>
                        <td>${nota.ci_ruc}</td>
                        <td>${isNaN(Number(nota.monto)) ? nota.monto : Number(nota.monto).toFixed(2)}</td>
                        <td>${nota.fecha}</td>
                        <td>${nota.estado}</td>
                        <td>${nota.tipo}</td>
                        <td><button class="button is-info" onclick="enviarNota(${nota.id_nota})">Ver nota</button></td> <!-- Botón para enviar nota -->
                    `;
                            tableBody.appendChild(row);
                        });
                    }
                })
                .catch((error) => {
                    tableBody.innerHTML = '<tr><td colspan="8">Error al cargar datos.</td></tr>';
                    console.error('Error:', error);
                });
        }

        // Función para enviar la nota
        function enviarNota(id_nota) {
            window.location.href = `../venta_v2/genera_pdf_notadebcred.php?nota_id=${id_nota}`;
        }
    </script>


    <script>
        function buscarFacturas() {
            // Obtener los valores ingresados en el formulario
            const numeroFactura = document.getElementById("numero-factura").value.trim();
            const rucCliente = document.getElementById("ruc-cliente").value.trim();
            const fechaFactura = document.getElementById("fecha-factura").value;
            const estadoFactura = document.getElementById("estado-factura").value;

            // Crear un objeto con los parámetros de búsqueda
            const parametrosBusqueda = {
                numero_factura: numeroFactura,
                ruc_ci: rucCliente,
                fecha: fechaFactura,
                estado: estadoFactura
            };

            // Enviar los parámetros al servidor usando fetch
            fetch('../venta_v2/buscar_facturas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(parametrosBusqueda)
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error al buscar las facturas');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Llamar a la función construirTablaFacturas con los resultados
                        construirTablaFacturas(data.data);
                    } else {
                        console.error("Error del servidor:", data.message);
                        alert("No se encontraron facturas o ocurrió un error en el servidor.");
                    }
                })
                .catch(error => {
                    console.error("Error en la búsqueda:", error);
                    alert("Ocurrió un error al realizar la búsqueda. Intente nuevamente.");
                });
        }
    </script>


    <script>
        function construirTablaFacturas(facturas) {
            // Seleccionar el cuerpo de la tabla
            const tableBody = document.getElementById('facturas-table-body');

            // Limpiar contenido previo de la tabla
            tableBody.innerHTML = '';

            // Recorrer las facturas y construir las filas
            facturas.forEach(factura => {
                // Crear una fila
                const row = document.createElement('tr');

                // Construir celdas
                row.innerHTML = `
            <td>${factura.id_venta}</td>
            <td>${factura.nombre_cliente} ${factura.apellido_cliente}</td>
            <td>${factura.cedula_cliente}</td>
            <td>${factura.fecha}</td>
            <td>${factura.estado}</td>
            <td>
                <button class="button is-small is-info" onclick="verDetalles(${factura.id_venta})">Generar Nota</button>
            </td>
        `;

                // Agregar la fila al cuerpo de la tabla
                tableBody.appendChild(row);
            });
        }
    </script>
</body>

</html>