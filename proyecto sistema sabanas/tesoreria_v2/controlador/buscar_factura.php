<?php
// Configuración de conexión a la base de datos
include "../../conexion/configv2.php";

// Obtener criterios de búsqueda
$estado_pago = isset($_GET['estado_pago']) ? $_GET['estado_pago'] : null;
$numero_factura = isset($_GET['numero_factura']) ? $_GET['numero_factura'] : null;
$fecha_emision = isset($_GET['fecha_emision']) ? $_GET['fecha_emision'] : null;
$ruc_proveedor = isset($_GET['ruc_proveedor']) ? $_GET['ruc_proveedor'] : null; // Nuevo criterio

// Construir consulta SQL dinámica
$query = "
    SELECT f.*, p.ruc  -- Seleccionamos todos los campos de facturas y el campo ruc de proveedores
    FROM facturas_cabecera_t f
    LEFT JOIN proveedores p ON f.id_proveedor = p.id_proveedor
    WHERE f.estado_pago = 'Pendiente'  -- Solo mostrar facturas con estado 'Pendiente'
";

// Inicializamos el array de parámetros
$params = [];

// Agregar filtros dinámicamente
if ($estado_pago) {
    $query .= " AND f.estado_pago = $1";
    $params[] = $estado_pago;
}
if ($numero_factura) {
    $query .= " AND f.numero_factura ILIKE $".(count($params) + 1);
    $params[] = "%$numero_factura%";
}
if ($fecha_emision) {
    $query .= " AND f.fecha_emision = $".(count($params) + 1);
    $params[] = $fecha_emision;
}
if ($ruc_proveedor) {  // Filtro por RUC
    $query .= " AND p.ruc ILIKE $".(count($params) + 1);
    $params[] = "%$ruc_proveedor%";
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
