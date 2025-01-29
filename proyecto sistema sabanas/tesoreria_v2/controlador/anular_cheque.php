<?php
// anular_cheque.php
header('Content-Type: application/json');

// Conexión a la base de datos PostgreSQL
include "../../conexion/configv2.php";

// Obtener los datos enviados por el cliente
$data = json_decode(file_get_contents('php://input'), true);
$id_cheque = $data['id_cheque'];
$estado = $data['estado'];

// Consulta SQL para actualizar el estado del cheque
$query = "UPDATE cheques SET estado = $1 WHERE id = $2";

// Preparar y ejecutar la consulta
$result = pg_query_params($conn, $query, array($estado, $id_cheque));

if ($result) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar el estado del cheque.']);
}

// Cerrar la conexión a la base de datos
pg_close($conn);
?>
