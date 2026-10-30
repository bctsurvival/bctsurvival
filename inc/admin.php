<?php
/**
 * Administrace – přihlášení, ochrana formulářů a zpracování fotek.
 */
declare(strict_types=1);
require __DIR__ . '/app.php';

session_name('bctadmin');
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'path' => '/',
]);
session_start();
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');

const LOGIN_MAX_FAILS = 5;
const LOGIN_LOCK_SECONDS = 600;

function is_logged_in(): bool
{
    return !empty($_SESSION['ok']) && ($_SESSION['pw'] ?? '') === substr(settings()['password_hash'], -12);
}

function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: index.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Formulář vypršel. Vraťte se zpět a zkuste to znovu.');
    }
}

function flash(?string $msg = null, string $kind = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $kind];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Zámek proti hádání hesla (podle IP adresy). */
function login_locked(): int
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $l = data_read('login', [])[$ip] ?? null;
    if ($l && $l['n'] >= LOGIN_MAX_FAILS && time() - $l['t'] < LOGIN_LOCK_SECONDS) {
        return LOGIN_LOCK_SECONDS - (time() - $l['t']);
    }
    return 0;
}

function login_fail(bool $reset = false): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '?';
    $all = data_read('login', []);
    if ($reset) {
        unset($all[$ip]);
    } else {
        $l = $all[$ip] ?? ['n' => 0, 't' => 0];
        if (time() - $l['t'] > LOGIN_LOCK_SECONDS) {
            $l['n'] = 0;
        }
        $all[$ip] = ['n' => $l['n'] + 1, 't' => time()];
    }
    data_write('login', $all);
}

function save_settings(array $changes): void
{
    $cur = array_merge(default_settings(), data_read('settings', []));
    data_write('settings', array_merge($cur, $changes));
}

/** Uloží všechny záznamy (včetně nezveřejněných). */
function save_entries(array $list): void
{
    data_write('entries', array_values($list));
    regen_static();
}

/* ---------- fotky ---------- */

/**
 * Zpracuje nahraný soubor: otočí podle EXIF, uloží velkou verzi a náhled.
 * Vrací údaje o fotce, nebo text chyby.
 */
function process_upload(array $f, string $dir, string $name)
{
    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Fotku se nepodařilo nahrát (kód ' . (int)$f['error'] . '). Zkuste menší soubor.';
    }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
        return 'Soubor ' . $f['name'] . ' není fotka ve formátu JPG, PNG nebo WebP.';
    }
    if ($info[0] * $info[1] > 14_000_000) {
        return 'Fotka ' . $f['name'] . ' je příliš velká. Nahrávejte ji přes prohlížeč v počítači nebo mobilu, ten ji zmenší sám.';
    }
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($f['tmp_name']),
        IMAGETYPE_PNG => @imagecreatefrompng($f['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($f['tmp_name']),
    };
    if (!$src) {
        return 'Fotku ' . $f['name'] . ' se nepodařilo přečíst.';
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 1] ?? 0;
        if ($rot) {
            $r = imagerotate($src, $rot, 0);
            imagedestroy($src);
            $src = $r;
        }
    }
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    [$w, $h] = save_resized($src, PHOTO_BIG, "$dir/$name.jpg", 80);
    [$sw, $sh] = save_resized($src, PHOTO_SMALL, "$dir/$name-m.jpg", 74);
    imagedestroy($src);
    return ['file' => "$name.jpg", 'w' => $w, 'h' => $h, 'sw' => $sw, 'sh' => $sh, 'caption' => ''];
}

function save_resized($src, int $max, string $path, int $q): array
{
    $w = imagesx($src);
    $h = imagesy($src);
    $k = min(1, $max / max($w, $h));
    $nw = (int)round($w * $k);
    $nh = (int)round($h * $k);
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($dst, true);
    imagejpeg($dst, $path, $q);
    imagedestroy($dst);
    return [$nw, $nh];
}

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $f) {
        if ($f !== '.' && $f !== '..') {
            is_dir("$dir/$f") ? rrmdir("$dir/$f") : unlink("$dir/$f");
        }
    }
    rmdir($dir);
}

/* ---------- vzhled administrace ---------- */

function admin_head(string $title): void
{
    $s = settings();
    ?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> – administrace <?= e($s['site_name']) ?></title>
<link rel="icon" href="../img/favicon.png" type="image/png">
<link rel="stylesheet" href="../<?= e(asset('css/style.css')) ?>">
<link rel="stylesheet" href="admin.css?v=<?= substr(md5_file(__DIR__ . '/../admin/admin.css'), 0, 8) ?>">
</head>
<body class="admin">
<a class="skip" href="#obsah">Přeskočit na obsah</a>
<header class="site-header">
  <div class="wrap header-in">
    <a class="brand" href="index.php" aria-label="Administrace – seznam záznamů"><img src="../img/logo-96.png" width="44" height="44" alt=""><span>Administrace</span></a>
    <?php if (is_logged_in()): ?>
    <nav aria-label="Menu administrace">
      <ul>
        <li><a href="index.php">Záznamy</a></li>
        <li><a href="nastaveni.php">Nastavení</a></li>
        <li><a href="../" target="_blank" rel="noopener">Web</a></li>
        <li><a href="odhlasit.php">Odhlásit</a></li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</header>
<main id="obsah" class="wrap admin-main">
<?php
    if ($f = flash()) {
        echo '<p class="flash flash-' . e($f[1]) . '" role="status">' . e($f[0]) . '</p>';
    }
}

function admin_foot(array $scripts = []): void
{
    echo "</main>\n";
    foreach ($scripts as $js) {
        echo '<script src="' . e($js) . '?v=' . substr(md5_file(__DIR__ . '/../admin/' . $js), 0, 8) . '" defer></script>' . "\n";
    }
    echo "</body>\n</html>\n";
}
