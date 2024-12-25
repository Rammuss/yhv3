<?php
$productos = json_decode($_POST['productos'], true);

if (isset($_POST['productos']) && is_array($productos)) {
    foreach ($productos as $producto) {
        // Aquí puedes acceder a los datos individuales de cada producto.
        $nombre = $producto['nombre'];
        $cantidad = $producto['cantidad'];
        $precioUnitario = $producto['precioUnitario'];
        // Realiza la inserción en la base de datos u otro procesamiento necesario.
    }
}
?>