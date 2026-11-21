"""Pages existantes conservées (nouvel en-tête et pied de page, nouvelles typographies),
feuille de style, script commun, polices, images de partage, envoi des formulaires, plan du site."""
import re, json, shutil, pathlib, datetime
from lpbuild import *  # noqa

FONT_PRELOAD_OLD = re.compile(r'<link rel="preload" href="/assets/lp/fonts/[^"]+" as="font" type="font/woff2" crossorigin>\n?')
FONT_PRELOAD_NEW = ('<link rel="preload" href="/assets/lp/fonts/newsreader-opsz-normal.woff2" as="font" type="font/woff2" crossorigin>\n'
                    '<link rel="preload" href="/assets/lp/fonts/instrument-sans-wght-normal.woff2" as="font" type="font/woff2" crossorigin>\n')

def active_for(rel):
    if rel.startswith(("formations/", "entreprises/", "ecoles/", "collectivites-institutions/", "laboratoire")):
        return "pole1"
    return ""

def refit(rel, s):
    n0 = len(s)
    s, k = re.subn(r'<header class="site-header" id="top">.*?</header>', header(active_for(rel)), s, count=1, flags=re.S)
    if not k:
        raise SystemExit("en-tête introuvable : " + rel)
    s = s.replace('<div class="nav-backdrop" aria-hidden="true"></div>', "")
    s, k = re.subn(r'<footer class="site-footer">.*?</footer>', footer(), s, count=1, flags=re.S)
    if not k:
        raise SystemExit("pied de page introuvable : " + rel)
    s = re.sub(r"\?v=20261113[a-z]?", "?v=" + V, s)
    first = [True]
    def pre(m):
        if first[0]:
            first[0] = False
            return FONT_PRELOAD_NEW
        return ""
    s = FONT_PRELOAD_OLD.sub(pre, s)
    s = re.sub(r'href="/(art-oratoire|leadership)/', 'href="/art-oratoire-leadership/', s)
    s = re.sub(r'href="/art-oratoire-leadership/#coaching"', 'href="/art-oratoire-leadership/#accompagnements"', s)
    s = s.replace('<meta name="theme-color" content="#0F2340">', '<meta name="theme-color" content="#0B1A33">')
    return s

LEGACY = ["entreprises/index.html", "ecoles/index.html", "collectivites-institutions/index.html", "formations/index.html",
          "formations/eloquence-elite/index.html", "formations/eloquence-levier-professionnel/index.html", "formations/parole-d-impact/index.html",
          "formations/leadership-relationnel/index.html", "formations/leadership-strategique/index.html", "laboratoire.html",
          "mentions-legales/index.html", "confidentialite/index.html"]
for rel in LEGACY:
    s = refit(rel, (LIVE / rel).read_text(encoding="utf-8"))
    if rel == "mentions-legales/index.html":
        s, k = re.subn(r"<p>Les polices Cormorant Garamond et Manrope.*?</ul>",
                       "<p>Les polices Newsreader et Instrument Sans sont distribuées sous la licence SIL Open Font License et hébergées sur le serveur du site.</p>"
                       "<p>Les photographies représentent des interventions réelles d'Abdou Fleur : conférences, ateliers, plateaux de télévision et missions diplomatiques. "
                       "Sur les photographies de groupe, les visages des autres personnes sont floutés. Les vignettes des vidéos sont issues des émissions concernées "
                       "(Global Africa Télésud, Parlons Médiation). Toute reproduction sans autorisation écrite préalable est interdite.</p>", s, count=1, flags=re.S)
        if not k:
            raise SystemExit("mentions légales : paragraphe des crédits introuvable")
    if rel == "confidentialite/index.html":
        add = ("<h2>3 bis. Boussole relationnelle</h2><p>La version courte de la Boussole relationnelle calcule votre résultat dans votre navigateur. "
               "Si vous demandez à le recevoir, votre courriel, la structure facultative que vous indiquez, vos réponses et le résultat sont transmis au cabinet, "
               "qui vous adresse la copie demandée. Aucun nom de personne n'est demandé. Ces données servent uniquement à vous répondre et, si vous l'avez coché, "
               "à vous adresser les ressources de Lead Performant au plus une fois par mois ; elles sont conservées au plus trois ans après le dernier échange.</p>")
        s, k = re.subn(r"(<h2>4\. Cookies, photographies et mesure d'audience</h2>)", add + r"\1", s, count=1)
        if not k:
            raise SystemExit("confidentialité : section 4 introuvable")
        s, k = re.subn(r"<p>Les illustrations du site sont des créations graphiques hébergées sur le serveur du site&nbsp;: leur affichage ne fait appel à aucun service tiers\.</p>",
                       "<p>Les photographies et illustrations du site sont hébergées sur son serveur : leur affichage ne fait appel à aucun service tiers. "
                       "Sur les photographies de groupe, les visages des autres personnes sont floutés.</p>", s, count=1)
        if not k:
            raise SystemExit("confidentialité : paragraphe des illustrations introuvable")
    write(rel, s)

