"""Genera las imágenes de marca del tema (categorías, productos de ejemplo y placeholder).
Uso: python3 tools/generar-imagenes.py   (requiere Pillow)"""
import math, os
from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
A = os.path.join(ROOT, 'wp-content/themes/mechanical-bull/assets')
NEGRO, ROJO, BLANCO = (14, 14, 14), (255, 2, 0), (255, 255, 255)
GRIS, GRIS_CLARO = (138, 135, 130), (218, 216, 211)
S = 2  # supersampling
FONT = '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'

def font(px): return ImageFont.truetype(FONT, px * S)
def isotipo(color=(255, 255, 255)):
    im = Image.open(os.path.join(A, 'isotipo-blanco.png')).convert('RGBA')
    if color != (255, 255, 255):
        px = im.load()
        for y in range(im.height):
            for x in range(im.width):
                r, g, b, a = px[x, y]
                if a and not (r > 150 and g < 90): px[x, y] = (*color, a)
    return im

def lienzo(w=800, h=800):
    im = Image.new('RGBA', (w * S, h * S))
    d = ImageDraw.Draw(im)
    for y in range(h * S):  # degradado vertical cálido
        t = y / (h * S)
        c = tuple(int(a + (b - a) * t) for a, b in zip((246, 245, 242), (228, 226, 221)))
        d.line([(0, y), (w * S, y)], fill=c)
    capa = Image.new('RGBA', im.size, (0, 0, 0, 0))
    ImageDraw.Draw(capa).ellipse([w * S * .70, -h * S * .16, w * S * 1.14, h * S * .28], fill=(255, 2, 0, 34))
    im.alpha_composite(capa)
    return im, ImageDraw.Draw(im)

def sombra(base, caja, radio=40, opacidad=70):
    sh = Image.new('RGBA', base.size, (0, 0, 0, 0))
    ImageDraw.Draw(sh).rounded_rectangle(caja, radius=radio, fill=(0, 0, 0, opacidad))
    base.alpha_composite(sh.filter(ImageFilter.GaussianBlur(28 * S)))

def marca_agua(im):
    b = isotipo(GRIS); b.thumbnail((90 * S, 90 * S))
    b.putalpha(b.getchannel('A').point(lambda v: int(v * .35)))
    im.alpha_composite(b, (im.width - b.width - 28 * S, im.height - b.height - 24 * S))

def guardar(im, nombre, size=800):
    os.makedirs(os.path.dirname(nombre), exist_ok=True)
    im = im.resize((size, size if im.width == im.height else int(size * im.height / im.width)), Image.LANCZOS)
    if nombre.endswith('.png'):
        im.save(nombre, optimize=True)  # conserva transparencia
    else:
        im.convert('RGB').save(nombre, quality=88, optimize=True)

def R(*v): return [x * S for x in v]

