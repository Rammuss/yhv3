<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrar Rangos de Facturas</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f4f4f4;
        }

        .activo {
            background-color: #d4edda;
            /* Verde para rango activo */
        }

        .inactivo {
            background-color: #f8d7da;
            /* Rojo para rango inactivo */
        }

        .btn {
            padding: 5px 10px;
            border: none;
            cursor: pointer;
        }

        .btn-activar {
            background-color: #28a745;
            color: white;
        }

        .btn-desactivar {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>

<body>
    <h1>Administrar Rangos de Facturas</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Timbrado</th>
                <th>Rango Inicio</th>
                <th>Rango Fin</th>
                <th>Actual</th>
                <th>Fecha Inicio</th>
                <th>Fecha Fin</th>
                <th>Estado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody id="tablaRangos">
            <!-- Los datos se cargarán dinámicamente -->
        </tbody>
    </table>
</body>
<script>
    // Función para cargar rangos desde el servidor
    async function cargarRangos() {
        try {
            const response = await fetch('../rango_facturas/obtener_rangos.php'); // Cambia la ruta
            const data = await response.json();
            const tabla = document.getElementById('tablaRangos');

            tabla.innerHTML = ''; // Limpiar contenido actual

            data.forEach(rango => {
                const fila = document.createElement('tr');
                fila.className = rango.activo === 't' ? 'activo' : 'inactivo';

                fila.innerHTML = `
                    <td>${rango.id}</td>
                    <td>${rango.timbrado}</td>
                    <td>${rango.rango_inicio}</td>
                    <td>${rango.rango_fin}</td>
                    <td>${rango.actual}</td>
                    <td>${rango.fecha_inicio}</td>
                    <td>${rango.fecha_fin}</td>
                    <td>${rango.activo === 't' ? 'Activo' : 'Inactivo'}</td>
                    <td>
                        ${rango.activo === 'f'
                            ? `<button class="btn btn-activar" onclick="activarRango(${rango.id})">Activar</button>` 
                            : `<button class="btn btn-desactivar" onclick="desactivarRango(${rango.id})">Desactivar</button>`}
                    </td>
                `;
                tabla.appendChild(fila);
            });
        } catch (error) {
            console.error('Error al cargar rangos:', error);
        }
    }

    // Función genérica para activar o desactivar un rango
    async function cambiarEstadoRango(id, accion) {
        try {
            const response = await fetch('/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/venta_v2/referenciales_venta/rango_facturas/activar_desactivar_rango.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: id,
                    accion: accion
                })
            });

            const data = await response.json();

            if (data.success) {
                console.log(data.message);
                // Aquí puedes actualizar la interfaz según el nuevo estado
                cargarRangos();
            } else {
                console.error(data.message);
            }
        } catch (error) {
            console.error('Error en la solicitud:', error);
        }
    }

    // Ejemplo de uso: activar un rango
    function activarRango(id) {
        cambiarEstadoRango(id, 'activar');
    }

    // Ejemplo de uso: desactivar un rango
    function desactivarRango(id) {
        cambiarEstadoRango(id, 'desactivar');
    }


    // Cargar rangos al cargar la página
    cargarRangos();
</script>

</html>