"""Génère la série d'œuvres graphiques Lead Performant (SVG) qui remplace les photos de banque d'images.
Motif fondateur : l'arc du logo (arc supérieur or, arc inférieur marine), décliné en ondes, cercles, trames."""
import math, os, random, sys

OUT = sys.argv[1]
os.makedirs(OUT, exist_ok=True)
W, H = 1600, 1200
GOLD, GOLD_L, NAVY, DEEP, IVORY = "#C9A23F", "#E3C277", "#1D3A60", "#0A1830", "#F5F0E6"

def svg(body, bg="dark", extra_defs=""):
    if bg == "dark":
        defs = f'''<radialGradient id="bg" cx="72%" cy="28%" r="95%"><stop offset="0" stop-color="#21406A"/><stop offset=".55" stop-color="#11264A"/><stop offset="1" stop-color="{DEEP}"/></radialGradient>
<linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{GOLD_L}"/><stop offset="1" stop-color="{GOLD}"/></linearGradient>'''
    else:
        defs = f'''<radialGradient id="bg" cx="30%" cy="20%" r="100%"><stop offset="0" stop-color="#FBF8F2"/><stop offset="1" stop-color="#EDE5D4"/></radialGradient>
<linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#C79A38"/><stop offset="1" stop-color="#9C7424"/></linearGradient>'''
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" preserveAspectRatio="xMidYMid slice">'
            f'<defs>{defs}{extra_defs}</defs><rect width="{W}" height="{H}" fill="url(#bg)"/>{body}</svg>')

def f(x): return f"{x:.1f}".rstrip("0").rstrip(".")

def arc(cx, cy, r, a0, a1):
    x0, y0 = cx + r*math.cos(math.radians(a0)), cy + r*math.sin(math.radians(a0))
    x1, y1 = cx + r*math.cos(math.radians(a1)), cy + r*math.sin(math.radians(a1))
    large = 1 if (a1 - a0) % 360 > 180 else 0
    return f"M{f(x0)} {f(y0)}A{f(r)} {f(r)} 0 {large} 1 {f(x1)} {f(y1)}"

def write(name, s):
    with open(os.path.join(OUT, name + ".svg"), "w") as fh: fh.write(s)

# 1. Voix : ondes concentriques qui se propagent depuis un point (la parole qui porte)
b = []
cx, cy = 360, 760
for i in range(1, 26):
    r = 60 + i*i*2.9 + i*26
    op = max(.16, 1 - i/30)
    sw = 2.6 if i % 5 == 0 else 1.1
    col = "url(#g)" if i % 5 == 0 else GOLD_L
    b.append(f'<path d="{arc(cx, cy, r, -78, 28)}" fill="none" stroke="{col}" stroke-width="{sw}" stroke-opacity="{op:.2f}" stroke-linecap="round"/>')
b.append(f'<circle cx="{cx}" cy="{cy}" r="16" fill="url(#g)"/><circle cx="{cx}" cy="{cy}" r="34" fill="none" stroke="{GOLD_L}" stroke-opacity=".5"/>')
write("voix", svg("".join(b)))

# 2. Embleme : le cercle du logo, monumental et recadré
b = []
cx, cy, R = 1060, 620, 520
b.append(f'<path d="{arc(cx, cy, R, 180, 360)}" fill="none" stroke="url(#g)" stroke-width="46" stroke-linecap="round"/>')
b.append(f'<path d="{arc(cx, cy, R, 8, 172)}" fill="none" stroke="#3A5C8C" stroke-width="46" stroke-linecap="round" stroke-opacity=".9"/>')
for k in range(1, 9):
    b.append(f'<circle cx="{cx}" cy="{cy}" r="{R - 70 - k*52}" fill="none" stroke="{GOLD_L}" stroke-opacity="{.30 - k*.03:.2f}" stroke-width="1"/>')
for k in range(0, 60):
    a = math.radians(180 + k*3)
    x0, y0 = cx + (R+44)*math.cos(a), cy + (R+44)*math.sin(a)
    x1, y1 = cx + (R+44+(22 if k % 5 == 0 else 10))*math.cos(a), cy + (R+44+(22 if k % 5 == 0 else 10))*math.sin(a)
    b.append(f'<line x1="{f(x0)}" y1="{f(y0)}" x2="{f(x1)}" y2="{f(y1)}" stroke="{GOLD_L}" stroke-opacity=".55" stroke-width="1.4"/>')
write("embleme", svg("".join(b)))

