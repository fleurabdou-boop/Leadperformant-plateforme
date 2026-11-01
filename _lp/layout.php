<?php
/* Gabarit des pages publiques : reprend l'en-tête, le menu et le pied de page du site existant (mêmes feuilles de style et scripts). */

function nav_html(string $actif = ''): string {
  $a = fn($k) => $actif === $k ? ' aria-current="page"' : '';
  return <<<HTML
<header class="site-header" id="top">
  <div class="wrap hbar">
    <a class="brand" href="/" aria-label="Lead Performant, accueil"><img src="/assets/img/embleme-lead-performant.png" alt="" width="40" height="39"><span>LEAD PERFORMANT</span></a>
    <nav class="nav" id="nav" aria-label="Navigation principale"><div class="nav-item has-mega is-pole" data-mega="art"><a class="nav-link" href="/art-oratoire/"><span class="r">I</span>Art oratoire</a><button type="button" class="mega-toggle" aria-expanded="false" aria-controls="mega-art" aria-label="Afficher le détail : Art oratoire"><svg width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.4"/></svg></button><div class="mega" id="mega-art" role="region" aria-label="Pôle Art oratoire"><div class="wrap mega-grid">
  <a class="m-feature m-item" style="--i:0" href="/art-oratoire/"><div class="ph r43 "></div><span class="m-roman">I</span><span class="m-name">Art oratoire</span><span class="m-tag">Oser parler · Convaincre · Marquer les esprits</span></a>
  <div class="m-list"><span class="eyebrow">Les programmes du pôle I</span><a class="m-prog m-item" style="--i:1" href="/formations/eloquence-elite/"><span class="num">01</span><span><b>Éloquence Élite</b><small>Comment mieux prendre la parole ?</small></span><span class="m-dur">3 jours</span></a><a class="m-prog m-item" style="--i:2" href="/formations/eloquence-levier-professionnel/"><span class="num">02</span><span><b>L'Éloquence comme levier professionnel</b><small>Comment mieux me présenter pour évoluer ?</small></span><span class="m-dur">3 jours</span></a><a class="m-prog m-item" style="--i:3" href="/formations/parole-d-impact/"><span class="num">03</span><span><b>Parole d'Impact</b><small>Comment convaincre dans une situation décisive ?</small></span><span class="m-dur">2 à 3 jours</span></a>
    <div class="m-foot m-item" style="--i:4"><a class="link" href="/art-oratoire/">Entrer dans le pôle Art oratoire <span class="arrow" aria-hidden="true">→</span></a><a class="link" href="/calendrier/">Calendrier des sessions</a></div></div>
</div></div></div><div class="nav-item has-mega is-pole" data-mega="lead"><a class="nav-link" href="/leadership/"><span class="r">II</span>Leadership</a><button type="button" class="mega-toggle" aria-expanded="false" aria-controls="mega-lead" aria-label="Afficher le détail : Leadership"><svg width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.4"/></svg></button><div class="mega" id="mega-lead" role="region" aria-label="Pôle Leadership"><div class="wrap mega-grid">
  <a class="m-feature m-item" style="--i:0" href="/leadership/"><div class="ph r43 "></div><span class="m-roman">II</span><span class="m-name">Leadership</span><span class="m-tag">Se connaître · Mobiliser · Faire agir</span></a>
  <div class="m-list"><span class="eyebrow">Les programmes du pôle II</span><a class="m-prog m-item" style="--i:1" href="/formations/leadership-relationnel/"><span class="num">04</span><span><b>Leadership relationnel et mobilisation</b><small>Comment faire grandir et mobiliser une équipe ?</small></span><span class="m-dur">2 jours</span></a><a class="m-prog m-item" style="--i:2" href="/formations/leadership-strategique/"><span class="num">05</span><span><b>Leadership stratégique et situations complexes</b><small>Comment agir quand la situation devient difficile ?</small></span><span class="m-dur">2 à 3 jours</span></a>
    <div class="m-foot m-item" style="--i:3"><a class="link" href="/leadership/">Entrer dans le pôle Leadership <span class="arrow" aria-hidden="true">→</span></a><a class="link" href="/leadership/#atelier">Atelier du leader</a></div></div>
</div></div></div><span class="sep" aria-hidden="true"></span><div class="nav-item"><a class="nav-link" href="/formations/">Programmes</a></div><div class="nav-item"><a class="nav-link" href="/calendrier/"{$a('calendrier')}>Calendrier</a></div><div class="nav-item accent"><a class="nav-link" href="/interventions/">Mes interventions<span class="live" aria-hidden="true"></span></a></div><div class="nav-item"><a class="nav-link" href="/ressources/">Ressources</a></div><div class="nav-item"><a class="nav-link" href="/boutique/"{$a('boutique')}>Boutique</a></div><div class="nav-item"><a class="nav-link" href="/contact/">Contact</a></div><span class="nav-ink" aria-hidden="true"></span><a class="btn nav-cta" href="/contact/">Demander un échange</a></nav>
    <a class="btn header-cta" href="/contact/">Demander un échange</a>
    <button class="menu-btn" id="menu-btn" aria-expanded="false" aria-controls="nav"><i></i><span>Menu</span></button>
  </div>
</header>
<div class="nav-backdrop" aria-hidden="true"></div>
HTML;
}