# ------------------------------------------------------------------ feuille de style et script communs
css0 = (LIVE / "assets/lp/site.css").read_text(encoding="utf-8").rstrip("\n")
# les noms des anciennes polices, encore cités par les styles de la plateforme, pointent désormais vers les nouvelles
css0, n1 = re.subn(r'url\("fonts/cormorant-garamond-latin-\d+-normal\.woff2"\)', 'url("fonts/newsreader-opsz-normal.woff2")', css0)
css0, n2 = re.subn(r'url\("fonts/cormorant-garamond-latin-\d+-italic\.woff2"\)', 'url("fonts/newsreader-opsz-italic.woff2")', css0)
css0, n3 = re.subn(r'url\("fonts/manrope-latin-\d+-normal\.woff2"\)', 'url("fonts/instrument-sans-wght-normal.woff2")', css0)
if (n1, n2, n3) != (2, 2, 4):
    raise SystemExit(f"polices : {n1} {n2} {n3}")
css = css0 + "\n" + (S2 / "theme.css").read_text(encoding="utf-8")
write("assets/lp/site.css", css)
shell = (S2 / "shell.js").read_text(encoding="utf-8")
shell = shell.replace("__HEADER__", json.dumps(header(""), ensure_ascii=False)).replace("__FOOTER__", json.dumps(footer(), ensure_ascii=False))
write("assets/lp/site.js", shell.rstrip("\n") + "\n" + (LIVE / "assets/lp/site.js").read_text(encoding="utf-8"))
# cms.js et seed.js inchangés : seule la version appelée change

# ------------------------------------------------------------------ polices
F = ROOT / "fonts"
for src, dst in [("fontsource-variable-newsreader-5.3.0/package/files/newsreader-latin-opsz-normal.woff2", "newsreader-opsz-normal.woff2"),
                 ("fontsource-variable-newsreader-5.3.0/package/files/newsreader-latin-opsz-italic.woff2", "newsreader-opsz-italic.woff2"),
                 ("fontsource-variable-instrument-sans-5.3.0/package/files/instrument-sans-latin-wght-normal.woff2", "instrument-sans-wght-normal.woff2")]:
    write("assets/lp/fonts/" + dst, (F / src).read_bytes())

# ------------------------------------------------------------------ images de partage
for n in ["partage-accueil-1200x630.jpg", "partage-pole-art-oratoire-leadership-1200x630.jpg", "partage-pole-mediation-gestion-conflits-1200x630.jpg"]:
    write("assets/lp/img/" + n, (ROOT / "photos/out/site" / n).read_bytes())

# ------------------------------------------------------------------ envoi des formulaires : type « boussole » et signature
import subprocess
php = subprocess.run(["git", "-C", str(ROOT / "leadperformant-plateforme"), "show", "refonte-design-2026-11:refonte/contact/envoyer.php"],
                     capture_output=True, text=True, check=True).stdout
