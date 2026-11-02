<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

$s = settings();
$base = site_url();
$p = stranka_find((string)($_GET['id'] ?? ''));
if (!$p) {
    http_response_code(404);
    page_head(['title' => 'Stránka nenalezena | ' . $s['site_name'], 'path' => '']);
    echo '<div class="wrap section"><h1>Stránka nenalezena</h1><p><a href="./">Zpět na úvod</a></p></div>';
    page_foot();
    return;
}
$path = 'stranka.php?id=' . $p['id'];
$dir = 'foto/stranka-' . rawurlencode($p['id']) . '/';
$small = fn($f) => preg_replace('~\.jpg$~', '-m.jpg', $f);
page_head([
    'title' => $p['title'] . ' | ' . $s['site_name'],
    'description' => $p['description'] ?: excerpt($p['text'], 160),
    'path' => $path,
    'nav' => $path,
    'image' => $p['photos'] ? $base . '/' . $dir . rawurlencode($p['photos'][0]['file']) : null,
    'jsonld' => [[
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $p['title'],
        'url' => $base . '/' . $path,
        'inLanguage' => 'cs',
        'isPartOf' => ['@type' => 'WebSite', 'name' => $s['site_name'], 'url' => $base . '/'],
    ]],
]);
?>
<div class="wrap page-head">
  <nav class="crumbs" aria-label="Drobečková navigace"><a href="./">Úvod</a> <span aria-hidden="true">›</span> <?= e($p['title']) ?></nav>
</div>
<article class="wrap section prose">
  <h1><?= e($p['title']) ?></h1>
  <?= format_text($p['text']) ?>
  <?php if ($p['photos']): ?>
  <ul class="page-gallery">
    <?php foreach ($p['photos'] as $ph): ?>
    <li><figure>
      <a href="<?= e($dir . rawurlencode($ph['file'])) ?>"><img src="<?= e($dir . rawurlencode($small($ph['file']))) ?>" width="<?= (int)$ph['sw'] ?>" height="<?= (int)$ph['sh'] ?>" alt="<?= e($ph['caption']) ?>" loading="lazy" decoding="async"></a>
      <?php if ($ph['caption'] !== ''): ?><figcaption><?= e($ph['caption']) ?></figcaption><?php endif; ?>
    </figure></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</article>
<?php page_foot();
