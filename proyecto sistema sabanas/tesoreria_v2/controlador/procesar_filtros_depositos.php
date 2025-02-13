<?php
require "../../conexion/config_pdo.php";

// Obtener valores del filtro
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin    = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';
$estado       = isset($_GET['estado']) ? $_GET['estado'] : '';

// Construir la consulta con filtros opcionales
$sql = "SELECT bd.id_deposito, bd.numero_boleta, bd.fecha, bd.monto, bd.concepto, bd.estado, cb.nombre_banco 
        FROM boletas_deposito bd
        JOIN cuentas_bancarias cb ON bd.id_cuenta_bancaria = cb.id_cuenta_bancaria
        WHERE 1=1";

// Agregar condiciones según los filtros seleccionados
$params = [];
if (!empty($fecha_inicio)) {
    $sql .= " AND bd.fecha >= :fecha_inicio";
    $params[':fecha_inicio'] = $fecha_inicio;
}
if (!empty($fecha_fin)) {
    $sql .= " AND bd.fecha <= :fecha_fin";
    $params[':fecha_fin'] = $fecha_fin;
}
if (!empty($estado)) {
    $sql .= " AND bd.estado = :estado";
    $params[':estado'] = $estado;
}

// Preparar y ejecutar la consulta
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$depositos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Número de Boleta</th>
            <th>Fecha</th>
            <th>Monto</th>
            <th>Concepto</th>
            <th>Cuenta Bancaria</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($depositos)): ?>
            <tr><td colspan="7">No hay resultados.</td></tr>
        <?php else: ?>
            <?php foreach ($depositos as $deposito): ?>
                <tr>
                    <td><?= $deposito['id_deposito'] ?></td>
                    <td><?= $deposito['numero_boleta'] ?></td>
                    <td><?= $deposito['fecha'] ?></td>
                    <td>$<?= number_format($deposito['monto'], 2) ?></td>
                    <td><?= $deposito['concepto'] ?></td>
                    <td><?= $deposito['nombre_banco'] ?></td>
                    <td><?= $deposito['estado'] ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
