<?php
// get_paises.php

header("Content-Type: application/json");
include "../../../conexion/configv2.php";  // Asegúrate de que la ruta sea correcta

// Consulta para obtener id_pais y nombre de los países, ordenados alfabéticamente
$sql = "SELECT id_pais, nombre FROM paises ORDER BY nombre ASC";
$result = pg_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener países: " . pg_last_error($conn)
    ]);
    exit;
}

$paises = [];
while ($row = pg_fetch_assoc($result)) {
    $paises[] = $row;
}

echo json_encode([
    "success" => true,
    "paises" => $paises
]);
?>
