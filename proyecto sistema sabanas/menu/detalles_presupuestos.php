<?php
// Configuración de conexión a la base de datos
include("../conexion/config.php");

// Establece la conexión con la base de datos
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

// Verificar si la conexión fue exitosa
if (!$conn) {
    echo json_encode(['error' => 'Error al conectar con la base de datos']);
    exit;
}

// Verificar que se ha enviado el ID del presupuesto
if (isset($_GET['id'])) {
    $presupuesto_id = intval($_GET['id']); // Sanitizar el ID del presupuesto

    // Consulta para obtener los detalles del presupuesto
    $query = "
        SELECT
            p.id_producto,
            p.nombre,
            d.cantidad,
            d.precio_unitario
        FROM
            presupuesto_detalle d
            JOIN producto p ON d.id_producto = p.id_producto
        WHERE
            d.id_presupuesto = $1
    ";
    $result = pg_query_params($conn, $query, array($presupuesto_id));

    if ($result) {
        $detalles = [];
        while ($row = pg_fetch_assoc($result)) {
            $detalles[] = $row;
        }
        echo json_encode(['detalles' => $detalles]);
    } else {
        echo json_encode(['error' => 'Error en la consulta']);
    }
} else {
    echo json_encode(['error' => 'ID de presupuesto no proporcionado']);
}

// Cerrar la conexión
pg_close($conn);
?>