if "boussole" not in php:
    php = php.replace("/* Lead Performant — réception des demandes (formulaire Contact et parcours Parole d'Impact).",
                      "/* Lead Performant — réception des demandes (formulaire Contact, parcours Parole d'Impact, Boussole relationnelle).")
    php = php.replace("$nom     = ligne(champ('nom', 120));",
                      "$typeBrut = champ('type', 60);\n$nom     = ligne(champ('nom', 120));\nif ($typeBrut === 'boussole' && $nom === '') $nom = 'Répondant Boussole relationnelle';")
    php = php.replace("$typeBrut = champ('type', 60);\n$type = $typeBrut === 'parcours-parole-d-impact'",
                      "$type = $typeBrut === 'boussole' ? 'Boussole relationnelle' : ($typeBrut === 'parcours-parole-d-impact'")
    php = php.replace(": 'Formulaire de contact'));", ": 'Formulaire de contact')));")
    boussole_ar = (
        "if ($typeBrut === 'boussole') {\n"
        "  $ar = \"Bonjour,\\n\\nVoici la copie de votre premier diagnostic, réalisé avec la Boussole relationnelle (version courte) sur leadperformant.com.\\n\\n\"\n"
        "      . $message . \"\\n\\n\"\n"
        "      . \"Ce premier diagnostic est indicatif. Il ne remplace ni l'évaluation réglementaire des risques psychosociaux, ni le DUERP, ni une enquête administrative ou disciplinaire.\\n\\n\"\n"
        "      . \"Pour en parler en toute confidentialité : répondez simplement à ce message, ou appelez le 06 62 57 92 74.\\n\\n\"\n"
        "      . \"Bien cordialement,\\n\\nAbdou Fleur\\nLead Performant\\nMédiation et gestion des conflits\\nwww.leadperformant.com\\n\";\n"
        "  $entetesAR = \"MIME-Version: 1.0\\r\\nContent-Type: text/plain; charset=UTF-8\\r\\nContent-Transfer-Encoding: 8bit\\r\\n\"\n"
        "             . \"From: \" . $enc('Lead Performant') . \" <$EXPEDITEUR>\\r\\nReply-To: $DESTINATAIRE\\r\\n\";\n"
        "  @mail($email, $enc('Votre premier diagnostic · Boussole relationnelle · Lead Performant'), $ar, $entetesAR, '-f' . $EXPEDITEUR);\n"
        "  fin(true);\n"
        "}\n")
    php = php.replace("if ($typeBrut === 'diagnostic') {", boussole_ar + "if ($typeBrut === 'diagnostic') {", 1)
    php = php.replace('"Bien cordialement,\\n\\nLead Performant\\nArt oratoire et leadership\\nwww.leadperformant.com\\n";',
                      '"Bien cordialement,\\n\\nLead Performant\\nParler pour convaincre. Dialoguer pour apaiser.\\nwww.leadperformant.com\\n";')
    php = php.replace("Nous vous répondons sous deux jours ouvrés pour convenir d'un premier échange de trente minutes, gratuit et sans engagement.",
                      "Nous vous répondons sous deux jours ouvrés pour convenir d'un premier échange de trente minutes, gratuit et sans engagement. Votre demande est traitée en toute confidentialité.")
for must in ["'boussole' ? 'Boussole relationnelle'", "Répondant Boussole", "Votre premier diagnostic · Boussole", "Dialoguer pour apaiser"]:
    if must not in php:
        raise SystemExit("envoyer.php : " + must)
write("contact/envoyer.php", php)

# ------------------------------------------------------------------ plan du site
today = "2026-11-20"
urls = ["/", "/art-oratoire-leadership/", "/mediation-gestion-des-conflits/", "/mediation-gestion-des-conflits/boussole-relationnelle/",
        "/interventions/", "/ressources/", "/fondateur/", "/contact/"]
import lpbuild as LB  # noqa: E402  (liste des articles, fournie par pages.py)
urls += [f"/ressources/{a['slug']}/" for a in LB.ARTS]
urls += ["/formations/"] + [f"/formations/{s}/" for s, _ in PROGS] + ["/entreprises/", "/ecoles/", "/collectivites-institutions/", "/laboratoire.html",
         "/calendrier/", "/boutique/", "/mentions-legales/", "/confidentialite/", "/cgv/", "/reglement-interieur/"]
