<?php
include '../../conexion/configv2.php';

$id = $_GET['id'];
$query = "
    SELECT 
        p.id_provision,
        p.monto_provisionado,
        p.tipo_provision,
        pr.nombre
    FROM provisiones_cuentas_pagar p
    JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
    WHERE p.id_provision = $id
";

$result = pg_query($conn, $query);
$provision = pg_fetch_assoc($result);

header('Content-Type: application/json');
echo json_encode($provision);
?>