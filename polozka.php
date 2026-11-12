<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

/* Detail položky ve Výbavě nebo Proviantu (stejné zobrazení jako zápis v deníku). */
$s = settings();
$base = site_url();
$sek = (string)($_GET['s'] ?? '');
if (!isset(sekce_def()[$sek])) {
    $sek = 'vybava';
}
$sx = sekce($sek);
$d = $sx['def'];
$all = $sx['items'];
$id = (string)($_GET['id'] ?? '');
$idx = null;
foreach ($all as $n => $x) {
    if ($x['id'] === $id) { $idx = $n; break; }
}

if ($idx === null) {
    http_response_code(404);
    page_head(['title' => 'Položka nenalezena | ' . $s['site_name'], 'path' => $sek . '.php', 'nav' => $sek . '.php']);
    echo '<div class="wrap page-head"><h1>Tahle položka tu není</h1>'
        . '<p class="lead">Možná byla přejmenována nebo smazána. Zkuste ji najít na stránce <a href="' . e($sek) . '.php">' . e($d['title']) . '</a>.</p></div>';
    page_foot();
    exit;
}

$i = $all[$idx];
$newer = $all[$idx - 1] ?? null;
$older = $all[$idx + 1] ?? null;
$tags = array_column($i['tabs'], 1);
$url = $base . '/' . $i['url'];
$desc = $i['text'] !== '' ? excerpt($i['text'], 158)
    : $d['title'] . ': ' . $i['title'] . ($tags ? ' (' . implode(', ', $tags) . ')' : '') . '.';

$ld = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $i['title'],
    'datePublished' => $i['date'],
    'dateModified' => substr($i['updated'] ?: $i['date'], 0, 10),
    'inLanguage' => 'cs',
    'url' => $url,
    'mainEntityOfPage' => $url,
    'author' => ['@type' => 'Person', 'name' => $s['nickname'], '@id' => $base . '/o-mne.php#mnich'],
    'publisher' => ['@type' => 'Person', 'name' => $s['nickname'], '@id' => $base . '/o-mne.php#mnich'],
    'description' => $desc,
    'articleSection' => $d['title'],
];
if ($tags) {
    $ld['keywords'] = implode(', ', $tags);
}
if ($i['photos']) {
    $ld['image'] = array_map(fn($p) => photo_url($i, $p, false, true), $i['photos']);
}

$GLOBALS['page_scripts'] = ['js/galerie.js'];
page_head([
    'title' => $i['title'] . ' | ' . $d['title'] . ' | ' . $s['site_name'],
    'og_title' => $i['title'],
    'description' => $desc,
    'path' => $i['url'],
    'type' => 'article',
    'image' => $i['photos'] ? photo_url($i, $i['photos'][0], false, true) : null,
    'nav' => $sek . '.php',
    'jsonld' => [$ld, [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Úvod', 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $d['title'], 'item' => $base . '/' . $sek . '.php'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $i['title'], 'item' => $url],
        ],
    ]],
]);
?>
<article class="entry">
  <div class="wrap narrow">
    <nav class="crumbs" aria-label="Drobečková navigace"><a href="./">Úvod</a> <span aria-hidden="true">›</span> <a href="<?= e($sek) ?>.php"><?= e($d['title']) ?></a> <span aria-hidden="true">›</span> <?= e($i['title']) ?></nav>
    <header class="entry-head">
      <?php if ($i['tabs']): ?><p class="card-tabs"><?php foreach ($i['tabs'] as [$cls, $lbl]): ?><span class="card-tab <?= e($cls) ?>"><?= e($lbl) ?></span><?php endforeach; ?></p><?php endif; ?>
      <h1><?= e($i['title']) ?></h1>
      <p class="entry-meta"><time datetime="<?= e($i['date']) ?>"><?= e(date_cz($i['date'])) ?></time> · <?= e($s['nickname']) ?></p>
      <?php if ($i['tabs']): ?>
      <ul class="chips small" aria-label="<?= e($d['l1'] . ' a ' . mb_strtolower($d['l2'], 'UTF-8')) ?>">
        <?php foreach ($i['filter1'] as $k): ?>
        <li><a class="chip" href="<?= e($sek . '.php?' . $d['p1'] . '=' . rawurlencode($k)) ?>">Vše: <?= e($sx['labels'][0][$k]['label']) ?></a></li>
        <?php endforeach; ?>
        <?php foreach ($i['filter2'] as $k): ?>
        <li><a class="chip" href="<?= e($sek . '.php?' . $d['p2'] . '=' . rawurlencode($k)) ?>">Vše od: <?= e($sx['labels'][1][$k]['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </header>

    <?php if ($i['photos']): ?>
    <div class="gallery n<?= count($i['photos']) ?>">
      <?php foreach ($i['photos'] as $n => $p): ?>
      <figure>
        <a href="<?= e(photo_url($i, $p)) ?>" class="zoom" data-caption="<?= e($p['caption'] ?? '') ?>">
          <img src="<?= e(photo_url($i, $p, $n > 0)) ?>" width="<?= (int)($n > 0 ? ($p['sw'] ?? 480) : ($p['w'] ?? 1300)) ?>" height="<?= (int)($n > 0 ? ($p['sh'] ?? 360) : ($p['h'] ?? 975)) ?>"
               alt="<?= e(($p['caption'] ?? '') !== '' ? $p['caption'] : $i['title'] . ' – fotka ' . ($n + 1)) ?>"<?= $n > 0 ? ' loading="lazy"' : ' fetchpriority="high"' ?> decoding="async">
        </a>
        <?php if (($p['caption'] ?? '') !== ''): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif; ?>
      </figure>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="prose">
      <?= $i['text'] !== '' ? format_text($i['text']) : '<p class="muted">K této položce zatím není text, jen fotky.</p>' ?>
    </div>

    <nav class="pager" aria-label="Další položky">
      <?php if ($older): ?><a class="pager-prev" href="<?= e($older['url']) ?>"><span>Starší</span><?= e($older['title']) ?></a><?php else: ?><span></span><?php endif; ?>
      <?php if ($newer): ?><a class="pager-next" href="<?= e($newer['url']) ?>"><span>Novější</span><?= e($newer['title']) ?></a><?php endif; ?>
    </nav>
  </div>
</article>

<dialog class="lightbox" id="lightbox" aria-label="Zvětšená fotka">
  <form method="dialog"><button class="lb-close" aria-label="Zavřít">×</button></form>
  <img alt="">
  <p class="lb-cap"></p>
</dialog>
<?php page_foot();
