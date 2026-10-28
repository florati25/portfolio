<?php
/** lib.php — fonctions partagées (données, bilingue FR/EN, icônes, textes d'interface). */
const DATA_FILE   = __DIR__ . '/data.json';
const UPLOADS_DIR = __DIR__ . '/uploads';

function load_data(): array {
    if (!is_file(DATA_FILE)) return [];
    $d = json_decode((string)file_get_contents(DATA_FILE), true);
    return is_array($d) ? $d : [];
}
function save_data(array $d): bool {
    return file_put_contents(DATA_FILE, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
}
function normalize(array $d): array {
    $d += ['password' => '', 'profile_photo' => '', 'cv_path' => '', 'cv_path_en' => '', 'allow_proof_downloads' => true,
           'hero' => [], 'profile' => [], 'about_cards' => [], 'availability' => [], 'experiences' => [], 'skills' => [],
           'projects' => [], 'blog' => [], 'education' => [], 'education_note' => [], 'certs' => [], 'contact' => [], 'socials' => [], 'ui' => []];
    $d['hero'] += ['tag' => '', 'first_name' => '', 'last_name' => '', 'role' => '', 'lede' => '', 'brand_sub' => '', 'tech' => '', 'note1' => '', 'note2' => '', 'stats' => []];
    $d['profile'] += ['title' => '', 'text1' => '', 'text2' => ''];
    $d['availability'] += ['title' => '', 'text' => '', 'tags' => ''];
    $d['education_note'] += ['title' => '', 'text' => ''];
    $d['contact'] += ['emails' => [], 'phones' => [], 'locs' => [], 'status' => '', 'interests' => ''];
    return $d;
}

/* ---------- Helpers texte ---------- */
function s($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function csv($v): array { return array_values(array_filter(array_map('trim', explode(',', (string)$v)), 'strlen')); }
function lines($v): array { return array_values(array_filter(array_map('trim', preg_split('/\R/', (string)$v)), 'strlen')); }
function lang_val($v, string $lang): string {
    if (is_array($v)) {
        $x = (string)($v[$lang] ?? '');
        if ($x === '') $x = (string)($v[$lang === 'fr' ? 'en' : 'fr'] ?? '');
        return $x;
    }
    return (string)$v;
}
function bi($v): array { return ['fr' => lang_val($v, 'fr'), 'en' => lang_val($v, 'en')]; }
function has($v): bool { return trim(lang_val($v, 'fr')) !== ''; }
function T($v): string {
    if (!is_array($v)) return s($v);
    $fr = lang_val($v, 'fr'); $en = lang_val($v, 'en');
    if ($fr === $en) return s($fr);
    return '<span class="l-fr">' . s($fr) . '</span><span class="l-en">' . s($en) . '</span>';
}
function TB($v, callable $render): string {
    if (!is_array($v)) return $render((string)$v);
    $fr = lang_val($v, 'fr'); $en = lang_val($v, 'en');
    if ($fr === $en) return $render($fr);
    return '<span class="lw l-fr">' . $render($fr) . '</span><span class="lw l-en">' . $render($en) . '</span>';
}
function pills($v, string $cls = 'pill'): string {
    return TB($v, fn($t) => implode('', array_map(fn($x) => '<span class="' . $cls . '">' . s($x) . '</span>', csv($t))));
}
function safe_src($p): string {
    $p = trim((string)$p);
    if ($p === '') return '';
    if (preg_match('#^https://[^\s"\'<>]+$#i', $p)) return $p;
    if (preg_match('#^uploads/[A-Za-z0-9._-]+$#', $p)) return $p;
    return '';
}
function file_ok($p): bool {
    $p = safe_src($p);
    return $p !== '' && strpos($p, 'uploads/') === 0 && is_file(__DIR__ . '/' . $p);
}
function initials(array $d): string {
    $i = mb_strtoupper(mb_substr(trim((string)$d['hero']['first_name']), 0, 1) . mb_substr(trim((string)$d['hero']['last_name']), 0, 1));
    return $i !== '' ? $i : '··';
}

/* ---------- Icônes ---------- */
function icon_paths(): array {
    return [
        'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 012-2h2a2 2 0 012 2v2M3 13h18"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'folder' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>',
        'cap' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
        'file' => '<path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
        'pin' => '<path d="M12 21s7-6.2 7-12a7 7 0 10-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'code' => '<path d="M8 8l-5 4 5 4M16 8l5 4-5 4M14 5l-4 14"/>',
        'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'server' => '<rect x="3" y="4" width="18" height="6" rx="1.5"/><rect x="3" y="14" width="18" height="6" rx="1.5"/><path d="M7 7h.01M7 17h.01"/>',
        'mobile' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'database' => '<ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>',
        'cpu' => '<rect x="6" y="6" width="12" height="12" rx="1"/><rect x="9" y="9" width="6" height="6"/><path d="M9 2v4M15 2v4M9 18v4M15 18v4M2 9h4M2 15h4M18 9h4M18 15h4"/>',
        'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 018 0v3"/>',
        'play' => '<path d="M7 4l13 8-13 8z" fill="currentColor" stroke="none"/>',
        'download' => '<path d="M12 3v12M7 11l5 5 5-5M4 20h16"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron' => '<path d="M6 9l6 6 6-6"/>',
        'moon' => '<path d="M21 13A9 9 0 1111 3a7 7 0 0010 10z"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M5 19l1.5-1.5M17.5 6.5L19 5"/>',
        'copy' => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 012-2h9"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'send' => '<path d="M21 3L10 14M21 3l-7 18-4-7-7-4z"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'link' => '<path d="M10 14a4 4 0 005.7 0l3-3a4 4 0 00-5.7-5.7l-1 1M14 10a4 4 0 00-5.7 0l-3 3a4 4 0 005.7 5.7l1-1"/>',
        'terminal' => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 9l3 3-3 3M13 15h4"/>',
        'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l3 12h11l2-8H6"/>',
        'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
        'award' => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14L7 22l5-3 5 3-1.5-8"/>',
        'up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'spark' => '<path d="M12 3l2 6 6 2-6 2-2 6-2-6-6-2 6-2z"/>',
        'linkedin' => '<path d="M5 9h3v10H5zM6.5 4.5a1.8 1.8 0 110 3.6 1.8 1.8 0 010-3.6zM11 9h3v1.4c.6-1 1.8-1.7 3.2-1.7 3 0 3.8 2 3.8 4.6V19h-3v-5c0-1.3-.3-2.3-1.7-2.3S14 12.8 14 14v5h-3z" fill="currentColor" stroke="none"/>',
        'github' => '<path d="M9 19c-4 1.5-4-2-6-2m12 4v-3.5c0-1 .1-1.4-.5-2 2.8-.3 5.5-1.4 5.5-6a4.6 4.6 0 00-1.3-3.2 4.2 4.2 0 00-.1-3.2s-1.1-.3-3.5 1.3a12 12 0 00-6.2 0C6.5 2.8 5.4 3.1 5.4 3.1a4.2 4.2 0 00-.1 3.2A4.6 4.6 0 004 9.5c0 4.6 2.7 5.7 5.5 6-.6.6-.6 1.2-.5 2V21"/>',
        'facebook' => '<path d="M14 8h3V4h-3a4 4 0 00-4 4v3H7v4h3v6h4v-6h3l1-4h-4V8.5c0-.3.2-.5.5-.5z"/>',
        'youtube' => '<rect x="3" y="6" width="18" height="12" rx="3"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor" stroke="none"/>',
        'wa' => '<path d="M3 21l1.6-5A8.5 8.5 0 1112 20.500a8.500 8.500 0 01-4-1z"/><path d="M9 8.500c0 3.500 3 6.500 6.500 6.500l1-1.500-2-1-1 .7a4 4 0 01-2-2l.7-1-1-2z"/>',
    ];
}
function icon(string $n, string $cls = 'ic'): string {
    static $P = null; if ($P === null) $P = icon_paths();
    return '<svg class="' . s($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($P[$n] ?? $P['spark']) . '</svg>';
}
function social_icon(string $name): string {
    $n = strtolower($name);
    foreach (['linkedin', 'github', 'facebook', 'youtube'] as $k) if (strpos($n, $k) !== false) return $k;
    return strpos($n, 'whatsapp') !== false ? 'wa' : 'link';
}
function admin_icons(): array {
    return ['shield', 'code', 'server', 'database', 'cpu', 'lock', 'terminal', 'globe', 'mobile', 'user', 'briefcase', 'cap', 'clock', 'file', 'award', 'layers', 'folder', 'cart', 'spark', 'pin', 'phone', 'mail'];
}

/* ---------- Textes d'interface FR / EN (formulations au féminin quand elles concernent la personne) ---------- */
function ui_defaults(): array {
    $r = [
        'nav_home' => ['Accueil', 'Home'], 'nav_about' => ['À propos', 'About'], 'nav_experience' => ['Parcours', 'Experience'],
        'nav_skills' => ['Compétences', 'Skills'], 'nav_projects' => ['Projets', 'Projects'], 'nav_education' => ['Formation', 'Education'],
        'nav_certs' => ['Diplômes & certifs', 'Degrees & certs'], 'nav_blog' => ['Blog & Story', 'Blog & Story'], 'nav_contact' => ['Contact', 'Contact'],
        'menu' => ['Menu', 'Menu'], 'back_top' => ['Retour en haut', 'Back to top'], 'page_title' => ['Portfolio Cybersécurité', 'Cybersecurity Portfolio'],
        'node' => ['Nœud sécurisé', 'Secure node'], 'empty_notice' => ["Portfolio en attente de configuration : connectez-vous à la console d'administration pour renseigner vos informations.", 'Portfolio awaiting setup: sign in to the admin console to fill in your information.'],
        'btn_contact_me' => ['Me contacter', 'Contact me'], 'greeting' => ['Bonjour, je suis', "Hello, I'm"],
        'btn_projects' => ['Explorer les projets', 'Explore projects'], 'btn_cv' => ['Télécharger mon CV', 'Download my CV'],
        'cv_fr' => ['CV Français', 'French résumé'], 'cv_en' => ['CV English', 'English résumé'],
        'available' => ['Disponible', 'Available'], 'btn_discuss' => ['Discuter de mon projet', 'Discuss my project'],
        'about_eyebrow' => ['01 - Profil', '01 - Profile'], 'about_journey' => ['Mon parcours', 'My journey'], 'about_full' => ['Voir mon parcours complet', 'See my full journey'],
        'exp_eyebrow' => ['02 - Parcours', '02 - Experience'], 'exp_title' => ['Parcours professionnel', 'Professional journey'],
        'exp_sub' => ['{n} expérience(s) au service de produits et de systèmes concrets.', '{n} experience(s) building real-world products and systems.'],
        'skills_eyebrow' => ['03 - Compétences', '03 - Skills'], 'skills_title' => ['Stack technique & sécurité', 'Technical & security stack'],
        'skills_sub' => ['Technologies et outils pour sécuriser et construire des solutions fiables.', 'Technologies and tools to secure and build reliable solutions.'],
        'proj_eyebrow' => ['04 - Réalisations', '04 - Work'], 'proj_title' => ['Projets & CTF', 'Projects & CTF'],
        'proj_sub' => ["Une sélection de réalisations en entreprise, en freelance et en cadre académique.", 'A selection of work for companies, freelance clients and academia.'],
        'filter_all' => ['Tous', 'All'], 'cat_web' => ['Web', 'Web'], 'cat_mobile' => ['Mobile', 'Mobile'], 'cat_web_mobile' => ['Web & Mobile', 'Web & Mobile'],
        'cat_backend' => ['Backend', 'Backend'], 'cat_security' => ['Sécurité', 'Security'], 'cat_other' => ['Autres', 'Other'],
        'view_project' => ['Voir le projet', 'View project'], 'demo' => ['Démo', 'Demo'], 'details' => ['Détails', 'Details'],
        'video' => ['Vidéo', 'Video'], 'confidential' => ['Projet confidentiel', 'Confidential project'],
        'show_more' => ['Voir plus', 'Show more'], 'show_less' => ['Voir moins', 'Show less'],
        'edu_eyebrow' => ['05 - Formation', '05 - Education'], 'edu_title' => ['Cursus académique', 'Academic background'],
        'edu_sub' => ['Un cursus construit en informatique, systèmes et cybersécurité.', 'A background built in computing, systems and cybersecurity.'],
        'certs_eyebrow' => ['Certifications', 'Certifications'], 'certs_title' => ['Diplômes, certifications & attestations', 'Degrees, certifications & attestations'],
        'certs_sub' => ['Cliquez pour consulter les justificatifs.', 'Click to view the supporting documents.'],
        'type_diploma' => ['Diplômes', 'Degrees'], 'type_cert' => ['Certifications', 'Certifications'], 'type_work' => ['Stages & travail', 'Internships & work'],
        'proofs_btn' => ['Consulter les justificatifs', 'View supporting documents'], 'proofs_title' => ['Justificatifs & preuves', 'Supporting documents'],
        'download_file' => ['Télécharger le fichier', 'Download file'],
        'blog_eyebrow' => ['06 - Blog', '06 - Blog'], 'blog_title' => ['Articles & analyses', 'Articles & analyses'],
        'blog_sub' => ['Coulisses de mes projets, tests et démonstrations.', 'Behind the scenes of my projects, tests and demos.'],
        'open_story' => ["Lire l'article", 'Read article'], 'watch' => ['Visionner', 'Watch'], 'external_stream' => ['Média externe', 'External media'],
        'contact_eyebrow' => ['07 - Contact', '07 - Contact'], 'contact_title' => ['Canal de communication sécurisé', 'Secure communication channel'],
        'contact_sub' => ["Une question, une opportunité ou l'envie d'échanger ?", 'A question, an opportunity or just want to chat?'],
        'contact_pitch' => ["À l'écoute de propositions en ingénierie de la cybersécurité, audits, analyses de vulnérabilités ou missions de conseil.", 'Open to cybersecurity engineering proposals, audits, vulnerability analysis and advisory missions.'],
        'send_message' => ["M'envoyer un message", 'Send me a message'], 'whatsapp_btn' => ['Contact WhatsApp', 'WhatsApp contact'],
        'whatsapp_msg' => ['Bonjour, je souhaite vous contacter suite à la visite de votre portfolio.', 'Hello, I would like to get in touch after visiting your portfolio.'],
        'copy_email' => ["Copier l'email", 'Copy email'], 'copied' => ['Email copié !', 'Email copied!'],
        'cv_panel' => ['Mes CV', 'My résumés'], 'always_open' => ['Toujours ouverte aux nouvelles opportunités !', 'Always open to new opportunities!'],
        'rights' => ['Tous droits réservés', 'All rights reserved'], 'last_update' => ['Dernière mise à jour', 'Last updated'],
    ];
    $o = [];
    foreach ($r as $k => $v) $o[$k] = ['fr' => $v[0], 'en' => $v[1]];
    return $o;
}
function ui_init(array $data): void {
    $UI = ui_defaults();
    foreach (($data['ui'] ?? []) as $k => $v) {
        if (!isset($UI[$k]) || !is_array($v)) continue;
        foreach (['fr', 'en'] as $l) if (!empty($v[$l])) $UI[$k][$l] = (string)$v[$l];
    }
    $GLOBALS['UI'] = $UI;
}
function UB(string $k, array $vars = []): array {
    $v = $GLOBALS['UI'][$k] ?? ['fr' => $k, 'en' => $k];
    foreach ($vars as $a => $b) foreach (['fr', 'en'] as $l) $v[$l] = str_replace('{' . $a . '}', (string)$b, $v[$l]);
    return $v;
}
function U(string $k, array $vars = []): string { return T(UB($k, $vars)); }
function UP(string $k, string $lang): string { return UB($k)[$lang] ?? $k; }
