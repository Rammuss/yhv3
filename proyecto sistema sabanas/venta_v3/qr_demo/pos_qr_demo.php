<?php
// pos_qr_demo.php — Demo POS con QR (review en celular) y efectos en POS
// - El QR contiene un LINK (review_url). El monto NO va adentro del QR.
// - El endpoint create devuelve amount_raw (número crudo) para que el JS lo formatee sin errores.
// - Al confirmar/rechazar desde el celular, el POS lo detecta por polling y muestra overlay.

// =================== Config ===================
$LAN_FALLBACK = '192.168.0.7'; // <-- CAMBIÁ por tu IP LAN (ipconfig -> IPv4)

// =================== Errores ==================
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');
error_reporting(E_ALL);

// ================== Storage FS ================
function intents_dir(){ $d=__DIR__.'/intents'; if(!is_dir($d)) mkdir($d,0777,true); return $d; }
function intent_path($id){ return intents_dir().'/'.preg_replace('/[^a-z0-9]/i','',$id).'.json'; }
function save_intent($data){
  $ok=@file_put_contents(intent_path($data['id']), json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), LOCK_EX);
  if($ok===false) throw new RuntimeException('No pude guardar el intent.');
}
function load_intent($id){
  $p=intent_path($id); if(!is_file($p)) return null;
  $raw=@file_get_contents($p); if($raw===false) throw new RuntimeException('No pude leer el intent.');
  return json_decode($raw,true);
}
function update_intent($id,$patch){
  $cur=load_intent($id); if(!$cur) return false;
  foreach($patch as $k=>$v){ $cur[$k]=$v; } save_intent($cur); return true;
}

// ============ Base URL (usa IP si es localhost) ============
function base_from_request(): string {
  global $LAN_FALLBACK;
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
  $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
  if (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/i',$host)) {
    $port = '';
    if (strpos($host, ':')!==false){ [, $p] = explode(':',$host,2); $port = ($p!=='80'&&$p!==''?':'.$p:''); }
    else { $sp = $_SERVER['SERVER_PORT'] ?? '80'; $port = ($sp!=='80'?':'.$sp:''); }
    $host = $LAN_FALLBACK.$port;
  }
  return $scheme.'://'.$host.($dir?$dir:'');
}

// ================ Router simple =================
$action = $_GET['action'] ?? $_POST['action'] ?? '';
if (!$action && isset($_GET['a'])) {
  $map = ['v'=>'review','c'=>'confirm','r'=>'reject'];
  $action = $map[$_GET['a']] ?? '';
}

// ================ CREATE ========================
if ($action==='create') {
  header('Content-Type: application/json; charset=utf-8');
  try{
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    if ($amount<=0) throw new InvalidArgumentException('Monto inválido');

    $id     = bin2hex(random_bytes(4));  // corto = URL corta = QR fácil
    $token  = bin2hex(random_bytes(6));
    $ttl    = 5*60;                      // 5 min
    $expires= time()+$ttl;

    save_intent(['id'=>$id,'amount'=>$amount,'status'=>'pending','token'=>$token,'expires'=>$expires,'created'=>time()]);

    $base = base_from_request();
    $self = basename($_SERVER['SCRIPT_NAME']);

    $reviewUrl  = "{$base}/{$self}?a=v&i={$id}&k={$token}";
    $confirmUrl = "{$base}/{$self}?a=c&i={$id}&k={$token}";
    $rejectUrl  = "{$base}/{$self}?a=r&i={$id}&k={$token}";
    $rawUrl     = $confirmUrl."&raw=1";

    echo json_encode([
      'id'           => $id,
      'amount_raw'   => $amount,                           // <- número puro (para JS)
      'amount_str'   => number_format($amount,0,'','.'),   // solo por si querés texto
      'expires_in'   => $ttl,
      'review_url'   => $reviewUrl,
      'confirm_url'  => $confirmUrl,
      'reject_url'   => $rejectUrl,
      'raw_url'      => $rawUrl
    ]);
  }catch(Throwable $e){
    error_log('[create] '.$e->getMessage());
    http_response_code(500); echo json_encode(['error'=>$e->getMessage()]);
  }
  exit;
}

