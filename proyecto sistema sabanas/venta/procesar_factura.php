<?php
include("../conexion/config.php");

$conn = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");

if (!$conn) {
    die("Error de conexión: " . pg_last_error());
}

// Paso 2: Recopilar los datos del formulario
$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$direccion = $_POST['direccion'];
$telefono = $_POST['telefono'];
$ruc = $_POST['ruc'];
$nro_factura = $_POST['nro_factura'];
$fecha = $_POST['fecha'];
$ruc_empresa = $_POST['ruc_empresa'];
$metodoPago = $_POST['metodoPago'];
if (isset($_POST['cuotas'])) {
    // La clave "cuotas" está definida en el array POST
    $cuotas = $_POST['cuotas'];
} else {
    // La clave "cuotas" no está definida en el array POST
    $cuotas = 0; // O proporciona un valor predeterminado apropiado
}
if (isset($_POST['Intervalo'])) {
    // La clave "Intervalo" está definida en el array POST
    $intervalo = $_POST['Intervalo'];
} else {
    // La clave "Intervalo" no está definida en el array POST
    $intervalo = 0; // O proporciona un valor predeterminado apropiado
}

// Paso 3: Construir y ejecutar una consulta SQL para insertar datos en las tablas correspondientes
// Insertar datos en la tabla "clientes"
$queryClientes = "INSERT INTO Clientes (nombre, apellido, direccion, telefono, ruc_ci) 
VALUES ('$nombre', '$apellido', '$direccion', '$telefono', '$ruc') RETURNING id_cliente";
$resultClientes = pg_query($conn,$queryClientes);

// Obtener el ID del cliente recién insertado
if ($resultClientes) {
    $row = pg_fetch_assoc($resultClientes);
    $idCliente = $row['id_cliente'];
} else {
    echo "Error en la consulta: " . pg_last_error($conn);
}


// Insertar datos en la tabla "facturas"
$queryFacturas = "INSERT INTO Facturas (nro_factura, fecha, ruc_empresa, metodo_pago, cuotas, intervalo_cuotas, id_cliente) 
VALUES ('$nro_factura', '$fecha', '$ruc_empresa', '$metodoPago', '$cuotas', '$intervalo', '$idCliente')RETURNING id_factura";
$resultFacturas = pg_query($conn,$queryFacturas);

// Obtener el ID de la factura recién insertada
if ($resultFacturas) {
    $row = pg_fetch_assoc($resultFacturas);
    $idFactura = $row['id_factura']; // Aquí obtienes el valor de la columna "id_cliente"
} else {
    echo "Error en la consulta: " . pg_last_error($conn);
}


// Recopilar datos de los productos y guardarlos en la tabla "detalles_factura"
if (isset($_POST['datosTabla'])) {
    $datosTabla = json_decode($_POST['datosTabla'], true);
    // Asigna valores predeterminados a los elementos "exenta" e "iva5" si están vacíos
    if (isset($datosTabla['exenta']) && ($datosTabla['exenta'] === null || $datosTabla['exenta'] === '')) {
        $datosTabla['exenta'] = 0; // Asigna 0 a "exenta" si está vacía o nula
    }
    if (isset($datosTabla['iva5']) && ($datosTabla['iva5'] === null || $datosTabla['iva5'] === '')) {
        $datosTabla['iva5'] = 0; // Asigna 0 a "iva5" si está vacía o nula
    }

    foreach ($datosTabla as $producto) {
        $codigoProducto = $producto['codigoProducto'];
        $productoNombre = $producto['producto'];
        $precio = $producto['precio'];
        $cantidad = $producto['cantidad'];
        $exenta = $producto['exenta'];
        $iva5 = $producto['iva5'];
        $iva10 = $producto['iva10'];
        $subTotal = $producto['subTotal'];
        

        // Ejecuta una consulta SQL para insertar los datos en la tabla
        $sql = "INSERT INTO Detalles_Factura (id_factura, cod_producto, nombre_producto, precio_unitario, cantidad, exenta, iva_5, iva_10, subtotal) 
        VALUES ('$idFactura','$codigoProducto', '$productoNombre', $precio, $cantidad, '$exenta', $iva5, $iva10, $subTotal)";

        $result = pg_query($conn, $sql);

        if (!$result) {
            die("Error en la consulta: " . pg_last_error($conn));
        }
        // Haz lo que necesites con estos datos en PHP, como guardarlos en una base de datos
    }
}

// Cierra la conexión a la base de datos
pg_close($conn);

// Redirige a una página de éxito o muestra un mensaje de éxito
if ($resultClientes && $resultFacturas && $result) {
    // Todo ha ido bien, redirige a una página de éxito
    header("Location: exito.php");
} else {
    // Hubo un error, muestra un mensaje de error
    echo "Error al procesar la factura. Por favor, inténtalo de nuevo.";
}
