"""Generates the product illustrations used by the site (img/products/*.svg).

Run:  python3 tools/make_images.py
Each product image is a clean vector illustration so the site works offline
and loads instantly. Replace any file with a real photo (same name, or update
js/data.js) whenever product photography is available.
"""
import os

OUT = os.path.join(os.path.dirname(__file__), "..", "img", "products")
os.makedirs(OUT, exist_ok=True)


def shade(hex_color, f):
    h = hex_color.lstrip("#")
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    if f < 0:
        r, g, b = (int(c * (1 + f)) for c in (r, g, b))
    else:
        r, g, b = (int(c + (255 - c) * f) for c in (r, g, b))
    return "#%02x%02x%02x" % (r, g, b)


def frame(body, bg1, bg2, defs=""):
    return f"""<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 600" width="600" height="600">
<defs>
  <radialGradient id="bg" cx="50%" cy="38%" r="75%">
    <stop offset="0" stop-color="{bg1}"/><stop offset="1" stop-color="{bg2}"/>
  </radialGradient>
  <filter id="sh" x="-30%" y="-30%" width="160%" height="160%">
    <feGaussianBlur in="SourceAlpha" stdDeviation="14"/><feOffset dy="18"/>
    <feComponentTransfer><feFuncA type="linear" slope=".28"/></feComponentTransfer>
    <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
  </filter>
  <pattern id="weave" width="10" height="10" patternUnits="userSpaceOnUse">
    <path d="M0 2.5h10M0 7.5h10" stroke="#000" stroke-opacity=".07" stroke-width="2.4"/>
    <path d="M2.5 0v10M7.5 0v10" stroke="#fff" stroke-opacity=".10" stroke-width="2.4"/>
  </pattern>
  <pattern id="flute" width="8" height="8" patternUnits="userSpaceOnUse">
    <path d="M0 4q2-4 4 0t4 0" fill="none" stroke="#000" stroke-opacity=".08" stroke-width="1.2"/>
  </pattern>
  <linearGradient id="gloss" x1="0" x2="1">
    <stop offset="0" stop-color="#fff" stop-opacity="0"/><stop offset=".45" stop-color="#fff" stop-opacity=".35"/>
    <stop offset=".55" stop-color="#fff" stop-opacity="0"/>
  </linearGradient>
  {defs}
</defs>
<rect width="600" height="600" fill="url(#bg)"/>
<circle cx="300" cy="300" r="230" fill="#fff" fill-opacity=".06"/>
<ellipse cx="300" cy="530" rx="200" ry="22" fill="#000" fill-opacity=".10"/>
<g filter="url(#sh)">{body}</g>
</svg>"""


def logo(x, y, s=1, color="#fff"):
    return (f'<g transform="translate({x} {y}) scale({s})" fill="{color}" font-family="Arial,Helvetica,sans-serif" '
            f'text-anchor="middle"><text font-size="34" font-weight="900" letter-spacing="3">ZAINCO</text>'
            f'<text y="22" font-size="11" letter-spacing="4" opacity=".85">PACKAGING</text></g>')


def woven_sack(c, label="50 KG"):
    d = shade(c, -.25)
    return f"""
<path d="M170 110 Q300 90 430 110 L450 480 Q300 505 150 480 Z" fill="{c}"/>
<path d="M170 110 Q300 90 430 110 L450 480 Q300 505 150 480 Z" fill="url(#weave)"/>
<path d="M170 110 Q300 90 430 110 L432 140 Q300 122 168 140 Z" fill="{d}"/>
<path d="M150 480 Q300 505 450 480 L448 455 Q300 478 152 455 Z" fill="{d}"/>
<path d="M175 128 q125-14 250 0" stroke="#fff" stroke-dasharray="6 6" stroke-width="2" fill="none" opacity=".7"/>
<rect x="205" y="200" width="190" height="200" rx="10" fill="#fff" fill-opacity=".92"/>
{logo(300, 265, 1, d)}
<rect x="235" y="310" width="130" height="38" rx="19" fill="{d}"/>
<text x="300" y="336" text-anchor="middle" font-family="Arial" font-weight="800" font-size="20" fill="#fff">{label}</text>
<text x="300" y="378" text-anchor="middle" font-family="Arial" font-size="12" letter-spacing="2" fill="{d}">PP WOVEN</text>
"""


