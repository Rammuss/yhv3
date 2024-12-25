<?php
// Conexión a la base de datos PostgreSQL
include("../conexion/config.php");


$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Consulta SQL para obtener el número de orden máximo
$query = "SELECT MAX(id_orden_compra) AS max_numero_orden FROM orden_compra";
$result = pg_query($conn, $query);

if ($row = pg_fetch_assoc($result)) {
    $max_numero_orden = $row['max_numero_orden'];
    $numero_orden_disponible = $max_numero_orden + 1;
} else {
    $numero_orden_disponible = 1; // Valor predeterminado si no se encuentra ningún registro
}
// Crear un arreglo asociativo con el número de orden
$respuesta = array("numero_orden" => $numero_orden_disponible);

// Convertir el arreglo en JSON
echo json_encode($respuesta);
