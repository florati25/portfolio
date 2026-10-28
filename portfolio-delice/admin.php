<?php
require __DIR__ . '/lib.php';

session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

if (!is_dir(UPLOADS_DIR)) @mkdir(UPLOADS_DIR, 0755, true);
$ht = UPLOADS_DIR . '/.htaccess';
if (is_dir(UPLOADS_DIR) && !is_file($ht)) @file_put_contents($ht, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|php[0-9])$\">\n  Require all denied\n</FilesMatch>\n");

$data = normalize(load_data());
if ($data['password'] === '') { $data['password'] = password_hash('Delice', PASSWORD_DEFAULT); save_data($data); } // mot de passe initial : Delice
ui_init($data);
$photo = file_ok($data['profile_photo']) ? $data['profile_photo'] : '';

function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function jexit(array $a): void { header('Content-Type: application/json'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function mask_email(string $e): string { $p = explode('@', $e, 2); return count($p) !== 2 ? $e : mb_substr($p[0], 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($p[0]) - 1))) . '@' . $p[1]; }

$CSRF = csrf();
$message = $_SESSION['message'] ?? ''; $error = $_SESSION['error'] ?? '';
unset($_SESSION['message'], $_SESSION['error']);
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

/* ===== AUTHENTIFICATION : mot de passe + code OTP par e-mail (OTP ignoré tant qu'aucun e-mail n'est renseigné) ===== */
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'verify_password') {
        $tries = (int)($_SESSION['pw_tries'] ?? 0);
        if ($tries >= 8 && time() < (int)($_SESSION['pw_lock'] ?? 0)) jexit(['success' => false, 'message' => 'Trop de tentatives. Réessayez dans quelques minutes.']);
        if (password_verify((string)($_POST['password'] ?? ''), $data['password'])) {
            $toEmail = trim((string)($data['contact']['emails'][0] ?? ''));
            if ($toEmail === '') { // première configuration : pas encore d'e-mail pour recevoir le code
                session_regenerate_id(true); $_SESSION['authenticated'] = true; $_SESSION['pw_tries'] = 0;
                jexit(['success' => true, 'direct' => true]);
            }
            if (!empty($_POST['resend']) && time() - (int)($_SESSION['otp_sent_at'] ?? 0) < 30) jexit(['success' => false, 'message' => 'Patientez quelques secondes avant de demander un nouveau code.']);
            $_SESSION['pw_tries'] = 0; $_SESSION['otp_tries'] = 0;
            $otp = sprintf('%04d', random_int(0, 9999));
            $host = preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
            $subject = '=?UTF-8?B?' . base64_encode("Portfolio - Vérification d'accès administrateur") . '?=';
            $body = "Bonjour,\n\nVotre code d'accès temporaire est : $otp\n\nIl expire dans 5 minutes. Si vous n'êtes pas à l'origine de cette demande, changez votre mot de passe.";
            $headers = "From: security@$host\r\nReply-To: no-reply@$host\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8";
            if (!@mail($toEmail, $subject, $body, $headers)) jexit(['success' => false, 'message' => "Impossible d'envoyer l'e-mail de vérification. Contactez l'hébergeur."]);
            $_SESSION['auth_password_verified'] = true; $_SESSION['admin_otp_code'] = $otp; $_SESSION['otp_expiry_time'] = time() + 300; $_SESSION['otp_sent_at'] = time();
            jexit(['success' => true, 'email' => mask_email($toEmail)]);
        }
        $_SESSION['pw_tries'] = $tries + 1;
        if ($tries + 1 >= 8) $_SESSION['pw_lock'] = time() + 300;
        usleep(700000);
        jexit(['success' => false, 'message' => 'Mot de passe incorrect.']);
    }
    if ($action === 'verify_otp') {
        if (empty($_SESSION['auth_password_verified'])) jexit(['success' => false, 'message' => 'Session invalide. Veuillez recommencer.']);
        $_SESSION['otp_tries'] = (int)($_SESSION['otp_tries'] ?? 0) + 1;
        if ($_SESSION['otp_tries'] > 5) { unset($_SESSION['auth_password_verified'], $_SESSION['admin_otp_code']); jexit(['success' => false, 'message' => "Trop d'essais. Recommencez la connexion."]); }
        if (isset($_SESSION['admin_otp_code']) && time() < (int)($_SESSION['otp_expiry_time'] ?? 0) && hash_equals($_SESSION['admin_otp_code'], (string)($_POST['otp'] ?? ''))) {
            session_regenerate_id(true); $_SESSION['authenticated'] = true;
            unset($_SESSION['admin_otp_code'], $_SESSION['otp_expiry_time'], $_SESSION['auth_password_verified'], $_SESSION['otp_tries'], $_SESSION['otp_sent_at']);
            jexit(['success' => true]);
        }
        jexit(['success' => false, 'message' => 'Le code est invalide ou expiré.']);
    }
    jexit(['success' => false]);
}

