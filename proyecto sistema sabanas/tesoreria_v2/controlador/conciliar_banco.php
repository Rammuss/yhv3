<?php
require '../../conexion/config_pdo.php';

header('Content-Type: application/json');

$fecha_inicio = $_POST['fecha_inicio'];
$fecha_fin = $_POST['fecha_fin'];

try {
    // Obtener transacciones del extracto bancario
    $stmt = $pdo->prepare("SELECT * FROM extracto_bancario WHERE fecha_transaccion BETWEEN :fecha_inicio AND :fecha_fin");
    $stmt->execute(['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin]);
    $transacciones_extracto = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener transacciones bancarias internas
    $stmt = $pdo->prepare("SELECT * FROM transacciones_bancarias WHERE fecha_transaccion BETWEEN :fecha_inicio AND :fecha_fin");
    $stmt->execute(['fecha_inicio' => $fecha_inicio, 'fecha_fin' => $fecha_fin]);
    $transacciones_internas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Comparar transacciones
    $transacciones_conciliadas = [];
    $transacciones_no_conciliadas = [];

    foreach ($transacciones_extracto as $transaccion_banco) {
        $conciliada = false;
        foreach ($transacciones_internas as $transaccion_interna) {
            if ($transaccion_banco['monto'] == $transaccion_interna['monto'] && $transaccion_banco['fecha_transaccion'] == $transaccion_interna['fecha_transaccion']) {
                $transacciones_conciliadas[] = $transaccion_banco;
                $conciliada = true;
                break;
            }
        }
        if (!$conciliada) {
            $transacciones_no_conciliadas[] = $transaccion_banco;
        }
    }

    echo json_encode([
        'exito' => true,
        'transacciones_conciliadas' => $transacciones_conciliadas,
        'transacciones_no_conciliadas' => $transacciones_no_conciliadas
    ]);
} catch (PDOException $e) {
    echo json_encode(['exito' => false, 'mensaje' => 'Error al obtener transacciones: ' . $e->getMessage()]);
}
?>