sm = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n' + "".join(
    f"  <url><loc>{SITE}{u}</loc><lastmod>{today}</lastmod></url>\n" for u in urls) + "</urlset>\n"
write("sitemap.xml", sm)

# ------------------------------------------------------------------ pages de service (404, confirmation, erreur d'envoi)
def service(path, title, eyebrow, h1, text, cta_label, cta_href, robots=True):
    body = ('<main id="contenu" class="lpx"><section style="background: #0B1A33; color: #F6F3EC; padding: clamp(80px, 10vw, 140px) 0">'
            '<div style="max-width: 1280px; margin: 0 auto; padding: 0 clamp(20px, 5vw, 64px); display: flex; flex-direction: column; gap: 26px; max-width: 900px">'
            f'<p class="rise" style="font-size: 13px; letter-spacing: 0.22em; text-transform: uppercase; color: #C9A45C; font-weight: 500">{eyebrow}</p>'
            f'<h1 class="rise d1" style="font-family: \'Newsreader\', Georgia, serif; font-weight: 380; font-size: clamp(40px, 5vw, 72px); line-height: 1.04; letter-spacing: -0.02em">{h1}</h1>'
            f'<p class="rise d2" style="font-size: 18px; line-height: 1.65; color: #D9D2C3; max-width: 640px">{text}</p>'
            '<div class="rise d3" style="display: flex; flex-wrap: wrap; gap: 14px 24px; align-items: center">'
            f'<a class="lpb lpb-gold" href="{cta_href}" style="display: inline-flex; align-items: center; gap: 12px; min-height: 54px; padding: 0 28px; background: #C9A45C; color: #0B1A33; text-decoration: none; font-size: 15.5px; font-weight: 600">{cta_label} <span aria-hidden="true">→</span></a>'
            '<a class="ulink" href="/art-oratoire-leadership/" style="color: #F6F3EC; font-size: 15px">Art oratoire et leadership</a>'
            '<a class="ulink" href="/mediation-gestion-des-conflits/" style="color: #F6F3EC; font-size: 15px">Médiation et gestion des conflits</a>'
            '</div></div></section></main>')
    html_ = page(path, title, text, typo(body))
    if not robots:
        html_ = html_.replace("<title>", '<meta name="robots" content="noindex, follow">\n<title>', 1)
    return html_
write("404.html", service("/404.html", "Page introuvable | Lead Performant", "Erreur 404", "Cette page n'existe pas.",
                          "Elle a peut-être été déplacée lors de la réunion de nos deux pôles. Reprenez depuis l'accueil, ou écrivez-nous si vous cherchiez un contenu précis.",
                          "Revenir à l'accueil", "/", robots=False))
write("merci/index.html", service("/merci/", "Demande envoyée | Lead Performant", "Demande transmise", "Votre demande est envoyée.",
                                  f"Merci. Nous vous répondons sous deux jours ouvrés. Pour une demande urgente, appelez le {TEL}.", "Revenir à l'accueil", "/", robots=False))
write("erreur-envoi/index.html", service("/erreur-envoi/", "Envoi impossible | Lead Performant", "Envoi interrompu", "Votre demande n'a pas pu être envoyée.",
                                         f"Vérifiez que les champs obligatoires sont remplis, puis réessayez. Vous pouvez aussi écrire à {EMAIL} ou appeler le {TEL}.",
                                         "Revenir au formulaire", "/contact/", robots=False))

# ------------------------------------------------------------------ fichiers serveur : gabarit PHP et règles de réécriture
SO = ROOT / "server_orig"
lay = (SO / "layout.php").read_text(encoding="utf-8")
a = lay.index("function nav_html(")
b = lay.index("function page_start(")
new_funcs = ("function nav_html(string $actif = ''): string {\n"
             "  // en-tête commun de la maison mère à deux pôles (novembre 2026)\n"
             "  $html = <<<'HTML'\n" + header("") + "\nHTML;\n"
             "  $k = ['calendrier' => 'sessions', 'sessions' => 'sessions', 'inscription' => 'sessions', 'boutique' => 'boutique'][$actif] ?? '';\n"
             "  return $k === '' ? $html : str_replace('data-k=\"' . $k . '\"', 'data-k=\"' . $k . '\" aria-current=\"page\"', $html);\n}\n\n"
             "function footer_html(): string {\n  return <<<'HTML'\n" + footer() + "\nHTML;\n}\n\n")