def bopp_bag(c, c2):
    d = shade(c, -.3)
    return f"""
<path d="M180 105 L420 105 L440 485 L160 485 Z" fill="{c}"/>
<path d="M180 105 L420 105 L440 485 L160 485 Z" fill="url(#weave)" opacity=".5"/>
<path d="M160 300 L440 300 L440 485 L160 485 Z" fill="{c2}"/>
<circle cx="300" cy="300" r="78" fill="#fff"/>
<circle cx="300" cy="300" r="66" fill="{c2}"/>
<path d="M262 318 q38-70 76 0" fill="none" stroke="#fff" stroke-width="10" stroke-linecap="round"/>
<circle cx="300" cy="282" r="12" fill="#fff"/>
{logo(300, 180, 1.1, "#fff")}
<text x="300" y="440" text-anchor="middle" font-family="Arial" font-weight="800" font-size="26" fill="#fff">PREMIUM RICE</text>
<path d="M180 105 L420 105 L424 130 L178 130 Z" fill="{d}"/>
<path d="M190 105 L260 105 L230 485 L175 485 Z" fill="url(#gloss)"/>
"""


def paper_bag(c, handle="#5b3a1e", text="#3b2410"):
    d = shade(c, -.18)
    return f"""
<path d="M245 175 Q245 95 300 95 Q355 95 355 175" fill="none" stroke="{handle}" stroke-width="12" stroke-linecap="round"/>
<path d="M150 175 L410 175 L430 485 L130 485 Z" fill="{c}"/>
<path d="M410 175 L470 195 L470 470 L430 485 Z" fill="{d}"/>
<path d="M150 175 L410 175 L405 205 L155 205 Z" fill="{d}" opacity=".5"/>
<circle cx="245" cy="200" r="7" fill="{shade(c,-.45)}"/><circle cx="355" cy="200" r="7" fill="{shade(c,-.45)}"/>
{logo(285, 330, 1.25, text)}
<path d="M200 395 h170" stroke="{text}" stroke-width="2" opacity=".5"/>
<text x="285" y="425" text-anchor="middle" font-family="Georgia" font-style="italic" font-size="18" fill="{text}">eco friendly</text>
"""


def box(c, tape="#c9a26b", printed=True):
    top, side, front = shade(c, .18), shade(c, -.22), c
    p = logo(235, 380, 1, shade(c, -.55)) if printed else ""
    return f"""
<path d="M120 230 L300 160 L480 230 L300 300 Z" fill="{top}"/>
<path d="M120 230 L300 300 L300 500 L120 430 Z" fill="{front}"/>
<path d="M120 230 L300 300 L300 500 L120 430 Z" fill="url(#flute)"/>
<path d="M480 230 L300 300 L300 500 L480 430 Z" fill="{side}"/>
<path d="M210 195 L390 265 L390 290 L210 220 Z" fill="{tape}" opacity=".9"/>
<path d="M390 265 L390 365 L370 373 L370 273 Z" fill="{shade(tape,-.15)}" opacity=".9"/>
<g transform="skewY(21) translate(0 -88)">{p}</g>
<path d="M150 390 l20 8 v-26 l-20-8 z M180 402 l20 8 v-26 l-20-8 z" fill="{shade(c,-.5)}" opacity=".5"/>
"""


