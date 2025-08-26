<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Conexión a la base de datos PostgreSQL
    require_once("../conexion/config.php");
    $conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
    if (!$conn) {
        http_response_code(500);
        exit("Error de conexión: " . pg_last_error());
    }

    // --- Obtener y limpiar datos de cabecera ---
    // OJO: en el HTML el name correcto debe ser name="departamento"
    $numero_pedido            = trim($_POST['numeroPedido'] ?? '');
    $departamento_solicitante = trim($_POST['departamento'] ?? '');  // <— corregido
    $telefono                 = trim($_POST['telefono'] ?? '');
    $correo                   = trim($_POST['email'] ?? '');
    $fecha_pedido             = trim($_POST['fechaPedido'] ?? '');
    $fecha_entrega_solicitada = trim($_POST['fechaEntrega'] ?? '');

    // --- Validaciones mínimas ---
    if ($numero_pedido === '' || $departamento_solicitante === '' || $fecha_pedido === '' || $fecha_entrega_solicitada === '') {
        http_response_code(400);
        exit("Faltan datos obligatorios de cabecera");
    }

    // Detalle (arrays)
    $id_productos = $_POST['id_producto']      ?? [];
    $nombres      = $_POST['nombre_producto']  ?? [];
    $cantidades   = $_POST['cantidad']         ?? [];

    if (!is_array($id_productos) || !count($id_productos)) {
        http_response_code(400);
        exit("El pedido debe contener al menos un producto.");
    }

    try {
        // Iniciar transacción
        if (!pg_query($conn, "BEGIN")) {
            throw new Exception("No se pudo iniciar transacción");
        }

        // Insertar cabecera
        $sqlCab = "INSERT INTO cabecera_pedido_interno
            (numero_pedido, departamento_solicitante, telefono, correo, fecha_pedido, fecha_entrega_solicitada)
            VALUES ($1, $2, $3, $4, $5, $6)
            RETURNING numero_pedido";
        $paramsCab = [
            $numero_pedido,
            $departamento_solicitante,
            $telefono,
            $correo,
            $fecha_pedido,
            $fecha_entrega_solicitada
        ];
        $resCab = pg_query_params($conn, $sqlCab, $paramsCab);
        if (!$resCab) {
            throw new Exception("Error al insertar cabecera: " . pg_last_error($conn));
        }
        $rowCab = pg_fetch_assoc($resCab);
        if (!$rowCab) {
            throw new Exception("No se pudo confirmar la cabecera.");
        }
        // Por si en el futuro usas PK autoincremental, aquí obtendrías el ID.
        $numeroPedidoConfirmado = $rowCab['numero_pedido'];

        // Preparar insert detalle
        $sqlDet = "INSERT INTO detalle_pedido_interno
            (numero_pedido, id_producto, nombre_producto, cantidad)
            VALUES ($1, $2, $3, $4)";
        // Insertar cada línea
        $n = count($id_productos);
        for ($i = 0; $i < $n; $i++) {
            $id_producto     = (int) ($id_productos[$i] ?? 0);
            $nombre_producto = trim($nombres[$i] ?? '');
            $cantidad        = (int) ($cantidades[$i] ?? 0);

            if ($id_producto <= 0 || $nombre_producto === '' || $cantidad <= 0) {
                throw new Exception("Línea $i inválida (id/cantidad/nombre).");
            }

            $resDet = pg_query_params($conn, $sqlDet, [
                $numeroPedidoConfirmado,
                $id_producto,
                $nombre_producto,
                $cantidad
            ]);
            if (!$resDet) {
                throw new Exception("Error al insertar detalle (producto $id_producto): " . pg_last_error($conn));
            }
        }

        if (!pg_query($conn, "COMMIT")) {
            throw new Exception("No se pudo confirmar la transacción");
        }

        // Redirigir con el parámetro que tu HTML espera:
        header("Location: registrar_pedidos.html?registro=exitoso");
        exit;

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        http_response_code(500);
        echo "Error: " . $e->getMessage();
        exit;
    }
}
