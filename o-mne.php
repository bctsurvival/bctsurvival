<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

$s = settings();
$base = site_url();
$all = entries();
$places = places_count($all);
$about = trim($s['about']) !== '' ? $s['about'] : default_about();

$faq = [
    ['Kde se potulky konají?', 'Nejčastěji v lesích kolem Starého Plzence a hradu Radyně (Zaječí rokle, Mechový vrch, Ostrá Hůrka, Val, Andrejšky, Skalní stezka). Další výpravy vedou na Toužimsko, třeba putování do Skoků přes Sovolusky.'],
    ['Jak často přibývají nové záznamy?', 'Zhruba jednou týdně, obvykle po víkendové potulce. Deník na webu sahá zpátky do roku ' . ($all ? substr(end($all)['date'], 0, 4) : date('Y')) . ', stránka BCT Survival na Facebooku existuje od roku ' . $s['since'] . '.'],
    ['Jak se mi můžete ozvat?', 'Napište na ' . $s['email'] . ' nebo zprávu přes Facebook BCT Survival.'],
];

page_head([
    'title' => 'O mně – ' . $s['nickname'] . ' | ' . $s['site_name'],
    'description' => 'Jsem ' . $s['nickname'] . ' a od roku ' . $s['since'] . ' píšu BCT Survival: potulky, houbaření, pochody a recenze výbavy z okolí Starého Plzence a Toužimi.',
    'path' => 'o-mne.php',
    'type' => 'profile',
    'nav' => 'o-mne.php',
    'image' => $base . '/img/logo-512.png',
    'jsonld' => [[
        '@context' => 'https://schema.org',
        '@type' => 'ProfilePage',
        'url' => $base . '/o-mne.php',
        'inLanguage' => 'cs',
        'mainEntity' => [
            '@type' => 'Person',
            '@id' => $base . '/o-mne.php#mnich',
            'name' => $s['nickname'],
            'alternateName' => $s['site_name'],
            'description' => $s['tagline'] . ' ' . $s['intro'],
            'email' => 'mailto:' . $s['email'],
            'image' => $base . '/img/logo-512.png',
            'sameAs' => [$s['facebook']],
            'knowsAbout' => ['turistika', 'houbaření', 'bushcraft', 'geocaching', 'bojové potravinové dávky', 'nože'],
        ],
    ], [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn($f) => [
            '@type' => 'Question', 'name' => $f[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]],
        ], $faq),
    ]],
]);
?>
<div class="wrap page-head">
  <nav class="crumbs" aria-label="Drobečková navigace"><a href="./">Úvod</a> <span aria-hidden="true">›</span> O mně</nav>
</div>
<div class="wrap profile">
  <aside class="profile-card" aria-label="Profil">
    <img src="img/logo-440.jpg" width="200" height="200" alt="Logo BCT Survival">
    <h1><?= e($s['nickname']) ?></h1>
    <p class="muted">autor <?= e($s['site_name']) ?></p>
    <dl>
      <div><dt>V lese od</dt><dd><?= e($s['since']) ?></dd></div>
      <div><dt>Záznamů</dt><dd><?= count($all) ?></dd></div>
      <div><dt>Domovský les</dt><dd>pod Radyní</dd></div>
    </dl>
    <p><a class="btn full" href="mailto:<?= e($s['email']) ?>">Napsat e-mail</a></p>
    <p><a class="btn btn-ghost full" href="<?= e($s['facebook']) ?>" rel="noopener">Facebook</a></p>
  </aside>
  <div class="prose">
    <p class="lead"><?= e($s['tagline']) ?> <?= e($s['intro']) ?></p>
    <?= format_text($about) ?>
    <h2>Moje místa podle deníku</h2>
    <ul class="chips">
      <?php foreach (array_slice($places, 0, 12, true) as $name => $n): ?>
      <li><a class="chip" href="denik.php?misto=<?= e(rawurlencode($name)) ?>"><?= e($name) ?> <span class="chip-n"><?= $n ?></span></a></li>
      <?php endforeach; ?>
    </ul>
    <h2>Časté otázky</h2>
    <?php foreach ($faq as $f): ?>
    <details class="faq">
      <summary><?= e($f[0]) ?></summary>
      <p><?= e($f[1]) ?></p>
    </details>
    <?php endforeach; ?>
  </div>
</div>
<?php page_foot();