# ---------------- Productos ----------------
def telefono(screen_top, screen_bot, nombre):
    im, d = lienzo(); sombra(im, R(270, 120, 540, 700), 60)
    d.rounded_rectangle(R(265, 100, 535, 690), radius=55 * S, fill=(20, 20, 22))
    d.rounded_rectangle(R(265, 100, 535, 690), radius=55 * S, outline=(70, 70, 75), width=4 * S)
    scr = Image.new('RGBA', im.size); sd = ImageDraw.Draw(scr)
    for y in range(115 * S, 676 * S):
        t = (y - 115 * S) / (561 * S)
        sd.line(R(0, 0, 0, 0)[:0] + [(280 * S, y), (520 * S, y)], fill=tuple(int(a + (b - a) * t) for a, b in zip(screen_top, screen_bot)))
    m = Image.new('L', im.size, 0); ImageDraw.Draw(m).rounded_rectangle(R(280, 115, 520, 676), radius=42 * S, fill=255)
    im.paste(scr, (0, 0), m)
    d.rounded_rectangle(R(360, 128, 440, 150), radius=11 * S, fill=(10, 10, 10))
    brillo = Image.new('RGBA', im.size, (0, 0, 0, 0))
    ImageDraw.Draw(brillo).polygon(R(280, 115, 520, 115, 520, 260, 280, 400), fill=(255, 255, 255, 26))
    im.paste(Image.alpha_composite(im, brillo), (0, 0), m)
    b = isotipo(); b.thumbnail((120 * S, 120 * S)); b.putalpha(b.getchannel('A').point(lambda v: int(v * .85)))
    im.alpha_composite(b, ((800 * S - b.width) // 2, 340 * S))
    marca_agua(im); guardar(im, nombre)

def bateria(nombre):
    im, d = lienzo(); sombra(im, R(250, 170, 560, 660), 36)
    d.rounded_rectangle(R(245, 150, 555, 650), radius=30 * S, fill=(34, 34, 38))
    d.rounded_rectangle(R(265, 170, 535, 630), radius=20 * S, fill=(48, 48, 54))
    d.rectangle(R(265, 300, 535, 420), fill=ROJO)
    d.text(R(400, 340), '5000 mAh', font=font(38), fill=BLANCO, anchor='mm')
    d.text(R(400, 385), 'Li-ion · 3.85 V', font=font(20), fill=(255, 220, 220), anchor='mm')
    d.rounded_rectangle(R(360, 110, 440, 160), radius=8 * S, fill=(200, 150, 40))
    d.rectangle(R(385, 70, 415, 115), fill=(210, 160, 50))
    d.rounded_rectangle(R(372, 52, 428, 78), radius=6 * S, fill=(30, 30, 30))
    marca_agua(im); guardar(im, nombre)

def puerto(nombre):
    im, d = lienzo(); sombra(im, R(180, 300, 620, 520), 30)
    d.rounded_rectangle(R(170, 300, 630, 500), radius=22 * S, fill=(25, 90, 60))
    for x in range(200, 600, 46):
        d.rectangle(R(x, 330, x + 26, 350), fill=(20, 20, 20))
    d.rounded_rectangle(R(350, 410, 450, 470), radius=26 * S, fill=(170, 172, 178))
    d.rounded_rectangle(R(368, 428, 432, 452), radius=12 * S, fill=(40, 40, 44))
    d.rounded_rectangle(R(470, 150, 520, 310), radius=10 * S, fill=(220, 150, 40))
    d.rounded_rectangle(R(455, 120, 535, 165), radius=8 * S, fill=(30, 30, 30))
    d.ellipse(R(220, 420, 260, 460), fill=(190, 190, 195)); d.ellipse(R(540, 420, 580, 460), fill=(190, 190, 195))
    d.text(R(400, 560), 'USB-C', font=font(30), fill=NEGRO, anchor='mm')
    marca_agua(im); guardar(im, nombre)

def ram(nombre):
    im, d = lienzo(); sombra(im, R(120, 300, 680, 520), 20)
    d.rounded_rectangle(R(110, 290, 690, 500), radius=10 * S, fill=(22, 110, 70))
    d.rectangle(R(110, 470, 690, 500), fill=(22, 110, 70))
    for x in range(130, 672, 12): d.rectangle(R(x, 472, x + 7, 500), fill=(214, 172, 60))
    d.rectangle(R(388, 470, 402, 500), fill=(240, 239, 236))
    for x in (150, 280, 430, 560): d.rectangle(R(x, 320, x + 100, 420), fill=(18, 18, 20))
    d.rounded_rectangle(R(160, 430, 640, 458), radius=4 * S, fill=ROJO)
    d.text(R(400, 444), 'DDR4 · 16 GB · 3200 MHz', font=font(18), fill=BLANCO, anchor='mm')
    marca_agua(im); guardar(im, nombre)

def ssd(nombre):
    im, d = lienzo(); sombra(im, R(110, 330, 690, 470), 18)
    d.rounded_rectangle(R(100, 330, 700, 460), radius=8 * S, fill=(20, 20, 22))
    for y in range(345, 446, 10): d.rectangle(R(100, y, 128, y + 6), fill=(214, 172, 60))
    d.ellipse(R(672, 380, 700, 410), fill=(240, 239, 236))
    d.rounded_rectangle(R(160, 345, 640, 445), radius=6 * S, fill=(245, 245, 245))
    d.rectangle(R(160, 345, 250, 445), fill=ROJO)
    d.text(R(205, 395), 'MB', font=font(30), fill=BLANCO, anchor='mm')
    d.text(R(445, 380), 'NVMe M.2 · 1 TB', font=font(30), fill=NEGRO, anchor='mm')
    d.text(R(445, 418), 'PCIe 3.0 · 3500 MB/s', font=font(18), fill=GRIS, anchor='mm')
    marca_agua(im); guardar(im, nombre)

def gpu(nombre):
    im, d = lienzo(); sombra(im, R(110, 250, 690, 560), 30)
    d.rectangle(R(640, 230, 680, 590), fill=(170, 172, 178))
    d.rounded_rectangle(R(100, 240, 650, 540), radius=34 * S, fill=(26, 26, 30))
    d.rectangle(R(100, 400, 650, 406), fill=ROJO)
    for cx in (240, 510):
        d.ellipse(R(cx - 115, 390 - 115, cx + 115, 390 + 115), fill=(40, 40, 46))
        d.ellipse(R(cx - 100, 390 - 100, cx + 100, 390 + 100), fill=(18, 18, 20))
        for k in range(9):
            a = k * 2 * math.pi / 9
            p = [(cx + 22 * math.cos(a + .2), 390 + 22 * math.sin(a + .2)), (cx + 95 * math.cos(a), 390 + 95 * math.sin(a)), (cx + 95 * math.cos(a + .45), 390 + 95 * math.sin(a + .45))]
            d.polygon([(x * S, y * S) for x, y in p], fill=(58, 58, 64))
        d.ellipse(R(cx - 26, 364, cx + 26, 416), fill=(30, 30, 34)); d.ellipse(R(cx - 10, 380, cx + 10, 400), fill=ROJO)
    for x in range(180, 520, 11): d.rectangle(R(x, 540, x + 7, 568), fill=(214, 172, 60))
    d.text(R(375, 280), 'RTX 4060 · 8 GB', font=font(22), fill=(200, 200, 200), anchor='mm')
    marca_agua(im); guardar(im, nombre)

def kit(nombre):
    im, d = lienzo(); sombra(im, R(160, 200, 640, 620), 30)
    d.rounded_rectangle(R(150, 190, 650, 610), radius=28 * S, fill=(26, 26, 30))
    d.rounded_rectangle(R(175, 215, 625, 585), radius=18 * S, fill=(44, 44, 50))
    for r in range(4):
        for c in range(6):
            x, y = 215 + c * 70, 250 + r * 62
            d.ellipse(R(x, y, x + 34, y + 34), fill=(175, 178, 184)); d.ellipse(R(x + 10, y + 10, x + 24, y + 24), fill=(90, 92, 98))
    d.rounded_rectangle(R(240, 520, 520, 556), radius=18 * S, fill=ROJO)
    d.rectangle(R(520, 530, 600, 546), fill=(190, 192, 198))
    marca_agua(im); guardar(im, nombre)

P = os.path.join(A, 'productos')
telefono((30, 40, 120), (230, 20, 40), f'{P}/pantalla-oled.jpg')
telefono((70, 80, 100), (150, 120, 120), f'{P}/pantalla-incell.jpg')
bateria(f'{P}/bateria.jpg'); puerto(f'{P}/puerto-carga.jpg'); ram(f'{P}/ram.jpg')
ssd(f'{P}/ssd.jpg'); gpu(f'{P}/gpu.jpg'); kit(f'{P}/kit-herramientas.jpg')

# ---------------- Categorías (íconos de línea) ----------------
def categoria(dibujo, nombre):
    im = Image.new('RGBA', (600 * S, 600 * S), (*NEGRO, 255)); d = ImageDraw.Draw(im)
    d.ellipse(R(400, -120, 720, 200), fill=ROJO); d.ellipse(R(-140, 470, 160, 770), fill=(40, 40, 40))
    dibujo(d); guardar(im, nombre, 600)
W = 14 * S
def ic_cel(d):
    d.rounded_rectangle(R(215, 110, 385, 490), radius=34 * S, outline=BLANCO, width=W)
    d.line(R(270, 150, 330, 150), fill=BLANCO, width=W); d.ellipse(R(287, 430, 313, 456), fill=ROJO)
def ic_laptop(d):
    d.rounded_rectangle(R(150, 160, 450, 370), radius=18 * S, outline=BLANCO, width=W)
    d.rounded_rectangle(R(100, 390, 500, 430), radius=14 * S, fill=BLANCO); d.rectangle(R(260, 390, 340, 402), fill=ROJO)
def ic_pc(d):
    d.rounded_rectangle(R(110, 150, 350, 330), radius=14 * S, outline=BLANCO, width=W)
    d.line(R(230, 330, 230, 400), fill=BLANCO, width=W); d.line(R(170, 405, 290, 405), fill=BLANCO, width=W)
    d.rounded_rectangle(R(385, 130, 495, 440), radius=16 * S, outline=BLANCO, width=W)
    d.ellipse(R(424, 180, 456, 212), fill=ROJO); d.line(R(415, 260, 465, 260), fill=BLANCO, width=8 * S); d.line(R(415, 290, 465, 290), fill=BLANCO, width=8 * S)
def ic_tools(d):
    # llave: mango + cabeza abierta
    d.line(R(165, 445, 365, 245), fill=BLANCO, width=30 * S)
    d.arc(R(330, 130, 480, 280), start=-20, end=250, fill=BLANCO, width=30 * S)
    # desarmador: mango rojo + vástago
    d.line(R(200, 200, 300, 300), fill=ROJO, width=40 * S)
    d.line(R(300, 300, 445, 445), fill=BLANCO, width=14 * S)
C = os.path.join(A, 'categorias-v2')
categoria(ic_cel, f'{C}/celulares.jpg'); categoria(ic_laptop, f'{C}/laptops.jpg')
categoria(ic_pc, f'{C}/pc-de-escritorio-y-gamer.jpg'); categoria(ic_tools, f'{C}/herramientas-y-accesorios.jpg')

# ---------------- Placeholder de marca ----------------
im, d = lienzo()
b = isotipo(GRIS_CLARO); b.thumbnail((360 * S, 360 * S)); im.alpha_composite(b, ((800 * S - b.width) // 2, 230 * S))
d.text(R(400, 610), 'Foto próximamente', font=font(30), fill=GRIS, anchor='mm')
guardar(im, os.path.join(A, 'placeholder.jpg'))
# ---------------- Portada y banners (sin fondo, para Elementor) ----------------
E = os.path.join(A, 'elementor')
im = Image.new('RGBA', (900 * S, 820 * S), (0, 0, 0, 0))
glow = Image.new('RGBA', im.size, (0, 0, 0, 0))
ImageDraw.Draw(glow).ellipse(R(150, 110, 750, 710), fill=(255, 2, 0, 150))
im.alpha_composite(glow.filter(ImageFilter.GaussianBlur(60 * S)))
circ = Image.new('RGBA', im.size, (0, 0, 0, 0)); cd = ImageDraw.Draw(circ)
for r in range(300, 0, -2):  # degradado radial del círculo
    t = r / 300
    col = tuple(int(a + (b - a) * t) for a, b in zip((255, 70, 60), (170, 0, 0)))
    cx, cy = 430 - (1 - t) * 40, 390 - (1 - t) * 50
    cd.ellipse(R(cx - r, cy - r, cx + r, cy + r), fill=(*col, 255))
im.alpha_composite(circ)
toro = isotipo()
ancho = 600 * S
toro = toro.resize((ancho, int(toro.height * ancho / toro.width)), Image.LANCZOS)
sombra_toro = Image.new('RGBA', im.size, (0, 0, 0, 0))
sombra_toro.paste((0, 0, 0, 110), ((900 * S - toro.width) // 2 + 30 * S, 400 * S - toro.height // 2 + 40 * S), toro)
im.alpha_composite(sombra_toro.filter(ImageFilter.GaussianBlur(18 * S)))
im.alpha_composite(toro, ((900 * S - toro.width) // 2 + 10 * S, 400 * S - toro.height // 2 + 10 * S))
guardar(im, f'{E}/hero-toro.png', 900)

_lienzo, _marca = lienzo, marca_agua
def lienzo(w=800, h=800):
    im = Image.new('RGBA', (w * S, h * S), (0, 0, 0, 0)); return im, ImageDraw.Draw(im)
marca_agua = lambda im: None
gpu(f'{E}/gpu-sin-fondo.png')
telefono((30, 40, 120), (230, 20, 40), f'{E}/telefono-sin-fondo.png')
lienzo, marca_agua = _lienzo, _marca

print('Imágenes generadas en', A)
