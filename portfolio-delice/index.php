<?php
require __DIR__ . '/lib.php';
$data = normalize(load_data());
ui_init($data);

$h = $data['hero']; $c = $data['contact'];
$fullName = trim($h['first_name'] . ' ' . $h['last_name']);
$clean = fn($a) => array_values(array_filter(array_map('trim', (array)$a), 'strlen'));
$emails = $clean($c['emails']); $phones = $clean($c['phones']); $locs = $clean($c['locs']);
$waNum  = $phones ? preg_replace('/\D/', '', $phones[0]) : '';
$photo  = file_ok($data['profile_photo']) ? $data['profile_photo'] : '';
$cvs = [];
if (file_ok($data['cv_path']))    $cvs[] = ['k' => 'cv_fr', 'p' => $data['cv_path']];
if (file_ok($data['cv_path_en'])) $cvs[] = ['k' => 'cv_en', 'p' => $data['cv_path_en']];
$tech = csv($h['tech']);
$socials = array_values(array_filter($data['socials'], fn($x) => !empty($x['url']) && safe_src($x['url']) !== ''));
$experiences = $data['experiences'];
$updated = is_file(DATA_FILE) ? filemtime(DATA_FILE) : time();

$catOrder = ['web', 'mobile', 'web_mobile', 'backend', 'security', 'other'];
$catIcon  = ['web' => 'globe', 'mobile' => 'mobile', 'web_mobile' => 'mobile', 'backend' => 'server', 'security' => 'shield', 'other' => 'layers'];
$projects = $data['projects'];
foreach ($projects as $i => $p) $projects[$i]['_cat'] = in_array($p['category'] ?? '', $catOrder, true) ? $p['category'] : 'other';
$certTypes = ['diploma' => 'type_diploma', 'certification' => 'type_cert', 'work' => 'type_work'];
$certs = $data['certs']; $usedTypes = [];
foreach ($certs as $i => $ce) $certs[$i]['_type'] = isset($certTypes[$ce['type'] ?? '']) ? $ce['type'] : 'certification';
foreach ($certTypes as $k => $_) foreach ($certs as $ce) if ($ce['_type'] === $k) { $usedTypes[] = $k; break; }

/* Groupes de justificatifs publics (affichés dans une modale à deux volets) */
$J = ['title' => ['fr' => UP('page_title', 'fr') . ($fullName ? ' - ' . $fullName : ''), 'en' => UP('page_title', 'en') . ($fullName ? ' - ' . $fullName : '')],
      'email' => $emails[0] ?? '', 'ts' => $updated, 'dl' => !empty($data['allow_proof_downloads']), 'ui' => [], 'projects' => [], 'blog' => [], 'proofs' => []];
function proof_group(array &$J, array $docs, $extra = ''): ?int {
    $out = [];
    $f = safe_src($extra);
    if ($f !== '') $out[] = ['title' => ['fr' => '', 'en' => ''], 'src' => $f];
    foreach ($docs as $d) if (!empty($d['public']) && file_ok($d['path'] ?? '')) $out[] = ['title' => bi($d['title'] ?? ''), 'src' => $d['path']];
    if (!$out) return null;
    $J['proofs'][] = $out;
    return count($J['proofs']) - 1;
}
foreach (['copied', 'close', 'show_more', 'show_less', 'video', 'view_project', 'demo', 'confidential', 'proofs_title', 'download_file', 'cat_web', 'cat_mobile', 'cat_web_mobile', 'cat_backend', 'cat_security', 'cat_other'] as $k) $J['ui'][$k] = UB($k);
foreach ($projects as $p) $J['projects'][] = [
    'title' => bi($p['title'] ?? ''), 'desc' => bi($p['desc'] ?? ''), 'status' => bi($p['status'] ?? ''), 'year' => (string)($p['year'] ?? ''),
    'cat' => $p['_cat'], 'tags' => csv(lang_val($p['tags'] ?? '', 'fr')), 'live' => safe_src($p['live_url'] ?? ''), 'demo' => safe_src($p['demo_url'] ?? ''),
    'video' => file_ok($p['demo_video'] ?? '') ? $p['demo_video'] : '', 'cover' => file_ok($p['cover'] ?? '') ? $p['cover'] : '', 'conf' => !empty($p['confidential'])];
foreach ($data['blog'] as $b) $J['blog'][] = [
    'title' => bi($b['title'] ?? ''), 'date' => bi($b['date'] ?? ''), 'category' => bi($b['category'] ?? ''), 'desc' => bi($b['desc'] ?? ''),
    'type' => $b['media_type'] ?? 'none', 'src' => safe_src($b['media_path'] ?? '')];
$jflags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

/* Rubriques affichées : une rubrique sans contenu est automatiquement masquée */
$show = [
    'about' => has($data['profile']['title']) || has($data['profile']['text1']) || has($data['profile']['text2']) || $data['about_cards'],
    'experience' => (bool)$experiences, 'skills' => (bool)$data['skills'], 'projects' => (bool)$projects,
    'education' => (bool)$data['education'], 'certs' => (bool)$certs, 'blog' => (bool)$data['blog'],
    'contact' => $emails || $phones || $locs || $cvs || has($c['status']) || $socials,
];
$navIcons = ['about' => 'user', 'experience' => 'briefcase', 'skills' => 'grid', 'projects' => 'folder', 'education' => 'cap', 'certs' => 'file', 'blog' => 'play', 'contact' => 'mail'];
$isEmpty = $fullName === '' && !array_filter($show);

