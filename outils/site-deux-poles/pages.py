"""Construction des pages du site à deux pôles (exécuter : python3 pages.py)."""
import json, re, html, shutil, pathlib, hashlib
from lpbuild import *  # noqa
import lpbuild as B

R = lambda s: Raw(s)

class Raw(str):
    pass

# ------------------------------------------------------------------ mini-moteur des gabarits de la maquette
def lookup(ctx, path):
    v = ctx
    for k in path.split("."):
        v = v[k] if isinstance(v, dict) else getattr(v, k)
    return v

def subst(t, ctx):
    def attr(m):
        v = lookup(ctx, m.group(2))
        return (" " + v) if v else ""
    t = re.sub(r'\s(onClick)="\{\{\s*([\w.]+)\s*\}\}"', attr, t)
    def val(m):
        v = lookup(ctx, m.group(1))
        return v if isinstance(v, Raw) else esc(v)
    return re.sub(r"\{\{\s*([\w.]+)\s*\}\}", val, t)

def render(t, ctx):
    out, i = [], 0
    opener = re.compile(r"<(sc-for|sc-if)\b([^>]*)>")
    while True:
        m = opener.search(t, i)
        if not m:
            out.append(subst(t[i:], ctx)); break
        out.append(subst(t[i:m.start()], ctx))
        tag, depth, j = m.group(1), 1, m.end()
        tagre = re.compile(r"<(/?)%s\b[^>]*>" % tag)
        while depth:
            n = tagre.search(t, j)
            depth += -1 if n.group(1) else 1
            j = n.end()
        body = t[m.end():n.start()]
        if tag == "sc-for":
            lst = re.search(r'list="\{\{\s*([\w.]+)\s*\}\}"', m.group(2)).group(1)
            var = re.search(r'as="(\w+)"', m.group(2)).group(1)
            for it in lookup(ctx, lst):
                out.append(render(body, {**ctx, var: it}))
        else:
            if lookup(ctx, re.search(r'value="\{\{\s*([\w.]+)\s*\}\}"', m.group(2)).group(1)):
                out.append(render(body, ctx))
        i = j
    return "".join(out)

def maquette(name):
    src = (MAQ / name).read_text(encoding="utf-8")
    s = strip_shell(src)
    return rename_classes(s)

def must_replace(s, old, new, count=1):
    if old not in s:
        raise SystemExit("introuvable : " + old[:90])
    return s.replace(old, new, count)

def body_main(attrs, inner):
    return f'<main id="contenu" class="lpx"{attrs}>{inner}</main>'

def faq_ld(s):
    qa = []
    for m in re.finditer(r"<details\b.*?<summary[^>]*>(.*?)</summary>(.*?)</details>", s, re.S):
        q = anchor_text(m.group(1)).strip().rstrip("+−- ").strip()
        a = re.sub(r"\s+", " ", anchor_text(m.group(2))).strip()
        if q and a:
            qa.append({"@type": "Question", "name": q, "acceptedAnswer": {"@type": "Answer", "text": a}})
    return {"@context": "https://schema.org", "@type": "FAQPage", "mainEntity": qa} if qa else None

def crumbs_ld(items):
    return {"@context": "https://schema.org", "@type": "BreadcrumbList", "itemListElement": [
        {"@type": "ListItem", "position": i + 1, "name": n, "item": SITE + u} for i, (n, u) in enumerate(items)]}

def finish(s):
    """Images, typographie, contrôle des restes de gabarit."""
    s = fix_images(s)
    s = s.replace("figure-t-il", "figure\u2011t\u2011il")
    if re.search(r"\{\{|\.dc\.html|/_blob/|<sc-|<dc-", s):
        bad = re.search(r".{0,60}(\{\{|\.dc\.html|/_blob/|<sc-|<dc-).{0,60}", s).group(0)
        raise SystemExit("reste de gabarit : " + bad)
    return s

# ================================================================== Accueil
attrs, inner = convert("Main.dc.html", "accueil")
inner = wire_video(inner, "Lire l'extrait vidéo", "4cjI6wiW8gE", "Parlons Médiation avec Line Kleinebreil")
inner = re.sub(r">Extrait\s*·\s*1(\s|\u00a0)min<", ">Émission · lecture sur la page<", inner)
home = page("/", "Lead Performant | Art oratoire, leadership, médiation et gestion des conflits",
            "Lead Performant, maison de conseil et de formation à deux pôles : art oratoire et leadership, médiation et gestion des conflits. Paris, Île-de-France, toute la France et à distance.",
            finish(body_main(attrs, inner)), active="", og="partage-accueil-1200x630.jpg",
            jsonld=[{"@context": "https://schema.org", "@type": "WebSite", "@id": SITE + "/#site", "name": "Lead Performant", "url": SITE + "/", "inLanguage": "fr-FR", "publisher": {"@id": SITE + "/#org"}}])
write("index.html", home)

# ================================================================== Pôle I
attrs, inner = convert("Pole-art-oratoire-leadership.dc.html", "pole1")
live_ao = (LIVE / "art-oratoire/index.html").read_text(encoding="utf-8")
live_ld = (LIVE / "leadership/index.html").read_text(encoding="utf-8")
diag = re.search(r'<section class="section paper" id="diagnostic">.*?</section>', live_ao, re.S).group(0)
atel = re.search(r'<section class="section" id="atelier">.*?</section>', live_ld, re.S).group(0)
inner = add_section_id(inner, "Au plus près de votre situation", "accompagnements")
cut = inner.rfind("<section", 0, inner.find("L'essentiel, avant de commencer"))
legacy = '</div><div class="lp2-legacy">' + diag + atel + '</div><div class="lpx">'
body = f'<main id="contenu"{attrs}><div class="lpx">' + inner[:cut] + legacy + inner[cut:] + "</div></main>"
fq = faq_ld(inner)
p1 = page("/art-oratoire-leadership/", "Art oratoire et leadership | Formations et coaching · Lead Performant",
          "Pôle I de Lead Performant : cinq programmes de prise de parole et de leadership, ateliers en établissement, coaching individuel et de dirigeants. Paris, Île-de-France et à distance.",
          finish(body), active="pole1", og="partage-pole-art-oratoire-leadership-1200x630.jpg",
          jsonld=[x for x in [fq, crumbs_ld([("Accueil", "/"), ("Art oratoire et leadership", "/art-oratoire-leadership/")])] if x])
write("art-oratoire-leadership/index.html", p1)

# anciennes adresses du pôle I : redirection (301 par le serveur, et relais ici)
def stub(path, target):
    return ("<!DOCTYPE html>\n<html lang=\"fr\">\n<head>\n<meta charset=\"utf-8\">\n<title>Art oratoire et leadership · Lead Performant</title>\n"
            '<meta name="robots" content="noindex, follow">\n'
            f'<link rel="canonical" href="{SITE}{target}">\n<meta http-equiv="refresh" content="0; url={target}">\n'
            f'<script>location.replace("{target}" + location.search + location.hash);</script>\n</head>\n<body>\n'
            f'<p>Cette page a rejoint le pôle <a href="{target}">Art oratoire et leadership</a>.</p>\n</body>\n</html>\n')
write("art-oratoire/index.html", stub("/art-oratoire/", "/art-oratoire-leadership/"))
write("leadership/index.html", stub("/leadership/", "/art-oratoire-leadership/"))

