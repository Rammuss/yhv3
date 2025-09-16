<?php
// banco/cuenta_cobros_default.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php';
header('Content-Type: application/json; charset=utf-8');

try{
  // Lee config
  $r = pg_query_params($conn, "SELECT valor FROM public.empresa_config WHERE clave='id_cuenta_bancaria_cobros'", []);
  if(!$r || pg_num_rows($r)===0){
    echo json_encode(['success'=>true,'hasDefault'=>false]); exit;
  }
  $id = (int)pg_fetch_result($r,0,0);

  // Valida cuenta activa
  $q = pg_query_params(
    $conn,
    "SELECT id_cuenta_bancaria, (banco||' · '||nro_cuenta) AS label
     FROM public.cuentas_bancarias
     WHERE id_cuenta_bancaria=$1 AND activa=TRUE",
    [$id]
  );
  if(!$q || pg_num_rows($q)===0){
    echo json_encode(['success'=>true,'hasDefault'=>false]); exit;
  }
  $row = pg_fetch_assoc($q);
  echo json_encode([
    'success'=>true,
    'hasDefault'=>true,
    'id_cuenta_bancaria'=>(int)$row['id_cuenta_bancaria'],
    'label'=>$row['label']
  ]);
}catch(Throwable $e){
  http_response_code(400);
  echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
