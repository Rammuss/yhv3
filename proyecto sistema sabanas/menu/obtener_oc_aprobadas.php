<?php
// Incluir tu archivo de conexión a la base de datos
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");


// Consulta para obtener órdenes de compra con estado "Aprobado"
$query = "SELECT id_orden_compra FROM public.ordenes_compra WHERE estado_orden = 'Aprobado'";
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => 'Error en la consulta']);
    exit;
}

// Obtener los datos y formatearlos en JSON
$ordenes = [];
while ($row = pg_fetch_assoc($result)) {
    $ordenes[] = $row;
}

echo json_encode($ordenes);
?>
