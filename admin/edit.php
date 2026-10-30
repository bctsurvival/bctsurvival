<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin.php';
require_login();

$list = data_read('entries', []);
$id = (string)($_GET['id'] ?? '');
$idx = null;
foreach ($list as $i => $x) {
    if ($x['id'] === $id) { $idx = $i; break; }
}
if ($id !== '' && $idx === null) {
    flash('Záznam nebyl nalezen.', 'err');
    header('Location: index.php');
    exit;
}
$isNew = $idx === null;
$e = $isNew ? [
    'id' => '', 'date' => date('Y-m-d'), 'title' => '', 'type' => 'potulka', 'places' => [],
    'text' => '', 'photos' => [], 'fb_count' => 0, 'published' => true, 'source' => 'admin',
] : $list[$idx];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (($_POST['akce'] ?? '') === 'smazat' && !$isNew) {
        rrmdir(FOTO_DIR . '/' . $e['id']);
        array_splice($list, $idx, 1);
        save_entries($list);
        flash('Záznam „' . $e['title'] . '“ byl smazán.');
        header('Location: index.php');
        exit;
    }

    $e['title'] = trim((string)($_POST['title'] ?? ''));
    $date = (string)($_POST['date'] ?? '');
    $e['type'] = array_key_exists($_POST['type'] ?? '', TYPES) ? $_POST['type'] : 'potulka';
    $e['text'] = str_replace("\r\n", "\n", trim((string)($_POST['text'] ?? '')));
    $e['published'] = !empty($_POST['published']);

    $places = array_map('trim', (array)($_POST['places'] ?? []));
    foreach (explode(',', (string)($_POST['new_places'] ?? '')) as $p) {
        $places[] = trim($p);
    }
    $e['places'] = array_values(array_unique(array_filter($places, fn($p) => $p !== '')));

    if ($e['title'] === '') {
        $errors[] = 'Vyplňte název.';
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4))) {
        $errors[] = 'Datum není platné.';
    } else {
        $e['date'] = $date;
    }

    if (!$errors) {
        if ($isNew) {
            $base = $e['date'] . '-' . (slugify($e['title']) ?: 'zaznam');
            $base = substr($base, 0, 70);
            $newId = $base;
            $ids = array_column($list, 'id');
            for ($n = 2; in_array($newId, $ids, true) || is_dir(FOTO_DIR . "/$newId"); $n++) {
                $newId = "$base-$n";
            }
            $e['id'] = $newId;
            $e['created'] = date('c');
        }
        $dir = FOTO_DIR . '/' . $e['id'];

        // Stávající fotky: popisky, mazání, hlavní fotka
        $kept = [];
        foreach ($e['photos'] as $i => $p) {
            if (!empty($_POST['del_photo'][$i])) {
                @unlink("$dir/{$p['file']}");
                @unlink("$dir/" . str_replace('.jpg', '-m.jpg', $p['file']));
                continue;
            }
            $p['caption'] = trim((string)($_POST['caption'][$i] ?? ''));
            $p['_main'] = ((string)($_POST['main'] ?? '') === (string)$i);
            $kept[] = $p;
        }

        // Nové fotky
        $files = $_FILES['upload'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $k => $name) {
                if ($files['error'][$k] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if (count($kept) >= MAX_PHOTOS) {
                    $errors[] = 'Záznam může mít nejvýš ' . MAX_PHOTOS . ' fotky, další se neuložily.';
                    break;
                }
                // Nový název souboru, aby prohlížeč neukazoval starou fotku z mezipaměti.
                $r = process_upload([
                    'name' => $name, 'tmp_name' => $files['tmp_name'][$k], 'error' => $files['error'][$k],
                ], $dir, date('ymd') . '-' . bin2hex(random_bytes(3)));
                if (is_string($r)) {
                    $errors[] = $r;
                } else {
                    $r['_main'] = false;
                    $kept[] = $r;
                }
            }
        }
        usort($kept, fn($a, $b) => (int)$b['_main'] <=> (int)$a['_main']);
        $e['photos'] = array_map(function ($p) { unset($p['_main']); return $p; }, $kept);
        $e['updated'] = date('c');

        if ($isNew) {
            $list[] = $e;
        } else {
            $list[$idx] = $e;
        }
        save_entries($list);
        flash($errors ? 'Uloženo, ale: ' . implode(' ', $errors) : 'Uloženo.', $errors ? 'err' : 'ok');
        header('Location: edit.php?id=' . rawurlencode($e['id']));
        exit;
    }
}

