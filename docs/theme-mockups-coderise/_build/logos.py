"""Detourage des logos Coderise fournis (JPG) vers des PNG a fond transparent.
Aucune retouche du dessin : on retrouve l'opacite de chaque pixel (anti-crenelage compris) et on
la reapplique a la couleur de charte exacte (cyan #10C9DE, blanc, bleu nuit #000F26)."""
import sys
from PIL import Image
import numpy as np

src, out = sys.argv[1], sys.argv[2]
CY = np.array([16, 201, 222], float)
NAVY = np.array([0, 15, 38], float)
WHITE = np.array([255, 255, 255], float)


def finish(alpha, rgb):
    alpha = np.clip(alpha, 0, 1)
    alpha[alpha < 0.04] = 0
    alpha[alpha > 0.97] = 1
    rgba = np.dstack([rgb, alpha * 255]).round().astype(np.uint8)
    return Image.fromarray(rgba, 'RGBA')


def on_light(a):
    # fond blanc ; avant-plan : bleu nuit ou cyan
    R, G, B = a[..., 0], a[..., 1], a[..., 2]
    dr, db = 255 - R, 255 - B
    ratio = dr / (db + 1e-3)
    cyan = ratio > 3.5
    alpha = np.where(cyan, dr / (255 - CY[0]), dr / 255.0)
    rgb = np.where(cyan[..., None], CY, NAVY)
    return finish(alpha, rgb)


def on_navy(a):
    # fond bleu nuit ; avant-plan : blanc ou cyan
    R, G, B = a[..., 0], a[..., 1], a[..., 2]
    cyan = (G - R) > 40
    a_cy = ((B - NAVY[2]) / (CY[2] - NAVY[2]) + (G - NAVY[1]) / (CY[1] - NAVY[1])) / 2
    a_wh = R / 255.0
    alpha = np.where(cyan, a_cy, a_wh)
    rgb = np.where(cyan[..., None], CY, WHITE)
    return finish(alpha, rgb)


def on_black(a):
    # fond noir opaque ; les parties bleu nuit (0,15,38) ne sont pas du dessin visible : elles tombent
    R, G, B = a[..., 0], a[..., 1], a[..., 2]
    cyan = (G - R) > 40
    cyan = cyan & (G > 0.7 * B) | ((G - R) > 90)
    a_cy = B / CY[2]
    a_wh = R / 255.0
    alpha = np.where(cyan, a_cy, a_wh)
    rgb = np.where(cyan[..., None], CY, WHITE)
    return finish(alpha, rgb)


def crop(img, pad=0):
    a = np.array(img)[..., 3]
    ys, xs = np.where(a > 25)
    return img.crop((xs.min() - pad, ys.min() - pad, xs.max() + 1 + pad, ys.max() + 1 + pad))


def split_symbol(img):
    """Separe le symbole (haut) du logotype (bas) a la premiere ligne vide apres le symbole."""
    a = np.array(img)[..., 3]
    rows = (a > 25).any(axis=1)
    ys = np.where(rows)[0]
    y0 = ys.min()
    y = y0
    while rows[y]:
        y += 1
    sym = img.crop((0, 0, img.width, y))
    return crop(sym)


def load(n):
    return np.array(Image.open(f'{src}/{n}.jpg').convert('RGB')).astype(float)


light = crop(on_light(load('1')))
light.save(f'{out}/logo-complet-clair.png', optimize=True)
split_symbol(light).save(f'{out}/symbole-clair.png', optimize=True)

navy = crop(on_navy(load('3')))
navy.save(f'{out}/logo-complet-sombre.png', optimize=True)
sym_dark = split_symbol(navy)
sym_dark.save(f'{out}/symbole-sombre.png', optimize=True)

horiz = crop(on_black(load('2')))
horiz.save(f'{out}/logo-horizontal-sombre.png', optimize=True)

# favicon / avatar : symbole seul centre sur un carre bleu nuit (composition, pas de retouche du dessin)
for size in (512, 180, 64, 32):
    k = 0.62 * size / max(sym_dark.size)
    s = sym_dark.resize((max(1, round(sym_dark.width * k)), max(1, round(sym_dark.height * k))), Image.LANCZOS)
    bg = Image.new('RGBA', (size, size), (0, 15, 38, 255))
    bg.alpha_composite(s, ((size - s.width) // 2, (size - s.height) // 2))
    bg.convert('RGB').save(f'{out}/favicon-{size}.png', optimize=True)

for f in ['logo-complet-clair', 'symbole-clair', 'logo-complet-sombre', 'symbole-sombre', 'logo-horizontal-sombre']:
    im = Image.open(f'{out}/{f}.png')
    print(f, im.size)