def carton(c, accent):
    d = shade(c, -.25)
    return f"""
<path d="M200 140 L360 120 L420 150 L260 170 Z" fill="{shade(c,.25)}"/>
<path d="M200 140 L260 170 L260 490 L200 460 Z" fill="{d}"/>
<path d="M260 170 L420 150 L420 470 L260 490 Z" fill="{c}"/>
<path d="M260 300 L420 285 L420 360 L260 375 Z" fill="{accent}"/>
<g transform="matrix(1 -.1 0 1 0 30)">{logo(340, 230, .95, "#fff")}</g>
<g transform="matrix(1 -.1 0 1 0 30)"><text x="340" y="330" text-anchor="middle" font-family="Arial" font-weight="700" font-size="17" fill="#fff">500 mg · 10x10</text></g>
<path d="M270 175 L300 171 L300 486 L270 489 Z" fill="url(#gloss)"/>
"""


def pouch(c, accent, zipper=True, window=False):
    d = shade(c, -.3)
    z = f'<path d="M185 150 L415 150" stroke="{d}" stroke-width="8"/>' if zipper else ""
    w = ('<rect x="255" y="330" width="90" height="100" rx="45" fill="#fff" fill-opacity=".55"/>'
         '<circle cx="285" cy="390" r="10" fill="#c58b3a"/><circle cx="310" cy="370" r="9" fill="#a8702c"/>'
         '<circle cx="318" cy="405" r="10" fill="#c58b3a"/>') if window else ""
    return f"""
<path d="M180 110 L420 110 L420 440 Q420 490 380 490 L220 490 Q180 490 180 440 Z" fill="{c}"/>
<path d="M180 110 L420 110 L420 132 L180 132 Z" fill="{d}"/>
<path d="M180 120 l10 -10 l10 10 l10 -10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10 l10-10 l10 10" fill="none" stroke="{shade(c,.3)}" stroke-width="2"/>
{z}
<path d="M180 210 L420 210 L420 300 L180 300 Z" fill="{accent}"/>
{logo(300, 255, 1.05, "#fff")}
{w}
<path d="M200 135 L250 135 L240 480 L200 470 Z" fill="url(#gloss)"/>
"""


def film_roll(c, clear=True):
    op = ".55" if clear else ".95"
    return f"""
<ellipse cx="300" cy="140" rx="120" ry="42" fill="{shade(c,.3)}" fill-opacity="{op}"/>
<path d="M180 140 L180 450 A120 42 0 0 0 420 450 L420 140" fill="{c}" fill-opacity="{op}"/>
<path d="M210 150 L210 460" stroke="#fff" stroke-opacity=".6" stroke-width="16"/>
<path d="M380 150 L380 460" stroke="#000" stroke-opacity=".06" stroke-width="22"/>
<ellipse cx="300" cy="140" rx="120" ry="42" fill="none" stroke="{shade(c,-.2)}" stroke-width="3"/>
<ellipse cx="300" cy="140" rx="34" ry="12" fill="#b88a55"/>
<ellipse cx="300" cy="138" rx="22" ry="7" fill="#7a5530"/>
<path d="M420 300 Q500 320 520 420 L470 440 Q450 360 420 350 Z" fill="{c}" fill-opacity=".45"/>
<rect x="225" y="270" width="150" height="70" rx="8" fill="#fff" fill-opacity=".9"/>
{logo(300, 305, .8, shade(c,-.5))}
"""


def jumbo_bag(c):
    d = shade(c, -.25)
    return f"""
<path d="M175 130 Q170 90 205 95 L215 135 Z M425 130 Q430 90 395 95 L385 135 Z" fill="none" stroke="{d}" stroke-width="16"/>
<path d="M165 130 L435 130 L455 470 L145 470 Z" fill="{c}"/>
<path d="M165 130 L435 130 L455 470 L145 470 Z" fill="url(#weave)"/>
<path d="M165 130 L180 470 M435 130 L420 470" stroke="{d}" stroke-width="12"/>
<path d="M260 95 L340 95 L345 130 L255 130 Z" fill="{shade(c,.15)}"/>
<rect x="225" y="230" width="150" height="150" rx="6" fill="#fff" fill-opacity=".9"/>
{logo(300, 290, .95, d)}
<text x="300" y="345" text-anchor="middle" font-family="Arial" font-weight="800" font-size="24" fill="{d}">1000 KG</text>
<rect x="160" y="470" width="280" height="40" fill="#8a6a43"/><rect x="160" y="470" width="280" height="8" fill="#a5845a"/>
"""