# 3. Onde : spectre de la voix, lignes horizontales modulées
b = []
random.seed(7)
rows = 34
for j in range(rows):
    y = 170 + j*26
    pts = []
    amp = 120*math.exp(-((j - rows*0.55)/9.5)**2)
    for i in range(0, 101):
        x = i*16
        env = math.exp(-((x-1000)/430)**2)
        yy = y - amp*env*(0.55+0.45*math.sin(i*0.53+j*0.7))*abs(math.sin(i*0.176+j*0.2))
        pts.append(f"{f(x)} {f(yy)}")
    op = .22 + .7*math.exp(-((j - rows*0.55)/7)**2)
    col = "url(#g)" if abs(j - int(rows*0.55)) < 2 else GOLD_L
    b.append(f'<polyline points="{" ".join(pts)}" fill="none" stroke="{col}" stroke-width="{1.8 if col != GOLD_L else 1.1}" stroke-opacity="{op:.2f}" stroke-linejoin="round"/>')
write("onde", svg("".join(b)))

# 4. Réseau : constellation, un point central qui relie et entraîne (leadership, mobilisation)
b = []
random.seed(11)
nodes = [(1040, 560, 1)]
for k in range(46):
    a = random.uniform(0, 2*math.pi); d = random.uniform(140, 720)
    nodes.append((1040 + d*math.cos(a)*1.15, 560 + d*math.sin(a)*.78, 0))
for (x, y, c) in nodes[1:]:
    d = math.hypot(x-1040, y-560)
    if d < 470:
        b.append(f'<line x1="1040" y1="560" x2="{f(x)}" y2="{f(y)}" stroke="{GOLD_L}" stroke-opacity="{.55 - d/1100:.2f}" stroke-width="1"/>')
for i, (x, y, c) in enumerate(nodes[1:]):
    for (x2, y2, c2) in nodes[i+2:]:
        if math.hypot(x-x2, y-y2) < 150:
            b.append(f'<line x1="{f(x)}" y1="{f(y)}" x2="{f(x2)}" y2="{f(y2)}" stroke="#8FA6C8" stroke-opacity=".22" stroke-width="1"/>')
for (x, y, c) in nodes[1:]:
    r = random.choice([3, 4, 5, 7])
    b.append(f'<circle cx="{f(x)}" cy="{f(y)}" r="{r}" fill="{GOLD_L if r > 5 else "#C8D4E6"}" fill-opacity="{.9 if r > 5 else .55}"/>')
for k in (60, 110, 170):
    b.append(f'<circle cx="1040" cy="560" r="{k}" fill="none" stroke="{GOLD_L}" stroke-opacity="{.5 - k/500:.2f}"/>')
b.append('<circle cx="1040" cy="560" r="20" fill="url(#g)"/>')
write("reseau", svg("".join(b)))

# 5. Dialogue : deux cercles qui se rencontrent, l'intersection en or
b = []
extra = '<clipPath id="c1"><circle cx="680" cy="600" r="380"/></clipPath>'
b.append('<circle cx="680" cy="600" r="380" fill="none" stroke="#8FA6C8" stroke-opacity=".55" stroke-width="2"/>')
b.append('<circle cx="1000" cy="600" r="380" fill="none" stroke="#8FA6C8" stroke-opacity=".55" stroke-width="2"/>')
b.append('<g clip-path="url(#c1)"><circle cx="1000" cy="600" r="380" fill="url(#g)" fill-opacity=".92"/></g>')
for k in range(1, 7):
    b.append(f'<circle cx="680" cy="600" r="{380 + k*34}" fill="none" stroke="#8FA6C8" stroke-opacity="{.22 - k*.03:.2f}"/>')
    b.append(f'<circle cx="1000" cy="600" r="{380 + k*34}" fill="none" stroke="#8FA6C8" stroke-opacity="{.22 - k*.03:.2f}"/>')
write("dialogue", svg("".join(b), extra_defs=extra))

