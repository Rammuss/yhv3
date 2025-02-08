<?php
header('Content-Type: application/json');

// Configuración de conexión a PostgreSQL
include "../../conexion/configv2.php";

// Consulta para obtener órdenes de pago disponibles para anular
$query = "
    SELECT 
        op.id_orden_pago, 
        p.nombre AS proveedor, 
        op.monto, 
        op.metodo_pago, 
        op.referencia, 
        op.estado, 
        op.fecha_creacion,
        c.numero_cheque 
    FROM ordenes_pago op
    JOIN proveedores p ON op.id_proveedor = p.id_proveedor
    LEFT JOIN cheques c ON op.referencia = c.numero_cheque 
        AND op.metodo_pago = 'Cheque' 
    
    ORDER BY COALESCE(op.fecha_pago, '1900-01-01') DESC
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
echo json_encode($ordenes ?? []); // Si no hay resultados, devuelve un array vacío

// Cerrar conexión
pg_close($conn);
?>
