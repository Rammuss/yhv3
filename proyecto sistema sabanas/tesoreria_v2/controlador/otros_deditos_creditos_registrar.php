<?php
header('Content-Type: application/json');
require '../../conexion/config_pdo.php';

// Recibir los datos enviados en formato JSON
$data = json_decode(file_get_contents("php://input"), true);

// Validar que se recibieron los campos obligatorios
$required_fields = ['fecha', 'descripcion', 'monto', 'tipo_credito_debito', 'tipo_movimiento', 'banco_id'];
foreach ($required_fields as $field) {
    if (!isset($data[$field]) || trim($data[$field]) === "") {
        echo json_encode(['exito' => false, 'mensaje' => "Datos incompletos: falta el campo $field"]);
        exit;
    }
}

// Asignar y sanitizar los datos
$fecha = $data['fecha']; // Formato YYYY-MM-DD
$descripcion = trim($data['descripcion']);
$monto = floatval($data['monto']);
$tipo_credito_debito = strtoupper($data['tipo_credito_debito']); // "DÉBITO" o "CRÉDITO"
$tipo_movimiento = strtoupper($data['tipo_movimiento']); // "INTERNAL" o "BANK"
$banco_id = intval($data['banco_id']);
$referencia = isset($data['referencia']) ? trim($data['referencia']) : null;
$usuario_registro = "sistema";

// Validar valores permitidos


if ($monto <= 0) {
    echo json_encode(['exito' => false, 'mensaje' => 'El monto debe ser mayor a cero']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Insertar en otros_debitos_creditos
    $stmt = $pdo->prepare("INSERT INTO otros_debitos_creditos (id_cuenta_bancaria, tipo_movimiento, monto, descripcion, fecha_movimiento, referencia, usuario_registro, origen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$banco_id, $tipo_credito_debito, $monto, $descripcion, $fecha, $referencia, $usuario_registro, $tipo_movimiento]);

    // Si es movimiento bancario, insertar en transacciones_bancarias
    if ($tipo_movimiento === "BANK") {
        $stmt_tb = $pdo->prepare("INSERT INTO transacciones_bancarias (fecha_transaccion, fecha_registro, descripcion, monto, tipo, referencia_interna, referencia_bancaria, cuenta_bancaria_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt_tb->execute([$fecha, $fecha, $descripcion, $monto, $tipo_credito_debito, $referencia, $referencia, $banco_id]);
    }

    $pdo->commit();
    echo json_encode(['exito' => true, 'mensaje' => 'Movimiento registrado correctamente']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['exito' => false, 'mensaje' => 'Error al registrar el movimiento: ' . $e->getMessage()]);
}
?>
