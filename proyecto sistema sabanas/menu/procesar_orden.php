<?php
// Conexión a la base de datos PostgreSQL
include("../conexion/config.php");
// Conectar a la base de datos
$conn = pg_connect("host=$host dbname=$dbname user=$user password=$password");
if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}


// Imprimir el contenido de $_POST['detalles'] para depuración



// Obtener datos del formulario
$id_orden_compra = $_POST['id_orden_compra'];
$fecha_emision = $_POST['fecha_emision'];
$fecha_entrega = $_POST['fecha_entrega'];
$condiciones_entrega = $_POST['condiciones_entrega'];
$metodo_pago = $_POST['metodo_pago'];
$cuotas = isset($_POST['cuotas']) ? $_POST['cuotas'] : null;
$estado_orden = $_POST['estado_orden'];
$proveedor_id = $_POST['proveedor_id'];
$presupuesto_id = $_POST['presupuesto_id'];

// Iniciar una transacción
pg_query($conn, "BEGIN");

try {
    if ($metodo_pago == 'credito' && $cuotas !== null) {
        // Si el método de pago es crédito y se proporciona el número de cuotas
        $query = "INSERT INTO public.ordenes_compra (id_orden_compra, fecha_emision, fecha_entrega, condiciones_entrega, metodo_pago, cuotas, estado_orden, id_proveedor, id_presupuesto) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9)";
        $params = array($id_orden_compra, $fecha_emision, $fecha_entrega, $condiciones_entrega, $metodo_pago, $cuotas, $estado_orden, $proveedor_id, $presupuesto_id);
    } else {
        // Si el método de pago es contado o no se proporcionan cuotas
        $query = "INSERT INTO public.ordenes_compra (id_orden_compra, fecha_emision, fecha_entrega, condiciones_entrega, metodo_pago, estado_orden, id_proveedor, id_presupuesto) VALUES ($1, $2, $3, $4, $5, $6, $7, $8)";
        $params = array($id_orden_compra, $fecha_emision, $fecha_entrega, $condiciones_entrega, $metodo_pago, $estado_orden, $proveedor_id, $presupuesto_id);
    }

    // Ejecutar la consulta para insertar la orden de compra
    $result = pg_query_params($conn, $query, $params);
    if (!$result) {
        throw new Exception("Error al insertar la orden de compra: " . pg_last_error());
    }

    // Verificar que $_POST['detalles'] está definido y no está vacío
    if (isset($_POST['detalles']) && is_array($_POST['detalles']) && !empty($_POST['detalles'])) {
        // Insertar los detalles de la orden de compra
        
        $query_detalles = "INSERT INTO detalle_orden_compra (id_orden_compra, id_producto, descripcion, cantidad, precio_unitario) VALUES ($1, $2, $3, $4, $5)";
        
        foreach ($_POST['detalles'] as $detalle) {
            $detalle_values = array(
                $id_orden_compra,
                $detalle['id_producto'],
                $detalle['descripcion'],
                $detalle['cantidad'],
                $detalle['precio_unitario']
            );
            
            $result_detalle = pg_query_params($conn, $query_detalles, $detalle_values);
            if (!$result_detalle) {
                throw new Exception("Error al insertar los detalles de la orden de compra: " . pg_last_error());
            }
        }
    } else {
        throw new Exception("Detalles de la orden de compra no proporcionados o vacíos.");
    }

    // Confirmar la transacción
    pg_query($conn, "COMMIT");

    // Redirigir a una página de éxito
    header('Location: registrar_ordenes_de_compras.html?status=success');
    exit;
} catch (Exception $e) {
    // Revertir la transacción en caso de error
    pg_query($conn, "ROLLBACK");

    // Mostrar mensaje de error
    echo "Error: " . $e->getMessage();
}





echo '<pre>';
print_r($_POST['detalles']);
echo '</pre>';
// Cerrar la conexión
pg_close($conn);
?>
