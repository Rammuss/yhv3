<?php
// Archivo: buscar_facturas.php

// Conexión a la base de datos
include '../conexion/configv2.php'; // Asegúrate de que este archivo tiene la configuración correcta de conexión

// Obtener los parámetros desde el JSON enviado
$data = json_decode(file_get_contents('php://input'), true);

$fecha = $data['fecha'] ?? null;
$numero_factura = $data['numero_factura'] ?? null;
$ruc_ci = $data['ruc_ci'] ?? null;
$estado = $data['estado'] ?? null; // Obtener el estado desde el frontend (pendiente, pagado, anulado)

// Construir la consulta SQL base
$query = "SELECT 
    v.id AS id_venta, 
    v.numero_factura, 
    v.fecha, 
    v.forma_pago, 
    v.estado, 
    v.cuotas, 
    v.timbrado, 
    c.nombre AS nombre_cliente, 
    c.apellido AS apellido_cliente, 
    c.ruc_ci AS cedula_cliente
FROM ventas v
LEFT JOIN clientes c ON v.cliente_id = c.id_cliente
WHERE 1=1"; // Base para agregar filtros dinámicamente

// Agregar condiciones según los parámetros recibidos
if (!empty($fecha)) {
    $query .= " AND DATE(v.fecha) = '$fecha'";
}
if (!empty($numero_factura)) {
    $query .= " AND v.numero_factura = '$numero_factura'";
}
if (!empty($ruc_ci)) {
    $query .= " AND c.ruc_ci = '$ruc_ci'";
}
if (!empty($estado)) {
    // Filtrar por estado si se ha recibido un valor
    $query .= " AND v.estado = '$estado'";
}

// Ordenar por fecha descendente
$query .= " ORDER BY v.fecha DESC";

// Ejecutar la consulta
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al ejecutar la consulta: ' . pg_last_error($conn)
    ]);
    exit;
}

// Construir el resultado en un array
$facturas = [];
while ($row = pg_fetch_assoc($result)) {
    $facturas[] = $row;
}

// Devolver los resultados en formato JSON
echo json_encode([
    'success' => true,
    'data' => $facturas
]);
?>
