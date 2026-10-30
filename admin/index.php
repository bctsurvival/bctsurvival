<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin.php';

$s = settings();
$err = '';

// První spuštění: ještě není nastavené heslo.
if ($s['password_hash'] === '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $p1 = (string)($_POST['pw1'] ?? '');
        $p2 = (string)($_POST['pw2'] ?? '');
        if (mb_strlen($p1) < 10) {
            $err = 'Heslo musí mít aspoň 10 znaků.';
        } elseif ($p1 !== $p2) {
            $err = 'Hesla se neshodují.';
        } else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            save_settings(['password_hash' => $hash]);
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['pw'] = substr($hash, -12);
            regen_static();
            flash('Heslo je nastavené. Vítejte v administraci.');
            header('Location: index.php');
            exit;
        }
    }
    admin_head('První spuštění');
    ?>
    <div class="box narrow">
      <h1>První spuštění</h1>
      <p>Administrace zatím nemá heslo. Zvolte si ho teď, ať ji nikdo jiný nezabere. Heslo si uložte, bez něj se do administrace nedostanete.</p>
      <?php if ($err): ?><p class="flash flash-err" role="alert"><?= e($err) ?></p><?php endif; ?>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label class="field"><span>Nové heslo</span><input type="password" name="pw1" autocomplete="new-password" minlength="10" required><small>Aspoň 10 znaků.</small></label>
        <label class="field"><span>Heslo znovu</span><input type="password" name="pw2" autocomplete="new-password" minlength="10" required></label>
        <p><button class="btn" type="submit">Uložit heslo</button></p>
      </form>
    </div>
    <?php
    admin_foot();
    exit;
}

// Přihlášení
if (!is_logged_in()) {
    $wait = login_locked();
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$wait) {
        csrf_check();
        if (password_verify((string)($_POST['pw'] ?? ''), $s['password_hash'])) {
            login_fail(true);
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['pw'] = substr($s['password_hash'], -12);
            header('Location: index.php');
            exit;
        }
        login_fail();
        $wait = login_locked();
        $err = 'Špatné heslo.';
    }
    if ($wait) {
        $err = 'Příliš mnoho pokusů. Zkuste to znovu za ' . ceil($wait / 60) . ' min.';
    }
    admin_head('Přihlášení');
    ?>
    <div class="box narrow">
      <h1>Přihlášení</h1>
      <?php if ($err): ?><p class="flash flash-err" role="alert"><?= e($err) ?></p><?php endif; ?>
      <form method="post" class="form-grid">
        <?= csrf_field() ?>
        <label class="field"><span>Heslo</span><input type="password" name="pw" autocomplete="current-password" required autofocus></label>
        <p><button class="btn" type="submit">Přihlásit</button></p>
      </form>
      <p class="hint">Zapomenuté heslo obnoví Michal přes FTP.</p>
    </div>
    <?php
    admin_foot();
    exit;
}

// Seznam záznamů
$all = entries(false);
$hidden = count(array_filter($all, fn($e) => empty($e['published'])));
admin_head('Záznamy');
?>
<div class="toolbar">
  <h1>Záznamy <span class="muted">(<?= count($all) ?>)</span></h1>
  <div class="btns">
    <a class="btn" href="edit.php">+ Nový záznam</a>
    <a class="btn btn-ghost" href="zaloha.php">Záloha</a>
  </div>
</div>
<?php if ($hidden): ?><p class="hint">Skrytých (nezveřejněných) záznamů: <?= $hidden ?>.</p><?php endif; ?>
<label class="field" style="margin-bottom:14px"><span>Hledat v seznamu</span><input type="search" id="hledat" placeholder="název, místo nebo rok"></label>
<ul class="list" id="seznam">
  <?php foreach ($all as $e): $p = $e['photos'][0] ?? null; ?>
  <li data-search="<?= e(mb_strtolower($e['title'] . ' ' . implode(' ', $e['places'] ?? []) . ' ' . $e['date'])) ?>">
    <?php if ($p): ?><img src="../<?= e(photo_url($e, $p, true)) ?>" alt="" loading="lazy" width="72" height="54"><?php else: ?><span class="noimg"></span><?php endif; ?>
    <div>
      <a href="edit.php?id=<?= e(rawurlencode($e['id'])) ?>"><?= e($e['title']) ?></a><?php if (empty($e['published'])): ?><span class="badge">skryto</span><?php endif; ?>
      <div class="meta"><?= e(date_cz($e['date'])) ?> · <?= e(type_label($e['type'])) ?><?= $e['places'] ? ' · ' . e(implode(', ', $e['places'])) : '' ?></div>
    </div>
  </li>
  <?php endforeach; ?>
</ul>
<?php admin_foot(['admin.js']);
