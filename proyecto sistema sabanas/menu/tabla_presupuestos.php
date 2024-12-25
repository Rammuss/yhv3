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
  $fecha = strval($data['fecha']);
  $sql = "select p1.id_presupuesto, p1.id_proveedor, p3.nombre, p1.fecharegistro, p1.fechavencimiento, 
  p2.id_producto, p4.nombre as nombre_producto, p2.cantidad, p2.precio_unitario, p2.precio_total 
  from presupuestos as p1 
  left join presupuesto_detalle as p2 
  on p1.id_presupuesto = p2.id_presupuesto
  left join proveedores as p3 
  on p1.id_proveedor= p3.id_proveedor
  left join producto as p4 
  on p2.id_producto= p4.id_producto
  where p2.id_producto is not null and p1.fecharegistro >= '$fecha'";



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
