<?php
// Conexión a la base de datos (asegúrate de establecer la conexión con tu configuración)
include("../conexion/config.php");
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}

// Consulta para obtener los proveedores

$query = "SELECT id_proveedor, nombre, direccion, telefono, email, ruc, id_pais, id_ciudad FROM proveedores";
$result = pg_query($conn, $query);

if (!$result) {
    echo "Error en la consulta: " . pg_last_error();
} else {
    $proveedores = array();

    while ($row = pg_fetch_assoc($result)) {
        $proveedores[] = $row;
    }

    // Devolver los datos en formato JSON
    header('Content-Type: application/json');
    echo json_encode($proveedores);
}

// Cerrar la conexión a la base de datos
pg_close($conn);
?>
