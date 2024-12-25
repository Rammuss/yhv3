<?php
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


if (!$conn) {
    die("Error en la conexión a la base de datos: " . pg_last_error());
}

$jsonData = file_get_contents("php://input");

$data = json_decode($jsonData, true);

 if ($data !== null){
  $data = intval($data['id_oc']);
  $sql = "SELECT p1.id_orden_compra, p1.id_producto, p2.nombre, p1.cantidad FROM orden_compra_detalle AS p1 
  LEFT JOIN producto AS p2 ON p1.id_producto = p2.id_producto
  WHERE p1.id_orden_compra = $data;";



$result = pg_query($conn, $sql);

$data = array();

while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

echo json_encode($data);  
// echo json_encode($fecha);  
}
else{
    echo json_encode($_POST);  
    
}



pg_close($conn);
