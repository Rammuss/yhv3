<?php
header('Content-Type: application/json');

// Configuración de conexión a PostgreSQL
include "../../conexion/configv2.php";

// Consulta para obtener las órdenes de pago con información adicional del proveedor y el cheque
$query = "
    SELECT op.id, op.id_cheque, p.nombre AS proveedor, op.monto_total, 
           op.fecha_orden, op.estado, c.numero_cheque
    FROM ordenes_pago op
    JOIN proveedores p ON op.id_proveedor = p.id_proveedor
    JOIN cheques c ON op.id_cheque = c.id
    ORDER BY op.fecha_orden DESC
";

$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode(["error" => "Error al obtener las órdenes de pago"]);
    pg_close($conn);
    exit;
}

// Convertimos los resultados en un array asociativo
$ordenes = pg_fetch_all($result);

// Enviar los datos en formato JSON
echo json_encode($ordenes);

// Cerrar conexión
pg_close($conn);
?>
