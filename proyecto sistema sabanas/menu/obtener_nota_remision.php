<?php
include("../conexion/config.php");

// Conectar a la base de datos
$conn = pg_connect("host=$host dbname=$dbname user=$user password=$password");

if (!$conn) {
    echo json_encode(['error' => 'No se pudo conectar a la base de datos.']);
    exit;
}

// Consulta para obtener los datos de la tabla nota_remision
$query = 'SELECT id_nota_remision, numero_remision, fecha_remision, id_proveedor, id_compra, estado FROM public.nota_remision';
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode(['error' => 'Error al obtener los datos: ' . pg_last_error($conn)]);
    exit;
}

$notas_remision = [];
while ($row = pg_fetch_assoc($result)) {
    $notas_remision[] = $row;
}

pg_close($conn);

// Retornar los datos en formato JSON
echo json_encode($notas_remision);
?>
