<?php
include '../../conexion/configv2.php';

$query = "SELECT id_cuenta_bancaria, nombre_banco, numero_cuenta FROM cuentas_bancarias";
$result = pg_query($conn, $query);
$cuentas = pg_fetch_all($result) ?: [];

header('Content-Type: application/json');
echo json_encode($cuentas);
?>