function footer_html(): string {
  $y = date('Y');
  return <<<HTML
<footer class="site-footer">
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand">
        <a class="logo" href="/" aria-label="Lead Performant, accueil"><img src="/assets/img/logo-lead-performant.png" alt="Lead Performant" width="100" height="97"></a>
        <p>Art oratoire et leadership. Formation, coaching et accompagnement pour celles et ceux qui doivent convaincre, fédérer et conduire le changement.</p>
        <p class="foot-contact"><b>06 62 57 92 74</b>leadperformant@gmail.com</p>
      </div>
      <div><h2>Programmes</h2><ul><li><a href="/formations/eloquence-elite/">Éloquence Élite</a></li><li><a href="/formations/eloquence-levier-professionnel/">L'Éloquence comme levier professionnel</a></li><li><a href="/formations/parole-d-impact/">Parole d'Impact</a></li><li><a href="/formations/leadership-relationnel/">Leadership relationnel et mobilisation</a></li><li><a href="/formations/leadership-strategique/">Leadership stratégique</a></li><li><a href="/calendrier/">Calendrier des sessions</a></li></ul></div>
      <div><h2>Pour qui</h2><ul><li><a href="/entreprises/">Entreprises</a></li><li><a href="/ecoles/">Écoles et universités</a></li><li><a href="/collectivites-institutions/">Collectivités et institutions</a></li><li><a href="/art-oratoire/#coaching">Particuliers</a></li><li><a href="/boutique/">Boutique</a></li></ul></div>
      <div><h2>Le cabinet</h2><ul><li><a href="/interventions/">Interventions et médias</a></li><li><a href="/ressources/">Ressources</a></li><li><a href="/laboratoire.html">Laboratoire de la parole</a></li><li><a href="/contact/">Contact</a></li></ul></div>
    </div>
    <div class="foot-legal">
      <span>© {$y} Lead Performant. Interventions en Île-de-France, en déplacement et à distance.</span>
      <nav aria-label="Informations légales"><a href="/mentions-legales/">Mentions légales</a><a href="/cgv/">Conditions générales de vente</a><a href="/reglement-interieur/">Règlement intérieur</a><a href="/confidentialite/">Confidentialité</a></nav>
    </div>
  </div>
</footer>
HTML;
}

function page_start(string $title, string $description, string $actif = '', bool $index = true, string $canonical = '', string $jsonld = ''): void {
  csrf_start();   // la session de formulaire doit démarrer avant toute sortie
  header('Content-Type: text/html; charset=utf-8');
  header('X-Content-Type-Options: nosniff');
  echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
    . '<title>' . h($title) . ' | Lead Performant</title><meta name="description" content="' . h($description) . '">'
    . ($index ? '' : '<meta name="robots" content="noindex, nofollow">')
    . ($canonical ? '<link rel="canonical" href="' . h(url($canonical)) . '">' : '')
    . '<meta property="og:type" content="website"><meta property="og:locale" content="fr_FR"><meta property="og:site_name" content="Lead Performant"><meta property="og:title" content="' . h($title) . '"><meta property="og:description" content="' . h($description) . '"><meta property="og:image" content="' . h(url('/assets/img/partage-lead-performant.jpg')) . '">'
    . '<meta name="theme-color" content="#21406A"><link rel="icon" type="image/png" href="/assets/img/favicon-lead-performant.png">'
    . '<link rel="preload" href="/assets/lp/fonts/cormorant-garamond-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>'
    . '<link rel="preload" href="/assets/lp/fonts/manrope-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>'
    . '<link rel="stylesheet" href="/assets/lp/site.css"><link rel="stylesheet" href="/assets/lp/plateforme.css?v=1">'
    . ($jsonld ? '<script type="application/ld+json">' . $jsonld . '</script>' : '')
    . '</head><body><a class="skip" href="#contenu">Aller au contenu</a>' . nav_html($actif) . '<main id="contenu">';
}
function page_end(): void {
  echo '</main>' . footer_html() . '<div class="toast" id="toast" role="status" aria-live="polite"></div>'
    . '<script>window.LP=window.LP||{"contact":"/contact/","base":"/","site":"' . h(url('/')) . '","mode":"prod","preview":false,"single":false,"programmes":{}};</script>'
    . '<script src="/assets/lp/site.js" defer></script></body></html>';
}
function crumbs(array $items): string {
  $out = '<nav class="crumbs" aria-label="Fil d’Ariane"><a href="/">Accueil</a>';
  foreach ($items as $label => $href) $out .= '<span aria-hidden="true">/</span>' . ($href ? '<a href="' . h($href) . '">' . h($label) . '</a>' : '<span aria-current="page">' . h($label) . '</span>');
  return $out . '</nav>';
}
function chip(string $label, string $ton): string { return '<span class="pf-chip pf-' . h($ton) . '">' . h($label) . '</span>'; }
function alert_box(string $msg, string $ton = 'rouge'): string { return '<div class="pf-alert pf-alert-' . h($ton) . '" role="alert">' . $msg . '</div>'; }
function page_message(string $title, string $html, string $ton = 'neutre'): void {
  page_start($title, $title, '', false);
  echo '<section class="section paper"><div class="wrap pf-narrow"><span class="eyebrow">Lead Performant</span><h1 class="pf-h1">' . h($title) . '</h1><div class="pf-card">' . $html . '</div></div></section>';
  page_end(); exit;
}
