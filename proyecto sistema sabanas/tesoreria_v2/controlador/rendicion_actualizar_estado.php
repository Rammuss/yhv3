<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Leer la entrada JSON
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (!isset($data['id']) || !isset($data['estado'])) {
  echo json_encode(["success" => false, "message" => "Datos incompletos"]);
  exit;
}

$id = (int)$data['id'];
$estado = $data['estado'];

// Validar que el estado sea uno de los permitidos
if (!in_array($estado, ['Aprobada', 'Rechazada'])) {
  echo json_encode(["success" => false, "message" => "Estado no válido"]);
  exit;
}

$sql = "UPDATE rendiciones_ff SET estado = $1 WHERE id = $2";
$result = pg_query_params($conn, $sql, [$estado, $id]);

if ($result) {
  echo json_encode(["success" => true, "message" => "Estado actualizado correctamente"]);
} else {
  echo json_encode(["success" => false, "message" => "Error al actualizar estado: " . pg_last_error($conn)]);
}
?>
