<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Nota de Remisión</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
    <script src="navbar.js"></script>
    <link rel="stylesheet" href="../venta_v2/styles_venta.css">

</head>

<body>
    <div id="navbar-container"></div>
    <div class="container mt-5">
        <!-- Título -->
        <h1 class="title">Registrar Nota de Remisión</h1>

        <!-- Tabs -->
        <div class="tabs is-boxed">
            <ul>
                <li id="tabBuscar" onclick="mostrarSeccion('buscar')">
                    <a>Buscar Factura</a>
                </li>

                <li class="is-active" id="tabManual" onclick="mostrarSeccion('manual')">
                    <a>Registro Manual</a>
                </li>

                <!-- Nueva pestaña -->
                <li id="tabResumen" onclick="mostrarSeccion('resumen')">
                    <a>Consulta de Notas</a>
                </li>


            </ul>
        </div>

        <!-- Sección de Registro Manual -->
        <section id="seccionManual" class="box">
            <h2 class="subtitle">Registrar Nota de Remisión Manualmente</h2>
            <form id="formNotaRemision" onsubmit="registrarNotaManual(event)">
                <div class="field">
                    <label class="label" for="numeroNota">Número de Nota</label>
                    <div class="control">
                        <input class="input" type="text" id="numeroNota" name="numeroNota" placeholder="Ingrese el número de nota" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="cliente">Cliente</label>
                    <div class="control">
                        <input class="input" type="text" id="cliente" name="cliente" placeholder="Nombre o cédula del cliente" required>
                    </div>
                </div>

                <div class="field">
                    <label class="label" for="detalle">Detalle</label>
                    <div class="control">
                        <textarea class="textarea" id="detalle" name="detalle" placeholder="Ingrese los detalles de la nota de remisión" required></textarea>
                    </div>
                </div>

                <div class="field is-grouped">
                    <div class="control">
                        <button class="button is-primary" type="submit">Registrar Nota</button>
                    </div>
                    <div class="control">
                        <button class="button is-light" type="reset">Limpiar</button>
                    </div>
                </div>
            </form>
        </section>

        <!-- Sección de Búsqueda de Facturas -->
        <section id="seccionBuscar" class="box is-hidden">
            <h2 class="subtitle">Buscar Factura</h2>

            <!-- Filtros de Búsqueda -->
            <div class="field">
                <label class="label" for="filtroFecha">Fecha</label>
                <div class="control">
                    <input class="input" type="date" id="filtroFecha" name="filtroFecha">
                </div>
            </div>

            <div class="field">
                <label class="label" for="filtroNumeroFactura">Número de Factura</label>
                <div class="control">
                    <input class="input" type="text" id="filtroNumeroFactura" name="filtroNumeroFactura" placeholder="Ingrese el número de factura">
                </div>
            </div>

            <div class="field">
                <label class="label" for="filtroCICliente">Cédula del Cliente</label>
                <div class="control">
                    <input class="input" type="text" id="filtroCICliente" name="filtroCICliente" placeholder="Ingrese la cédula del cliente">
                </div>
            </div>

            <button class="button is-info"
                onclick="buscarFacturas(
            document.getElementById('filtroFecha').value, 
            document.getElementById('filtroNumeroFactura').value, 
            document.getElementById('filtroCICliente').value
        )">Buscar</button>


            <!-- Tabla de Facturas -->
            <table class="table is-fullwidth is-striped">
                <thead>
                    <tr>
                        <th>Número de Factura</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody id="tablaFacturas">
                    <!-- Filas generadas dinámicamente -->
                </tbody>
            </table>
        </section>

        <!-- Nueva Sección de Resumen -->
        <section id="seccionResumen" class="container mt-5 is-hidden">
            <!-- Título -->
            <h1 class="title has-text-centered">Consultar Nota de Remisión</h1>

            <!-- Filtros de Búsqueda -->
            <div class="box">
                <h2 class="subtitle">Filtros de búsqueda</h2>
                <form id="formFiltro" onsubmit="consultarNotas(event)">
                    <div class="columns is-multiline">
                        <!-- Filtro de Fecha -->
                        <div class="column is-6">
                            <div class="field">
                                <label class="label" for="filtroFecha">Fecha</label>
                                <div class="control">
                                    <input class="input" type="date" id="filtroFecha" name="filtroFecha">
                                </div>
                            </div>
                        </div>

                        <!-- Filtro de Número de Nota -->
                        <div class="column is-6">
                            <div class="field">
                                <label class="label" for="filtroNumeroNota">Número de Nota</label>
                                <div class="control">
                                    <input class="input" type="text" id="filtroNumeroNota" name="filtroNumeroNota" placeholder="Ingrese el número de nota">
                                </div>
                            </div>
                        </div>

                        <!-- Filtro de Cliente -->
                        <div class="column is-6">
                            <div class="field">
                                <label class="label" for="filtroCliente">Cliente</label>
                                <div class="control">
                                    <input class="input" type="text" id="filtroCliente" name="filtroCliente" placeholder="Nombre o cédula del cliente">
                                </div>
                            </div>
                        </div>

                        <!-- Filtro de Estado -->
                        <div class="column is-6">
                            <div class="field">
                                <label class="label" for="filtroEstado">Estado</label>
                                <div class="control">
                                    <div class="select is-fullwidth">
                                        <select id="filtroEstado" name="filtroEstado">
                                            <option value="">Todos</option>
                                            <option value="pendiente">Pendiente</option>
                                            <option value="completado">Completado</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botón de Búsqueda -->
                    <div class="field">
                        <div class="control">
                            <button class="button is-info is-fullwidth" type="submit">Buscar</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Tabla de Notas de Remisión -->
            <div class="box">
                <h2 class="subtitle">Resultados</h2>
                <table class="table is-fullwidth is-striped is-hoverable">
                    <thead>
                        <tr>
                            <th>Número de Nota</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tablaNotasRemision">
                        <!-- Filas generadas dinámicamente -->
                    </tbody>
                </table>
            </div>
        </section>



    </div>

    <script>
        // enviar parametros para buscar la factura
        async function buscarFacturas(fecha, numeroFactura, rucCi) {
            // Crear el cuerpo del JSON
            const payload = {
                fecha: fecha || null,
                numero_factura: numeroFactura || null,
                ruc_ci: rucCi || null
            };

            try {
                // Realizar la solicitud al endpoint
                const response = await fetch('../venta_v2/buscar_facturas.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                // Verificar si la respuesta es exitosa
                if (!response.ok) {
                    throw new Error(`Error en la solicitud: ${response.statusText}`);
                }

                // Convertir la respuesta a JSON
                const data = await response.json();

                // Manejar la respuesta
                if (data.success) {
                    console.log("Facturas encontradas:", data.data);
                    // Llamar a la función para construir la tabla con los datos
                    construirTabla(data.data);
                } else {
                    console.error("Error en el servidor:", data.message);
                }
            } catch (error) {
                console.error("Error en la función buscarFacturas:", error);
            }
        }
    </script>

    <script>
        // Función para construir la tabla con los datos obtenidos
        function construirTabla(facturas) {
            const tablaFacturas = document.getElementById('tablaFacturas');
            tablaFacturas.innerHTML = '';

            if (facturas && facturas.length > 0) {
                facturas.forEach(factura => {
                    const fila = document.createElement('tr');

                    const celdaFactura = document.createElement('td');
                    celdaFactura.textContent = factura.numero_factura;

                    const celdaCliente = document.createElement('td');
                    celdaCliente.textContent = factura.nombre_cliente;

                    const celdaFecha = document.createElement('td');
                    celdaFecha.textContent = factura.fecha;

                    const celdaAccion = document.createElement('td');
                    const botonGenerarNota = document.createElement('button');
                    botonGenerarNota.textContent = 'Generar Nota de Remisión';
                    botonGenerarNota.classList.add('button', 'is-success');
                    botonGenerarNota.onclick = () => generarNotaDeRemision(factura.numero_factura);

                    celdaAccion.appendChild(botonGenerarNota);

                    fila.appendChild(celdaFactura);
                    fila.appendChild(celdaCliente);
                    fila.appendChild(celdaFecha);
                    fila.appendChild(celdaAccion);

                    tablaFacturas.appendChild(fila);
                });
            } else {
                const filaVacia = document.createElement('tr');
                const celdaVacia = document.createElement('td');
                celdaVacia.colSpan = 4;
                celdaVacia.textContent = 'No se encontraron facturas.';
                filaVacia.appendChild(celdaVacia);
                tablaFacturas.appendChild(filaVacia);
            }
        }
    </script>

    <script>
        // Función para generar la nota de remisión
        async function generarNotaDeRemision(numeroFactura) {
            const payload = {
                numero_factura: numeroFactura
            };

            try {
                const response = await fetch('../venta_v2/generar_nota_remision.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    throw new Error(`Error en la solicitud: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success) {
                    alert('Nota de remisión generada con éxito.');
                    console.log('Nota de remisión generada:', data);
                } else {
                    alert('Error al generar la nota de remisión.');
                    console.error("Error en el servidor:", data.message);
                }
            } catch (error) {
                console.error("Error al generar la nota de remisión:", error);
                alert('Error en la generación de la nota de remisión.');
            }
        }
    </script>




    <script>
        // Mostrar y ocultar secciones
        function mostrarSeccion(seccion) {
            // Ocultar todas las secciones
            document.getElementById('seccionManual').classList.add('is-hidden');
            document.getElementById('seccionBuscar').classList.add('is-hidden');
            document.getElementById('seccionResumen').classList.add('is-hidden');

            // Remover la clase activa de las pestañas
            document.getElementById('tabManual').classList.remove('is-active');
            document.getElementById('tabBuscar').classList.remove('is-active');
            document.getElementById('tabResumen').classList.remove('is-active');

            // Mostrar la sección seleccionada
            if (seccion === 'manual') {
                document.getElementById('seccionManual').classList.remove('is-hidden');
                document.getElementById('tabManual').classList.add('is-active');
            } else if (seccion === 'buscar') {
                document.getElementById('seccionBuscar').classList.remove('is-hidden');
                document.getElementById('tabBuscar').classList.add('is-active');
            } else if (seccion === 'resumen') {
                document.getElementById('seccionResumen').classList.remove('is-hidden');
                document.getElementById('tabResumen').classList.add('is-active');
            }
        }



        // Función para generar nota desde una factura
        function generarNota(numeroFactura) {
            alert(`Generando nota de remisión para la factura ${numeroFactura}`);
        }

        // Inicializar en la sección manual
        mostrarSeccion('buscar');
    </script>


    <script>
        function consultarNotas(event) {
            event.preventDefault(); // Evita el comportamiento por defecto del formulario

            // Capturar los valores del formulario
            const filtros = {
                fecha: document.getElementById('filtroFecha').value,
                numero_nota: document.getElementById('filtroNumeroNota').value,
                cliente: document.getElementById('filtroCliente').value,
                estado: document.getElementById('filtroEstado').value,
            };

            // Enviar los datos al backend usando la función AJAX
            consultarNotaRemision(filtros);
        }



        // enviar parametros al backend
        async function consultarNotaRemision(filtros) {
            try {
                // Realizar la solicitud al backend
                const response = await fetch('../venta_v2/consultar_nota_remision_venta.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(filtros),
                });

                // Verificar si la respuesta es exitosa
                if (!response.ok) {
                    throw new Error('Error en la solicitud al servidor');
                }

                // Procesar la respuesta en JSON
                const data = await response.json();

                // Verificar si el backend retornó éxito
                if (data.success) {
                    console.log('Datos recibidos:', data.data);

                    construirTablaNotas(data);
                } else {
                    console.error('Error en el backend:', data.message);
                    alert('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error en la consulta:', error);
                alert('Ocurrió un error al consultar las notas de remisión.');
            }
        }
    </script>

    <script>
        function construirTablaNotas(data) {
            const tabla = document.getElementById('tablaNotasRemision');
            tabla.innerHTML = ''; // Limpiar la tabla antes de agregar nuevas filas

            // Recorrer los datos de las notas de remisión
            data.data.forEach(nota => {
                // Crear una nueva fila para cada nota de remisión
                const fila = document.createElement('tr');

                // Asignar las celdas con los valores correspondientes
                const celdaNumeroNota = document.createElement('td');
                celdaNumeroNota.textContent = nota.id_remision;
                fila.appendChild(celdaNumeroNota);

                const celdaCliente = document.createElement('td');
                celdaCliente.textContent = nota.nombre; // Asumiendo que el nombre del cliente está en "nota.nombre"
                fila.appendChild(celdaCliente);

                const celdaFecha = document.createElement('td');
                celdaFecha.textContent = nota.fecha;
                fila.appendChild(celdaFecha);

                // Asignar el color según el estado usando clases de Bulma
                const celdaEstado = document.createElement('td');

                // Crear un contenedor dentro de la celda para aplicar el fondo
                const contenedorEstado = document.createElement('span');
                contenedorEstado.textContent = nota.estado; // Asignar el texto del estado

                // Aplicar las clases de fondo solo al contenedor
                if (nota.estado === 'pendiente') {
                    contenedorEstado.classList.add('has-background-warning', 'has-text-dark', 'px-2', 'py-1'); // Color amarillo suave
                } else if (nota.estado === 'completado') {
                    contenedorEstado.classList.add('has-background-success', 'has-text-white', 'px-2', 'py-1'); // Color verde suave
                } else if (nota.estado === 'anulado') {
                    contenedorEstado.classList.add('has-background-danger', 'has-text-white', 'px-2', 'py-1'); // Color rojo suave
                }


                // Añadir el contenedor al `td`
                celdaEstado.appendChild(contenedorEstado);

                // Añadir la celda de estado a la fila
                fila.appendChild(celdaEstado);

                // Crear la celda de acción con botones
                const celdaAccion = document.createElement('td');

                // Crear el botón para PDF
                const botonPdf = document.createElement('button');
                botonPdf.textContent = 'PDF';
                botonPdf.classList.add('button', 'is-info');

                // Agregar un evento click al botón de PDF
                botonPdf.addEventListener('click', function() {
                    const idNota = nota.id_remision; // Obtener el ID de la nota de remisión
                    console.log(`Enviando ID de la nota: ${idNota}`);

                    // Redirigir al archivo que genera el PDF y pasar el ID como parámetro
                    window.location.href = `../venta_v2/pdf_nota_remision_venta.php?idNota=${idNota}`;
                });

                // Crear los botones de completar y anular
                const botonCompletar = document.createElement('button');
                botonCompletar.textContent = 'Completar';
                botonCompletar.classList.add('button', 'is-success');

                // Agregar evento de clic para el botón Completar
                botonCompletar.addEventListener('click', function() {
                    cambiarEstadoNota(nota.id_remision, 'completado');
                });

                const botonAnular = document.createElement('button');
                botonAnular.textContent = 'Anular';
                botonAnular.classList.add('button', 'is-danger');

                // Agregar evento de clic para el botón Anular
                botonAnular.addEventListener('click', function() {
                    cambiarEstadoNota(nota.id_remision, 'anulado');
                });

                // Función para manejar la solicitud AJAX de cambio de estado
                function cambiarEstadoNota(id_remision, nuevo_estado) {
                    // Enviar una solicitud AJAX al backend
                    fetch('../venta_v2/update_nota_remision_venta.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                id_remision: id_remision,
                                nuevo_estado: nuevo_estado
                            }),
                        })
                        .then(response => response.json())
                        .then(data => {
                            // Verificar si la respuesta contiene 'mensaje' o 'error'
                            if (data.mensaje) {
                                alert(data.mensaje); // Mostrar el mensaje de éxito
                                // Recargar las notas después de cambiar el estado
                                location.reload();
                            } else if (data.error) {
                                alert(data.error); // Mostrar el mensaje de error
                            } else {
                                alert('Error desconocido al cambiar el estado');
                            }
                        })
                        .catch(error => {
                            console.error('Error en la solicitud:', error);
                            alert('Ocurrió un error al actualizar el estado');
                        });
                }

                // Añadir los botones a la celda de acción
                celdaAccion.appendChild(botonPdf);
                celdaAccion.appendChild(botonCompletar);
                celdaAccion.appendChild(botonAnular);

                // Añadir la celda de acción a la fila
                fila.appendChild(celdaAccion);

                // Añadir la fila a la tabla
                tabla.appendChild(fila);
            });
        }
    </script>
</body>

</html>