// ================ STATUS ========================
if ($action==='status') {
  header('Content-Type: application/json; charset=utf-8');
  try{
    $id = $_GET['id'] ?? ($_GET['i'] ?? '');
    $it = load_intent($id);
    if(!$it){ http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    if ($it['status']==='pending' && time()>$it['expires']) { $it['status']='expired'; save_intent($it); }
    echo json_encode(['status'=>$it['status']]);
  }catch(Throwable $e){
    error_log('[status] '.$e->getMessage());
    http_response_code(500); echo json_encode(['error'=>$e->getMessage()]);
  }
  exit;
}

// =============== REVIEW (móvil) =================
if ($action==='review') {
  $id    = $_GET['id'] ?? ($_GET['i'] ?? '');
  $token = $_GET['token'] ?? ($_GET['k'] ?? '');
  $it = load_intent($id);
  $ok = ($it && isset($it['token']) && hash_equals((string)$it['token'], (string)$token) && time() <= (int)$it['expires']);
  $amountFmt = $ok ? number_format($it['amount'],0,'','.') : '—';

  $base = base_from_request();
  $self = basename($_SERVER['SCRIPT_NAME']);
  $confirm = "{$base}/{$self}?a=c&i=".urlencode($id)."&k=".urlencode($token);
  $reject  = "{$base}/{$self}?a=r&i=".urlencode($id)."&k=".urlencode($token);
  http_response_code($ok?200:400);
  ?>
  <!doctype html><meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Revisar pago</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;margin:0;background:#0b1020;color:#e7e7ef}
    .wrap{max-width:480px;margin:32px auto;padding:20px}
    .card{background:#151b33;border:1px solid #30395c;border-radius:16px;padding:20px}
    h1{margin:0 0 8px 0;font-size:22px}
    .amt{font-size:34px;font-weight:800;margin:8px 0 4px 0}
    .muted{opacity:.85}
    .row{display:flex;gap:12px;margin-top:16px}
    .btn{flex:1;text-align:center;padding:14px 16px;border-radius:12px;font-weight:700;text-decoration:none;display:block}
    .pay{background:#22c55e;color:#04170b}
    .cancel{background:#ef4444;color:#2a0b0b}
    .warn{color:#ffd27a}
  </style>
  <div class="wrap">
    <div class="card">
      <h1>Revisá y confirmá</h1>
      <div class="muted">Intent: <?=htmlspecialchars($id ?: '—')?></div>
      <div class="amt">Gs. <?=$amountFmt?></div>
      <?php if(!$ok): ?>
        <p class="warn">Este link no es válido o expiró. Volvé a la caja y generá un nuevo QR.</p>
      <?php else: ?>
        <div class="row">
          <a class="btn pay" href="<?=$confirm?>">Pagar</a>
          <a class="btn cancel" href="<?=$reject?>">Cancelar</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php exit;
}

// ============ CONFIRM / REJECT (móvil) =========
if ($action==='confirm' || $action==='reject') {
  $asRaw = isset($_GET['raw']);
  try{
    $id    = $_GET['id']    ?? ($_GET['i'] ?? '');
    $token = $_GET['token'] ?? ($_GET['k'] ?? '');
    $it  = load_intent($id);
    $msg = ''; $cls = 'bad'; $http = 200;

    if (!$it) { $http=404; $msg='❌ Intent no encontrado'; }
    elseif (!isset($it['token']) || !hash_equals((string)$it['token'], (string)$token)) { $http=401; $msg='❌ Token inválido'; }
    elseif (time() > (int)$it['expires']) { update_intent($id,['status'=>'expired']); $msg='⌛ Este intento expiró.'; }
    else {
      $new = ($action==='confirm') ? 'confirmed' : 'rejected';
      if (($it['status'] ?? 'pending') !== 'confirmed') update_intent($id, ['status'=>$new]);
      $msg = ($new==='confirmed') ? '✅ Pago simulado confirmado' : '❌ Pago simulado rechazado';
      $cls = ($new==='confirmed') ? 'ok' : 'bad';
    }
    http_response_code($http);
    if ($asRaw) { header('Content-Type: text/plain; charset=utf-8'); echo $msg."\nIntent: ".$id."\n"; exit; }
  }catch(Throwable $e){
    error_log('[confirm/reject] '.$e->getMessage());
    http_response_code(500); $msg = '❌ Error interno: '.$e->getMessage(); $cls='bad';
  }
  ?>
  <!doctype html><meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Resultado</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial;margin:0;background:#0b1020;color:#e7e7ef}
    .wrap{max-width:480px;margin:32px auto;padding:20px}
    .card{background:#151b33;border:1px solid #30395c;border-radius:16px;padding:20px}
    .ok{color:#22c55e} .bad{color:#ef4444}
    a{color:#9ab7ff}
  </style>
  <div class="wrap">
    <div class="card">
      <h2 class="<?=$cls?>"><?=$msg?></h2>
      <p><b>Intent:</b> <?=htmlspecialchars($_GET['i'] ?? ($_GET['id'] ?? ''))?></p>
      <p><a href="<?=htmlspecialchars(basename($_SERVER['SCRIPT_NAME']))?>">Volver</a></p>
    </div>
  </div>
  <?php exit;
}
?>
<!doctype html>
<meta charset="utf-8">
<title>POS – Demo QR (Review + Efectos)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,"Helvetica Neue",Arial;margin:0;background:#0b1020;color:#e7e7ef}
.container{max-width:880px;margin:40px auto;padding:24px;background:#151b33;border-radius:20px;box-shadow:0 10px 35px rgba(0,0,0,.35)}
h1{margin-top:0}
label,input,button{font-size:16px}
input[type=number]{padding:10px;border-radius:12px;border:1px solid #30395c;background:#0f1430;color:#fff}
button{padding:10px 16px;border:0;border-radius:12px;background:#4b6bff;color:#fff;cursor:pointer;margin-right:8px}
button:disabled{opacity:.6;cursor:not-allowed}
.grid{display:grid;grid-template-columns:320px 1fr;gap:24px;margin-top:24px}
.panel{padding:12px 14px;border-radius:10px;background:#0f1430;border:1px solid #30395c}
.small{opacity:.85;font-size:13px}
.qr{background:#fff;border-radius:12px;padding:8px;display:flex;align-items:center;justify-content:center;min-height:300px}
.urlbox{word-break:break-all;background:#0b1020;padding:8px;border-radius:8px;border:1px solid #30395c}
a{color:#9ab7ff}

/* Estado POS */
.status-ok { color:#22c55e; }
.status-bad{ color:#ef4444; }

/* Overlay de resultado */
#overlayResult{position:fixed; inset:0; display:none; align-items:center; justify-content:center; background:rgba(5,8,16,.55); z-index:9999; backdrop-filter: blur(2px);}
#overlayResult.show{ display:flex; }
.overlay-card{ background:#151b33; border:1px solid #2a3356; color:#e7e7ef; border-radius:20px; padding:28px 32px; text-align:center; box-shadow:0 10px 35px rgba(0,0,0,.4); animation:popIn .35s ease-out both;}
.overlay-card h2{ margin:0 0 6px 0; font-size:28px }
.overlay-card .sub{ opacity:.85; font-size:14px }
.overlay-card.ok h2{ color:#22c55e; }
.overlay-card.bad h2{ color:#ef4444; }

/* Confetti */
.confetti{ position:fixed; left:0; top:0; width:100%; height:0; pointer-events:none; z-index:10000; }
.confetti i{ position:absolute; width:10px; height:14px; opacity:.9; transform:translateY(-10vh) rotate(0deg); animation: fall 1200ms linear forwards; }
@keyframes fall{ to{ transform:translateY(110vh) rotate(360deg); opacity:1; } }

/* Shake rechazo */
.shake{ animation:shake .6s ease-in-out 1; }
@keyframes shake{ 10%, 90%{ transform:translateX(-2px) } 20%, 80%{ transform:translateX(4px) } 30%, 50%, 70%{ transform:translateX(-6px) } 40%, 60%{ transform:translateX(6px) } }
@keyframes popIn{ 0%{ transform:scale(.9); opacity:0 } 100%{ transform:scale(1); opacity:1 } }

/* === Monto grande en POS === */
.big-amount{ font-size:36px !important; font-weight:800 !important; letter-spacing:.3px; line-height:1.1; }
</style>

<div class="container">
  <h1>POS – Demo QR (con pantalla de review y efectos)</h1>
  <p class="small">El QR abre en el celular una pantalla con <b>monto</b> y botones <b>Pagar/Cancelar</b>. En esta pantalla verás un <b>overlay animado</b> cuando se confirme o rechace.</p>

  <div>
    <label>Monto (Gs.): </label>
    <input id="amount" type="number" min="1000" step="500" value="125000">
    <button id="btn">Generar</button>
  </div>

  <div id="result" style="display:none" class="grid">
    <div>
      <div class="qr"><div id="qr"></div></div>
      <div class="panel" style="margin-top:12px">
        <div class="small">Si algo no renderiza, usá el link <b>Raw</b> (texto plano de confirmación).</div>
      </div>
    </div>
    <div>
      <div class="panel">
        <div><b>Intent:</b> <span id="intent">—</span></div>
        <div><b>Monto:</b> <span class="big-amount">Gs. <span id="amt">—</span></span></div>
        <div><b>Estado:</b> <span id="st">pending</span></div>
        <div><b>Expira en:</b> <span id="t">—</span></div>
      </div>

      <div class="panel" style="margin-top:12px">
        <div style="margin-bottom:6px"><b>Links para copiar/pegar</b></div>
        <div class="small">Pantalla de revisión (QR):</div>
        <div class="urlbox"><a id="revLink" target="_blank">—</a></div>

        <div class="small" style="margin-top:8px">Confirmación directa:</div>
        <div class="urlbox"><a id="confLink" target="_blank">—</a></div>

        <div class="small" style="margin-top:8px">Raw (texto crudo confirm):</div>
        <div class="urlbox"><a id="rawLink" target="_blank">—</a></div>

        <div class="small" style="margin-top:8px">Rechazo:</div>
        <div class="urlbox"><a id="rejLink" target="_blank">—</a></div>
      </div>
    </div>
  </div>
</div>

<!-- Overlay de resultado (POS) -->
<div id="overlayResult">
  <div id="overlayCard" class="overlay-card">
    <h2 id="overlayTitle">Resultado</h2>
    <div class="sub" id="overlaySub">—</div>
  </div>
</div>
<div class="confetti" id="confetti"></div>

<!-- QR (lib local vía CDN) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
// ===== Formato consistente de Gs. (evita 1→1000) =====
function formatGs(n){ return Number(n||0).toLocaleString('es-PY',{maximumFractionDigits:0}); }

const $=q=>document.querySelector(q);
const btn=$('#btn'), amount=$('#amount');
let intentId=null, deadline=0, pollInt=null, firedEffect=false;

btn.onclick = async ()=>{
  btn.disabled = true;
  try{
    const fd = new FormData();
    fd.append('action','create');
    fd.append('amount', amount.value);
    const r = await fetch('<?=htmlspecialchars(basename($_SERVER["SCRIPT_NAME"]))?>', {method:'POST', body:fd});
    const j = await r.json();
    if(j.error) throw new Error(j.error);

    intentId = j.id;
    $('#result').style.display='grid';
    $('#intent').textContent = j.id;
    // *** MOSTRAR SIEMPRE EL CRUDO ***
    $('#amt').textContent = formatGs(j.amount_raw);
    $('#st').textContent = 'pending';
    firedEffect = false;
    deadline = Date.now() + (Number(j.expires_in)*1000);

    // QR → review_url
    const qrBox = document.getElementById('qr');
    qrBox.innerHTML = '';
    try {
      new QRCode(qrBox, { text: j.review_url, width: 300, height: 300, correctLevel: QRCode.CorrectLevel.L });
    } catch (e) {
      const img = new Image(); img.width=300; img.height=300; img.alt='QR';
      img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' + encodeURIComponent(j.review_url);
      qrBox.appendChild(img);
    }

    // Links
    $('#revLink').textContent  = j.review_url;  $('#revLink').href  = j.review_url;
    $('#confLink').textContent = j.confirm_url; $('#confLink').href = j.confirm_url;
    $('#rawLink').textContent  = j.raw_url;     $('#rawLink').href  = j.raw_url;
    $('#rejLink').textContent  = j.reject_url;  $('#rejLink').href  = j.reject_url;

    if(pollInt) clearInterval(pollInt);
    pollInt = setInterval(pollStatus, 1200);
    tick();
  }catch(e){ alert('Error: '+e.message); }
  finally{ btn.disabled = false; }
};

function tick(){
  const s = Math.max(0, Math.floor((deadline - Date.now())/1000));
  document.getElementById('t').textContent = s+'s';
  if(s>0) requestAnimationFrame(tick); else document.getElementById('st').textContent = 'expired';
}

async function pollStatus(){
  if(!intentId) return;
  const r = await fetch('<?=htmlspecialchars(basename($_SERVER["SCRIPT_NAME"]))?>?action=status&i='+encodeURIComponent(intentId), {cache:'no-store'});
  const j = await r.json();
  if(j.error){ console.error('status error:', j.error); return; }

  document.getElementById('st').textContent = j.status;
  paintStatusColor(j.status);

  if(!firedEffect && (j.status==='confirmed' || j.status==='rejected')){
    firedEffect = true;
    const amountText = document.getElementById('amt').textContent.trim();
    const idText = document.getElementById('intent').textContent.trim();
    if(j.status==='confirmed') showOverlayOk(amountText, idText);
    else                       showOverlayReject(amountText, idText);
  }

  if(j.status==='confirmed' || j.status==='rejected' || j.status==='expired'){
    clearInterval(pollInt);
  }
}

/* ===== Efectos POS ===== */
function playTone(type='ok'){
  try{
    const ac = new (window.AudioContext||window.webkitAudioContext)();
    const o = ac.createOscillator();
    const g = ac.createGain();
    o.connect(g); g.connect(ac.destination);
    if(type==='ok'){
      o.type='sine'; o.frequency.value=880;
      g.gain.setValueAtTime(0.0001, ac.currentTime);
      g.gain.exponentialRampToValueAtTime(0.08, ac.currentTime+0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime+0.25);
      o.start(); o.stop(ac.currentTime+0.28);
    }else{
      o.type='square'; o.frequency.value=220;
      g.gain.setValueAtTime(0.0001, ac.currentTime);
      g.gain.exponentialRampToValueAtTime(0.05, ac.currentTime+0.02);
      g.gain.exponentialRampToValueAtTime(0.0001, ac.currentTime+0.35);
      o.start(); o.stop(ac.currentTime+0.38);
    }
  }catch(e){}
}

function confettiBurst(){
  const wrap = document.getElementById('confetti'); if(!wrap) return;
  wrap.innerHTML = '';
  const colors = ['#ff7676','#ffd166','#4ade80','#60a5fa','#c084fc','#f472b6'];
  const n = 80, W = window.innerWidth;
  for(let i=0;i<n;i++){
    const el = document.createElement('i');
    el.style.left = (Math.random()*W) + 'px';
    el.style.background = colors[(Math.random()*colors.length)|0];
    el.style.transform += ' rotate('+(Math.random()*180)+'deg)';
    el.style.animationDelay = (Math.random()*0.5)+'s';
    wrap.appendChild(el);
  }
  setTimeout(()=>{ wrap.innerHTML=''; }, 2000);
}

function showOverlayOk(amountText, intentId){
  const ov = document.getElementById('overlayResult');
  const card = document.getElementById('overlayCard');
  document.getElementById('overlayTitle').textContent = '¡PAGADO!';
  document.getElementById('overlaySub').textContent   = `Gs. ${amountText} · Intent ${intentId}`;
  card.classList.remove('bad','shake'); card.classList.add('ok');
  ov.classList.add('show');
  confettiBurst(); playTone('ok');
  if(navigator.vibrate) navigator.vibrate(40);
  setTimeout(()=>ov.classList.remove('show'), 1800);
}

function showOverlayReject(amountText, intentId){
  const ov = document.getElementById('overlayResult');
  const card = document.getElementById('overlayCard');
  document.getElementById('overlayTitle').textContent = 'RECHAZADO';
  document.getElementById('overlaySub').textContent   = `Gs. ${amountText} · Intent ${intentId}`;
  card.classList.remove('ok'); card.classList.add('bad','shake');
  ov.classList.add('show');
  playTone('bad');
  if(navigator.vibrate) navigator.vibrate([30,40,30]);
  setTimeout(()=>ov.classList.remove('show'), 1600);
}

function paintStatusColor(status){
  const st = document.getElementById('st');
  st.classList.remove('status-ok','status-bad');
  if(status==='confirmed') st.classList.add('status-ok');
  if(status==='rejected')  st.classList.add('status-bad');
}
</script>