# 6. Boussole : cap et arbitrage (leadership stratégique)
b = []
cx, cy = 1020, 600
for r in (470, 400, 330, 180):
    b.append(f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="{GOLD_L}" stroke-opacity="{.18 + (470-r)/1600:.2f}" stroke-width="{1.4 if r != 400 else 2.2}"/>')
for k in range(0, 360, 3):
    a = math.radians(k); l = 26 if k % 30 == 0 else 10
    b.append(f'<line x1="{f(cx+400*math.cos(a))}" y1="{f(cy+400*math.sin(a))}" x2="{f(cx+(400-l)*math.cos(a))}" y2="{f(cy+(400-l)*math.sin(a))}" stroke="{GOLD_L}" stroke-opacity=".6" stroke-width="1.2"/>')
b.append(f'<path d="M{cx} {cy-330}L{cx+42} {cy}L{cx} {cy+330}L{cx-42} {cy}Z" fill="#3A5C8C" fill-opacity=".55"/>')
b.append(f'<path d="M{cx} {cy-330}L{cx+42} {cy}L{cx-42} {cy}Z" fill="url(#g)"/>')
b.append(f'<path d="M{cx-330} {cy}L{cx} {cy-24}L{cx+330} {cy}L{cx} {cy+24}Z" fill="#8FA6C8" fill-opacity=".25"/>')
b.append(f'<circle cx="{cx}" cy="{cy}" r="12" fill="{DEEP}" stroke="url(#g)" stroke-width="4"/>')
write("boussole", svg("".join(b)))

# 7. Tribune : l'institution, colonnes et arc (conférences, interventions)
b = []
for i in range(13):
    x = 300 + i*85
    h = 520 + 60*math.sin(i/12*math.pi)
    b.append(f'<rect x="{x}" y="{1000-h}" width="34" height="{h}" fill="#3A5C8C" fill-opacity="{.25+.25*math.sin(i/12*math.pi):.2f}"/>')
b.append(f'<path d="{arc(830, 470, 560, 196, 344)}" fill="none" stroke="url(#g)" stroke-width="10" stroke-linecap="round"/>')
b.append(f'<path d="{arc(830, 470, 620, 200, 340)}" fill="none" stroke="{GOLD_L}" stroke-opacity=".35" stroke-width="1.4"/>')
b.append(f'<line x1="200" y1="1000" x2="1460" y2="1000" stroke="url(#g)" stroke-width="3"/>')
b.append(f'<line x1="240" y1="1030" x2="1420" y2="1030" stroke="{GOLD_L}" stroke-opacity=".4"/>')
write("tribune", svg("".join(b)))

# 8. Amphi : demi-cercles étagés (écoles, auditoires)
b = []
for i in range(16):
    r = 120 + i*52
    b.append(f'<path d="{arc(800, 1060, r, 180, 360)}" fill="none" stroke="{"url(#g)" if i in (0, 7) else GOLD_L}" stroke-width="{3 if i in (0,7) else 1.2}" stroke-opacity="{1 if i in (0,7) else .45 - i*.02:.2f}" stroke-dasharray="{"none" if i < 8 else "2 10"}"/>')
b.append('<circle cx="800" cy="1060" r="22" fill="url(#g)"/>')
write("amphi", svg("".join(b)))

# 9. Grille : trame architecturale, une diagonale or (organisations, méthode)
b = []
for x in range(0, W+1, 80):
    b.append(f'<line x1="{x}" y1="0" x2="{x}" y2="{H}" stroke="#8FA6C8" stroke-opacity="{.20 if x % 400 else .38}" stroke-width="1"/>')
for y in range(0, H+1, 80):
    b.append(f'<line x1="0" y1="{y}" x2="{W}" y2="{y}" stroke="#8FA6C8" stroke-opacity="{.20 if y % 400 else .38}" stroke-width="1"/>')
pts = [(160, 1040), (480, 880), (720, 900), (960, 640), (1200, 560), (1440, 240)]
b.append('<polyline points="' + " ".join(f"{x} {y}" for x, y in pts) + '" fill="none" stroke="url(#g)" stroke-width="6" stroke-linejoin="round" stroke-linecap="round"/>')
for x, y in pts:
    b.append(f'<circle cx="{x}" cy="{y}" r="11" fill="{DEEP}" stroke="url(#g)" stroke-width="4"/>')
write("grille", svg("".join(b)))

# 10-11. Variantes claires (papier ivoire, encre marine) pour les ressources
def light_voice(name, seed):
    random.seed(seed); b = []
    cx, cy = {1: (260, 900), 2: (1340, 240), 5: (820, 1150)}[seed]
    for i in range(1, 22):
        r = 50 + i*58
        b.append(f'<circle cx="{cx}" cy="{cy}" r="{r}" fill="none" stroke="{"url(#g)" if i % 6 == 0 else NAVY}" stroke-width="{2.4 if i % 6 == 0 else 1}" stroke-opacity="{.85 if i % 6 == 0 else .16}"/>')
    b.append(f'<circle cx="{cx}" cy="{cy}" r="14" fill="url(#g)"/>')
    write(name, svg("".join(b), bg="light"))
light_voice("papier-1", 1)
light_voice("papier-2", 2)
light_voice("papier-3", 5)
print("ok", sorted(os.listdir(OUT)))