function sec_head(string $eyebrow, string $title, string $sub = '', array $vars = []): void {
    echo '<div class="sec-head reveal"><span class="eyebrow">' . U($eyebrow) . '</span><h2>' . U($title) . '</h2>';
    if ($sub !== '') echo '<p>' . U($sub, $vars) . '</p>';
    echo '</div>';
}
function proof_btn(?int $g, string $cls = ''): string {
    return $g === null ? '' : '<div class="row" style="margin-top:12px"><button class="btn btn-ghost btn-sm" type="button" data-proofs="' . $g . '">' . icon('file') . U('proofs_btn') . '</button></div>';
}
?>
<!DOCTYPE html>
<html lang="fr" data-lang="fr" data-theme="dark">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= s(UP('page_title', 'fr') . ($fullName ? ' - ' . $fullName : '')) ?></title>
<meta name="description" content="<?= s(lang_val($h['lede'], 'fr')) ?>">
<?php if ($photo): ?><link rel="icon" type="image/png" href="<?= s($photo) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<script>
(function(){var d=document.documentElement,l=null,t=null;try{l=localStorage.getItem('lang');t=localStorage.getItem('theme')}catch(e){}
if(l!=='fr'&&l!=='en')l=((navigator.language||'fr').toLowerCase().indexOf('en')===0)?'en':'fr';
if(t!=='light'&&t!=='dark')t='dark';
d.setAttribute('data-lang',l);d.setAttribute('lang',l);d.setAttribute('data-theme',t)})();
</script>
<style>
:root{--bg-silk:#FFFFFF;--panel-lace:#F8FAFC;--panel-dusty:#F1F5F9;--line-cyber:rgba(30,64,175,.15);--line-cyber-strong:rgba(30,64,175,.35);
--text-charcoal:#0F172A;--text-muted:#334155;--text-dim:#64748B;--cyber-blue:#1D4ED8;--cyber-blue-2:#0EA5E9;--cyber-blue-dim:rgba(29,78,216,.08);
--font-display:'JetBrains Mono',monospace;--font-body:'Inter',sans-serif;--rail-w:260px;--top-h:60px}
html[data-theme="dark"]{--bg-silk:#000;--panel-lace:#0A0A0A;--panel-dusty:#121212;--line-cyber:rgba(59,130,246,.2);--line-cyber-strong:rgba(59,130,246,.4);
--text-charcoal:#F8FAFC;--text-muted:#94A3B8;--text-dim:#64748B;--cyber-blue:#3B82F6;--cyber-blue-2:#38BDF8;--cyber-blue-dim:rgba(59,130,246,.1)}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:20px}
body{background:var(--bg-silk);color:var(--text-charcoal);font-family:var(--font-body);line-height:1.7;overflow-x:hidden;transition:background .4s,color .4s;word-break:break-word}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(var(--line-cyber) 1px,transparent 1px),linear-gradient(90deg,var(--line-cyber) 1px,transparent 1px);background-size:50px 50px;pointer-events:none;z-index:0}
a{color:inherit;text-decoration:none}ul{list-style:none}img,video{max-width:100%;display:block}button{font:inherit;color:inherit}
:focus-visible{outline:2px solid var(--cyber-blue);outline-offset:3px}[hidden]{display:none!important}
html[data-lang="fr"] .l-en,html[data-lang="en"] .l-fr{display:none!important}.lw{display:contents}
.ic{width:1.1em;height:1.1em;flex-shrink:0}
.grad{background:linear-gradient(110deg,var(--cyber-blue),var(--cyber-blue-2));-webkit-background-clip:text;background-clip:text;color:transparent}
.eyebrow{font-family:var(--font-display);font-size:11px;letter-spacing:.15em;text-transform:uppercase;color:var(--cyber-blue);display:flex;align-items:center;gap:10px}
.eyebrow::before{content:'';width:6px;height:6px;background:var(--cyber-blue);transform:rotate(45deg)}
.btn{font-family:var(--font-display);font-size:11px;letter-spacing:.1em;text-transform:uppercase;padding:12px 24px;border:1px solid var(--line-cyber-strong);border-radius:6px;display:inline-flex;align-items:center;justify-content:center;gap:10px;transition:all .3s;cursor:pointer;background:transparent;color:var(--text-charcoal)}
.btn-primary{background:var(--cyber-blue);color:#fff;border-color:var(--cyber-blue);font-weight:600}.btn-primary:hover{background:transparent;color:var(--cyber-blue);box-shadow:0 0 20px var(--cyber-blue-dim)}
.btn-ghost:hover,.btn-cv:hover{border-color:var(--cyber-blue);color:var(--cyber-blue);background:var(--cyber-blue-dim)}
.btn-cv{border-color:var(--cyber-blue);color:var(--cyber-blue)}
.btn-wa{background:#25D366;border-color:#25D366;color:#fff;font-weight:600}.btn-wa:hover{background:transparent;color:#25D366}
.btn-sm{padding:7px 14px;font-size:10px}
.tag{font-family:var(--font-display);font-size:9.5px;color:var(--text-dim);border:1px solid var(--line-cyber);padding:4px 8px;border-radius:4px;background:var(--panel-dusty)}
.pill{font-family:var(--font-display);font-size:10.5px;color:var(--text-muted);border:1px solid var(--line-cyber-strong);padding:6px 12px;border-radius:4px;background:var(--panel-dusty);transition:all .3s}
.pill:hover{color:var(--cyber-blue);border-color:var(--cyber-blue);transform:translateY(-1px)}
.row{display:flex;flex-wrap:wrap;gap:8px}
.card{background:var(--panel-lace);border:1px solid var(--line-cyber-strong);border-radius:12px;transition:all .3s}
.card:hover{border-color:var(--cyber-blue);box-shadow:0 10px 30px var(--cyber-blue-dim)}
.ico-sq{width:38px;height:38px;display:inline-flex;align-items:center;justify-content:center;background:var(--cyber-blue-dim);color:var(--cyber-blue);border:1px solid var(--line-cyber-strong);border-radius:8px;flex-shrink:0}.ico-sq .ic{width:19px;height:19px}

/* Rail */
.rail{position:fixed;inset:0 auto 0 0;width:var(--rail-w);background:var(--panel-lace);border-right:1px solid var(--line-cyber-strong);z-index:210;display:flex;flex-direction:column;padding:30px 22px;overflow-y:auto;transition:transform .35s}
.brand{display:flex;gap:14px;align-items:center;margin-bottom:20px}
.mark{width:64px;height:64px;border-radius:12px;border:1px solid var(--cyber-blue);padding:3px;background:var(--panel-dusty);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:700;font-size:18px;color:var(--cyber-blue);overflow:hidden}
.mark img{width:100%;height:100%;border-radius:8px;object-fit:cover}
.brand b{display:block;font-family:var(--font-display);font-size:16px;line-height:1.2}
.brand small{display:block;font-family:var(--font-display);font-size:9px;color:var(--text-dim);letter-spacing:.1em;text-transform:uppercase;margin-top:5px}
.rail-status{display:flex;align-items:center;gap:8px;font-family:var(--font-display);font-size:10px;letter-spacing:.1em;color:var(--cyber-blue);text-transform:uppercase;padding:8px 12px;border:1px solid var(--line-cyber-strong);background:var(--cyber-blue-dim);border-radius:6px;margin-bottom:16px;width:fit-content}
.dot-live{width:6px;height:6px;border-radius:50%;background:var(--cyber-blue);animation:pulse 2s infinite}
@keyframes pulse{0%,100%{opacity:1;box-shadow:0 0 0 0 rgba(59,130,246,.4)}50%{opacity:.4;box-shadow:0 0 0 5px rgba(59,130,246,0)}}
.controls-row{display:flex;gap:8px;margin-bottom:18px}
.ctl{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;background:var(--bg-silk);border:1px solid var(--line-cyber-strong);border-radius:6px;font-family:var(--font-display);font-size:10px;color:var(--text-muted);cursor:pointer;text-transform:uppercase;letter-spacing:.1em;transition:all .3s}
.ctl:hover,.ctl.on{border-color:var(--cyber-blue);color:var(--cyber-blue)}
.rail-cv{display:flex;flex-direction:column;gap:8px;margin-bottom:18px}.rail-cv .btn{width:100%;font-size:10px}
.rail-nav{flex:1}.rail-nav li{margin-bottom:4px}
.rail-nav a{display:flex;align-items:baseline;gap:12px;padding:8px 6px;font-size:12px;letter-spacing:.04em;color:var(--text-muted);border-left:2px solid transparent;transition:all .3s}
.rail-nav .num{color:var(--text-dim);font-family:var(--font-display);font-size:10px}
.rail-nav a:hover,.rail-nav a.active{color:var(--text-charcoal);border-left-color:var(--cyber-blue);padding-left:14px;background:var(--cyber-blue-dim)}
.rail-nav a.active .num{color:var(--cyber-blue)}
.rail-soc{display:flex;gap:8px;flex-wrap:wrap;margin:14px 0}
.rail-soc a{width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:var(--text-muted);border:1px solid var(--line-cyber);border-radius:6px;transition:all .3s}.rail-soc a:hover{color:var(--cyber-blue);border-color:var(--cyber-blue)}
.rail-foot{font-family:var(--font-display);font-size:9px;color:var(--text-dim);letter-spacing:.08em;line-height:2;padding-top:16px;border-top:1px solid var(--line-cyber)}
.backdrop{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:205;opacity:0;pointer-events:none;transition:opacity .3s}.backdrop.on{opacity:1;pointer-events:auto}
.topbar{display:none}

main{margin-left:var(--rail-w);position:relative;z-index:1}
section{padding:100px 80px;border-bottom:1px solid var(--line-cyber);position:relative}
.sec-head{margin-bottom:50px;max-width:720px}
.sec-head h2{font-family:var(--font-display);font-weight:700;font-size:clamp(26px,3.5vw,40px);margin-top:10px;letter-spacing:-.02em}
.sec-head p{color:var(--text-muted);font-size:15px;margin-top:10px;font-weight:300}

/* Hero */
#hero{padding:0 80px;min-height:100vh;display:flex;align-items:center;overflow:hidden}
.hero-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);gap:40px;align-items:center;width:100%;padding:60px 0}
h1.name{font-family:var(--font-display);font-weight:700;font-size:clamp(36px,5.5vw,76px);line-height:1.1;letter-spacing:-.03em;margin-top:18px}
.role{font-family:var(--font-display);font-size:clamp(13px,1.4vw,17px);color:var(--cyber-blue);margin-top:18px;letter-spacing:.05em;font-weight:500}
.lede{max-width:580px;color:var(--text-muted);font-size:15px;margin-top:22px;text-align:justify;font-weight:300}
.chips{margin-top:18px;max-width:580px}.chips .pill{font-size:10px}
.cta{display:flex;gap:14px;flex-wrap:wrap;margin-top:34px}
.dd{position:relative}.dd-menu{position:absolute;top:calc(100% + 6px);left:0;min-width:100%;background:var(--panel-lace);border:1px solid var(--line-cyber-strong);border-radius:8px;z-index:20;display:none;overflow:hidden}
.dd.open .dd-menu{display:block}.dd-menu a{display:flex;gap:10px;align-items:center;padding:12px 16px;font-size:12px;white-space:nowrap}.dd-menu a:hover{background:var(--cyber-blue-dim);color:var(--cyber-blue)}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:16px;margin-top:60px;border-top:1px solid var(--line-cyber);padding-top:30px;max-width:680px}
.stat{display:flex;gap:10px}.stat .ic{color:var(--cyber-blue);width:18px;height:18px;margin-top:6px}
.stat b{font-family:var(--font-display);font-size:28px;display:block;line-height:1.1}
.stat span{font-family:var(--font-display);font-size:9.5px;color:var(--text-dim);letter-spacing:.08em;text-transform:uppercase}
.avail{margin-top:34px;max-width:580px;padding:20px;border-color:var(--cyber-blue);background:linear-gradient(135deg,var(--cyber-blue-dim),transparent 70%),var(--panel-lace)}
.avail-badge{display:inline-flex;align-items:center;gap:8px;font-family:var(--font-display);font-size:10px;text-transform:uppercase;letter-spacing:.1em;color:var(--cyber-blue);margin-bottom:6px}
.avail h3{font-family:var(--font-display);font-size:16px;margin-bottom:6px}.avail p{font-size:13px;color:var(--text-muted);margin-bottom:12px;font-weight:300}.avail .row{margin-bottom:14px}
.notice{margin-top:30px;padding:18px;border:1px dashed var(--line-cyber-strong);border-radius:10px;color:var(--text-muted);font-size:13px;max-width:580px}

