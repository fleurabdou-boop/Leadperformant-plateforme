<?php
/* Gabarit des pages publiques : en-tête, menu et pied de page communs du site à deux pôles (mêmes feuilles de style et scripts). */

function nav_html(string $actif = ''): string {
  // en-tête commun de la maison mère à deux pôles (novembre 2026)
  $html = <<<'HTML'
<header class="lp2-h" id="top"><div class="lp2-bar"><a class="lp2-brand" href="/" aria-label="Lead Performant, accueil"><svg width="38" height="38" viewBox="0 0 48 48" aria-hidden="true"><path d="M5.2 19.5 A19.4 19.4 0 0 1 42.8 19.5" fill="none" stroke="#C9A45C" stroke-width="2.6" stroke-linecap="round"></path><path d="M42.8 28.5 A19.4 19.4 0 0 1 5.2 28.5" fill="none" stroke="#F6F3EC" stroke-width="2.6" stroke-linecap="round"></path></svg><span class="lp2-brand-t"><span class="lp2-brand-n">LEAD PERFORMANT</span><span class="lp2-brand-s"><span>Parler pour convaincre.</span><span>Dialoguer pour apaiser.</span></span></span></a><div class="lp2-act"><nav class="lp2-svc" aria-label="Inscriptions et boutique"><a href="/calendrier/" data-k="sessions"><svg class="lp2-ico" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1.65" y="2.9" width="12.7" height="11.45" fill="none" stroke="#C9A45C" stroke-width="1.3"></rect><path d="M1.65 6.4h12.7M5 1.1v3.4M11 1.1v3.4" fill="none" stroke="#C9A45C" stroke-width="1.3"></path></svg><span>Inscription aux sessions</span></a><a href="/boutique/" data-k="boutique"><svg class="lp2-ico" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.6 5.4h10.8l-.9 9.25H3.5z" fill="none" stroke="#C9A45C" stroke-width="1.3" stroke-linejoin="round"></path><path d="M5.6 7.2V4.3a2.4 2.4 0 0 1 4.8 0v2.9" fill="none" stroke="#C9A45C" stroke-width="1.3"></path></svg><span>Boutique</span></a></nav><a class="lp2-tel" href="tel:+33662579274">06 62 57 92 74</a><a class="lp2-cta" href="/contact/">Demander un échange</a><button class="lp2-burger" type="button" aria-expanded="false" aria-controls="lp2-menu" aria-label="Ouvrir le menu"><svg width="18" height="12" viewBox="0 0 18 12" aria-hidden="true"><path d="M0 1h18M0 6h18M0 11h18" stroke="#F6F3EC" stroke-width="1.5"></path></svg><span>Menu</span></button></div></div><nav class="lp2-svcbar" aria-label="Inscriptions et boutique"><a href="/calendrier/" data-k="sessions"><svg class="lp2-ico" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><rect x="1.65" y="2.9" width="12.7" height="11.45" fill="none" stroke="#C9A45C" stroke-width="1.3"></rect><path d="M1.65 6.4h12.7M5 1.1v3.4M11 1.1v3.4" fill="none" stroke="#C9A45C" stroke-width="1.3"></path></svg><span class="lp2-svc-l">Inscription aux sessions</span><span class="lp2-svc-s">Inscriptions</span></a><a href="/boutique/" data-k="boutique"><svg class="lp2-ico" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M2.6 5.4h10.8l-.9 9.25H3.5z" fill="none" stroke="#C9A45C" stroke-width="1.3" stroke-linejoin="round"></path><path d="M5.6 7.2V4.3a2.4 2.4 0 0 1 4.8 0v2.9" fill="none" stroke="#C9A45C" stroke-width="1.3"></path></svg><span>Boutique</span></a></nav><nav class="lp2-nav" aria-label="Navigation principale"><ul><li class="lp2-pole"><a href="/art-oratoire-leadership/" data-k="pole1"><span class="lp2-pole-n" aria-hidden="true">I</span><span class="lp2-pole-l">Art oratoire et leadership</span></a></li><li class="lp2-pole"><a href="/mediation-gestion-des-conflits/" data-k="pole2"><span class="lp2-pole-n" aria-hidden="true">II</span><span class="lp2-pole-l">Médiation et gestion des conflits</span></a></li><li><a href="/interventions/" data-k="interventions">Interventions et médias</a></li><li><a href="/references/" data-k="references">Témoignages</a></li><li><a href="/ressources/" data-k="ressources">Ressources</a></li><li><a href="/fondateur/" data-k="fondateur">Le fondateur</a></li></ul></nav><nav class="lp2-menu" id="lp2-menu" aria-label="Menu mobile" hidden><ul><li class="lp2-pole"><a href="/art-oratoire-leadership/" data-k="pole1"><span class="lp2-pole-n" aria-hidden="true">I</span><span class="lp2-pole-l">Art oratoire et leadership</span><span class="lp2-arr" aria-hidden="true">→</span></a></li><li class="lp2-pole"><a href="/mediation-gestion-des-conflits/" data-k="pole2"><span class="lp2-pole-n" aria-hidden="true">II</span><span class="lp2-pole-l">Médiation et gestion des conflits</span><span class="lp2-arr" aria-hidden="true">→</span></a></li><li><a href="/interventions/" data-k="interventions">Interventions et médias<span class="lp2-arr" aria-hidden="true">→</span></a></li><li><a href="/references/" data-k="references">Témoignages<span class="lp2-arr" aria-hidden="true">→</span></a></li><li><a href="/ressources/" data-k="ressources">Ressources<span class="lp2-arr" aria-hidden="true">→</span></a></li><li><a href="/fondateur/" data-k="fondateur">Le fondateur<span class="lp2-arr" aria-hidden="true">→</span></a></li></ul><a class="lp2-menu-cta" href="/contact/">Demander un échange</a><a class="lp2-menu-tel" href="tel:+33662579274">06 62 57 92 74</a></nav></header>
HTML;
  $k = ['calendrier' => 'sessions', 'sessions' => 'sessions', 'inscription' => 'sessions', 'boutique' => 'boutique', 'references' => 'references'][$actif] ?? '';
  return $k === '' ? $html : str_replace('data-k="' . $k . '"', 'data-k="' . $k . '" aria-current="page"', $html);
}

