<?php
include "../../conexion/configv2.php";

if (!isset($_GET["id_cheque"])) {
    die("ID del cheque no proporcionado.");
}

$id_cheque = $_GET["id_cheque"];

// Obtener datos del cheque desde la base de datos
$query = "SELECT numero_cheque, beneficiario, monto_cheque, fecha_cheque 
          FROM cheques WHERE id = $1";
$result = pg_query_params($conn, $query, [$id_cheque]);

if (!$result || pg_num_rows($result) == 0) {
    die("Cheque no encontrado.");
}

$cheque = pg_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cheque</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .cheque-container {
            width: 600px;
            margin: auto;
            border: 2px solid black;
            padding: 20px;
            background: #f9f9f9;
        }
        .cheque-header {
            font-size: 18px;
            font-weight: bold;
        }
        .cheque-info {
            margin-top: 20px;
            text-align: left;
            font-size: 16px;
        }
        .cheque-footer {
            margin-top: 30px;
            font-style: italic;
        }
        .buttons {
            margin-top: 20px;
        }
        button {
            padding: 10px 20px;
            font-size: 16px;
            margin: 5px;
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="cheque-container">
        <div class="cheque-header">
            🏦 CHEQUE BANCARIO
        </div>
        <div class="cheque-info">
            <p><strong>Número de Cheque:</strong> <?php echo htmlspecialchars($cheque["numero_cheque"]); ?></p>
            <p><strong>Beneficiario:</strong> <?php echo htmlspecialchars($cheque["beneficiario"]); ?></p>
            <p><strong>Monto:</strong> $<?php echo number_format($cheque["monto_cheque"], 2); ?></p>
            <p><strong>Fecha:</strong> <?php echo date("d/m/Y", strtotime($cheque["fecha_cheque"])); ?></p>
        </div>
        <div class="cheque-footer">
            Firma del Responsable: _______________________
        </div>
    </div>

    <div class="buttons">
        <button onclick="window.print()">🖨️ Imprimir</button>
        <button onclick="window.history.back()">🔙 Volver</button>
    </div>

</body>
</html>
