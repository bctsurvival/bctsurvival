<?php
/**
 * BCT Survival – společné funkce šablon.
 * Stránky se vykreslí při sestavení webu (build/build.py) do statických HTML.
 * Data připraví sestavení z content/ do data/*.json.php.
 */

declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';
const DATA_DIR = APP_ROOT . '/data';
const FOTO_DIR = APP_ROOT . '/foto';
const MAX_PHOTOS = 3;          // max. fotek u jednoho záznamu
const PHOTO_BIG = 1300;        // delší strana velké fotky (px)
const PHOTO_SMALL = 480;       // delší strana náhledu (px)

// Soubory s daty začínají PHP zarážkou, takže je nikdo nestáhne
// ani na hostingu, který neumí .htaccess.
const DATA_GUARD = "<?php exit; ?>\n";

const TYPES = [
    'potulka'    => 'Potulka',
    'pochod'     => 'Pochod a putování',
    'recenze'    => 'Recenze a výbava',
    'geocaching' => 'Geocaching',
    'misto'      => 'Místo',
    'akce'       => 'Akce',
];

const TYPES_PLURAL = [
    'potulka'    => 'Potulky',
    'pochod'     => 'Pochody a putování',
    'recenze'    => 'Recenze a výbava',
    'geocaching' => 'Geocaching',
    'misto'      => 'Místa',
    'akce'       => 'Akce',
];

const CZ_MONTHS = ['', 'ledna', 'února', 'března', 'dubna', 'května', 'června',
    'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];

/* ---------- ukládání dat ---------- */

function data_read(string $name, $default)
{
    $file = DATA_DIR . "/$name.json.php";
    if (!is_file($file)) {
        return $default;
    }
    $raw = (string)file_get_contents($file);
    if (str_starts_with($raw, DATA_GUARD)) {
        $raw = substr($raw, strlen(DATA_GUARD));
    }
    $val = json_decode($raw, true);
    return $val ?? $default;
}

function data_write(string $name, $value): void
{
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $file = DATA_DIR . "/$name.json.php";
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp = $file . '.tmp';
    file_put_contents($tmp, DATA_GUARD . $json, LOCK_EX);
    rename($tmp, $file);
}

function settings(): array
{
    static $s = null;
    if ($s === null) {
        $s = array_merge(default_settings(), data_read('settings', []));
    }
    return $s;
}

function default_settings(): array
{
    return [
        'site_name'   => 'BCT Survival',
        'nickname'    => 'Mnich',
        'tagline'     => 'Každý týden mizím mezi stromy.',
        'intro'       => 'Potulky, houbaření, lesní stezky, ranní mlhy, jídlo z batohu a příběhy z míst, kde se dá na chvíli zapomenout na okolní svět.',
        'about'       => '',
        'email'       => 'bctsurvival@seznam.cz',
        'facebook'    => 'https://www.facebook.com/BCTSurvival',
        'site_url'    => '',
        'since'       => '2015',
    ];
}

/** Všechny záznamy, nejnovější první. */
function entries(bool $onlyPublished = true): array
{
    $all = data_read('entries', []);
    if ($onlyPublished) {
        $all = array_values(array_filter($all, fn($e) => !empty($e['published'])));
    }
    usort($all, fn($a, $b) => strcmp($b['date'] . $b['id'], $a['date'] . $a['id']));
    return $all;
}

function entry_find(string $id, bool $onlyPublished = true): ?array
{
    foreach (entries($onlyPublished) as $e) {
        if ($e['id'] === $id) {
            return $e;
        }
    }
    return null;
}

/** Místa se počtem záznamů, seřazená podle četnosti. */
function places_count(array $entries): array
{
    $c = [];
    foreach ($entries as $e) {
        foreach ($e['places'] ?? [] as $p) {
            $c[$p] = ($c[$p] ?? 0) + 1;
        }
    }
    uksort($c, fn($a, $b) => ($c[$b] <=> $c[$a]) ?: strcoll($a, $b));
    return $c;
}


/** Zajímavosti o vybraných místech (content/mista.json, „Zobrazit na úvodu“). */
function place_info(): array
{
    $out = [];
    foreach (data_read('mista', ['mista' => []])['mista'] ?? [] as $m) {
        if (!empty($m['featured']) && trim($m['text'] ?? '') !== '' && !empty($m['img'])) {
            $out[$m['name']] = $m;
        }
    }
    return $out;
}

/* ---------- pomocné funkce ---------- */

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $s): string
{
    $map = ['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o','ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z'];
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, $map);
    $s = (string)preg_replace('~[^a-z0-9]+~', '-', $s);
    return substr(trim($s, '-'), 0, 60);
}

