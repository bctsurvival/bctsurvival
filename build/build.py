"""Sestaví statický web do složky _site.

1. Z content/ připraví data pro šablony (data/*.json.php) a zmenší fotky.
2. Šablony PHP vykreslí přes vestavěný server PHP.
3. Uloží hotové HTML a odkazy přepíše na .html.

Spuštění:  python3 build/build.py
Proměnné:  SITE_URL  adresa webu (výchozí https://bctsurvival.cz)
           PREVIEW=1 testovací verze: pruh nahoře a zákaz indexování
"""
import hashlib
import json
import os
import re
import shutil
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

from PIL import Image, ImageOps

try:
    from pillow_heif import register_heif_opener  # fotky z iPhonu (HEIC)
    register_heif_opener()
except ImportError:
    pass

ROOT = Path(__file__).resolve().parent.parent
BUILD = ROOT / '_build'
SITE = ROOT / '_site'
SITE_URL = os.environ.get('SITE_URL', 'https://bctsurvival.cz').rstrip('/')
PREVIEW = os.environ.get('PREVIEW') == '1'
BIG, SMALL, PLACE = 1300, 480, 480
MAX_PHOTOS = 3
GUARD = '<?php exit; ?>\n'


def slug(s):
    s = s.lower()
    s = s.translate(str.maketrans('áčďéěíňóřšťúůýž', 'acdeeinorstuuyz'))
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')[:60] or 'foto'


def ascii_id(s):
    """Adresa zápisu bez diakritiky (soubory z administrace můžou mít v názvu háčky a čárky)."""
    s = s.lower().translate(str.maketrans('áäčďéěëíňóöřšťúůüýž', 'aacdeeeinoorstuuuyz'))
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-') or 'zapis'


def save(img, path, size, quality):
    """Zmenší fotku na delší stranu `size` a uloží JPG. Vrací (šířka, výška)."""
    im = img.copy()
    im.thumbnail((size, size), Image.LANCZOS)
    path.parent.mkdir(parents=True, exist_ok=True)
    im.save(path, 'JPEG', quality=quality, optimize=True, progressive=True)
    return im.size


def open_photo(src):
    im = ImageOps.exif_transpose(Image.open(src))
    if im.mode != 'RGB':
        bg = Image.new('RGB', im.size, 'white')
        bg.paste(im, mask=im.convert('RGBA').split()[3] if 'A' in im.mode else None)
        im = bg
    return im


def last_change(path):
    """Datum poslední změny souboru podle gitu (pro sitemap)."""
    r = subprocess.run(['git', 'log', '-1', '--format=%cs', '--', str(path)],
                       cwd=ROOT, capture_output=True, text=True)
    return r.stdout.strip()


def write_data(name, value):
    (BUILD / 'data').mkdir(parents=True, exist_ok=True)
    (BUILD / 'data' / f'{name}.json.php').write_text(
        GUARD + json.dumps(value, ensure_ascii=False, indent=1), encoding='utf-8')