def poly_bag(c, handle=True):
    hh = '<path d="M250 175 Q250 110 300 110 Q350 110 350 175" fill="none" stroke="{c}" stroke-width="30" stroke-opacity=".9"/>'.format(c=c) if handle else ""
    return f"""
{hh}
<path d="M150 150 L450 150 L460 480 L140 480 Z" fill="{c}" fill-opacity=".88"/>
<ellipse cx="300" cy="170" rx="40" ry="22" fill="#fff" fill-opacity=".9"/>
<path d="M170 170 L230 170 L215 470 L165 470 Z" fill="#fff" fill-opacity=".18"/>
{logo(300, 340, 1.2, "#fff")}
<text x="300" y="400" text-anchor="middle" font-family="Arial" font-size="13" letter-spacing="3" fill="#fff" opacity=".9">LDPE · HDPE</text>
"""


def mailer(c):
    d = shade(c, -.3)
    return f"""
<path d="M140 140 L460 140 L460 470 L140 470 Z" fill="{c}"/>
<path d="M140 140 L460 140 L460 210 L140 210 Z" fill="{d}"/>
<path d="M140 210 L460 210" stroke="#fff" stroke-dasharray="10 8" stroke-width="3"/>
<rect x="175" y="245" width="170" height="100" rx="6" fill="#fff"/>
<path d="M190 270 h120 M190 292 h100 M190 314 h130" stroke="#888" stroke-width="6"/>
<path d="M355 360 h80 M355 372 h80 M355 384 h80" stroke="#222" stroke-width="5" stroke-dasharray="3 4"/>
{logo(300, 425, .9, "#fff")}
"""


def jute_bag(c):
    return f"""
<path d="M230 180 Q230 90 300 90 Q370 90 370 180" fill="none" stroke="{shade(c,-.35)}" stroke-width="14"/>
<path d="M150 170 L450 170 L460 480 L140 480 Z" fill="{c}"/>
<path d="M150 170 L450 170 L460 480 L140 480 Z" fill="url(#weave)" opacity="1"/>
<path d="M150 170 L450 170 L450 192 L150 192 Z" fill="{shade(c,-.25)}"/>
<circle cx="300" cy="330" r="88" fill="none" stroke="#2f5d34" stroke-width="5"/>
<path d="M300 280 q-40 40 0 100 q40-60 0-100z" fill="#2f5d34"/>
{logo(300, 445, .8, "#3a2a16")}
"""


def tape(c):
    return f"""
<ellipse cx="300" cy="300" rx="170" ry="170" fill="{c}"/>
<ellipse cx="300" cy="300" rx="170" ry="170" fill="url(#gloss)" opacity=".7"/>
<circle cx="300" cy="300" r="95" fill="#c99a62"/>
<circle cx="300" cy="300" r="78" fill="url(#bg)"/>
<path d="M440 390 L520 470 L500 490 L420 410 Z" fill="{c}" opacity=".85"/>
<g transform="rotate(-20 300 300)"><path id="arc" d="M160 300 A140 140 0 0 1 440 300" fill="none"/>
<text font-family="Arial" font-weight="900" font-size="26" letter-spacing="6" fill="#fff"><textPath href="#arc" startOffset="16%">ZAINCO · ZAINCO</textPath></text></g>
"""


def bubble(c):
    dots = "".join(f'<circle cx="{x}" cy="{y}" r="13" fill="#fff" fill-opacity=".55" stroke="#fff" stroke-opacity=".8"/>'
                   for x in range(195, 430, 34) for y in range(175, 440, 34))
    return f"""
<path d="M170 150 L430 150 L440 460 L160 460 Z" fill="{c}" fill-opacity=".7"/>
{dots}
<rect x="210" y="270" width="180" height="60" rx="8" fill="#fff" fill-opacity=".95"/>
{logo(300, 300, .85, shade(c,-.45))}
"""


