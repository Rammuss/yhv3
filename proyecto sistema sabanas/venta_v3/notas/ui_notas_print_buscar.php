<?php
session_start();
if (empty($_SESSION['nombre_usuario'])) {
  header('Location: /TALLER DE ANALISIS Y PROGRAMACIÓN I/proyecto sistema sabanas/mantenimiento_seguridad/acceso/acceso.html');
  exit;
}
require_once __DIR__.'/../../conexion/configv2.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$clase  = isset($_GET['clase']) ? strtoupper(trim($_GET['clase'])) : '*'; // 'NC' | 'ND' | '*'
$q_cli  = isset($_GET['q_cli']) ? trim($_GET['q_cli']) : '';              // cliente o RUC/CI
$desde  = isset($_GET['desde']) ? trim($_GET['desde']) : '';
$hasta  = isset($_GET['hasta']) ? trim($_GET['hasta']) : '';
$num    = isset($_GET['numero']) ? trim($_GET['numero']) : '';
$limit  = 100;

// validación simple de fechas (YYYY-MM-DD)
$reDate = '/^\d{4}\-\d{2}\-\d{2}$/';
if ($desde!=='' && !preg_match($reDate,$desde)) $desde='';
if ($hasta!=='' && !preg_match($reDate,$hasta)) $hasta='';

$rows = [];
$params = [];
$conds = [];
$idx = 1;

/*
  Armamos dos consultas homogéneas (NC y ND) y hacemos UNION ALL según corresponda.
  Campos uniformes: doc_tipo, id_doc, numero_documento, fecha_emision, estado, id_cliente, cliente, ruc_ci, total_neto
*/

$baseFiltros = function($aliasCab, $aliasCli) use (&$conds, &$params, &$idx, $q_cli, $desde, $hasta, $num) {
  if ($q_cli !== '') {
    // Busca por nombre/apellido o por RUC/CI
    $conds[] = " ( {$aliasCli}.ruc_ci ILIKE $".$idx." OR CONCAT(COALESCE({$aliasCli}.nombre,''),' ',COALESCE({$aliasCli}.apellido,'')) ILIKE $".($idx+1)." ) ";
    $params[] = '%'.$q_cli.'%';
    $params[] = '%'.$q_cli.'%';
    $idx += 2;
  }
  if ($desde !== '') {
    $conds[] = " {$aliasCab}.fecha_emision >= $".$idx." ";
    $params[] = $desde;
    $idx++;
  }
  if ($hasta !== '') {
    $conds[] = " {$aliasCab}.fecha_emision <= $".$idx." ";
    $params[] = $hasta;
    $idx++;
  }
  if ($num !== '') {
    $conds[] = " {$aliasCab}.numero_documento ILIKE $".$idx." ";
    $params[] = '%'.$num.'%';
    $idx++;
  }
};

$conds = []; $params = []; $idx = 1;
$sqlParts = [];

if ($clase === 'NC' || $clase === '*'){
  $conds = []; $paramsNC = []; $idxNC = 1;
  // filtros para NC
  $tmpConds = []; $tmpParams = []; $tmpIdx = 1;
  $baseFiltrosNC = function($aCab,$aCli) use (&$tmpConds,&$tmpParams,&$tmpIdx,$q_cli,$desde,$hasta,$num){
    if ($q_cli !== '') {
      $tmpConds[] = " ( {$aCli}.ruc_ci ILIKE $".$tmpIdx." OR CONCAT(COALESCE({$aCli}.nombre,''),' ',COALESCE({$aCli}.apellido,'')) ILIKE $".($tmpIdx+1)." ) ";
      $tmpParams[] = '%'.$q_cli.'%';
      $tmpParams[] = '%'.$q_cli.'%';
      $tmpIdx += 2;
    }
    if ($desde !== '') { $tmpConds[] = " {$aCab}.fecha_emision >= $".$tmpIdx." "; $tmpParams[] = $desde; $tmpIdx++; }
    if ($hasta !== '') { $tmpConds[] = " {$aCab}.fecha_emision <= $".$tmpIdx." "; $tmpParams[] = $hasta; $tmpIdx++; }
    if ($num   !== '') { $tmpConds[] = " {$aCab}.numero_documento ILIKE $".$tmpIdx." "; $tmpParams[] = '%'.$num.'%'; $tmpIdx++; }
  };
  $baseFiltrosNC('n','c');
  $whereNC = $tmpConds ? ('WHERE '.implode(' AND ', $tmpConds)) : '';
  $sqlNC = "
    SELECT
      'NC'::text AS doc_tipo,
      n.id_nc AS id_doc,
      n.numero_documento,
      n.fecha_emision,
      n.estado,
      n.id_cliente,
      CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')) AS cliente,
      c.ruc_ci,
      COALESCE(n.total_neto,0) AS total_neto
    FROM public.nc_venta_cab n
    JOIN public.clientes c ON c.id_cliente = n.id_cliente
    $whereNC
  ";
  $sqlParts[] = ['sql'=>$sqlNC, 'params'=>$tmpParams];
}

