<?php
// Configuración de la base de datos
require "../../conexion/config_pdo.php";

try {
    

    // Filtro opcional por tipo de movimiento
    $tipo_movimiento = $_GET['tipo_movimiento'] ?? '';

    $sql = "SELECT id_movimiento, id_cuenta_bancaria, tipo_movimiento, monto, descripcion, 
                   fecha_movimiento, referencia, usuario_registro, origen 
            FROM otros_debitos_creditos";
    
    if (!empty($tipo_movimiento)) {
        $sql .= " WHERE tipo_movimiento = :tipo_movimiento";
    }

    $stmt = $pdo->prepare($sql);

    if (!empty($tipo_movimiento)) {
        $stmt->bindParam(':tipo_movimiento', $tipo_movimiento, PDO::PARAM_STR);
    }

    $stmt->execute();
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error en la conexión: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos Varios</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bulma@0.9.3/css/bulma.min.css">
    <link rel="stylesheet" href="../css/styles_T.css">

</head>
<body>
<div id="navbar-container"></div>

    <section class="section">
        <div class="container">
            <h1 class="title">Movimientos Varios</h1>
            
            <!-- Filtro por tipo de movimiento -->
            <form method="GET" class="box">
                <div class="field">
                    <label class="label">Filtrar por Tipo de Movimiento:</label>
                    <div class="control">
                        <div class="select">
                            <select name="tipo_movimiento">
                                <option value="">Todos</option>
                                <option value="DEBITO" <?= $tipo_movimiento === 'DEBITO' ? 'selected' : '' ?>>Débito</option>
                                <option value="CREDITO" <?= $tipo_movimiento === 'CREDITO' ? 'selected' : '' ?>>Crédito</option>
                            </select>
                        </div>
                        <button class="button is-primary" type="submit">Filtrar</button>
                    </div>
                </div>
            </form>

            <!-- Tabla de Movimientos -->
            <table class="table is-striped is-bordered is-fullwidth">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Cuenta</th>
                        <th>Tipo</th>
                        <th>Monto</th>
                        <th>Descripción</th>
                        <th>Fecha</th>
                        <th>Referencia</th>
                        <th>Usuario</th>
                        <th>Origen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($movimientos) > 0): ?>
                        <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td><?= htmlspecialchars($mov['id_movimiento']) ?></td>
                                <td><?= htmlspecialchars($mov['id_cuenta_bancaria']) ?></td>
                                <td><?= htmlspecialchars($mov['tipo_movimiento']) ?></td>
                                <td>$<?= number_format($mov['monto'], 2) ?></td>
                                <td><?= htmlspecialchars($mov['descripcion']) ?></td>
                                <td><?= date('d-m-Y H:i', strtotime($mov['fecha_movimiento'])) ?></td>
                                <td><?= htmlspecialchars($mov['referencia'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($mov['usuario_registro']) ?></td>
                                <td><?= htmlspecialchars($mov['origen']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="has-text-centered">No hay registros.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <script src="../js/navbarT.js"></script>

</body>
</html>
