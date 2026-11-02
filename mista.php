<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

$s = settings();
$base = site_url();
$all = entries();
$places = places_count($all);
$info = place_info();
$rest = array_diff_key($places, $info);

page_head([
    'title' => 'Místa, kam chodím | ' . $s['site_name'],
    'description' => 'Zajímavosti o místech, kam ' . $s['nickname'] . ' chodí nejraději: ' . implode(', ', array_keys($info)) . '.',
    'path' => 'mista.php',
    'nav' => 'mista.php',
    'jsonld' => [[
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'Místa, kam chodím',
        'itemListElement' => array_values(array_map(fn($n, $i) => [
            '@type' => 'ListItem', 'position' => $i + 1,
            'item' => ['@type' => 'Place', 'name' => $n, 'description' => $info[$n]['text'], 'address' => ['@type' => 'PostalAddress', 'addressCountry' => 'CZ']],
        ], array_keys($info), array_keys(array_keys($info)))),
    ]],
]);
?>
<div class="wrap page-head">
  <nav class="crumbs" aria-label="Drobečková navigace"><a href="./">Úvod</a> <span aria-hidden="true">›</span> Místa</nav>
  <h1>Místa, kam chodím</h1>
  <p class="lead">Zajímavosti o místech, která se v deníku objevují nejčastěji. U každého najdete odkaz na zápisy odtud.</p>
</div>
<div class="wrap">
  <ul class="place-list">
    <?php foreach ($info as $name => $p): [$iw, $ih] = getimagesize(APP_ROOT . '/' . $p['img']); ?>
    <li class="place-row" id="<?= e(slugify($name)) ?>">
      <img src="<?= e($p['img']) ?>" width="<?= $iw ?>" height="<?= $ih ?>" alt="<?= e($name) ?>" loading="lazy" decoding="async">
      <div>
        <?php if (trim($p['kicker'] ?? '') !== ''): ?><p class="place-kicker"><?= e($p['kicker']) ?></p><?php endif; ?>
        <h2><?= e($name) ?></h2>
        <p><?= e($p['text']) ?></p>
        <p><a class="btn btn-ghost" href="denik.php?misto=<?= e(rawurlencode($name)) ?>">Zápisy odtud (<?= (int)($places[$name] ?? 0) ?>)</a></p>
      </div>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($rest): ?>
  <h2>Další místa v deníku</h2>
  <ul class="chips">
    <?php foreach ($rest as $name => $n): ?>
    <li><a class="chip" href="denik.php?misto=<?= e(rawurlencode($name)) ?>"><?= e($name) ?> <span class="chip-n"><?= $n ?></span></a></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php page_foot();
