<?php
include '../../conexion/configv2.php';

$data = json_decode(file_get_contents('php://input'), true);

// Validar campos requeridos
if (empty($data['id_provision']) || empty($data['id_proveedor']) || empty($data['monto'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Insertar en la tabla ordenes_pago
$query = "
    INSERT INTO ordenes_pago (
        id_provision,
        id_proveedor,
        monto,
        metodo_pago,
        id_cuenta_bancaria,
        referencia,
        id_usuario_creacion,
        estado
    ) VALUES ($1, $2, $3, $4, $5, $6, $7, 'Pendiente')
";

$params = [
    $data['id_provision'],
    $data['id_proveedor'],
    $data['monto'],
    $data['metodo_pago'],
    $data['id_cuenta_bancaria'],
    $data['referencia'] ?? null,
    $data['id_usuario_creacion']
];

$result = pg_query_params($conn, $query, $params);

if ($result) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => pg_last_error()]);
}
?>