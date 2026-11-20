"""Lead Performant — intégration de la maquette à deux pôles dans le site en ligne.
Produit dans refonte/ les pages, la feuille de style, le script commun et les images,
à partir de la maquette (canvas/project) et des fichiers en ligne (live/)."""
import json, re, shutil, hashlib, html, sys, pathlib
from PIL import Image

sys.path.insert(0, "/home/claude/canvas")
from build import typo  # même typographie française que la maquette

ROOT = pathlib.Path("/home/claude")
MAQ = ROOT / "canvas/project"
LIVE = ROOT / "live"
OUT = ROOT / "leadperformant-plateforme/refonte"
S2 = ROOT / "site2"
SITE = "https://www.leadperformant.com"
V = "20261120c"
EMAIL = "leadperformant@gmail.com"
TEL, TELH = "06 62 57 92 74", "tel:+33662579274"
NB = " "

written = []
def write(rel, data):
    p = OUT / rel
    p.parent.mkdir(parents=True, exist_ok=True)
    if isinstance(data, str):
        p.write_text(data, encoding="utf-8")
    else:
        p.write_bytes(data)
    written.append(rel)

def esc(s):
    return html.escape(str(s), quote=True)

# ------------------------------------------------------------------ images
BLOB = json.load(open(ROOT / "work/blobmap.json"))
RENAME = {  # noms publics des vignettes et photos d'intervention
    "portrait.webp": "portrait-abdou-fleur-carre.webp", "interv1.webp": "intervention-paris-2026-conference.webp",
    "interv2.webp": "intervention-paris-2026-soiree-debats.webp", "interv3.webp": "intervention-paris-2026-echange-public.webp",
    "unesco-bamba.webp": "unesco-journee-cheikh-ahmadou-bamba-moderation.webp",
}
IMG = {}  # blob -> (url, w, h, srcset)
def add_image(src):
    src = pathlib.Path(src)
    name = RENAME.get(src.name, src.name)
    if name.startswith("yt-"):
        name = "video-" + name[3:]
    im = Image.open(src)
    w, h = im.size
    url = "/assets/lp/img/" + name
    write("assets/lp/img/" + name, src.read_bytes())
    srcset = ""
    if w > 1300:
        small = name.replace(".webp", "-960.webp")
        sm = im.convert("RGB").resize((960, round(h * 960 / w)), Image.LANCZOS)
        p = OUT / "assets/lp/img" / small
        p.parent.mkdir(parents=True, exist_ok=True)
        sm.save(p, "WEBP", quality=82, method=6)
        written.append("assets/lp/img/" + small)
        srcset = f"/assets/lp/img/{small} 960w, {url} {w}w"
    return url, w, h, srcset

for b, f in BLOB.items():
    if f:
        IMG[b] = add_image(f)
# vignette supplémentaire (émission avec Doudou Diène etc. sont déjà dans la table)
EXTRA_IMG = {}
for n in ["unesco-pupitre-journee-cheikh-ahmadou-bamba-4x5.webp"]:
    p = ROOT / "photos/out/site" / n
    if p.exists():
        EXTRA_IMG[n] = add_image(p)

def img_attrs(tag, first=False):
    """Ajoute dimensions, srcset, chargement différé à une balise <img> de la maquette."""
    m = re.search(r'src="/_blob/([0-9a-f]+)"', tag)
    if not m:
        return tag
    url, w, h, srcset = IMG[m.group(1)]
    tag = tag.replace(m.group(0), f'src="{url}"')
    st = re.search(r'style="([^"]*)"', tag)
    if st and not re.search(r"(?<![-\w])height\s*:", st.group(1)):
        tag = tag.replace(st.group(0), 'style="' + st.group(1).rstrip("; ") + '; height: auto"')
    extra = f' width="{w}" height="{h}"'
    if srcset:
        extra += f' srcset="{srcset}" sizes="(max-width: 900px) 100vw, 60vw"'
    extra += ' fetchpriority="high"' if first else ' loading="lazy"'
    extra += ' decoding="async"'
    return tag.replace("<img ", "<img" + extra + " ", 1)

def fix_images(s):
    n = [0]
    def rep(m):
        n[0] += 1
        return img_attrs(m.group(0), first=(n[0] == 1))
    return re.sub(r"<img [^>]*>", rep, s)