.hv{position:relative;width:100%;max-width:430px;margin:0 auto;aspect-ratio:1/1.05}
.hv-orb{position:absolute;inset:4% 4% 14%;border-radius:50%;overflow:hidden;border:1px solid var(--line-cyber-strong);background:radial-gradient(circle at 30% 25%,var(--cyber-blue-dim),transparent 70%),var(--panel-dusty)}
.hv-orb svg{position:absolute;inset:0;width:100%;height:100%}
.sweep{transform-origin:50% 50%;animation:sweep 6s linear infinite}@keyframes sweep{to{transform:rotate(360deg)}}
.blip{animation:blip 3s ease-in-out infinite}.blip.b2{animation-delay:1s}.blip.b3{animation-delay:2s}@keyframes blip{0%,100%{opacity:.15}50%{opacity:1}}
.hv-photo{position:absolute;inset:15% 15% 21%;border-radius:50%;overflow:hidden;border:2px solid var(--cyber-blue);background:var(--bg-silk);display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:700;font-size:clamp(34px,6vw,70px);color:var(--cyber-blue);box-shadow:0 0 50px -10px var(--cyber-blue)}
.hv-photo img{width:100%;height:100%;object-fit:cover}
.float{position:absolute;display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--panel-lace);border:1px solid var(--line-cyber-strong);border-radius:8px;animation:floaty 6s ease-in-out infinite;max-width:210px;z-index:2}
.float small{font-family:var(--font-display);font-size:9px;color:var(--text-dim);display:block}.float b{font-size:11px;display:block;line-height:1.2}
.float.f1{top:6%;right:0}.float.f2{top:28%;right:-3%;animation-delay:-2s}@keyframes floaty{0%,100%{translate:0 0}50%{translate:0 -8px}}
.note{position:absolute;font-family:var(--font-display);font-size:clamp(10px,1.2vw,12px);color:var(--cyber-blue);white-space:pre-line;line-height:1.5;z-index:2;letter-spacing:.08em;text-transform:uppercase}
.note.n1{top:14%;left:0}.note.n2{bottom:26%;right:0;text-align:right;color:var(--text-muted)}
.hv-strip{position:absolute;left:0;right:0;bottom:2%;display:flex;justify-content:space-around;gap:4px;padding:8px;background:var(--panel-lace);border:1px solid var(--line-cyber-strong);border-radius:8px;flex-wrap:wrap;z-index:2}
.tb{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:700;font-size:10px;color:var(--cyber-blue);border:1px solid var(--line-cyber-strong);background:var(--cyber-blue-dim)}

/* À propos */
.about-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(0,1fr);gap:60px;align-items:start}
.about-text h2{font-family:var(--font-display);font-size:clamp(24px,3vw,36px);line-height:1.15;margin:12px 0 22px;letter-spacing:-.02em}
.about-text p{color:var(--text-muted);font-size:15px;margin-bottom:18px;max-width:640px;text-align:justify;font-weight:300}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin:26px 0}
.info{padding:18px}.info .ico-sq{margin-bottom:12px}.info small{font-family:var(--font-display);font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:.1em;display:block}
.info b{display:block;font-size:14px;margin-top:3px}.info .sub{font-size:12px;color:var(--text-muted)}
.journey{padding:24px;position:sticky;top:20px}.journey h3{font-family:var(--font-display);font-size:16px;margin-bottom:18px;color:var(--cyber-blue)}
.tl-mini li{display:grid;grid-template-columns:14px 1fr;gap:12px;padding-bottom:16px;position:relative}
.tl-mini li::before{content:'';position:absolute;left:4px;top:14px;bottom:-2px;width:1px;background:var(--line-cyber-strong)}.tl-mini li:last-child::before{display:none}
.tl-mini i{width:9px;height:9px;background:var(--cyber-blue);transform:rotate(45deg);margin-top:6px}
.tl-mini small{font-family:var(--font-display);font-size:10px;color:var(--text-dim);display:block}.tl-mini b{font-size:13px;display:block;line-height:1.3}.tl-mini span{font-size:11.5px;color:var(--text-muted)}

/* Parcours */
.exp-item{display:grid;grid-template-columns:220px 1fr;gap:50px;padding:40px 0;border-bottom:1px solid var(--line-cyber)}.exp-item:first-of-type{padding-top:0}
.exp-date{font-family:var(--font-display);font-size:11px;color:var(--text-dim);padding-top:6px;letter-spacing:.05em;text-transform:uppercase}
.exp-role{font-family:var(--font-display);font-size:20px;font-weight:700}
.exp-org{font-family:var(--font-display);font-size:11px;color:var(--cyber-blue);margin:6px 0 18px;text-transform:uppercase;letter-spacing:.08em}
.exp-bullets li{position:relative;padding-left:20px;color:var(--text-muted);font-size:14px;margin-bottom:10px;text-align:justify;font-weight:300}
.exp-bullets li::before{content:'';position:absolute;left:0;top:9px;width:6px;height:6px;background:var(--cyber-blue);transform:rotate(45deg)}

/* Compétences */
.skills-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:20px}
.skill{padding:28px}.skill h3{display:flex;align-items:center;gap:12px;font-family:var(--font-display);font-size:17px;color:var(--cyber-blue);margin-bottom:18px;padding-bottom:12px;border-bottom:1px solid var(--line-cyber)}

/* Projets */
.filters{display:inline-flex;flex-wrap:wrap;gap:4px;padding:4px;border:1px solid var(--line-cyber-strong);border-radius:8px;background:var(--panel-lace);margin-bottom:28px;max-width:100%}
.filters button{border:0;background:transparent;padding:8px 14px;font-family:var(--font-display);font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;color:var(--text-muted);cursor:pointer;border-radius:5px;transition:all .2s;white-space:nowrap}
.filters button.on{background:var(--cyber-blue);color:#fff;font-weight:600}.filters button:hover:not(.on){color:var(--cyber-blue)}
.proj-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,300px),1fr));gap:28px}
.proj{display:flex;flex-direction:column;overflow:hidden}.proj:hover{transform:translateY(-4px)}
.pop{animation:pop .45s backwards}@keyframes pop{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
.pm{height:150px;position:relative;overflow:hidden;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--cyber-blue);background:linear-gradient(135deg,var(--cyber-blue-dim),var(--panel-dusty));border-bottom:1px solid var(--line-cyber)}
.pm img,.pm video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .5s}.proj:hover .pm img,.proj:hover .pm video{transform:scale(1.06)}
.pm .ic{width:38px;height:38px}
.vbadge{position:absolute;left:10px;bottom:10px;display:inline-flex;align-items:center;gap:5px;background:rgba(0,0,0,.8);color:var(--cyber-blue);font-family:var(--font-display);font-size:9px;text-transform:uppercase;padding:4px 10px;border:1px solid var(--cyber-blue);border-radius:4px;z-index:2}.vbadge .ic{width:10px;height:10px}
.pb{padding:22px 26px 10px;flex:1;display:flex;flex-direction:column}
.ph{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:14px}.ph .meta{font-family:var(--font-display);font-size:10px;color:var(--text-dim);letter-spacing:.05em;text-transform:uppercase}
.pb h3{font-family:var(--font-display);font-size:18px;line-height:1.25;margin-bottom:10px;cursor:pointer}.pb h3:hover{color:var(--cyber-blue)}
.pb p{font-size:13.5px;color:var(--text-muted);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:14px;white-space:pre-line;font-weight:300}
.pb .row{margin-top:auto;max-height:56px;overflow:hidden}
.pf{padding:14px 26px 22px;display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap}
.status{font-family:var(--font-display);font-size:9px;letter-spacing:.08em;text-transform:uppercase;padding:4px 10px;border:1px solid var(--line-cyber-strong);color:var(--cyber-blue);background:var(--cyber-blue-dim);border-radius:4px}
.lnks{display:flex;gap:12px;flex-wrap:wrap}.lnk{display:inline-flex;align-items:center;gap:5px;font-family:var(--font-display);font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;color:var(--cyber-blue);cursor:pointer;background:none;border:0;padding:4px 0}.lnk:hover{gap:9px}
.more-wrap{display:flex;justify-content:center;margin-top:36px}