# ================================================================== Pôle II
attrs, inner = convert("Pole-mediation-conflits.dc.html", "pole2")
inner = wire_video(inner, "Lire la présentation de Parlons Médiation", "kNrTnr7Nymk", "Présentation de Parlons Médiation")
inner = add_section_id(inner, "Trois domaines, un même fil", "expertises")
inner = add_section_id(inner, "Des compétences relationnelles qui restent", "formations")
inner = add_section_id(inner, "Faites le point en quatre minutes", "boussole")
fq = faq_ld(inner)
p2 = page("/mediation-gestion-des-conflits/", "Médiation et gestion des conflits | Lead Performant",
          "Pôle II de Lead Performant : médiation professionnelle et institutionnelle, gestion des conflits, communication non violente, diagnostic du climat relationnel. Sur devis, en toute confidentialité.",
          finish(body_main(attrs, inner)), active="pole2", og="partage-pole-mediation-gestion-conflits-1200x630.jpg",
          jsonld=[x for x in [fq, crumbs_ld([("Accueil", "/"), ("Médiation et gestion des conflits", "/mediation-gestion-des-conflits/")])] if x])
write("mediation-gestion-des-conflits/index.html", p2)

# ================================================================== Le fondateur
attrs, inner = convert("Fondateur.dc.html", "fondateur")
person = {"@context": "https://schema.org", "@type": "Person", "@id": SITE + "/fondateur/#abdou-fleur", "name": "Abdou FLEUR",
          "jobTitle": "Fondateur de Lead Performant", "description": "Juriste, médiateur, spécialisé en diplomatie multilatérale, président de Cap Éloquence.",
          "url": SITE + "/fondateur/", "image": SITE + "/assets/lp/img/portrait-officiel-costume-marine-4x5.webp",
          "worksFor": {"@id": SITE + "/#org"},
          "memberOf": [{"@type": "Organization", "name": "Cap Éloquence", "url": "https://www.capeloquence.net/"},
                       {"@type": "Organization", "name": "Institut International de la Médiation"}]}
fd = page("/fondateur/", "Abdou FLEUR, fondateur | Lead Performant",
          "Abdou FLEUR, juriste, médiateur, spécialisé en diplomatie multilatérale, président de Cap Éloquence et cofondateur de l'Institut International de la Médiation.",
          finish(body_main(attrs, inner)), active="fondateur", og="partage-accueil-1200x630.jpg",
          jsonld=[person, crumbs_ld([("Accueil", "/"), ("Le fondateur", "/fondateur/")])])
write("fondateur/index.html", fd)

# ================================================================== Interventions et médias
def blob(h):
    return "/_blob/" + h

