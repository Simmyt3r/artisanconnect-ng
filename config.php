<?php
// ============================================================
//  ArtisanConnect NG — config.php
//  Database, session, helpers, shared CSS, header/footer
//  Compatible: InfinityFree / shared cPanel / PHP 7.4+ / MySQL 8
// ============================================================

// ── DB CREDENTIALS (replace before uploading) ───────────────
define('DB_HOST', 'localhost');            // InfinityFree: e.g. sql123.infinityfree.com
define('DB_NAME', 'artisanconnect');       // your cPanel DB name
define('DB_USER', 'your_db_user');         // cPanel MySQL username
define('DB_PASS', 'your_db_password');     // cPanel MySQL password
define('SITE_URL', '');                    // leave '' for relative paths
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_UPLOAD_MB', 5);

// ── SESSION ─────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// ── PDO SINGLETON ─────────────────────────────────────────
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_EMULATE_PREPARES => false]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:monospace;padding:20px;color:#c0392b"><b>DB Error:</b> ' .
                htmlspecialchars($e->getMessage()) . '<br>Check DB credentials in config.php.</div>');
        }
    }
    return $pdo;
}

// ── AUTH HELPERS ─────────────────────────────────────────────
function isLoggedIn(): bool  { return isset($_SESSION['user_id']); }
function isArtisan(): bool   { return ($_SESSION['role'] ?? '') === 'artisan'; }
function isCustomer(): bool  { return ($_SESSION['role'] ?? '') === 'customer'; }
function isAdmin(): bool     { return ($_SESSION['role'] ?? '') === 'admin'; }
function uid(): int          { return (int)($_SESSION['user_id'] ?? 0); }

function requireLogin(): void {
    if (!isLoggedIn()) { redirect('auth.php?next=' . urlencode($_SERVER['REQUEST_URI'])); }
}
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) redirect('index.php');
}

// ── UTIL ─────────────────────────────────────────────────────
function redirect(string $url): void { header('Location: ' . $url); exit; }
function e(string $s): string        { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function flash(string $key, string $msg = ''): string {
    if ($msg !== '') { $_SESSION['flash'][$key] = $msg; return ''; }
    $v = $_SESSION['flash'][$key] ?? '';
    unset($_SESSION['flash'][$key]);
    return $v;
}

function csrf(): string {
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf_token'];
}
function verifyCsrf(): void {
    if (($_POST['_csrf'] ?? '') !== ($_SESSION['csrf_token'] ?? ''))
        die('Security token mismatch. Please go back and retry.');
}

function stars(float $r, bool $label = true): string {
    $html  = '<span class="stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $r ? '★' : ($i - 0.5 <= $r ? '½' : '☆');
    }
    $html .= '</span>';
    if ($label) $html .= ' <small style="color:var(--light)">' . number_format($r, 1) . '</small>';
    return $html;
}

function ago(string $ts): string {
    $d = time() - strtotime($ts);
    if ($d < 60)     return 'just now';
    if ($d < 3600)   return floor($d/60) . 'm ago';
    if ($d < 86400)  return floor($d/3600) . 'h ago';
    if ($d < 604800) return floor($d/86400) . 'd ago';
    return date('d M Y', strtotime($ts));
}

function uploadFile(string $key, string $sub = 'portfolio'): ?string {
    if (!isset($_FILES[$key]) || $_FILES[$key]['error'] !== UPLOAD_ERR_OK) return null;
    $f = $_FILES[$key];
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) { flash('error', 'File exceeds ' . MAX_UPLOAD_MB . 'MB limit.'); return null; }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $fi = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fi->file($f['tmp_name']);
    if (!isset($allowed[$mime])) { flash('error', 'Only JPG/PNG/GIF/WEBP allowed.'); return null; }
    $dir = UPLOAD_DIR . $sub . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = uniqid('', true) . '.' . $allowed[$mime];
    if (!move_uploaded_file($f['tmp_name'], $dir . $name)) { flash('error', 'Upload failed — check folder permissions.'); return null; }
    return $sub . '/' . $name;
}

function imgUrl(string $path, string $fallback = ''): string {
    if (!$path) return $fallback ?: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect fill="%23e2e8f0" width="200" height="200"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="60">👤</text></svg>';
    return SITE_URL . '/uploads/' . ltrim($path, '/');
}

