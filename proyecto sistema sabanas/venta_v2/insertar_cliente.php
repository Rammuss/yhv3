<?php
header('Content-Type: application/json');

// Conexión a la base de datos
include '../conexion/configv2.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $apellido = $_POST['apellido'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $ruc_ci = $_POST['ruc_ci'];

    // Validar los datos
    if (empty($nombre) || empty($apellido) || empty($direccion) || empty($telefono) || empty($ruc_ci)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios.']);
        exit;
    }

    // Llamar al procedimiento almacenado
    $query = "SELECT insertar_cliente($1, $2, $3, $4, $5)";
    $result = pg_query_params($conn, $query, [$nombre, $apellido, $direccion, $telefono, $ruc_ci]);

    if ($result) {
        $nuevo_id = pg_fetch_result($result, 0, 0); // Obtener el ID generado
        echo json_encode(['success' => true, 'cliente_id' => $nuevo_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al insertar el cliente.']);
    }
}
?>
