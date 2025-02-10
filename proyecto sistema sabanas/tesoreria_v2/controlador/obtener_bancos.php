<?php
header('Content-Type: application/json');

require "../../conexion/config_pdo.php";

try {
    

    $stmt = $pdo->query("SELECT * FROM cuentas_bancarias ORDER BY nombre_banco ASC");
    $cuentas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($cuentas);
} catch (PDOException $e) {
    echo json_encode(["error" => "Error al obtener cuentas bancarias: " . $e->getMessage()]);
}
?>