/* Formation / certifs */
.edu-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:40px;align-items:start}
.edu-list{display:grid;gap:14px}.edu{display:flex;gap:14px;padding:20px;align-items:flex-start}
.edu h4{font-family:var(--font-display);font-size:16px;line-height:1.3}.edu .org{font-family:var(--font-display);font-size:11px;color:var(--cyber-blue);margin-top:6px;text-transform:uppercase;letter-spacing:.05em}
.edu .yr{margin-left:auto;font-family:var(--font-display);font-size:10px;color:var(--text-dim);text-align:right;max-width:150px;flex-shrink:0}
.hl{padding:28px;position:sticky;top:20px;border-color:var(--cyber-blue);background:linear-gradient(135deg,var(--cyber-blue-dim),transparent),var(--panel-lace)}
.hl .ico-sq{margin-bottom:16px}.hl h3{font-family:var(--font-display);font-size:19px;margin-bottom:8px}.hl p{font-size:13.5px;color:var(--text-muted);font-weight:300}
.docs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,340px),1fr));gap:18px}
.doc{display:flex;gap:16px;padding:20px;align-items:flex-start}
.doc small.t{font-family:var(--font-display);font-size:9.5px;letter-spacing:.1em;text-transform:uppercase;color:var(--cyber-blue);display:block;margin-bottom:4px}
.doc h4{font-family:var(--font-display);font-size:15px;line-height:1.3}.doc p{font-size:12px;color:var(--text-muted);margin-top:4px}

/* Blog */
.blog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,320px),1fr));gap:28px}
.bc{padding:26px;display:flex;flex-direction:column}
.bc .bt{display:flex;justify-content:space-between;gap:8px;margin-bottom:14px;font-family:var(--font-display);font-size:10px;color:var(--text-dim);text-transform:uppercase}.bc .bt span:first-child{color:var(--cyber-blue)}
.bc h3{font-family:var(--font-display);font-size:17px;margin-bottom:12px;line-height:1.25}
.bm{height:130px;background:#000;margin-bottom:14px;border-radius:8px;position:relative;overflow:hidden;cursor:pointer}
.bm img,.bm video{width:100%;height:100%;object-fit:cover}.bm .vbadge{left:50%;top:50%;bottom:auto;transform:translate(-50%,-50%)}
.bm .ext{height:100%;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-size:11px;color:var(--text-dim)}
.bc p{font-size:13.5px;color:var(--text-muted);margin-bottom:16px;flex:1;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;white-space:pre-line;text-align:justify;font-weight:300}

/* Contact */
.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:start}
.c-list{margin-bottom:24px}.c-list li{border-top:1px solid var(--line-cyber);padding:22px 0;display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}.c-list li:last-child{border-bottom:1px solid var(--line-cyber)}
.c-label{font-family:var(--font-display);font-size:10px;color:var(--text-dim);letter-spacing:.1em;text-transform:uppercase}
.c-val{font-family:var(--font-display);font-size:15px;font-weight:700}a.c-val:hover{color:var(--cyber-blue)}
.c-btns{display:flex;gap:12px;flex-wrap:wrap}
.cv-panel{padding:22px;margin-bottom:22px}.cv-panel h3{font-family:var(--font-display);font-size:14px;color:var(--cyber-blue);margin-bottom:14px}
.cv-row{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--panel-dusty);border:1px solid var(--line-cyber);border-radius:8px;margin-bottom:8px;font-size:13px;transition:all .2s}.cv-row:hover{border-color:var(--cyber-blue);transform:translateX(3px)}.cv-row .ic:last-child{margin-left:auto;color:var(--cyber-blue)}
.pitch{color:var(--text-muted);font-size:15px;max-width:420px;text-align:justify;font-weight:300}
.hand{font-family:var(--font-display);font-size:12px;color:var(--cyber-blue);margin-top:22px;letter-spacing:.06em;text-transform:uppercase}
footer{margin-left:var(--rail-w);padding:32px 80px;border-top:1px solid var(--line-cyber-strong);display:flex;justify-content:space-between;align-items:center;gap:20px;flex-wrap:wrap;position:relative;z-index:1;font-family:var(--font-display);font-size:9.5px;color:var(--text-dim);letter-spacing:.05em}
.f-soc{display:flex;gap:20px;flex-wrap:wrap}.f-soc a{color:var(--cyber-blue);text-transform:uppercase;letter-spacing:.1em;font-weight:600}.f-soc a:hover{color:var(--text-charcoal)}

/* Modale */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:500;display:none;align-items:center;justify-content:center;padding:16px;overflow-y:auto}.modal.open{display:flex}
.modal-box{background:var(--panel-lace);border:1px solid var(--cyber-blue);border-radius:12px;width:100%;max-width:960px;max-height:92vh;overflow-y:auto;padding:26px;position:relative;box-shadow:0 0 40px var(--cyber-blue-dim)}
.modal-x{float:right;width:34px;height:34px;border:1px solid var(--line-cyber-strong);border-radius:6px;background:var(--panel-dusty);cursor:pointer;display:flex;align-items:center;justify-content:center;margin-left:10px}.modal-x:hover{border-color:var(--cyber-blue);color:var(--cyber-blue)}
.mv .mm{display:flex;gap:10px;flex-wrap:wrap;align-items:center;font-family:var(--font-display);font-size:10px;color:var(--text-dim);margin-bottom:12px;text-transform:uppercase;border-bottom:1px solid var(--line-cyber);padding-bottom:10px}
.mv h3{font-family:var(--font-display);font-size:clamp(18px,2.4vw,24px);color:var(--cyber-blue);line-height:1.25;margin-bottom:18px}
.mv .media{width:100%;aspect-ratio:16/9;background:#000;border:1px solid var(--line-cyber-strong);border-radius:8px;overflow:hidden;margin-bottom:18px;display:flex;align-items:center;justify-content:center}
.mv .media video,.mv .media img,.mv .media iframe{width:100%;height:100%;object-fit:contain;border:0}
.mv .txt{white-space:pre-line;font-size:14px;line-height:1.8;margin-bottom:16px;text-align:justify;font-weight:300}
.proofs-layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:20px;height:62vh}
.proofs-sidebar{border-right:1px solid var(--line-cyber);padding-right:14px;overflow-y:auto;display:flex;flex-direction:column;gap:8px}
.proofs-viewer{display:flex;flex-direction:column;gap:12px;min-height:0;background:var(--panel-dusty);border:1px solid var(--line-cyber-strong);border-radius:8px;padding:14px}
.proofs-viewer .media{flex:1;aspect-ratio:auto;margin:0;min-height:260px}
.pbtn{width:100%;text-align:left;padding:9px 12px;background:transparent;border:1px solid var(--line-cyber);border-radius:6px;font-size:12px;cursor:pointer;transition:all .2s}.pbtn:hover,.pbtn.active{border-color:var(--cyber-blue);color:var(--cyber-blue);background:var(--cyber-blue-dim)}
.fabs{position:fixed;right:20px;bottom:20px;z-index:160;display:flex;flex-direction:column;gap:10px;align-items:center}
.fab{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;border:0;cursor:pointer;transition:transform .2s,opacity .3s}.fab:hover{transform:scale(1.1)}
.fab-wa{background:#25D366;color:#fff}.fab-wa .ic{width:22px;height:22px}
.fab-up{background:var(--panel-lace);color:var(--cyber-blue);border:1px solid var(--cyber-blue);opacity:0;pointer-events:none;width:40px;height:40px}.fab-up.show{opacity:1;pointer-events:auto}
.toast{position:fixed;left:50%;bottom:24px;transform:translate(-50%,40px);background:var(--panel-lace);border:1px solid var(--cyber-blue);padding:10px 18px;display:flex;gap:8px;align-items:center;z-index:600;opacity:0;transition:all .3s;font-family:var(--font-display);font-size:11px;pointer-events:none;border-radius:6px}.toast.on{opacity:1;transform:translate(-50%,0)}
.reveal{opacity:0;transform:translateY(25px);transition:opacity .8s,transform .8s}.reveal.in{opacity:1;transform:none}

@media(max-width:1100px){
 :root{--rail-w:0px}
 .rail{transform:translateX(-100%);width:280px}.rail.open{transform:none}
 .topbar{display:flex;position:fixed;top:0;left:0;right:0;height:var(--top-h);z-index:150;background:var(--panel-lace);border-bottom:1px solid var(--line-cyber-strong);align-items:center;justify-content:space-between;padding:0 20px}
 .topbar .tb-name{font-family:var(--font-display);font-size:15px;font-weight:700}
 .burger{width:38px;height:38px;border:1px solid var(--line-cyber-strong);border-radius:6px;background:transparent;cursor:pointer;display:flex;align-items:center;justify-content:center}.burger .ic{width:20px;height:20px}
 main,footer{margin-left:0}main{padding-top:var(--top-h)}
 section{padding:70px 24px}#hero{padding:0 24px;min-height:auto}
 .hero-grid,.about-grid,.edu-grid,.contact-grid{grid-template-columns:1fr;gap:40px}.hv{max-width:340px}.journey,.hl{position:static}
 .exp-item{grid-template-columns:1fr;gap:12px}footer{padding:28px 24px;flex-direction:column;align-items:flex-start}
 .proofs-layout{grid-template-columns:1fr;height:auto}.proofs-sidebar{border-right:0;border-bottom:1px solid var(--line-cyber);padding:0 0 12px;max-height:150px}
}
@media(max-width:768px){.filters{width:100%;overflow-x:auto;flex-wrap:nowrap}.filters button{flex-shrink:0}}
@media(max-width:560px){section{padding:56px 16px}.btn{width:100%}.cta{flex-direction:column}.dd,.dd>.btn{width:100%}.edu{flex-wrap:wrap}.edu .yr{margin:0;text-align:left;max-width:none}.hv-strip .tb:nth-child(n+7){display:none}.float{transform:scale(.85);transform-origin:bottom right}.modal-box{padding:18px}}
@media(prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}.reveal{opacity:1;transform:none}}
</style>
</head>
<body>
<div class="backdrop" id="backdrop"></div>

