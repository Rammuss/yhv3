<?php
include("../conexion/config.php");

// Conectar a la base de datos
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
    exit;
}

$query = 'SELECT id_compra FROM compras';
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => 'Error al obtener los IDs de compra.']);
    exit;
}

$ids_compra = [];
while ($row = pg_fetch_assoc($result)) {
    $ids_compra[] = $row['id_compra'];
}

echo json_encode(['ids_compra' => $ids_compra]);

pg_close($conn);
?>