# ------------------------------------------------------------------ navigation
NAV = [("pole1", "Art oratoire et leadership", "/art-oratoire-leadership/"),
       ("pole2", "Médiation et gestion des conflits", "/mediation-gestion-des-conflits/"),
       ("interventions", "Interventions et médias", "/interventions/"),
       ("ressources", "Ressources", "/ressources/"),
       ("fondateur", "Le fondateur", "/fondateur/")]
LOGO = ('<svg width="{s}" height="{s}" viewBox="0 0 48 48" aria-hidden="true"><path d="M5.2 19.5 A19.4 19.4 0 0 1 42.8 19.5" fill="none" stroke="#C9A45C" stroke-width="{w}" stroke-linecap="round"></path>'
        '<path d="M42.8 28.5 A19.4 19.4 0 0 1 5.2 28.5" fill="none" stroke="#F6F3EC" stroke-width="{w}" stroke-linecap="round"></path></svg>')

def header(active=""):
    cur = lambda k: ' aria-current="page"' if k == active else ""
    items = "".join(f'<li><a href="{h}" data-k="{k}"{cur(k)}>{l}</a></li>' for k, l, h in NAV)
    mitems = "".join(f'<li><a href="{h}" data-k="{k}"{cur(k)}>{l}<span aria-hidden="true">→</span></a></li>' for k, l, h in NAV)
    return (
        '<header class="lp2-h" id="top">'
        '<div class="lp2-bar">'
        f'<a class="lp2-brand" href="/" aria-label="Lead Performant, accueil">{LOGO.format(s=38, w=2.6)}'
        '<span class="lp2-brand-t"><span class="lp2-brand-n">LEAD PERFORMANT</span><span class="lp2-brand-s"><span>Parler pour convaincre.</span> <span>Dialoguer pour apaiser.</span></span></span></a>'
        '<div class="lp2-act">'
        f'<a class="lp2-tel" href="{TELH}">{TEL.replace(" ", NB)}</a>'
        '<a class="lp2-cta" href="/contact/">Demander un échange</a>'
        '<button class="lp2-burger" type="button" aria-expanded="false" aria-controls="lp2-menu" aria-label="Ouvrir le menu">'
        '<svg width="18" height="12" viewBox="0 0 18 12" aria-hidden="true"><path d="M0 1h18M0 6h18M0 11h18" stroke="#F6F3EC" stroke-width="1.5"></path></svg><span>Menu</span></button>'
        '</div></div>'
        f'<nav class="lp2-nav" aria-label="Navigation principale"><ul>{items}</ul></nav>'
        f'<nav class="lp2-menu" id="lp2-menu" aria-label="Menu mobile" hidden><ul>{mitems}</ul>'
        '<a class="lp2-menu-cta" href="/contact/">Demander un échange</a>'
        f'<a class="lp2-menu-tel" href="{TELH}">{TEL.replace(" ", NB)}</a></nav>'
        '</header>'
    )

PROGS = [("eloquence-elite", "Éloquence Élite"), ("eloquence-levier-professionnel", "L'Éloquence comme levier professionnel"),
         ("parole-d-impact", "Parole d'Impact"), ("leadership-relationnel", "Leadership relationnel et mobilisation"),
         ("leadership-strategique", "Leadership stratégique et situations complexes")]