<nav class="rail" id="rail" aria-label="Navigation">
  <a class="brand" href="#hero">
    <span class="mark"><?php if ($photo): ?><img src="<?= s($photo) ?>" alt=""><?php else: ?><?= s(initials($data)) ?><?php endif; ?></span>
    <span><b><?= s($fullName) ?></b><?php if (has($h['brand_sub'])): ?><small><?= T($h['brand_sub']) ?></small><?php endif; ?></span>
  </a>
  <div class="controls-row">
    <button class="ctl" id="themeBtn" type="button"><?= icon('moon') ?><span id="themeLbl"></span></button>
    <button class="ctl lang-btn" data-l="fr" type="button">FR</button>
    <button class="ctl lang-btn" data-l="en" type="button">EN</button>
  </div>
  <div class="rail-status"><span class="dot-live"></span><?= U('node') ?></div>
  <?php if ($cvs): ?><div class="rail-cv"><?php foreach ($cvs as $cv): ?><a href="<?= s($cv['p']) ?>" download class="btn btn-cv"><?= icon('download') ?><?= U($cv['k']) ?></a><?php endforeach; ?></div><?php endif; ?>
  <ul class="rail-nav">
    <li><a href="#hero" class="active"><span class="num">00</span><span><?= U('nav_home') ?></span></a></li>
    <?php $n = 1; foreach ($navIcons as $k => $_): if (!$show[$k]) continue; ?>
      <li><a href="#<?= $k ?>"><span class="num"><?= str_pad($n++, 2, '0', STR_PAD_LEFT) ?></span><span><?= U('nav_' . $k) ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <?php if ($socials): ?><div class="rail-soc"><?php foreach ($socials as $so): ?><a href="<?= s($so['url']) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= s($so['name']) ?>" title="<?= s($so['name']) ?>"><?= icon(social_icon($so['name'])) ?></a><?php endforeach; ?></div><?php endif; ?>
  <div class="rail-foot">© <?= date('Y') ?> <?= s($fullName) ?><br><?= U('rights') ?></div>
</nav>

<header class="topbar">
  <span class="tb-name"><?= s($fullName) ?></span>
  <button class="burger" id="burger" type="button" aria-label="<?= s(UP('menu', 'fr')) ?>"><?= icon('menu') ?></button>
</header>

<main>
<section id="hero">
  <div class="hero-grid">
    <div>
      <?php if (has($h['tag'])): ?><div class="eyebrow reveal"><?= T($h['tag']) ?></div><?php endif; ?>
      <?php if ($fullName !== ''): ?><h1 class="name reveal"><?= s($h['first_name']) ?><br><span class="grad"><?= s($h['last_name']) ?></span></h1><?php endif; ?>
      <?php if (has($h['role'])): ?><div class="role reveal"><?= T($h['role']) ?></div><?php endif; ?>
      <?php if (has($h['lede'])): ?><p class="lede reveal"><?= T($h['lede']) ?></p><?php endif; ?>
      <?php if ($tech): ?><div class="row chips reveal"><?php foreach ($tech as $t): ?><span class="pill"><?= s($t) ?></span><?php endforeach; ?></div><?php endif; ?>
      <?php if ($isEmpty): ?><div class="notice"><?= U('empty_notice') ?></div><?php endif; ?>
      <div class="cta reveal">
        <?php if ($show['projects']): ?><a href="#projects" class="btn btn-primary"><?= U('btn_projects') ?><?= icon('arrow') ?></a><?php endif; ?>
        <?php if (count($cvs) > 1): ?>
          <div class="dd" id="cvDd"><button class="btn btn-cv" type="button"><?= icon('download') ?><?= U('btn_cv') ?><?= icon('chevron') ?></button>
            <div class="dd-menu"><?php foreach ($cvs as $cv): ?><a href="<?= s($cv['p']) ?>" download><?= icon('download') ?><?= U($cv['k']) ?></a><?php endforeach; ?></div></div>
        <?php elseif ($cvs): ?><a href="<?= s($cvs[0]['p']) ?>" download class="btn btn-cv"><?= icon('download') ?><?= U('btn_cv') ?></a><?php endif; ?>
        <?php if ($show['contact']): ?><a href="#contact" class="btn btn-ghost"><?= U('btn_contact_me') ?></a><?php endif; ?>
      </div>
      <?php if ($h['stats']): ?><div class="stats reveal"><?php foreach ($h['stats'] as $st): ?>
        <div class="stat"><?= icon($st['icon'] ?? 'spark') ?><div><b><?= s($st['val'] ?? '') ?></b><span><?= T($st['label'] ?? '') ?></span></div></div><?php endforeach; ?></div><?php endif; ?>
      <?php if (has($data['availability']['title'])): ?>
      <aside class="avail card reveal">
        <div class="avail-badge"><span class="dot-live"></span><?= U('available') ?></div>
        <h3><?= T($data['availability']['title']) ?></h3>
        <?php if (has($data['availability']['text'])): ?><p><?= T($data['availability']['text']) ?></p><?php endif; ?>
        <?php if (has($data['availability']['tags'])): ?><div class="row"><?= pills($data['availability']['tags'], 'tag') ?></div><?php endif; ?>
        <div class="c-btns">
          <?php if ($waNum): ?><a class="btn btn-wa btn-sm" data-wa href="https://wa.me/<?= s($waNum) ?>" target="_blank" rel="noopener"><?= icon('wa') ?><?= U('btn_discuss') ?></a><?php endif; ?>
          <?php if ($show['contact']): ?><a class="btn btn-ghost btn-sm" href="#contact"><?= icon('mail') ?><?= U('btn_contact_me') ?></a><?php endif; ?>
        </div>
      </aside>
      <?php endif; ?>
    </div>

    <div class="hv">
      <div class="hv-orb"><svg viewBox="0 0 400 400" aria-hidden="true">
        <g fill="none" stroke="var(--line-cyber-strong)"><circle cx="200" cy="200" r="60"/><circle cx="200" cy="200" r="120"/><circle cx="200" cy="200" r="180"/><path d="M0 200h400M200 0v400"/></g>
        <defs><linearGradient id="sg" x1="200" y1="200" x2="330" y2="60" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="var(--cyber-blue)" stop-opacity=".45"/><stop offset="1" stop-color="var(--cyber-blue)" stop-opacity="0"/></linearGradient></defs>
        <g class="sweep"><path d="M200 200L200 0A200 200 0 0 1 340 58Z" fill="url(#sg)"/></g>
        <circle class="blip" cx="110" cy="120" r="4" fill="var(--cyber-blue)"/><circle class="blip b2" cx="300" cy="270" r="4" fill="var(--cyber-blue-2)"/><circle class="blip b3" cx="280" cy="110" r="4" fill="var(--cyber-blue)"/></svg></div>
      <div class="hv-photo"><?php if ($photo): ?><img src="<?= s($photo) ?>" alt="<?= s($fullName) ?>"><?php else: ?><?= s(initials($data)) ?><?php endif; ?></div>
      <?php if (has($h['note1'])): ?><div class="note n1"><?= T($h['note1']) ?></div><?php endif; ?>
      <?php if (has($h['note2'])): ?><div class="note n2"><?= T($h['note2']) ?></div><?php endif; ?>
      <?php if ($socials): $so = $socials[0]; ?><a class="float f1" href="<?= s($so['url']) ?>" target="_blank" rel="noopener noreferrer"><span class="ico-sq"><?= icon(social_icon($so['name'])) ?></span><span><small><?= s($so['name']) ?></small><b><?= s(preg_replace('#^https?://(www\.)?#', '', rtrim($so['url'], '/'))) ?></b></span></a><?php endif; ?>
      <?php if ($experiences): $e0 = $experiences[0]; ?><div class="float f2"><span class="ico-sq"><?= icon('briefcase') ?></span><span><small><?= T($e0['role'] ?? '') ?></small><b><?= T($e0['org'] ?? '') ?></b></span></div><?php endif; ?>
      <?php if ($tech): ?><div class="hv-strip"><?php foreach (array_slice($tech, 0, 8) as $t): ?><span class="tb" title="<?= s($t) ?>"><?= s(mb_strtoupper(mb_substr($t, 0, 2))) ?></span><?php endforeach; ?></div><?php endif; ?>
    </div>
  </div>
