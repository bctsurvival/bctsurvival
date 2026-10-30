<?php
declare(strict_types=1);
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/layout.php';

$s = settings();
$all = entries();
$base = site_url();
$places = places_count($all);
$years = array_values(array_unique(array_map(fn($e) => substr($e['date'], 0, 4), $all)));
$counts = array_count_values(array_column($all, 'type'));

$typ = isset(TYPES[$_GET['typ'] ?? '']) ? $_GET['typ'] : '';
$misto = isset($places[$_GET['misto'] ?? '']) ? $_GET['misto'] : '';
$rok = in_array($_GET['rok'] ?? '', $years, true) ? $_GET['rok'] : '';
$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));

$match = function (array $e) use ($typ, $misto, $rok, $q): bool {
    if ($typ && $e['type'] !== $typ) return false;
    if ($misto && !in_array($misto, $e['places'] ?? [], true)) return false;
    if ($rok && substr($e['date'], 0, 4) !== $rok) return false;
    if ($q !== '') {
        $hay = mb_strtolower($e['title'] . ' ' . implode(' ', $e['places'] ?? []) . ' ' . $e['text'], 'UTF-8');
        if (!str_contains($hay, mb_strtolower($q, 'UTF-8'))) return false;
    }
    return true;
};
$shown = count(array_filter($all, $match));

$heading = 'Kartotéka';
$bits = [];
if ($typ) $bits[] = mb_strtolower(TYPES_PLURAL[$typ], 'UTF-8');
if ($misto) $bits[] = $misto;
if ($rok) $bits[] = $rok;
$title = ($bits ? 'Kartotéka: ' . implode(', ', $bits) : 'Kartotéka potulek, pochodů a recenzí') . ' | ' . $s['site_name'];
$filtered = $typ || $misto || $rok || $q;
$path = 'kartoteka.php' . ($misto && !$typ && !$rok && !$q ? '?misto=' . rawurlencode($misto) : '');

$GLOBALS['page_scripts'] = ['js/kartoteka.js'];
page_head([
    'title' => $title,
    'description' => 'Všechny záznamy ' . $s['site_name'] . ' na jednom místě: ' . count($all)
        . ' potulek, pochodů, recenzí a míst. Hledejte podle druhu, místa, roku nebo slova.',
    'path' => $path,
    'nav' => 'kartoteka.php',
    'jsonld' => [[
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => 'Kartotéka ' . $s['site_name'],
        'url' => $base . '/kartoteka.php',
        'inLanguage' => 'cs',
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($all),
            'itemListElement' => array_map(fn($e, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1,
                'url' => entry_url($e, true), 'name' => $e['title'],
            ], $all, array_keys($all)),
        ],
    ], [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Úvod', 'item' => $base . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Kartotéka', 'item' => $base . '/kartoteka.php'],
        ],
    ]],
]);
?>
<div class="wrap page-head">
  <nav class="crumbs" aria-label="Drobečková navigace"><a href="./">Úvod</a> <span aria-hidden="true">›</span> Kartotéka</nav>
  <h1><?= e($heading) ?></h1>
  <p class="lead">Všechny potulky, pochody, recenze a místa na jednom místě. Vyberte druh, místo nebo rok, případně hledejte podle slova.</p>
</div>

<div class="wrap">
  <form class="filters" method="get" action="kartoteka.php" role="search" aria-label="Filtr kartotéky">
    <fieldset class="types">
      <legend>Druh záznamu</legend>
      <label class="type-pill"><input type="radio" name="typ" value=""<?= $typ === '' ? ' checked' : '' ?>><span>Vše <span class="n"><?= count($all) ?></span></span></label>
      <?php foreach (TYPES_PLURAL as $k => $label): if (empty($counts[$k])) continue; ?>
      <label class="type-pill type-<?= e($k) ?>"><input type="radio" name="typ" value="<?= e($k) ?>"<?= $typ === $k ? ' checked' : '' ?>><span><?= e($label) ?> <span class="n"><?= (int)$counts[$k] ?></span></span></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="filter-row">
      <label>Místo
        <select name="misto">
          <option value="">Všechna místa</option>
          <?php foreach ($places as $name => $n): ?>
          <option value="<?= e($name) ?>"<?= $misto === $name ? ' selected' : '' ?>><?= e($name) ?> (<?= $n ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Rok
        <select name="rok">
          <option value="">Všechny roky</option>
          <?php foreach ($years as $y): ?>
          <option value="<?= e($y) ?>"<?= $rok === $y ? ' selected' : '' ?>><?= e($y) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="grow">Hledat
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="např. křemenáč, BPD, Skalní stezka" autocomplete="off">
      </label>
      <button class="btn" type="submit">Filtrovat</button>
    </div>
  </form>

  <p class="result-count" role="status" aria-live="polite">
    <span id="count"><?= $shown ?></span> z <?= count($all) ?> záznamů
    <a id="reset" class="reset" href="kartoteka.php"<?= $filtered ? '' : ' hidden' ?>>Zrušit filtr</a>
  </p>

  <ul class="cards" id="cards">
    <?php foreach ($all as $e) { entry_card($e, !$match($e), 'h2'); } ?>
  </ul>
  <p class="empty" id="empty"<?= $shown ? ' hidden' : '' ?>>Takový záznam v kartotéce zatím není. Zkuste jiné místo nebo slovo.</p>
</div>
<?php page_foot();
