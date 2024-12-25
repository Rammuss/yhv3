<?php
// Conexión a la base de datos PostgreSQL
include("../conexion/config.php");
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}


// Obtener el número de pedido desde la solicitud GET
$numero_pedido = $_GET['numero_pedido'];

// Consulta SQL para obtener los datos del pedido
$query = "
    SELECT 
        c.numero_pedido,
        c.departamento_solicitante,
        c.telefono,
        c.correo,
        c.fecha_pedido,
        c.fecha_entrega_solicitada,
        d.id,
        d.id_producto,
        d.nombre_producto,
        d.cantidad
    FROM 
        cabecera_pedido_interno c
    LEFT JOIN 
        detalle_pedido_interno d ON c.numero_pedido = d.numero_pedido
    WHERE 
        c.numero_pedido = $1
";

// Ejecutar la consulta con parámetros seguros
$result = pg_query_params($conn, $query, array($numero_pedido));

if (!$result) {
    echo json_encode(['error' => 'Error al ejecutar la consulta de los detalles del pedido.']);
    exit;
}

// Preparar el arreglo para almacenar los datos
$data = [];

// Recorrer los resultados y almacenarlos en el arreglo
while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

// Devolver la respuesta en formato JSON
echo json_encode($data);

// Cerrar la conexión
pg_close($conn);
?>