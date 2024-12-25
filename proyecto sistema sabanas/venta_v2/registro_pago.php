<?php

// Conexión a la base de datos
$conn = pg_connect("host=localhost dbname=tu_base_de_datos user=postgres password=tu_contraseña");

// Verificar la conexión
if (!$conn) {
    die("Error en la conexión a la base de datos");
}

// Función para registrar el pago
function registrar_pago($cuenta_id, $monto_pago, $fecha_pago, $forma_pago) {
    global $conn;

    // Llamar al procedimiento almacenado
    $query = "SELECT * FROM public.registrar_pago($1, $2, $3, $4)";
    $result = pg_query_params($conn, $query, array($cuenta_id, $monto_pago, $fecha_pago, $forma_pago));

    // Verificar si la consulta fue exitosa
    if (!$result) {
        return json_encode(array("error" => "Error al registrar el pago"));
    }

    // Obtener el resultado del procedimiento almacenado
    $row = pg_fetch_assoc($result);

    // Devolver el mensaje de respuesta
    return json_encode($row);
}

// Verificar si se ha recibido una solicitud POST con los datos del pago
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validar los datos recibidos
    if (!isset($data['cuenta_id']) || !isset($data['monto_pago']) || !isset($data['fecha_pago']) || !isset($data['forma_pago'])) {
        echo json_encode(array("error" => "Faltan parámetros"));
        exit;
    }

    // Llamar a la función registrar_pago
    $cuenta_id = $data['cuenta_id'];
    $monto_pago = $data['monto_pago'];
    $fecha_pago = $data['fecha_pago'];
    $forma_pago = $data['forma_pago'];

    echo registrar_pago($cuenta_id, $monto_pago, $fecha_pago, $forma_pago);
}

?>
