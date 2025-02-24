<?php
include "../../conexion/configv2.php";

// Obtener los datos del POST
$data = json_decode(file_get_contents('php://input'), true);

// Extraer los valores del cheque entregado
$id_cheque = $data['id_cheque'];
$fecha_entrega = $data['fecha_entrega'];
$recibido_por = $data['recibido_por'];
$observaciones = isset($data['observaciones']) ? $data['observaciones'] : null;
$ci = $data['ci'];

// Actualizar la tabla cheques con la entrega registrada
$query = "UPDATE public.cheques
          SET estado = 'Entregado',
              fecha_entrega = $1,
              recibido_por = $2,
              observaciones = $3,
              ci = $4
          WHERE id = $5";

$result = pg_query_params($conn, $query, array($fecha_entrega, $recibido_por, $observaciones, $ci, $id_cheque));

if ($result) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo registrar la entrega.']);
}

pg_close($conn);
?>
