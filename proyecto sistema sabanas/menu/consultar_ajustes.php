<?php
// Incluir el archivo de configuración
include("../conexion/config.php");


// Crear conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error en la conexión a la base de datos.");
}

// Consultar ajustes de inventario
$query = "SELECT * FROM ajustes_inventario ORDER BY fecha_ajuste DESC";
$result = pg_query($conn, $query);

if (!$result) {
    die("Error en la consulta: " . pg_last_error($conn));
}

// Retornar los datos en formato JSON
$data = pg_fetch_all($result);
echo json_encode($data);

// Cerrar la conexión
pg_close($conn);
?>
