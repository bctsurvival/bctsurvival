<?php
declare(strict_types=1);
require __DIR__ . '/../inc/admin.php';
require_login();

$co = $_GET['stahnout'] ?? '';

if ($co === 'texty') {
    $set = settings();
    unset($set['password_hash']);
    $json = json_encode(['vytvoreno' => date('c'), 'nastaveni' => $set, 'zaznamy' => data_read('entries', [])],
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="bct-survival-texty-' . date('Y-m-d') . '.json"');
    echo $json;
    exit;
}

if ($co === 'vse' && class_exists('ZipArchive')) {
    @set_time_limit(300);
    $tmp = tempnam(sys_get_temp_dir(), 'bct');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    foreach (['entries', 'settings'] as $n) {
        if (is_file(DATA_DIR . "/$n.json.php")) {
            $zip->addFile(DATA_DIR . "/$n.json.php", "data/$n.json.php");
        }
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FOTO_DIR, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $rel = 'foto/' . substr($f->getPathname(), strlen(FOTO_DIR) + 1);
        $zip->addFile($f->getPathname(), $rel);
        $zip->setCompressionName($rel, ZipArchive::CM_STORE); // fotky už jsou zmenšené
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="bct-survival-zaloha-' . date('Y-m-d') . '.zip"');
    readfile($tmp);
    unlink($tmp);
    exit;
}

$size = 0;
$count = 0;
if (is_dir(FOTO_DIR)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(FOTO_DIR, FilesystemIterator::SKIP_DOTS)) as $f) {
        $size += $f->getSize();
        $count++;
    }
}
admin_head('Záloha');
?>
<h1>Záloha</h1>
<p>Hosting zdarma zálohy nedělá. Stáhněte si zálohu do počítače aspoň jednou za měsíc a po větších změnách.</p>
<div class="box">
  <h2>Jen texty</h2>
  <p>Všechny záznamy a nastavení v jednom malém souboru (bez fotek a bez hesla).</p>
  <p><a class="btn" href="zaloha.php?stahnout=texty">Stáhnout texty</a></p>
</div>
<div class="box">
  <h2>Celý web s fotkami</h2>
  <p>Texty i všech <?= $count ?> souborů fotek, celkem asi <?= number_format($size / 1048576, 0, ',', ' ') ?> MB. Stahování chvíli trvá.</p>
  <?php if (class_exists('ZipArchive')): ?>
  <p><a class="btn btn-ghost" href="zaloha.php?stahnout=vse">Stáhnout vše (ZIP)</a></p>
  <?php else: ?>
  <p class="hint">Tento hosting neumí vytvářet ZIP. Celou zálohu stáhne Michal přes FTP (složky data a foto).</p>
  <?php endif; ?>
</div>
<?php admin_foot();