ITEMS = [
    dict(cat="tv", where="Global Africa Télésud", title="« Aujourd'hui, le Sénégal a besoin d'un consensus fort »", desc="Chronique consacrée aux conditions d'un consensus national au Sénégal.", img=blob("87c90222429d83d815893c394ec0c1e2"), alt="Abdou FLEUR en chronique sur Global Africa Télésud", yt="W78c0MezTjM"),
    dict(cat="tv", where="Global Africa Télésud", title="« Le dialogue est rompu au Sénégal : au sein des familles et au sein des institutions »", desc="Chronique consacrée à la rupture du dialogue et aux conditions d'un nouveau consensus.", img=blob("3e2df487a19954d10310207b821d8039"), alt="Visuel de la chronique sur le dialogue au Sénégal", yt="vd8IvWaM9oY"),
    dict(cat="tv", where="Global Africa Télésud", title="Présentation de Parlons Médiation", desc="Abdou FLEUR et Michèle Guillaume-Hofnung présentent l'émission qu'ils ont cofondée, consacrée à la médiation et au vivre-ensemble.", img=blob("9cb97ce2668e9e81db4e49cc28bae181"), alt="Plateau de Parlons Médiation", yt="kNrTnr7Nymk"),
    dict(cat="tv", where="Global Africa Télésud · Bande à Carlyle", title="« 50 ans après, que reste-t-il de la CEDEAO ? »", desc="Analyse sur le plateau de Carlyle Gbei.", img=blob("2509ef0eef6a5e05c3c17c3799a71860"), alt="Abdou FLEUR sur le plateau de Global Africa Télésud"),
    dict(cat="tv", where="Global Africa Télésud · Bande à Carlyle", title="« Sénégal : Macky Sall à l’ONU, les élections locales vont-elles être reportées ? »", desc="Débat sur le plateau de Carlyle Gbei.", img=blob("919e69f5ac664f5b73e33139a093a3c1"), alt="Abdou FLEUR sur le plateau de Global Africa Télésud"),
    dict(cat="tv", where="Global Africa Télésud · 20 septembre", title="Face à Face avec Abdou FLEUR", desc="Entretien animé par Carlyle Gbei.", img=blob("ccff2c22add74d65a1ebd5c03b46e97b"), alt="Affiche de l’émission Face à Face", pos="82% 30%"),
    dict(cat="diplomatie", where="Changsha, Chine · juillet 2023", title="Forum international de la jeunesse sur la créativité et le patrimoine le long des routes de la soie", desc="Prise de parole devant la presse, à Changsha, ville membre du réseau des villes créatives de l’UNESCO.", img=blob("2b53a944f4fe7ae0ebfc85153558ee26"), alt="Abdou FLEUR répond à la presse à Changsha", pos="50% 45%"),
    dict(cat="diplomatie", where="UNESCO, Paris", title="Délégation du Sénégal en salle plénière", desc="Conseiller diplomatique de la délégation du Sénégal auprès de l’UNESCO.", img=blob("1e5937bec0ee5cfef7e81332966c7e00"), alt="Abdou FLEUR à la place du Sénégal en salle plénière de l’UNESCO", pos="60% 40%"),
    dict(cat="diplomatie", where="UNESCO, Paris · juillet 2025", title="Modérateur de la Journée internationale Cheikh Ahmadou Bamba", desc="Thème : la science, l’éducation et la culture de paix dans la pensée soufie de Cheikh Ahmadou Bamba.", img=blob("d96c25034b053bb4c8e78991ee4b9251"), alt="Abdou FLEUR au pupitre de l'UNESCO", yt="CSgttwBzNRU"),
    dict(cat="tv", where="Parlons Médiation · Global Africa", title="Journée internationale du vivre-ensemble en paix", desc="Numéro spécial consacré à la Journée internationale du vivre-ensemble en paix.", img=blob("3cc9876758d411b127dc3ad16e0f8f96"), alt="Invités du numéro spécial de Parlons Médiation", yt="INxLEo7Bgdk"),
    dict(cat="tv", where="Parlons Médiation · Global Africa", title="Parlons Médiation avec Doudou Diène", desc="Entretien avec Doudou Diène.", img=blob("6f9545ad34f79e01fb82502a8a34c553"), alt="Plateau de Parlons Médiation", yt="7C_gYzi3F44"),
    dict(cat="tv", where="Parlons Médiation · Global Africa", title="Parlons Médiation avec le Frère Gabriel Nissim", desc="Entretien avec le Frère Gabriel Nissim.", img=blob("faa212f87733103d9216995dd3a9b6e1"), alt="Michèle Guillaume-Hofnung et le Frère Gabriel Nissim", yt="RAQTBQk0uJ8"),
    dict(cat="tv", where="Parlons Médiation · Global Africa", title="Parlons Médiation avec Jimmy Fakourou", desc="Entretien avec Jimmy Fakourou.", img=blob("5834f592ec58056c4e5d9494b36393d6"), alt="Michèle Guillaume-Hofnung et Jimmy Fakourou", yt="k-n_LcLHy-g"),
    dict(cat="tv", where="Parlons Médiation · Global Africa", title="Parlons Médiation avec Line Kleinebreil", desc="Entretien avec Line Kleinebreil.", img=blob("da5e7b1504a6fe1eb0f2a7c48b63ccd8"), alt="Line Kleinebreil et Abdou FLEUR", yt="4cjI6wiW8gE"),
    dict(cat="conferences", where="Paris · octobre 2026", title="Prise de parole en conférence", desc="Intervention en conférence, à Paris.", img=blob("e2895cac47cce263ec894727e0cf9058"), alt="Abdou FLEUR en prise de parole", pos="50% 22%"),
    dict(cat="conferences", where="Paris · octobre 2026", title="Animation d'une soirée de débats", desc="Conduite d'une soirée de débats et de prises de parole.", img=blob("723bb4574be1fbcf8fa26b57fc8633d0"), alt="Animation d'une soirée de débats", pos="70% 50%"),
    dict(cat="conferences", where="Paris · octobre 2026", title="Échange avec le public", desc="Temps de questions et de dialogue avec la salle.", img=blob("b9c908fe1b17fe0e73b1774570ea9a75"), alt="Abdou FLEUR en échange avec le public", pos="50% 25%"),
    dict(cat="conferences", where="Patrimoine mondial", title="Intervention sur le patrimoine mondial africain", desc="Présentation d’un projet de décision du Comité du patrimoine mondial.", img=blob("c782ef9718be7e4d15f96e3c58983f1d"), alt="Abdou FLEUR présente un projet de décision du Comité du patrimoine mondial"),
    dict(cat="ateliers", where="Journée d’intégration d’étudiants", title="Panel « L’éloquence comme outil de réussite »", desc="Intervention de Cap Éloquence, invitée d’honneur de la journée.", img=blob("50ed438509dd3f497398a1b2156ed4ff"), alt="Abdou FLEUR intervient devant des étudiants"),
    dict(cat="ateliers", where="Cap Éloquence · Paris", title="Présentation du pôle formation et pédagogie", desc="Les ateliers Tous Orateurs, deux fois par mois, et les programmes destinés aux enfants.", img=blob("70e41b4ee134eadfb0fcd2e607193555"), alt="Abdou FLEUR présente le pôle formation de Cap Éloquence"),
    dict(cat="ateliers", where="Université Paris 8 · 10 octobre 2026", title="Atelier de prise de parole, journée d'intégration d'étudiants", desc="Atelier d'éloquence animé à l'invitation d'une association d'étudiants sénégalais, lors de sa journée d'accueil."),
    dict(cat="ateliers", where="Cap Éloquence · Paris", title="Ateliers Tous Orateurs", desc="Deux ateliers par mois à Bastille : discours évalués, débat public, improvisations, avec toujours une question de leadership.", img=blob("e458afe03b0622669511c4889a936b77"), alt="Atelier Tous Orateurs de Cap Éloquence", pos="66% 40%", link="https://www.capeloquence.net/", linkLabel="En savoir plus"),
    dict(cat="presse", where="New African, le magazine de l'Afrique · 13 juin 2026", title="« Nous sommes tous orateurs par nature »", desc="Entretien mené par Aïssatou Diamanka-Besland : avec Cap Éloquence, Abdou FLEUR veut rendre à la parole sa fonction première, au service du leadership.", link="https://magazinedelafrique.com/culture/nous-sommes-tous-orateurs-par-nature/", linkLabel="Lire l'article"),
    dict(cat="diplomatie", where="UNESCO, Paris · mars 2022", title="Conseil exécutif de l'UNESCO, 7e session extraordinaire", desc="Membre de la délégation du Sénégal. Le compte rendu officiel des débats est disponible sur UNESDOC.", link="https://unesdoc.unesco.org/ark:/48223/pf0000381847", linkLabel="Consulter UNESDOC"),
    dict(cat="presse", where="Afrik.com · 7 avril 2017", title="« Tracer les chemins d'une Afrique émergente »", desc="Portrait signé Kardiatou Traoré : l'engagement d'Abdou FLEUR au sein de la diaspora africaine de France.", link="https://www.afrik.com/abdou-fleur-tracer-les-chemins-d-une-afrique-emergente", linkLabel="Lire l'article"),
    dict(cat="presse", where="Tribune · septembre 2015", title="« Un peu plus de rigueur, messieurs : la médiation n’est ni la négociation, ni la conciliation »", desc="Contribution publiée lors de la crise burkinabè : un plaidoyer pour la rigueur des concepts et des pratiques de médiation."),
]
LABELS = {"tv": "Télévision", "conferences": "Conférences", "ateliers": "Formations et ateliers", "presse": "Presse", "diplomatie": "Diplomatie"}
ACT_STYLE = "margin-top: auto; padding-top: 12px; font-size: 13.5px; font-weight: 500; color: #0B1A33"
ARR = '<span aria-hidden="true" style="color: #7A5C1B">→</span>'

def int_items():
    out = []
    for n, it in enumerate(ITEMS):
        x = dict(it)
        x["hasImg"] = bool(it.get("img"))
        x["video"] = bool(it.get("yt"))
        x["pos"] = it.get("pos", "50% 50%")
        x["alt"] = it.get("alt", "")
        x["catLabel"] = LABELS[it["cat"]]
        vid = f"v-{it['yt']}" if it.get("yt") else ""
        x["mediaAttrs"] = R(f' id="{vid}" data-yt="{it["yt"]}" data-title="{esc(it["title"])}"' if vid else "")
        x["playBtn"] = R(f'<button type="button" class="lp2-play" data-play="" aria-label="Lire la vidéo : {esc(it["title"])}"></button>' if vid else "")
        if vid:
            act = f'<p style="{ACT_STYLE}"><button type="button" data-play="{vid}" style="all: unset; cursor: pointer">Regarder {ARR}</button></p>'
        elif it.get("link"):
            act = f'<p style="{ACT_STYLE}"><a href="{esc(it["link"])}" target="_blank" rel="noopener" style="color: #0B1A33; text-decoration: none">{esc(it["linkLabel"])} {ARR}</a></p>'
        else:
            act = ""
        x["actionHtml"] = R(act)
        out.append(x)
    return out

CHIP_BASE = "min-height: 44px; padding: 0 18px; font-family: inherit; font-size: 14px; cursor: pointer; border: 1px solid "
ON = CHIP_BASE + "#0B1A33; background: #0B1A33; color: #F6F3EC"
OFF = CHIP_BASE + "#CFC5B1; background: transparent; color: #0B1A33"
def chips(defs, cur="tout"):
    return [dict(label=l, pressed="true" if k == cur else "false", style=ON if k == cur else OFF,
                 pick=R(f'data-f="{k}" data-on="{esc(ON)}" data-off="{esc(OFF)}"')) for k, l in defs]

