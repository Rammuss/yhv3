<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

// Verificamos si la solicitud es POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $inputJSON = file_get_contents("php://input");
    $data     = json_decode($inputJSON, true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "Datos inválidos"]);
        exit;
    }

    // Capturamos los datos del cuerpo de la solicitud
    $id_orden_pago       = $data["id_orden_pago"];
    $id_cuenta_bancaria  = $data["id_cuenta_bancaria"];
    $monto               = $data["monto"];
    $referencia_bancaria = $data["referencia_bancaria"];
    $metodo_pago         = $data["metodo_pago"]; // "Cheque", "Transferencia" o "Efectivo"
    $nombre_beneficiario = isset($data["nombre_beneficiario"]) ? $data["nombre_beneficiario"] : null;
    $id_usuario          = 1; // Por ejemplo, se obtiene de la sesión

    // VALIDAR SI EL id_orden_pago YA EXISTE EN pagos_ejecutados
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
        $result_pago = pg_query_params($conn, $query_pago, [
            $id_orden_pago, $id_cuenta_bancaria, $monto, $referencia_bancaria, $id_usuario
        ]);

        if (!$result_pago) {
            throw new Exception("Error al registrar el pago.");
        }

        $id_pago = pg_fetch_result($result_pago, 0, "id_pago");
        $id_cheque = null; // Inicializamos el ID del cheque como null

        // Si el método de pago es "Cheque", insertar en cheques
        if ($metodo_pago === "Cheque" && $nombre_beneficiario) {
            $query_cheque = "INSERT INTO cheques (numero_cheque, beneficiario, monto_cheque, fecha_cheque)
                             VALUES ($1, $2, $3, NOW()) RETURNING id";
            $result_cheque = pg_query_params($conn, $query_cheque, [
                $referencia_bancaria, $nombre_beneficiario, $monto
            ]);

            if (!$result_cheque) {
                throw new Exception("Error al registrar el cheque.");
            }

            $id_cheque = pg_fetch_result($result_cheque, 0, "id");
        }

        // Actualizar el estado de la orden de pago a "Pagado"
        $query_actualizar_estado = "UPDATE ordenes_pago SET estado = 'Pagado' WHERE id_orden_pago = $1";
        $result_actualizar_estado = pg_query_params($conn, $query_actualizar_estado, [$id_orden_pago]);

        if (!$result_actualizar_estado) {
            throw new Exception("Error al actualizar el estado de la orden de pago.");
        }

        // Según el método de pago:
        if ($metodo_pago === "Transferencia") {
            // Registrar en transacciones_bancarias (movimiento de salida - DEBITO)
            $descripcion = "Pago de orden $id_orden_pago";
            $referencia_interna = $id_orden_pago; // O usar $id_pago si conviene
            $query_transaccion = "INSERT INTO transacciones_bancarias 
                (fecha_transaccion, fecha_registro, descripcion, monto, tipo, referencia_interna, referencia_bancaria, cuenta_bancaria_id, created_at)
                VALUES (CURRENT_DATE, CURRENT_DATE, $1, $2, 'DEBITO', $3, $4, $5, CURRENT_TIMESTAMP)";
            $result_transaccion = pg_query_params($conn, $query_transaccion, [
                $descripcion, $monto, $referencia_interna, $referencia_bancaria, $id_cuenta_bancaria
            ]);

            if (!$result_transaccion) {
                throw new Exception("Error al registrar la transacción bancaria para transferencia.");
            }
        } elseif ($metodo_pago === "Efectivo") {
            // Para efectivo, no se registra en transacciones bancarias,
            // se actualiza directamente el saldo de la cuenta (por ejemplo, de Caja).
            $query_actualizar_saldo = "UPDATE cuentas_bancarias 
                SET saldo_disponible = saldo_disponible - $1 
                WHERE id_cuenta_bancaria = $2";
            $result_actualizar_saldo = pg_query_params($conn, $query_actualizar_saldo, [
                $monto, $id_cuenta_bancaria
            ]);

            if (!$result_actualizar_saldo) {
                throw new Exception("Error al actualizar el saldo de la cuenta bancaria para efectivo.");
            }
        } else {
            // Para otros métodos (por ejemplo, Cheque ya se procesó en transacciones bancarias)
            // O si deseas registrar movimientos para otros métodos, inclúyelo aquí.
            // En este ejemplo, si es Cheque, ya se registró la transacción bancaria en el bloque "else" a continuación.
            $descripcion = "Pago de orden $id_orden_pago";
            $referencia_interna = $id_orden_pago;
            $query_transaccion = "INSERT INTO transacciones_bancarias 
                (fecha_transaccion, fecha_registro, descripcion, monto, tipo, referencia_interna, referencia_bancaria, cuenta_bancaria_id, created_at)
                VALUES (CURRENT_DATE, CURRENT_DATE, $1, $2, 'DEBITO', $3, $4, $5, CURRENT_TIMESTAMP)";
            $result_transaccion = pg_query_params($conn, $query_transaccion, [
                $descripcion, $monto, $referencia_interna, $referencia_bancaria, $id_cuenta_bancaria
            ]);

            if (!$result_transaccion) {
                throw new Exception("Error al registrar la transacción bancaria para otros métodos.");
            }
        }

        // Confirmar la transacción
        pg_query($conn, "COMMIT");

        // Preparar la respuesta: para Transferencia y Efectivo, se indica que se debe preguntar por imprimir comprobante
        $respuesta = [
            "success"   => true,
            "message"   => "Pago registrado correctamente, estado actualizado a 'Pagado' y movimiento bancario procesado.",
            "id_pago"   => $id_pago,
            "id_cheque" => $id_cheque // Será null si no se registró un cheque
        ];

        if ($metodo_pago === "Transferencia" || $metodo_pago === "Efectivo") {
            $respuesta["imprimir_comprobante"] = true;
        }

        echo json_encode($respuesta);

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Método no permitido"]);
}
?>
