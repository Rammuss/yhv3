<?php
include "../../conexion/configv2.php";
// Consultar los cheques pendientes
$query = "SELECT id, numero_cheque, beneficiario, monto_cheque, fecha_cheque, estado, fecha_entrega, recibido_por, observaciones
          FROM public.cheques
          WHERE estado = 'Pendiente'";

$result = pg_query($conn, $query);

// Verificar si la consulta fue exitosa
if ($result) {
    $cheques = pg_fetch_all($result);
    echo json_encode($cheques);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No se pudo obtener los cheques.']);
}

pg_close($conn);
?>
