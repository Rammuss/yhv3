<?php
include "../../conexion/configv2.php"; // Asegúrate de que la ruta es correcta

// Verificar si se recibió un ID de pago válido
if (!isset($_GET['id_pago']) || empty($_GET['id_pago'])) {
    die("<script>alert('ID de pago no proporcionado'); window.history.back();</script>");
}

$id_pago = intval($_GET['id_pago']); // Convertir a entero para mayor seguridad

// Paso 1: Obtener la referencia bancaria del pago
$query_pago = "
    SELECT referencia_bancaria 
    FROM pagos_ejecutados 
    WHERE id_pago = $id_pago
    LIMIT 1";

$result_pago = pg_query($conn, $query_pago);

if (!$result_pago || pg_num_rows($result_pago) == 0) {
    die("<script>alert('No se encontró el pago con el ID proporcionado'); window.history.back();</script>");
}

$row_pago = pg_fetch_assoc($result_pago);
$referencia_bancaria = $row_pago['referencia_bancaria'];

// Paso 2: Buscar el cheque usando la referencia bancaria como número de cheque
$query_cheque = "
    SELECT id, numero_cheque, beneficiario, monto_cheque, fecha_cheque, fecha_entrega, recibido_por, observaciones
    FROM cheques 
    WHERE numero_cheque = '$referencia_bancaria'
    LIMIT 1";

$result_cheque = pg_query($conn, $query_cheque);

if (!$result_cheque || pg_num_rows($result_cheque) == 0) {
    die("<script>alert('No se encontró un cheque con la referencia bancaria proporcionada'); window.history.back();</script>");
}

$row_cheque = pg_fetch_assoc($result_cheque);

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Cheque</title>
    <link rel="stylesheet" href="../../styles.css"> <!-- Agrega tu archivo CSS aquí -->
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .cheque-container {
            border: 2px solid #333;
            padding: 20px;
            max-width: 500px;
            margin: auto;
            text-align: left;
            background: #f9f9f9;
            border-radius: 10px;
        }
        .cheque-container h2 {
            text-align: center;
            margin-bottom: 15px;
        }
        .cheque-container p {
            margin: 5px 0;
            font-size: 18px;
        }
        .buttons {
            margin-top: 20px;
            text-align: center;
        }
        button {
            padding: 10px 15px;
            font-size: 16px;
            margin: 5px;
            cursor: pointer;
            border: none;
            border-radius: 5px;
        }
        .print-btn {
            background: green;
            color: white;
        }
        .back-btn {
            background: red;
            color: white;
        }
    </style>
</head>
<body>

    <div class="cheque-container">
        <h2>Detalle del Cheque</h2>
        <p><strong>Número de Cheque:</strong> <?php echo htmlspecialchars($row_cheque['numero_cheque']); ?></p>
        <p><strong>Beneficiario:</strong> <?php echo htmlspecialchars($row_cheque['beneficiario']); ?></p>
        <p><strong>Monto:</strong> <?php echo number_format($row_cheque['monto_cheque'], 2); ?> Gs</p>
        <p><strong>Fecha de Emisión:</strong> <?php echo htmlspecialchars($row_cheque['fecha_cheque']); ?></p>

        <?php if (!empty($row_cheque['fecha_entrega'])) { ?>
            <p><strong>Fecha de Entrega:</strong> <?php echo htmlspecialchars($row_cheque['fecha_entrega']); ?></p>
        <?php } ?>
        
        <?php if (!empty($row_cheque['recibido_por'])) { ?>
            <p><strong>Recibido por:</strong> <?php echo htmlspecialchars($row_cheque['recibido_por']); ?></p>
        <?php } ?>
        
        <?php if (!empty($row_cheque['observaciones'])) { ?>
            <p><strong>Observaciones:</strong> <?php echo nl2br(htmlspecialchars($row_cheque['observaciones'])); ?></p>
        <?php } ?>

        <div class="buttons">
            <button class="print-btn" onclick="window.print()">Imprimir</button>
            <button class="back-btn" onclick="window.history.back()">Volver</button>
        </div>
    </div>

</body>
</html>
