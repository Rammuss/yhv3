<?php
include "../../conexion/configv2.php";

$query = "
    SELECT 
        p.id_provision, 
        p.id_proveedor, 
        p.monto_provisionado, 
        pr.nombre AS nombre_proveedor  -- Usa un alias claro
    FROM provisiones_cuentas_pagar p
    JOIN proveedores pr ON p.id_proveedor = pr.id_proveedor
    WHERE p.estado_provision = 'pendiente'
";

$result = pg_query($conn, $query);
$provisiones = pg_fetch_all($result) ?: [];

header('Content-Type: application/json');
echo json_encode($provisiones);
?>