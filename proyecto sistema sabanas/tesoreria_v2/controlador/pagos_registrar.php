<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Verificamos si la solicitud es POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputJSON = file_get_contents("php://input");
    $data = json_decode($inputJSON, true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Datos inválidos"]);
        exit;
    }

    // Capturamos los datos del cuerpo de la solicitud
    $id_orden_pago = $data["id_orden_pago"];
    $id_cuenta_bancaria = $data["id_cuenta_bancaria"];
    $monto = $data["monto"];
    $referencia_bancaria = $data["referencia_bancaria"];
    $metodo_pago = $data["metodo_pago"]; // "Cheque" o cualquier otro
    $nombre_beneficiario = isset($data["nombre_beneficiario"]) ? $data["nombre_beneficiario"] : null;
    $id_usuario = 1;

    // **VALIDAR SI EL id_orden_pago YA EXISTE EN pagos_ejecutados**
    $query_verificar = "SELECT COUNT(*) FROM pagos_ejecutados WHERE id_orden_pago = $1";
    $result_verificar = pg_query_params($conn, $query_verificar, [$id_orden_pago]);
    
    if (!$result_verificar) {
        echo json_encode(["success" => false, "message" => "Error al verificar existencia del pago."]);
        exit;
    }

    $existe = pg_fetch_result($result_verificar, 0, 0);
    if ($existe > 0) {
        echo json_encode(["success" => false, "message" => "Este pago ya fue registrado previamente."]);
        exit;
    }

    // Iniciar una transacción
    pg_query($conn, "BEGIN");

    try {
        // Insertar en pagos_ejecutados
        $query_pago = "INSERT INTO pagos_ejecutados (id_orden_pago, id_cuenta_bancaria, monto, referencia_bancaria, id_usuario)
                       VALUES ($1, $2, $3, $4, $5) RETURNING id_pago";
        $result_pago = pg_query_params($conn, $query_pago, [$id_orden_pago, $id_cuenta_bancaria, $monto, $referencia_bancaria, $id_usuario]);

        if (!$result_pago) {
            throw new Exception("Error al registrar el pago.");
        }

        $id_pago = pg_fetch_result($result_pago, 0, "id_pago");
        $id_cheque = null; // Inicializamos el ID del cheque como null

        // Si el método de pago es "Cheque", insertamos en cheques
        if ($metodo_pago === "Cheque" && $nombre_beneficiario) {
            $query_cheque = "INSERT INTO cheques (numero_cheque, beneficiario, monto_cheque, fecha_cheque)
                             VALUES ($1, $2, $3, NOW()) RETURNING id";
            $result_cheque = pg_query_params($conn, $query_cheque, [$referencia_bancaria, $nombre_beneficiario, $monto]);

            if (!$result_cheque) {
                throw new Exception("Error al registrar el cheque.");
            }

            $id_cheque = pg_fetch_result($result_cheque, 0, "id"); // Obtenemos el ID del cheque insertado
        }

        // 🔥 **Actualizar el estado de la orden de pago a "Pagado"**
        $query_actualizar_estado = "UPDATE ordenes_pago SET estado = 'Pagado' WHERE id_orden_pago = $1";
        $result_actualizar_estado = pg_query_params($conn, $query_actualizar_estado, [$id_orden_pago]);

        if (!$result_actualizar_estado) {
            throw new Exception("Error al actualizar el estado de la orden de pago.");
        }

        // Confirmamos la transacción
        pg_query($conn, "COMMIT");

        // Retornamos el ID del pago y, si se registró, el ID del cheque
        echo json_encode([
            "success" => true,
            "message" => "Pago registrado correctamente y estado actualizado a 'Pagado'.",
            "id_pago" => $id_pago,
            "id_cheque" => $id_cheque // Esto será null si no se registró un cheque
        ]);
    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
}
?>