function date_cz(string $iso, bool $long = true): string
{
    [$y, $m, $d] = array_map('intval', explode('-', $iso));
    return $long ? "$d. " . CZ_MONTHS[$m] . " $y" : "$d. $m. $y";
}

function type_label(string $t): string
{
    return TYPES[$t] ?? 'Záznam';
}

/** Kořenová adresa webu bez lomítka na konci, např. https://bct.wz.cz */
function site_url(): string
{
    $u = trim(settings()['site_url']);
    if ($u !== '') {
        return rtrim($u, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function entry_url(array $e, bool $abs = false): string
{
    return ($abs ? site_url() . '/' : '') . 'zaznam.php?id=' . rawurlencode($e['id']);
}

function photo_url(array $e, array $p, bool $small = false, bool $abs = false): string
{
    $f = $small ? preg_replace('~\.jpg$~', '-m.jpg', $p['file']) : $p['file'];
    return ($abs ? site_url() . '/' : '') . 'foto/' . rawurlencode($e['id']) . '/' . rawurlencode($f);
}

/** Krátký výtah z textu pro popisky a meta description. */
function excerpt(string $text, int $len = 160): string
{
    $t = trim((string)preg_replace('~\s+~u', ' ', $text));
    if (mb_strlen($t) <= $len) {
        return $t;
    }
    $cut = mb_substr($t, 0, $len - 1);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: $len - 1), ' ,.;:-–') . '…';
}

/**
 * Jednoduché formátování textu: prázdný řádek = nový odstavec,
 * „## “ = nadpis, „- “ = odrážka, adresy webů se stanou odkazy.
 */
function format_text(string $text): string
{
    $text = str_replace("\r", '', trim($text));
    if ($text === '') {
        return '';
    }
    $out = '';
    foreach (preg_split("~\n\s*\n~", $text) as $block) {
        $lines = explode("\n", trim($block));
        if (preg_match('~^#{2,3} ~', $lines[0])) {
            $out .= '<h2>' . inline(ltrim(array_shift($lines), '# ')) . "</h2>\n";
            if (!$lines) {
                continue;
            }
        }
        if (count(array_filter($lines, fn($l) => preg_match('~^[-•*+]\s~u', $l))) === count($lines)) {
            $out .= "<ul>\n";
            foreach ($lines as $l) {
                $out .= '<li>' . inline((string)preg_replace('~^[-•*+]\s+~u', '', $l)) . "</li>\n";
            }
            $out .= "</ul>\n";
        } elseif (count(array_filter($lines, fn($l) => preg_match('~^\d+[.)]\s~', $l))) === count($lines)) {
            $out .= "<ol>\n";
            foreach ($lines as $l) {
                $out .= '<li>' . inline((string)preg_replace('~^\d+[.)]\s+~', '', $l)) . "</li>\n";
            }
            $out .= "</ol>\n";
        } else {
            $out .= '<p>' . implode("<br>\n", array_map('inline', $lines)) . "</p>\n";
        }
    }
    return $out;
}

function inline(string $s): string
{
    $s = str_replace('\\', '', $s); // escapování z editoru (např. 1\. ledna)
    $links = [];
    // [text](adresa) z editoru; schováme, aby je nepřepsalo automatické odkazování
    $s = (string)preg_replace_callback('~\[([^\]]+)\]\((https?://[^\s)]+)\)~', function ($m) use (&$links) {
        $links[] = '<a href="' . e($m[2]) . '" rel="noopener">' . e($m[1]) . '</a>';
        return "\x01" . (count($links) - 1) . "\x01";
    }, $s);
    $s = e($s);
    $s = (string)preg_replace('~(\*\*|__)(.+?)\1~u', '<strong>$2</strong>', $s);
    $s = (string)preg_replace('~(?<![\w*])\*(?![\s*])(.+?)(?<!\s)\*(?![\w*])~u', '<em>$1</em>', $s);
    $s = (string)preg_replace_callback(
        '~\b(https?://[^\s<\x01]+|www\.[a-z0-9.-]+\.[a-z]{2,}[^\s<\x01]*)~i',
        function ($m) {
            $url = rtrim($m[1], '.,);');
            $tail = substr($m[1], strlen($url));
            $href = str_starts_with($url, 'http') ? $url : 'https://' . $url;
            return '<a href="' . $href . '" rel="noopener">' . $url . '</a>' . $tail;
        },
        $s
    );
    return (string)preg_replace_callback("~\x01(\d+)\x01~", fn($m) => $links[(int)$m[1]], $s);
}

/** Otisk souboru pro obejití staré verze v mezipaměti prohlížeče. */
function asset(string $path): string
{
    $f = APP_ROOT . '/' . $path;
    return $path . (is_file($f) ? '?v=' . substr(md5_file($f), 0, 8) : '');
}

/* ---------- statické soubory pro vyhledávače a AI ---------- */

/** Vytvoří sitemap.xml, llms.txt a robots.txt (volá sestavení webu). */
function regen_static(): void
{
    $s = settings();
    $base = site_url();
    $list = entries();

    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    $last = $list[0]['updated'] ?? ($list[0]['date'] ?? date('Y-m-d'));
    foreach (['' => $last, 'denik.php' => $last, 'o-mne.php' => $last] as $u => $d) {
        $x .= "  <url><loc>" . e("$base/$u") . "</loc><lastmod>" . substr($d, 0, 10) . "</lastmod></url>\n";
    }
    foreach ($list as $e) {
        $x .= "  <url><loc>" . e(entry_url($e, true)) . "</loc><lastmod>"
            . substr($e['updated'] ?? $e['date'], 0, 10) . "</lastmod></url>\n";
    }
    $x .= "</urlset>\n";
    file_put_contents(APP_ROOT . '/sitemap.xml', $x);

    $byType = [];
    foreach ($list as $e) {
        $byType[$e['type']][] = $e;
    }
    $places = array_slice(array_keys(places_count($list)), 0, 12);
    $t = "# {$s['site_name']}\n\n"
        . "> Outdoorový deník potulek, pochodů a recenzí výbavy. Píše {$s['nickname']} od roku {$s['since']}, "
        . "převážně z okolí Starého Plzence (hrad Radyně) a Toužimi.\n\n"
        . "{$s['tagline']} {$s['intro']}\n\n"
        . "- Autor: {$s['nickname']} (přezdívka)\n"
        . "- Kontakt: {$s['email']}\n"
        . "- Facebook: {$s['facebook']}\n"
        . "- Počet záznamů: " . count($list) . "\n"
        . "- Nejčastější místa: " . implode(', ', $places) . "\n\n"
        . "## Hlavní stránky\n\n"
        . "- [Úvod]($base/): kdo je {$s['nickname']} a co na webu najdete\n"
        . "- [Deník]($base/denik.php): všechny záznamy s filtrem podle druhu, místa a roku\n"
        . "- [O mně]($base/o-mne.php): profil autora\n";
    foreach (TYPES_PLURAL as $k => $label) {
        if (empty($byType[$k])) {
            continue;
        }
        $t .= "\n## $label\n\n";
        foreach ($byType[$k] as $e) {
            $t .= "- [" . date_cz($e['date'], false) . " – {$e['title']}](" . entry_url($e, true) . ")"
                . ($e['text'] ? ': ' . excerpt($e['text'], 140) : '') . "\n";
        }
    }
    file_put_contents(APP_ROOT . '/llms.txt', $t);

    file_put_contents(APP_ROOT . '/robots.txt', "# Vyhledávače i AI asistenti jsou vítáni\nUser-agent: *\nAllow: /\nDisallow: /admin/\n\nSitemap: $base/sitemap.xml\n");
}
