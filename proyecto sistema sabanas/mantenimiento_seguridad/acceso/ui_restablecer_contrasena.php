<?php
session_start();
include("../../conexion/configv2.php"); // Asegúrate de incluir la conexión

$token = $_GET['token'] ?? null;

// Verifica si el token es válido
if ($token) {
    // Cambiamos la comparación de expiry a NOW()
    $query = "SELECT email FROM recuperacion_contrasena WHERE token = $1 AND expiry > NOW()";
    $result = pg_query_params($conn, $query, array($token));

    if (!$result) {
        // Manejo de errores en la consulta
        echo "Error en la consulta: " . pg_last_error($conn);
        exit;
    }

    if (pg_num_rows($result) > 0) {
        // El token es válido, permite restablecer la contraseña
        $email = pg_fetch_result($result, 0, 'email');
    } else {
        echo "Token inválido o expirado.";
        exit;
    }
} else {
    echo "No se proporcionó ningún token.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña</title>
    <style>
        /* Estilos generales */
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
        }

        h2 {
            color: #333;
            text-align: center;
        }

        form {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            margin: 20px auto;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: bold;
        }

        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            box-sizing: border-box;
        }

        button {
            background-color: #5cb85c; /* Color de fondo verde */
            color: white; /* Color del texto */
            border: none; /* Sin borde */
            padding: 10px 15px; /* Espaciado interno */
            border-radius: 5px; /* Bordes redondeados */
            cursor: pointer; /* Cambia el cursor a puntero al pasar por encima */
            width: 100%; /* Ancho completo */
        }

        button:hover {
            background-color: #4cae4c; /* Color más oscuro al pasar el mouse */
        }

        .error {
            color: red;
            display: none; /* Oculto por defecto */
            margin-top: -15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <h2>Restablecer Contraseña</h2>
    <form id="resetForm" action="actualizar_contrasena.php" method="POST" onsubmit="return validateForm()">
        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
        
        <label for="password">Nueva Contraseña:</label>
        <input type="password" id="password" name="password" required>
        
        <label for="confirm_password">Confirmar Nueva Contraseña:</label>
        <input type="password" id="confirm_password" name="confirm_password" required>
        <div class="error" id="error-message">Las contraseñas no coinciden.</div>
        
        <button type="submit">Actualizar Contraseña</button>
    </form>

    <script>
        function validateForm() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const errorMessage = document.getElementById('error-message');

            if (password !== confirmPassword) {
                errorMessage.style.display = 'block'; // Muestra el mensaje de error
                return false; // Previene el envío del formulario
            }

            errorMessage.style.display = 'none'; // Oculta el mensaje de error
            return true; // Permite el envío del formulario
        }
    </script>
</body>
</html>
