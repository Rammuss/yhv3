<?php
// Configuración de la base de datos
include "../../conexion/configv2.php";

// Leer los datos JSON (por ejemplo, desde un POST)
$data = json_decode(file_get_contents('php://input'), true);

// Extraer los datos del JSON
$numero_cheque = $data['numero_cheque'];
$beneficiario = $data['beneficiario'];
$monto_cheque = $data['monto_cheque'];
$fecha_cheque = $data['fecha_cheque'];
$facturas = $data['facturas'];

// Calcular el monto total (sumar los totales de las facturas)
$monto_total = 0;
foreach ($facturas as $factura) {
    $monto_total += (float)$factura['total']; // Sumar los totales de las facturas
}

// Obtener el id del proveedor (asumimos que el id del proveedor es el mismo para todas las facturas)
$id_proveedor = $facturas[0]['idproveedor'];
$estado_orden = 'Pagado'; // Estado predeterminado como 'Pagado'
$fecha_orden = date('Y-m-d'); // Fecha actual

// Iniciar transacción
pg_query($conn, "BEGIN");

try {
    // 1. Verificar si ya existe una orden de pago con el mismo número de cheque
    $query_check_orden_pago = "SELECT 1 FROM public.ordenes_pago WHERE id_cheque = (SELECT id FROM public.cheques WHERE numero_cheque = '$numero_cheque' LIMIT 1)";
    $result_check_orden_pago = pg_query($conn, $query_check_orden_pago);
    
    if (pg_num_rows($result_check_orden_pago) > 0) {
        throw new Exception("Ya existe una orden de pago con el mismo número de cheque.");
    }

    // 2. Insertar en la tabla 'cheques'
    $query_cheque = "INSERT INTO public.cheques (numero_cheque, beneficiario, monto_cheque, fecha_cheque) 
                     VALUES ('$numero_cheque', '$beneficiario', $monto_cheque, '$fecha_cheque') RETURNING id";
    $result_cheque = pg_query($conn, $query_cheque);
    
    if (!$result_cheque) {
        throw new Exception("Error al insertar en la tabla cheques");
    }
    
    // Obtener el ID del cheque insertado
    $row_cheque = pg_fetch_assoc($result_cheque);
    $id_cheque = $row_cheque['id'];

    // 3. Insertar en la tabla 'ordenes_pago' con el monto total y asociando el id_factura
    foreach ($facturas as $factura) {
        $query_orden_pago = "INSERT INTO public.ordenes_pago (id_cheque, id_proveedor, monto_total, estado, fecha_orden, id_factura) 
                             VALUES ($id_cheque, $id_proveedor, {$factura['total']}, '$estado_orden', '$fecha_orden', {$factura['id']})";
        $result_orden_pago = pg_query($conn, $query_orden_pago);
        
        if (!$result_orden_pago) {
            throw new Exception("Error al insertar en la tabla ordenes_pago");
        }
    }

    // 4. Actualizar el estado de las facturas a 'Pagado' y asociarlas con la orden de pago
    foreach ($facturas as $factura) {
        $query_update_factura = "UPDATE public.facturas_cabecera_t 
                                 SET estado_pago = 'Pagado' 
                                 WHERE id_factura = {$factura['id']}";
        $result_factura = pg_query($conn, $query_update_factura);
        
        if (!$result_factura) {
            throw new Exception("Error al actualizar la factura con el estado 'Pagado'");
        }
    }

    // Confirmar transacción
    pg_query($conn, "COMMIT");

    // Respuesta exitosa
    echo json_encode(["message" => "Orden de pago registrada correctamente"]);

} catch (Exception $e) {
    // Rollback en caso de error
    pg_query($conn, "ROLLBACK");
    echo json_encode(["error" => "Error al registrar la orden de pago: " . $e->getMessage()]);
}

// Cerrar la conexión
pg_close($conn);
?>
