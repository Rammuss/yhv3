<!-- recuperar_contrasena.php -->
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña</title>
    <style>
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        /* Estilos del encabezado */
        h2 {
            color: #333;
            text-align: center;
        }

        /* Estilos del formulario */
        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: 20px auto;
        }

        /* Estilos de las etiquetas */
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        /* Estilos de los campos de entrada */
        input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
            /* Incluye el padding y el borde en el ancho total */
        }

        /* Estilos del botón */
        button {
            background-color: #5cb85c;
            /* Color de fondo verde */
            color: white;
            /* Color del texto */
            border: none;
            /* Sin borde */
            padding: 10px 15px;
            /* Espaciado interno */
            border-radius: 5px;
            /* Bordes redondeados */
            cursor: pointer;
            /* Cambia el cursor a puntero al pasar por encima */
            width: 100%;
            /* Ancho completo */
        }

        /* Efecto al pasar el mouse sobre el botón */
        button:hover {
            background-color: #4cae4c;
            /* Color más oscuro al pasar el mouse */
        }
    </style>
</head>

<body>
    <h2>Recuperar Contraseña</h2>
    <form action="enviar_token.php" method="POST">
        <label for="email">Correo Electrónico:</label>
        <input type="email" id="email" name="email" required>
        <button type="submit">Enviar Token</button>
    </form>
</body>

</html>