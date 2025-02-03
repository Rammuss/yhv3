<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Consulta: se hace JOIN entre asignaciones_ff y proveedores para obtener datos del proveedor
$sql = "SELECT 
            a.id AS asignacion_id,
            a.fecha_asignacion,
            a.monto,
            p.nombre AS proveedor_nombre,
            p.ruc
        FROM asignaciones_ff a
        JOIN proveedores p ON a.proveedor_id = p.id_proveedor
        ORDER BY a.fecha_asignacion DESC";
$result = pg_query($conn, $sql);

if (!$result) {
    echo json_encode([
        "success" => false,
        "message" => "Error al obtener asignaciones: " . pg_last_error($conn)
    ]);
    exit;
}

$asignaciones = [];
while ($row = pg_fetch_assoc($result)) {
    $asignaciones[] = $row;
}

echo json_encode($asignaciones);
?>
