<?php
header("Content-Type: application/json"); // Establecer el tipo de contenido como JSON

include "../../conexion/configv2.php";

// Obtener los parámetros de búsqueda
$fecha = $_GET["fecha"] ?? null;
$estado = $_GET["estado"] ?? null;
$numeroFactura = $_GET["numero_factura"] ?? null;
$proveedor = $_GET["proveedor"] ?? null;

// Construir la consulta SQL con los filtros
$query = "
    SELECT 
        f.id_factura,
        f.numero_factura,
        p.nombre AS proveedor,
        f.fecha_emision,
        f.total,
        f.estado_pago,
        f.provision_generada, -- Incluir el campo provision_generada
        f.iva_generado -- Incluir el campo iva_generado
    FROM 
        facturas_cabecera_T f
    INNER JOIN 
        proveedores p ON f.id_proveedor = p.id_proveedor
    WHERE 
        1 = 1
";

// Aplicar filtros si están presentes
if ($fecha) {
    $query .= " AND f.fecha_emision = '$fecha'";
}
if ($estado) {
    $query .= " AND f.estado_pago = '$estado'";
}
if ($numeroFactura) {
    $query .= " AND f.numero_factura LIKE '%$numeroFactura%'";
}
if ($proveedor) {
    $query .= " AND p.nombre LIKE '%$proveedor%'";
}

// Ejecutar la consulta
$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode(["error" => "Error al buscar facturas"]);
    exit;
}

// Obtener los resultados
$facturas = [];
while ($row = pg_fetch_assoc($result)) {
    $facturas[] = $row;
}

// Devolver los resultados en formato JSON
echo json_encode($facturas);

// Cerrar la conexión
pg_close($conn);
?>