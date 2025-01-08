<?php
include '../conexion/configv2.php'; // Conexión a la base de datos

$id_venta = $_GET['id_venta'];

// Obtener la venta
$query = "
    SELECT 
        v.id AS id_venta, 
        v.numero_factura, 
        v.fecha, 
        v.forma_pago, 
        v.estado, 
        v.cliente_id,
        c.nombre AS nombre_cliente, 
        c.apellido AS apellido_cliente, 
        c.ruc_ci AS cedula_cliente
    FROM ventas v
    LEFT JOIN clientes c ON v.cliente_id = c.id_cliente
    WHERE v.id = $id_venta
";
$result = pg_query($conn, $query);
$venta = pg_fetch_assoc($result);

// Obtener los detalles de la venta y calcular el monto total
$query_detalles = "
    SELECT 
        d.producto_id, 
        p.nombre AS nombre_producto, 
        d.cantidad, 
        d.precio_unitario, 
        (d.cantidad * d.precio_unitario) AS monto
    FROM detalle_venta d
    LEFT JOIN producto p ON d.producto_id = p.id_producto
    WHERE d.venta_id = $id_venta
";
$result_detalles = pg_query($conn, $query_detalles);
$detalles = pg_fetch_all($result_detalles);

// Calcular el monto total de la venta
$monto_total = 0;
foreach ($detalles as $detalle) {
    $monto_total += $detalle['monto']; // Sumar cada monto calculado para cada producto
}

// Añadir el monto total al array de la venta
$venta['monto_total'] = $monto_total;

echo json_encode([
    'success' => true,
    'data' => [
        'venta' => $venta,
        'detalles' => $detalles
    ]
]);