t = maquette("Interventions-medias.dc.html")
t = must_replace(t, '<section aria-labelledby="liste"', '<section id="interv-list" aria-labelledby="liste"')
t = must_replace(t, '<h2 id="liste" ', '<h2 id="liste" data-lp2-count ')
t = must_replace(t, '<div role="group" aria-label="Filtrer par type"', '<div role="group" aria-label="Filtrer par type" data-lp2-filter="interv-list"')
t = must_replace(t, '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr)); gap: 28px">',
                 '<div data-lp2-int style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(100%, 340px), 1fr)); gap: 28px">')
t = must_replace(t, '<article class="lift" style=', '<article data-item data-count data-cat="{{ it.cat }}" data-title="{{ it.title }}" class="lift" style=')
_k = t.index('<sc-for list="{{ items }}"')
t = t[:_k] + must_replace(t[_k:], '<div class="zoom" style="position: relative; overflow: hidden; aspect-ratio: 16 / 9; background: #12264A">',
                 '<div class="zoom"{{ it.mediaAttrs }} style="position: relative; overflow: hidden; aspect-ratio: 16 / 9; background: #12264A">')
t = re.sub(r'(<sc-if value="\{\{ it\.video \}\}"[^>]*>\s*<span aria-hidden="true".*?</span>)', r'\1{{ it.playBtn }}', t, count=1, flags=re.S)
t = re.sub(r'<p style="margin-top: auto; padding-top: 12px;[^"]*">\{\{ it\.action \}\}.*?</p>', '{{ it.actionHtml }}', t, count=1, flags=re.S)
t = re.sub(r"Les extraits courts, d'environ une minute,.*?après un clic sur lecture\.",
           "Les émissions sont intégrées depuis YouTube et lues sur la page même : rien n'est chargé avant un clic sur lecture, et aucun cookie n'est déposé auparavant.", t, flags=re.S)
items = int_items()
ctx = dict(count=len(items), chips=chips([("tout", "Tout"), ("tv", "Télévision"), ("conferences", "Conférences"), ("ateliers", "Formations et ateliers"), ("presse", "Presse"), ("diplomatie", "Diplomatie")]), items=items)
s = render(t, ctx)
s = wire_video(s, "Lire la chronique", "W78c0MezTjM", "« Aujourd'hui, le Sénégal a besoin d'un consensus fort »")
# modèle des cartes ajoutées depuis l'espace de gestion
card_tpl = ('<template data-lp2-card><article data-item data-count data-cat="" data-title="" class="lift" style="background: #FFFFFF; border: 1px solid #E3DCCD; display: flex; flex-direction: column">'
            '<div class="zoom" data-c-media style="position: relative; overflow: hidden; aspect-ratio: 16 / 9; background: #12264A">'
            '<img src="" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover">'
            '<span data-c-badge aria-hidden="true" style="position: absolute; left: 16px; bottom: 16px; width: 46px; height: 46px; border-radius: 50%; border: 1px solid #C9A45C; background: rgba(11,26,51,0.6); display: flex; align-items: center; justify-content: center"><svg width="13" height="15" viewBox="0 0 18 20"><path d="M2 1.5v17L16.5 10z" fill="#C9A45C"></path></svg></span>'
            '<button type="button" class="lp2-play" data-play="" aria-label="Lire la vidéo"></button></div>'
            '<div style="padding: 26px 28px 28px; display: flex; flex-direction: column; gap: 10px; flex: 1">'
            '<p data-c-kind style="font-size: 11.5px; letter-spacing: 0.16em; text-transform: uppercase; color: #7A5C1B"></p>'
            '<h3 data-c-title style="font-family: \'Newsreader\', Georgia, serif; font-weight: 450; font-size: 21px; line-height: 1.3"></h3>'
            '<p data-c-text style="font-size: 14.5px; line-height: 1.6; color: #4A5468"></p>'
            f'<p data-c-act style="{ACT_STYLE}"></p></div></article></template>')
s = s.replace("</main>", card_tpl + "</main>", 1)
attrs, inner = main_inner(s)
inner = map_links(typo(inner), "interventions", "Interventions-medias.dc.html")
iv = page("/interventions/", "Interventions et médias | Abdou FLEUR · Lead Performant",
          "Plateaux de télévision, conférences, ateliers, presse et diplomatie : les prises de parole publiques d'Abdou FLEUR, fondateur de Lead Performant.",
          finish(body_main(attrs, inner)), active="interventions",
          jsonld=[crumbs_ld([("Accueil", "/"), ("Interventions et médias", "/interventions/")])])
write("interventions/index.html", iv)

# ================================================================== Articles (pôle I et pôle II)
CMS = json.load(open(ROOT / "work/contenus.json"))
def md(t):
    def inline(x):
        x = esc(x)
        x = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", x)
        def ln(m):
            u = html.unescape(m.group(2)); ext = u.startswith("http") and "leadperformant.com" not in u
            u = u.replace("https://www.leadperformant.com", "")
            return f'<a href="{esc(u)}"' + (' target="_blank" rel="noopener"' if ext else "") + f">{m.group(1)}</a>"
        return re.sub(r"\[([^\]]+)\]\(((?:https?://|/)[^\s)]*)\)", ln, x)
    out = []
    for block in re.split(r"\n\s*\n", t.replace("\r", "").strip()):
        lines = [l.rstrip() for l in block.split("\n") if l.strip()]
        if not lines:
            continue
        o = ""
        if lines[0].startswith("## "):
            o += "<h2>" + inline(lines.pop(0)[3:]) + "</h2>"
            if not lines:
                out.append(o); continue
        if all(l.startswith("- ") for l in lines):
            o += "<ul>" + "".join("<li>" + inline(l[2:]) + "</li>" for l in lines) + "</ul>"
        elif lines[0].startswith("> "):
            o += '<div class="aside"><p>' + inline(" ".join(re.sub(r"^>\s?", "", l) for l in lines)) + "</p></div>"
        else:
            o += "<p>" + inline(" ".join(lines)) + "</p>"
        out.append(o)
    return "".join(out)

GDC = (ROOT / "dl/gdc/ressources.txt").read_text(encoding="utf-8")
def gdc_section(start, end):
    a = GDC.index(start); b = GDC.index(end, a + len(start))
    return [p.strip() for p in GDC[a:b].split("\n\n") if p.strip()]

def para(ps):
    return "".join("<p>" + esc(p) + "</p>" for p in ps)

ARTS = []
for a in CMS["articles"]:
    ARTS.append(dict(slug=a["slug"], pole="p1" if a["categorie"] == "Art oratoire" else "p2", kind=a["categorie"], title=a["titre"],
                     desc=a["resume"], time=a["lecture"] + " de lecture", chapo=a["chapo"], body=md(a["corps"]),
                     legacy=(a["slug"], a["maj"]), programme=a.get("programme")))
# textes repris du site Gestion des conflits
p = gdc_section("On nous a appris à fuir le conflit", "— Le regard du médiateur")
ARTS.append(dict(slug="le-conflit-n-est-pas-l-ennemi", pole="p2", kind="Regard du médiateur", title="Le conflit n'est pas l'ennemi",
                 desc="Dans une équipe comme dans une famille, le conflit n'est pas une anomalie : c'est un signal qu'il faut apprendre à écouter.",
                 time="2 min de lecture", chapo=p[0],
                 body=para(p[1:2]) + "<blockquote>" + esc(p[2]) + "</blockquote>" + para(p[3:]) + '<p class="sign">Le regard du médiateur</p>'))
