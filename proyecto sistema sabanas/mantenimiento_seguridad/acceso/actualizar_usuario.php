<?php
// Configuración de conexión a PostgreSQL
include("../../conexion/configv2.php");



// Obtener los datos del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

$id = $data['id'];
$nombre_usuario = $data['nombre_usuario'];
$rol = $data['rol'];
$telefono = $data['telefono'];
$email = $data['email'];

// Preparar la consulta SQL para actualizar el usuario
$query = "UPDATE usuarios SET nombre_usuario = $1, rol = $2, telefono = $3, email = $4 WHERE id = $5";
$result = pg_query_params($conn, $query, [$nombre_usuario, $rol, $telefono, $email, $id]);

if ($result) {
    echo json_encode(["success" => true, "message" => "Usuario actualizado correctamente."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al actualizar el usuario."]);
}

// Cerrar la conexión
pg_close($conn);
?>
