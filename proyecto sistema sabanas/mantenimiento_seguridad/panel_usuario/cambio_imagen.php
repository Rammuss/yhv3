<?php
session_start();
include("../../conexion/configv2.php");

$response = ['success' => false, 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagen_perfil'])) {
    // Obtener el nombre del usuario de la sesión
    $usuario = $_SESSION['nombre_usuario'];

    // Obtener la información del archivo de la imagen
    $imagen = $_FILES['imagen_perfil'];
    $nombre_imagen = $imagen['name'];
    $tipo_imagen = $imagen['type'];
    $tmp_imagen = $imagen['tmp_name'];
    $error_imagen = $imagen['error'];

    // Verificar si hubo algún error con el archivo
    if ($error_imagen === 0) {
        // Verificar que el archivo es una imagen
        $extensiones_validas = ['image/jpeg', 'image/png', 'image/jpg'];
        if (in_array($tipo_imagen, $extensiones_validas)) {
            // Generar un nombre único para la imagen
            $nuevo_nombre_imagen = uniqid('perfil_', true) . '.' . pathinfo($nombre_imagen, PATHINFO_EXTENSION);

            // Directorio donde se guardarán las imágenes
            $directorio_imagenes = 'imagenes_perfil/'; // Asegúrate de que esta carpeta exista
            $ruta_imagen = $directorio_imagenes . $nuevo_nombre_imagen;

            // Mover la imagen al directorio
            if (move_uploaded_file($tmp_imagen, $ruta_imagen)) {
                // Conexión a la base de datos
                $sql = "SELECT imagen_perfil FROM usuarios WHERE nombre_usuario = $1";
                $result = pg_query_params($conn, $sql, array($usuario));
                $usuario_data = pg_fetch_assoc($result);

                // Si ya existe una imagen, eliminarla
                if ($usuario_data['imagen_perfil']) {
                    $imagen_anterior = $usuario_data['imagen_perfil'];
                    // Eliminar la imagen anterior del servidor
                    if (file_exists($directorio_imagenes . $imagen_anterior)) {
                        unlink($directorio_imagenes . $imagen_anterior);
                    }
                }

                // Actualizar la base de datos con el nuevo nombre de la imagen
                $sql_update = "UPDATE usuarios SET imagen_perfil = $1 WHERE nombre_usuario = $2";
                $result_update = pg_query_params($conn, $sql_update, array($nuevo_nombre_imagen, $usuario));

                if ($result_update) {
                    $response['success'] = true;
                    $response['message'] = 'Imagen de perfil actualizada correctamente.';
                    $response['imageName'] = $nuevo_nombre_imagen;
                } else {
                    $response['message'] = 'Error al actualizar la imagen de perfil.';
                }
            } else {
                $response['message'] = 'Error al mover la imagen al servidor.';
            }
        } else {
            $response['message'] = 'Solo se permiten imágenes en formato JPG, JPEG o PNG.';
        }
    } else {
        $response['message'] = 'Hubo un error al cargar la imagen.';
    }
}

// Responder en formato JSON
header('Content-Type: application/json');
echo json_encode($response);
?>
