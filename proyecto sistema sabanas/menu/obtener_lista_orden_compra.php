<?php
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}




$sql = "SELECT o.id_orden_compra, p.nombre AS nombre_proveedor, o.fecha
FROM orden_compra o
INNER JOIN proveedores p ON o.id_proveedor = p.id_proveedor";



$result = pg_query($conn, $sql);

$data = array();

while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode($data);

pg_close($conn);
