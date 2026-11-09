"""Builds the DeenQ AI brand: vector SVG masters plus PNG renders through headless Edge.

The Q is drawn like a qalam (reed pen) stroke, a hairline at the upper right and a full stroke at
the lower left, with a slim swash tail and the Arabic qaf's two gold rhombus dots above it. The
wordmark sets "Deen" and "AI" in Marcellus, a flared inscriptional face whose pen-like strokes sit
well beside Arabic calligraphy, around that Q. Colours come only from the app palette in
lib/app/theme/app_theme.dart.

Usage: python build_brand.py <mobile folder> <scratch folder>   (both absolute paths)
"""
import math
import os
import subprocess
import sys

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

MOBILE = sys.argv[1]
WORK = sys.argv[2]
EDGE = r'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe'
FONT = os.path.join(MOBILE, 'branding', 'fonts', 'Marcellus-Regular.ttf')

EMERALD = '#075E45'
GREEN = '#0B7555'
GOLD = '#C6A45B'
CREAM = '#F8F6EF'
DARK_BG = '#101C19'
MINT = '#6FD3AE'
MINT_SOFT = '#BDEFD9'

# ---- The Q, on a 512 grid with the bowl centred at (256, 256) ----

R_OUT = 168
# The symbol's bowl is heavier so it holds up at icon sizes.
HEAVY = (128, (20, -20))
# The wordmark's bowl is lighter, so its full stroke matches the typeface's stems.
LIGHT = (140, (14, -14))

TAIL_D = (
    'M 326 388 '
    'C 360 440 410 472 466 470 '
    'C 489 469 503 458 512 438 '
    'C 499 452 483 459 463 459 '
    'C 420 459 388 432 358 392 Z'
)
BOOK_D = (
    'M 270 318 C 250 302 222 298 200 304 L 200 246 C 222 240 250 244 270 260 Z '
    'M 282 318 C 302 302 330 298 352 304 L 352 246 C 330 240 302 244 282 260 Z'
)
SYMBOL_BBOX = (88, 12, 514, 482)
LETTER_BBOX = (88, 88, 514, 482)
DOTS_TOP = 12


def ring_d(weight):
    r_in, (dx, dy) = weight
    cx = cy = 256
    icx, icy = cx + dx, cy + dy
    return (
        f'M {cx - R_OUT} {cy} A {R_OUT} {R_OUT} 0 1 0 {cx + R_OUT} {cy} A {R_OUT} {R_OUT} 0 1 0 {cx - R_OUT} {cy} Z '
        f'M {icx - r_in} {icy} A {r_in} {r_in} 0 1 1 {icx + r_in} {icy} A {r_in} {r_in} 0 1 1 {icx - r_in} {icy} Z'
    )


def rhombus_d(x, y, w, h, angle=-16):
    a = math.radians(angle)
    pts = [(0, -h / 2), (w / 2, 0), (0, h / 2), (-w / 2, 0)]
    rot = [(x + px * math.cos(a) - py * math.sin(a), y + px * math.sin(a) + py * math.cos(a)) for px, py in pts]
    return 'M ' + ' L '.join(f'{px:.1f} {py:.1f}' for px, py in rot) + ' Z'


DOTS_D = rhombus_d(230, 42, 36, 48) + ' ' + rhombus_d(282, 36, 36, 48)


def gradient(gid, c1, c2):
    # One gradient across the whole letter, so the bowl and tail meet without a colour step.
    return (f'<defs><linearGradient id="{gid}" gradientUnits="userSpaceOnUse" x1="90" y1="80" x2="470" y2="470">'
            f'<stop offset="0" stop-color="{c1}"/><stop offset="1" stop-color="{c2}"/></linearGradient></defs>')


def symbol(fill, accent, book, grad=None):
    """The standalone mark: heavy Q, gold dots and an open book in the bowl."""
    defs = gradient(*grad) if grad else ''
    return (f'{defs}<path d="{ring_d(HEAVY)}" fill="{fill}" fill-rule="evenodd"/><path d="{TAIL_D}" fill="{fill}"/>'
            f'<path d="{BOOK_D}" fill="{book}"/><path d="{DOTS_D}" fill="{accent}"/>')


def letter_q(fill, accent):
    return (f'<path d="{ring_d(LIGHT)}" fill="{fill}" fill-rule="evenodd"/><path d="{TAIL_D}" fill="{fill}"/>'
            f'<path d="{DOTS_D}" fill="{accent}"/>')


def centred(bbox, cx, cy, scale):
    """Transform that puts the centre of [bbox] at (cx, cy)."""
    bcx, bcy = (bbox[0] + bbox[2]) / 2, (bbox[1] + bbox[3]) / 2
    return f'translate({cx:.1f} {cy:.1f}) scale({scale:.4f}) translate({-bcx:.1f} {-bcy:.1f})'


