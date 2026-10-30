<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

$s = settings();
$base = site_url();
$all = entries();
$id = (string)($_GET['id'] ?? '');
$idx = null;
foreach ($all as $i => $x) {
    if ($x['id'] === $id) { $idx = $i; break; }
}

if ($idx === null) {
    http_response_code(404);
    page_head(['title' => 'Záznam nenalezen | ' . $s['site_name'], 'path' => 'denik.php']);
    echo '<div class="wrap page-head"><h1>Tenhle zápis v deníku není</h1>'
        . '<p class="lead">Možná byl přejmenován nebo smazán. Zkuste ho najít v <a href="denik.php">deníku</a>.</p></div>';
    page_foot();
    exit;
}

$e = $all[$idx];
$newer = $all[$idx - 1] ?? null;
$older = $all[$idx + 1] ?? null;
$places = $e['places'] ?? [];
$label = type_label($e['type']);
$desc = $e['text'] !== '' ? excerpt($e['text'], 158)
    : $label . ' z ' . date_cz($e['date']) . ($places ? ' – ' . implode(', ', $places) : '') . '.';
$img = $e['photos'] ? photo_url($e, $e['photos'][0], false, true) : null;

$ld = [
    '@context' => 'https://schema.org',
    '@type' => $e['type'] === 'recenze' ? 'Review' : 'BlogPosting',
    'headline' => $e['title'],
    'datePublished' => $e['date'],
    'dateModified' => substr($e['updated'] ?? $e['date'], 0, 10),
    'inLanguage' => 'cs',
    'url' => entry_url($e, true),
    'mainEntityOfPage' => entry_url($e, true),
    'author' => ['@type' => 'Person', 'name' => $s['nickname'], '@id' => $base . '/o-mne.php#mnich'],
    'publisher' => ['@type' => 'Person', 'name' => $s['nickname'], '@id' => $base . '/o-mne.php#mnich'],
    'description' => $desc,
    'articleSection' => $label,
];
if ($e['type'] === 'recenze') {
    $ld['itemReviewed'] = ['@type' => 'Thing', 'name' => $e['title']];
    $ld['name'] = $e['title'];
    unset($ld['headline'], $ld['articleSection'], $ld['mainEntityOfPage'], $ld['dateModified'], $ld['publisher']);
}
if ($e['photos']) {
    $ld['image'] = array_map(fn($p) => photo_url($e, $p, false, true), $e['photos']);
}
if ($places) {
    $ld[$e['type'] === 'recenze' ? 'locationCreated' : 'contentLocation'] = array_map(
        fn($p) => ['@type' => 'Place', 'name' => $p . ($p === 'Radyně' ? ' (hrad u Starého Plzence)' : ''), 'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'CZ']],
        $places
    );
}

$GLOBALS['page_scripts'] = ['js/galerie.js'];
page_head([
    'title' => $e['title'] . ' (' . date_cz($e['date'], false) . ') | ' . $s['site_name'],
    'og_title' => $e['title'],
    'description' => $desc,
    'path' => entry_url($e),
    'type' => 'article',
    'image' => $img,
    'nav' => 'denik.php',
    'jsonld' => [$ld, [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Úvod', 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Deník', 'item' => $base . '/denik.php'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $e['title'], 'item' => entry_url($e, true)],
        ],
    ]],
]);
?>
<article class="entry">
  <div class="wrap narrow">
    <nav class="crumbs" aria-label="Drobečková navigace"><a href="./">Úvod</a> <span aria-hidden="true">›</span> <a href="denik.php">Deník</a> <span aria-hidden="true">›</span> <?= e($e['title']) ?></nav>
    <header class="entry-head">
      <p class="card-tab tab-<?= e($e['type']) ?>"><?= e($label) ?></p>
      <h1><?= e($e['title']) ?></h1>
      <p class="entry-meta"><time datetime="<?= e($e['date']) ?>"><?= e(date_cz($e['date'])) ?></time> · <?= e($s['nickname']) ?></p>
      <ul class="chips small" aria-label="Druh a místa">
        <li><a class="chip" href="denik.php?typ=<?= e($e['type']) ?>">Všechny: <?= e(mb_strtolower(TYPES_PLURAL[$e['type']] ?? $label)) ?></a></li>
        <?php foreach ($places as $p): ?>
        <li><a class="chip" href="denik.php?misto=<?= e(rawurlencode($p)) ?>"><?= e($p) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </header>

    <?php if ($e['photos']): ?>
    <div class="gallery n<?= count($e['photos']) ?>">
      <?php foreach ($e['photos'] as $i => $p): ?>
      <figure>
        <a href="<?= e(photo_url($e, $p)) ?>" class="zoom" data-caption="<?= e($p['caption'] ?? '') ?>">
          <img src="<?= e(photo_url($e, $p, $i > 0)) ?>" width="<?= (int)($i > 0 ? ($p['sw'] ?? 480) : ($p['w'] ?? 1300)) ?>" height="<?= (int)($i > 0 ? ($p['sh'] ?? 360) : ($p['h'] ?? 975)) ?>"
               alt="<?= e(($p['caption'] ?? '') !== '' ? $p['caption'] : $e['title'] . ' – fotka ' . ($i + 1)) ?>"<?= $i > 0 ? ' loading="lazy"' : ' fetchpriority="high"' ?> decoding="async">
        </a>
        <?php if (($p['caption'] ?? '') !== ''): ?><figcaption><?= e($p['caption']) ?></figcaption><?php endif; ?>
      </figure>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="prose">
      <?= $e['text'] !== '' ? format_text($e['text']) : '<p class="muted">K tomuto záznamu zatím není text, jen fotky.</p>' ?>
    </div>

    <?php if (($e['fb_count'] ?? 0) > count($e['photos'])): ?>
    <p class="muted more-photos">Na webu jsou vybrané fotky. Všech <?= (int)$e['fb_count'] ?> fotek z této potulky najdete na <a href="<?= e($s['facebook']) ?>" rel="noopener">Facebooku BCT Survival</a>.</p>
    <?php endif; ?>

    <nav class="pager" aria-label="Další záznamy">
      <?php if ($older): ?><a class="pager-prev" href="<?= e(entry_url($older)) ?>"><span>Starší</span><?= e($older['title']) ?></a><?php else: ?><span></span><?php endif; ?>
      <?php if ($newer): ?><a class="pager-next" href="<?= e(entry_url($newer)) ?>"><span>Novější</span><?= e($newer['title']) ?></a><?php endif; ?>
    </nav>
  </div>
</article>

<dialog class="lightbox" id="lightbox" aria-label="Zvětšená fotka">
  <form method="dialog"><button class="lb-close" aria-label="Zavřít">×</button></form>
  <img alt="">
  <p class="lb-cap"></p>
</dialog>
<?php page_foot();
