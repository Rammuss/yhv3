<?php
session_start();
include("../../conexion/configv2.php");

header('Content-Type: application/json');
$response = array(
    'nombre_usuario' => isset($_SESSION['nombre_usuario']) ? $_SESSION['nombre_usuario'] : 'Invitado'
);
echo json_encode($response);