def container(c):
    return f"""
<path d="M160 230 L440 230 L410 470 L190 470 Z" fill="#fff" fill-opacity=".92"/>
<path d="M150 200 L450 200 L450 235 L150 235 Z" fill="{c}"/>
<path d="M175 175 L425 175 L450 200 L150 200 Z" fill="{shade(c,.25)}"/>
<rect x="215" y="290" width="170" height="90" rx="10" fill="{c}" fill-opacity=".15"/>
{logo(300, 330, .9, shade(c,-.3))}
<path d="M180 245 L205 245 L215 460 L198 460 Z" fill="url(#gloss)"/>
"""


def label_roll(c):
    return f"""
<circle cx="250" cy="300" r="150" fill="#fff"/>
<circle cx="250" cy="300" r="150" fill="none" stroke="#e8e8e8" stroke-width="40"/>
<circle cx="250" cy="300" r="55" fill="#c99a62"/><circle cx="250" cy="300" r="40" fill="url(#bg)"/>
<path d="M250 150 L470 150 L470 450 L250 450" fill="#fff"/>
<rect x="300" y="175" width="140" height="80" rx="12" fill="{c}"/>
<rect x="300" y="270" width="140" height="80" rx="12" fill="{c}"/>
<rect x="300" y="365" width="140" height="70" rx="12" fill="{c}"/>
<g transform="translate(0 0)">{logo(370, 218, .55, "#fff")}{logo(370, 313, .55, "#fff")}{logo(370, 404, .55, "#fff")}</g>
"""


def nonwoven(c):
    return f"""
<path d="M250 175 L250 120 L350 120 L350 175" fill="none" stroke="{shade(c,-.25)}" stroke-width="22"/>
<rect x="150" y="165" width="300" height="320" rx="6" fill="{c}"/>
<rect x="150" y="165" width="300" height="320" rx="6" fill="url(#weave)" opacity=".6"/>
<path d="M150 165 v320 M450 165 v320 M150 485 h300" stroke="{shade(c,-.2)}" stroke-width="6" stroke-dasharray="4 5"/>
<circle cx="300" cy="320" r="85" fill="#fff" fill-opacity=".92"/>
{logo(300, 320, .85, shade(c,-.35))}
"""


def egg_tray(c):
    cups = "".join(f'<ellipse cx="{x}" cy="{y}" rx="28" ry="20" fill="{shade(c,-.15)}"/><ellipse cx="{x}" cy="{y-6}" rx="18" ry="12" fill="#f6efe2"/>'
                   for x in range(190, 430, 60) for y in range(240, 400, 50))
    return f"""
<path d="M140 210 L460 210 L480 420 L120 420 Z" fill="{c}"/>
<path d="M120 420 L480 420 L480 445 L120 445 Z" fill="{shade(c,-.25)}"/>
{cups}
"""


def scene_factory():
    return """<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 800">
<defs><linearGradient id="s" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0b2545"/><stop offset="1" stop-color="#13315c"/></linearGradient>
<linearGradient id="g" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#1d3b66"/><stop offset="1" stop-color="#0b2545"/></linearGradient></defs>
<rect width="1200" height="800" fill="url(#s)"/>
<circle cx="950" cy="170" r="70" fill="#f4a261" opacity=".85"/>
<path d="M0 520 L0 360 L160 280 L160 360 L320 280 L320 360 L480 280 L480 360 L640 280 L640 520 Z" fill="#1b3a63"/>
<rect x="700" y="170" width="44" height="350" fill="#24497a"/><rect x="790" y="230" width="36" height="290" fill="#24497a"/>
<path d="M722 160 q30-50 0-90 q40 30 20 90z" fill="#fff" opacity=".18"/>
<rect x="640" y="330" width="560" height="190" fill="#24497a"/>
<g fill="#f4a261" opacity=".8">""" + "".join(f'<rect x="{x}" y="380" width="40" height="28" rx="3"/>' for x in range(670, 1180, 70)) + "".join(f'<rect x="{x}" y="400" width="34" height="22" rx="3"/>' for x in range(30, 620, 80)) + """</g>
<rect y="520" width="1200" height="280" fill="url(#g)"/>
<g fill="#c49a6c">""" + "".join(f'<path d="M{x} 600 l40-16 40 16-40 16z"/><path d="M{x} 600 l40 16v44l-40-16z" fill="#a37a4e"/><path d="M{x+80} 600 l-40 16v44l40-16z" fill="#8a6440"/>' for x in range(80, 1120, 140)) + """</g>
</svg>"""


