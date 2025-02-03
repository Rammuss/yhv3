<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger datos de la rendición
    $asignacion_id = isset($_POST["asignacion"]) ? $_POST["asignacion"] : null;
    $fecha_rendicion = isset($_POST["fecha_rendicion"]) ? $_POST["fecha_rendicion"] : null;
    $total_rendido = isset($_POST["total_rendido"]) ? $_POST["total_rendido"] : null;

    // Procesar el archivo subido (si lo hay)
    $documento_path = null;
    if (isset($_FILES["documento"]) && $_FILES["documento"]["error"] == 0) {
        $uploadDir = "/TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/tesoreria_v2/docs";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $documento_path = $uploadDir . basename($_FILES["documento"]["name"]);
        if (!move_uploaded_file($_FILES["documento"]["tmp_name"], $documento_path)) {
            echo json_encode(["success" => false, "message" => "Error al subir el documento."]);
            exit;
        }
    }

    // Insertar en rendiciones_ff
    $sql = "INSERT INTO rendiciones_ff (asignacion_id, fecha_rendicion, total_rendido, documento_path) 
            VALUES ($1, $2, $3, $4) RETURNING id";
    $stmt = pg_prepare($conn, "insert_rendicion", $sql);
    $result = pg_execute($conn, "insert_rendicion", [$asignacion_id, $fecha_rendicion, $total_rendido, $documento_path]);

    if ($result) {
        $rendicion_id = pg_fetch_result($result, 0, "id");

        // Insertar detalles (cada detalle viene en arrays)
        if (isset($_POST["descripcion"]) && is_array($_POST["descripcion"])) {
            $descripciones = $_POST["descripcion"];
            $montos = $_POST["monto"];
            $fechas_gasto = $_POST["fecha_gasto"];
            $documentos_asociados = $_POST["documento_asociado"];

            foreach ($descripciones as $i => $descripcion) {
                $monto = $montos[$i];
                $fecha_gasto = $fechas_gasto[$i];
                $documento_asociado = $documentos_asociados[$i];

                $sql_detalle = "INSERT INTO detalle_rendiciones (rendicion_id, descripcion, monto, fecha_gasto, documento_asociado) 
                                VALUES ($1, $2, $3, $4, $5)";
                pg_query_params($conn, $sql_detalle, [$rendicion_id, $descripcion, $monto, $fecha_gasto, $documento_asociado]);
            }
        }

        echo json_encode(["success" => true, "message" => "Rendición registrada con éxito."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar la rendición: " . pg_last_error($conn)]);
    }
}
?>
