<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Rango Facturas</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="">
    <style>
        /* Estilo para centrar el formulario en la página */
        body,
        html {
            height: 100%;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f0f0f0;
            /* Fondo general */
        }

        /* Estilo para el contenedor del formulario */
        #formTimbrado {
            width: 350px;
            /* Ancho predefinido */
            max-width: 100%;
            /* Asegura que no exceda el ancho del contenedor */
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: #fff;
            /* Fondo blanco */
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }

        /* Estilo para las etiquetas de los campos */
        #formTimbrado label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        /* Estilo para los campos de entrada */
        #formTimbrado input[type="text"],
        #formTimbrado input[type="number"],
        #formTimbrado input[type="date"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* Estilo para el botón de envío */
        #formTimbrado button[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
        }

        #formTimbrado button[type="submit"]:hover {
            background-color: #45a049;
        }

        /* Estilo para el mensaje de respuesta */
        #responseMessage {
            margin-top: 20px;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>

<body>

    <form id="formTimbrado">
        <label for="timbrado">Timbrado:</label>
        <input type="text" id="timbrado" name="timbrado" required><br>

        <label for="rango_inicio">Rango Inicio:</label>
        <input type="number" id="rango_inicio" name="rango_inicio" required><br>

        <label for="rango_fin">Rango Fin:</label>
        <input type="number" id="rango_fin" name="rango_fin" required><br>

        <label for="fecha_inicio">Fecha Inicio:</label>
        <input type="date" id="fecha_inicio" name="fecha_inicio" required><br>

        <label for="fecha_fin">Fecha Fin:</label>
        <input type="date" id="fecha_fin" name="fecha_fin" required><br>

        <button type="submit">Cargar Rango</button>
        <div id="responseMessage"></div>
    </form>



    </div>
    <script src="" async defer></script>
    <script>
        document.getElementById('formTimbrado').addEventListener('submit', async function(event) {
            event.preventDefault(); // Evitar el envío estándar del formulario

            const form = event.target;
            const formData = new FormData(form);

            try {
                // Enviar datos al servidor
                const response = await fetch('cargar_rango_factura.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                // Mostrar el mensaje al usuario
                const messageDiv = document.getElementById('responseMessage');
                if (result.success) {
                    messageDiv.textContent = result.message; // Mensaje de éxito
                    messageDiv.style.color = 'green';
                } else {
                    messageDiv.textContent = result.message; // Mensaje de error
                    messageDiv.style.color = 'red';
                }

                // Opcional: Limpiar el formulario después del éxito
                if (result.success) {
                    form.reset();
                }
            } catch (error) {
                console.error('Error en la solicitud:', error);
            }
        });
    </script>
</body>

</html>