<?php
// Incluye la configuración de la base de datos
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

// Verifica la conexión
if (!$conn) {
    die("Error de conexión: No se pudo conectar a la base de datos.");
}

// Consulta SQL para obtener los datos de los usuarios
$sql = "SELECT id, nombre_usuario, rol FROM usuarios";

$result = pg_query($conn, $sql);

// Comprueba si hay resultados
if ($result) {
    $usuarios = array();

    while ($row = pg_fetch_assoc($result)) {
        $usuarios[] = $row;
    }

    // Devuelve los resultados como JSON
    header('Content-Type: application/json');
    echo json_encode($usuarios);
} else {
    // No se encontraron resultados
    echo json_encode(array("mensaje" => "No se encontraron usuarios."));
}

// Cierra la conexión a la base de datos
pg_close($conn);
?>
