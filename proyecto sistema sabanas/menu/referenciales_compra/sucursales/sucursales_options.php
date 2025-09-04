<?php
// sucursales_options.php
header('Content-Type: application/json; charset=utf-8');
require_once("../../../conexion/configv2.php");

$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"No se pudo conectar a la BD"]);
    exit;
}

// Leer filtro de estado (opcional)
$estado = $_GET['estado'] ?? null;
$params = [];
$where = "";

if ($estado !== null) {
    $where = "WHERE estado = $1";
    $params[] = $estado;
}

$sql = "SELECT id_sucursal, nombre FROM public.sucursales $where ORDER BY nombre";
$res = pg_query_params($c, $sql, $params);

if(!$res){
    http_response_code(500);
    echo json_encode(["ok"=>false,"error"=>"Error en la consulta"]);
    exit;
}

$out = [];
while($row = pg_fetch_assoc($res)){
    $out[] = [
        "id_sucursal" => (int)$row["id_sucursal"],
        "nombre"      => $row["nombre"]
    ];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