p = gdc_section("Un désaccord est sain", "Édito\n")
p = [x for x in p if x not in ("Édito",)]
body = ""
for x in p[1:]:
    if re.match(r"^\d\. ", x):
        body += "<h2>" + esc(x[3:]) + "</h2>"
    else:
        body += "<p>" + esc(x) + "</p>"
ARTS.append(dict(slug="les-trois-erreurs-qui-transforment-un-desaccord-en-conflit", pole="p2", kind="Gestion des conflits",
                 title="Les trois erreurs qui transforment un désaccord en conflit",
                 desc="Le conflit naît rarement du désaccord lui-même, mais de la manière dont on le traite.", time="5 min de lecture", chapo=p[0], body=body))
p = gdc_section("Les organisations investissent massivement", "— L'édito")
ARTS.append(dict(slug="la-competence-relationnelle-grande-oubliee-de-la-performance", pole="p2", kind="Édito",
                 title="La compétence relationnelle, grande oubliée de la performance",
                 desc="Savoir écouter et conduire une conversation difficile ne sont pas des qualités innées : ce sont des compétences.",
                 time="3 min de lecture", chapo=p[0], body=para(p[1:]) + '<p class="sign">L\'édito de Lead Performant</p>'))
p = gdc_section("Exemple anonymisé et représentatif", "Un besoin, une situation ?")
body = ""
for x in p[1:]:
    if x in ("La situation", "L'intervention", "Le résultat"):
        body += "<h2>" + esc(x) + "</h2>"
    elif x.startswith("Ce que ce cas illustre"):
        body += '<div class="aside"><p><strong>Ce que ce cas illustre</strong>' + esc(x[len("Ce que ce cas illustre"):]) + "</p></div>"
    else:
        body += "<p>" + esc(x) + "</p>"
ARTS.append(dict(slug="desamorcer-un-conflit-d-equipe-en-trois-seances", pole="p2", kind="Cas pratique",
                 title="Désamorcer un conflit d'équipe en trois séances", desc="Un exemple anonymisé et représentatif de la méthode d’intervention.",
                 time="4 min de lecture", chapo=p[0], body=body, note="Entreprise · service de douze personnes"))

POLE_NAME = {"p1": "Art oratoire et leadership", "p2": "Médiation et gestion des conflits"}
POLE_URL = {"p1": "/art-oratoire-leadership/", "p2": "/mediation-gestion-des-conflits/"}
PROG = dict(PROGS)

def article_page(a):
    others = [x for x in ARTS if x["slug"] != a["slug"] and x["pole"] == a["pole"]][:2]
    leg = f' data-legacy="{a["legacy"][0]}" data-maj="{a["legacy"][1]}"' if a.get("legacy") else ""
    if a.get("programme") in PROG:
        nxt = (f'<div style="background: #0B1A33; color: #F6F3EC; padding: 30px 32px; display: flex; flex-direction: column; gap: 12px; margin-top: 2.2em">'
               f'<p style="font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase; color: #C9A45C">Aller plus loin</p>'
               f'<p style="font-family: \'Newsreader\', Georgia, serif; font-size: 24px; line-height: 1.3">{esc(PROG[a["programme"]])}</p>'
               f'<a class="lpb lpb-gold" href="/formations/{a["programme"]}/" style="align-self: flex-start; display: inline-flex; align-items: center; gap: 12px; min-height: 50px; padding: 0 24px; background: #C9A45C; color: #0B1A33; text-decoration: none; font-size: 15px; font-weight: 600">Découvrir le programme <span aria-hidden="true">→</span></a></div>')
    else:
        ctx = "?pole=mediation" if a["pole"] == "p2" else ""
        nxt = (f'<div style="background: #0B1A33; color: #F6F3EC; padding: 30px 32px; display: flex; flex-direction: column; gap: 12px; margin-top: 2.2em">'
               f'<p style="font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase; color: #C9A45C">Pôle {"I" if a["pole"] == "p1" else "II"} · {POLE_NAME[a["pole"]]}</p>'
               f'<p style="font-family: \'Newsreader\', Georgia, serif; font-size: 24px; line-height: 1.3">Une situation à éclairer ? Un premier échange, confidentiel et sans engagement.</p>'
               f'<a class="lpb lpb-gold" href="/contact/{ctx}" style="align-self: flex-start; display: inline-flex; align-items: center; gap: 12px; min-height: 50px; padding: 0 24px; background: #C9A45C; color: #0B1A33; text-decoration: none; font-size: 15px; font-weight: 600">Demander un échange <span aria-hidden="true">→</span></a></div>')
    rel = "".join(
        f'<li style="border-bottom: 1px solid #D8CFBD"><a href="/ressources/{o["slug"]}/" style="display: flex; flex-direction: column; gap: 8px; padding: 24px 0; color: #0B1A33; text-decoration: none">'
        f'<span style="font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; color: #7A5C1B">{esc(o["kind"])} · {esc(o["time"])}</span>'
        f'<span style="font-family: \'Newsreader\', Georgia, serif; font-size: 24px; line-height: 1.25">{esc(o["title"])}</span></a></li>' for o in others)
    note = f'<p style="font-size: 14px; color: #4A5468; font-style: italic">{esc(a["note"])}</p>' if a.get("note") else ""
    body = (
        '<main id="contenu" class="lpx"><article style="padding: 40px 0 clamp(72px, 8vw, 112px)">'
        '<div style="max-width: 1280px; margin: 0 auto; padding: 0 clamp(20px, 5vw, 64px); display: flex; flex-direction: column; gap: 40px">'
        '<nav aria-label="Fil d\'Ariane" style="font-size: 13px; color: #4A5468; display: flex; flex-wrap: wrap; gap: 10px"><a href="/" style="color: #4A5468; text-decoration: none">Accueil</a><span aria-hidden="true">/</span>'
        '<a href="/ressources/" style="color: #4A5468; text-decoration: none">Ressources</a><span aria-hidden="true">/</span>'
        f'<span style="color: #0B1A33">{esc(a["kind"])}</span></nav>'
        '<header style="max-width: 860px; display: flex; flex-direction: column; gap: 20px; padding-bottom: 36px; border-bottom: 1px solid #0B1A33">'
        f'<p class="rise" style="font-size: 13px; letter-spacing: 0.22em; text-transform: uppercase; color: #7A5C1B; font-weight: 500">{esc(a["kind"])} · <a href="{POLE_URL[a["pole"]]}" style="color: #7A5C1B">{POLE_NAME[a["pole"]]}</a></p>'
        f'<h1 class="rise d1" style="font-family: \'Newsreader\', Georgia, serif; font-weight: 380; font-size: clamp(38px, 4.6vw, 64px); line-height: 1.06; letter-spacing: -0.015em">{esc(a["title"])}</h1>'
        f'<p class="rise d2" style="font-size: 14px; color: #4A5468">{esc(a["time"])} · Abdou FLEUR, Lead Performant</p></header>'
        f'<div class="lp2-art"{leg}>{note}<p class="chapo">{esc(a["chapo"])}</p>{a["body"]}{nxt}</div>'
        + (f'<aside aria-labelledby="alire" style="max-width: 760px; width: 100%; margin: 24px auto 0"><h2 id="alire" style="font-size: 13px; letter-spacing: 0.2em; text-transform: uppercase; font-weight: 600; padding-bottom: 14px; border-bottom: 1px solid #D8CFBD">À lire aussi</h2><ul style="list-style: none; padding: 0">{rel}</ul>'
           '<p style="margin-top: 24px"><a class="ulink" href="/ressources/" style="color: #0B1A33; font-size: 15px; font-weight: 500">Toutes les ressources <span aria-hidden="true" style="color: #7A5C1B">→</span></a></p></aside>' if rel else "")
        + "</div></article></main>")
    ld = {"@context": "https://schema.org", "@type": "Article", "headline": a["title"], "description": a["desc"], "inLanguage": "fr-FR",
          "author": {"@id": SITE + "/fondateur/#abdou-fleur"}, "publisher": {"@id": SITE + "/#org"},
          "mainEntityOfPage": SITE + f"/ressources/{a['slug']}/", "articleSection": a["kind"]}
    return page(f"/ressources/{a['slug']}/", f"{a['title']} | Lead Performant", a["desc"], finish(typo(body)), active="ressources",
                og="partage-pole-art-oratoire-leadership-1200x630.jpg" if a["pole"] == "p1" else "partage-pole-mediation-gestion-conflits-1200x630.jpg",
                jsonld=[ld, crumbs_ld([("Accueil", "/"), ("Ressources", "/ressources/"), (a["title"], f"/ressources/{a['slug']}/")])], cms=bool(a.get("legacy")))

