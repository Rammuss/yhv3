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
    $monto_inicial = floatval($row_caja['monto_inicial']);

    // Consultar el total de ventas, agrupando por método de pago
    $query_ventas = "
        SELECT 
            metodo_pago,
            SUM(dv.cantidad * dv.precio_unitario * (1 + COALESCE(p.tipo_iva::numeric, 0) / 100)) AS monto_total
        FROM 
            ventas v
        JOIN 
            detalle_venta dv ON v.id = dv.venta_id
        JOIN
            producto p ON dv.producto_id = p.id_producto
        WHERE 
            v.fecha::date = CURRENT_DATE
            AND v.estado = 'pendiente' -- Incluir solo ventas pendientes
        GROUP BY 
            metodo_pago";
    $result_ventas = pg_query($conn, $query_ventas);

    $metodos_pago = [];
    $total_ventas = 0;

    while ($row_ventas = pg_fetch_assoc($result_ventas)) {
        $metodos_pago[$row_ventas['metodo_pago']] = floatval($row_ventas['monto_total']);
        $total_ventas += floatval($row_ventas['monto_total']);
    }

    // Consultar las notas de crédito aplicadas
    $query_notas_credito = "
        SELECT 
            SUM(monto_nc_aplicado) AS total_notas_credito
        FROM 
            ventas
        WHERE 
            fecha::date = CURRENT_DATE
            AND estado = 'pendiente'"; // Incluir solo notas de crédito pendientes
    $result_nc = pg_query($conn, $query_notas_credito);

    $total_notas_credito = 0;
    if ($row_nc = pg_fetch_assoc($result_nc)) {
        $total_notas_credito = floatval($row_nc['total_notas_credito']);
    }

    // Calcular el monto esperado en la caja
    $monto_esperado = $monto_inicial + $total_ventas - $total_notas_credito;

    // Responder con los datos
    echo json_encode([
        'monto_inicial' => $monto_inicial,
        'metodos_pago' => $metodos_pago,
        'total_ventas' => $total_ventas,
        'total_notas_credito' => $total_notas_credito,
        'monto_esperado' => $monto_esperado,
    ]);
} else {
    echo json_encode(['error' => 'No hay cajas abiertas para este usuario.']);
}

// Cerrar conexión
pg_close($conn);
?>
