<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin.php';
require_login();

$s = settings();
$errors = [];
$fields = ['nickname', 'tagline', 'intro', 'about', 'email', 'facebook', 'site_url', 'since'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['akce'] ?? '') === 'heslo') {
        $p1 = (string)($_POST['pw1'] ?? '');
        if (!password_verify((string)($_POST['pw'] ?? ''), $s['password_hash'])) {
            $errors[] = 'Současné heslo nesedí.';
        } elseif (mb_strlen($p1) < 10) {
            $errors[] = 'Nové heslo musí mít aspoň 10 znaků.';
        } elseif ($p1 !== (string)($_POST['pw2'] ?? '')) {
            $errors[] = 'Nová hesla se neshodují.';
        } else {
            $hash = password_hash($p1, PASSWORD_DEFAULT);
            save_settings(['password_hash' => $hash]);
            $_SESSION['pw'] = substr($hash, -12);
            flash('Heslo je změněné.');
            header('Location: nastaveni.php');
            exit;
        }
    } else {
        $new = [];
        foreach ($fields as $f) {
            $new[$f] = str_replace("\r\n", "\n", trim((string)($_POST[$f] ?? '')));
        }
        // Text „O mně“ shodný s výchozím neukládáme, ať se dál může aktualizovat.
        if ($new['about'] === trim(default_about())) {
            $new['about'] = '';
        }
        $new['site_url'] = rtrim($new['site_url'], '/');
        if ($new['nickname'] === '') {
            $errors[] = 'Vyplňte přezdívku.';
        }
        if (!filter_var($new['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'E-mail není platný.';
        }
        foreach (['facebook', 'site_url'] as $u) {
            if ($new[$u] !== '' && !preg_match('~^https?://~', $new[$u])) {
                $errors[] = 'Adresa musí začínat https://.';
            }
        }
        if (!preg_match('/^\d{4}$/', $new['since'])) {
            $errors[] = 'Rok začátku musí být čtyřmístné číslo.';
        }
        if (!$errors) {
            save_settings($new);
            regen_static();
            flash('Nastavení uloženo.');
            header('Location: nastaveni.php');
            exit;
        }
        $s = array_merge($s, $new);
    }
}

$about = trim($s['about']) !== '' ? $s['about'] : default_about();
admin_head('Nastavení');
?>
<h1>Nastavení</h1>
<?php if ($errors): ?><p class="flash flash-err" role="alert"><?= e(implode(' ', $errors)) ?></p><?php endif; ?>

<form method="post" class="form-grid box">
  <?= csrf_field() ?>
  <h2>Texty na webu</h2>
  <div class="two">
    <label class="field"><span>Přezdívka</span><input type="text" name="nickname" value="<?= e($s['nickname']) ?>" required></label>
    <label class="field"><span>Na Facebooku od roku</span><input type="text" name="since" value="<?= e($s['since']) ?>" inputmode="numeric" pattern="\d{4}" required></label>
  </div>
  <label class="field"><span>Motto</span><input type="text" name="tagline" value="<?= e($s['tagline']) ?>"><small>Krátká věta pod názvem webu.</small></label>
  <label class="field"><span>Úvodní text</span><textarea name="intro"><?= e($s['intro']) ?></textarea><small>Zobrazí se na úvodní stránce a ve výsledcích vyhledávání.</small></label>
  <label class="field"><span>O mně</span><textarea name="about" class="big"><?= e($about) ?></textarea><small>Formátování jako u záznamů: <code>## Nadpis</code>, odrážky <code>- </code>, <code>**tučně**</code>.</small></label>
  <h2>Kontakty</h2>
  <div class="two">
    <label class="field"><span>E-mail</span><input type="email" name="email" value="<?= e($s['email']) ?>" required></label>
    <label class="field"><span>Facebook</span><input type="url" name="facebook" value="<?= e($s['facebook']) ?>"></label>
  </div>
  <label class="field"><span>Adresa webu</span><input type="url" name="site_url" value="<?= e($s['site_url']) ?>" placeholder="https://adresa-webu.cz"><small>Celá adresa, na které web běží. Používá se pro Google a sdílení na Facebooku. Prázdné = zjistí se sama.</small></label>
  <p><button class="btn" type="submit">Uložit nastavení</button></p>
</form>

<form method="post" class="form-grid box narrow">
  <?= csrf_field() ?>
  <input type="hidden" name="akce" value="heslo">
  <h2>Změna hesla</h2>
  <label class="field"><span>Současné heslo</span><input type="password" name="pw" autocomplete="current-password" required></label>
  <label class="field"><span>Nové heslo</span><input type="password" name="pw1" autocomplete="new-password" minlength="10" required></label>
  <label class="field"><span>Nové heslo znovu</span><input type="password" name="pw2" autocomplete="new-password" minlength="10" required></label>
  <p><button class="btn" type="submit">Změnit heslo</button></p>
</form>
<?php admin_foot();
