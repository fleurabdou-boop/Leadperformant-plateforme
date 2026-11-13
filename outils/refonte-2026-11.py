"""Applique la refonte de novembre 2026 au miroir local du site.
Usage : python3 transform.py <dossier_site> <dossier_build>"""
import re, sys, os, json, shutil

SITE, BUILD = sys.argv[1], sys.argv[2]
V = "20261113"
ART = {
    "1544531586-fde5298cdd40": "voix",      # pôle Art oratoire
    "1542744173-8e7e53415bb0": "reseau",    # pôle Leadership
    "1560439513-74b037a25d84": "embleme",   # Éloquence Élite
    "1551836022-4c4c79ecde51": "dialogue",  # Levier professionnel
    "1758691736490-03d39c292d7a": "onde",   # Parole d'Impact
    "1517245386807-bb43f82c33c4": "grille", # Leadership relationnel
    "1758518731462-d091b0b4ed0d": "boussole", # Leadership stratégique
    "1587825140708-dfaf72ae4b04": "embleme",
    "1629327896333-7ecec1515ae5": "onde",
    "1475721027785-f74eccf877e2": "papier-1",
    "1710360683896-e0506fb3579a": "papier-2",
    "1582192730841-2a682d7375f9": "tribune",
    "1557804506-669a67965ba0": "grille",
    "1540575467063-178a50c2df87": "amphi",
    "1531058020387-3be344556be6": "tribune",
    "1758270704587-43339a801396": "amphi",
    "1758691736483-5f600b509962": "papier-3",
    "1573165662973-4ab3cf3d3508": "papier-2",
}
UNS = re.compile(r'https://images\.unsplash\.com/photo-([0-9a-f]+-[0-9a-f]+)\?[^"\s\\]*')

def art_url(m):
    return "/assets/lp/art/%s.svg" % ART.get(m.group(1), "embleme")

def fix_imgs(html):
    # <img ... unsplash ...> : source locale, sans srcset/sizes, image décorative
    def one(m):
        tag = m.group(0)
        if "images.unsplash.com" not in tag:
            return tag
        tag = re.sub(r'\s(srcset|sizes)="[^"]*"', "", tag)
        tag = UNS.sub(art_url, tag)
        tag = re.sub(r'\salt="[^"]*"', ' alt=""', tag)
        return tag
    html = re.sub(r"<img\b[^>]*>", one, html)
    return UNS.sub(art_url, html)  # restes : JSON window.LP, styles

NAV_CONTACT = '<div class="nav-item "><a class="nav-link" href="/contact/">Contact</a></div>'
NAV_CONTACT_CUR = '<div class="nav-item "><a class="nav-link" href="/contact/" aria-current="page">Contact</a></div>'

def common(html):
    html = fix_imgs(html)
    html = html.replace(NAV_CONTACT, "").replace(NAV_CONTACT_CUR, "")
    html = re.sub(r'/assets/lp/site\.css(\?v=\d+)?', "/assets/lp/site.css?v=" + V, html)
    html = re.sub(r'/assets/lp/site\.js(\?v=\d+)?', "/assets/lp/site.js?v=" + V, html)
    html = re.sub(r'/assets/lp/cms\.js(\?v=\d+)?', "/assets/lp/cms.js?v=" + V, html)
    return html

changed = []
for root, _, files in os.walk(SITE):
    for fn in files:
        if not fn.endswith(".html"):
            continue
        p = os.path.join(root, fn)
        src = open(p, encoding="utf-8").read()
        out = common(src)
        rel = os.path.relpath(p, SITE)
        if rel == "index.html":
            main = open(os.path.join(BUILD, "accueil-main.html"), encoding="utf-8").read()
            out = re.sub(r'<main id="contenu">.*?</main>', lambda _: main.strip(), out, flags=re.S)
            # FAQ structurée alignée sur les quatre questions affichées
            faq = re.findall(r"<details><summary>(.*?)</summary><p>(.*?)</p></details>", main, flags=re.S)
            clean = lambda s: re.sub(r"<[^>]+>", "", s).replace("&nbsp;", " ").replace("&#x27;", "'").replace("&amp;", "&")
            data = {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [
                {"@type": "Question", "name": clean(q), "acceptedAnswer": {"@type": "Answer", "text": clean(a)}} for q, a in faq]}
            out = re.sub(r'<script type="application/ld\+json">\{"@context": "https://schema.org", "@type": "FAQPage".*?</script>',
                         lambda _: '<script type="application/ld+json">' + json.dumps(data, ensure_ascii=False) + "</script>", out, flags=re.S)
            out = out.replace('<title>Formation prise de parole en public et leadership | Lead Performant, Paris</title>',
                              '<title>Lead Performant | Art oratoire et leadership, formation et coaching à Paris</title>')
            out = out.replace('<link rel="preload" href="/assets/lp/fonts/manrope-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>',
                              '<link rel="preload" href="/assets/lp/fonts/manrope-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>\n<link rel="preload" href="/assets/lp/fonts/cormorant-garamond-latin-500-italic.woff2" as="font" type="font/woff2" crossorigin>')
            out = out.replace('<script src="/assets/lp/accueil.js?v=20261105" defer></script>', "")
            out = out.replace('<meta name="theme-color" content="#21406A">', '<meta name="theme-color" content="#0B1A33">')
        if out != src:
            open(p, "w", encoding="utf-8").write(out)
            changed.append(rel)

# Feuille de style : ajout du bloc de refonte (une seule fois)
css_p = os.path.join(SITE, "assets/lp/site.css")
css = open(css_p, encoding="utf-8").read()
marker = "/* =====================================================================\n   Refonte visuelle — novembre 2026"
if marker in css:
    css = css[:css.index(marker)].rstrip() + "\n"
css += open(os.path.join(BUILD, "refonte-2026-11.css"), encoding="utf-8").read()
open(css_p, "w", encoding="utf-8").write(css)
changed.append("assets/lp/site.css")

# Contenus du CMS (articles) : les anciennes images de banque d'images deviennent les œuvres de la marque
for js in ("assets/lp/cms.js", "assets/lp/seed.js"):
    p = os.path.join(SITE, js)
    s = open(p, encoding="utf-8").read()
    o = s
    if js.endswith("cms.js") and "LP_ART" not in s:
        o = s.replace("  function asset(v) {\n    if (!v",
            "  var LP_ART = " + json.dumps(ART) + ";\n"
            "  function asset(v) {\n"
            "    var um = v && /images\\.unsplash\\.com\\/photo-([0-9a-f]+-[0-9a-f]+)/.exec(v);\n"
            "    if (um) return \"/assets/lp/art/\" + (LP_ART[um[1]] || \"papier-1\") + \".svg\";\n"
            "    if (!v", 1)
    else:
        o = UNS.sub(art_url, s)
    if o != s:
        open(p, "w", encoding="utf-8").write(o)
        changed.append(js)

# Œuvres graphiques
dst = os.path.join(SITE, "assets/lp/art")
os.makedirs(dst, exist_ok=True)
for fn in os.listdir(os.path.join(BUILD, "out/art")):
    shutil.copy(os.path.join(BUILD, "out/art", fn), os.path.join(dst, fn))
    changed.append("assets/lp/art/" + fn)
print("\n".join(sorted(set(changed))))
left = [os.path.relpath(os.path.join(r, f), SITE) for r, _, fs in os.walk(SITE) for f in fs
        if f.endswith((".html", ".js", ".css")) and "images.unsplash.com" in open(os.path.join(r, f), encoding="utf-8", errors="ignore").read()]
print("RESTE unsplash :", left)
