<?php
include("../conexion/config.php");

// Realiza la conexión
$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

// Verificar conexión
if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Consulta para obtener el último numeroPedido
$query = "SELECT MAX(numero_pedido) AS ultimo_numero FROM cabecera_pedido_interno";
$result = pg_query($conn, $query);

if ($result) {
    $row = pg_fetch_assoc($result);
    $ultimo_numero_pedido = $row['ultimo_numero'];
    $siguiente_numero_pedido = $ultimo_numero_pedido + 1;
} else {
    // Si no hay registros, iniciar con 1
    $siguiente_numero_pedido = 1;
}
header('Content-Type: application/json');
echo json_encode(array('siguiente_numero_pedido' => $siguiente_numero_pedido));
