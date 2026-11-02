# BCT Survival

Deník potulek, pochodů a recenzí výbavy. Píše Mnich.

## Jak web funguje

- Obsah je v `content/`: zápisy (`content/zapisy/*.json`), texty a kontakty (`content/nastaveni.json`) a místa (`content/mista.json`). Fotky jsou ve `foto/`.
- Obsah se upravuje v administraci na adrese `/admin/` (Sveltia CMS). Přihlášení: „Sign In Using Access Token“ a osobní token GitHubu s právem zápisu do tohoto repozitáře.
- Po každém uložení GitHub web znovu sestaví (`.github/workflows/web.yml`): zmenší fotky, vykreslí stránky ze šablon PHP do statického HTML a nasadí je na GitHub Pages. Trvá to 1–2 minuty.

## Sestavení u sebe

Potřeba je PHP 8 a Python 3 s balíčky Pillow a pillow-heif.

```
python3 build/build.py
```

Hotový web je ve složce `_site`.

## Spuštění na doméně

1. Do kořene repozitáře přidat soubor `CNAME` s doménou.
2. V `.github/workflows/web.yml` smazat řádek `PREVIEW: "1"` a nastavit `SITE_URL`.
3. V `admin/config.yml` změnit `site_url`.
