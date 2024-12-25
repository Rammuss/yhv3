<?php
// conectar a la base de datos
include("../../conexion/configv2.php");

// Obtener el ID del usuario a "eliminar"
$id = $_GET['id'];

// Ejecutar la consulta para actualizar el estado del usuario a false
$query = "UPDATE usuarios SET estado = false WHERE id = $1";
$result = pg_query_params($conn, $query, array($id));

// Verificar si la actualización fue exitosa
if ($result) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => pg_last_error($conn)]);
}

// Cerrar la conexión
pg_close($conn);
?>