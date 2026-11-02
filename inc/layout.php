<?php
declare(strict_types=1);

/**
 * Hlavička stránky.
 * $o: title, description, path (relativní adresa stránky), image (absolutní URL),
 *     type (og:type), jsonld (pole objektů), nav (aktivní položka menu)
 */
function page_head(array $o): void
{
    $s = settings();
    $base = site_url();
    $title = $o['title'] ?? $s['site_name'];
    $desc = $o['description'] ?? ($s['tagline'] . ' ' . $s['intro']);
    $canonical = $base . '/' . ($o['path'] ?? '');
    $image = $o['image'] ?? $base . '/img/og.jpg';
    $nav = $o['nav'] ?? '';
    $items = ['' => 'Úvod', 'denik.php' => 'Deník', 'mista.php' => 'Místa', 'o-mne.php' => 'O mně'];
    foreach (stranky() as $p) {
        if ($p['menu']) {
            $items['stranka.php?id=' . $p['id']] = $p['menu_label'] ?: $p['title'];
        }
    }
    ?><!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e(excerpt($desc, 160)) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($s['site_name']) ?>">
<meta property="og:locale" content="cs_CZ">
<meta property="og:type" content="<?= e($o['type'] ?? 'website') ?>">
<meta property="og:title" content="<?= e($o['og_title'] ?? $title) ?>">
<meta property="og:description" content="<?= e(excerpt($desc, 200)) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($image) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#1d2329">
<link rel="icon" href="img/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="img/logo-180.png">
<link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
<?php if (is_file(APP_ROOT . '/css/vzhled.css')): ?>
<link rel="stylesheet" href="<?= e(asset('css/vzhled.css')) ?>">
<?php endif; ?>
<?php foreach ($o['jsonld'] ?? [] as $j): ?>
<script type="application/ld+json"><?= json_encode($j, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
</head>
<body>
<a class="skip" href="#obsah">Přeskočit na obsah</a>
<header class="site-header">
  <div class="wrap header-in">
    <a class="brand" href="./" aria-label="<?= e($s['site_name']) ?> – úvod">
      <img src="img/logo-96.png" width="48" height="48" alt="">
      <span><?= e($s['site_name']) ?></span>
    </a>
    <nav aria-label="Hlavní menu">
      <ul>
        <?php foreach ($items as $href => $label): ?>
        <li><a href="<?= $href === '' ? './' : e($href) ?>"<?= $nav === $href ? ' aria-current="page"' : '' ?>><?= e($label) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</header>
<main id="obsah">
<?php
}

function page_foot(): void
{
    $s = settings();
    $friends = data_read('pratele', []);
    ?>
</main>
<?php if ($friends): ?>
<nav class="friends" aria-label="Spřátelené weby">
  <ul class="wrap friends-in">
    <?php foreach ($friends as $f): ?>
    <li><a href="<?= e($f['url']) ?>" rel="noopener"><img src="<?= e($f['img']) ?>" width="<?= intdiv((int)$f['w'], 2) ?>" height="<?= intdiv((int)$f['h'], 2) ?>" alt="<?= e($f['name']) ?>" loading="lazy" decoding="async"></a></li>
    <?php endforeach; ?>
  </ul>
</nav>
<?php endif; ?>
<footer class="site-footer">
  <div class="wrap footer-in">
    <div>
      <p class="footer-brand"><?= e($s['site_name']) ?></p>
      <p><?= e($s['tagline']) ?></p>
    </div>
    <div>
      <p class="footer-h">Kontakt</p>
      <p><a class="plain" href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></p>
      <p><a class="plain" href="<?= e($s['facebook']) ?>" rel="noopener">Facebook BCT Survival</a></p>
    </div>
  </div>
  <div class="wrap footer-bottom">
    <p>© <?= e($s['since']) ?>–<?= date('Y') ?> <?= e($s['site_name']) ?> · <?= e($s['nickname']) ?></p>
    <p>Web vytvořil Michal Nedvídek</p>
  </div>
</footer>
<?php if (!empty($GLOBALS['page_scripts'])): foreach ($GLOBALS['page_scripts'] as $js): ?>
<script src="<?= e(asset($js)) ?>" defer></script>
<?php endforeach; endif; ?>
</body>
</html>
<?php
}

/** Kartotéční lístek v přehledu. */
function entry_card(array $e, bool $hidden = false, string $heading = 'h3'): void
{
    $p = $e['photos'][0] ?? null;
    $places = $e['places'] ?? [];
    $search = mb_strtolower($e['title'] . ' ' . implode(' ', $places) . ' ' . $e['text'], 'UTF-8');
    ?>
<li class="card type-<?= e($e['type']) ?>"<?= $hidden ? ' hidden' : '' ?>
    data-type="<?= e($e['type']) ?>" data-year="<?= e(substr($e['date'], 0, 4)) ?>"
    data-places="<?= e(implode('|', $places)) ?>" data-search="<?= e((string)preg_replace('~\s+~u', ' ', $search)) ?>">
  <article>
    <a class="card-link" href="<?= e(entry_url($e)) ?>">
      <div class="card-img">
        <?php if ($p): ?>
        <img src="<?= e(photo_url($e, $p, true)) ?>" width="<?= (int)($p['sw'] ?? 480) ?>" height="<?= (int)($p['sh'] ?? 360) ?>" alt="" loading="lazy" decoding="async">
        <?php endif; ?>
      </div>
      <div class="card-body">
        <p class="card-tab"><?= e(type_label($e['type'])) ?></p>
        <<?= $heading ?> class="card-title"><?= e($e['title']) ?></<?= $heading ?>>
        <p class="card-meta"><time datetime="<?= e($e['date']) ?>"><?= e(date_cz($e['date'])) ?></time><?php if ($places): ?> · <?= e(implode(', ', array_slice($places, 0, 3))) ?><?php endif; ?></p>
      </div>
    </a>
  </article>
</li>
<?php
}
