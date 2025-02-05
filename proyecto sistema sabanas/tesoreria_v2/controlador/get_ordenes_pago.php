<?php
// Configurar los encabezados para la respuesta JSON
header('Content-Type: application/json');

include "../../conexion/configv2.php";

// Consulta SQL para obtener solo las órdenes de pago con estado "Pendiente"
$query = "
    SELECT 
        o.id_orden_pago, 
        o.id_cuenta_bancaria, 
        o.monto, 
        o.referencia AS referencia_bancaria,
        o.metodo_pago,
        p.nombre AS nombre_beneficiario
    FROM ordenes_pago o
    JOIN proveedores p ON o.id_proveedor = p.id_proveedor
    WHERE o.estado = 'Pendiente'  
    ORDER BY o.id_orden_pago;
";

// Ejecutar la consulta
$result = pg_query($conn, $query);
if (!$result) {
    echo json_encode(array("error" => "Error en la consulta."));
    pg_close($conn);
    exit;
}

// Recorrer el resultado y formar un arreglo asociativo
$data = array();
while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

// Devolver la respuesta en formato JSON
echo json_encode($data);

// Cerrar la conexión a la base de datos
pg_close($conn);
?>
