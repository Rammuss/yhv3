<?php
// Configuración de conexión a PostgreSQL
include("../conexion/config.php");

// Conexión a PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    echo "Error de conexión a PostgreSQL.";
    exit;
}

// Consulta SQL para obtener la tabla de cabecera de pedidos internos
$query = "SELECT 
    p.id_presupuesto,
    pr.nombre,
    p.fecharegistro,
    p.fechavencimiento,
    p.estado
FROM 
    public.presupuestos p
JOIN 
    public.proveedores pr ON p.id_proveedor = pr.id_proveedor;
";

$result = pg_query($conn, $query);

if (!$result) {
    echo "Error al ejecutar la consulta.";
    exit;
}

// Preparar resultados para ser devueltos como JSON (opcional)
$pedidos = array();
while ($row = pg_fetch_assoc($result)) {
    $pedidos[] = $row;
}

// Cerrar la conexión a PostgreSQL
pg_free_result($result);
pg_close($conn);

// Devolver los resultados como JSON (opcional)
header('Content-Type: application/json');
echo json_encode($pedidos);
?>