def footer():
    col1 = "".join(f'<a href="/formations/{s}/">{l}</a>' for s, l in PROGS) + '<a href="/calendrier/">Calendrier des sessions</a>'
    p2 = [("Boussole relationnelle", "/mediation-gestion-des-conflits/#offres"),
          ("L'Art du Désamorçage", "/mediation-gestion-des-conflits/#offres"),
          ("Alliance Durable", "/mediation-gestion-des-conflits/#offres"),
          ("Médiation professionnelle et institutionnelle", "/mediation-gestion-des-conflits/#expertises"),
          ("Formations CNV et gestion des conflits", "/mediation-gestion-des-conflits/#formations"),
          ("Faire le point en ligne", "/mediation-gestion-des-conflits/boussole-relationnelle/")]
    col2 = "".join(f'<a href="{h}">{l}</a>' for l, h in p2)
    col3 = ('<a href="/interventions/">Interventions et médias</a><a href="/ressources/">Ressources</a><a href="/fondateur/">Le fondateur</a>'
            '<a href="/boutique/">Boutique</a><a class="lp2-strong" href="/contact/">Demander un échange</a>')
    legal = [("Mentions légales", "/mentions-legales/"), ("Confidentialité", "/confidentialite/"),
             ("Conditions générales de vente", "/cgv/"), ("Règlement intérieur", "/reglement-interieur/")]
    return typo(
        '<footer class="lp2-f"><div class="lp2-f-in"><div class="lp2-f-cols">'
        '<div class="lp2-f-id">'
        f'<a class="lp2-f-brand" href="/" aria-label="Lead Performant, accueil">{LOGO.format(s=44, w=2.4)}<span>LEAD PERFORMANT</span></a>'
        '<p class="lp2-f-sig">Parler pour convaincre.<br><span>Dialoguer pour apaiser.</span></p>'
        '<p class="lp2-f-txt">Conseil et formation en art oratoire, leadership, médiation et gestion des conflits. Paris, Île-de-France, toute la France et à distance.</p>'
        f'<div class="lp2-f-contact"><a href="mailto:{EMAIL}">{EMAIL}</a><a href="{TELH}">{TEL.replace(" ", NB)}</a></div>'
        '</div>'
        f'<nav class="lp2-f-col" aria-label="Pôle Art oratoire et leadership"><p>I · Art oratoire et leadership</p>{col1}</nav>'
        f'<nav class="lp2-f-col" aria-label="Pôle Médiation et gestion des conflits"><p>II · Médiation et gestion des conflits</p>{col2}</nav>'
        f'<nav class="lp2-f-col lp2-f-narrow" aria-label="Lead Performant"><p>Lead Performant</p>{col3}</nav>'
        '</div><div class="lp2-f-legal">'
        '<p>© 2026 Lead Performant · Abdou FLEUR, entrepreneur individuel · TVA non applicable, article 293 B du CGI</p>'
        '<ul>' + "".join(f'<li><a href="{h}">{l}</a></li>' for l, h in legal) + '<li><a href="/gestion/" rel="nofollow">Espace de gestion</a></li></ul>'
        '</div></div></footer>'
    )

# ------------------------------------------------------------------ gabarit de page
LIVE_INDEX = (LIVE / "index.html").read_text(encoding="utf-8")
LPCONF = re.search(r"<script>window\.LP=(\{.*?\});?</script>", LIVE_INDEX, re.S).group(1)
ORG = {
    "@context": "https://schema.org", "@type": "ProfessionalService", "@id": SITE + "/#org", "name": "Lead Performant",
    "url": SITE + "/", "logo": SITE + "/assets/img/logo-lead-performant.png", "image": SITE + "/assets/lp/img/partage-accueil-1200x630.jpg",
    "slogan": "Parler pour convaincre. Dialoguer pour apaiser.",
    "description": "Conseil et formation en art oratoire, leadership, médiation et gestion des conflits, à Paris, en Île-de-France, dans toute la France et à distance.",
    "telephone": "+33662579274", "email": EMAIL,
    "address": {"@type": "PostalAddress", "addressLocality": "Vigneux-sur-Seine", "postalCode": "91270", "addressCountry": "FR"},
    "areaServed": ["Île-de-France", "France"],
    "founder": {"@type": "Person", "@id": SITE + "/fondateur/#abdou-fleur", "name": "Abdou FLEUR", "url": SITE + "/fondateur/"},
    "knowsAbout": ["Art oratoire", "Prise de parole en public", "Leadership", "Médiation", "Gestion des conflits", "Communication non violente"],
}

