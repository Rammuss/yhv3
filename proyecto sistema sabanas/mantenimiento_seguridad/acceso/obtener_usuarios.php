<?php
// archivo: obtener_usuarios.php
include("../../conexion/configv2.php");






// Verificar si se proporcionó un ID
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Preparar la consulta SQL para obtener el usuario por ID
    $query = "SELECT id, nombre_usuario, rol, telefono, email FROM usuarios WHERE id = $1";
    $result = pg_query_params($conn, $query, [$id]);

    if ($result) {
        $usuario = pg_fetch_assoc($result);
        // Verificamos si el usuario fue encontrado
        if ($usuario) {
            echo json_encode($usuario);
        } else {
            echo json_encode(["success" => false, "message" => "Usuario no encontrado."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Error al obtener el usuario."]);
    }
} else {
    // Si no se proporciona un ID, devolver todos los usuarios
    $query = "SELECT id, nombre_usuario, estado,rol, telefono, email FROM usuarios";
    $result = pg_query($conn, $query);

    $usuarios = [];
    while ($usuario = pg_fetch_assoc($result)) {
        $usuarios[] = $usuario;
    }

    echo json_encode($usuarios);
}

// Cerrar la conexión
pg_close($conn);
?>
