<?php
// upload_reporte_tarjetas.php

// Incluir la configuración y conexión a la base de datos
require "../../conexion/config_pdo.php";

// Reportar errores para facilitar el debug (en ambiente de desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que se esté usando el método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Método no permitido.";
    exit;
}

// Validar que existan los campos requeridos
if (!isset($_POST['id_procesadora'], $_POST['fecha_reporte'], $_FILES['reporte_file'])) {
    http_response_code(400);
    echo "Faltan campos requeridos.";
    exit;
}

$id_procesadora = trim($_POST['id_procesadora']);
$fecha_reporte  = trim($_POST['fecha_reporte']);

// Validar y procesar el archivo subido
$file = $_FILES['reporte_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo "Error al cargar el archivo.";
    exit;
}

// Verificar la extensión del archivo (se espera CSV)
$allowed_extensions = ['csv'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($file_ext, $allowed_extensions)) {
    http_response_code(400);
    echo "Formato de archivo no permitido. Solo se admite CSV.";
    exit;
}

// Definir directorio de carga (por ejemplo, dentro de la carpeta actual "uploads/reporte_tarjetas/")
$upload_dir = __DIR__ . '../controlador/reportes_tarjetas';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generar un nombre único para evitar conflictos (opcional)
$filename = time() . "_" . basename($file['name']);
$target_file = $upload_dir . $filename;

// Mover el archivo cargado al directorio destino
if (!move_uploaded_file($file['tmp_name'], $target_file)) {
    http_response_code(500);
    echo "Error al mover el archivo cargado.";
    exit;
}

try {
    // Iniciar una transacción para asegurar la atomicidad de las operaciones
    $pdo->beginTransaction();

    // 1. Insertar el registro en la tabla reportes_tarjetas (tabla maestra)
    $sqlMaster = "INSERT INTO reportes_tarjetas (id_procesadora, fecha_reporte, nombre_archivo, ruta_archivo)
                  VALUES (:id_procesadora, :fecha_reporte, :nombre_archivo, :ruta_archivo)";
    $stmtMaster = $pdo->prepare($sqlMaster);
    $stmtMaster->execute([
        ':id_procesadora' => $id_procesadora,
        ':fecha_reporte'  => $fecha_reporte,
        ':nombre_archivo' => $filename,
        ':ruta_archivo'   => $target_file
    ]);

    // Obtener el id del reporte insertado
    $id_reporte = $pdo->lastInsertId();

    // 2. Preparar la inserción en la tabla de detalle: reporte_tarjetas_detalle
    $sqlDetail = "INSERT INTO reporte_tarjetas_detalle 
                  (id_reporte, fecha, hora, numero_tarjeta, tipo_tarjeta, monto, comision, monto_neto, estado, comercio)
                  VALUES (:id_reporte, :fecha, :hora, :numero_tarjeta, :tipo_tarjeta, :monto, :comision, :monto_neto, :estado, :comercio)";
    $stmtDetail = $pdo->prepare($sqlDetail);

    // 3. Abrir y leer el archivo CSV
    if (($handle = fopen($target_file, "r")) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            // Saltar la primera línea si es encabezado
            if ($row === 0) {
                $row++;
                continue;
            }

            // Se asume que el CSV tiene al menos 7 columnas:
            // [0] Fecha, [1] Hora, [2] Número de Tarjeta, [3] Tipo de Tarjeta, [4] Monto, [5] Estado, [6] Comercio
            if (count($data) < 7) {
                // Si la línea no tiene suficientes columnas, la saltamos
                continue;
            }

            // Extraer y limpiar datos
            $csv_fecha = trim($data[0]); // Ejemplo: "10/02/2025"
            // Convertir la fecha del formato dd/mm/YYYY a YYYY-mm-dd
            $dateParts = explode("/", $csv_fecha);
            if (count($dateParts) == 3) {
                $csv_fecha = $dateParts[2] . "-" . $dateParts[1] . "-" . $dateParts[0];
            }

            $csv_hora           = trim($data[1]);   // Ejemplo: "14:35"
            $csv_numero_tarjeta = trim($data[2]);   // Ejemplo: "**** **** **** 1234"
            $csv_tipo_tarjeta   = trim($data[3]);   // Ejemplo: "Crédito" o "Débito"
            // El monto puede venir con símbolos de moneda; se limpia y se convierte a número
            $csv_monto = floatval(str_replace(['$',','], '', trim($data[4])));
            $csv_estado  = trim($data[5]);           // Ejemplo: "Aprobada" o "Rechazada"
            $csv_comercio = trim($data[6]);          // Ejemplo: "Supermercado ABC"

            // Si el archivo incluye columnas adicionales para comisión y monto neto, usarlas; de lo contrario, asignar por defecto
            $csv_comision = 0;
            $csv_monto_neto = $csv_monto;  // Si no se especifica, el neto es igual al monto

            // Si hay al menos 9 columnas, se asume:
            // [7] Comisión, [8] Monto Neto
            if (count($data) >= 9) {
                $csv_comision = floatval(str_replace(['$',','], '', trim($data[7])));
                $csv_monto_neto = floatval(str_replace(['$',','], '', trim($data[8])));
            }

            // Insertar el registro en la tabla de detalle
            $stmtDetail->execute([
                ':id_reporte'      => $id_reporte,
                ':fecha'           => $csv_fecha,
                ':hora'            => $csv_hora,
                ':numero_tarjeta'  => $csv_numero_tarjeta,
                ':tipo_tarjeta'    => $csv_tipo_tarjeta,
                ':monto'           => $csv_monto,
                ':comision'        => $csv_comision,
                ':monto_neto'      => $csv_monto_neto,
                ':estado'          => $csv_estado,
                ':comercio'        => $csv_comercio
            ]);

            $row++;
        }
        fclose($handle);
    } else {
        throw new Exception("No se pudo abrir el archivo CSV para leer.");
    }

    // Confirmar la transacción si todo fue exitoso
    $pdo->commit();
    echo "Reporte y detalles cargados exitosamente.";
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo "Error al cargar el reporte: " . $e->getMessage();
}
?>
