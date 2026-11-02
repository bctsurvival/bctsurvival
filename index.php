<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

$s = settings();
$all = entries();
$base = site_url();
$places = places_count($all);

page_head([
    'title' => $s['site_name'] . ' – potulky, houbaření a recenze výbavy z okolí Radyně a Toužimi',
    'description' => $s['tagline'] . ' ' . $s['intro'],
    'path' => '',
    'nav' => '',
    'jsonld' => [[
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => $s['site_name'],
        'url' => $base . '/',
        'inLanguage' => 'cs',
        'description' => $s['tagline'] . ' ' . $s['intro'],
        'author' => ['@id' => $base . '/o-mne.php#mnich'],
    ], [
        '@context' => 'https://schema.org',
        '@type' => 'Person',
        '@id' => $base . '/o-mne.php#mnich',
        'name' => $s['nickname'],
        'alternateName' => $s['site_name'],
        'url' => $base . '/o-mne.php',
        'email' => 'mailto:' . $s['email'],
        'image' => $base . '/img/logo-512.png',
        'sameAs' => [$s['facebook']],
        'knowsAbout' => ['turistika', 'houbaření', 'bushcraft', 'geocaching', 'bojové potravinové dávky', 'nože'],
        'homeLocation' => ['@type' => 'Place', 'name' => 'Starý Plzenec a okolí'],
    ]],
]);
?>
<section class="hero">
  <div class="wrap hero-in">
    <img class="hero-logo" src="img/logo-440.jpg" width="256" height="256" alt="Logo BCT Survival: hrad Radyně nad lesem, oheň a letopočet 2015">
    <div>
      <p class="kicker">Outdoorový deník od roku <?= e($s['since']) ?></p>
      <h1><?= e($s['site_name']) ?></h1>
      <p class="lead"><?= e($s['tagline']) ?> <?= e($s['intro']) ?></p>
      <p class="actions">
        <a class="btn" href="denik.php">Otevřít deník</a>
        <a class="btn btn-ghost" href="o-mne.php">Kdo je <?= e($s['nickname']) ?></a>
      </p>
    </div>
  </div>
</section>

<section class="wrap facts" aria-labelledby="v-kostce">
  <h2 id="v-kostce" class="visually-hidden">V kostce</h2>
  <p class="summary"><strong><?= e($s['site_name']) ?></strong> je deník potulek, pochodů, houbaření a recenzí outdoorové výbavy, kterou od roku <?= e($s['since']) ?> píše <?= e($s['nickname']) ?>. Většina výprav vede do lesů kolem Starého Plzence a hradu Radyně, další na Toužimsko. Ke každé potulce najdete datum, místa, vyprávění a fotky.</p>
</section>

<section class="wrap section" aria-labelledby="nejnovejsi">
  <div class="section-head">
    <h2 id="nejnovejsi">Nejnovější záznamy</h2>
    <a class="more" href="denik.php">Celý deník</a>
  </div>
  <ul class="cards cards-latest">
    <?php foreach (array_slice($all, 0, 4) as $e) { entry_card($e); } ?>
  </ul>
</section>

<section class="wrap section" aria-labelledby="mista">
  <div class="funfact">
    <p class="funfact-label">Věděli jste?</p>
    <h2 id="mista">Kam chodím nejraději</h2>
    <ul class="places">
      <?php foreach (place_info() as $name => $info): [$iw, $ih] = getimagesize(APP_ROOT . '/' . $info['img']); ?>
      <li class="place">
        <img src="<?= e($info['img']) ?>" width="<?= $iw ?>" height="<?= $ih ?>" alt="" loading="lazy" decoding="async">
        <div class="place-body">
          <?php if (trim($info['kicker'] ?? '') !== ''): ?><p class="place-kicker"><?= e($info['kicker']) ?></p><?php endif; ?>
          <h3><?= e($name) ?></h3>
          <p><?= e($info['text']) ?></p>
          <p><a href="denik.php?misto=<?= e(rawurlencode($name)) ?>">Zápisy z místa <?= e($name) ?> (<?= (int)($places[$name] ?? 0) ?>)</a></p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<section class="wrap section about-teaser" aria-labelledby="kdo">
  <div>
    <h2 id="kdo">Kdo píše</h2>
    <p>Jsem <?= e($s['nickname']) ?>. Skoro každý víkend balím batoh, vařič a něco dobrého k jídlu a mizím do lesa. Píšu o tom, kudy jsem šel, co rostlo, co jsem uvařil a jak se osvědčila výbava.</p>
    <p><a class="btn btn-ghost" href="o-mne.php">Více o mně</a></p>
  </div>
  <div class="contact-box">
    <h2>Napište mi</h2>
    <p><a class="plain big" href="mailto:<?= e($s['email']) ?>"><?= e($s['email']) ?></a></p>
    <p>Novinky z každé potulky najdete i na <a href="<?= e($s['facebook']) ?>" rel="noopener">Facebooku</a>.</p>
  </div>
</section>
<?php page_foot();
