"""Sestaví statický web do složky _site.

1. Z content/ připraví data pro šablony (data/*.json.php) a zmenší fotky.
2. Šablony PHP vykreslí přes vestavěný server PHP.
3. Uloží hotové HTML a odkazy přepíše na .html.

Spuštění:  python3 build/build.py
Proměnné:  SITE_URL  adresa webu (výchozí https://bctsurvival.github.io/bctsurvival)
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
SITE_URL = os.environ.get('SITE_URL', 'https://bctsurvival.github.io/bctsurvival').rstrip('/')
PREVIEW = os.environ.get('PREVIEW') == '1'
BIG, SMALL, PLACE = 1300, 480, 480
MAX_PHOTOS = 3
GUARD = '<?php exit; ?>\n'


def slug(s):
    s = s.lower()
    s = s.translate(str.maketrans('áčďéěíňóřšťúůýž', 'acdeeinorstuuyz'))
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')[:60] or 'foto'


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
    for f in ['index.php', 'denik.php', 'mista.php', 'o-mne.php', 'zaznam.php']:
        shutil.copy(ROOT / f, BUILD / f)
    for d in ['inc', 'css', 'js', 'img']:
        shutil.copytree(ROOT / d, BUILD / d)

    settings = json.loads((ROOT / 'content/nastaveni.json').read_text(encoding='utf-8'))
    settings['site_url'] = SITE_URL
    write_data('settings', settings)

    entries, problems = [], []
    for f in sorted((ROOT / 'content/zapisy').glob('*.json')):
        e = json.loads(f.read_text(encoding='utf-8'))
        eid = f.stem
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
            'places': [x for x in (e.get('places') or []) if x],
            'text': (e.get('text') or '').strip(),
            'photos': photos,
            'fb_count': int(e.get('fb_count') or 0),
            'published': e.get('published', True) is not False,
            'updated': last_change(f) or str(e.get('date', ''))[:10],
        })
    write_data('entries', entries)

    mista = json.loads((ROOT / 'content/mista.json').read_text(encoding='utf-8')).get('mista', [])
    for m in mista:
        rel = (m.get('image') or '').lstrip('/')
        if m.get('featured') and rel and (ROOT / rel).is_file():
            out = f'img/mista-gen/{slug(m["name"])}.jpg'
            save(open_photo(ROOT / rel), BUILD / out, PLACE, 78)
            m['img'] = out
    write_data('mista', {'mista': mista})

    for msg in problems:
        print('POZOR:', msg)
    return entries


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
            html = html.replace('denik.php', 'denik.html').replace('o-mne.php', 'o-mne.html').replace('mista.php', 'mista.html')
            if PREVIEW:
                html = html.replace('<head>', '<head>\n<meta name="robots" content="noindex, nofollow">', 1)
                html = re.sub(r'(<main[^>]*>)', r'\1\n<p class="preview-bar">Testovací verze webu</p>', html, count=1)
            return html

        shutil.rmtree(SITE, ignore_errors=True)
        SITE.mkdir()
        pages = {'index.html': '/', 'denik.html': '/denik.php', 'mista.html': '/mista.php', 'o-mne.html': '/o-mne.php',
                 '404.html': '/zaznam.php?id=neexistuje'}
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