FIT = 0.92 * 512 / max(SYMBOL_BBOX[2] - SYMBOL_BBOX[0], SYMBOL_BBOX[3] - SYMBOL_BBOX[1])

# ---- Wordmark ----


def glyph_run(text, size, tracking):
    font = TTFont(FONT)
    gs = font.getGlyphSet()
    cmap = font.getBestCmap()
    scale = size / font['head'].unitsPerEm
    cap = (getattr(font['OS/2'], 'sCapHeight', 0) or 0.7 * font['head'].unitsPerEm) * scale
    x = 0.0
    out = []
    for ch in text:
        pen = SVGPathPen(gs)
        gs[cmap[ord(ch)]].draw(TransformPen(pen, (scale, 0, 0, -scale, x, 0)))
        out.append(f'<path d="{pen.getCommands()}"/>')
        x += gs[cmap[ord(ch)]].width * scale + tracking * size
    return ''.join(out), x - tracking * size, cap


def wordmark(size, main, ai_colour, q_fill, q_accent, tracking=0.05):
    """'Deen' + calligraphic Q + 'AI'. Returns (svg, width, height above baseline, depth below)."""
    deen, w_deen, cap = glyph_run('Deen', size, tracking)
    ai, w_ai, _ = glyph_run('AI', size, tracking)
    k = cap * 1.06 / (2 * R_OUT)  # the bowl matches the capitals, with a small optical overshoot
    q_left = w_deen + size * (0.05 + tracking)
    q_tx = q_left - (256 - R_OUT) * k
    q_ty = -cap / 2 - 256 * k
    ai_x = q_left + (LETTER_BBOX[2] - (256 - R_OUT)) * k + size * 0.18
    svg = (f'<g fill="{main}">{deen}</g>'
           f'<g transform="translate({q_tx:.2f} {q_ty:.2f}) scale({k:.4f})">{letter_q(q_fill, q_accent)}</g>'
           f'<g transform="translate({ai_x:.2f} 0)" fill="{ai_colour}">{ai}</g>')
    return svg, ai_x + w_ai, -(q_ty + DOTS_TOP * k), q_ty + LETTER_BBOX[3] * k


# ---- Compositions ----


def doc(w, h, body, bg=None, defs=''):
    back = f'<rect width="{w}" height="{h}" fill="{bg}"/>' if bg else ''
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w} {h}" width="{w}" height="{h}">{defs}{back}{body}</svg>\n'


def logo_horizontal(main, ai_colour, q_grad, bg=None):
    """Primary logo: the wordmark alone, centred on a 1600 x 600 canvas."""
    g, width, top, depth = wordmark(220, main, ai_colour, f'url(#{q_grad[0]})', GOLD)
    scale = min(1.0, 1400 / width)
    x = (1600 - width * scale) / 2
    y = 300 + (top - depth) * scale / 2
    return doc(1600, 600, f'<g transform="translate({x:.1f} {y:.1f}) scale({scale:.4f})">{g}</g>', bg, gradient(*q_grad))


def logo_stacked(main, ai_colour, q_grad, sym_grad, bg=None):
    """The symbol above the wordmark, on a 1200 x 1000 canvas."""
    g, width, top, depth = wordmark(150, main, ai_colour, f'url(#{q_grad[0]})', GOLD)
    sym = symbol(f'url(#{sym_grad[0]})', GOLD, GOLD)
    body = (f'<g transform="{centred(SYMBOL_BBOX, 600, 340, 1.0)}">{sym}</g>'
            f'<g transform="translate({(1200 - width) / 2:.1f} {840 - depth:.1f})">{g}</g>')
    return doc(1200, 1000, body, bg, gradient(*q_grad) + gradient(*sym_grad))


def square(inner, scale, bg=None):
    return doc(512, 512, f'<g transform="{centred(SYMBOL_BBOX, 256, 256, scale * FIT)}">{inner}</g>', bg)


# ---- Rendering ----


def render(svg_text, out_png, w, h):
    """Small sizes are rendered large and scaled down: headless Edge lays out narrow windows wider
    than the screenshot, which would crop and shift the artwork."""
    if min(w, h) < 1000:
        from PIL import Image
        factor = 1000 / min(w, h)
        big = out_png + '.large.png'
        render(svg_text, big, round(w * factor), round(h * factor))
        Image.open(big).convert('RGBA').resize((w, h), Image.LANCZOS).save(out_png)
        os.remove(big)
        return
    html = os.path.join(WORK, 'render.html')
    with open(html, 'w', encoding='utf-8') as f:
        f.write('<!doctype html><html><head><style>html,body{margin:0;background:transparent}svg{display:block;width:100vw;height:100vh}</style></head><body>' + svg_text + '</body></html>')
    subprocess.run([
        EDGE, '--headless=new', '--disable-gpu', '--hide-scrollbars', '--default-background-color=00000000',
        f'--user-data-dir={os.path.join(WORK, "edge-profile")}', f'--window-size={w},{h}',
        f'--screenshot={out_png}', 'file:///' + html.replace('\\', '/'),
    ], check=True, capture_output=True, timeout=90)


