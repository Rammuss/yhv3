<?php
// Iniciar la sesión
session_start();
include '../conexion/configv2.php';

// Función para obtener el monto inicial de la última caja cerrada por el usuario
function obtenerMontoInicial($conn, $user_id)
{
    $sql = "SELECT monto_inicial FROM cajas WHERE usuario = $1 ORDER BY fecha_cierre DESC LIMIT 1";
    $result = pg_query_params($conn, $sql, array($user_id));

    if ($result && $row = pg_fetch_assoc($result)) {
        return $row['monto_inicial'];
    } else {
        return 0; // Si no hay registros anteriores, devolver 0 o un valor por defecto
    }
}

// Función para generar recaudaciones a depositar
function generarRecaudacionesADepositar($conn, $user_id)
{
    // Obtener el usuario desde la sesión
    $usuario = isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : 'Usuario desconocido';

    // Obtener el monto inicial desde la última caja cerrada por el usuario
    $monto_inicial = obtenerMontoInicial($conn, $user_id);

    // Consulta para obtener el total de ventas del día
    $sql = "
    SELECT
        v.forma_pago,
        SUM(dv.cantidad * dv.precio_unitario * (1 + COALESCE(p.tipo_iva::numeric, 0) / 100)) AS monto_total
    FROM
        ventas v
    JOIN
        detalle_venta dv ON v.id = dv.venta_id
    JOIN
        producto p ON dv.producto_id = p.id_producto
    WHERE
        v.fecha::date = CURRENT_DATE
    GROUP BY
        v.forma_pago";

    $result = pg_query($conn, $sql);

    if (!$result) {
        echo "Error en la consulta: " . pg_last_error();
        exit;
    }

    $recaudaciones = pg_fetch_all($result);

    // Calcular el total de ventas del día
    $total_ventas_dia = 0;
    foreach ($recaudaciones as $fila) {
        $total_ventas_dia += $fila['monto_total'];
    }

    // Calcular el total a depositar (monto inicial + total de ventas del día)
    $total_depositar = $monto_inicial + $total_ventas_dia;

    // Mostrar los datos en una tabla HTML
    echo "<h2>Recaudaciones a Depositar</h2>";
    echo "<p><strong>Usuario:</strong> " . htmlspecialchars($usuario) . "</p>";
    echo "<p><strong>Monto Inicial:</strong> " . htmlspecialchars($monto_inicial) . "</p>";
    echo "<table border='1'>";
    echo "<tr><th>Forma de Pago</th><th>Monto Total</th></tr>";

    foreach ($recaudaciones as $fila) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($fila['forma_pago']) . "</td>";
        echo "<td>" . htmlspecialchars(number_format($fila['monto_total'], 2)) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "<p><strong>Total Ventas del Día:</strong> " . htmlspecialchars($total_ventas_dia) . "</p>";
    echo "<p><strong>Total a Depositar:</strong> " . htmlspecialchars($total_depositar) . "</p>";

    return $recaudaciones;
}

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    echo "No estás autenticado.";
    exit();
}

// Obtener el ID del usuario desde la sesión
$user_id = $_SESSION['user_id'];

// Generar y mostrar recaudaciones a depositar
generarRecaudacionesADepositar($conn, $user_id);

pg_close($conn);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Recaudaciones a Depositar</title>
    <script type="text/javascript">
        function imprimir() {
            window.print();
        }

        function irAPagina() {
            window.location.href = '../venta_v2/ui_apertura_cierre_caja.php';
        }
    </script>
</head>

<body>
    <!-- El contenido de las recaudaciones a depositar se mostrará aquí -->
    <button onclick="imprimir()">Imprimir</button>
    <button onclick="irAPagina()">Ir a Apertura</button>
</body>

</html>