</section>

<?php if ($show['about']): ?>
<section id="about"><div class="about-grid">
  <div class="about-text">
    <div class="reveal"><span class="eyebrow"><?= U('about_eyebrow') ?></span><?php if (has($data['profile']['title'])): ?><h2><?= T($data['profile']['title']) ?></h2><?php endif; ?></div>
    <?php if (has($data['profile']['text1'])): ?><p class="reveal"><?= T($data['profile']['text1']) ?></p><?php endif; ?>
    <?php if (has($data['profile']['text2'])): ?><p class="reveal"><?= T($data['profile']['text2']) ?></p><?php endif; ?>
    <?php if ($data['about_cards']): ?><div class="info-grid"><?php foreach ($data['about_cards'] as $ac): ?>
      <div class="card info reveal"><span class="ico-sq"><?= icon($ac['icon'] ?? 'spark') ?></span><small><?= T($ac['label'] ?? '') ?></small><b><?= T($ac['line1'] ?? '') ?></b><span class="sub"><?= T($ac['line2'] ?? '') ?></span></div><?php endforeach; ?></div><?php endif; ?>
    <?php if ($show['experience']): ?><a href="#experience" class="btn btn-ghost reveal"><?= U('about_full') ?><?= icon('arrow') ?></a><?php endif; ?>
  </div>
  <?php if ($experiences): ?><aside class="card journey reveal"><h3><?= U('about_journey') ?></h3><ul class="tl-mini">
    <?php foreach (array_slice($experiences, 0, 5) as $e): ?><li><i></i><div><small><?= T($e['date'] ?? '') ?></small><b><?= T($e['role'] ?? '') ?></b><span><?= T($e['org'] ?? '') ?></span></div></li><?php endforeach; ?>
  </ul></aside><?php endif; ?>
</div></section>
<?php endif; ?>

<?php if ($show['experience']): ?>
<section id="experience">
  <?php sec_head('exp_eyebrow', 'exp_title', 'exp_sub', ['n' => count($experiences)]); ?>
  <?php foreach ($experiences as $e): $g = proof_group($J, $e['docs'] ?? []); ?>
    <div class="exp-item reveal">
      <div class="exp-date"><?= T($e['date'] ?? '') ?></div>
      <div>
        <div class="exp-role"><?= T($e['role'] ?? '') ?></div><div class="exp-org"><?= T($e['org'] ?? '') ?></div>
        <?= TB($e['bullets'] ?? '', fn($t) => '<ul class="exp-bullets">' . implode('', array_map(fn($l) => '<li>' . s($l) . '</li>', lines($t))) . '</ul>') ?>
        <?php if (has($e['tags'] ?? '')): ?><div class="row" style="margin-top:14px"><?= pills($e['tags'], 'tag') ?></div><?php endif; ?>
        <?= proof_btn($g) ?>
      </div>
    </div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($show['skills']): ?>
<section id="skills">
  <?php sec_head('skills_eyebrow', 'skills_title', 'skills_sub'); ?>
  <div class="skills-grid"><?php foreach ($data['skills'] as $g): ?>
    <div class="card skill reveal"><h3><span class="ico-sq"><?= icon($g['icon'] ?? 'spark') ?></span><?= T($g['group'] ?? '') ?></h3><div class="row"><?= pills($g['items'] ?? '') ?></div></div>
  <?php endforeach; ?></div>
</section>
<?php endif; ?>

<?php if ($show['projects']): ?>
<section id="projects" data-filter-root data-size="6">
  <?php sec_head('proj_eyebrow', 'proj_title', 'proj_sub'); ?>
  <div class="filters reveal"><button type="button" class="on" data-f="all"><?= U('filter_all') ?></button>
    <?php foreach ($catOrder as $k) foreach ($projects as $p) if ($p['_cat'] === $k) { echo '<button type="button" data-f="' . s($k) . '">' . U('cat_' . $k) . '</button>'; break; } ?></div>
  <div class="proj-grid">
    <?php foreach ($projects as $i => $p): $cat = $p['_cat']; $video = file_ok($p['demo_video'] ?? ''); $cover = file_ok($p['cover'] ?? ''); $live = safe_src($p['live_url'] ?? ''); $demo = safe_src($p['demo_url'] ?? ''); ?>
    <article class="card proj" data-item data-cat="<?= s($cat) ?>">
      <div class="pm" data-proj="<?= $i ?>">
        <?php if ($cover): ?><img src="<?= s($p['cover']) ?>" alt="" loading="lazy"><?php elseif ($video): ?><video src="<?= s($p['demo_video']) ?>#t=0.6" muted playsinline preload="metadata"></video><?php else: ?><?= icon($catIcon[$cat]) ?><?php endif; ?>
        <?php if ($video): ?><span class="vbadge"><?= icon('play') ?><?= U('video') ?></span><?php endif; ?>
      </div>
      <div class="pb">
        <div class="ph"><span class="ico-sq"><?= icon($catIcon[$cat]) ?></span><span class="meta"><?= U('cat_' . $cat) ?> · <?= s($p['year'] ?? '') ?></span></div>
        <h3 data-proj="<?= $i ?>"><?= T($p['title'] ?? '') ?></h3><p><?= T($p['desc'] ?? '') ?></p>
        <div class="row"><?php foreach (array_slice(csv(lang_val($p['tags'] ?? '', 'fr')), 0, 5) as $tg): ?><span class="tag"><?= s($tg) ?></span><?php endforeach; ?></div>
      </div>
      <div class="pf"><span class="status"><?= !empty($p['confidential']) ? U('confidential') : T($p['status'] ?? '') ?></span>
        <div class="lnks"><button class="lnk" type="button" data-proj="<?= $i ?>"><?= U('details') ?><?= icon('arrow') ?></button>
          <?php if ($live): ?><a class="lnk" href="<?= s($live) ?>" target="_blank" rel="noopener noreferrer"><?= U('view_project') ?><?= icon('external') ?></a><?php endif; ?>
          <?php if ($demo): ?><a class="lnk" href="<?= s($demo) ?>" target="_blank" rel="noopener noreferrer"><?= U('demo') ?><?= icon('external') ?></a><?php endif; ?></div></div>
    </article>
    <?php endforeach; ?>
  </div>
  <div class="more-wrap"><button class="btn btn-ghost more-btn" type="button" hidden></button></div>
</section>
<?php endif; ?>

