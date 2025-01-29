<?php
// Conectar a la base de datos
include "../../conexion/configv2.php";
// Verificar si se recibió el ID del cheque
if (!isset($_GET['id_cheque'])) {
    die("Error: No se proporcionó un ID de cheque.");
}

$id_cheque = intval($_GET['id_cheque']); // Convertir a número entero

// Consultar el cheque en la base de datos
$sql = "SELECT c.numero_cheque, c.beneficiario, c.monto_cheque, c.fecha_cheque 
        FROM cheques c
        WHERE c.id = $id_cheque";

$result = pg_query($conn, $sql);

if (!$result || pg_num_rows($result) == 0) {
    die("Error: Cheque no encontrado.");
}

$cheque = pg_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Cheque</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <style>
        .cheque-container {
            width: 80%;
            margin: auto;
            border: 2px solid black;
            padding: 20px;
            text-align: center;
            font-size: 18px;
        }
        .cheque-title {
            font-weight: bold;
            font-size: 24px;
            margin-bottom: 15px;
        }
        .cheque-details {
            text-align: left;
            margin-top: 15px;
        }
        .button-container {
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="cheque-container">
        <div class="cheque-title">Cheque N° <?php echo htmlspecialchars($cheque['numero_cheque']); ?></div>
        <div class="cheque-details">
            <p><strong>Beneficiario:</strong> <?php echo htmlspecialchars($cheque['beneficiario']); ?></p>
            <p><strong>Monto:</strong> $<?php echo number_format($cheque['monto_cheque'], 2); ?></p>
            <p><strong>Fecha:</strong> <?php echo htmlspecialchars($cheque['fecha_cheque']); ?></p>
        </div>
        <div class="button-container">
            <button class="button is-success" onclick="window.print()">🖨️ Imprimir</button>
            <button class="button is-danger" onclick="window.history.back()">🔙 Atrás</button>
        </div>
    </div>
</body>
</html>