def main():
    brand = os.path.join(MOBILE, 'branding')
    images = os.path.join(MOBILE, 'assets', 'images')
    light_q = ('ql', EMERALD, GREEN)
    dark_q = ('qd', MINT, MINT_SOFT)

    sym_light = symbol('url(#sl)', GOLD, GOLD, ('sl', EMERALD, GREEN))
    sym_dark = symbol('url(#sd)', GOLD, GOLD, ('sd', MINT, MINT_SOFT))
    sym_on_brand = symbol(CREAM, GOLD, GOLD)

    masters = {
        'deenq_logo_light.svg': logo_horizontal(EMERALD, GREEN, light_q),
        'deenq_logo_dark.svg': logo_horizontal(CREAM, MINT, dark_q),
        'deenq_logo_stacked_light.svg': logo_stacked(EMERALD, GREEN, light_q, ('sl2', EMERALD, GREEN)),
        'deenq_logo_stacked_dark.svg': logo_stacked(CREAM, MINT, dark_q, ('sd2', MINT, MINT_SOFT)),
        'deenq_symbol_light.svg': square(sym_light, 1.0),
        'deenq_symbol_dark.svg': square(sym_dark, 1.0),
        'deenq_app_icon.svg': square(sym_on_brand, 0.80, bg=EMERALD),
    }
    for name, svg in masters.items():
        with open(os.path.join(brand, name), 'w', encoding='utf-8') as f:
            f.write(svg)

    # Compact mark for the website header tile, drawn in the tile's text colour.
    with open(os.path.join(WORK, 'web_mark.svg'), 'w', encoding='utf-8') as f:
        f.write(f'<svg viewBox="0 0 512 512" fill="none"><g transform="{centred(SYMBOL_BBOX, 256, 256, FIT)}">'
                f'{symbol("currentColor", GOLD, GOLD)}</g></svg>')

    renders = [
        (masters['deenq_logo_light.svg'], os.path.join(brand, 'deenq_logo_light.png'), 1600, 600),
        (masters['deenq_logo_dark.svg'], os.path.join(brand, 'deenq_logo_dark.png'), 1600, 600),
        (masters['deenq_logo_stacked_light.svg'], os.path.join(brand, 'deenq_logo_stacked_light.png'), 1200, 1000),
        (masters['deenq_logo_stacked_dark.svg'], os.path.join(brand, 'deenq_logo_stacked_dark.png'), 1200, 1000),
        (logo_horizontal(EMERALD, GREEN, light_q, bg=CREAM), os.path.join(brand, 'preview_on_light.png'), 1600, 600),
        (logo_horizontal(CREAM, MINT, dark_q, bg=DARK_BG), os.path.join(brand, 'preview_on_dark.png'), 1600, 600),
        (masters['deenq_symbol_light.svg'], os.path.join(brand, 'deenq_symbol_light.png'), 1024, 1024),
        (masters['deenq_symbol_dark.svg'], os.path.join(brand, 'deenq_symbol_dark.png'), 1024, 1024),
        # Files the app and the icon and splash generators read.
        (masters['deenq_app_icon.svg'], os.path.join(images, 'app_icon.png'), 1024, 1024),
        # Adaptive icon foreground: inside the 66% safe zone the launcher may mask to.
        (square(sym_on_brand, 0.56), os.path.join(images, 'app_icon_foreground.png'), 1024, 1024),
        # Android 12 crops splash icons to a circle two thirds of the image wide.
        (square(sym_light, 0.56), os.path.join(images, 'splash_logo.png'), 768, 768),
        (square(sym_dark, 0.56), os.path.join(images, 'splash_logo_dark.png'), 768, 768),
        (square(sym_light, 1.0), os.path.join(images, 'logo_mark.png'), 288, 288),
        (square(sym_dark, 1.0), os.path.join(images, 'logo_mark_dark.png'), 288, 288),
        # For the onboarding welcome card, which sits on the emerald gradient.
        (square(sym_on_brand, 1.0), os.path.join(images, 'logo_mark_on_brand.png'), 288, 288),
    ]
    for svg, out, w, h in renders:
        render(svg, out, w, h)
        print('rendered', os.path.relpath(out, MOBILE), w, h)


if __name__ == '__main__':
    main()
