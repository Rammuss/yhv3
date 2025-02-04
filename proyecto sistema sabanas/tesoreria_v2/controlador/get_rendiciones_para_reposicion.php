<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Consulta para obtener las rendiciones aprobadas junto con información de la asignación y proveedor
$sql = "SELECT 
          r.id,
          r.fecha_rendicion,
          r.total_rendido,
          a.monto AS monto_asignado,
          CONCAT('Proveedor: ', p.nombre, ' - RUC: ', p.ruc, ' | Fecha Asig: ', a.fecha_asignacion, ' | Monto Asignado: ', a.monto) AS asignacion_info
        FROM rendiciones_ff r
        JOIN asignaciones_ff a ON r.asignacion_id = a.id
        JOIN proveedores p ON a.proveedor_id = p.id_proveedor
        WHERE r.estado = 'Aprobada'
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
