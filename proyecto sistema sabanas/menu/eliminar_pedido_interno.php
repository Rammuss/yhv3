<?php
include("../conexion/config.php");

// Conexión a PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    echo "Error de conexión a PostgreSQL.";
    exit;
}

// Obtener el ID del pedido a eliminar desde la solicitud GET
$numero_pedido = $_GET['numero_pedido'];

// Consulta SQL para eliminar el pedido
$query = "DELETE FROM cabecera_pedido_interno WHERE numero_pedido = $1";
$result = pg_query_params($conn, $query, array($numero_pedido));

if (!$result) {
    echo "Error al ejecutar la consulta.";
    exit;
}

// Cerrar la conexión a PostgreSQL
pg_close($conn);

echo "Pedido eliminado correctamente.";
?>
