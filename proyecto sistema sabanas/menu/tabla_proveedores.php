<?php
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}




$sql = "SELECT proveedores.id_proveedor, proveedores.nombre, proveedores.direccion, proveedores.telefono, proveedores.email, proveedores.ruc, ciudades.nombre AS ciudad, paises.nombre AS pais
FROM proveedores
LEFT JOIN ciudades ON proveedores.id_ciudad = ciudades.id_ciudad
LEFT JOIN paises ON proveedores.id_pais = paises.id_pais;
";


$result = pg_query($conn, $sql);

$data = array();

while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode($data);

pg_close($conn);
