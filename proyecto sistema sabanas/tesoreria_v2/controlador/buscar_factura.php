<?php
// Configuración de conexión a la base de datos
include "../../conexion/configv2.php";

// Obtener criterios de búsqueda
$estado_pago = isset($_GET['estado_pago']) ? $_GET['estado_pago'] : null;
$numero_factura = isset($_GET['numero_factura']) ? $_GET['numero_factura'] : null;
$fecha_emision = isset($_GET['fecha_emision']) ? $_GET['fecha_emision'] : null;

// Construir consulta SQL dinámica
$query = "SELECT * FROM facturas_cabecera_t WHERE 1=1";
$params = [];

// Agregar filtros dinámicamente
if ($estado_pago) {
    $query .= " AND estado_pago = $1";
    $params[] = $estado_pago;
}
if ($numero_factura) {
    $query .= " AND numero_factura ILIKE $".(count($params) + 1);
    $params[] = "%$numero_factura%";
}
if ($fecha_emision) {
    $query .= " AND fecha_emision = $".(count($params) + 1);
    $params[] = $fecha_emision;
}

// Preparar y ejecutar consulta
$result = pg_query_params($conn, $query, $params);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al ejecutar la consulta: ' . pg_last_error($conn)]);
    exit;
}

// Obtener resultados y devolver en formato JSON
$data = [];
while ($row = pg_fetch_assoc($result)) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);

// Cerrar conexión
pg_close($conn);
?>
