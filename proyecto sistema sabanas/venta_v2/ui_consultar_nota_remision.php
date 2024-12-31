<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultar Nota de Remisión</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.4/css/bulma.min.css">
</head>

<body>
    <div class="container mt-5">
        <!-- Título -->
        <h1 class="title">Consultar Nota de Remisión</h1>

        <!-- Filtros de Búsqueda -->
        <div class="box">
            <h2 class="subtitle">Filtros de búsqueda</h2>
            <form id="formFiltro" onsubmit="consultarNotas(event)">
                <div class="columns">
                    <div class="column">
                        <label class="label" for="filtroFecha">Fecha</label>
                        <input class="input" type="date" id="filtroFecha" name="filtroFecha">
                    </div>
                    <div class="column">
                        <label class="label" for="filtroNumeroNota">Número de Nota</label>
                        <input class="input" type="text" id="filtroNumeroNota" name="filtroNumeroNota" placeholder="Ingrese el número de nota">
                    </div>
                    <div class="column">
                        <label class="label" for="filtroCliente">Cliente</label>
                        <input class="input" type="text" id="filtroCliente" name="filtroCliente" placeholder="Nombre o cédula del cliente">
                    </div>
                    <div class="column">
                        <label class="label" for="filtroEstado">Estado</label>
                        <div class="select is-fullwidth">
                            <select id="filtroEstado" name="filtroEstado">
                                <option value="">Todos</option>
                                <option value="pendiente">Pendiente</option>
                                <option value="completado">Completado</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <button class="button is-info" type="submit">Buscar</button>
                </div>
            </form>
        </div>

        <!-- Tabla de Notas de Remisión -->
        <div class="box">
            <h2 class="subtitle">Resultados</h2>
            <table class="table is-fullwidth is-striped">
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
    </div>

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



    <script>

    </script>


</body>

</html>