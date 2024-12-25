<?php
include("../conexion/config.php");

// Conectar a la base de datos
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
    exit;
}

$id_compra = $_GET['id_compra'];

$query = 'SELECT id_producto, descripcion, cantidad, precio_unitario FROM detalle_compras WHERE id_compra = $1';
$result = pg_query_params($conn, $query, [$id_compra]);

if (!$result) {
    echo json_encode(['error' => 'Error al obtener los detalles de compra.']);
    exit;
}

$detalles = [];
while ($row = pg_fetch_assoc($result)) {
    $detalles[] = $row;
}

echo json_encode(['detalles' => $detalles]);

pg_close($conn);
?>
