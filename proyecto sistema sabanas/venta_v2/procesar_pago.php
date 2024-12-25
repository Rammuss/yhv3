<?php
include '../conexion/configv2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cuota_id = $_POST['cuota_id'];
    $monto = $_POST['monto'];
    $ruc_ci = $_POST['ruc_ci_hidden']; // Recibir el RUC desde el formulario

    // Validación de datos
    if (!ctype_digit($cuota_id) || !is_numeric($monto)) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }

    // Iniciar transacción
    pg_query($conn, "BEGIN");

    // Obtener el monto total de la cuota
    $query_monto = "SELECT monto FROM cuentas_por_cobrar WHERE id = $1";
    $result_monto = pg_query_params($conn, $query_monto, array($cuota_id));

    if (!$result_monto) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'No se pudo obtener el monto de la cuota']);
        exit;
    }

    $cuota = pg_fetch_assoc($result_monto);
    $monto_total = $cuota['monto'];

    // Calcular el total pagado hasta ahora
    $query_total_pagado = "
        SELECT COALESCE(SUM(monto_pago), 0) AS total_pagado
        FROM pagos
        WHERE cuenta_id = $1;
    ";
    $result_total_pagado = pg_query_params($conn, $query_total_pagado, array($cuota_id));

    if (!$result_total_pagado) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'No se pudo obtener el total pagado']);
        exit;
    }

    $total_pagado = pg_fetch_result($result_total_pagado, 0, 'total_pagado');

    // Determinar el nuevo estado de la cuota
    $saldo_restante = $monto_total - ($total_pagado + $monto);
    if ($saldo_restante <= 0) {
        $nuevo_estado = 'pagado'; // Pago completo
    } else {
        $nuevo_estado = 'pendiente'; // Aún queda saldo por pagar
    }

    // Registrar el pago en la tabla de pagos
    $query_registro = "
        INSERT INTO pagos (cuenta_id, monto_pago, fecha_pago, forma_pago, estado_pago)
        VALUES ($1, $2, NOW(), 'Efectivo', 'Pagado')
        RETURNING id;
    ";
    $result_registro = pg_query_params($conn, $query_registro, array($cuota_id, $monto));

    if (!$result_registro) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'Error al registrar el pago']);
        exit;
    }

    $pago_id = pg_fetch_result($result_registro, 0, 'id');

    // Actualizar el estado de la cuota en la tabla cuentas_por_cobrar
    $query_actualizar_estado = "
        UPDATE cuentas_por_cobrar
        SET estado = $1, fecha_pago = NOW()
        WHERE id = $2;
    ";
    $result_actualizar_estado = pg_query_params($conn, $query_actualizar_estado, array($nuevo_estado, $cuota_id));

    if (!$result_actualizar_estado) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(['success' => false, 'error' => 'Error al actualizar el estado de la cuota']);
        exit;
    }

    // Confirmar transacción
    pg_query($conn, "COMMIT");

    // Responder con la URL de redirección
    

    echo json_encode([
        'success' => true,
        'message' => 'Pago procesado correctamente.',
        'redirect' => 'comprobante_pago.php?pago_id=' . $pago_id  // La URL de redirección
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No se pudo procesar el pago.'
    ]);


    exit;
}

pg_close($conn);