<?php if ($show['education']): ?>
<section id="education">
  <?php sec_head('edu_eyebrow', 'edu_title', 'edu_sub'); ?>
  <div class="edu-grid">
    <div class="edu-list"><?php foreach ($data['education'] as $ed): $g = proof_group($J, $ed['docs'] ?? []); ?>
      <div class="card edu reveal"><span class="ico-sq"><?= icon('cap') ?></span>
        <div><h4><?= T($ed['title'] ?? '') ?></h4><div class="org"><?= T($ed['org'] ?? '') ?></div><?= proof_btn($g) ?></div>
        <span class="yr"><?= T($ed['meta'] ?? '') ?></span></div>
    <?php endforeach; ?></div>
    <?php if (has($data['education_note']['title'])): ?><aside class="card hl reveal"><span class="ico-sq"><?= icon('user') ?></span><h3><?= T($data['education_note']['title']) ?></h3><p><?= T($data['education_note']['text']) ?></p></aside><?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($show['certs']): ?>
<section id="certs" data-filter-root data-size="8">
  <?php sec_head('certs_eyebrow', 'certs_title', 'certs_sub'); ?>
  <?php if (count($usedTypes) > 1): ?><div class="filters reveal"><button type="button" class="on" data-f="all"><?= U('filter_all') ?></button>
    <?php foreach ($usedTypes as $k): ?><button type="button" data-f="<?= s($k) ?>"><?= U($certTypes[$k]) ?></button><?php endforeach; ?></div><?php endif; ?>
  <div class="docs-grid"><?php foreach ($certs as $ce): $g = proof_group($J, $ce['docs'] ?? [], $ce['file'] ?? ''); ?>
    <article class="card doc" data-item data-cat="<?= s($ce['_type']) ?>">
      <span class="ico-sq"><?= icon($ce['_type'] === 'work' ? 'briefcase' : ($ce['_type'] === 'diploma' ? 'cap' : 'award')) ?></span>
      <div><small class="t"><?= U($certTypes[$ce['_type']]) ?></small><h4><?= T($ce['title'] ?? '') ?></h4>
        <p><?= T($ce['org'] ?? '') ?><?php if (has($ce['date'] ?? '')): ?> · <?= T($ce['date']) ?><?php endif; ?></p><?= proof_btn($g) ?></div>
    </article><?php endforeach; ?></div>
  <div class="more-wrap"><button class="btn btn-ghost more-btn" type="button" hidden></button></div>
</section>
<?php endif; ?>

<?php if ($show['blog']): ?>
<section id="blog" data-filter-root data-size="4">
  <?php sec_head('blog_eyebrow', 'blog_title', 'blog_sub'); ?>
  <div class="blog-grid"><?php foreach ($data['blog'] as $i => $b): $type = $b['media_type'] ?? 'none'; $src = safe_src($b['media_path'] ?? ''); ?>
    <article class="card bc" data-item>
      <div class="bt"><span><?= T($b['category'] ?? '') ?></span><span><?= T($b['date'] ?? '') ?></span></div>
      <h3><?= T($b['title'] ?? '') ?></h3>
      <?php if ($src && $type === 'image'): ?><div class="bm" data-blog="<?= $i ?>"><img src="<?= s($src) ?>" alt="" loading="lazy"></div>
      <?php elseif ($src && $type === 'local_video'): ?><div class="bm" data-blog="<?= $i ?>"><video src="<?= s($src) ?>#t=0.6" muted playsinline preload="metadata"></video><span class="vbadge"><?= icon('play') ?><?= U('watch') ?></span></div>
      <?php elseif ($src && $type === 'video_url'): ?><div class="bm" data-blog="<?= $i ?>"><div class="ext"><?= U('external_stream') ?></div><span class="vbadge"><?= icon('play') ?><?= U('watch') ?></span></div><?php endif; ?>
      <p><?= T($b['desc'] ?? '') ?></p>
      <button class="btn btn-cv btn-sm" type="button" data-blog="<?= $i ?>"><?= U('open_story') ?><?= icon('arrow') ?></button>
    </article><?php endforeach; ?></div>
  <div class="more-wrap"><button class="btn btn-ghost more-btn" type="button" hidden></button></div>
</section>
<?php endif; ?>

<?php if ($show['contact']): ?>
<section id="contact">
  <div class="sec-head reveal"><span class="eyebrow"><?= U('contact_eyebrow') ?></span><h2><?= U('contact_title') ?></h2><p><?= U('contact_sub') ?></p></div>
  <div class="contact-grid">
    <div class="reveal">
      <ul class="c-list">
        <?php foreach ($emails as $em): ?><li><span class="c-label">Email</span><a class="c-val" href="mailto:<?= s($em) ?>"><?= s($em) ?></a></li><?php endforeach; ?>
        <?php foreach ($phones as $ph): ?><li><span class="c-label">Tel</span><a class="c-val" href="tel:<?= s(preg_replace('/[^\d+]/', '', $ph)) ?>"><?= s($ph) ?></a></li><?php endforeach; ?>
        <?php foreach ($locs as $lo): ?><li><span class="c-label"><?= icon('pin') ?></span><span class="c-val"><?= s($lo) ?></span></li><?php endforeach; ?>
        <?php if (has($c['status'])): ?><li><span class="c-label"><?= icon('clock') ?></span><span class="c-val"><?= T($c['status']) ?></span></li><?php endif; ?>
      </ul>
      <div class="c-btns">
        <?php if ($emails): ?><a class="btn btn-primary" href="mailto:<?= s($emails[0]) ?>"><?= icon('send') ?><?= U('send_message') ?></a><?php endif; ?>
        <?php if ($waNum): ?><a class="btn btn-wa" data-wa href="https://wa.me/<?= s($waNum) ?>" target="_blank" rel="noopener"><?= icon('wa') ?><?= U('whatsapp_btn') ?></a><?php endif; ?>
        <?php if ($emails): ?><button class="btn btn-ghost" id="copyMail" type="button"><?= icon('copy') ?><?= U('copy_email') ?></button><?php endif; ?>
      </div>
    </div>
    <div class="reveal">
      <?php if ($cvs): ?><div class="card cv-panel"><h3><?= U('cv_panel') ?></h3><?php foreach ($cvs as $cv): ?><a class="cv-row" href="<?= s($cv['p']) ?>" download><?= icon('file') ?><b><?= U($cv['k']) ?></b><?= icon('download') ?></a><?php endforeach; ?></div><?php endif; ?>
      <p class="pitch"><?= U('contact_pitch') ?></p>
      <?php if (has($c['interests'])): ?><div class="row" style="margin-top:22px"><?= pills($c['interests'], 'tag') ?></div><?php endif; ?>
      <div class="hand">// <?= U('always_open') ?></div>
    </div>
  </div>
</section>
<?php endif; ?>
</main>

