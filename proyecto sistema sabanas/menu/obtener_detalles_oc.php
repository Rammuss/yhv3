<?php
// Incluir tu archivo de conexión a la base de datos
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

// Obtener el ID de la orden de compra de la solicitud
$id_orden_compra = $_GET['id_orden_compra'];

// Consulta para obtener detalles de la orden de compra seleccionada
$query = "
    SELECT 
        d.id_detalle, 
        d.id_producto, 
        d.descripcion, 
        d.cantidad, 
        d.precio_unitario, 
        (d.cantidad * d.precio_unitario) AS total 
    FROM 
        public.detalle_orden_compra d
    WHERE 
        d.id_orden_compra = $1
";
$result = pg_query_params($conn, $query, [$id_orden_compra]);

if (!$result) {
    echo json_encode(['error' => 'Error en la consulta']);
    exit;
}

// Obtener los datos y formatearlos en JSON
$detalles = [];
while ($row = pg_fetch_assoc($result)) {
    $detalles[] = $row;
}

echo json_encode($detalles);
