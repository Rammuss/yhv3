<?php
// Configuración de conexión a PostgreSQL
include("../conexion/config.php");

// Crear una cadena de conexión
$conn_string = "host=$host dbname=$dbname user=$user password=$password";

// Establecer la conexión a la base de datos
$conn = pg_connect($conn_string);

if (!$conn) {
    // Manejo de errores de conexión
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error en la conexión a la base de datos']);
    exit;
}

// Obtener el ID del presupuesto desde la consulta GET
$id_presupuesto = $_GET['id_presupuesto'];

// Sanitizar el ID del presupuesto para evitar inyección SQL
$id_presupuesto = pg_escape_string($id_presupuesto);

// Preparar la consulta SQL con parámetros
$sql = "
    SELECT
        p.id_presupuesto,
        pr.nombre AS nombre_proveedor,
        p.fecharegistro,
        p.fechavencimiento,
        p.estado,
        d.id_presupuesto_detalle,
        pro.nombre AS nombre_producto,
        d.cantidad,
        d.precio_unitario,
        d.precio_total
    FROM
        public.presupuestos p
    LEFT JOIN
        public.proveedores pr
    ON
        p.id_proveedor = pr.id_proveedor
    LEFT JOIN
        public.presupuesto_detalle d
    ON
        p.id_presupuesto = d.id_presupuesto
    LEFT JOIN
        public.producto pro
    ON
        d.id_producto = pro.id_producto
    WHERE
        p.id_presupuesto = $1
";

// Preparar la consulta
$result = pg_prepare($conn, "my_query", $sql);
if (!$result) {
    // Manejo de errores de preparación
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error en la preparación de la consulta SQL']);
    pg_close($conn);
    exit;
}

// Ejecutar la consulta con el parámetro
$result = pg_execute($conn, "my_query", array($id_presupuesto));

if (!$result) {
    // Manejo de errores de consulta
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error en la consulta SQL']);
    pg_close($conn);
    exit;
}

// Obtener los resultados
$presupuestos = [];
while ($row = pg_fetch_assoc($result)) {
    $presupuestos[] = $row;
}

// Verificar si se encontraron datos
if ($presupuestos) {
    // Devolver los datos en formato JSON
    header('Content-Type: application/json');
    echo json_encode($presupuestos);
} else {
    // En caso de no encontrar el presupuesto, devolver un mensaje de error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Presupuesto no encontrado']);
}

// Cerrar la conexión
pg_close($conn);
?>