def prepare():
    shutil.rmtree(BUILD, ignore_errors=True)
    BUILD.mkdir()
    for f in ['index.php', 'denik.php', 'mista.php', 'o-mne.php', 'zaznam.php', 'stranka.php']:
        shutil.copy(ROOT / f, BUILD / f)
    for d in ['inc', 'css', 'js', 'img']:
        shutil.copytree(ROOT / d, BUILD / d)

    settings = json.loads((ROOT / 'content/nastaveni.json').read_text(encoding='utf-8'))
    settings['site_url'] = SITE_URL
    write_data('settings', settings)

    entries, problems = [], []
    for f in sorted((ROOT / 'content/zapisy').glob('*.json')):
        e = json.loads(f.read_text(encoding='utf-8'))
        eid = ascii_id(f.stem)
        photos, used = [], set()
        for p in (e.get('photos') or [])[:MAX_PHOTOS]:
            rel = (p.get('image') or '').lstrip('/')
            src = ROOT / rel
            if not rel or not src.is_file():
                problems.append(f'{eid}: chybí fotka {rel}')
                continue
            name = slug(src.stem)
            while name in used:
                name += '-2'
            used.add(name)
            im = open_photo(src)
            w, h = save(im, BUILD / 'foto' / eid / f'{name}.jpg', BIG, 80)
            sw, sh = save(im, BUILD / 'foto' / eid / f'{name}-m.jpg', SMALL, 74)
            photos.append({'file': f'{name}.jpg', 'w': w, 'h': h, 'sw': sw, 'sh': sh,
                           'caption': (p.get('caption') or '').strip()})
        entries.append({
            'id': eid,
            'date': str(e.get('date', ''))[:10],
            'title': (e.get('title') or eid).strip(),
            'type': e.get('type') or 'potulka',
            'types': list(dict.fromkeys(t for t in [e.get('type') or 'potulka', e.get('type2') or ''] if t)),
            'places': [x for x in (e.get('places') or []) if x],
            'text': (e.get('text') or '').strip(),
            'photos': photos,
            'fb_count': int(e.get('fb_count') or 0),
            'fb_url': (e.get('fb_url') or '').strip(),
            'published': e.get('published', True) is not False,
            'updated': last_change(f) or str(e.get('date', ''))[:10],
        })
    write_data('entries', entries)

    mista = [json.loads(f.read_text(encoding='utf-8')) for f in sorted((ROOT / 'content/mista').glob('*.json'))]
    mista.sort(key=lambda m: (int(m.get('order') or 999), m.get('name', '')))
    for m in mista:
        rel = (m.get('image') or '').lstrip('/')
        if m.get('featured') and rel and (ROOT / rel).is_file():
            out = f'img/mista-gen/{slug(m["name"])}.jpg'
            save(open_photo(ROOT / rel), BUILD / out, PLACE, 78)
            m['img'] = out
    write_data('mista', {'mista': mista})

    # Loga spřátelených webů: zachovat průhlednost, uložit jako PNG
    pratele = []
    pf = ROOT / 'content/pratele.json'
    for i, p in enumerate(json.loads(pf.read_text(encoding='utf-8')).get('pratele', []) if pf.is_file() else []):
        rel = (p.get('logo') or '').lstrip('/')
        url = (p.get('url') or '').strip()
        if not rel or not (ROOT / rel).is_file() or not url:
            continue
        im = ImageOps.exif_transpose(Image.open(ROOT / rel))
        im = im.convert('RGBA') if 'A' in im.mode or im.mode == 'P' else im.convert('RGB')
        im.thumbnail((360, 120), Image.LANCZOS)
        out = f'img/pratele-gen/{i:02d}-{slug(p.get("name") or "web")}.png'
        (BUILD / out).parent.mkdir(parents=True, exist_ok=True)
        im.save(BUILD / out, optimize=True)
        pratele.append({'name': (p.get('name') or url).strip(), 'url': url, 'img': out,
                        'w': im.size[0], 'h': im.size[1]})
    write_data('pratele', pratele)

    # Úvodní stránka
    uf = ROOT / 'content/uvod.json'
    write_data('uvod', json.loads(uf.read_text(encoding='utf-8')) if uf.is_file() else {})

    # Vlastní stránky (content/stranky)
    pages = []
    for f in sorted((ROOT / 'content/stranky').glob('*.json')):
        p = json.loads(f.read_text(encoding='utf-8'))
        if p.get('published') is False:
            continue
        pid = slug(f.stem)
        if pid in RESERVED or pid.startswith('zaznam-'):
            pid += '-stranka'
        photos = []
        for i, ph in enumerate((p.get('photos') or [])[:12]):
            rel = (ph.get('image') or '').lstrip('/')
            if not rel or not (ROOT / rel).is_file():
                problems.append(f'stránka {f.stem}: chybí fotka {rel}')
                continue
            im = open_photo(ROOT / rel)
            name = f'{i + 1:02d}-{slug(Path(rel).stem)}'
            w, h = save(im, BUILD / 'foto' / f'stranka-{pid}' / f'{name}.jpg', BIG, 80)
            sw, sh = save(im, BUILD / 'foto' / f'stranka-{pid}' / f'{name}-m.jpg', SMALL, 74)
            photos.append({'file': f'{name}.jpg', 'w': w, 'h': h, 'sw': sw, 'sh': sh,
                           'caption': (ph.get('caption') or '').strip()})
        pages.append({'id': pid, 'title': (p.get('title') or pid).strip(),
                      'menu': bool(p.get('menu')), 'menu_label': (p.get('menu_label') or '').strip(),
                      'order': int(p.get('order') or 50), 'description': (p.get('description') or '').strip(),
                      'text': (p.get('text') or '').strip(), 'photos': photos,
                      'updated': last_change(f)})
    pages.sort(key=lambda x: (x['order'], x['title']))
    write_data('stranky', pages)

    write_theme()
    write_druhy()

    for msg in problems:
        print('POZOR:', msg)
    return entries


