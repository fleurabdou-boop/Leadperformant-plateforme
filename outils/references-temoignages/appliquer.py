#!/usr/bin/env python3
"""Références et témoignages ; Inscription aux sessions et Boutique dans l'en-tête (20/11/2026).

Applique les modifications aux fichiers publiés (copie de public_html passée en argument) :
- en-tête commun : « Inscription aux sessions » et « Boutique » sortent de la navigation et deviennent
  deux rubriques de service, dans la barre supérieure sur ordinateur et dans un bandeau à deux cases sur mobile ;
- pied de page : lien « Références et témoignages » ;
- accueil et pages des pôles : section Références et témoignages (masquée tant que rien n'est publié) ;
- feuille de style et script communs, plan du site, numéro de version des ressources.
Idempotent : relancer le script ne modifie rien de plus.
Usage : python3 appliquer.py <dossier public_html>
"""
import json, pathlib, sys

ROOT = pathlib.Path(sys.argv[1])
HERE = pathlib.Path(__file__).resolve().parent
V_OLD, V_NEW = "?v=20261120d", "?v=20261120f"

CAL = ('<svg class="lp2-ico" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1.65" y="2.9" width="12.7" height="11.45" fill="none" stroke="#C9A45C" stroke-width="1.3"></rect>'
       '<path d="M1.65 6.4h12.7M5 1.1v3.4M11 1.1v3.4" fill="none" stroke="#C9A45C" stroke-width="1.3"></path></svg>')
BAG = ('<svg class="lp2-ico" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.6 5.4h10.8l-.9 9.25H3.5z" fill="none" stroke="#C9A45C" stroke-width="1.3" stroke-linejoin="round"></path>'
       '<path d="M5.6 7.2V4.3a2.4 2.4 0 0 1 4.8 0v2.9" fill="none" stroke="#C9A45C" stroke-width="1.3"></path></svg>')
SVC = ('<nav class="lp2-svc" aria-label="Inscriptions et boutique"><a href="/calendrier/" data-k="sessions">' + CAL + '<span>Inscription aux sessions</span></a>'
       '<a href="/boutique/" data-k="boutique">' + BAG + '<span>Boutique</span></a></nav>')
STRIP = ('<nav class="lp2-svcbar" aria-label="Inscriptions et boutique"><a href="/calendrier/" data-k="sessions">' + CAL + '<span class="lp2-svc-l">Inscription aux sessions</span><span class="lp2-svc-s">Inscriptions</span></a>'
         '<a href="/boutique/" data-k="boutique">' + BAG + '<span>Boutique</span></a></nav>')

REPL = [
    ('<div class="lp2-act"><a class="lp2-tel"', '<div class="lp2-act">' + SVC + '<a class="lp2-tel"'),
    ('</span></button></div></div><nav class="lp2-nav" aria-label="Navigation principale">', '</span></button></div></div>' + STRIP + '<nav class="lp2-nav" aria-label="Navigation principale">'),
    ('<li><a href="/calendrier/" data-k="sessions">Sessions et inscriptions</a></li><li><a href="/boutique/" data-k="boutique">Boutique</a></li>', ''),
    ('<li><a href="/calendrier/" data-k="sessions">Sessions et inscriptions<span class="lp2-arr" aria-hidden="true">→</span></a></li>'
     '<li><a href="/boutique/" data-k="boutique">Boutique<span class="lp2-arr" aria-hidden="true">→</span></a></li>', ''),
    ('<a href="/fondateur/">Le fondateur</a><a href="/boutique/">Boutique</a>', '<a href="/fondateur/">Le fondateur</a><a href="/references/">Références et témoignages</a><a href="/boutique/">Boutique</a>'),
]
DONE = 'class="lp2-svc"'

def section(pole, eyebrow):
    return ('\n<section class="lpr-band" data-lp-refs="' + pole + '" aria-labelledby="refs-' + pole + '" hidden>\n<div class="lpr-in">\n'
            '<div class="lpr-head"><div class="lpr-head-t"><p class="lpr-eyebrow">' + eyebrow + '</p>'
            '<h2 id="refs-' + pole + '" class="lpr-h2">Ils nous ont fait confiance. <em>Ils en témoignent.</em></h2></div>'
            '<a class="ulink lpr-more" href="/references/">Toutes les références <span aria-hidden="true">→</span></a></div>\n'
            '<ul class="lpr-strip" data-lp-logos aria-label="Organisations qui ont fait confiance à Lead Performant" hidden></ul>\n'
            '<div class="lpr-tems" data-lp-tems hidden></div>\n</div>\n</section>\n')

PLACES = {
    'index.html': ('<section aria-labelledby="echange"', section('tous', 'Références et témoignages')),
    'art-oratoire-leadership/index.html': ('<section aria-labelledby="faq1"', section('p1', 'Références · Art oratoire et leadership')),
    'mediation-gestion-des-conflits/index.html': ('<section aria-labelledby="faq2"', section('p2', 'Références · Médiation et gestion des conflits')),
}

def header_footer(s, escaped=False):
    n = 0
    for a, b in REPL:
        if escaped:
            a, b = json.dumps(a, ensure_ascii=False)[1:-1], json.dumps(b, ensure_ascii=False)[1:-1]
        c = s.count(a)
        if c:
            s = s.replace(a, b); n += c
    return s, n

changed = []
def write(p, s, old):
    if s != old:
        p.write_text(s, encoding="utf-8"); changed.append(str(p.relative_to(ROOT)))

# 1. Pages statiques et gabarit PHP
for p in sorted(list(ROOT.rglob("*.html")) + [ROOT / "_lp/layout.php"]):
    rel = p.relative_to(ROOT).as_posix()
    if rel.startswith(("admin/", "_lp/sauvegarde", "_plateforme_src/")):
        continue
    old = s = p.read_text(encoding="utf-8")
    if 'class="lp2-h"' in s and DONE not in s:
        s, n = header_footer(s)
        assert DONE in s and 'data-k="sessions">Sessions et inscriptions' not in s, rel
    s = s.replace(V_OLD, V_NEW)
    if rel in PLACES and 'data-lp-refs=' not in s:
        anchor, html = PLACES[rel]
        assert s.count(anchor) == 1, rel
        s = s.replace(anchor, html + anchor)
    write(p, s, old)

# 2. Script commun : en-tête des pages anciennes, rendu des références
js = ROOT / "assets/lp/site.js"
old = s = js.read_text(encoding="utf-8")
if DONE.replace('"', '\\"') not in s:
    s, n = header_footer(s, escaped=True)
    assert n == 5, n
if "data-lp-refs" not in s:
    s = s.rstrip("\n") + "\n" + (HERE / "references.js").read_text(encoding="utf-8")
write(js, s, old)

# 3. Feuille de style commune
css = ROOT / "assets/lp/site.css"
old = s = css.read_text(encoding="utf-8")
if ".lp2-svcbar" not in s:
    s = s.rstrip("\n") + "\n" + (HERE / "references.css").read_text(encoding="utf-8")
write(css, s, old)

# 4. Plan du site
sm = ROOT / "sitemap.xml"
old = s = sm.read_text(encoding="utf-8")
if "/references/" not in s:
    s = s.replace("</urlset>", "  <url><loc>https://www.leadperformant.com/references/</loc><lastmod>2026-11-20</lastmod></url>\n</urlset>")
write(sm, s, old)

print(len(changed), "fichier(s) modifié(s)")
for c in changed:
    print(" ", c)