def page(path, title, desc, body, active="", og="partage-accueil-1200x630.jpg", jsonld=(), cms=False, extra_head=""):
    lds = [ORG] + list(jsonld)
    ld = "".join('<script type="application/ld+json">' + json.dumps(x, ensure_ascii=False) + "</script>" for x in lds)
    crumbs = ""
    scripts = '<script src="/assets/lp/seed.js" defer></script><script src="/assets/lp/cms.js?v=' + V + '" defer></script>' if cms else ""
    return (
        "<!DOCTYPE html>\n<html lang=\"fr\">\n<head>\n<meta charset=\"utf-8\">\n"
        '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">\n'
        f"<title>{esc(title)}</title>\n<meta name=\"description\" content=\"{esc(desc)}\">\n"
        f'<link rel="canonical" href="{SITE}{path}">\n'
        '<meta property="og:type" content="website">\n<meta property="og:locale" content="fr_FR">\n<meta property="og:site_name" content="Lead Performant">\n'
        f'<meta property="og:title" content="{esc(title)}">\n<meta property="og:description" content="{esc(desc)}">\n'
        f'<meta property="og:url" content="{SITE}{path}">\n<meta property="og:image" content="{SITE}/assets/lp/img/{og}">\n'
        '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">\n'
        '<meta name="twitter:card" content="summary_large_image">\n<meta name="theme-color" content="#0B1A33">\n'
        '<link rel="icon" type="image/png" href="/assets/img/favicon-lead-performant.png">\n'
        '<link rel="preload" href="/assets/lp/fonts/newsreader-opsz-normal.woff2" as="font" type="font/woff2" crossorigin>\n'
        '<link rel="preload" href="/assets/lp/fonts/instrument-sans-wght-normal.woff2" as="font" type="font/woff2" crossorigin>\n'
        f'<link rel="stylesheet" href="/assets/lp/site.css?v={V}">\n{extra_head}{ld}\n</head>\n'
        '<body class="lp2">\n<a class="skip" href="#contenu">Aller au contenu</a>\n'
        + header(active) + "\n" + body + "\n" + footer() + "\n"
        '<div id="toast" class="toast" role="status" aria-live="polite"></div>\n'
        f"<script>window.LP={LPCONF};</script>\n{scripts}<script src=\"/assets/lp/site.js?v={V}\" defer></script>\n"
        "</body>\n</html>\n"
    )

# ------------------------------------------------------------------ conversion des pages statiques de la maquette
def anchor_text(inner):
    return html.unescape(re.sub(r"<[^>]+>", " ", inner)).replace(NB, " ").replace(" ", " ")

def contact_url(text, ctx):
    t = text.lower()
    if "guide" in t:
        return "/contact/?pole=mediation&besoin=guide"
    if "livre blanc" in t:
        return "/contact/?pole=mediation&besoin=livre-blanc"
    if "médiation" in t and ("demander" in t or "engager" in t):
        return "/contact/?pole=mediation&besoin=mediation"
    if "rappel" in t:
        return "/contact/?pole=" + ("mediation" if ctx == "pole2" else "art-oratoire") + "&besoin=echange"
    if ctx == "pole2":
        if "formation" in t:
            return "/contact/?pole=mediation&besoin=formation-cnv"
        return "/contact/?pole=mediation"
    if "prochaines dates" in t or "calendrier" in t:
        return "/calendrier/"
    if ctx == "pole1":
        if "organisation" in t or "proposition" in t:
            return "/contact/?besoin=devis&profil=entreprise"
        if "devis" in t:
            return "/contact/?besoin=devis&profil=entreprise"
        if "coaching" in t:
            return "/contact/?besoin=coaching"
        if "atelier" in t:
            return "/contact/?besoin=atelier&profil=ecole"
    if ctx == "interventions":
        return "/contact/?besoin=intervention&profil=organisateur"
    return "/contact/"

PAGES = {"Main.dc.html": "/", "Pole-art-oratoire-leadership.dc.html": "/art-oratoire-leadership/",
         "Pole-mediation-conflits.dc.html": "/mediation-gestion-des-conflits/", "Fondateur.dc.html": "/fondateur/",
         "Interventions-medias.dc.html": "/interventions/", "Ressources.dc.html": "/ressources/",
         "Boussole-relationnelle.dc.html": "/mediation-gestion-des-conflits/boussole-relationnelle/"}
LINKLOG = []

