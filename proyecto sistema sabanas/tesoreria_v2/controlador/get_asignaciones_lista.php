<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Consulta para obtener las asignaciones junto con la información del proveedor.
$sql = "SELECT 
          a.id,
          a.monto,
          a.fecha_asignacion,
          a.estado,
          a.descripcion,
          p.nombre AS proveedor_nombre
        FROM asignaciones_ff a
        JOIN proveedores p ON a.proveedor_id = p.id_proveedor
        ORDER BY a.fecha_asignacion DESC";

$result = pg_query($conn, $sql);

if (!$result) {
  echo json_encode([]);
  exit;
}

$asignaciones = [];
while ($row = pg_fetch_assoc($result)) {
  $asignaciones[] = $row;
}

echo json_encode($asignaciones);
?>
