<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Consulta para obtener rendiciones con estado "Pendiente" junto con información de la asignación y proveedor.
$sql = "SELECT 
          r.id,
          r.fecha_rendicion,
          r.total_rendido,
          r.estado,
          -- Se asume que en asignaciones_ff existe el id del proveedor y se hace JOIN con proveedores
          CONCAT('Proveedor: ', p.nombre, ' - RUC: ', p.ruc, ' | Fecha Asig: ', a.fecha_asignacion) AS asignacion_info
        FROM rendiciones_ff r
        JOIN asignaciones_ff a ON r.asignacion_id = a.id
        JOIN proveedores p ON a.proveedor_id = p.id_proveedor
        WHERE r.estado = 'Pendiente'
        ORDER BY r.fecha_rendicion DESC";
        
$result = pg_query($conn, $sql);

if (!$result) {
  echo json_encode([]);
  exit;
}

$rendiciones = [];
while ($row = pg_fetch_assoc($result)) {
  $rendiciones[] = $row;
}

echo json_encode($rendiciones);
?>
