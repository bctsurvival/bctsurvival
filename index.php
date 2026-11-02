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
  <div class="section-head">
    <h2 id="mista">Kam chodím nejčastěji</h2>
  </div>
  <ul class="chips">
    <?php foreach (array_slice($places, 0, 14, true) as $name => $n): ?>
    <li><a class="chip" href="denik.php?misto=<?= e(rawurlencode($name)) ?>"><?= e($name) ?> <span class="chip-n"><?= $n ?></span></a></li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="wrap section" aria-label="Věděli jste?">
  <div class="funfact" id="funfact">
    <div class="funfact-top">
      <p class="funfact-label">Věděli jste?</p>
      <?php if (count(place_info()) > 1): ?><button type="button" class="btn btn-ghost funfact-next" hidden>Další zajímavost</button><?php endif; ?>
    </div>
    <?php $fi = 0; foreach (place_info() as $name => $info): [$iw, $ih] = getimagesize(APP_ROOT . '/' . $info['img']); ?>
    <div class="funfact-item"<?= $fi++ ? ' hidden' : '' ?>>
      <img src="<?= e($info['img']) ?>" width="<?= $iw ?>" height="<?= $ih ?>" alt="" loading="lazy" decoding="async">
      <div>
        <h2><?= e($name) ?></h2>
        <p><?= e($info['fact']) ?></p>
        <p><a href="mista.php#<?= e(slugify($name)) ?>">Další zajímavá místa</a> · <a href="denik.php?misto=<?= e(rawurlencode($name)) ?>">Zápisy odtud</a></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<script>
// Náhodné místo při načtení, pak střídání po 30 s (pozastaví se pod myší nebo při ovládání klávesnicí)
(function () {
  var box = document.getElementById('funfact');
  var items = box ? box.querySelectorAll('.funfact-item') : [];
  if (items.length < 2) return;
  var btn = box.querySelector('.funfact-next');
  var n = Math.floor(Math.random() * items.length), paused = false;
  function show(i) { n = i % items.length; items.forEach(function (el, j) { el.hidden = j !== n; }); }
  show(n);
  btn.hidden = false;
  btn.addEventListener('click', function () { show(n + 1); });
  box.addEventListener('mouseenter', function () { paused = true; });
  box.addEventListener('mouseleave', function () { paused = false; });
  box.addEventListener('focusin', function () { paused = true; });
  box.addEventListener('focusout', function () { paused = false; });
  setInterval(function () { if (!paused && !document.hidden) show(n + 1); }, 30000);
})();
</script>

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
