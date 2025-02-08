<?php
// Datos de conexión a la base de datos
include "../../conexion/configv2.php";

// Obtener el id_factura de la URL
if (!isset($_GET['id_factura'])) {
    die(json_encode(["message" => "Error: id_factura no proporcionado."]));
}

$id_factura = $_GET['id_factura'];

try {
    // 1. Verificar si ya existe una provisión para esta factura
    $query_check_provision = "
        SELECT COUNT(*) AS total
        FROM provisiones_cuentas_pagar
        WHERE id_factura = $1;
    ";

    $result_check_provision = pg_query_params($conn, $query_check_provision, array($id_factura));
    if (!$result_check_provision) {
        die(json_encode(["message" => "Error al verificar la provisión existente: " . pg_last_error()]));
    }

    $row_check = pg_fetch_assoc($result_check_provision);
    if ($row_check['total'] > 0) {
        die(json_encode(["message" => "Ya existe una provisión generada para esta factura."]));
    }

    // 2. Obtener los datos de la factura
    $query_factura = "
        SELECT id_factura, id_proveedor, total
        FROM facturas_cabecera_t
        WHERE id_factura = $1 AND estado_pago = 'Pendiente';
    ";
    $result_factura = pg_query_params($conn, $query_factura, array($id_factura));

    if (pg_num_rows($result_factura) === 0) {
        die(json_encode(["message" => "Factura no encontrada o ya no está pendiente."]));
    }

    $factura = pg_fetch_assoc($result_factura);

    // 3. Insertar la provisión en la tabla provisiones_cuentas_pagar
    $query_provision = "
        INSERT INTO provisiones_cuentas_pagar (
            id_factura, id_proveedor, monto_provisionado, estado_provision, id_usuario_creacion, tipo_provision
        )
        VALUES ($1, $2, $3, $4, $5, 'Factura Proveedor')
        RETURNING *;
    ";

    $monto_provisionado = $factura['total'];
    $estado_provision = 'pendiente';
    $id_usuario_creacion = 1; // Aquí puedes obtener el ID del usuario desde la sesión

    $params_provision = array(
        $factura['id_factura'],
        $factura['id_proveedor'],
        $monto_provisionado,
        $estado_provision,
        $id_usuario_creacion,
    );

    $result_provision = pg_query_params($conn, $query_provision, $params_provision);

    if (!$result_provision) {
        die(json_encode(["message" => "Error al generar la provisión: " . pg_last_error()]));
    }

    $provision = pg_fetch_assoc($result_provision);

    // 4. Actualizar el campo provision_generada en facturas_cabecera_t
    $query_update_factura = "
        UPDATE facturas_cabecera_t
        SET provision_generada = TRUE
        WHERE id_factura = $1;
    ";
    $result_update_factura = pg_query_params($conn, $query_update_factura, array($id_factura));

    if (!$result_update_factura) {
        die(json_encode(["message" => "Error al actualizar el estado de la factura: " . pg_last_error()]));
    }

    // 5. Responder con la provisión generada
    echo json_encode([
        "message" => "Provisión generada exitosamente.",
        "provision" => $provision,
    ]);
} catch (Exception $e) {
    die(json_encode(["message" => "Error interno del servidor: " . $e->getMessage()]));
}

// Cerrar la conexión
pg_close($conn);
?>