$allPlaces = array_keys(places_count($list));
sort($allPlaces, SORT_LOCALE_STRING);
admin_head($isNew ? 'Nový záznam' : $e['title']);
?>
<div class="toolbar">
  <h1><?= $isNew ? 'Nový záznam' : 'Upravit záznam' ?></h1>
  <div class="btns">
    <?php if (!$isNew && !empty($e['published'])): ?><a class="btn btn-ghost btn-small" href="../<?= e(entry_url($e)) ?>" target="_blank" rel="noopener">Zobrazit na webu</a><?php endif; ?>
    <a class="btn btn-ghost btn-small" href="index.php">Zpět na seznam</a>
  </div>
</div>
<?php if ($errors): ?><div class="flash flash-err" role="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-grid box" id="zaznam">
  <?= csrf_field() ?>
  <label class="field"><span>Název</span><input type="text" name="title" value="<?= e($e['title']) ?>" required maxlength="140"></label>
  <div class="two">
    <label class="field"><span>Datum</span><input type="date" name="date" value="<?= e($e['date']) ?>" required></label>
    <label class="field"><span>Druh</span>
      <select name="type">
        <?php foreach (TYPES as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $e['type'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </div>

  <fieldset class="field">
    <legend>Místa</legend>
    <div class="checks">
      <?php foreach ($allPlaces as $p): ?>
      <label class="check"><input type="checkbox" name="places[]" value="<?= e($p) ?>"<?= in_array($p, $e['places'], true) ? ' checked' : '' ?>> <?= e($p) ?></label>
      <?php endforeach; ?>
    </div>
    <label class="field" style="margin-top:10px"><span>Nové místo</span><input type="text" name="new_places" placeholder="např. Kokšín, Brdy"><small>Víc míst oddělte čárkou. Hrad nad Starým Plzencem pište „Radyně“, vesnici u Toužimi „Radyně u Toužimi“.</small></label>
  </fieldset>

  <label class="field"><span>Text</span>
    <textarea name="text" class="big"><?= e($e['text']) ?></textarea>
  </label>
  <details class="help">
    <summary>Jak formátovat text</summary>
    <p>Prázdný řádek = nový odstavec. <code>## Nadpis</code> = mezititulek. Řádek začínající <code>- </code> = odrážka. <code>**tučně**</code> = tučné písmo. Webové adresy se samy změní na odkazy.</p>
  </details>

  <fieldset class="field">
    <legend>Fotky (nejvýš <?= MAX_PHOTOS ?>)</legend>
    <?php if ($e['photos']): ?>
    <div class="photos">
      <?php foreach ($e['photos'] as $i => $p): ?>
      <div class="photo">
        <img src="../<?= e(photo_url($e, $p, true)) ?>" alt="Fotka <?= $i + 1 ?>" loading="lazy">
        <label class="field"><span>Popisek</span><input type="text" name="caption[<?= $i ?>]" value="<?= e($p['caption'] ?? '') ?>" placeholder="co je na fotce"></label>
        <label class="check"><input type="radio" name="main" value="<?= $i ?>"<?= $i === 0 ? ' checked' : '' ?>> Hlavní fotka</label>
        <label class="check"><input type="checkbox" name="del_photo[<?= $i ?>]" value="1"> Smazat fotku</label>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (count($e['photos']) < MAX_PHOTOS): ?>
    <label class="field" style="margin-top:12px"><span>Přidat fotky</span>
      <input type="file" name="upload[]" accept="image/jpeg,image/png,image/webp" multiple data-max="<?= MAX_PHOTOS - count($e['photos']) ?>">
      <small>Můžete přidat ještě <?= MAX_PHOTOS - count($e['photos']) ?>. Velké fotky z mobilu se před odesláním samy zmenší.</small>
    </label>
    <?php else: ?>
    <p class="hint">Záznam už má <?= MAX_PHOTOS ?> fotky. Pro novou nejdřív některou smažte.</p>
    <?php endif; ?>
  </fieldset>

  <label class="check"><input type="checkbox" name="published" value="1"<?= !empty($e['published']) ? ' checked' : '' ?>> Zveřejnit na webu</label>

  <div class="actions-bar">
    <button class="btn" type="submit">Uložit</button>
    <span class="hint" id="stav" role="status"></span>
  </div>
</form>

<?php if (!$isNew): ?>
<form method="post" class="box" data-confirm="Opravdu smazat záznam „<?= e($e['title']) ?>“ i s fotkami? Nejde to vrátit.">
  <?= csrf_field() ?>
  <input type="hidden" name="akce" value="smazat">
  <h2>Smazat záznam</h2>
  <p class="hint">Pokud ho chcete jen dočasně schovat, odškrtněte místo toho „Zveřejnit na webu“.</p>
  <button class="btn btn-danger" type="submit">Smazat záznam</button>
</form>
<?php endif; ?>
<?php admin_foot(['admin.js']);
