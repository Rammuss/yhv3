<?php
// Configuración de conexión a PostgreSQL
include("../../conexion/configv2.php");

// Comprobación si se ha enviado el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir y sanitizar los datos del formulario
    $nombre_usuario = trim($_POST['usuario']);
    $contrasena = $_POST['contrasena'];
    $rol = $_POST['rol'];
    $email = $_POST['email'];
    $telefono = $_POST['telefono'];

    // Verificar si el nombre de usuario ya existe
    $query = "SELECT COUNT(*) AS count FROM usuarios WHERE nombre_usuario = $1";
    $result = pg_query_params($conn, $query, array($nombre_usuario));

    if (!$result) {
        echo "Error en la consulta de verificación.";
        exit();
    }

    $row = pg_fetch_assoc($result);
    if ($row['count'] > 0) {
        echo "El nombre de usuario ya está en uso.";
    } else {
        // Encriptar la contraseña antes de guardar
        $hashed_password = password_hash($contrasena, PASSWORD_BCRYPT);

        // Insertar el nuevo usuario en la base de datos
        $query = "INSERT INTO usuarios (nombre_usuario, contrasena, rol, email, telefono) VALUES ($1, $2, $3, $4, $5)";
        $result = pg_query_params($conn, $query, array($nombre_usuario, $hashed_password, $rol, $email, $telefono));

        if ($result) {
            echo "Usuario registrado exitosamente.";
        } else {
            echo "Error al registrar el usuario.";
        }
    }
}

// Cerrar la conexión
pg_close($conn);
?>
