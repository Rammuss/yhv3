<?php
session_start(); // Iniciar sesión para manejar la verificación

// Configuración de conexión a PostgreSQL
include("../../conexion/configv2.php");

// Encabezado para indicar que la respuesta es JSON
header('Content-Type: application/json');

$response = ['success' => false, 'mensaje' => '']; // Inicializar la respuesta

// Manejo de la verificación del código
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $codigo = $_POST['codigo'];

    // Supongamos que el código se almacenó en la sesión cuando se envió el correo
    if (isset($_SESSION['codigo_verificacion'])) {
        // Verificar el código ingresado
        if (trim($codigo) === trim($_SESSION['codigo_verificacion'])) {
            // Código correcto, redirigir o realizar otra acción
            $response['success'] = true;
            $response['mensaje'] = "Código verificado exitosamente. ¡Bienvenido!";
            // Aquí podrías realizar acciones adicionales, como cambiar el estado del usuario a 'verificado'
            // Por ejemplo: 
            // $updateQuery = "UPDATE usuarios SET verificado = TRUE WHERE id = $1";
            // pg_query_params($conn, $updateQuery, array($_SESSION['user_id']));
        } else {
            $response['mensaje'] = "Código de verificación incorrecto. Inténtalo de nuevo.";
        }
    } else {
        $response['mensaje'] = "No se encontró el código de verificación. Por favor, solicita un nuevo código.";
    }

    echo json_encode($response); // Devolver la respuesta en formato JSON
    exit();
}
?>