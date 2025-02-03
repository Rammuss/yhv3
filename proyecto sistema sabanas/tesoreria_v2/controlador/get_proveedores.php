<?php
// get_proveedores.php

header("Content-Type: application/json");
include "../../conexion/configv2.php";  // Asegúrate que la ruta sea correcta

// Consulta para obtener proveedores donde el tipo es 'Fondo Fijo', ordenados alfabéticamente
$sql = "SELECT id_proveedor, nombre FROM proveedores WHERE tipo = 'fondo_fijo' ORDER BY nombre ASC";
$result = pg_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener proveedores: " . pg_last_error($conn)
    ]);
    exit;
}

$proveedores = [];
while ($row = pg_fetch_assoc($result)) {
    $proveedores[] = $row;
}

echo json_encode([
    "success" => true,
    "proveedores" => $proveedores
]);
?>