RESERVED = {'index', 'denik', 'mista', 'o-mne', 'zaznam', 'stranka', '404', 'admin', 'css', 'js', 'img', 'foto',
            'sitemap', 'robots', 'llms'}


def hex_rgb(h):
    h = (h or '').strip().lstrip('#')
    if len(h) == 3:
        h = ''.join(c * 2 for c in h)
    if not re.fullmatch(r'[0-9a-fA-F]{6}', h):
        return None
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def lum(c):
    ch = [v / 255 for v in c]
    ch = [v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4 for v in ch]
    return 0.2126 * ch[0] + 0.7152 * ch[1] + 0.0722 * ch[2]


def contrast(a, b):
    la, lb = sorted([lum(a), lum(b)], reverse=True)
    return (la + 0.05) / (lb + 0.05)


def to_hex(c):
    return '#%02x%02x%02x' % tuple(round(v) for v in c)


def readable(fg, bg, need=4.5):
    """Když barva textu na pozadí není čitelná, posune ji k černé nebo bílé, dokud nebude."""
    if contrast(fg, bg) >= need:
        return fg, False
    target = (0, 0, 0) if contrast((0, 0, 0), bg) >= contrast((255, 255, 255), bg) else (255, 255, 255)
    for t in range(1, 21):
        c = tuple(fg[i] + (target[i] - fg[i]) * t / 20 for i in range(3))
        if contrast(c, bg) >= need:
            return c, True
    return target, True


def best_ink(bg):
    return (17, 22, 15) if contrast((17, 22, 15), bg) >= contrast((255, 255, 255), bg) else (255, 255, 255)


def write_theme():
    """Barvy z administrace (content/vzhled.json) -> css/vzhled.css. Nečitelné kombinace opraví."""
    vf = ROOT / 'content/vzhled.json'
    v = json.loads(vf.read_text(encoding='utf-8')) if vf.is_file() else {}
    out = BUILD / 'css/vzhled.css'
    if out.exists():
        out.unlink()
    if not v.get('custom'):
        return
    bg = hex_rgb(v.get('bg')) or (245, 238, 226)
    surface = hex_rgb(v.get('surface')) or bg
    ink = hex_rgb(v.get('ink')) or (31, 39, 34)
    accent = hex_rgb(v.get('accent')) or (47, 93, 52)
    fire = hex_rgb(v.get('fire')) or (168, 71, 12)
    header = hex_rgb(v.get('header')) or (29, 35, 41)
    fixed = []
    ink, f1 = readable(ink, surface if lum(surface) < lum(bg) else bg)
    link, f2 = readable(accent, surface if contrast(accent, surface) < contrast(accent, bg) else bg)
    fire_t, f3 = readable(fire, bg)
    if f1: fixed.append('text')
    if f2: fixed.append('odkazy')
    if f3: fixed.append('zvýraznění')
    css = (':root, :root[data-theme] {\n'
           f'  --bg: {to_hex(bg)}; --surface: {to_hex(surface)}; --ink: {to_hex(ink)};\n'
           f'  --surface-2: color-mix(in srgb, {to_hex(surface)} 85%, {to_hex(ink)});\n'
           f'  --muted: color-mix(in srgb, {to_hex(ink)} 78%, {to_hex(bg)});\n'
           f'  --line: color-mix(in srgb, {to_hex(bg)} 75%, {to_hex(ink)});\n'
           f'  --accent: {to_hex(accent)}; --accent-ink: {to_hex(best_ink(accent))}; --link: {to_hex(link)};\n'
           f'  --fire: {to_hex(fire_t)}; --fire-ink: {to_hex(best_ink(fire_t))}; --header: {to_hex(header)}; --header-ink: {to_hex(best_ink(header))};\n'
           f'  color-scheme: {"light" if lum(bg) > 0.18 else "dark"};\n'
           '}\n')
    if v.get('dark') == 'auto':
        css = '@media (prefers-color-scheme: light) {\n' + css + '}\n'
    out.write_text('/* Barvy z administrace (vygenerováno) */\n' + css, encoding='utf-8')
    if fixed:
        print('POZOR: barvy upraveny kvůli čitelnosti:', ', '.join(fixed))