lay2 = lay[:a] + new_funcs + lay[b:]
reps = [("/* Gabarit des pages publiques : reprend l'en-tête, le menu et le pied de page du site existant (mêmes feuilles de style et scripts). */",
         "/* Gabarit des pages publiques : en-tête, menu et pied de page communs du site à deux pôles (mêmes feuilles de style et scripts). */"),
        ('<meta name="theme-color" content="#21406A">', '<meta name="theme-color" content="#0B1A33">'),
        ("<link rel=\"preload\" href=\"/assets/lp/fonts/cormorant-garamond-latin-500-normal.woff2\" as=\"font\" type=\"font/woff2\" crossorigin>",
         "<link rel=\"preload\" href=\"/assets/lp/fonts/newsreader-opsz-normal.woff2\" as=\"font\" type=\"font/woff2\" crossorigin>"),
        ("<link rel=\"preload\" href=\"/assets/lp/fonts/manrope-latin-400-normal.woff2\" as=\"font\" type=\"font/woff2\" crossorigin>",
         "<link rel=\"preload\" href=\"/assets/lp/fonts/instrument-sans-wght-normal.woff2\" as=\"font\" type=\"font/woff2\" crossorigin>"),
        ("/assets/lp/site.css?v=20261113", "/assets/lp/site.css?v=" + V),
        ("/assets/lp/site.js?v=20261113", "/assets/lp/site.js?v=" + V)]
for o, n in reps:
    if o not in lay2:
        raise SystemExit("layout.php : " + o[:60])
    lay2 = lay2.replace(o, n)
if "\nHTML;" in header("") or "\nHTML;" in footer():
    raise SystemExit("marqueur HTML dans le gabarit")
(ROOT / "leadperformant-plateforme/_lp/layout.php").write_text(lay2, encoding="utf-8")
SERVER = {"_lp/layout.php": lay2.encode("utf-8")}

ht = (SO / ".htaccess").read_text(encoding="utf-8")
old = "# 3. Ancienne rubrique Médiation : redirigée vers le pôle Leadership\nRewriteRule ^mediation/?$ https://www.leadperformant.com/leadership/ [L,R=301]\n"
new = ("# 3. Ancienne rubrique Médiation : redirigée vers le pôle Médiation et gestion des conflits\n"
       "RewriteRule ^mediation/?$ https://www.leadperformant.com/mediation-gestion-des-conflits/ [L,R=301]\n"
       "# 3 bis. Maison mère à deux pôles (novembre 2026) : les anciens pôles Art oratoire et Leadership sont réunis\n"
       "RewriteRule ^art-oratoire/?$ https://www.leadperformant.com/art-oratoire-leadership/ [L,R=301]\n"
       "RewriteRule ^leadership/?$ https://www.leadperformant.com/art-oratoire-leadership/ [L,R=301]\n")
if old not in ht:
    raise SystemExit(".htaccess : règle 3 introuvable")
ht2 = ht.replace(old, new)
old2 = '  ExpiresByType image/svg+xml "access plus 30 days"\n'
new2 = old2 + '  ExpiresByType image/webp "access plus 30 days"\n  ExpiresByType image/jpeg "access plus 30 days"\n  ExpiresByType font/woff2 "access plus 1 year"\n'
if old2 not in ht2:
    raise SystemExit(".htaccess : expires")
ht2 = ht2.replace(old2, new2)
SERVER[".htaccess"] = ht2.encode("utf-8")
import lpbuild as LB2
LB2.SERVER = SERVER
pathlib.Path(ROOT / "server_new").mkdir(exist_ok=True)
for k, v in SERVER.items():
    (ROOT / "server_new" / k.replace("/", "__")).write_bytes(v)