if (empty($_SESSION['authenticated'])) { ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow">
<?php if ($photo): ?><link rel="icon" type="image/png" href="<?= s($photo) ?>"><?php endif; ?>
<title>Admin · Console sécurisée</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}:root{--b:#3B82F6;--b2:#38BDF8;--red:#EF4444}
body{background:#000;color:#F8FAFC;font-family:Inter,system-ui,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;overflow:hidden;transition:background .5s}
.portal-wrapper{display:flex;align-items:center;justify-content:center;gap:80px;width:100%;max-width:900px;padding:20px}
.lamp-container{position:relative;width:180px;height:320px;display:flex;flex-direction:column;align-items:center}
.lamp-fixture{width:140px;height:140px;border-radius:50%;border:2px dashed rgba(59,130,246,.4);background:#0A0A0A;display:flex;align-items:center;justify-content:center;transition:all .5s}
.lamp-bulb{width:44px;height:44px;border-radius:50%;background:#121212;border:1px solid rgba(59,130,246,.4);transition:all .5s}
.lamp-cord{position:absolute;width:2px;background:#64748B;left:50%;transform:translateX(-50%);top:140px;height:80px;cursor:grab;z-index:10}
.lamp-cord::before{content:'';position:absolute;inset:0 -14px}
.lamp-cord-knob{position:absolute;width:14px;height:14px;border-radius:50%;background:var(--b);bottom:-7px;left:50%;transform:translateX(-50%);border:2px solid #000;box-shadow:0 0 8px rgba(59,130,246,.6)}
body.light-on{background:radial-gradient(circle at 25% 50%,#0f1b33 0%,#000 75%)}
body.light-on .lamp-fixture{border-style:solid;border-color:var(--b);box-shadow:0 0 50px rgba(59,130,246,.35)}
body.light-on .lamp-bulb{background:var(--b);border-color:#fff;box-shadow:0 0 44px var(--b)}
.hint{position:absolute;top:290px;font:11px 'JetBrains Mono',monospace;color:#64748B;text-align:center;width:200px;letter-spacing:.08em}body.light-on .hint{display:none}
.login-card,.otp-container{background:#0A0A0A;padding:38px;width:360px;position:relative;box-shadow:0 20px 50px rgba(0,0,0,.7);border-radius:12px;display:none;border:1px solid rgba(59,130,246,.4)}
.login-card{opacity:0;transform:translateX(24px);transition:all .6s cubic-bezier(.16,1,.3,1)}body.light-on .login-card{display:block;opacity:1;transform:none}
h2{font:700 20px 'JetBrains Mono',monospace;color:var(--b);margin:0 0 6px}
.sub-title{font:10px 'JetBrains Mono',monospace;color:#64748B;margin-bottom:26px;display:block;text-transform:uppercase;letter-spacing:.15em}
label{font:10px 'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.1em;color:var(--b);display:block;margin-bottom:8px}
input[type=password]{width:100%;padding:13px 14px;background:#121212;border:1px solid rgba(59,130,246,.3);border-radius:8px;color:#fff;margin-bottom:20px;outline:none;font:14px inherit}
input[type=password]:focus{border-color:var(--b);box-shadow:0 0 0 3px rgba(59,130,246,.15)}
.btn-submit{width:100%;padding:13px;background:var(--b);border:0;border-radius:8px;color:#fff;font:600 11px 'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.1em;cursor:pointer;transition:all .2s}
.btn-submit:hover{box-shadow:0 0 15px rgba(59,130,246,.45)}.btn-submit:disabled{opacity:.6;cursor:wait}
.error-msg{color:var(--red);font-size:12px;margin-bottom:15px;border-left:2px solid var(--red);padding:2px 0 2px 10px;display:none}.error-msg.ok{color:var(--b2);border-left-color:var(--b2)}
.otp-header{text-align:center;margin-bottom:18px}.otp-desc{font-size:12.5px;color:#94A3B8;line-height:1.65;text-align:center;margin:0 0 22px}.otp-desc b{color:#fff}
.otp-inputs{display:flex;justify-content:space-between;gap:12px;margin-bottom:20px}
.otp-box{width:64px;height:64px;background:#121212;border:1px solid rgba(59,130,246,.3);color:#fff;font:700 24px 'JetBrains Mono',monospace;text-align:center;outline:none;border-radius:8px}
.otp-box:focus{border-color:var(--b);box-shadow:0 0 15px rgba(59,130,246,.3)}
.otp-loading{display:none;text-align:center;font:11px 'JetBrains Mono',monospace;color:var(--b);margin-bottom:10px}
.otp-resend{text-align:center;font:11px 'JetBrains Mono',monospace;color:#64748B}.otp-resend button{background:none;border:0;color:var(--b);cursor:pointer;font:600 11px inherit}.otp-resend button:disabled{color:#64748B;cursor:default}
.shake{animation:sk .35s}@keyframes sk{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-8px)}40%,80%{transform:translateX(8px)}}
@media(max-width:768px){.portal-wrapper{flex-direction:column;gap:30px}.lamp-container{transform:scale(.8);height:250px}.hint{top:230px}.login-card,.otp-container{width:min(360px,92vw);padding:28px}.otp-box{width:54px;height:58px}}
</style>
</head>
<body>
<div class="portal-wrapper">
  <div class="lamp-container"><div class="lamp-fixture"><div class="lamp-bulb"></div></div><div class="lamp-cord" id="lampCord"><div class="lamp-cord-knob"></div></div><div class="hint">↓ Tirez la cordelette</div></div>
  <div class="login-card" id="passwordCard">
    <h2>Secure Auth</h2><span class="sub-title">// Console d'administration</span>
    <div class="error-msg" id="passwordError"></div>
    <form id="passwordForm"><label for="passwordInput">Mot de passe</label><input type="password" id="passwordInput" placeholder="••••••••" required autocomplete="current-password"><button type="submit" class="btn-submit" id="pwBtn">Authentification</button></form>
  </div>
  <div class="otp-container" id="otpCard">
    <div class="otp-header"><h2>Double facteur (OTP)</h2></div>
    <p class="otp-desc">Saisissez le code à 4 chiffres envoyé par e-mail à <b id="otpTargetEmail"></b>. Valable 5 minutes.</p>
    <div class="error-msg" id="otpError" style="text-align:center;border-left:0;padding-left:0"></div>
    <div class="otp-inputs"><?php for ($i = 1; $i <= 4; $i++): ?><input type="text" class="otp-box" maxlength="1" inputmode="numeric" autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>" aria-label="Chiffre <?= $i ?>"><?php endfor; ?></div>
    <div class="otp-loading" id="otpLoader">Vérification du protocole…</div>
    <div class="otp-resend">Pas reçu ? <button type="button" id="resendBtn" onclick="resendOTP()">Renvoyer</button></div>
  </div>
</div>
<script>
const cord=document.getElementById('lampCord');let drag=false,y0=0;const pt=e=>e.touches?e.touches[0].clientY:e.clientY;
cord.addEventListener('mousedown',e=>{drag=true;y0=pt(e);cord.style.transition='none';document.body.style.userSelect='none'});
cord.addEventListener('touchstart',e=>{drag=true;y0=pt(e);cord.style.transition='none'},{passive:true});
const mv=e=>{if(!drag)return;const d=pt(e)-y0;if(d>0&&d<80)cord.style.height=(80+d)+'px'};
addEventListener('mousemove',mv);addEventListener('touchmove',mv,{passive:true});
function end(){if(!drag)return;drag=false;document.body.style.userSelect='';const h=parseInt(cord.style.height||80,10);cord.style.transition='height .3s';cord.style.height='80px';if(h>=115)light()}
function light(){document.body.classList.toggle('light-on');if(document.body.classList.contains('light-on'))setTimeout(()=>document.getElementById('passwordInput').focus(),400)}
addEventListener('mouseup',end);addEventListener('touchend',end);cord.addEventListener('dblclick',light);

const pwErr=document.getElementById('passwordError'),boxes=[...document.querySelectorAll('.otp-box')],oErr=document.getElementById('otpError'),oLoad=document.getElementById('otpLoader'),rBtn=document.getElementById('resendBtn');
const shake=id=>{const e=document.getElementById(id);e.classList.add('shake');setTimeout(()=>e.classList.remove('shake'),400)};
function sendPassword(cb,resend){const fd=new FormData();fd.append('password',document.getElementById('passwordInput').value);if(resend)fd.append('resend','1');
 return fetch('admin.php?action=verify_password',{method:'POST',body:fd}).then(r=>r.json()).then(cb).catch(()=>{pwErr.textContent='Erreur de connexion sécurisée.';pwErr.style.display='block'})}
function cooldown(s){rBtn.disabled=true;const t=setInterval(()=>{rBtn.textContent=s>0?'Renvoyer ('+s+' s)':'Renvoyer';if(s--<=0){clearInterval(t);rBtn.disabled=false}},1000)}
document.getElementById('passwordForm').addEventListener('submit',e=>{e.preventDefault();pwErr.style.display='none';const b=document.getElementById('pwBtn');b.disabled=true;
 sendPassword(d=>{b.disabled=false;
  if(d.success&&d.direct){location.href='admin.php';return}
  if(d.success){document.getElementById('otpTargetEmail').textContent=d.email;document.getElementById('passwordCard').style.display='none';document.getElementById('otpCard').style.display='block';boxes[0].focus();cooldown(30)}
  else{pwErr.textContent=d.message;pwErr.style.display='block';shake('passwordCard')}}).finally(()=>b.disabled=false)});
boxes.forEach((b,i)=>{
 b.addEventListener('input',()=>{b.value=b.value.replace(/\D/g,'').slice(-1);if(b.value){if(i<3)boxes[i+1].focus();else if(boxes.every(x=>x.value))verifyOTP()}});
 b.addEventListener('keydown',e=>{if(e.key==='Backspace'&&!b.value&&i>0){boxes[i-1].focus();boxes[i-1].value=''}});
 b.addEventListener('paste',e=>{const t=(e.clipboardData.getData('text')||'').replace(/\D/g,'').slice(0,4);if(t.length===4){e.preventDefault();boxes.forEach((x,k)=>x.value=t[k]);boxes[3].focus();verifyOTP()}})});
function verifyOTP(){oErr.style.display='none';oLoad.style.display='block';const fd=new FormData();fd.append('otp',boxes.map(b=>b.value).join(''));
 fetch('admin.php?action=verify_otp',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{oLoad.style.display='none';
  if(d.success){document.getElementById('otpCard').innerHTML='<div style="text-align:center;padding:20px 0"><h2>Accès autorisé</h2><p style="font-size:12px;color:#94A3B8">Chargement…</p></div>';setTimeout(()=>location.href='admin.php',900)}
  else{oErr.classList.remove('ok');oErr.textContent=d.message;oErr.style.display='block';shake('otpCard');boxes.forEach(b=>b.value='');boxes[0].focus()}
 }).catch(()=>{oLoad.style.display='none';oErr.textContent='Erreur de vérification.';oErr.style.display='block'})}
function resendOTP(){oErr.style.display='none';sendPassword(d=>{oErr.classList.toggle('ok',!!d.success);oErr.textContent=d.success?'Nouveau code envoyé par e-mail.':d.message;oErr.style.display='block';if(d.success){boxes.forEach(b=>b.value='');boxes[0].focus();cooldown(30)}},true)}
</script>
</body>
</html>
<?php exit; }

/* ===================== OUTILS ===================== */
function &pref(array &$a, array $p) { $r = &$a; foreach ($p as $k) { if (!isset($r[$k]) || !is_array($r[$k])) $r[$k] = []; $r = &$r[$k]; } return $r; }
function pget(array $a, array $p): array { foreach ($p as $k) { $a = $a[$k] ?? []; if (!is_array($a)) return []; } return $a; }
function go(string $tab): void { header('Location: admin.php#p-' . $tab); exit; }
function up_exts(string $k): array { return ['video' => ['mp4', 'webm', 'ogg'], 'media' => ['mp4', 'webm', 'ogg', 'jpg', 'jpeg', 'png', 'webp'], 'image' => ['jpg', 'jpeg', 'png', 'webp'], 'pdf' => ['pdf'], 'doc' => ['pdf', 'jpg', 'jpeg', 'png', 'webp']][$k] ?? []; }
function up_accept(string $k): string { return ['video' => 'video/mp4,video/webm,video/ogg', 'media' => 'image/*,video/mp4,video/webm', 'image' => 'image/*', 'pdf' => '.pdf', 'doc' => '.pdf,image/*'][$k] ?? ''; }
function store_upload(array $f, string $kind, string $prefix, ?string &$err): ?string {
    $code = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($code === UPLOAD_ERR_NO_FILE) return null;
    if ($code !== UPLOAD_ERR_OK) { $err = "Échec du téléversement (code $code). Vérifiez la configuration du serveur."; return null; }
    $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, up_exts($kind), true)) { $err = "Format non autorisé (.$ext)."; return null; }
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && !@getimagesize($f['tmp_name'])) { $err = 'Image invalide.'; return null; }
    $name = preg_replace('/[^a-z_]/', '', $prefix) . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOADS_DIR . '/' . $name)) { $err = "Impossible d'enregistrer le fichier."; return null; }
    return 'uploads/' . $name;
}
function del_upload($p): void { if (is_string($p) && preg_match('#^uploads/[A-Za-z0-9._-]+$#', $p)) { $f = __DIR__ . '/' . $p; if (is_file($f)) @unlink($f); } }
function handle_doc_upload(array &$errs): ?array {
    if (!isset($_FILES['doc_file'])) return null;
    $e = null; $path = store_upload($_FILES['doc_file'], 'doc', 'proof', $e);
    if ($e) $errs[] = $e;
    if (!$path) return null;
    $title = trim((string)($_POST['doc_title'] ?? '')) ?: pathinfo((string)$_FILES['doc_file']['name'], PATHINFO_FILENAME);
    return ['title' => $title, 'path' => $path, 'public' => !empty($_POST['doc_public'])];
}
function parse_fields(array $fields, array $post, array $files, array $old, array &$errs): array {
    $out = []; $in = $post['f'] ?? [];
    foreach ($fields as $f) {
        $n = $f[0]; $type = $f[2]; $ex = $f[3] ?? null;
        switch ($type) {
            case 'bt': case 'bta':
                $v = $in[$n] ?? []; $cl = fn($x) => trim(str_replace("\r", '', (string)$x));
                $out[$n] = ['fr' => $cl($v['fr'] ?? ''), 'en' => $cl($v['en'] ?? '')]; break;
            case 'sel': case 'icon':
                $v = (string)($in[$n] ?? ''); $opts = $type === 'icon' ? admin_icons() : array_keys($ex);
                $out[$n] = in_array($v, $opts, true) ? $v : ($old[$n] ?? $opts[0]); break;
            case 'chk': $out[$n] = !empty($in[$n]); break;
            case 'file': case 'fileurl':
                $cur = $old[$n] ?? ''; $e = null;
                $new = isset($files['file_' . $n]) ? store_upload($files['file_' . $n], (string)$ex, $n, $e) : null;
                if ($e) $errs[] = $e;
                if ($new) { del_upload($cur); $cur = $new; }
                elseif (!empty($post['rm'][$n])) { del_upload($cur); $cur = ''; }
                elseif ($type === 'fileurl') {
                    $u = trim((string)($post['fu'][$n] ?? ''));
                    if ($u !== '' && strpos($u, 'https://') === 0 && safe_src($u) !== '') { if ($u !== $cur) del_upload($cur); $cur = $u; }
                    elseif ($u === '' && strpos((string)$cur, 'uploads/') !== 0) $cur = '';
                }
                $out[$n] = $cur; break;
            default: $out[$n] = trim((string)($in[$n] ?? ''));
        }
    }
    return $out;
}

/* ===================== CONFIGURATION DES FORMULAIRES ===================== */
$CATS = ['web' => 'Web', 'mobile' => 'Mobile', 'web_mobile' => 'Web et Mobile', 'backend' => 'Backend', 'security' => 'Sécurité', 'other' => 'Autres'];
$COLS = [
    'stats' => ['label' => 'Statistiques clés', 'path' => ['hero', 'stats'], 'title' => 'val', 'sub' => 'label', 'fields' => [['val', 'Valeur (ex : 10+, 19/20)', 't'], ['label', 'Libellé', 'bt'], ['icon', 'Icône', 'icon']]],
    'about_cards' => ['label' => 'Cartes « À propos »', 'path' => ['about_cards'], 'title' => 'label', 'sub' => 'line1', 'fields' => [['icon', 'Icône', 'icon'], ['label', 'Intitulé', 'bt'], ['line1', 'Ligne principale', 'bt'], ['line2', 'Ligne secondaire', 'bt']]],
    'experiences' => ['label' => 'Parcours professionnel', 'path' => ['experiences'], 'title' => 'role', 'sub' => 'date', 'fields' => [['date', 'Période', 'bt'], ['role', 'Poste', 'bt'], ['org', 'Organisme & ville', 'bt'], ['bullets', 'Missions (une ligne = une puce)', 'bta'], ['tags', 'Mots-clés (séparés par des virgules)', 'bt']]],
    'skills' => ['label' => 'Compétences', 'path' => ['skills'], 'title' => 'group', 'sub' => '', 'fields' => [['group', 'Nom du groupe', 'bt'], ['icon', 'Icône', 'icon'], ['items', 'Compétences (séparées par des virgules)', 'bta']]],
    'projects' => ['label' => 'Projets & CTF', 'path' => ['projects'], 'title' => 'title', 'sub' => 'year', 'fields' => [
        ['year', 'Année', 't'], ['category', 'Catégorie (filtre)', 'sel', $CATS], ['status', 'Statut', 'bt'], ['title', 'Titre', 'bt'], ['desc', 'Description complète', 'bta'], ['tags', 'Technologies (séparées par des virgules)', 't'],
        ['live_url', 'Lien « Voir le projet » (https://…)', 't'], ['demo_url', 'Lien démo externe (https://…)', 't'], ['confidential', 'Projet confidentiel', 'chk'],
        ['demo_video', 'Vidéo de démonstration (.mp4, .webm)', 'file', 'video'], ['cover', 'Image de couverture (optionnelle)', 'file', 'image']]],
    'education' => ['label' => 'Formations', 'path' => ['education'], 'title' => 'title', 'sub' => 'meta', 'fields' => [['title', 'Diplôme / formation', 'bt'], ['org', 'Établissement', 'bt'], ['meta', 'Dates & mention', 'bt']]],
    'certs' => ['label' => 'Diplômes, certifications & attestations', 'path' => ['certs'], 'title' => 'title', 'sub' => 'date', 'fields' => [
        ['type', 'Type', 'sel', ['diploma' => 'Diplôme', 'certification' => 'Certification', 'work' => 'Stage & travail']], ['title', 'Intitulé', 'bt'], ['org', 'Organisme', 'bt'], ['date', 'Date', 'bt'], ['file', 'Document principal (PDF ou image, optionnel)', 'file', 'doc']]],
    'blog' => ['label' => 'Articles & stories', 'path' => ['blog'], 'title' => 'title', 'sub' => 'date', 'fields' => [
        ['title', 'Titre', 'bt'], ['date', 'Date', 'bt'], ['category', 'Catégorie', 'bt'], ['desc', 'Contenu', 'bta'],
        ['media_type', 'Type de média', 'sel', ['none' => 'Aucun', 'image' => 'Image', 'local_video' => 'Vidéo locale (.mp4/.webm)', 'video_url' => 'Lien vidéo (embed https://)']], ['media_path', 'Média', 'fileurl', 'media']]],
    'socials' => ['label' => 'Réseaux professionnels', 'path' => ['socials'], 'title' => 'name', 'sub' => 'url', 'fields' => [['name', 'Nom (LinkedIn, GitHub, YouTube…)', 't'], ['url', 'Lien (https://…)', 't']]],
];
$SINGLES = [
    'hero' => ['label' => 'Identité & accueil', 'path' => ['hero'], 'fields' => [
        ['first_name', 'Prénom(s)', 't'], ['last_name', 'Nom', 't'], ['tag', "Tag d'introduction (ex : // PORTFOLIO SÉCURISÉ)", 'bt'], ['role', 'Rôle principal (ex : Ingénieure en cybersécurité)', 'bt'],
        ['lede', "Texte d'accroche", 'bta'], ['brand_sub', 'Sous-titre (barre latérale)', 'bt'], ['tech', 'Technologies mises en avant (séparées par des virgules)', 't'],
        ['note1', 'Note 1 sur le visuel (une ligne par mot)', 'bta'], ['note2', 'Note 2 sur le visuel', 'bta']]],
    'profile' => ['label' => 'Section « Profil »', 'path' => ['profile'], 'fields' => [['title', 'Titre', 'bt'], ['text1', 'Paragraphe 1', 'bta'], ['text2', 'Paragraphe 2', 'bta']]],
    'availability' => ['label' => 'Carte « Disponibilité » (accueil)', 'path' => ['availability'], 'fields' => [['title', 'Titre', 'bt'], ['text', 'Texte', 'bta'], ['tags', 'Étiquettes (séparées par des virgules)', 'bt']]],
    'education_note' => ['label' => "Carte d'accent (Formation)", 'path' => ['education_note'], 'fields' => [['title', 'Titre', 'bt'], ['text', 'Texte', 'bta']]],
    'media' => ['label' => 'Photo de profil & CV', 'path' => [], 'fields' => [['profile_photo', 'Photo de profil', 'file', 'image'], ['cv_path', 'CV en français (.pdf)', 'file', 'pdf'], ['cv_path_en', 'CV en anglais (.pdf)', 'file', 'pdf']]],
    'privacy' => ['label' => 'Confidentialité des justificatifs', 'path' => [], 'fields' => [['allow_proof_downloads', 'Autoriser le téléchargement des justificatifs publics', 'chk']]],
];
$TABS = [
    'identity' => ['Identité', [['single', 'hero'], ['single', 'profile']], 'user'],
    'home' => ['Accueil', [['col', 'stats'], ['single', 'availability']], 'home'],
    'about' => ['Profil', [['col', 'about_cards']], 'spark'],
    'experiences' => ['Parcours', [['col', 'experiences']], 'briefcase'],
    'skills' => ['Compétences', [['col', 'skills']], 'grid'],
    'projects' => ['Projets', [['col', 'projects']], 'folder'],
    'education' => ['Formation', [['col', 'education'], ['single', 'education_note']], 'cap'],
    'certs' => ['Diplômes', [['col', 'certs']], 'file'],
    'blog' => ['Blog & Story', [['col', 'blog']], 'play'],
    'contact' => ['Contact & réseaux', [['contact'], ['col', 'socials']], 'mail'],
    'files' => ['Photo, CV & accès', [['single', 'media'], ['single', 'privacy']], 'download'],
    'ui' => ['Textes du site', [['ui']], 'code'],
    'security' => ['Sécurité', [['password']], 'lock'],
];
$TAB_OF = [];
foreach ($TABS as $tid => $t) foreach ($t[1] as $b) $TAB_OF[$b[1] ?? $b[0]] = $tid;

/* ===== Téléversements AJAX (vidéo de projet / média de blog) ===== */
if (isset($_GET['ajax_upload'])) {
    $res = ['success' => false, 'message' => 'Requête invalide.', 'path' => ''];
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) jexit(['success' => false, 'message' => 'Fichier trop volumineux pour les limites du serveur.']);
        if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) jexit(['success' => false, 'message' => 'Session expirée, rechargez la page.']);
        $action = $_POST['action'] ?? ''; $e = null;
        if ($action === 'upload_proj_video') {
            $idx = (int)($_POST['proj_idx'] ?? -1);
            if (isset($data['projects'][$idx]) && isset($_FILES['proj_demo_video'])) {
                $url = store_upload($_FILES['proj_demo_video'], 'video', 'demo', $e);
                if ($url) { del_upload($data['projects'][$idx]['demo_video'] ?? ''); $data['projects'][$idx]['demo_video'] = $url; save_data($data); $res = ['success' => true, 'message' => 'Vidéo mise à jour.', 'path' => $url]; }
                else $res['message'] = $e ?: 'Aucun fichier reçu.';
            }
        } elseif ($action === 'upload_blog_media') {
            $idx = (int)($_POST['blog_idx'] ?? -1);
            if (isset($data['blog'][$idx]) && isset($_FILES['blog_media_file'])) {
                $url = store_upload($_FILES['blog_media_file'], 'media', 'blog', $e);
                if ($url) {
                    del_upload($data['blog'][$idx]['media_path'] ?? ''); $data['blog'][$idx]['media_path'] = $url;
                    $data['blog'][$idx]['media_type'] = preg_match('/\.(mp4|webm|ogg)$/i', $url) ? 'local_video' : 'image';
                    save_data($data); $res = ['success' => true, 'message' => 'Média enregistré.', 'path' => $url];
                } else $res['message'] = $e ?: 'Aucun fichier reçu.';
            }
        }
    }
    jexit($res);
}

/* ===== Suppression d'un justificatif ===== */
if (isset($_GET['del_doc'], $_GET['item_idx'], $_GET['doc_idx'])) {
    $type = $_GET['del_doc']; $iIdx = (int)$_GET['item_idx']; $dIdx = (int)$_GET['doc_idx'];
    if (!hash_equals($CSRF, (string)($_GET['csrf'] ?? ''))) { $_SESSION['error'] = 'Session expirée, veuillez réessayer.'; go('identity'); }
    if (in_array($type, ['education', 'experiences', 'certs'], true) && isset($data[$type][$iIdx]['docs'][$dIdx])) {
        del_upload($data[$type][$iIdx]['docs'][$dIdx]['path'] ?? '');
        array_splice($data[$type][$iIdx]['docs'], $dIdx, 1); save_data($data); $_SESSION['message'] = 'Justificatif supprimé.';
    }
    go($TAB_OF[$type] ?? $type);
}

/* ===== Traitement POST ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = preg_replace('/[^a-z_]/', '', (string)($_POST['tab'] ?? 'identity')) ?: 'identity';
    if (empty($_POST) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) { $_SESSION['error'] = 'Fichier trop volumineux pour les limites du serveur.'; go($tab); }
    if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) { $_SESSION['error'] = 'Session expirée, veuillez actualiser la page.'; go($tab); }
    $do = (string)($_POST['do'] ?? ''); $errs = [];

    if (in_array($do, ['save_item', 'delete_item', 'move_item'], true)) {
        $col = (string)($_POST['col'] ?? '');
        if (!isset($COLS[$col])) go($tab);
        $cfg = $COLS[$col]; $tab = $TAB_OF[$col] ?? $tab;
        $list = &pref($data, $cfg['path']);
        $idx = ($_POST['idx'] ?? '') !== '' ? (int)$_POST['idx'] : null;
        if ($do === 'save_item') {
            $old = ($idx !== null && isset($list[$idx])) ? $list[$idx] : [];
            $item = parse_fields($cfg['fields'], $_POST, $_FILES, $old, $errs) + $old;
            if (in_array($col, ['experiences', 'education', 'certs'], true)) {
                if (!isset($item['docs']) || !is_array($item['docs'])) $item['docs'] = [];
                foreach ($item['docs'] as $dIdx => &$doc) $doc['public'] = !empty($_POST['existing_docs_public'][$dIdx]);
                unset($doc);
                $newDoc = handle_doc_upload($errs);
                if ($newDoc) $item['docs'][] = $newDoc;
            }
            if ($idx !== null && isset($list[$idx])) $list[$idx] = $item; else $list[] = $item;
            $_SESSION['message'] = 'Élément enregistré.';
        } elseif ($do === 'delete_item' && $idx !== null && isset($list[$idx])) {
            foreach ($cfg['fields'] as $f) if (in_array($f[2], ['file', 'fileurl'], true)) del_upload($list[$idx][$f[0]] ?? '');
            foreach (($list[$idx]['docs'] ?? []) as $d) del_upload($d['path'] ?? '');
            array_splice($list, $idx, 1); $_SESSION['message'] = 'Élément supprimé.';
        } elseif ($do === 'move_item' && $idx !== null && isset($list[$idx])) {
            $to = ($_POST['dir'] ?? '') === 'up' ? $idx - 1 : $idx + 1;
            if (isset($list[$to])) { $tmp = $list[$to]; $list[$to] = $list[$idx]; $list[$idx] = $tmp; }
            $_SESSION['message'] = 'Ordre mis à jour.';
        }
        $list = array_values($list); unset($list);
        save_data($data);
        if ($errs) $_SESSION['error'] = implode(' ', $errs);
        go($tab);
    }
    if ($do === 'save_single') {
        $key = (string)($_POST['key'] ?? '');
        if (!isset($SINGLES[$key])) go($tab);
        $cfg = $SINGLES[$key]; $tab = $TAB_OF[$key] ?? $tab;
        $obj = &pref($data, $cfg['path']);
        foreach (parse_fields($cfg['fields'], $_POST, $_FILES, $obj, $errs) as $k => $v) $obj[$k] = $v;
        unset($obj); save_data($data);
        $_SESSION['message'] = 'Modifications enregistrées.';
        if ($errs) $_SESSION['error'] = implode(' ', $errs);
        go($tab);
    }
    if ($do === 'save_contact') {
        $cl = fn($k) => array_values(array_filter(array_map('trim', (array)($_POST[$k] ?? [])), 'strlen'));
        $data['contact']['emails'] = $cl('contact_emails'); $data['contact']['phones'] = $cl('contact_phones'); $data['contact']['locs'] = $cl('contact_locs');
        $vals = parse_fields([['status', '', 'bt'], ['interests', '', 'bt']], $_POST, [], [], $errs);
        $data['contact']['status'] = $vals['status']; $data['contact']['interests'] = $vals['interests'];
        save_data($data); $_SESSION['message'] = 'Coordonnées enregistrées.'; go('contact');
    }
    if ($do === 'save_ui') {
        $ui = [];
        foreach (ui_defaults() as $k => $d) foreach (['fr', 'en'] as $l) { $v = trim((string)($_POST['ui'][$k][$l] ?? '')); if ($v !== '' && $v !== $d[$l]) $ui[$k][$l] = $v; }
        $data['ui'] = $ui; save_data($data); $_SESSION['message'] = 'Textes du site mis à jour.'; go('ui');
    }
    if ($do === 'save_password') {
        $new = (string)($_POST['new_password'] ?? '');
        if (!password_verify((string)($_POST['old_password'] ?? ''), $data['password'])) $_SESSION['error'] = 'Ancien mot de passe invalide.';
        elseif (strlen($new) < 6) $_SESSION['error'] = 'Le mot de passe doit comporter au moins 6 caractères.';
        else { $data['password'] = password_hash($new, PASSWORD_DEFAULT); save_data($data); $_SESSION['message'] = 'Mot de passe modifié.'; }
        go('security');
    }
    go($tab);
}

/* ===================== AFFICHAGE ===================== */
function field_html(array $f, $val): string {
    $n = $f[0]; $type = $f[2]; $ex = $f[3] ?? null;
    $h = '<div class="fld"><label>' . s($f[1]) . '</label>';
    switch ($type) {
        case 'bt': case 'bta':
            $v = bi($val);
            foreach (['fr' => 'FR', 'en' => 'EN'] as $l => $L) {
                $cls = $l === 'fr' ? ' class="auto-fr"' : '';
                $h .= '<div class="bi"><span class="lg">' . $L . '</span>' . ($type === 'bt'
                    ? '<input type="text" name="f[' . $n . '][' . $l . ']" value="' . s($v[$l]) . '"' . $cls . '>'
                    : '<textarea name="f[' . $n . '][' . $l . ']"' . $cls . '>' . s($v[$l]) . '</textarea>') . '</div>';
            }
            break;
        case 'sel': case 'icon':
            $opts = $type === 'icon' ? array_combine(admin_icons(), admin_icons()) : $ex;
            $h .= '<select name="f[' . $n . ']">';
            foreach ($opts as $k => $lab) $h .= '<option value="' . s($k) . '"' . ((string)$val === (string)$k ? ' selected' : '') . '>' . s($lab) . '</option>';
            $h .= '</select>'; break;
        case 'chk': $h .= '<label class="chk"><input type="checkbox" name="f[' . $n . ']" value="1"' . (!empty($val) ? ' checked' : '') . '> Oui</label>'; break;
        case 'file': case 'fileurl':
            $val = (string)$val;
            if ($val !== '') {
                $isImg = preg_match('/\.(png|jpe?g|webp)$/i', $val) && strpos($val, 'uploads/') === 0 && is_file(__DIR__ . '/' . $val);
                $h .= '<div class="cur">' . ($isImg ? '<img src="' . s($val) . '?v=' . time() . '" alt="">' : '') . '<code>' . s(basename($val)) . '</code><label class="chk"><input type="checkbox" name="rm[' . $n . ']" value="1"> Retirer</label></div>';
            }
            $h .= '<input type="file" name="file_' . $n . '" accept="' . s(up_accept((string)$ex)) . '" class="filein">';
            if ($type === 'fileurl') $h .= '<input type="text" name="fu[' . $n . ']" placeholder="…ou lien externe https://" value="' . s(strpos($val, 'https://') === 0 ? $val : '') . '" style="margin-top:8px">';
            $h .= '<div class="prev"></div>'; break;
        default: $h .= '<input type="text" name="f[' . $n . ']" value="' . s($val) . '">';
    }
    return $h . '</div>';
}
function hidden(string $name, $v): string { return '<input type="hidden" name="' . s($name) . '" value="' . s($v) . '">'; }
function mini_form(string $tab, string $do, array $fields, string $label, string $cls = '', string $confirm = '', string $title = ''): string {
    global $CSRF;
    $h = '<form method="post" class="inl"' . ($confirm ? ' onsubmit="return confirm(\'' . s($confirm) . '\')"' : '') . '>' . hidden('csrf', $CSRF) . hidden('do', $do) . hidden('tab', $tab);
    foreach ($fields as $k => $v) $h .= hidden($k, $v);
    return $h . '<button class="btn sm ' . $cls . '" type="submit"' . ($title ? ' title="' . s($title) . '"' : '') . '>' . $label . '</button></form>';
}
function progress_html(string $id = ''): string { return '<div class="progress-wrapper"' . ($id ? ' id="' . $id . '"' : '') . '><div class="bar"></div><span class="ptxt">0%</span></div>'; }

function render_col(string $key, string $tab): void {
    global $COLS, $data, $CSRF;
    $cfg = $COLS[$key]; $list = pget($data, $cfg['path']);
    $editing = (($_GET['col'] ?? '') === $key && isset($_GET['edit']) && isset($list[(int)$_GET['edit']])) ? (int)$_GET['edit'] : null;
    $item = $editing !== null ? $list[$editing] : [];
    echo '<div class="block"><h2>' . s($cfg['label']) . ' <span class="count">' . count($list) . '</span></h2><div class="two"><div><h3>Liste</h3>';
    if (!$list) echo '<p class="empty">Aucun élément pour le moment. Utilisez le formulaire pour en ajouter un.</p>';
    foreach ($list as $i => $it) {
        $t = lang_val($it[$cfg['title']] ?? '', 'fr'); $sub = $cfg['sub'] ? lang_val($it[$cfg['sub']] ?? '', 'fr') : '';
        echo '<div class="li' . ($editing === $i ? ' sel' : '') . '"><div class="li-t"><b>' . s($t ?: '(sans titre)') . '</b>' . ($sub !== '' ? '<small>' . s(mb_strimwidth($sub, 0, 60, '…')) . '</small>' : '') . '</div><div class="act">';
        echo mini_form($tab, 'move_item', ['col' => $key, 'idx' => $i, 'dir' => 'up'], '↑', 'ghost', '', 'Monter') . mini_form($tab, 'move_item', ['col' => $key, 'idx' => $i, 'dir' => 'down'], '↓', 'ghost', '', 'Descendre');
        echo '<a class="btn sm accent" href="admin.php?col=' . s($key) . '&edit=' . $i . '#p-' . s($tab) . '">Modifier</a>';
        echo mini_form($tab, 'delete_item', ['col' => $key, 'idx' => $i], 'Supprimer', 'danger', 'Supprimer cet élément ? Cette action est irréversible.') . '</div></div>';
    }
    echo '</div><div><h3>' . ($editing !== null ? "Modifier l'élément" : 'Ajouter un élément') . '</h3>';
    echo '<form method="post" enctype="multipart/form-data" class="ajaxf">' . hidden('csrf', $CSRF) . hidden('do', 'save_item') . hidden('col', $key) . hidden('tab', $tab) . hidden('idx', $editing ?? '');
    foreach ($cfg['fields'] as $f) echo field_html($f, $item[$f[0]] ?? ($f[2] === 'icon' ? 'spark' : ''));
    if (in_array($key, ['experiences', 'education', 'certs'], true)) {
        if ($editing !== null && !empty($item['docs'])) {
            echo '<div class="fld"><label>Justificatifs actuels</label>';
            foreach ($item['docs'] as $dIdx => $doc) {
                echo '<div class="li" style="font-size:12px"><span class="li-t">' . s($doc['title'] ?? '') . '</span><label class="chk"><input type="checkbox" name="existing_docs_public[' . $dIdx . ']" value="1"' . (!empty($doc['public']) ? ' checked' : '') . '> Public</label>';
                echo '<a class="btn sm danger" href="admin.php?del_doc=' . s($key) . '&item_idx=' . $editing . '&doc_idx=' . $dIdx . '&csrf=' . s($CSRF) . '" onclick="return confirm(\'Supprimer ce justificatif ?\')">Suppr.</a></div>';
            }
            echo '</div>';
        }
        echo '<div class="dropbox"><label>Ajouter un justificatif (PDF ou image)</label><div class="fld"><input type="text" name="doc_title" placeholder="Titre du justificatif"></div>';
        echo '<div class="fld"><input type="file" name="doc_file" accept=".pdf,image/*"></div><label class="chk"><input type="checkbox" name="doc_public" value="1" checked> Afficher ce justificatif sur le site</label></div>';
    }
    echo progress_html() . '<div class="formbar"><button class="btn accent" type="submit">' . ($editing !== null ? 'Enregistrer' : 'Ajouter') . '</button>';
    if ($editing !== null) echo '<a class="btn ghost" href="admin.php#p-' . s($tab) . '">Annuler</a>';
    echo '</div></form>';
    if ($editing !== null && $key === 'projects') {
        echo '<div class="dropbox" style="margin-top:22px"><label>Vidéo de démonstration (envoi direct)</label><form id="ajaxProjVideoForm" method="POST" enctype="multipart/form-data">' . hidden('csrf', $CSRF) . hidden('action', 'upload_proj_video') . hidden('proj_idx', $editing);
        echo '<input type="file" name="proj_demo_video" accept="video/mp4,video/webm"><button type="button" class="btn sm accent full" style="margin-top:10px" onclick="uploadFileAJAX(\'ajaxProjVideoForm\',\'proj-progress\')">Envoyer la vidéo</button></form>' . progress_html('proj-progress') . '</div>';
    }
    if ($editing !== null && $key === 'blog') {
        echo '<div class="dropbox" style="margin-top:22px"><label>Média de l\'article (envoi direct)</label><form id="ajaxBlogMediaForm" method="POST" enctype="multipart/form-data">' . hidden('csrf', $CSRF) . hidden('action', 'upload_blog_media') . hidden('blog_idx', $editing);
        echo '<input type="file" name="blog_media_file" accept="image/*,video/mp4,video/webm"><button type="button" class="btn sm accent full" style="margin-top:10px" onclick="uploadFileAJAX(\'ajaxBlogMediaForm\',\'blog-progress\')">Envoyer le média</button></form>' . progress_html('blog-progress') . '</div>';
    }
    echo '</div></div></div>';
}
function render_single(string $key, string $tab): void {
    global $SINGLES, $data, $CSRF;
    $cfg = $SINGLES[$key]; $obj = $cfg['path'] ? pget($data, $cfg['path']) : $data;
    echo '<div class="block"><h2>' . s($cfg['label']) . '</h2><form method="post" enctype="multipart/form-data" class="ajaxf">' . hidden('csrf', $CSRF) . hidden('do', 'save_single') . hidden('key', $key) . hidden('tab', $tab) . '<div class="grid2">';
    foreach ($cfg['fields'] as $f) echo field_html($f, $obj[$f[0]] ?? '');
    echo '</div>' . progress_html() . '<div class="formbar"><button class="btn accent" type="submit">Enregistrer</button></div></form></div>';
}
function render_contact(string $tab): void {
    global $data, $CSRF; $c = $data['contact'];
    echo '<div class="block"><h2>Coordonnées</h2><form method="post">' . hidden('csrf', $CSRF) . hidden('do', 'save_contact') . hidden('tab', $tab) . '<div class="grid2">';
    foreach ([['contact_emails', 'Adresses e-mail', 'emails', 'email', 'nom@domaine.com'], ['contact_phones', 'Téléphones (le 1er alimente WhatsApp)', 'phones', 'text', '+216 …'], ['contact_locs', 'Localisations', 'locs', 'text', 'Ville, Pays']] as $g) {
        echo '<div class="fld"><label>' . $g[1] . '</label><div class="rows" data-name="' . $g[0] . '" data-type="' . $g[3] . '" data-ph="' . s($g[4]) . '">';
        foreach ($c[$g[2]] as $v) echo '<div class="drow"><input type="' . $g[3] . '" name="' . $g[0] . '[]" value="' . s($v) . '"><button type="button" class="btn sm danger" onclick="this.parentNode.remove()" aria-label="Retirer">✕</button></div>';
        echo '</div><button type="button" class="btn sm ghost" onclick="addRow(this)">+ Ajouter</button></div>';
    }
    echo '</div><p class="empty">Le premier e-mail reçoit le code de connexion (OTP). Tant qu\'aucun e-mail n\'est renseigné, la connexion se fait par mot de passe seul.</p><div class="grid2">';
    echo field_html(['status', 'Statut de disponibilité (ex : Ouverte aux opportunités)', 'bt'], $c['status']) . field_html(['interests', "Centres d'intérêt (séparés par des virgules)", 'bt'], $c['interests']);
    echo '</div><div class="formbar"><button class="btn accent" type="submit">Enregistrer les coordonnées</button></div></form></div>';
}
function render_ui(string $tab): void {
    global $data, $CSRF; $cur = $data['ui'] ?? [];
    echo '<div class="block"><h2>Textes d\'interface FR / EN</h2><p class="empty">Laissez vide pour garder le texte par défaut (affiché en gris).</p><form method="post">' . hidden('csrf', $CSRF) . hidden('do', 'save_ui') . hidden('tab', $tab);
    foreach (ui_defaults() as $k => $d) {
        echo '<div class="uirow"><code>' . s($k) . '</code>';
        foreach (['fr', 'en'] as $l) echo '<div class="bi"><span class="lg">' . strtoupper($l) . '</span><input type="text" name="ui[' . s($k) . '][' . $l . ']" value="' . s($cur[$k][$l] ?? '') . '" placeholder="' . s($d[$l]) . '"></div>';
        echo '</div>';
    }
    echo '<div class="formbar"><button class="btn accent" type="submit">Enregistrer les textes</button></div></form></div>';
}
function render_password(string $tab): void {
    global $CSRF;
    echo '<div class="block"><h2>Mot de passe administrateur</h2><form method="post" style="max-width:420px" onsubmit="return confirm(\'Modifier le mot de passe administrateur ?\')">' . hidden('csrf', $CSRF) . hidden('do', 'save_password') . hidden('tab', $tab);
    echo '<div class="fld"><label>Ancien mot de passe</label><input type="password" name="old_password" required autocomplete="current-password"></div>';
    echo '<div class="fld"><label>Nouveau mot de passe (6 caractères minimum)</label><input type="password" name="new_password" minlength="6" required autocomplete="new-password"></div>';
    echo '<div class="formbar"><button class="btn danger" type="submit">Modifier le mot de passe</button></div></form></div>';
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="robots" content="noindex,nofollow">
<?php if ($photo): ?><link rel="icon" type="image/png" href="<?= s($photo) ?>"><?php endif; ?>
<title>Administration · Portfolio</title>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>(function(){var t=null;try{t=localStorage.getItem('theme')}catch(e){}document.documentElement.setAttribute('data-theme',t==='light'?'light':'dark')})();</script>
<style>
:root{--bg:#000;--panel:#0A0A0A;--panel2:#0f0f10;--input:#121212;--text:#F8FAFC;--dim:#94A3B8;--cy:#3B82F6;--red:#EF4444;--line:rgba(59,130,246,.25);--r:12px}
html[data-theme="light"]{--bg:#fff;--panel:#F8FAFC;--panel2:#fff;--input:#F1F5F9;--text:#0F172A;--dim:#475569;--cy:#1D4ED8;--line:rgba(30,64,175,.2)}
*{box-sizing:border-box}html{scroll-behavior:smooth}
body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,sans-serif;font-size:14px;line-height:1.5;transition:background .3s,color .3s}
[hidden]{display:none!important}:focus-visible{outline:2px solid var(--cy);outline-offset:2px}
.ic{width:1.15em;height:1.15em}
.top{position:sticky;top:0;z-index:30;display:flex;align-items:center;justify-content:space-between;gap:14px;padding:12px 28px;background:var(--panel);border-bottom:1px solid var(--line)}
.top h1{margin:0;font:700 17px 'JetBrains Mono',monospace;color:var(--cy)}.top .r{display:flex;gap:8px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 18px;background:var(--cy);color:#fff;border:1px solid transparent;border-radius:6px;font:600 10.5px 'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.1em;cursor:pointer;text-decoration:none;transition:all .2s}
.btn:hover{opacity:.9;transform:translateY(-1px)}.btn:disabled{opacity:.55;cursor:wait}
.btn.ghost{background:transparent;border-color:var(--line);color:var(--text)}.btn.ghost:hover{border-color:var(--cy);color:var(--cy)}
.btn.danger{background:var(--red)}.btn.sm{padding:6px 11px;font-size:10px}.btn.full{width:100%}
.layout{display:grid;grid-template-columns:240px minmax(0,1fr);min-height:calc(100vh - 58px)}
.side{border-right:1px solid var(--line);padding:16px 12px;position:sticky;top:58px;align-self:start;height:calc(100vh - 58px);overflow-y:auto;background:var(--panel)}
.side a{display:flex;align-items:center;gap:10px;padding:10px 12px;margin-bottom:2px;color:var(--dim);text-decoration:none;border-radius:6px;font-weight:500;transition:all .2s}
.side a:hover{color:var(--text);background:var(--input)}.side a.on{color:var(--text);background:color-mix(in srgb,var(--cy) 14%,transparent);box-shadow:inset 3px 0 0 var(--cy);font-weight:700}
main{padding:30px;min-width:0;max-width:1280px}.panel{animation:fi .25s}@keyframes fi{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.toasts{position:fixed;top:72px;right:20px;z-index:50;display:flex;flex-direction:column;gap:10px;max-width:min(380px,calc(100vw - 40px))}
.toast{padding:13px 16px;border-radius:8px;background:var(--panel);border:1px solid var(--line);border-left:4px solid var(--cy);cursor:pointer;font:12px 'JetBrains Mono',monospace}.toast.err{border-left-color:var(--red)}.toast.out{opacity:0;transform:translateX(20px);transition:all .3s}
.block{background:var(--panel);border:1px solid var(--line);border-radius:var(--r);padding:26px;margin-bottom:24px}
.block h2{margin:0 0 22px;font:700 18px 'JetBrains Mono',monospace;color:var(--cy);display:flex;align-items:center;gap:10px;padding-bottom:14px;border-bottom:1px solid var(--line)}
.block h3{margin:0 0 14px;font:600 14px 'JetBrains Mono',monospace}
.count{font-size:11px;background:var(--input);padding:2px 9px;border-radius:99px;color:var(--dim)}
.two{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:34px}.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:0 24px}
.fld{margin-bottom:16px}.fld>label,.dropbox>label{display:block;margin-bottom:6px;font:700 10px 'JetBrains Mono',monospace;text-transform:uppercase;letter-spacing:.08em;color:var(--cy)}
input[type=text],input[type=email],input[type=password],textarea,select{width:100%;padding:10px 12px;background:var(--input);border:1px solid var(--line);border-radius:6px;color:var(--text);font:13.5px inherit;outline:none;transition:border-color .2s}
input:focus,textarea:focus,select:focus{border-color:var(--cy)}textarea{min-height:84px;resize:vertical;line-height:1.55}
input[type=file]{width:100%;padding:9px;background:var(--input);border:1px dashed var(--line);border-radius:6px;color:var(--dim);font-size:12.5px}
.dropbox{background:var(--panel2);padding:16px;border:1px dashed var(--line);border-radius:10px;margin-bottom:18px}
.bi{display:flex;gap:8px;align-items:flex-start;margin-bottom:6px}.bi>:last-child{flex:1;min-width:0}
.lg{flex:0 0 34px;text-align:center;font:700 10px 'JetBrains Mono',monospace;background:var(--input);color:var(--cy);padding:10px 0;border:1px solid var(--line);border-radius:6px}
.lg.busy{animation:bl 1s infinite}.lg.done{color:#fff;background:var(--cy)}.lg.fail{color:#fff;background:var(--red)}@keyframes bl{50%{opacity:.4}}
.chk{display:inline-flex;align-items:center;gap:8px;font-weight:400!important;color:var(--text)!important;font-size:13px!important;cursor:pointer;text-transform:none!important;letter-spacing:0!important}.chk input{width:auto}
.cur{display:flex;align-items:center;gap:12px;margin-bottom:8px;flex-wrap:wrap;font-size:12px}.cur img{width:64px;height:64px;object-fit:cover;border:1px solid var(--line);border-radius:8px}.cur code{color:var(--cy)}
.prev img,.prev video{max-width:220px;margin-top:8px;border:1px solid var(--line);border-radius:8px}
.li{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:11px 13px;background:var(--input);border:1px solid var(--line);border-radius:8px;margin-bottom:8px;flex-wrap:wrap}
.li.sel{border-color:var(--cy)}.li-t{min-width:0;flex:1}.li-t b{display:block;font-size:13px}.li-t small{color:var(--dim);font-size:11.5px}
.act{display:flex;gap:6px;flex-wrap:wrap}.inl{display:inline}.empty{color:var(--dim);font-size:13px;margin:0 0 14px}
.drow{display:flex;gap:8px;margin-bottom:8px}.drow input{flex:1}
.uirow{display:grid;grid-template-columns:150px 1fr 1fr;gap:10px;align-items:center;padding:8px 0;border-bottom:1px solid var(--line)}.uirow code{font-size:11px;color:var(--cy);word-break:break-all}.uirow .bi{margin:0}
.formbar{display:flex;gap:10px;flex-wrap:wrap;margin-top:8px}.formbar .btn{min-width:150px}
.progress-wrapper{display:none;position:relative;height:24px;background:var(--input);border:1px solid var(--line);border-radius:99px;margin:6px 0 14px;overflow:hidden}
.bar{height:100%;width:0;background:var(--cy);transition:width .15s}.ptxt{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:700 11px 'JetBrains Mono',monospace}
@media(max-width:980px){.layout{grid-template-columns:1fr}.side{top:58px;z-index:20;height:auto;display:flex;overflow-x:auto;padding:8px;border-right:0;border-bottom:1px solid var(--line);gap:4px}.side a{white-space:nowrap;margin:0}.two,.uirow{grid-template-columns:1fr}main{padding:16px}.top{padding:10px 16px}.block{padding:18px}}
</style>
</head>
<body>
<div class="top">
  <h1>// ADMIN CONSOLE</h1>
  <div class="r">
    <button class="btn ghost" type="button" onclick="toggleTheme()"><?= icon('moon') ?> Thème</button>
    <a class="btn ghost" href="index.php" target="_blank" rel="noopener">Voir le portfolio</a>
    <a class="btn danger" href="admin.php?logout=1" onclick="return confirm('Voulez-vous vraiment vous déconnecter ?')">Déconnexion</a>
  </div>
</div>
<div class="toasts"><?php if ($message): ?><div class="toast"><?= s($message) ?></div><?php endif; ?><?php if ($error): ?><div class="toast err"><?= s($error) ?></div><?php endif; ?></div>
<div class="layout">
  <nav class="side"><?php foreach ($TABS as $tid => $t): ?><a href="#p-<?= s($tid) ?>" data-tab="<?= s($tid) ?>"><?= icon($t[2]) ?><?= s($t[0]) ?></a><?php endforeach; ?></nav>
  <main>
    <?php foreach ($TABS as $tid => $t): ?><section class="panel" id="p-<?= s($tid) ?>" hidden>
      <?php foreach ($t[1] as $b) { if ($b[0] === 'col') render_col($b[1], $tid); elseif ($b[0] === 'single') render_single($b[1], $tid); elseif ($b[0] === 'contact') render_contact($tid); elseif ($b[0] === 'ui') render_ui($tid); elseif ($b[0] === 'password') render_password($tid); } ?>
    </section><?php endforeach; ?>
  </main>
</div>
<script>
const tabs=[...document.querySelectorAll('.side a')],panels=[...document.querySelectorAll('.panel')];
function showTab(id){if(!panels.some(p=>p.id==='p-'+id))id=tabs[0].dataset.tab;panels.forEach(p=>p.hidden=p.id!=='p-'+id);tabs.forEach(a=>a.classList.toggle('on',a.dataset.tab===id));try{localStorage.setItem('adm_tab',id)}catch(e){}panels.filter(p=>!p.hidden).forEach(p=>p.querySelectorAll('textarea').forEach(autoGrow))}
function initTab(){let id=location.hash.replace('#p-','');if(!id){try{id=localStorage.getItem('adm_tab')||''}catch(e){}}showTab(id)}
addEventListener('hashchange',initTab);
tabs.forEach(a=>a.addEventListener('click',e=>{e.preventDefault();history.replaceState(null,'','#p-'+a.dataset.tab);showTab(a.dataset.tab);scrollTo({top:0})}));
document.querySelectorAll('.toast').forEach(t=>{const c=()=>{t.classList.add('out');setTimeout(()=>t.remove(),300)};t.addEventListener('click',c);setTimeout(c,t.classList.contains('err')?9000:4500)});
function addRow(btn){const box=btn.previousElementSibling,row=document.createElement('div');row.className='drow';const i=document.createElement('input');i.type=box.dataset.type;i.name=box.dataset.name+'[]';i.placeholder=box.dataset.ph;i.required=true;
 const d=document.createElement('button');d.type='button';d.className='btn sm danger';d.textContent='✕';d.onclick=()=>row.remove();row.append(i,d);box.appendChild(row);i.focus()}
function autoGrow(t){if(t.tagName!=='TEXTAREA'||t.offsetParent===null)return;t.style.height='auto';t.style.height=Math.min(t.scrollHeight+2,520)+'px'}

/* Traduction automatique FR → EN (découpage en blocs ≤ 420 caractères, MyMemory) */
const TR={max:420,cache:new Map(),dec:document.createElement('textarea')};
const decodeHtml=s=>{TR.dec.innerHTML=s;return TR.dec.value};
function splitByWords(s,max){const o=[];let c='';for(const w of s.split(/\s+/)){if((c+' '+w).trim().length>max){if(c)o.push(c);c=w}else c=(c+' '+w).trim()}if(c)o.push(c);return o}
function chunkLine(line,max){const ss=line.match(/[^.!?…]+[.!?…]*["»”)]*\s*/g)||[line],ch=[];let c='';
 for(const r of ss){const s=r.trim();if(!s)continue;if(s.length>max){if(c){ch.push(c);c=''}ch.push(...splitByWords(s,max));continue}if((c+' '+s).trim().length>max){ch.push(c);c=s}else c=(c+' '+s).trim()}if(c)ch.push(c);return ch}
async function translateChunk(text){if(TR.cache.has(text))return TR.cache.get(text);
 const r=await fetch('https://api.mymemory.translated.net/get?q='+encodeURIComponent(text)+'&langpair=fr|en');if(!r.ok)throw new Error('http');
 const j=await r.json(),out=j&&j.responseData&&j.responseData.translatedText;
 if(!out||String(j.responseStatus)!=='200'||/MYMEMORY WARNING|QUERY LENGTH LIMIT/i.test(out))throw new Error('quota');
 const c=decodeHtml(out);TR.cache.set(text,c);return c}
async function translateLong(text){const lines=text.split('\n'),jobs=[],res=lines.map(()=>[]);
 lines.forEach((l,li)=>{if(l.trim())chunkLine(l.trim(),TR.max).forEach((c,ci)=>jobs.push({li,ci,c}))});
 let n=0;const w=async()=>{while(n<jobs.length){const j=jobs[n++];res[j.li][j.ci]=await translateChunk(j.c)}};await Promise.all([w(),w(),w()]);return res.map(p=>p.join(' ')).join('\n')}
function setBadge(f,st){const lg=f.parentNode.querySelector('.lg');if(!lg)return;lg.classList.remove('busy','done','fail');lg.textContent=st==='busy'?'…':st==='fail'?'!':'EN';if(st)lg.classList.add(st);
 if(st==='fail')lg.title='Traduction automatique indisponible : saisissez le texte anglais manuellement.';if(st==='done')setTimeout(()=>lg.classList.remove('done'),1800)}
document.addEventListener('input',e=>{const t=e.target;if(t.tagName==='TEXTAREA')autoGrow(t);
 if(/\[en\]$/.test(t.name||'')){t.dataset.autoTranslated='false';return}
 if(!/\[fr\]$/.test(t.name||''))return;
 const en=t.form&&t.form.querySelector('[name="'+t.name.replace(/\[fr\]$/,'[en]').replace(/"/g,'\\"')+'"]');
 if(!en||(en.value&&en.dataset.autoTranslated!=='true'))return;
 const text=t.value.replace(/\r/g,'');clearTimeout(t._timer);const tok=t._tok=(t._tok||0)+1;
 if(!text.trim()){en.value='';en.dataset.autoTranslated='true';setBadge(en,'');return}
 setBadge(en,'busy');
 t._timer=setTimeout(async()=>{try{const r=await translateLong(text);if(tok!==t._tok||en.dataset.autoTranslated==='false')return;en.value=r;en.dataset.autoTranslated='true';autoGrow(en);setBadge(en,'done')}catch(err){if(tok===t._tok)setBadge(en,'fail')}},text.length>600?1200:700)});

document.addEventListener('change',e=>{const i=e.target;if(!i.classList||!i.classList.contains('filein')||!i.files[0])return;
 const f=i.files[0],box=i.parentNode.querySelector('.prev'),u=URL.createObjectURL(f);box.replaceChildren();
 if(f.type.startsWith('image/')){const m=document.createElement('img');m.src=u;box.appendChild(m)}
 else if(f.type.startsWith('video/')){const v=document.createElement('video');v.src=u;v.controls=true;v.style.maxWidth='220px';box.appendChild(v)}
 else box.textContent='📄 '+f.name});
document.querySelectorAll('form.ajaxf').forEach(form=>form.addEventListener('submit',e=>{
 if(![...form.querySelectorAll('input[type=file]')].some(i=>i.files.length))return;e.preventDefault();
 const w=form.querySelector('.progress-wrapper'),bar=w.querySelector('.bar'),tx=w.querySelector('.ptxt'),tab=form.querySelector('[name=tab]').value,btn=form.querySelector('button[type=submit]');
 w.style.display='block';btn.disabled=true;const x=new XMLHttpRequest();
 x.upload.onprogress=ev=>{if(ev.lengthComputable){const p=Math.round(ev.loaded/ev.total*100);bar.style.width=p+'%';tx.textContent=p+'% · envoi…'}};
 x.onload=()=>{tx.textContent='100% · terminé';history.replaceState(null,'','admin.php#p-'+tab);location.reload()};
 x.onerror=()=>{alert('Échec du téléversement.');w.style.display='none';btn.disabled=false};
 x.open('POST','admin.php');x.send(new FormData(form))}));
function uploadFileAJAX(formId,progressId){const form=document.getElementById(formId),wrap=document.getElementById(progressId),bar=wrap.querySelector('.bar'),tx=wrap.querySelector('.ptxt'),fi=form.querySelector('input[type=file]');
 if(!fi||!fi.files.length){alert('Veuillez sélectionner un fichier.');return}
 const x=new XMLHttpRequest();wrap.style.display='block';bar.style.width='0%';
 x.upload.onprogress=e=>{if(e.lengthComputable){const p=Math.round(e.loaded/e.total*100);bar.style.width=p+'%';tx.textContent=p+'% · envoi…'}};
 x.onload=()=>{try{const r=JSON.parse(x.responseText);if(r.success){tx.textContent='100% · terminé';setTimeout(()=>location.reload(),700)}else{alert('Erreur : '+r.message);wrap.style.display='none'}}catch(e){alert('Réponse inattendue du serveur.');wrap.style.display='none'}};
 x.onerror=()=>{alert('Échec du téléversement.');wrap.style.display='none'};
 x.open('POST','admin.php?ajax_upload=1');x.send(new FormData(form))}
function toggleTheme(){const n=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=n;try{localStorage.setItem('theme',n)}catch(e){}}
initTab();
</script>
</body>
</html>
