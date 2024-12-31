<?php
// Conexión a la base de datos
include '../conexion/configv2.php';

// Recibir datos del frontend en formato JSON
$data = json_decode(file_get_contents('php://input'), true);

// Validar los parámetros recibidos
$fecha = isset($data['fecha']) ? $data['fecha'] : null;
$numero_nota = isset($data['numero_nota']) ? $data['numero_nota'] : null;
$ruc = isset($data['ruc']) ? $data['ruc'] : null;
$estado = isset($data['estado']) ? $data['estado'] : null;

try {
    // Construir la consulta dinámica

    $query = "SELECT nrc.*, c.ruc_ci, c.nombre 
          FROM nota_remision_venta_cabecera nrc
          INNER JOIN clientes c ON nrc.cliente_id = c.id_cliente
          WHERE 1=1"; // Esto permite agregar condiciones dinámicamente



    $params = [];
    if ($fecha) {
        $query .= " AND nrc.fecha::date = $1";
        $params[] = $fecha;
    }
    if ($numero_nota) {
        $query .= " AND nrc.id_remision = $" . (count($params) + 1);
        $params[] = $numero_nota;
    }
    if ($ruc) {
        $query .= " AND c.ruc ILIKE $" . (count($params) + 1);
        $params[] = '%' . $ruc . '%';
    }
    if ($estado) {
        $query .= " AND nrc.estado = $" . (count($params) + 1);
        $params[] = $estado;
    }

    // Ejecutar la consulta
    $result = pg_query_params($conn, $query, $params);

    if (!$result) {
        throw new Exception("Error al realizar la consulta");
    }

    // Convertir los resultados a un array
    $notas = [];
    while ($row = pg_fetch_assoc($result)) {
        $notas[] = $row;
    }

    // Enviar la respuesta en formato JSON
    echo json_encode(['success' => true, 'data' => $notas]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    // Cerrar la conexión
    pg_close($conn);
}
