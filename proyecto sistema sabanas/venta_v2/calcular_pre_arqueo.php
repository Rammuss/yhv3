<?php
session_start(); // Iniciar sesión

// Configuración de conexión a PostgreSQL
include '../conexion/configv2.php';


// Verificar si el ID del usuario está en la sesión
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['error' => 'No se ha iniciado sesión correctamente.']);
    exit;
}

$usuario_id = $_SESSION['user_id']; // Obtener el ID del usuario desde la sesión

// Consultar la caja abierta asociada al usuario
$query_caja_abierta = "SELECT id_caja, monto_inicial
                       FROM cajas
                       WHERE usuario = $1 AND estado = 'Abierta'";
$result_caja = pg_query_params($conn, $query_caja_abierta, [$usuario_id]);

if ($row_caja = pg_fetch_assoc($result_caja)) {
    $id_caja = intval($row_caja['id_caja']);
    $monto_inicial = intval($row_caja['monto_inicial']);

    // Consultar el total de ventas en efectivo para el día
    $query_ventas = "
        SELECT 
            SUM(dv.cantidad * dv.precio_unitario * (1 + COALESCE(p.tipo_iva::numeric, 0) / 100)) AS monto_total_ventas_dia
        FROM 
            ventas v
        JOIN 
            detalle_venta dv ON v.id = dv.venta_id
        JOIN
            producto p ON dv.producto_id = p.id_producto
        WHERE 
            v.forma_pago = 'efectivo'
            AND v.fecha::date = CURRENT_DATE";
    $result_ventas = pg_query($conn, $query_ventas);

    $total_ventas_efectivo = 0;
    if ($row_ventas = pg_fetch_assoc($result_ventas)) {
        $total_ventas_efectivo = floatval($row_ventas['monto_total_ventas_dia']);
    }

    // Calcular el monto esperado
    $monto_esperado = $monto_inicial + $total_ventas_efectivo;

    // Responder con los datos
    echo json_encode([
        'monto_inicial' => $monto_inicial,
        'total_ventas_efectivo' => $total_ventas_efectivo,
        'monto_esperado' => $monto_esperado,
    ]);
} else {
    echo json_encode(['error' => 'No hay cajas abiertas para este usuario.']);
}

// Cerrar conexión
pg_close($conn);