PRODUCTS = {
    "pp-woven-sack": woven_sack("#e9ecef", "50 KG"),
    "pp-woven-sack-color": woven_sack("#2a9d8f", "25 KG"),
    "pp-cement-bag": woven_sack("#adb5bd", "CEMENT"),
    "pp-flour-bag": woven_sack("#f1faee", "ATTA 10KG"),
    "bopp-rice-bag": bopp_bag("#1d3557", "#e63946"),
    "bopp-feed-bag": bopp_bag("#2d6a4f", "#f4a261"),
    "kraft-paper-bag": paper_bag("#c8a27a"),
    "white-paper-bag": paper_bag("#f8f9fa", "#1d3557", "#1d3557"),
    "paper-sack": woven_sack("#d4b48c", "MULTIWALL"),
    "food-paper-bag": paper_bag("#e6c9a0", "#e6c9a0", "#7f1d1d"),
    "corrugated-box": box("#c49a6c"),
    "shipping-box-5ply": box("#b5895a", "#1d3557"),
    "printed-ecom-box": box("#264653", "#e9c46a"),
    "pizza-box": box("#e9d8a6", "#ae2012"),
    "pharma-carton": carton("#ffffff", "#0077b6"),
    "food-carton": carton("#f4a261", "#9b2226"),
    "cosmetic-carton": carton("#3d0066", "#c77dff"),
    "standup-pouch": pouch("#6a994e", "#386641", True, True),
    "zipper-pouch": pouch("#ffb703", "#fb8500", True, False),
    "spice-pouch": pouch("#9d0208", "#370617", False, False),
    "laminated-roll": film_roll("#e76f51", False),
    "stretch-film": film_roll("#a8dadc", True),
    "shrink-film": film_roll("#caf0f8", True),
    "fibc-jumbo-bag": jumbo_bag("#f1f3f5"),
    "jumbo-bag-baffle": jumbo_bag("#e9c46a"),
    "poly-shopping-bag": poly_bag("#0077b6"),
    "garbage-bag": poly_bag("#212529", False),
    "courier-mailer": mailer("#495057"),
    "jute-bag": jute_bag("#c9a46a"),
    "non-woven-bag": nonwoven("#e63946"),
    "packing-tape": tape("#a47148"),
    "bubble-wrap": bubble("#90e0ef"),
    "food-container": container("#e63946"),
    "product-labels": label_roll("#1d3557"),
    "egg-tray": egg_tray("#cbb293"),
}

BGS = [("#f5f7fa", "#dde4ee"), ("#fdf6ec", "#efe0c9"), ("#eef6f3", "#d4e7df"), ("#f3f0f8", "#ddd5ea")]

for i, (name, body) in enumerate(PRODUCTS.items()):
    b1, b2 = BGS[i % len(BGS)]
    with open(os.path.join(OUT, name + ".svg"), "w") as f:
        f.write(frame(body, b1, b2))

with open(os.path.join(OUT, "..", "factory-scene.svg"), "w") as f:
    f.write(scene_factory())

print(f"wrote {len(PRODUCTS)} product images + factory scene")
