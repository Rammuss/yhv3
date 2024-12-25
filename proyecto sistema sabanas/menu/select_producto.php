<?php
// Conexión a la base de datos (asegúrate de establecer la conexión con tu configuración)
include("../conexion/config.php");
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}

// Consulta para obtener los proveedores

$query = "SELECT id_producto, nombre, precio_unitario, precio_compra FROM producto";
$result = pg_query($conn, $query);

if (!$result) {
    echo "Error en la consulta: " . pg_last_error();
} else {
    $producto = array();

    while ($row = pg_fetch_assoc($result)) {
        $producto[] = $row;
    }

    // Devolver los datos en formato JSON
    header('Content-Type: application/json');
    echo json_encode($producto);
}


// Cerrar la conexión a la base de datos
pg_close($conn);
?>
