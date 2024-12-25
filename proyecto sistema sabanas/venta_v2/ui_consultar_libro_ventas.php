<?php
// Iniciar la sesión
session_start();
include '../conexion/configv2.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    echo "No estás autenticado.";
    exit();
}

// Obtener los parámetros de los filtros
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';

// Función para obtener los registros del libro de ventas con filtros
function obtenerLibroVentas($conn, $fecha_inicio, $fecha_fin, $estado)
{
    $sql = "SELECT * FROM libro_ventas WHERE 1=1";
    $params = [];

    if (!empty($fecha_inicio)) {
        $sql .= " AND fecha >= $1";
        $params[] = $fecha_inicio;
    }

    if (!empty($fecha_fin)) {
        $sql .= " AND fecha <= $2";
        $params[] = $fecha_fin;
    }

    if (!empty($estado)) {
        $sql .= " AND estado = $3";
        $params[] = $estado;
    }

    $sql .= " ORDER BY fecha DESC";

    $result = pg_query_params($conn, $sql, $params);

    if (!$result) {
        echo "Error en la consulta: " . pg_last_error();
        exit();
    }

    $libro_ventas = pg_fetch_all($result);
    return $libro_ventas;
}

// Obtener los registros del libro de ventas con los filtros aplicados
$libro_ventas = obtenerLibroVentas($conn, $fecha_inicio, $fecha_fin, $estado);

pg_close($conn);
?>

<!DOCTYPE html>
<html>

<head>
    <title>Consultar Libro de Ventas</title>
    <link rel="stylesheet" href="styles_venta.css"> <!-- Enlace a tu archivo CSS -->
    <script src="navbar.js"></script>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }

        table,
        th,
        td {
            border: 1px solid #ddd;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f1f1f1;
        }
    </style>
</head>

<body>
<div id="navbar-container"></div>

    <h2>Libro de Ventas</h2>

    <!-- Formulario de filtros -->
    <form method="get" action="ui_consultar_libro_ventas.php">
        <label for="fecha_inicio">Fecha Inicio:</label>
        <input type="date" id="fecha_inicio" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">

        <label for="fecha_fin">Fecha Fin:</label>
        <input type="date" id="fecha_fin" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">

        <label for="estado">Estado:</label>
        <input type="text" id="estado" name="estado" value="<?= htmlspecialchars($estado) ?>">

        <button type="submit">Consultar</button>
    </form>

    <?php if ($libro_ventas): ?>
        <table>
            <tr>
                <th>Fecha</th>
                <th>Número de Factura</th>
                <th>Timbrado</th>
                <th>Cliente</th>
                <th>Forma de Pago</th>
                <th>Monto Total</th>
                <th>Estado</th>
            </tr>
            <?php foreach ($libro_ventas as $venta): ?>
                <tr>
                    <td><?= htmlspecialchars($venta['fecha']) ?></td>
                    <td><?= htmlspecialchars($venta['numero_factura']) ?></td>
                    <td><?= htmlspecialchars($venta['timbrado']) ?></td>
                    <td><?= htmlspecialchars($venta['cliente_nombre']) ?></td>
                    <td><?= htmlspecialchars($venta['forma_pago']) ?></td>
                    <td><?= htmlspecialchars(number_format($venta['monto_total'], 2)) ?></td>
                    <td><?= htmlspecialchars($venta['estado']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No se encontraron registros en el libro de ventas.</p>
    <?php endif; ?>
</body>

</html>