<?php
declare(strict_types=1);

/** Přehled záložky Výbava nebo Proviant: stejné kartičky a filtr jako v deníku. */
function sekce_page(string $sek): void
{
    $s = settings();
    $base = site_url();
    $sx = sekce($sek);
    $d = $sx['def'];
    $all = $sx['items'];
    $years = array_values(array_unique(array_map(fn($i) => substr($i['date'], 0, 4), $all)));
    $c1 = $c2 = [];
    foreach ($all as $i) {
        foreach ($i['filter1'] as $k) $c1[$k] = ($c1[$k] ?? 0) + 1;
        foreach ($i['filter2'] as $k) $c2[$k] = ($c2[$k] ?? 0) + 1;
    }

    $v1 = isset($c1[$_GET[$d['p1']] ?? '']) ? $_GET[$d['p1']] : '';
    $v2 = isset($c2[$_GET[$d['p2']] ?? '']) ? $_GET[$d['p2']] : '';
    $rok = in_array($_GET['rok'] ?? '', $years, true) ? $_GET['rok'] : '';
    $q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
    $match = function (array $i) use ($v1, $v2, $rok, $q): bool {
        if ($v1 && !in_array($v1, $i['filter1'], true)) return false;
        if ($v2 && !in_array($v2, $i['filter2'], true)) return false;
        if ($rok && substr($i['date'], 0, 4) !== $rok) return false;
        if ($q !== '') {
            $hay = mb_strtolower($i['title'] . ' ' . implode(' ', array_column($i['tabs'], 1)) . ' ' . $i['text'], 'UTF-8');
            if (!str_contains($hay, mb_strtolower($q, 'UTF-8'))) return false;
        }
        return true;
    };
    $shown = count(array_filter($all, $match));
    $filtered = $v1 || $v2 || $rok || $q;

    $GLOBALS['page_scripts'] = ['js/denik.js'];
    page_head([
        'title' => $d['title'] . ' | ' . $s['site_name'],
        'description' => $d['lead'],
        'path' => $sek . '.php',
        'nav' => $sek . '.php',
        'jsonld' => [[
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $d['title'] . ' – ' . $s['site_name'],
            'url' => $base . '/' . $sek . '.php',
            'inLanguage' => 'cs',
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => count($all),
                'itemListElement' => array_map(fn($i, $n) => [
                    '@type' => 'ListItem', 'position' => $n + 1,
                    'url' => $base . '/' . $i['url'], 'name' => $i['title'],
                ], $all, array_keys($all)),
            ],
        ], [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Úvod', 'item' => $base . '/'],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $d['title'], 'item' => $base . '/' . $sek . '.php'],
            ],
        ]],
    ]);
    ?>
<div class="wrap page-head">
  <nav class="crumbs" aria-label="Drobečková navigace"><a href="./">Úvod</a> <span aria-hidden="true">›</span> <?= e($d['title']) ?></nav>
  <h1><?= e($d['title']) ?></h1>
  <p class="lead"><?= e($d['lead']) ?></p>
</div>

<div class="wrap">
  <?php if (!$all): ?>
  <p class="empty">Tady zatím nic není. První <?= e($d['one']) ?> brzy přibude.</p>
  <?php else: ?>
  <form class="filters" method="get" action="<?= e($sek) ?>.php" role="search" aria-label="Filtr: <?= e($d['title']) ?>" data-p1="<?= e($d['p1']) ?>" data-p2="<?= e($d['p2']) ?>">
    <fieldset class="types">
      <legend><?= e($d['l1']) ?></legend>
      <label class="type-pill"><input type="radio" name="<?= e($d['p1']) ?>" value=""<?= $v1 === '' ? ' checked' : '' ?>><span>Vše <span class="n"><?= count($all) ?></span></span></label>
      <?php foreach ($sx['labels'][0] as $k => $l): if (empty($c1[$k])) continue; ?>
      <label class="type-pill tab-<?= e($d['folders'][0] . '-' . $k) ?>"><input type="radio" name="<?= e($d['p1']) ?>" value="<?= e($k) ?>"<?= $v1 === $k ? ' checked' : '' ?>><span><?= e($l['label']) ?> <span class="n"><?= (int)$c1[$k] ?></span></span></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="filter-row">
      <label><?= e($d['l2']) ?>
        <select name="<?= e($d['p2']) ?>">
          <option value=""><?= e($d['l2_all']) ?></option>
          <?php foreach ($sx['labels'][1] as $k => $l): if (empty($c2[$k])) continue; ?>
          <option value="<?= e($k) ?>"<?= $v2 === $k ? ' selected' : '' ?>><?= e($l['label']) ?> (<?= (int)$c2[$k] ?>)</option>
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
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e($d['placeholder']) ?>" autocomplete="off">
      </label>
      <button class="btn" type="submit">Filtrovat</button>
    </div>
  </form>

  <p class="result-count" role="status" aria-live="polite">
    <span id="count"><?= $shown ?></span> z <?= count($all) ?> položek
    <a id="reset" class="reset" href="<?= e($sek) ?>.php"<?= $filtered ? '' : ' hidden' ?>>Zrušit filtr</a>
  </p>

  <ul class="cards" id="cards">
    <?php foreach ($all as $i) { entry_card($i, !$match($i), 'h2'); } ?>
  </ul>
  <p class="empty" id="empty"<?= $shown ? ' hidden' : '' ?>>Nic takového tu zatím není. Zkuste jiný filtr nebo slovo.</p>
  <?php endif; ?>
</div>
<?php page_foot();
}
