<?php
// Conexión a la base de datos utilizando pg_connect
include '../conexion/configv2.php';

// Recibir datos POST en formato JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id_remision']) || !isset($data['nuevo_estado'])) {
    echo json_encode(["error" => "Faltan parámetros"]);
    exit;
}

$id_remision = $data['id_remision'];
$nuevo_estado = $data['nuevo_estado'];

// Validar estado actual antes de actualizar
$query = "SELECT estado FROM public.nota_remision_venta_cabecera WHERE id_remision = $1";
$result = pg_query_params($conn, $query, array($id_remision));

if (!$result || pg_num_rows($result) == 0) {
    echo json_encode(["error" => "Remisión no encontrada"]);
    exit;
}

$row = pg_fetch_assoc($result);
$estado_actual = $row['estado'];

// Verificar que el cambio de estado es válido
if ($estado_actual == 'anulado' && $nuevo_estado == 'completado') {
    echo json_encode(["error" => "No se puede cambiar de 'Anulado' a 'Completado'"]);
    exit;
} elseif ($estado_actual == 'completado' && $nuevo_estado == 'anulado') {
    echo json_encode(["error" => "No se puede cambiar de 'Completado' a 'Anulado'"]);
    exit;
} elseif ($estado_actual == $nuevo_estado) {
    echo json_encode(["error" => "El estado ya es el mismo"]);
    exit;
}

// Actualizar el estado
$update_query = "UPDATE public.nota_remision_venta_cabecera SET estado = $1 WHERE id_remision = $2";
$update_result = pg_query_params($conn, $update_query, array($nuevo_estado, $id_remision));

// Verificar si se actualizó correctamente
if ($update_result && pg_affected_rows($update_result) > 0) {
    echo json_encode(["mensaje" => "Estado actualizado con éxito"]);
} else {
    echo json_encode(["error" => "Error al actualizar el estado"]);
}

// Cerrar la conexión
pg_close($conn);
?>
