<?php
// Archivo: anular_factura.php

// Conexión a la base de datos
include '../conexion/configv2.php'; // Asegúrate de que este archivo tiene la configuración correcta de conexión

// Obtener los datos enviados
$data = json_decode(file_get_contents('php://input'), true);

$numero_factura = $data['numero_factura'] ?? null;
$estado = $data['estado'] ?? null;

// Verificar si los parámetros están disponibles
if (empty($numero_factura) || empty($estado)) {
    echo json_encode([
        'success' => false,
        'message' => 'Faltan datos para realizar la actualización.'
    ]);
    exit;
}

// Actualizar el estado de la factura en la base de datos
$query = "UPDATE ventas SET estado = '$estado' WHERE numero_factura = '$numero_factura'";

$result = pg_query($conn, $query);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar el estado de la factura: ' . pg_last_error($conn)
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Factura anulada correctamente.'
    ]);
}
?>
