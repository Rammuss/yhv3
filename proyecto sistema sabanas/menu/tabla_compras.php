<?php
// Incluir el archivo de configuración
include("../conexion/config.php");


$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Consulta SQL para obtener los datos de la tabla 'compras' con el nombre del proveedor
$query = "
    SELECT c.id_compra, c.numero_factura, c.fecha_factura, p.nombre AS nombre_proveedor, c.id_orden_compra, c.condicion_pago, c.cantidad_cuotas
    FROM compras c
    JOIN proveedores p ON c.id_proveedor = p.id_proveedor
";
$result = pg_query($conn, $query);

if (!$result) {
    die("Error en la consulta: " . pg_last_error());
}

// Convertir los datos a formato JSON
$compras = [];
while ($row = pg_fetch_assoc($result)) {
    $compras[] = $row;
}

// Cerrar la conexión
pg_free_result($result);
pg_close($conn);

// Devolver los datos en formato JSON
header('Content-Type: application/json');
echo json_encode($compras);
?>
