<?php
include "../../conexion/configv2.php";

// Obtener datos del JSON recibido
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['id_provision'])) {
    die(json_encode(["success" => false, "message" => "ID de provisión no proporcionado."]));
}

$id_provision = $data['id_provision'];

try {
    // Verificar si la provisión ya está anulada
    $query_check = "SELECT estado_provision FROM provisiones_cuentas_pagar WHERE id_provision = $1;";
    $result_check = pg_query_params($conn, $query_check, array($id_provision));

    if (!$result_check) {
        die(json_encode(["success" => false, "message" => "Error al verificar la provisión."]));
    }

    $provision = pg_fetch_assoc($result_check);
    if ($provision['estado_provision'] === 'anulado') {
        die(json_encode(["success" => false, "message" => "Esta provisión ya está anulada."]));
    }

    // Anular la provisión
    $query_update = "UPDATE provisiones_cuentas_pagar SET estado_provision = 'anulado' WHERE id_provision = $1;";
    $result_update = pg_query_params($conn, $query_update, array($id_provision));

    if (!$result_update) {
        die(json_encode(["success" => false, "message" => "Error al anular la provisión."]));
    }

    echo json_encode(["success" => true, "message" => "Provisión anulada exitosamente."]);
} catch (Exception $e) {
    die(json_encode(["success" => false, "message" => "Error interno del servidor: " . $e->getMessage()]));
}

// Cerrar conexión
pg_close($conn);
?>
