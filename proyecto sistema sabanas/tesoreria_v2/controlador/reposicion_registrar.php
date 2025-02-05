<?php
header("Content-Type: application/json");
include "../../conexion/configv2.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recoger datos del formulario
    $rendicion_id = isset($_POST["rendicion_id"]) ? $_POST["rendicion_id"] : null;
    $monto_repuesto = isset($_POST["monto_repuesto"]) ? (float)$_POST["monto_repuesto"] : 0;
    $fecha_reposicion = isset($_POST["fecha_reposicion"]) ? $_POST["fecha_reposicion"] : null;

    // Procesar comprobante si se subió uno (opcional)
    $comprobante_path = null;
    if (isset($_FILES["comprobante"]) && $_FILES["comprobante"]["error"] == 0) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $comprobante_path = $uploadDir . basename($_FILES["comprobante"]["name"]);
        if (!move_uploaded_file($_FILES["comprobante"]["tmp_name"], $comprobante_path)) {
            echo json_encode(["success" => false, "message" => "Error al subir el comprobante."]);
            exit;
        }
    }

    // Insertar reposición en reposiciones_ff (estado se deja inicialmente en 'Pendiente')
    $sql = "INSERT INTO reposiciones_ff (rendicion_id, monto_repuesto, fecha_reposicion, documento_path)
            VALUES ($1, $2, $3, $4) RETURNING id";
    $stmt = pg_prepare($conn, "insert_reposicion", $sql);
    $result = pg_execute($conn, "insert_reposicion", [$rendicion_id, $monto_repuesto, $fecha_reposicion, $comprobante_path]);
    
    if (!$result) {
        echo json_encode(["success" => false, "message" => "Error al registrar reposición: " . pg_last_error($conn)]);
        exit;
    }
    
    $reposicion_id = pg_fetch_result($result, 0, "id");
    
    // Recuperar el monto asignado y total_rendido de la rendición asociada (a través de la asignación)
    $sqlRend = "SELECT a.monto AS monto_asignado, r.total_rendido 
                FROM rendiciones_ff r 
                JOIN asignaciones_ff a ON r.asignacion_id = a.id 
                WHERE r.id = $1";
    $resultRend = pg_query_params($conn, $sqlRend, [$rendicion_id]);
    if (!$resultRend || pg_num_rows($resultRend) == 0) {
        echo json_encode(["success" => false, "message" => "Rendición no encontrada."]);
        exit;
    }
    $rowRend = pg_fetch_assoc($resultRend);
    $monto_asignado = (float)$rowRend["monto_asignado"];
    $total_rendido = (float)$rowRend["total_rendido"];
    
    // Determinar la situación y calcular el "valor esperado" para la reposición
    $estado_reposicion = "Pendiente"; // por defecto
    if ($monto_asignado >= $total_rendido) {
        // Caso A: No se excede el fondo fijo
        $esperado = $monto_asignado - $total_rendido; // dinero no gastado, a reponer
        if (round($monto_repuesto, 2) == round($esperado, 2)) {
            $estado_reposicion = "Completada";
        } else {
            $estado_reposicion = "Pendiente";
        }
    } else {
        // Caso B: Se excede el fondo fijo
        $esperado_exceso = $total_rendido - $monto_asignado; // exceso de gasto que se debe aportar
        // En este caso, aunque el usuario aporte lo "esperado", la reposición se marcará como Pendiente
        // para que se gestione el excedente (cuenta por pagar).
        $estado_reposicion = "Pendiente";
    }
    
    // Actualizar el estado en reposiciones_ff
    $sqlUpdate = "UPDATE reposiciones_ff SET estado = $1 WHERE id = $2";
    pg_query_params($conn, $sqlUpdate, [$estado_reposicion, $reposicion_id]);
    
    // Si la reposición es completada, actualizamos también la rendición asociada a 'Completada'
    if ($estado_reposicion === "Completada") {
        $sqlUpdateRend = "UPDATE rendiciones_ff SET estado = 'Completada' WHERE id = $1";
        pg_query_params($conn, $sqlUpdateRend, [$rendicion_id]);
    }
    
    // En caso de que se haya excedido el fondo fijo (Caso B), generar la cuenta por pagar
    if ($monto_asignado < $total_rendido) {
        // En este caso, se espera que el empleado aporte el excedente, que es:
        $excedente = round($total_rendido - $monto_asignado, 2);
        
        // Recuperar id_proveedor de la rendición (a través de asignaciones)
        $sqlProv = "SELECT p.id_proveedor 
                    FROM rendiciones_ff r
                    JOIN asignaciones_ff a ON r.asignacion_id = a.id
                    JOIN proveedores p ON a.proveedor_id = p.id_proveedor
                    WHERE r.id = $1";
        $resultProv = pg_query_params($conn, $sqlProv, [$rendicion_id]);
        if ($resultProv && pg_num_rows($resultProv) > 0) {
            $id_proveedor = pg_fetch_result($resultProv, 0, "id_proveedor");
        } else {
            $id_proveedor = null;
        }
        
        // Insertar la provisión en provisiones_cuentas_pagar para el excedente
        $sqlProvIns = "INSERT INTO provisiones_cuentas_pagar 
                       (id_proveedor, monto_provisionado, estado_provision, id_usuario_creacion, id_reposicion_ff, tipo_provision)
                       VALUES ($1, $2, $3, $4, $5, $6)";
        // Suponiendo que id_usuario_creacion se obtiene de la sesión o se fija (por ejemplo, 1)
        $id_usuario_creacion = 1;
        $estado_provision = "Pendiente";
        $tipo_provision = "Fondo Fijo";
        
        $resultProvIns = pg_query_params($conn, $sqlProvIns, [
            $id_proveedor, 
            $excedente, 
            $estado_provision, 
            $id_usuario_creacion, 
            $reposicion_id, 
            $tipo_provision
        ]);
        
        if (!$resultProvIns) {
            echo json_encode(["success" => false, "message" => "Reposición registrada, pero error al crear cuenta por pagar: " . pg_last_error($conn)]);
            exit;
        }
    }
    
    echo json_encode(["success" => true, "message" => "Reposición registrada correctamente. Estado: $estado_reposicion"]);
}
?>
