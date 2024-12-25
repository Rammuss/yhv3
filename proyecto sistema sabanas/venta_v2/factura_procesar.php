<?php
// Configuración de la conexión a PostgreSQL
include '../conexion/configv2.php';

// Leer el cuerpo JSON enviado por el frontend
$input = file_get_contents("php://input");
$data = json_decode($input, true);

// Verificar si el JSON es válido
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "El formato del JSON es inválido: " . json_last_error_msg()
    ]);
    exit;
}

// Verificar que los parámetros obligatorios están presentes en el JSON
if (
    empty($data['cabecera']['id_cliente']) ||
    empty($data['cabecera']['forma_pago']) ||
    !isset($data['cabecera']['cantidad_cuotas']) || // Permitir 0 como valor válido
    empty($data['detalle']) ||
    empty($data['cabecera']['fecha_venta'])
) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Faltan parámetros obligatorios en el JSON."
    ]);
    exit;
}

// Acceder a los valores dentro de 'cabecera'
$cliente_id = (int) $data['cabecera']['id_cliente'];
$forma_pago = $data['cabecera']['forma_pago'];
$cuotas = (int) $data['cabecera']['cantidad_cuotas'];
$fecha = $data['cabecera']['fecha_venta'];

// Validar el formato de la fecha
if (!strtotime($fecha)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "El formato de la fecha es inválido."
    ]);
    exit;
}

// Acceder a los detalles de la venta y convertir a JSON
$detalles_pg = json_encode($data['detalle']);
if ($detalles_pg === false) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Error al convertir los detalles a JSON: " . json_last_error_msg()
    ]);
    exit;
}

// Preparar la llamada al SP
$query = "
    SELECT public.generar_venta(
        $1, $2, $3, $4::jsonb, $5::timestamp
    ) AS venta_id
";

$params = [
    $cliente_id,
    $forma_pago,
    $cuotas,
    $detalles_pg,
    $fecha
];

// Ejecutar la consulta
$result = pg_query_params($conn, $query, $params);

if ($result) {
    // Obtener el `venta_id` del resultado del SP
    $venta_id = pg_fetch_result($result, 0, 'venta_id');

    echo json_encode([
        "success" => true,
        "message" => "Venta procesada correctamente. $venta_id",
        "venta_id" => $venta_id
    ]);
} else {
    $error = pg_last_error($conn);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error al procesar la venta: $error"
    ]);
}

// Cerrar la conexión
pg_close($conn);
?>
