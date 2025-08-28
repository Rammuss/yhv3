<?php
include("../conexion/config.php");

// Conexión a PostgreSQL
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    http_response_code(500);
    echo "Error de conexión a PostgreSQL.";
    exit;
}

// Obtener datos desde POST
$numero_pedido = $_POST['numero_pedido'] ?? null;
$motivo        = $_POST['motivo'] ?? null;

if (!$numero_pedido) {
    http_response_code(400);
    echo "Número de pedido no especificado.";
    exit;
}

// Consulta SQL para actualizar el estado a 'Anulado'
$query = "UPDATE cabecera_pedido_interno 
          SET estado = 'Anulado',
              motivo_anulacion = $2,
              fecha_cierre = CURRENT_DATE
          WHERE numero_pedido = $1";

$result = pg_query_params($conn, $query, array($numero_pedido, $motivo));

if (!$result) {
    http_response_code(500);
    echo "Error al ejecutar la consulta: " . pg_last_error($conn);
    exit;
}

// Cerrar la conexión
pg_close($conn);

// Respuesta
echo "Pedido $numero_pedido anulado correctamente.";
?>