function footer_html(): string {
  return <<<'HTML'
<footer class="lp2-f"><div class="lp2-f-in"><div class="lp2-f-cols"><div class="lp2-f-id"><a class="lp2-f-brand" href="/" aria-label="Lead Performant, accueil"><svg width="44" height="44" viewBox="0 0 48 48" aria-hidden="true"><path d="M5.2 19.5 A19.4 19.4 0 0 1 42.8 19.5" fill="none" stroke="#C9A45C" stroke-width="2.4" stroke-linecap="round"></path><path d="M42.8 28.5 A19.4 19.4 0 0 1 5.2 28.5" fill="none" stroke="#F6F3EC" stroke-width="2.4" stroke-linecap="round"></path></svg><span>LEAD PERFORMANT</span></a><p class="lp2-f-sig">Parler pour convaincre.<br><span>Dialoguer pour apaiser.</span></p><p class="lp2-f-txt">Conseil et formation en art oratoire, leadership, médiation et gestion des conflits. Paris, Île-de-France, toute la France et à distance.</p><div class="lp2-f-contact"><a href="mailto:leadperformant@gmail.com">leadperformant@gmail.com</a><a href="tel:+33662579274">06 62 57 92 74</a></div></div><nav class="lp2-f-col" aria-label="Pôle Art oratoire et leadership"><p>I · Art oratoire et leadership</p><a href="/formations/eloquence-elite/">Éloquence Élite</a><a href="/formations/eloquence-levier-professionnel/">L'Éloquence comme levier professionnel</a><a href="/formations/parole-d-impact/">Parole d'Impact</a><a href="/formations/leadership-relationnel/">Leadership relationnel et mobilisation</a><a href="/formations/leadership-strategique/">Leadership stratégique et situations complexes</a><a href="/calendrier/">Calendrier des sessions</a></nav><nav class="lp2-f-col" aria-label="Pôle Médiation et gestion des conflits"><p>II · Médiation et gestion des conflits</p><a href="/mediation-gestion-des-conflits/#offres">Boussole relationnelle</a><a href="/mediation-gestion-des-conflits/#offres">L'Art du Désamorçage</a><a href="/mediation-gestion-des-conflits/#offres">Alliance Durable</a><a href="/mediation-gestion-des-conflits/#expertises">Médiation professionnelle et institutionnelle</a><a href="/mediation-gestion-des-conflits/#formations">Formations CNV et gestion des conflits</a><a href="/mediation-gestion-des-conflits/boussole-relationnelle/">Faire le point en ligne</a></nav><nav class="lp2-f-col lp2-f-narrow" aria-label="Lead Performant"><p>Lead Performant</p><a href="/interventions/">Interventions et médias</a><a href="/ressources/">Ressources</a><a href="/fondateur/">Le fondateur</a><a href="/references/">Références et témoignages</a><a href="/boutique/">Boutique</a><a class="lp2-strong" href="/contact/">Demander un échange</a></nav></div><div class="lp2-f-legal"><p>© 2026 Lead Performant · Abdou FLEUR, entrepreneur individuel · TVA non applicable, article 293 B du CGI</p><ul><li><a href="/mentions-legales/">Mentions légales</a></li><li><a href="/confidentialite/">Confidentialité</a></li><li><a href="/cgv/">Conditions générales de vente</a></li><li><a href="/reglement-interieur/">Règlement intérieur</a></li><li><a href="/gestion/" rel="nofollow">Espace de gestion</a></li></ul></div></div></footer>
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
    . '<meta name="theme-color" content="#0B1A33"><link rel="icon" type="image/png" href="/assets/img/favicon-lead-performant.png">'
    . '<link rel="preload" href="/assets/lp/fonts/newsreader-opsz-normal.woff2" as="font" type="font/woff2" crossorigin>'
    . '<link rel="preload" href="/assets/lp/fonts/instrument-sans-wght-normal.woff2" as="font" type="font/woff2" crossorigin>'
    . '<link rel="stylesheet" href="/assets/lp/site.css?v=20261120g"><link rel="stylesheet" href="/assets/lp/plateforme.css?v=1">'
    . ($jsonld ? '<script type="application/ld+json">' . $jsonld . '</script>' : '')
    . '</head><body><a class="skip" href="#contenu">Aller au contenu</a>' . nav_html($actif) . '<main id="contenu">';
}
function page_end(): void {
  echo '</main>' . footer_html() . '<div class="toast" id="toast" role="status" aria-live="polite"></div>'
    . '<script>window.LP=window.LP||{"contact":"/contact/","base":"/","site":"' . h(url('/')) . '","mode":"prod","preview":false,"single":false,"programmes":{}};</script>'
    . '<script src="/assets/lp/site.js?v=20261120g" defer></script></body></html>';
  taches_opportunistes();
}
/* Filet de sécurité : si le planificateur n'a pas tourné depuis 15 minutes, les tâches s'exécutent après l'envoi de la page. */
function taches_opportunistes(): void {
  if (PHP_SAPI === 'cli' || !empty($GLOBALS['LP_TACHES_PREVUES'])) return;
  $last = (string)param('taches_derniere', '');
  if ($last !== '' && ts($last) > time() - 900) return;
  $GLOBALS['LP_TACHES_PREVUES'] = true;
  register_shutdown_function(function () {
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request(); elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
    $fp = @fopen(LP_STORAGE . '/logs/taches.lock', 'c');
    if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) return;
    try { require_once LP_DIR . '/taches.php'; @set_time_limit(120); taches_run(); } catch (Throwable $e) { log_error('Tâches (filet) : ' . $e->getMessage()); }
    flock($fp, LOCK_UN);
  });
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
