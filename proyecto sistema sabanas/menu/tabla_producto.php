<?php
include("../conexion/config.php");

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}

$sql = "
  SELECT *
    FROM producto
   WHERE COALESCE(tipo_item, 'P') NOT IN ('S','D')";

$result = pg_query($conn, $sql);
$data = [];

while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode($data);

pg_close($conn);
?>