// ── SHARED CSS ───────────────────────────────────────────────
function css(): string { return <<<CSS
<style>
:root{
  --green:#008751;--green-dark:#006b40;--green-light:#e8f5f0;
  --gold:#f5a623;--gold-light:#fff8ed;
  --dark:#1a2332;--mid:#4a5568;--light:#718096;
  --border:#e2e8f0;--bg:#f7fafc;--white:#fff;
  --r:12px;--sh:0 2px 12px rgba(0,0,0,.08);--sh2:0 8px 30px rgba(0,0,0,.12);
  --font:"Segoe UI",system-ui,-apple-system,sans-serif
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font);background:var(--bg);color:var(--dark);line-height:1.6}
a{color:var(--green);text-decoration:none}a:hover{color:var(--green-dark)}
img{max-width:100%;display:block}
input,select,textarea,button{font-family:inherit;font-size:1rem}

/* NAV */
.nav{background:var(--white);box-shadow:0 1px 3px rgba(0,0,0,.1);position:sticky;top:0;z-index:100}
.nav-i{max-width:1200px;margin:auto;padding:0 1rem;display:flex;align-items:center;gap:.8rem;height:64px}
.brand{font-size:1.25rem;font-weight:800;color:var(--green);display:flex;align-items:center;gap:.3rem;white-space:nowrap}
.brand em{color:var(--gold);font-style:normal}
.nav-links{display:flex;align-items:center;gap:.1rem;margin-left:auto;flex-wrap:wrap}
.nav-links a,.nl-btn{padding:.4rem .85rem;border-radius:8px;font-size:.88rem;font-weight:500;border:none;cursor:pointer;background:none;color:var(--mid)}
.nav-links a:hover,.nl-btn:hover{background:var(--green-light);color:var(--green)}
.nav-cta{background:var(--green)!important;color:#fff!important;padding:.4rem 1rem;border-radius:8px;font-size:.88rem;font-weight:600}
.nav-cta:hover{background:var(--green-dark)!important}
.nav-tog{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:.4rem;border:none;background:none;margin-left:auto}
.nav-tog span{width:22px;height:2px;background:var(--dark);border-radius:2px;transition:.3s}
@media(max-width:768px){
  .nav-tog{display:flex}
  .nav-links{display:none;position:absolute;top:64px;left:0;right:0;background:var(--white);flex-direction:column;padding:.8rem;box-shadow:0 4px 12px rgba(0,0,0,.1);align-items:stretch}
  .nav-links.open{display:flex}
}

/* CONTAINER */
.ctr{max-width:1200px;margin:0 auto;padding:0 1rem}
.sec{padding:2.5rem 0}

/* PAGE HEADER */
.ph{background:linear-gradient(135deg,var(--green),var(--green-dark));color:#fff;padding:2.5rem 1rem}
.ph h1{font-size:1.9rem;font-weight:800;margin-bottom:.25rem}
.ph p{opacity:.85}

/* ALERTS */
.al{padding:.85rem 1.1rem;border-radius:8px;margin:.7rem 0;font-size:.9rem}
.al-ok{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
.al-err{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
.al-info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:.35rem;padding:.6rem 1.25rem;border-radius:8px;font-weight:600;font-size:.9rem;cursor:pointer;border:2px solid transparent;transition:.2s;text-align:center;justify-content:center}
.btn-p{background:var(--green);color:#fff;border-color:var(--green)}
.btn-p:hover{background:var(--green-dark)}
.btn-o{background:transparent;color:var(--green);border-color:var(--green)}
.btn-o:hover{background:var(--green);color:#fff}
.btn-d{background:#e53e3e;color:#fff;border-color:#e53e3e}
.btn-d:hover{background:#c53030}
.btn-g{background:var(--gold);color:#fff;border-color:var(--gold)}
.btn-sm{padding:.3rem .75rem;font-size:.82rem}
.btn-blk{width:100%}

/* CARD */
.card{background:var(--white);border-radius:var(--r);box-shadow:var(--sh);padding:1.4rem}

/* FORM */
.fg{margin-bottom:1rem}
.fg label{display:block;font-weight:600;font-size:.85rem;color:var(--mid);margin-bottom:.3rem}
.fc{width:100%;padding:.62rem .9rem;border:1.5px solid var(--border);border-radius:8px;font-size:.93rem;transition:.2s;color:var(--dark)}
.fc:focus{outline:none;border-color:var(--green);box-shadow:0 0 0 3px rgba(0,135,81,.1)}
select.fc{appearance:none;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath fill='%23718096' d='M0 0l5 6 5-6z'/%3E%3C/svg%3E") no-repeat right .9rem center #fff;padding-right:2.2rem}
textarea.fc{min-height:95px;resize:vertical}
.fr{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
@media(max-width:600px){.fr{grid-template-columns:1fr}}

/* BADGE */
.b{display:inline-flex;align-items:center;gap:.2rem;padding:.15rem .55rem;border-radius:20px;font-size:.74rem;font-weight:600}
.b-g{background:var(--green-light);color:var(--green)}
.b-gold{background:var(--gold-light);color:#9a5f10}
.b-gr{background:#edf2f7;color:var(--light)}
.b-bl{background:#ebf8ff;color:#2b6cb0}
.b-r{background:#fff5f5;color:#c53030}

/* STARS */
.stars{color:var(--gold);font-size:1rem}

/* ARTISAN CARD */
.ac{background:var(--white);border-radius:var(--r);box-shadow:var(--sh);overflow:hidden;transition:transform .2s,box-shadow .2s;display:flex;flex-direction:column}
.ac:hover{transform:translateY(-3px);box-shadow:var(--sh2)}
.ac-img{height:175px;object-fit:cover;width:100%}
.ac-ph{height:175px;background:linear-gradient(135deg,#e2e8f0,#cbd5e0);display:flex;align-items:center;justify-content:center;font-size:2.8rem}
.ac-b{padding:1rem;flex:1;display:flex;flex-direction:column}
.ac-n{font-weight:700;font-size:.97rem;margin-bottom:.15rem}
.ac-t{font-size:.82rem;color:var(--mid);margin-bottom:.4rem}
.ac-m{display:flex;align-items:center;justify-content:space-between;margin-top:auto;padding-top:.65rem;border-top:1px solid var(--border)}

/* GRIDS */
.g3{display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem}
.g2{display:grid;grid-template-columns:repeat(2,1fr);gap:1.25rem}
.g4{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem}
@media(max-width:960px){.g3{grid-template-columns:repeat(2,1fr)}.g4{grid-template-columns:repeat(2,1fr)}}
@media(max-width:600px){.g3,.g2,.g4{grid-template-columns:1fr}}

/* TABS */
.tabs{display:flex;gap:.3rem;border-bottom:2px solid var(--border);margin-bottom:1.5rem;flex-wrap:wrap}
.tab{padding:.55rem 1.1rem;border:none;background:none;cursor:pointer;font-weight:600;font-size:.88rem;color:var(--light);border-bottom:2px solid transparent;margin-bottom:-2px;transition:.2s}
.tab.on,.tab:hover{color:var(--green);border-bottom-color:var(--green)}
.tp{display:none}.tp.on{display:block}

/* TABLE */
.tw{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:.88rem}
th,td{padding:.7rem 1rem;text-align:left;border-bottom:1px solid var(--border)}
th{background:var(--bg);font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:var(--mid)}
tr:last-child td{border-bottom:none}

/* STAT CARD */
.sc{background:var(--white);border-radius:var(--r);box-shadow:var(--sh);padding:1.2rem 1.4rem;display:flex;align-items:center;gap:1rem}
.sc-ico{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0}

/* EMPTY STATE */
.es{text-align:center;padding:3rem 1rem;color:var(--light)}
.es .ei{font-size:3rem;margin-bottom:.7rem}

/* PORTFOLIO GRID */
.pg{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:.8rem}
.pi{position:relative;border-radius:8px;overflow:hidden;aspect-ratio:1;cursor:pointer;background:#edf2f7}
.pi img{width:100%;height:100%;object-fit:cover;transition:.3s}
.pi:hover img{transform:scale(1.06)}
.pio{position:absolute;inset:0;background:rgba(0,0,0,.45);opacity:0;transition:.3s;display:flex;align-items:flex-end;padding:.5rem;color:#fff;font-size:.8rem}
.pi:hover .pio{opacity:1}

/* MESSAGES */
.msg-w{display:grid;grid-template-columns:280px 1fr;gap:0;border-radius:var(--r);overflow:hidden;box-shadow:var(--sh);background:var(--white);height:70vh}
.msg-list{border-right:1px solid var(--border);overflow-y:auto}
.msg-li{padding:.85rem 1rem;border-bottom:1px solid var(--border);cursor:pointer;transition:.15s}
.msg-li:hover,.msg-li.on{background:var(--green-light)}
.msg-th{display:flex;flex-direction:column;height:100%}
.msg-body{flex:1;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:.6rem}
.msg-bbl{max-width:72%;padding:.65rem .95rem;border-radius:16px;font-size:.9rem;line-height:1.5}
.msg-mine{background:var(--green);color:#fff;border-bottom-right-radius:4px;margin-left:auto}
.msg-theirs{background:#f0f4f8;color:var(--dark);border-bottom-left-radius:4px}
.msg-ts{font-size:.72rem;opacity:.65;text-align:right;margin-top:.2rem}
.msg-inp{padding:.8rem;border-top:1px solid var(--border);display:flex;gap:.6rem}
@media(max-width:650px){.msg-w{grid-template-columns:1fr;height:auto}}

/* MODAL / LIGHTBOX */
.lb{display:none;position:fixed;inset:0;background:rgba(0,0,0,.88);z-index:999;align-items:center;justify-content:center;padding:1rem}
.lb.open{display:flex}
.lb-close{position:absolute;top:1rem;right:1.5rem;color:#fff;font-size:2rem;cursor:pointer;line-height:1}
.lb img{max-height:90vh;max-width:90vw;border-radius:8px}

/* FOOTER */
footer{background:var(--dark);color:#a0aec0;padding:2rem 1rem;text-align:center;font-size:.85rem;margin-top:3rem}
footer strong{color:var(--green)}
</style>
CSS;
}

// ── HEADER ───────────────────────────────────────────────────
function head(string $title, string $extra = ''): void {
    $e = flash('error'); $s = flash('success'); $i = flash('info');
    $unread = 0;
    if (isLoggedIn()) {
        $u = uid();
        $st = db()->prepare("SELECT COUNT(*) FROM messages m JOIN conversations c ON m.conversation_id=c.conversation_id WHERE m.is_read=0 AND m.sender_id!=? AND (c.user1_id=? OR c.user2_id=?)");
        $st->execute([$u,$u,$u]);
        $unread = (int)$st->fetchColumn();
    }
    $csrf = csrf();
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=e($title)?> — ArtisanConnect NG</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🔨</text></svg>">
<?=css()?><?=$extra?>
</head>
<body>
<nav class="nav">
 <div class="nav-i">
  <a class="brand" href="index.php">🔨 Artisan<em>Connect</em></a>
  <button class="nav-tog" onclick="document.querySelector('.nav-links').classList.toggle('open')" aria-label="Menu"><span></span><span></span><span></span></button>
  <div class="nav-links">
   <a href="index.php">🔍 Find Artisans</a>
   <?php if(isLoggedIn()):?>
   <a href="dashboard.php">📋 Dashboard</a>
   <a href="messages.php" style="position:relative">💬 Messages<?php if($unread>0):?><span style="position:absolute;top:-6px;right:-4px;background:#e53e3e;color:#fff;border-radius:50%;width:17px;height:17px;font-size:.65rem;display:flex;align-items:center;justify-content:center;font-weight:700"><?=$unread?></span><?php endif?></a>
   <?php if(isAdmin()):?><a href="admin.php">⚙ Admin</a><?php endif?>
   <form method="post" action="auth.php" style="display:inline"><input type="hidden" name="_csrf" value="<?=$csrf?>"><input type="hidden" name="action" value="logout"><button class="nl-btn nav-cta">Logout</button></form>
   <span style="font-size:.8rem;color:var(--light);padding:.3rem .6rem">👤<?=e($_SESSION['first_name']??'')?></span>
   <?php else:?>
   <a href="auth.php">Login</a>
   <a href="auth.php?tab=register" class="nav-cta">Join Free</a>
   <?php endif?>
  </div>
 </div>
</nav>
<?php if($e) echo '<div class="ctr"><div class="al al-err" style="margin-top:.7rem">⚠ '.e($e).'</div></div>';
      if($s) echo '<div class="ctr"><div class="al al-ok"  style="margin-top:.7rem">✓ '.e($s).'</div></div>';
      if($i) echo '<div class="ctr"><div class="al al-info" style="margin-top:.7rem">ℹ '.e($i).'</div></div>';
}

// ── FOOTER ─────────────────────────────────────────────────
function foot(): void { ?>
<footer>
 <p>© <?=date('Y')?> <strong>ArtisanConnect NG</strong> — Empowering Nigerian Artisans Digitally</p>
 <p style="margin-top:.25rem">PHP &amp; MySQL · Peter Nice Benet FT21BCMP0678 · Nasarawa State University, Keffi</p>
</footer></body></html><?php }
