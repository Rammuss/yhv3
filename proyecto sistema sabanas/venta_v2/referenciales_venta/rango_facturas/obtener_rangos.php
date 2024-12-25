<?php
include '../../../conexion/configv2.php';

header('Content-Type: application/json');

$query = "SELECT id, timbrado, rango_inicio, rango_fin, actual, fecha_inicio, fecha_fin, activo FROM rango_facturas";
$result = pg_query($conn, $query);

$rangos = [];
while ($row = pg_fetch_assoc($result)) {
    $rangos[] = $row;
}

echo json_encode($rangos);

pg_close($conn);
?>