def map_links(s, ctx, page_name):
    def rep(m):
        attrs, inner = m.group(1), m.group(2)
        hm = re.search(r'href="([^"]*)"', attrs)
        if not hm:
            return m.group(0)
        href, text = hm.group(1), anchor_text(inner)
        new = href
        prog = next((sl for sl, nm in PROGS if nm.split(" et ")[0].replace("'", "’") in text.replace("'", "’") or nm.replace("'", "’") in text.replace("'", "’")), None)
        if href.endswith(".dc.html") or href == "":
            base = href
            if base in ("Pole-art-oratoire-leadership.dc.html", "Demander-un-echange.dc.html") and prog and ctx in ("pole1", "accueil", "footer"):
                new = f"/formations/{prog}/"
            elif base == "Demander-un-echange.dc.html":
                new = contact_url(text, ctx)
            elif base == "Plan-du-site.dc.html":
                t = text.lower()
                new = ("/mentions-legales/" if "mentions" in t else "/confidentialite/" if "confidentialit" in t else
                       "/cgv/" if "conditions" in t else "/reglement-interieur/" if "règlement" in t else "/plan-du-site/")
            elif base == "Ressources.dc.html":
                t = text.lower()
                new = ("/art-oratoire-leadership/#diagnostic" if "diagnostic d" in t else "/laboratoire.html" if "studio" in t or "laboratoire" in t else
                       "/art-oratoire-leadership/#atelier" if "atelier du leader" in t else "/boutique/" if "boutique" in t else "/ressources/")
            elif base == "Pole-art-oratoire-leadership.dc.html" and "calendrier" in text.lower():
                new = "/calendrier/"
            elif base in PAGES:
                new = PAGES[base]
            else:
                raise SystemExit(f"lien inconnu {href} dans {page_name}")
        elif href.startswith("mailto:contact@leadperformant.com"):
            new = "mailto:" + EMAIL
        LINKLOG.append((page_name, text.strip()[:70], new))
        attrs = attrs.replace(hm.group(0), f'href="{new}"')
        return f"<a{attrs}>{inner}</a>"
    s = re.sub(r"<a\b([^>]*)>(.*?)</a>", rep, s, flags=re.S)
    return s.replace("contact@leadperformant.com", EMAIL)

def strip_shell(src):
    s = src[src.index("<x-dc>") + 6: src.rindex("</x-dc>")]
    s = re.sub(r"<helmet>.*?</helmet>", "", s, flags=re.S)
    s = re.sub(r"<!--(?!\[).*?-->", "", s, flags=re.S)
    return s

def rename_classes(s):
    def rc(m):
        cls = m.group(1).split()
        cls = ["lpb" if c == "btn" else "lpb-" + c[4:] if c.startswith("btn-") else c for c in cls]
        return 'class="' + " ".join(cls) + '"'
    return re.sub(r'class="([^"]*)"', rc, s)

def main_inner(s):
    """Contenu entre l'en-tête et le pied de page, sans la balise <main> de la maquette."""
    a = re.search(r'<dc-import name="Header"[^>]*></dc-import>', s)
    b = re.search(r'<dc-import name="Footer"[^>]*></dc-import>', s)
    inner = s[a.end(): b.start()].strip()
    m = re.match(r"<main([^>]*)>(.*)</main>$", inner, re.S)
    if not m:
        raise SystemExit("main introuvable")
    attrs = re.sub(r'\s*id="contenu"', "", m.group(1))
    return attrs, m.group(2)

def add_section_id(s, heading_contains, sid):
    i = s.find(heading_contains)
    if i < 0:
        raise SystemExit("titre introuvable " + heading_contains)
    j = s.rfind("<section", 0, i)
    tag_end = s.index(">", j)
    tag = s[j:tag_end]
    if " id=" in tag:
        return s
    return s[:j] + '<section id="' + sid + '"' + s[j + 8:]

def wire_video(s, button_label, yt, title):
    """Le bouton de lecture de la maquette charge la vidéo YouTube sur place, au clic seulement."""
    i = s.find(f'aria-label="{button_label}"')
    if i < 0:
        raise SystemExit("bouton vidéo introuvable " + button_label)
    b = s.rfind("<button", 0, i)
    s = s[:b] + '<button data-play=""' + s[b + 7:]
    # conteneur : l'ancêtre div ou figure le plus proche du bouton
    cands = sorted([m.start() for m in re.finditer(r"<(div|figure)\b", s[:b])], reverse=True)
    for k in cands:
        tag = "div" if s.startswith("<div", k) else "figure"
        seg = s[k:b]
        if len(re.findall(r"<%s\b" % tag, seg)) > len(re.findall(r"</%s>" % tag, seg)):
            tag_end = s.index(">", k)
            return s[:tag_end] + f' data-yt="{yt}" data-title="{esc(title)}"' + s[tag_end:]
    raise SystemExit("conteneur vidéo introuvable")

def convert(name, ctx):
    src = (MAQ / name).read_text(encoding="utf-8")
    s = strip_shell(src)
    s = rename_classes(s)
    mattrs, inner = main_inner(s)
    inner = map_links(inner, ctx, name)
    return mattrs, inner

