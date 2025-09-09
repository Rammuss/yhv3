<?php
// nota_remision_anular.php
header('Content-Type: application/json; charset=utf-8');
require_once("../conexion/config.php");
$c = pg_connect("host=$host port=$port dbname=$dbname user=$user password=$password");
if(!$c){ echo json_encode(["ok"=>false,"error"=>"DB"]); exit; }

$id = isset($_POST['id_nota_remision']) ? (int)$_POST['id_nota_remision'] : 0;
$motivo = isset($_POST['motivo']) ? trim($_POST['motivo']) : '';
if($id<=0){ echo json_encode(["ok"=>false,"error"=>"id requerido"]); exit; }

pg_query($c, "BEGIN");
try{
  $r = pg_query_params($c, "SELECT estado FROM nota_remision_cab WHERE id_nota_remision=$1 FOR UPDATE", [$id]);
  if(!$r || pg_num_rows($r)==0){ throw new Exception("No existe la nota"); }
  $estado = pg_fetch_result($r, 0, 'estado');
  if($estado==='Anulada'){ throw new Exception("Ya está anulada"); }

  // (Opcional) revertir stock aquí si tu modelo lo requiere.

  $ok = pg_query_params($c, "UPDATE nota_remision_cab SET estado='Anulada', observacion = CASE WHEN observacion IS NULL OR observacion='' THEN $1 ELSE observacion || ' | ANULADA: ' || $1 END WHERE id_nota_remision=$2", [$motivo!==''?$motivo:'Anulación vía sistema', $id]);
  if(!$ok){ throw new Exception("No se pudo anular"); }

  pg_query($c, "COMMIT");
  echo json_encode(["ok"=>true]);
}catch(Throwable $e){
  pg_query($c, "ROLLBACK");
  echo json_encode(["ok"=>false,"error"=>$e->getMessage()]);
}