for a in ARTS:
    write(f"ressources/{a['slug']}/index.html", article_page(a))

# ================================================================== Ressources
ORDER = ["preparer-une-prise-de-parole-a-fort-enjeu", "gerer-le-trac", "le-conflit-n-est-pas-l-ennemi", "les-trois-erreurs-qui-transforment-un-desaccord-en-conflit",
         "mediation-negociation-conciliation", "la-competence-relationnelle-grande-oubliee-de-la-performance", "desamorcer-un-conflit-d-equipe-en-trois-seances"]
by = {a["slug"]: a for a in ARTS}
arts = [dict(by[s], href="/ressources/" + s + "/") for s in ORDER]
arts.append(dict(slug="tribune-2015", pole="p2", kind="Tribune · 2015", title="« Un peu plus de rigueur, messieurs : la médiation n'est ni la négociation, ni la conciliation »",
                 desc="Contribution publiée lors de la crise burkinabè.", time="Contribution", href=""))
TOOLS = [dict(pole="p2", title="Boussole relationnelle", desc="Version courte : dix affirmations, cinq thèmes, un premier diagnostic du climat relationnel.", meta="Quatre minutes", href="/mediation-gestion-des-conflits/boussole-relationnelle/", dark=True),
         dict(pole="p1", title="Diagnostic d'aisance orale", desc="Votre objectif, puis huit questions : vos points forts, vos deux priorités et le programme adapté.", meta="Deux minutes", href="/art-oratoire-leadership/#diagnostic"),
         dict(pole="p1", title="Studio d'entraînement", desc="Le Laboratoire de la parole en ligne : volume, débit, silences, tics verbaux, souffle et improvisation.", meta="Au micro", href="/laboratoire.html"),
         dict(pole="p1", title="Atelier du leader", desc="Formuler une vision, déléguer juste, donner un feedback : trois exercices, trois livrables.", meta="Trois exercices", href="/art-oratoire-leadership/#atelier")]
for x in TOOLS:
    x["cat"] = x["pole"]; x["poleName"] = POLE_NAME[x["pole"]]
    x["style"] = "padding: 30px; min-height: 260px; display: flex; flex-direction: column; gap: 12px; text-decoration: none; border: 1px solid " + ("#0B1A33; background: #0B1A33; color: #F6F3EC" if x.get("dark") else "#E3DCCD; background: #FFFFFF; color: #0B1A33")
    x["tagStyle"] = "font-size: 11.5px; letter-spacing: 0.16em; text-transform: uppercase; color: " + ("#C9A45C" if x.get("dark") else "#7A5C1B")
t = maquette("Ressources.dc.html")
t = must_replace(t, '<div role="group" aria-label="Filtrer par pôle"', '<div role="group" aria-label="Filtrer par pôle" data-lp2-filter=""')
t = must_replace(t, '<a class="lift" href="{{ t.href }}" style="{{ t.style }}">', '<a data-item data-cat="{{ t.cat }}" class="lift" href="{{ t.href }}" style="{{ t.style }}">')
t = must_replace(t, '<span style="{{ t.tagStyle }}">{{ t.pole }}</span>', '<span style="{{ t.tagStyle }}">{{ t.poleName }}</span>')
t = must_replace(t, '<ol style="list-style: none; padding: 0; display: flex; flex-direction: column; border-top: 1px solid #D8CFBD">',
                 '<ol data-lp2-arts style="list-style: none; padding: 0; display: flex; flex-direction: column; border-top: 1px solid #D8CFBD">')
t = must_replace(t, '<li style="border-bottom: 1px solid #D8CFBD">\n<a href="Ressources.dc.html"', '<li data-item data-cat="{{ a.pole }}" data-slug="{{ a.slug }}" style="border-bottom: 1px solid #D8CFBD">\n<a href="{{ a.href }}"')
li_tpl = re.search(r'<li data-item data-cat="\{\{ a\.pole \}\}".*?</li>', t, re.S).group(0)
tabs = chips([("tout", "Tout"), ("p1", "Art oratoire et leadership"), ("p2", "Médiation et gestion des conflits")])
s = render(t, dict(tools=TOOLS, articles=arts, tabs=tabs))
s = re.sub(r'<a href=""(\s[^>]*)>(.*?)</a>', r'<div\1>\2</div>', s, flags=re.S)  # tribune : pas de page dédiée
art_tpl = render(li_tpl, {"a": dict(pole="", slug="", href="#", kind="", title="", desc="", time="")})
art_tpl = art_tpl.replace('color: #7A5C1B"></span>', 'color: #7A5C1B" data-a-kind></span>', 1)
art_tpl = re.sub(r'(<span style="font-family: \'Newsreader\', Georgia, serif; font-size: 25px; line-height: 1.25")></span>', r'\1 data-a-title></span>', art_tpl, count=1)
art_tpl = re.sub(r'(<span style="font-size: 15px; line-height: 1.6; color: #4A5468")></span>', r'\1 data-a-desc></span>', art_tpl, count=1)
art_tpl = re.sub(r'(<span style="flex: 0 0 auto; font-size: 13.5px; color: #4A5468")></span>', r'\1 data-a-time></span>', art_tpl, count=1)
for hook in ("data-a-kind", "data-a-title", "data-a-desc", "data-a-time"):
    if hook not in art_tpl:
        raise SystemExit("modèle d'article : " + hook)
s = s.replace("</main>", "<template data-lp2-art>" + art_tpl + "</template></main>", 1)
attrs, inner = main_inner(s)
inner = map_links(typo(inner), "ressources", "Ressources.dc.html")
rs = page("/ressources/", "Ressources | Art oratoire, médiation et gestion des conflits · Lead Performant",
          "Articles, éditos, cas pratiques et outils en libre accès : préparer une prise de parole, apprivoiser le trac, comprendre un désaccord, faire le point sur le climat d'une équipe.",
          finish(body_main(attrs, inner)), active="ressources",
          jsonld=[crumbs_ld([("Accueil", "/"), ("Ressources", "/ressources/")])])
