<?php
header('Content-Type: application/json');
require '../../conexion/config_pdo.php';
require '../../../autoload.php'; // Incluir el autoload de PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo']) && isset($_POST['banco_id'])) {
    $banco_id = $_POST['banco_id'];
    $archivo = $_FILES['archivo']['tmp_name'];

    if ($archivo && $banco_id) {
        try {
            $pdo->beginTransaction();
            $spreadsheet = IOFactory::load($archivo);
            $worksheet = $spreadsheet->getActiveSheet();

            $stmt = $pdo->prepare("INSERT INTO extracto_bancario (fecha_transaccion, descripcion, monto, tipo, referencia_bancaria, banco_id) VALUES (?, ?, ?, ?, ?, ?)");

            $fila = 0;
            foreach ($worksheet->getRowIterator() as $row) {
                $fila++;
                if ($fila == 1) continue; // Saltar encabezado

                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);

                $data = [];
                foreach ($cellIterator as $cell) {
                    $data[] = $cell->getValue();
                }

                // Validar que la fila tenga al menos 5 valores
                if (count($data) < 5) continue;

                // Convertir la fecha correctamente
                if (is_numeric($data[0])) {
                    $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($data[0])->format('Y-m-d');
                } else {
                    $fecha = date('Y-m-d', strtotime($data[0]));
                }

                // Validar y convertir `monto` en número
                $monto = is_numeric($data[2]) ? floatval($data[2]) : null;
                if ($monto === null) {
                    file_put_contents('error_log.txt', "Fila $fila: Error en monto: " . print_r($data, true) . "\n", FILE_APPEND);
                    continue; // Saltar fila con error
                }

                // Ejecutar según el banco
                if (in_array($banco_id, [1, 3])) {
                    $stmt->execute([$fecha, $data[1], $monto, $data[3], $data[4], $banco_id]);
                } elseif ($banco_id == 2) { // Diferente orden en Banco XYZ
                    $stmt->execute([$fecha, $data[2], $monto, $data[3], $data[4], $banco_id]);
                }
            }

            $pdo->commit();
            echo json_encode(['exito' => true, 'mensaje' => 'Extracto bancario cargado correctamente.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['exito' => false, 'mensaje' => 'Error al procesar el archivo: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['exito' => false, 'mensaje' => 'Datos incompletos.']);
    }
} else {
    echo json_encode(['exito' => false, 'mensaje' => 'Datos incompletos.']);
}
?>
