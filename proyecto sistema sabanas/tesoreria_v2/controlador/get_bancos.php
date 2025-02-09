<?php
header('Content-Type: application/json');
require '../../conexion/config_pdo.php'; // Archivo de conexión a la BD

try {
    $sql = "SELECT id_cuenta_bancaria AS id, nombre_banco AS nombre FROM cuentas_bancarias";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $bancos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($bancos);
} catch (Exception $e) {
    echo json_encode([]);
}
