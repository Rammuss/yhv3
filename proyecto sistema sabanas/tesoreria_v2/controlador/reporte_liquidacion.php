<?php
require "../../conexion/config_pdo.php";

if (!isset($_GET['fecha_desde'], $_GET['fecha_hasta'], $_GET['id_procesadora'])) {
    http_response_code(400);
    echo "Faltan datos requeridos.";
    exit;
}

$fecha_desde = $_GET['fecha_desde'];
$fecha_hasta = $_GET['fecha_hasta'];
$id_procesadora = $_GET['id_procesadora'];

try {
    // Consulta para obtener el resumen
    $sqlResumen = "SELECT p.nombre AS procesadora, 
                          SUM(monto_neto) AS total_monto_neto, 
                          SUM(monto) AS total_monto, 
                          SUM(comision) AS total_comision, 
                          COUNT(*) AS total_transacciones
                   FROM reporte_tarjetas_detalle r
                   JOIN reportes_tarjetas rt ON r.id_reporte = rt.id_reporte
                   JOIN procesadoras p ON rt.id_procesadora = p.id
                   WHERE rt.fecha_reporte BETWEEN :fecha_desde AND :fecha_hasta
                   AND p.id = :id_procesadora
                   GROUP BY p.nombre";

    $stmtResumen = $pdo->prepare($sqlResumen);
    $stmtResumen->execute([
        ':id_procesadora' => $id_procesadora,
        ':fecha_desde' => $fecha_desde,
        ':fecha_hasta' => $fecha_hasta
    ]);

    $result = $stmtResumen->fetch(PDO::FETCH_ASSOC);
    $procesadora = $result['procesadora'];
    $total_monto = number_format($result['total_monto'] ?? 0, 2);
    $total_comision = number_format($result['total_comision'] ?? 0, 2);
    $total_neto = number_format(($result['total_monto'] ?? 0) - ($result['total_comision'] ?? 0), 2);
    $total_transacciones = $result['total_transacciones'] ?? 0;

    // Consulta para obtener los detalles de las transacciones
    $sqlDetalles = "SELECT fecha, hora, numero_tarjeta, tipo_tarjeta, monto, comision, monto_neto, estado, comercio
                    FROM reporte_tarjetas_detalle r
                    JOIN reportes_tarjetas rt ON r.id_reporte = rt.id_reporte
                    WHERE rt.fecha_reporte BETWEEN :fecha_desde AND :fecha_hasta
                    AND rt.id_procesadora = :id_procesadora";

    $stmtDetalles = $pdo->prepare($sqlDetalles);
    $stmtDetalles->execute([
        ':id_procesadora' => $id_procesadora,
        ':fecha_desde' => $fecha_desde,
        ':fecha_hasta' => $fecha_hasta
    ]);

    $transacciones = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);

    // Generar HTML
    echo "
    <!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Liquidación de Tarjetas</title>
        <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css'>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; margin: 20px; }
            h1 { color: #333; }
            .container { max-width: 90%; margin: auto; }
            .buttons { margin-top: 20px; }
            button { padding: 10px 15px; margin: 5px; cursor: pointer; }
            table { width: 100%; margin: 20px 0; border-collapse: collapse; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f4f4f4; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1 class='title'>Liquidación de Tarjetas</h1>
            <p><strong>Procesadora:</strong> $procesadora</p>
            <p><strong>Período:</strong> $fecha_desde - $fecha_hasta</p>
            
            <h2 class='subtitle'>Detalles de las Transacciones</h2>
            <table class='table is-striped is-bordered'>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Número Tarjeta</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Comisión</th>
                        <th>Monto Neto</th>
                        <th>Estado</th>
                        <th>Comercio</th>
                    </tr>
                </thead>
                <tbody>";

    // Generar filas de detalles
    foreach ($transacciones as $transaccion) {
        echo "
            <tr>
                <td>{$transaccion['fecha']}</td>
                <td>{$transaccion['hora']}</td>
                <td>{$transaccion['numero_tarjeta']}</td>
                <td>{$transaccion['tipo_tarjeta']}</td>
                <td>\${$transaccion['monto']}</td>
                <td>\${$transaccion['comision']}</td>
                <td>\${$transaccion['monto_neto']}</td>
                <td>{$transaccion['estado']}</td>
                <td>{$transaccion['comercio']}</td>
            </tr>";
    }

    echo "
                </tbody>
            </table>

            <h2 class='subtitle'>Resumen de Transacciones</h2>
            <table class='table is-bordered'>
                <tr>
                    <th>Total de Transacciones</th>
                    <th>Monto Total</th>
                    <th>Comisión Total</th>
                    <th>Monto Neto</th>
                </tr>
                <tr>
                    <td>$total_transacciones</td>
                    <td>$$total_monto</td>
                    <td>$$total_comision</td>
                    <td>$$total_neto</td>
                </tr>
            </table>

            <div class='buttons'>
                <button class='button is-info' onclick='window.history.back()'>Volver</button>
                <button class='button is-primary' onclick='window.print()'>Imprimir</button>
            </div>
        </div>
    </body>
    </html>";
} catch (Exception $e) {
    http_response_code(500);
    echo "Error al generar la liquidación: " . $e->getMessage();
}
?>