write("ressources/index.html", rs)

# ================================================================== Demander un échange
t = maquette("Demander-un-echange.dc.html")
FIELD = 'class="field" '
t = must_replace(t, '<form style=', '<form id="contact-form" data-contact novalidate style=')
# choix du pôle
t = re.sub(r'<sc-for list="\{\{ poles \}\}".*?</sc-for>', '{{ polesHtml }}', t, count=1, flags=re.S)
t = must_replace(t, '<legend', '<legend id="pole-legend"')
POLE_BASE = "min-height: 92px; padding: 16px 18px; text-align: left; display: flex; flex-direction: column; gap: 6px; justify-content: center; font-family: inherit; cursor: pointer; border: 1px solid "
PON = POLE_BASE + "#0B1A33; background: #0B1A33; color: #F6F3EC"
POFF = POLE_BASE + "#CFC5B1; background: #FBFAF7; color: #0B1A33"
m = re.search(r'<button type="button" onClick="\{\{ p\.pick \}\}" aria-pressed="\{\{ p\.pressed \}\}" style="\{\{ p\.style \}\}"><span([^>]*)>\{\{ p\.num \}\}</span><span([^>]*)>\{\{ p\.label \}\}</span></button>',
              (MAQ / "Demander-un-echange.dc.html").read_text(encoding="utf-8"))
num_attr, lab_attr = m.group(1), m.group(2)
poles_html = "".join(
    f'<button type="button" data-pole="{k}" aria-pressed="{"true" if k == "p1" else "false"}" data-on="{esc(PON)}" data-off="{esc(POFF)}" style="{esc(PON if k == "p1" else POFF)}"><span{num_attr}>{n}</span><span{lab_attr}>{l}</span></button>'
    for k, n, l in [("p1", "Pôle I", "Art oratoire et leadership"), ("p2", "Pôle II", "Médiation et gestion des conflits"), ("none", "Orientation", "Je ne sais pas encore")])
# grille des pôles : conteneur des boutons
t = re.sub(r'(<legend id="pole-legend"[^>]*>.*?</legend>\s*<div)', r'\1 data-lp2-poles', t, count=1, flags=re.S)
ERR = lambda k: f'<span class="lp2-err" id="e-{k}" aria-live="polite"></span>'
# Vous êtes
t = re.sub(r'<label([^>]*)>Vous êtes\s*<select([^>]*)>.*?</select></label>',
           lambda m: f'<label {FIELD}{m.group(1).strip()} for="f-profil">Vous êtes<select id="f-profil" name="profil" required{m.group(2)}>'
           '<option value="">Choisir</option><option value="entreprise">Une entreprise ou une organisation</option><option value="ecole">Une école ou un établissement d\'enseignement</option>'
           '<option value="institution">Une collectivité, une institution ou une association</option><option value="particulier">Un particulier</option><option value="organisateur">Un organisateur d\'événements</option>'
           f'</select>{ERR("profil")}</label>', t, count=1, flags=re.S)
t = re.sub(r'<label([^>]*)>Votre besoin\s*<select([^>]*)>.*?</select></label>',
           lambda m: f'<label {FIELD}{m.group(1).strip()} for="f-besoin">Votre besoin<select id="f-besoin" name="besoin" required{m.group(2)}>'
           '<option value="">Choisir</option><option value="echange">Un premier échange</option><option value="formation">Une formation</option><option value="devis">Un devis pour mon organisation</option>'
           '<option value="atelier">Un atelier en établissement</option><option value="coaching">Un coaching individuel</option><option value="intervention">Une intervention ou une conférence</option>'
           f'</select>{ERR("besoin")}</label>', t, count=1, flags=re.S)
t = re.sub(r'<sc-if value="\{\{ isP1 \}\}"[^>]*>\s*<label([^>]*)>(Programme concerné.*?)<select([^>]*)>.*?</select></label>\s*</sc-if>',
           lambda m: f'<label data-lp2-prog{m.group(1)} for="f-programme"><span>{m.group(2).strip()}</span><select id="f-programme" name="programme"{m.group(3)}>'
           '<option value="">Aucun en particulier</option>' + "".join(f'<option value="{s}">{i + 1:02d} · {l}</option>' for i, (s, l) in enumerate(PROGS)) +
           '<option value="pole-2" hidden>Pôle II · Médiation et gestion des conflits</option><option value="orientation" hidden>Orientation · je ne sais pas encore</option></select></label>',
           t, count=1, flags=re.S)
t = re.sub(r'<sc-if value="\{\{ isP2 \}\}"[^>]*>\s*<p([^>]*)>(.*?)</p>\s*</sc-if>', r'<p data-lp2-p2note hidden\1>\2</p>', t, count=1, flags=re.S)
def inp(label, fid, name, extra="", err=None):
    global t
    pat = r'<label([^>]*)>(' + re.escape(label) + r'(?:\s*<span[^>]*>[^<]*</span>)?)<input([^>]*)></label>'
    if not re.search(pat, t):
        raise SystemExit("champ " + label)
    t = re.sub(pat, lambda m: f'<label {FIELD}{m.group(1).strip()} for="{fid}"><span>{m.group(2)}</span><input id="{fid}" name="{name}"{extra}{m.group(3)}>' + (ERR(err) if err else "") + "</label>", t, count=1)
inp("Nom et prénom", "f-nom", "nom", " required", "nom")
inp("Organisation", "f-organisation", "organisation")
inp("Courriel", "f-email", "email", " required", "email")
inp("Téléphone", "f-tel", "telephone")
t = re.sub(r'<label([^>]*)>Votre message<textarea([^>]*)></textarea></label>',
           lambda m: f'<label {FIELD}{m.group(1).strip()} for="f-message">Votre message<textarea id="f-message" name="message" required{m.group(2)}></textarea>{ERR("message")}</label>', t, count=1)
for slot in ["Matin", "Midi", "Après-midi", "Fin de journée"]:
    t = re.sub(r'<input type="checkbox"([^>]*)>' + re.escape(slot) + "</label>", f'<input type="checkbox" name="disponibilites" value="{slot}"\\1>{slot}</label>', t, count=1)
t = re.sub(r'<label([^>]*)><input type="checkbox" required="required"([^>]*)><span>(J\'accepte que Lead Performant.*?)</span></label>',
           lambda m: f'<label {FIELD}{m.group(1).strip()}><input type="checkbox" id="f-consent" name="consentement" required{m.group(2)}><span>{m.group(3)}{ERR("consent")}</span></label>', t, count=1, flags=re.S)
t = re.sub(r'<button type="button" class="lpb lpb-navy"([^>]*)>Envoyer ma demande',
           r'<p id="form-status" class="form-status" role="status" hidden></p><div aria-hidden="true" style="position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden"><label for="f-site">Site web</label><input id="f-site" name="site_web" type="text" tabindex="-1" autocomplete="off"></div><button type="submit" id="f-submit" class="lpb lpb-navy"\1>Envoyer ma demande', t, count=1)
s = render(t, dict(polesHtml=R(poles_html)))
attrs, inner = main_inner(s)
inner = map_links(typo(inner), "contact", "Demander-un-echange.dc.html")
ct = page("/contact/", "Demander un échange | Lead Performant",
          "Décrivez votre besoin en art oratoire, leadership, médiation ou gestion des conflits : réponse sous deux jours ouvrés pour un premier échange gratuit et sans engagement.",
          finish(body_main(attrs, inner)), active="contact",
          jsonld=[crumbs_ld([("Accueil", "/"), ("Demander un échange", "/contact/")])])