<footer>
  <span>© <?= date('Y') ?> <?= s($fullName) ?> · <?= U('rights') ?></span>
  <?php if ($socials): ?><div class="f-soc"><?php foreach ($socials as $so): ?><a href="<?= s($so['url']) ?>" target="_blank" rel="noopener noreferrer"><?= s($so['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
  <span><?= U('last_update') ?> : <time id="upd"></time></span>
</footer>

<div class="fabs">
  <button class="fab fab-up" id="toTop" type="button" aria-label="<?= s(UP('back_top', 'fr')) ?>"><?= icon('up') ?></button>
  <?php if ($waNum): ?><a class="fab fab-wa" data-wa href="https://wa.me/<?= s($waNum) ?>" target="_blank" rel="noopener" aria-label="WhatsApp"><?= icon('wa') ?></a><?php endif; ?>
</div>
<div class="toast" id="toast"><?= icon('check') ?><span></span></div>
<div class="modal" id="modal" role="dialog" aria-modal="true"><div class="modal-box"><button class="modal-x" id="modalX" type="button" aria-label="Close"><?= icon('x') ?></button><div id="modalBody"></div></div></div>

<script>window.__D=<?= json_encode($J, $jflags) ?>;window.__WA=<?= json_encode(UB('whatsapp_msg'), $jflags) ?>;</script>
<script>
(function(){'use strict';
var D=window.__D,html=document.documentElement;
var $=function(s,r){return (r||document).querySelector(s)},$$=function(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))};
var L=function(){return html.dataset.lang==='en'?'en':'fr'};
var pick=function(v){return v&&typeof v==='object'?(v[L()]||v.fr||v.en||''):(v||'')};
var tt=function(k){return pick(D.ui[k])};
function store(k,v){try{localStorage.setItem(k,v)}catch(e){}}
function el(tag,props){var e=document.createElement(tag);props=props||{};for(var k in props){var v=props[k];if(k==='class')e.className=v;else if(k==='text')e.textContent=v;else if(v!==false&&v!=null)e.setAttribute(k,v)}
 for(var i=2;i<arguments.length;i++){var c=arguments[i];if(c==null||c===false)continue;if(Array.isArray(c))c.forEach(function(x){x&&e.appendChild(x)});else e.appendChild(c.nodeType?c:document.createTextNode(c))}return e}

function setLang(l){html.dataset.lang=l;html.lang=l;store('lang',l);document.title=D.title[l];
 $$('.lang-btn').forEach(function(b){b.classList.toggle('on',b.dataset.l===l)});
 $$('a[data-wa]').forEach(function(a){a.href=a.href.split('?')[0]+'?text='+encodeURIComponent(window.__WA[l])});
 var t=$('#upd');if(t){var d=new Date(D.ts*1000);t.textContent=new Intl.DateTimeFormat(l,{month:'long',year:'numeric'}).format(d)}
 themeLbl();filters.forEach(function(r){r(true)})}
$$('.lang-btn').forEach(function(b){b.addEventListener('click',function(){setLang(b.dataset.l)})});
function themeLbl(){var d=html.dataset.theme==='dark';$('#themeLbl').textContent=L()==='fr'?(d?'Sombre':'Clair'):(d?'Dark':'Light')}
$('#themeBtn').addEventListener('click',function(){var n=html.dataset.theme==='dark'?'light':'dark';html.dataset.theme=n;store('theme',n);themeLbl()});

var rail=$('#rail'),bd=$('#backdrop');
function toggleRail(o){rail.classList.toggle('open',o);bd.classList.toggle('on',o)}
$('#burger').addEventListener('click',function(){toggleRail(!rail.classList.contains('open'))});
bd.addEventListener('click',function(){toggleRail(false)});
$$('.rail-nav a').forEach(function(a){a.addEventListener('click',function(){toggleRail(false)})});
var dd=$('#cvDd');if(dd){dd.querySelector('button').addEventListener('click',function(e){e.stopPropagation();dd.classList.toggle('open')});document.addEventListener('click',function(){dd.classList.remove('open')})}

var toastT;function toast(m){var t=$('#toast');t.querySelector('span').textContent=m;t.classList.add('on');clearTimeout(toastT);toastT=setTimeout(function(){t.classList.remove('on')},2200)}
var cm=$('#copyMail');if(cm)cm.addEventListener('click',function(){var done=function(){toast(tt('copied'))};
 if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(D.email).then(done,done);
 else{var i=el('input',{value:D.email});document.body.appendChild(i);i.select();try{document.execCommand('copy')}catch(e){}i.remove();done()}});
var up=$('#toTop');window.addEventListener('scroll',function(){up.classList.toggle('show',window.scrollY>600)},{passive:true});
up.addEventListener('click',function(){window.scrollTo({top:0,behavior:'smooth'})});

var links={};$$('.rail-nav a').forEach(function(a){links[a.getAttribute('href').slice(1)]=a});
var navIO=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting&&links[e.target.id]){$$('.rail-nav a.active').forEach(function(a){a.classList.remove('active')});links[e.target.id].classList.add('active')}})},{rootMargin:'-40% 0px -50% 0px'});
$$('main section').forEach(function(s){navIO.observe(s)});
var revIO=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');revIO.unobserve(e.target)}})},{threshold:.1});
$$('.reveal').forEach(function(r){revIO.observe(r)});

var filters=[];
function makeFilter(root){var items=$$('[data-item]',root),size=parseInt(root.dataset.size,10)||6,bar=$('.filters',root),btn=$('.more-btn',root),cat='all',shown=size;
 function render(first){var m=items.filter(function(i){return cat==='all'||i.dataset.cat===cat});items.forEach(function(i){i.hidden=true});
  m.forEach(function(i,k){if(k<shown){var was=i.hidden;i.hidden=false;if(!first&&was){i.classList.remove('pop');void i.offsetWidth;i.classList.add('pop')}}});
  btn.hidden=m.length<=size;btn.textContent=shown>=m.length?tt('show_less'):tt('show_more')+' ('+Math.max(m.length-shown,0)+')';btn.dataset.total=m.length}
 if(bar)bar.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;$$('button',bar).forEach(function(x){x.classList.toggle('on',x===b)});cat=b.dataset.f;shown=size;render()});
 btn.addEventListener('click',function(){var t=parseInt(btn.dataset.total,10);shown=shown>=t?size:shown+size;render()});
 filters.push(render);render(true)}
$$('[data-filter-root]').forEach(makeFilter);
setLang(L());

var modal=$('#modal'),mbody=$('#modalBody');
function closeModal(){modal.classList.remove('open');mbody.replaceChildren();document.body.style.overflow=''}
function openModal(n){mbody.replaceChildren(n);modal.classList.add('open');document.body.style.overflow='hidden'}
$('#modalX').addEventListener('click',closeModal);modal.addEventListener('click',function(e){if(e.target===modal)closeModal()});
document.addEventListener('keydown',function(e){if(e.key==='Escape'){closeModal();toggleRail(false)}});
function mediaNode(type,src){if(!src)return null;var m;
 if(type==='image'||/\.(png|jpe?g|webp|gif)$/i.test(src))m=el('img',{src:src,alt:''});
 else if(type==='local_video'||/\.(mp4|webm|ogg)$/i.test(src))m=el('video',{src:src,controls:'',autoplay:'',playsinline:''});
 else if(/^https:\/\//i.test(src)||/\.pdf$/i.test(src))m=el('iframe',{src:src,allow:'autoplay; encrypted-media; fullscreen',allowfullscreen:'',referrerpolicy:'no-referrer'});
 else return null;return el('div',{class:'media'},m)}
function openProject(i){var p=D.projects[i];if(!p)return;
 var links=el('div',{class:'c-btns'});
 if(p.live)links.appendChild(el('a',{class:'btn btn-ghost btn-sm',href:p.live,target:'_blank',rel:'noopener noreferrer',text:tt('view_project')}));
 if(p.demo)links.appendChild(el('a',{class:'btn btn-primary btn-sm',href:p.demo,target:'_blank',rel:'noopener noreferrer',text:tt('demo')}));
 openModal(el('div',{class:'mv'},el('div',{class:'mm'},el('span',{text:tt('cat_'+p.cat)}),el('span',{text:p.year}),el('span',{class:'status',text:p.conf?tt('confidential'):pick(p.status)})),
  el('h3',{text:pick(p.title)}),p.video?mediaNode('local_video',p.video):(p.cover?mediaNode('image',p.cover):null),el('div',{class:'txt',text:pick(p.desc)}),
  el('div',{class:'row'},p.tags.map(function(t){return el('span',{class:'tag',text:t})})),el('div',{style:'height:16px'}),links))}
function openBlog(i){var b=D.blog[i];if(!b)return;
 openModal(el('div',{class:'mv'},el('div',{class:'mm'},el('span',{text:'// '+pick(b.category)}),el('span',{text:pick(b.date)})),el('h3',{text:pick(b.title)}),
  b.type!=='none'?mediaNode(b.type==='video_url'?'iframe':b.type,b.src):null,el('div',{class:'txt',text:pick(b.desc)})))}
function openProofs(i){var g=D.proofs[i];if(!g||!g.length)return;
 var side=el('div',{class:'proofs-sidebar'}),view=el('div',{class:'proofs-viewer'});
 function load(d,b){$$('.pbtn',side).forEach(function(x){x.classList.remove('active')});b.classList.add('active');view.replaceChildren();
  var m=mediaNode('',d.src);if(m)view.appendChild(m);
  if(D.dl)view.appendChild(el('a',{class:'btn btn-primary btn-sm',href:d.src,download:'',text:tt('download_file')}))}
 g.forEach(function(d,k){var b=el('button',{class:'pbtn',type:'button',text:pick(d.title)||(L()==='fr'?'Document '+(k+1):'Document '+(k+1))});b.addEventListener('click',function(){load(d,b)});side.appendChild(b);if(k===0)setTimeout(function(){load(d,b)},30)});
 openModal(el('div',{class:'mv'},el('h3',{text:tt('proofs_title')}),el('div',{class:'proofs-layout'},side,view)))}
document.addEventListener('click',function(e){var t;
 if((t=e.target.closest('[data-proj]')))openProject(+t.dataset.proj);
 else if((t=e.target.closest('[data-blog]')))openBlog(+t.dataset.blog);
 else if((t=e.target.closest('[data-proofs]')))openProofs(+t.dataset.proofs)});
})();
</script>
</body>
</html>
