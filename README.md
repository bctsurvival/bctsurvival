# BCT Survival

Web s deníkem potulek, pochodů a recenzí výbavy. Píše Mnich.

- PHP 8 (rozšíření GD; ZIP a EXIF jsou volitelné), bez databáze. Data jsou v `data/*.json.php`, fotky v `foto/<id záznamu>/`.
- Administrace je na adrese `/admin/`.

## Nahrání na hosting

1. Přes FTP nahrajte celý obsah repozitáře do kořene webu (`www`), včetně souborů `.htaccess`.
2. Složky `data/` a `foto/` musí mít právo zápisu (obvykle 755).
3. Hned po nahrání otevřete `/admin/` a nastavte heslo. Dokud není nastavené, může ho nastavit kdokoli.
4. V administraci otevřete Nastavení a vyplňte Adresu webu. Uložení vytvoří `sitemap.xml`, `llms.txt` a `robots.txt`.

## Zapomenuté heslo

Přes FTP v souboru `data/settings.json.php` smažte hodnotu `password_hash` (nechte `""`). Pak na `/admin/` nastavte nové heslo.

## Zálohy

Hosting zdarma zálohy nedělá. Zálohu stáhnete v administraci (Záloha), nebo přes FTP složky `data/` a `foto/`.