write("contact/index.html", ct)

# ================================================================== Boussole relationnelle
t = maquette("Boussole-relationnelle.dc.html")
def step_block(t, cond, name):
    m = re.search(r'<sc-if value="\{\{ ' + cond + r' \}\}"[^>]*>\s*<div', t)
    if not m:
        raise SystemExit("étape " + cond)
    start = m.start()
    inner_start = m.end() - 4
    # fermeture correspondante
    depth, j = 1, m.end()
    while depth:
        n = re.compile(r"<(/?)sc-if\b[^>]*>").search(t, j)
        depth += -1 if n.group(1) else 1
        j = n.end()
    body = t[inner_start:n.start()].rstrip()
    body = "<div" + f' data-step="{name}"' + ("" if name == "intro" else " hidden") + body[4:]
    return t[:start] + body + t[j:]
for cond, name in [("isIntro", "intro"), ("isQ", "q"), ("isGate", "gate"), ("isResult", "result")]:
    t = step_block(t, cond, name)
# conteneur des étapes : le parent direct de la première étape
k = t.index('data-step="intro"')
par = t.rfind("<div", 0, t.rfind("<div", 0, k))
t = t[:par] + "<div data-boussole" + t[par + 4:]
t = must_replace(t, 'onClick="{{ start }}"', "data-b-start")
t = must_replace(t, '<span style="letter-spacing: 0.16em; text-transform: uppercase; color: #7A5C1B">{{ theme }}</span><span>Affirmation {{ num }} sur 10</span>',
                 '<span data-b-theme style="letter-spacing: 0.16em; text-transform: uppercase; color: #7A5C1B">Communication</span><span data-b-num>Affirmation 1 sur 10</span>')
t = must_replace(t, 'aria-valuenow="{{ num }}"', 'aria-valuenow="1"')
t = must_replace(t, '<div style="{{ barStyle }}"></div>', '<div data-b-bar style="height: 3px; background: #0B1A33; width: 10%; transition: width .35s ease"></div>')
t = re.sub(r'(<h1[^>]*)>\{\{ question \}\}</h1>', r'\1 data-b-q>Les informations importantes circulent bien entre les personnes et les services.</h1>', t, count=1)
OPT_OFF = "min-height: 72px; padding: 14px 16px; text-align: left; border: 1px solid #CFC5B1; background: #FFFFFF; color: #0B1A33; font-family: inherit; font-size: 15px; line-height: 1.35; cursor: pointer"
OPT_ON = OPT_OFF.replace("border: 1px solid #CFC5B1; background: #FFFFFF; color: #0B1A33", "border: 1px solid #0B1A33; background: #0B1A33; color: #F6F3EC")
opts = "".join(f'<button type="button" data-b-opt="{i}" aria-pressed="false" data-on="{esc(OPT_ON)}" data-off="{esc(OPT_OFF)}" style="{esc(OPT_OFF)}">{l}</button>'
               for i, l in enumerate(["Pas du tout d’accord", "Plutôt pas d’accord", "Plutôt d’accord", "Tout à fait d’accord"]))
t = re.sub(r'<sc-for list="\{\{ options \}\}".*?</sc-for>', opts, t, count=1, flags=re.S)
t = t.replace('onClick="{{ back }}"', "data-b-back")
t = must_replace(t, 'onClick="{{ submit }}"', "data-b-submit")
t = must_replace(t, 'onClick="{{ restart }}"', "data-b-restart")
t = re.sub(r'<label([^>]*)>Votre courriel professionnel<input type="email"([^>]*)></label>',
           r'<label\1 for="b-email">Votre courriel professionnel<input id="b-email" name="email" type="email" required\2><span class="lp2-err" id="b-e-email" aria-live="polite"></span></label>', t, count=1)
t = re.sub(r'(Votre structure.*?)<select', r'\1<select id="b-structure" name="structure"', t, count=1, flags=re.S)
t = re.sub(r'<input type="checkbox" required="required"([^>]*)><span>(J\'accepte que mon adresse.*?)</span>',
           r'<input type="checkbox" id="b-consent" required\1><span>\2<span class="lp2-err" id="b-e-consent" aria-live="polite"></span></span>', t, count=1, flags=re.S)
t = re.sub(r'<input type="checkbox"([^>]*)><span>(Je souhaite aussi recevoir)', r'<input type="checkbox" id="b-news"\1><span>\2', t, count=1)
t = t.replace("<button type=\"button\" data-b-submit", '<input id="b-site" type="text" tabindex="-1" autocomplete="off" aria-hidden="true" style="position: absolute; left: -9999px; width: 1px; height: 1px"><button type="button" data-b-submit', 1)
t = re.sub(r'(<p[^>]*)>Votre premier diagnostic · une copie vous a été adressée</p>', r'\1 data-b-sent>Votre premier diagnostic</p>', t, count=1)
t = re.sub(r'(<p[^>]*)>\{\{ score \}\}<span', r'\1><span data-b-score>0</span><span', t, count=1)
t = re.sub(r'(<h1[^>]*)>\{\{ level \}\}</h1>', r'\1 data-b-level></h1>', t, count=1)
t = re.sub(r'(<p[^>]*)>\{\{ levelText \}\}</p>', r'\1 data-b-leveltext></p>', t, count=1)
t = re.sub(r'(<p[^>]*)>\{\{ next \}\}</p>', r'\1 data-b-next></p>', t, count=1)
m = re.search(r'(<ul[^>]*)>\s*<sc-for list="\{\{ themes \}\}"[^>]*>(.*?)</sc-for>', t, re.S)
li = m.group(2).strip()
li = li.replace("<li ", "<li data-b-t ", 1).replace("<span>{{ t.label }}</span>", "<span data-b-tl></span>").replace('<span style="{{ t.bar }}"></span>', '<span data-b-tb style="display: block; height: 6px; background: #0B1A33; width: 0"></span>').replace(">{{ t.pct }}</span>", " data-b-tp></span>")
t = t[:m.start()] + m.group(1) + " data-b-themes>" + li * 5 + t[m.end():]
s = render(t, {})
attrs, inner = main_inner(s)
inner = map_links(typo(inner), "pole2", "Boussole-relationnelle.dc.html")
bs = page("/mediation-gestion-des-conflits/boussole-relationnelle/", "Boussole relationnelle, version courte | Lead Performant",
          "Dix affirmations, cinq thèmes, environ quatre minutes : un premier diagnostic gratuit et confidentiel du climat relationnel de votre équipe, avec des pistes d'action.",
          finish(body_main(attrs, inner)), active="pole2", og="partage-pole-mediation-gestion-conflits-1200x630.jpg",
          jsonld=[crumbs_ld([("Accueil", "/"), ("Médiation et gestion des conflits", "/mediation-gestion-des-conflits/"), ("Boussole relationnelle", "/mediation-gestion-des-conflits/boussole-relationnelle/")])])
write("mediation-gestion-des-conflits/boussole-relationnelle/index.html", bs)

B.ARTS = ARTS
import legacy_pages  # noqa: E402,F401  pages existantes, feuille de style, script, envoi, plan du site
