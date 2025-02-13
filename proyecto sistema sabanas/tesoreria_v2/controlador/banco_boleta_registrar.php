<?php
// procesar_deposito.php

// Incluir la configuración y conexión a la base de datos
require "../../conexion/config_pdo.php";

// Recoger y sanitizar los datos enviados vía POST
$id_cuenta_bancaria = isset($_POST['cuenta_bancaria']) ? trim($_POST['cuenta_bancaria']) : '';
$numero_boleta     = isset($_POST['numero_boleta']) ? trim($_POST['numero_boleta']) : '';
$fecha             = isset($_POST['fecha']) ? trim($_POST['fecha']) : '';
$monto             = isset($_POST['monto']) ? trim($_POST['monto']) : '';
$concepto          = isset($_POST['concepto']) ? trim($_POST['concepto']) : '';
$estado            = isset($_POST['estado']) ? trim($_POST['estado']) : 'Confirmado';

// Validar campos requeridos
if (empty($id_cuenta_bancaria) || empty($numero_boleta) || empty($fecha) || empty($monto)) {
    http_response_code(400);
    echo "Faltan campos requeridos.";
    exit;
}

try {
    // Iniciar la transacción
    $pdo->beginTransaction();

    // 1. Insertar en boletas_deposito
    $sqlDeposit = "INSERT INTO boletas_deposito (
                        id_cuenta_bancaria, 
                        numero_boleta, 
                        fecha, 
                        monto, 
                        concepto, 
                        estado
                   ) VALUES (
                        :id_cuenta_bancaria, 
                        :numero_boleta, 
                        :fecha, 
                        :monto, 
                        :concepto, 
                        :estado
                   )";
    $stmtDeposit = $pdo->prepare($sqlDeposit);
    $stmtDeposit->execute([
        ':id_cuenta_bancaria' => $id_cuenta_bancaria,
        ':numero_boleta'      => $numero_boleta,
        ':fecha'              => $fecha,
        ':monto'              => $monto,
        ':concepto'           => $concepto,
        ':estado'             => $estado
    ]);

    // 2. Insertar en transacciones_bancarias
    $sqlTrans = "INSERT INTO transacciones_bancarias (
                        fecha_transaccion,
                        fecha_registro,
                        descripcion,
                        monto,
                        tipo,
                        referencia_interna,
                        referencia_bancaria,
                        cuenta_bancaria_id,
                        estado
                   ) VALUES (
                        :fecha_transaccion,
                        :fecha_registro,
                        :descripcion,
                        :monto,
                        :tipo,
                        :referencia_interna,
                        :referencia_bancaria,
                        :cuenta_bancaria_id,
                        :estado
                   )";
    $stmtTrans = $pdo->prepare($sqlTrans);

    // Establecer los valores
    $fecha_transaccion   = $fecha; // Fecha del depósito
    $fecha_registro      = date('Y-m-d'); // Fecha actual
    $descripcion         = "Depósito registrado: " . $numero_boleta;
    $tipo                = "CREDITO"; // o "DEP", según prefieras
    $referencia_interna  = $numero_boleta;
    $referencia_bancaria = $numero_boleta;
    $estadoTrans         = "PEDNIENTE"; // Puede ser 'PENDIENTE' o 'CONFIRMADO'

    $stmtTrans->execute([
        ':fecha_transaccion'   => $fecha_transaccion,
        ':fecha_registro'      => $fecha_registro,
        ':descripcion'         => $descripcion,
        ':monto'               => $monto,
        ':tipo'                => $tipo,
        ':referencia_interna'  => $referencia_interna,
        ':referencia_bancaria' => $referencia_bancaria,
        ':cuenta_bancaria_id'  => $id_cuenta_bancaria,
        ':estado'              => $estadoTrans
    ]);

    // 3. ACTUALIZAR SALDO EN cuentas_bancarias
    $sqlUpdateSaldo = "UPDATE cuentas_bancarias 
                       SET saldo_disponible = saldo_disponible + :monto 
                       WHERE id_cuenta_bancaria = :id_cuenta_bancaria";
    $stmtSaldo = $pdo->prepare($sqlUpdateSaldo);
    $stmtSaldo->execute([
        ':monto' => $monto,
        ':id_cuenta_bancaria' => $id_cuenta_bancaria
    ]);

    // Confirmar la transacción si todo fue exitoso
    $pdo->commit();

    echo "Depósito registrado y saldo actualizado exitosamente.";
} catch (PDOException $e) {
    // Revertir la transacción en caso de error
    $pdo->rollBack();
    http_response_code(500);
    echo "Error al registrar el depósito: " . $e->getMessage();
}
?>