def write_druhy():
    """Druhy zápisů z administrace (content/druhy) -> data/druhy + css/druhy.css (barvy štítků)."""
    druhy = []
    for f in sorted((ROOT / 'content/druhy').glob('*.json')):
        d = json.loads(f.read_text(encoding='utf-8'))
        name = (d.get('name') or f.stem).strip()
        druhy.append({'key': f.stem, 'label': name, 'plural': (d.get('plural') or '').strip() or name,
                      'color': d.get('color') or '#2f5d34', 'order': int(d.get('order') or 100)})
    druhy.sort(key=lambda x: (x['order'], x['label']))
    write_data('druhy', druhy)
    css = '/* Barvy štítků druhů (vygenerováno z administrace) */\n'
    for d in druhy:
        c = hex_rgb(d['color']) or (47, 93, 52)
        c, _ = readable(c, (255, 255, 255))  # bílý text na štítku musí být čitelný
        k = re.sub(r'[^a-zA-Z0-9_-]', '', d['key'])
        css += f'.type-{k}, .tab-{k} {{ --tab: {to_hex(c)}; }}\n'
    (BUILD / 'css/druhy.css').write_text(css, encoding='utf-8')


def free_port():
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        return s.getsockname()[1]


def render(entries):
    port = free_port()
    local = f'http://127.0.0.1:{port}'
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(BUILD)],
                              stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:
                urllib.request.urlopen(local + '/')
                break
            except Exception:
                time.sleep(0.1)

        def get(path):
            try:
                return urllib.request.urlopen(local + path).read().decode('utf-8')
            except urllib.error.HTTPError as x:
                return x.read().decode('utf-8')

        def fix(html):
            html = html.replace(local, SITE_URL)
            html = re.sub(r'zaznam\.php\?id=([\w-]+)', r'zaznam-\1.html', html)
            html = re.sub(r'stranka\.php\?id=([\w-]+)', r'\1.html', html)
            html = html.replace('denik.php', 'denik.html').replace('o-mne.php', 'o-mne.html').replace('mista.php', 'mista.html')
            if PREVIEW:
                html = html.replace('<head>', '<head>\n<meta name="robots" content="noindex, nofollow">', 1)
                html = re.sub(r'(<main[^>]*>)', r'\1\n<p class="preview-bar">Testovací verze webu</p>', html, count=1)
            return html

        shutil.rmtree(SITE, ignore_errors=True)
        SITE.mkdir()
        pages = {'index.html': '/', 'denik.html': '/denik.php', 'mista.html': '/mista.php', 'o-mne.html': '/o-mne.php',
                 '404.html': '/zaznam.php?id=neexistuje'}
        for p in json.loads((BUILD / 'data/stranky.json.php').read_text(encoding='utf-8')[len(GUARD):]):
            pages[f'{p["id"]}.html'] = '/stranka.php?id=' + p['id']
        for e in entries:
            if e['published']:
                pages[f'zaznam-{e["id"]}.html'] = '/zaznam.php?id=' + e['id']
        for out, path in pages.items():
            html = fix(get(path))
            if 'Fatal error' in html or 'Warning:' in html:
                sys.exit(f'Chyba PHP na stránce {path}')
            (SITE / out).write_text(html, encoding='utf-8')
    finally:
        server.terminate()

    # sitemap.xml, llms.txt, robots.txt
    subprocess.run(['php', '-r', 'require "inc/app.php"; regen_static();'], cwd=BUILD, check=True)
    for f in ['sitemap.xml', 'llms.txt', 'robots.txt']:
        (SITE / f).write_text(fix((BUILD / f).read_text(encoding='utf-8')), encoding='utf-8')
    if PREVIEW:
        (SITE / 'robots.txt').write_text('User-agent: *\nDisallow: /\n')

    for d in ['css', 'js', 'img', 'foto']:
        shutil.copytree(BUILD / d, SITE / d)
    if (ROOT / 'admin').is_dir():
        shutil.copytree(ROOT / 'admin', SITE / 'admin')
    if (ROOT / 'CNAME').is_file():
        shutil.copy(ROOT / 'CNAME', SITE / 'CNAME')
    (SITE / '.nojekyll').write_text('')
    print(f'Hotovo: {len(pages)} stránek v {SITE}')


if __name__ == '__main__':
    render(prepare())