if ($clase === 'ND' || $clase === '*'){
  $tmpConds = []; $tmpParams = []; $tmpIdx = 1;
  $baseFiltrosND = function($aCab,$aCli) use (&$tmpConds,&$tmpParams,&$tmpIdx,$q_cli,$desde,$hasta,$num){
    if ($q_cli !== '') {
      $tmpConds[] = " ( {$aCli}.ruc_ci ILIKE $".$tmpIdx." OR CONCAT(COALESCE({$aCli}.nombre,''),' ',COALESCE({$aCli}.apellido,'')) ILIKE $".($tmpIdx+1)." ) ";
      $tmpParams[] = '%'.$q_cli.'%';
      $tmpParams[] = '%'.$q_cli.'%';
      $tmpIdx += 2;
    }
    if ($desde !== '') { $tmpConds[] = " {$aCab}.fecha_emision >= $".$tmpIdx." "; $tmpParams[] = $desde; $tmpIdx++; }
    if ($hasta !== '') { $tmpConds[] = " {$aCab}.fecha_emision <= $".$tmpIdx." "; $tmpParams[] = $hasta; $tmpIdx++; }
    if ($num   !== '') { $tmpConds[] = " {$aCab}.numero_documento ILIKE $".$tmpIdx." "; $tmpParams[] = '%'.$num.'%'; $tmpIdx++; }
  };
  $baseFiltrosND('d','c');
  $whereND = $tmpConds ? ('WHERE '.implode(' AND ', $tmpConds)) : '';
  $sqlND = "
    SELECT
      'ND'::text AS doc_tipo,
      d.id_nd AS id_doc,
      d.numero_documento,
      d.fecha_emision,
      d.estado,
      d.id_cliente,
      CONCAT(COALESCE(c.nombre,''),' ',COALESCE(c.apellido,'')) AS cliente,
      c.ruc_ci,
      COALESCE(d.total_neto,0) AS total_neto
    FROM public.nd_venta_cab d
    JOIN public.clientes c ON c.id_cliente = d.id_cliente
    $whereND
  ";
  $sqlParts[] = ['sql'=>$sqlND, 'params'=>$tmpParams];
}

// Ejecutamos
if ($sqlParts){
  if (count($sqlParts) === 1){
    $sql = $sqlParts[0]['sql'] . " ORDER BY fecha_emision DESC, numero_documento DESC LIMIT $limit";
    $r = pg_query_params($conn, $sql, $sqlParts[0]['params']);
  } else {
    // UNION ALL
    $sql = "SELECT * FROM (".$sqlParts[0]['sql']." UNION ALL ".$sqlParts[1]['sql'].") x
            ORDER BY x.fecha_emision DESC, x.numero_documento DESC
            LIMIT $limit";
    $r = pg_query_params($conn, $sql, array_merge($sqlParts[0]['params'], $sqlParts[1]['params']));
  }
  if ($r) { while($x = pg_fetch_assoc($r)){ $rows[] = $x; } }
}

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Buscar Notas (NC/ND) por cliente y fecha</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body{font:15px/1.45 system-ui,-apple-system,Segoe UI,Roboto;margin:0;background:#f6f7fb;color:#111827}
  .wrap{max-width:980px;margin:24px auto;padding:0 16px}
  .card{background:#fff;border-radius:12px;box-shadow:0 8px 20px rgba(0,0,0,.08);padding:16px}
  label{display:block;margin:8px 0 4px}
  input,select{padding:8px;border:1px solid #e5e7eb;border-radius:10px}
  .row{display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end}
  .btn{padding:10px 14px;border-radius:10px;border:1px solid #e5e7eb;background:#2563eb;color:#fff;cursor:pointer}
  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{border:1px solid #e5e7eb;padding:8px;text-align:left}
  th{background:#eef2ff}
  .right{text-align:right}
  .muted{color:#6b7280}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2>Buscar notas por Cliente y Fecha</h2>
    <form method="get" class="row">
      <div>
        <label>Clase</label>
        <select name="clase">
          <option value="*" <?= $clase==='*'?'selected':'' ?>>NC y ND</option>
          <option value="NC" <?= $clase==='NC'?'selected':'' ?>>Solo NC</option>
          <option value="ND" <?= $clase==='ND'?'selected':'' ?>>Solo ND</option>
        </select>
      </div>
      <div style="flex:1;min-width:220px">
        <label>Cliente / RUC</label>
        <input type="text" name="q_cli" value="<?= e($q_cli) ?>" placeholder="Nombre y apellido o RUC/CI">
      </div>
      <div>
        <label>Desde</label>
        <input type="date" name="desde" value="<?= e($desde) ?>">
      </div>
      <div>
        <label>Hasta</label>
        <input type="date" name="hasta" value="<?= e($hasta) ?>">
      </div>
      <div>
        <label>Nº (opcional)</label>
        <input type="text" name="numero" value="<?= e($num) ?>" placeholder="EEE-PPP-0000001">
      </div>
      <div>
        <button class="btn" type="submit">Buscar</button>
      </div>
    </form>

    <?php if ($_GET): ?>
      <p class="muted" style="margin:8px 0 0">
        Resultados máx. <?= (int)$limit ?> registros · Ordenados por fecha y número
      </p>
      <table>
        <thead>
          <tr>
            <th>Clase</th>
            <th>Nº</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>RUC/CI</th>
            <th class="right">Total</th>
            <th>Estado</th>
            <th class="right">Imprimir</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows): foreach($rows as $r): ?>
            <tr>
              <td><?= e($r['doc_tipo']) ?></td>
              <td><?= e($r['numero_documento']) ?></td>
              <td><?= e($r['fecha_emision']) ?></td>
              <td><?= e($r['cliente']) ?></td>
              <td><?= e($r['ruc_ci']) ?></td>
              <td class="right"><?= number_format((float)$r['total_neto'], 0, ',', '.') ?></td>
              <td><?= e($r['estado']) ?></td>
              <td class="right">
                <a class="btn" style="background:#10b981;border-color:#10b981"
                   target="_blank"
                   href="nota_print.php?clase=<?= e($r['doc_tipo']) ?>&id=<?= (int)$r['id_doc'] ?>&auto=1">
                  Imprimir
                </a>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="8">Sin resultados</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
