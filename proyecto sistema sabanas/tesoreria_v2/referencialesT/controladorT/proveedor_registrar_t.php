<?php
// register_proveedor.php

header("Content-Type: application/json");
include "../../../conexion/configv2.php"; // Asegúrate de que la ruta sea la correcta

// Leer la entrada JSON
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    echo json_encode(["success" => false, "message" => "No se recibieron datos válidos."]);
    exit;
}

// Recoger y sanitizar los datos
$nombre    = isset($data['nombre']) ? trim($data['nombre']) : '';
$direccion = isset($data['direccion']) ? trim($data['direccion']) : '';
$telefono  = isset($data['telefono']) ? trim($data['telefono']) : '';
$email     = isset($data['email']) ? trim($data['email']) : '';
$ruc       = isset($data['ruc']) ? trim($data['ruc']) : '';
$id_pais   = isset($data['id_pais']) ? $data['id_pais'] : null;
$id_ciudad = isset($data['id_ciudad']) ? $data['id_ciudad'] : null;
$tipo      = isset($data['tipo']) ? trim($data['tipo']) : null;

// Validar campos obligatorios
$errors = [];
if (empty($nombre))    $errors[] = "El nombre es obligatorio.";
if (empty($direccion)) $errors[] = "La dirección es obligatoria.";
if (empty($telefono))  $errors[] = "El teléfono es obligatorio.";
if (empty($email))     $errors[] = "El email es obligatorio.";
if (empty($ruc))       $errors[] = "El RUC es obligatorio.";

if (!empty($errors)) {
    echo json_encode(["success" => false, "message" => implode(" ", $errors)]);
    exit;
}

// Preparar la consulta SQL utilizando parámetros
$sql = "INSERT INTO proveedores (nombre, direccion, telefono, email, ruc, id_pais, id_ciudad, tipo)
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8)";

$result = pg_query_params($conn, $sql, [$nombre, $direccion, $telefono, $email, $ruc, $id_pais, $id_ciudad, $tipo]);

if ($result) {
    echo json_encode(["success" => true, "message" => "Proveedor registrado exitosamente."]);
} else {
    echo json_encode(["success" => false, "message" => "Error en la inserción: " . pg_last_error($conn)]);
}
?>
