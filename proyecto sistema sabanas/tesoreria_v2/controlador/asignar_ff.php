<?php
// register.php

header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Leer la entrada JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No se recibieron datos válidos."]);
    exit;
}

// Recoger y sanitizar los datos
$proveedor_id    = isset($data['proveedor_id']) ? (int)$data['proveedor_id'] : null;
$monto           = isset($data['monto']) ? (float)$data['monto'] : null;
$fecha_asignacion = isset($data['fecha_asignacion']) ? $data['fecha_asignacion'] : null;
$estado          = isset($data['estado']) ? $data['estado'] : 'Activa';
$descripcion     = isset($data['descripcion']) ? $data['descripcion'] : null;

// Validaciones básicas
$errors = [];

if (!$proveedor_id) {
    $errors[] = "El proveedor es obligatorio.";
}
if (!$monto || $monto < 0) {
    $errors[] = "El monto es obligatorio y debe ser un valor positivo.";
}
if (!$fecha_asignacion) {
    $errors[] = "La fecha de asignación es obligatoria.";
}
if (!in_array($estado, ['Activa', 'Cerrada'])) {
    $errors[] = "El estado debe ser 'Activa' o 'Cerrada'.";
}

if (!empty($errors)) {
    echo json_encode(["success" => false, "message" => implode(" ", $errors)]);
    exit;
}

// Preparar la consulta SQL utilizando parámetros
$sql = "INSERT INTO asignaciones_ff (proveedor_id, monto, fecha_asignacion, estado, descripcion) 
        VALUES ($1, $2, $3, $4, $5)";

$result = pg_query_params($conn, $sql, [$proveedor_id, $monto, $fecha_asignacion, $estado, $descripcion]);

if ($result) {
    echo json_encode(["success" => true, "message" => "Registro exitoso."]);
} else {
    echo json_encode(["success" => false, "message" => "Error en la inserción: " . pg_last_error($conn)]);
}
?>
