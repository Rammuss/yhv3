<?php

session_start();
include("../../conexion/configv2.php");

// Verifica si el método de la solicitud es POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Decodifica el cuerpo de la solicitud JSON
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['contraseña_actual'], $data['nueva_contraseña'])) {
        $usuario = $_SESSION['nombre_usuario']; // Obtiene el nombre de usuario de la sesión
        $contraseña_actual = $data['contraseña_actual'];
        $nueva_contraseña = $data['nueva_contraseña'];

        // Obtén la contraseña almacenada en la base de datos para este usuario
        $query = "SELECT contrasena FROM usuarios WHERE nombre_usuario = $1";
        $result = pg_query_params($conn, $query, array($usuario));

        if (pg_num_rows($result) > 0) {
            $hashed_password = pg_fetch_result($result, 0, 'contrasena');

            // Verifica si la contraseña actual ingresada coincide con la almacenada
            if (password_verify($contraseña_actual, $hashed_password)) {
                // Si coincide, actualiza la contraseña con la nueva
                $hashed_new_password = password_hash($nueva_contraseña, PASSWORD_DEFAULT);

                $update_query = "UPDATE usuarios SET contrasena = $1 WHERE nombre_usuario = $2";
                $update_result = pg_query_params($conn, $update_query, array($hashed_new_password, $usuario));

                if ($update_result) {
                    // Envía una respuesta JSON
                    echo json_encode(["success" => true, "message" => "Contraseña actualizada exitosamente."]);
                } else {
                    echo json_encode(["success" => false, "message" => "Error al actualizar la contraseña: " . pg_last_error($conn)]);
                }
            } else {
                echo json_encode(["success" => false, "message" => "La contraseña actual no es correcta."]);
            }
        } else {
            echo json_encode(["success" => false, "message" => "Usuario no encontrado."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Datos incompletos."]);
    }
}

?>
