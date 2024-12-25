<?php
session_start();
include("../../conexion/configv2.php");

// Verifica si la solicitud es POST y el nuevo correo está definido
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Leer el cuerpo JSON de la solicitud
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['nuevo_email'])) {
        $usuario = $_SESSION['nombre_usuario']; // Obtiene el nombre de usuario de la sesión
        $nuevo_email = filter_var($input['nuevo_email'], FILTER_SANITIZE_EMAIL);

        // Validar el nuevo correo electrónico
        if (!filter_var($nuevo_email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'El correo electrónico no es válido.']);
            exit;
        }

        // Obtener el correo actual del usuario
        $query_actual = "SELECT email FROM usuarios WHERE nombre_usuario = $1";
        $result_actual = pg_query_params($conn, $query_actual, array($usuario));
        if ($result_actual) {
            $correo_actual = pg_fetch_result($result_actual, 0, 'email');
            
            // Verificar si el nuevo correo es el mismo que el actual
            if ($nuevo_email === $correo_actual) {
                echo json_encode(['success' => false, 'message' => 'El nuevo correo es el mismo que el actual. No se realizaron cambios.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al verificar el correo actual: ' . pg_last_error($conn)]);
            exit;
        }

        // Verificar si el nuevo correo ya existe en la base de datos
        $verificar_query = "SELECT COUNT(*) FROM usuarios WHERE email = $1";
        $verificar_result = pg_query_params($conn, $verificar_query, array($nuevo_email));
        if ($verificar_result) {
            $row = pg_fetch_result($verificar_result, 0, 0);
            if ($row > 0) {
                echo json_encode(['success' => false, 'message' => 'El correo electrónico ya está en uso por otro usuario.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al verificar el correo: ' . pg_last_error($conn)]);
            exit;
        }

        // Actualizar el correo electrónico en la base de datos
        $query = "UPDATE usuarios SET email = $1 WHERE nombre_usuario = $2";
        $result = pg_query_params($conn, $query, array($nuevo_email, $usuario));

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Correo actualizado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el correo: ' . pg_last_error($conn)]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Falta el nuevo correo.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud no válida.']);
}
?>
