<?php
// ui_config_banco_cobros.php
session_start();
require_once __DIR__.'/../../conexion/configv2.php'; // ajustá ruta si hace falta

// Guardar selección
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = isset($_POST['id_cuenta_bancaria']) ? (int)$_POST['id_cuenta_bancaria'] : null;
  if ($id) {
    // validar que exista y esté activa
    $chk = pg_query_params($conn, "
      SELECT 1
      FROM public.cuenta_bancaria
      WHERE id_cuenta_bancaria=$1 AND estado='Activa'
      LIMIT 1", [$id]);
    if ($chk && pg_num_rows($chk) > 0) {
      pg_query_params($conn, "
        INSERT INTO public.config_sistema(clave, valor, actualizado_en)
        VALUES ('banco_cobro_default', $1::text, now())
        ON CONFLICT (clave)
        DO UPDATE SET valor=$1::text, actualizado_en=now()", [strval($id)]);
      $msg = 'Banco por defecto actualizado.';
    } else {
      $msg = 'Cuenta bancaria inválida o inactiva.';
    }
  } else {
    // limpiar (dejar sin banco por defecto)
    pg_query_params($conn, "
      UPDATE public.config_sistema
      SET valor=NULL, actualizado_en=now()
      WHERE clave='banco_cobro_default'");
    $msg = 'Se eliminó el banco por defecto.';
  }
}

// Leer valor actual
$rDef = pg_query_params($conn, "
  SELECT valor FROM public.config_sistema
  WHERE clave='banco_cobro_default'
  LIMIT 1", []);
$defId = ($rDef && pg_num_rows($rDef)>0) ? (int)pg_fetch_result($rDef,0,0) : null;

// Traer cuentas ACTIVAS
$r = pg_query($conn, "
  SELECT id_cuenta_bancaria, banco, numero_cuenta, tipo, moneda
  FROM public.cuenta_bancaria
  WHERE estado='Activa'
  ORDER BY banco, numero_cuenta
");

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Configurar banco por defecto</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font-family:system-ui,Arial,sans-serif;margin:20px;color:#222}
  .card{border:1px solid #ddd;border-radius:8px;padding:12px;max-width:620px}
  label{font-weight:600}
  select,button{padding:8px}
  .btn{cursor:pointer;border:1px solid #ddd;background:#fff;border-radius:6px}
  .btn.primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
  .muted{color:#666}
  .row{display:flex;gap:10px;align-items:center}
  .ok{color:#256029}
</style>
</head>
<body>
  <h1>Banco por defecto para cobros</h1>

  <?php if (!empty($msg)): ?>
    <div class="muted ok"><?=htmlspecialchars($msg)?></div>
  <?php endif; ?>

  <div class="card">
    <form method="post">
      <label>Seleccioná la cuenta bancaria por defecto</label><br>
      <select name="id_cuenta_bancaria" style="min-width:360px">
        <option value="">— Sin banco por defecto —</option>
        <?php while($row = pg_fetch_assoc($r)): ?>
          <?php
            $id   = (int)$row['id_cuenta_bancaria'];
            $text = $row['banco']." · ".$row['numero_cuenta']." (".$row['tipo']." ".$row['moneda'].")";
          ?>
          <option value="<?=$id?>" <?=$defId===$id?'selected':''?>>
            <?=htmlspecialchars($text)?>
          </option>
        <?php endwhile; ?>
      </select>
      <div class="row" style="margin-top:12px">
        <button class="btn primary" type="submit">Guardar</button>
        <span class="muted">Sólo cuentas con <strong>estado = Activa</strong> aparecen aquí.</span>
      </div>
    </form>
  </div>
</body>
</html>
