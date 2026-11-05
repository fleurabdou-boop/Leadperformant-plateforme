<?php
/* Lead Performant — installation et mise à jour de la plateforme de formation sur le site existant.
   Appel : /_plateforme_src/installer.php?k=<jeton>&mode=install|maj|verif
   Le jeton n'est pas dans le dépôt : seule son empreinte SHA-256 y figure.
   Ordre : contrôles, sauvegarde complète du site (hors du dossier public), copie des fichiers de la plateforme
   sans toucher aux pages existantes, configuration, règles serveur, liens de navigation, pages légales, base de données. */
declare(strict_types=1);
const JETON_SHA256 = '24aaa878c1f343ce3f7904eb9c192f2695cdf1fcea7e7698c719d79a784da609';
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
if (!hash_equals(JETON_SHA256, hash('sha256', (string)($_GET['k'] ?? '')))) { http_response_code(404); echo "Introuvable.\n"; exit; }
@set_time_limit(600);
@ini_set('memory_limit', '512M');

$SRC = __DIR__;
$DST = dirname(__DIR__);                       // public_html
$HORS = dirname($DST);                         // domains/leadperformant.com (non servi)
$SAUV = $HORS . '/sauvegardes-lp';
$VERROU = $DST . '/_lp/storage/.installe';
$mode = (string)($_GET['mode'] ?? 'verif');
$out = [];
function ok(string $m): void { global $out; $out[] = 'OK      ' . $m; }
function info(string $m): void { global $out; $out[] = '        ' . $m; }
function ko(string $m): void { global $out; $out[] = 'ERREUR  ' . $m; }
function fin(int $code = 0): void { global $out; echo implode("\n", $out), "\n"; exit; }
function lire(string $f): string { return is_file($f) ? (string)file_get_contents($f) : ''; }
function ecrire(string $f, string $s): bool { $t = $f . '.tmp-lp'; if (file_put_contents($t, $s, LOCK_EX) === false) return false; return rename($t, $f); }

/* ---------- 1. Contrôles ---------- */
info('Plateforme Lead Performant — mode ' . $mode . ' — ' . date('Y-m-d H:i:s') . ' — PHP ' . PHP_VERSION);
$ext = ['pdo_sqlite', 'mbstring', 'curl', 'gd', 'zip', 'openssl'];
$manque = array_values(array_filter($ext, fn($e) => !extension_loaded($e)));
if ($manque) ko('Extensions PHP absentes : ' . implode(', ', $manque)); else ok('Extensions PHP : ' . implode(', ', $ext));
if (PHP_VERSION_ID < 80100) { ko('PHP 8.1 au moins est requis.'); fin(); }
info('Racine du site : ' . $DST);
info('Installée : ' . (is_file($VERROU) ? 'oui (' . trim(lire($VERROU)) . ')' : 'non'));
foreach (['/opt/alt/php83/usr/bin/php', '/opt/alt/php82/usr/bin/php', '/usr/bin/php', '/usr/local/bin/php'] as $b) { if (@is_executable($b)) { info('PHP en ligne de commande : ' . $b); break; } }
if ($mode === 'verif') fin();
if (!in_array($mode, ['install', 'maj'], true)) { ko('Mode inconnu.'); fin(); }
if ($mode === 'install' && is_file($VERROU)) { ko('La plateforme est déjà installée : utiliser mode=maj.'); fin(); }
if ($mode === 'maj' && !is_file($VERROU)) { ko('La plateforme n’est pas installée : utiliser mode=install.'); fin(); }
if (in_array('pdo_sqlite', $manque, true) || in_array('zip', $manque, true)) { ko('Installation interrompue : extensions indispensables absentes.'); fin(); }

/* ---------- 2. Sauvegarde complète du site, hors du dossier public ---------- */
if (!is_dir($SAUV) && !@mkdir($SAUV, 0750, true)) { ko('Impossible de créer ' . $SAUV); fin(); }
$zipf = $SAUV . '/site-' . date('Ymd-His') . '-' . $mode . '.zip';
$z = new ZipArchive();
if ($z->open($zipf, ZipArchive::CREATE | ZipArchive::EXCL) !== true) { ko('Sauvegarde : création du fichier impossible.'); fin(); }
$n = 0; $taille = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($DST, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::LEAVES_ONLY);
foreach ($it as $f) {
  $p = $f->getPathname(); $rel = substr($p, strlen($DST) + 1);
  if (str_starts_with($rel, '_plateforme_src/') || !$f->isFile() || $f->isLink()) continue;
  if ($z->addFile($p, $rel)) { $n++; $taille += $f->getSize(); }
}
$z->close();
$zz = new ZipArchive();
if ($n === 0 || $zz->open($zipf) !== true || $zz->numFiles !== $n) { ko('Sauvegarde incomplète : installation interrompue, rien n’a été modifié.'); fin(); }
$zz->close();
ok('Sauvegarde : ' . $n . ' fichiers (' . round($taille / 1048576, 1) . ' Mo) dans ' . $zipf);

/* ---------- 3. Copie des fichiers de la plateforme ---------- */
$exclus = ['.git', '.htaccess', 'installer.php', 'htaccess-ajouts.conf', '.gitignore', 'README.md', 'refonte', 'publier.php'];
$copies = 0; $inchanges = 0; $conflits = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($SRC, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
$liste = [];
foreach ($it as $f) {
  $rel = substr($f->getPathname(), strlen($SRC) + 1);
  $premier = explode('/', $rel)[0];
  if (in_array($premier, $exclus, true) && !str_contains($rel, '/')) continue;
  if (str_starts_with($rel, '.git/')) continue;
  if ($f->isFile()) $liste[] = $rel;
}
$manifeste_ancien = json_decode(lire($DST . '/_lp/storage/manifeste.json'), true) ?: [];
foreach ($liste as $rel) {
  $cible = $DST . '/' . $rel;
  if (is_file($cible) && !in_array($rel, $manifeste_ancien, true) && hash_file('sha256', $cible) !== hash_file('sha256', $SRC . '/' . $rel)) $conflits[] = $rel;
}
if ($conflits) { ko('Fichiers existants qui seraient remplacés (non installés par la plateforme) : ' . implode(', ', $conflits) . '. Installation interrompue.'); fin(); }
foreach ($liste as $rel) {
  $cible = $DST . '/' . $rel;
  if (!is_dir(dirname($cible))) @mkdir(dirname($cible), 0755, true);
  if (is_file($cible) && hash_file('sha256', $cible) === hash_file('sha256', $SRC . '/' . $rel)) { $inchanges++; continue; }
  if (!copy($SRC . '/' . $rel, $cible . '.tmp-lp') || !rename($cible . '.tmp-lp', $cible)) { ko('Copie impossible : ' . $rel); fin(); }
  $copies++;
}
foreach (['logs', 'docs', 'produits', 'uploads', 'outbox'] as $d) if (!is_dir($DST . "/_lp/storage/$d")) @mkdir($DST . "/_lp/storage/$d", 0750, true);
ecrire($DST . '/_lp/storage/.htaccess', "Require all denied\n");
ecrire($DST . '/_lp/storage/manifeste.json', json_encode($liste, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
ok("Fichiers de la plateforme : $copies copiés, $inchanges déjà à jour, aucune page existante remplacée");

/* ---------- 4. Configuration (créée une seule fois ; les clés se saisissent ensuite dans le tableau de bord) ---------- */
$cfgf = $DST . '/_lp/config.php';
if (!is_file($cfgf)) {
  $cfg = [
    'site_url' => 'https://www.leadperformant.com',
    'env' => 'prod',
    'db' => ['dsn' => 'sqlite:' . $DST . '/_lp/storage/lp.sqlite', 'user' => '', 'pass' => ''],
    'stripe' => ['secret' => '', 'webhook_secret' => ''],
    'mail' => ['from' => 'site@leadperformant.com', 'from_name' => 'Lead Performant', 'reply_to' => 'leadperformant@gmail.com', 'admin' => 'leadperformant@gmail.com',
      'smtp_host' => 'smtp.hostinger.com', 'smtp_port' => 465, 'smtp_user' => 'site@leadperformant.com', 'smtp_pass' => '', 'transport' => 'mail'],
    'cron_key' => bin2hex(random_bytes(24)),
    'secret' => bin2hex(random_bytes(32)),
  ];
  if (!ecrire($cfgf, "<?php\n/* Configuration de production, générée par l'installateur le " . date('d/m/Y H:i') . ". Ne pas versionner. */\nreturn " . var_export($cfg, true) . ";\n")) { ko('Écriture de la configuration impossible.'); fin(); }
  @chmod($cfgf, 0640);
  ok('Configuration de production créée (base SQLite hors d’accès public, paiement inactif tant que la clé Stripe n’est pas saisie)');
} else ok('Configuration existante conservée');

/* ---------- 5. Règles serveur ---------- */
$ht = lire($DST . '/.htaccess');
if ($ht === '') { ko('.htaccess introuvable à la racine.'); fin(); }
if (!str_contains($ht, '# 6. Plateforme de formation')) {
  $regles = "# 6. Plateforme de formation : dossiers internes fermés, adresses lisibles des sessions et des produits\n"
    . "RewriteRule ^_lp(/|$) - [F,L]\n"
    . "RewriteRule ^gestion/_admin\\.php$ - [F,L]\n"
    . "RewriteRule ^sessions/([a-z0-9-]+)/?$ /sessions/index.php?s=$1 [L,QSA]\n"
    . "RewriteRule ^boutique/([a-z0-9-]+)/?$ /boutique/produit.php?p=$1 [L,QSA]\n";
  $ancre = "RewriteRule ^a-propos/?$ https://www.leadperformant.com/ [L,R=302]\n";
  if (str_contains($ht, $ancre)) $ht2 = str_replace($ancre, $ancre . $regles, $ht);
  else { $pos = strpos($ht, '</IfModule>'); if ($pos === false) { ko('Bloc de réécriture introuvable dans .htaccess.'); fin(); } $ht2 = substr($ht, 0, $pos) . $regles . substr($ht, $pos); }
  $ht2 = str_replace('<FilesMatch "\.(zip|csv|bak|log|py|md)$">', '<FilesMatch "\.(zip|csv|bak|log|py|md|sqlite|lock)$">', $ht2);
  ecrire($DST . '/.htaccess', $ht2) ? ok('.htaccess : règles de la plateforme ajoutées après la règle 5') : ko('.htaccess non modifié');
} else ok('.htaccess : règles déjà présentes');

/* ---------- 6. Navigation et pied de page des pages existantes ---------- */
function patch_page(string $h): string {
  if (!str_contains($h, 'href="/interventions/">Mes interventions')) return $h;
  if (!preg_match('~<a class="nav-link" href="/calendrier/"~', $h))
    $h = preg_replace('~(<div class="nav-item[^"]*"><a class="nav-link" href="/interventions/")~', '<div class="nav-item "><a class="nav-link" href="/calendrier/">Calendrier</a></div>$1', $h, 1);
  if (!preg_match('~<a class="nav-link" href="/boutique/"~', $h))
    $h = preg_replace('~(<div class="nav-item[^"]*"><a class="nav-link" href="/contact/")~', '<div class="nav-item "><a class="nav-link" href="/boutique/">Boutique</a></div>$1', $h, 1);
  if (!str_contains($h, 'Calendrier des sessions</a></li>'))
    $h = str_replace('<li><a href="/laboratoire.html">Laboratoire de la parole</a></li>', '<li><a href="/laboratoire.html">Laboratoire de la parole</a></li><li><a href="/calendrier/">Calendrier des sessions</a></li>', $h);
  if (!str_contains($h, '<li><a href="/boutique/">'))
    $h = str_replace('<li><a href="/ressources/">Ressources</a></li>', '<li><a href="/ressources/">Ressources</a></li><li><a href="/boutique/">Boutique</a></li>', $h);
  if (!str_contains($h, 'href="/cgv/"'))
    $h = str_replace('<a href="/admin/">Espace de publication</a>', '<a href="/cgv/">Conditions générales de vente</a><a href="/gestion/">Espace de gestion</a>', $h);
  return $h;
}
$pages = 0; $vus = 0;
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($DST, FilesystemIterator::SKIP_DOTS));
$ignores = ['_plateforme_src', '_lp', 'uploads', 'assets', 'admin', 'espace-admin', 'api'];
foreach ($it as $f) {
  $rel = substr($f->getPathname(), strlen($DST) + 1);
  if (in_array(explode('/', $rel)[0], $ignores, true) || in_array($rel, $liste, true)) continue;
  if (!preg_match('/\.(html|php)$/', $rel) || !$f->isFile()) continue;
  $h = lire($f->getPathname()); $h2 = patch_page($h);
  if (str_contains($h, 'href="/interventions/">Mes interventions')) $vus++;
  if ($h2 !== $h && ecrire($f->getPathname(), $h2)) $pages++;
}
ok("Navigation : Calendrier et Boutique ajoutés, CGV et espace de gestion en pied de page ($pages pages mises à jour sur $vus)");

/* ---------- 7. Accueil : réponse sur le financement, sans revendiquer de prise en charge non établie ---------- */
$idx = lire($DST . '/index.html');
$neuf = "Les entreprises et les structures peuvent financer la formation sur leur budget propre, notamment au titre du plan de développement des compétences, sur la base de notre devis et de notre convention. Les financements mutualisés ou publics (OPCO, CPF, dispositifs d'insertion) obéissent à des conditions de certification que nous examinons avec vous au premier échange, avec les documents que nous pouvons fournir (programme, devis, convention, attestation).";
if ($idx !== '' && !str_contains($idx, 'sur leur budget propre')) {
  $idx2 = preg_replace('~Cela dépend de votre structure et de votre financeur.*?attestation\)\.~su', $neuf, $idx, -1, $cnt);
  if ($cnt && ecrire($DST . '/index.html', $idx2)) ok("Accueil : réponse « Les formations sont-elles finançables ? » corrigée ($cnt occurrence(s))"); else info('Accueil : texte du financement non trouvé, inchangé');
} else ok('Accueil : réponse sur le financement déjà corrigée');

/* ---------- 8. Pages des deux programmes ouverts : accès aux dates ---------- */
foreach (['eloquence-elite' => 'Éloquence Élite', 'eloquence-levier-professionnel' => 'L\'Éloquence comme levier professionnel'] as $slug => $titre) {
  $f = $DST . "/formations/$slug/index.html"; $h = lire($f);
  if ($h === '' || str_contains($h, 'id="prochaines-dates"')) { info("Programme $slug : " . ($h === '' ? 'page absente' : 'bloc déjà présent')); continue; }
  $bloc = '<section class="section" id="prochaines-dates"><div class="wrap"><span class="eyebrow">Sessions en groupe</span><h2>Prochaines dates</h2>'
    . '<p class="lede" style="margin-top:14px">' . htmlspecialchars($titre) . ' se déroule en trois jours, dix-huit heures, en groupe de sept à quinze participants. Les particuliers et les entreprises s’inscrivent en ligne ; le tarif est présenté avant tout engagement.</p>'
    . '<div class="actions" style="margin-top:26px"><a class="btn" href="/calendrier/?programme=' . $slug . '">Voir les dates et s’inscrire</a><a class="link" href="/contact/">Organiser une session pour votre structure <span class="arrow" aria-hidden="true">→</span></a></div></div></section>' . "\n";
  $pos = strrpos($h, '</main>');
  if ($pos !== false && ecrire($f, substr($h, 0, $pos) . $bloc . substr($h, $pos))) ok("Programme $slug : bloc « Prochaines dates » ajouté");
}

/* ---------- 9. Plan du site ---------- */
$sm = lire($DST . '/sitemap.xml');
if ($sm !== '') {
  $add = '';
  foreach (['/calendrier/', '/boutique/', '/cgv/', '/reglement-interieur/'] as $u) if (!str_contains($sm, 'https://www.leadperformant.com' . $u . '<')) $add .= "  <url><loc>https://www.leadperformant.com$u</loc><lastmod>" . date('Y-m-d') . "</lastmod></url>\n";
  if ($add !== '' && ecrire($DST . '/sitemap.xml', str_replace('</urlset>', $add . '</urlset>', $sm))) ok('Plan du site : nouvelles pages déclarées'); else ok('Plan du site : à jour');
}
$rb = lire($DST . '/robots.txt');
if ($rb !== '' && !str_contains($rb, '/gestion/')) {
  $rb2 = preg_replace('~(User-agent:\s*\*\s*\n)~i', "$1Disallow: /gestion/\nDisallow: /mon-acces/\nDisallow: /paiement/\nDisallow: /telecharger/\n", $rb, 1);
  if ($rb2 !== $rb && ecrire($DST . '/robots.txt', $rb2)) ok('robots.txt : espaces privés exclus de l’indexation');
}

/* ---------- 10. Politique de confidentialité ---------- */
$cf = $DST . '/confidentialite/index.html'; $h = lire($cf);
if ($h !== '' && !str_contains($h, 'id="lp-inscriptions"')) {
  $sec = '<h2 id="lp-inscriptions">4 ter. Inscriptions, paiements et boutique</h2><ul>'
    . '<li>Données collectées : identité, coordonnées, adresse postale, le cas échéant organisation et fonction, programme et session choisis, signature électronique des documents (nom saisi, date et adresse IP), présence et évaluations, réponses aux questionnaires de satisfaction, commandes de la boutique.</li>'
    . '<li>Finalités : gérer votre inscription ou votre commande, établir les documents contractuels (contrat, convention, devis, convocation, attestation), facturer, organiser la session, mesurer la satisfaction et satisfaire aux obligations des prestataires de formation, dont le bilan pédagogique et financier.</li>'
    . '<li>Bases légales : exécution du contrat ou mesures précontractuelles prises à votre demande ; obligations légales comptables et de la formation professionnelle ; intérêt légitime pour les rappels et les questionnaires de satisfaction.</li>'
    . '<li>Paiement : il est traité par Stripe (Stripe Payments Europe Ltd), prestataire de services de paiement. Les données de carte sont saisies sur la page sécurisée de Stripe ; Lead Performant ne les reçoit ni ne les conserve.</li>'
    . '<li>Courriels : les messages liés à votre inscription ou à votre commande (confirmations, rappels, liens de paiement, documents) sont envoyés depuis le serveur du site, hébergé par Hostinger.</li>'
    . '<li>Alertes et liste d’attente : votre adresse ne sert qu’à vous prévenir d’une nouvelle date ou d’une place disponible ; vous pouvez y mettre fin à tout moment en écrivant à leadperformant@gmail.com.</li>'
    . '<li>Conservation : factures et pièces comptables, dix ans ; documents contractuels et de formation, cinq ans après la fin de la formation ; alertes et liste d’attente, jusqu’à la session concernée ou votre demande, et au plus deux ans.</li>'
    . '<li>Un cookie de session strictement nécessaire protège les formulaires d’inscription et de commande ; il est supprimé à la fermeture du navigateur. La fréquentation des pages de la plateforme est comptée de façon agrégée, sans cookie ni donnée personnelle.</li></ul>' . "\n";
  $h2 = str_replace('<h2>5. Vidéos YouTube</h2>', $sec . '<h2>5. Vidéos YouTube</h2>', $h, $c1);
  if (!$c1) { $p = strrpos($h, '<h2>6.'); $h2 = $p !== false ? substr($h, 0, $p) . $sec . substr($h, $p) : $h; }
  $h2 = preg_replace('~Mise à jour le [^<]+?\.~u', 'Mise à jour le 1er novembre 2026.', $h2, 1);
  if ($h2 !== $h && ecrire($cf, $h2)) ok('Confidentialité : section inscriptions, paiements et boutique ajoutée'); else info('Confidentialité : emplacement non trouvé, inchangée');
} else ok('Confidentialité : déjà à jour');

/* ---------- 11. Base de données et message de contrôle ---------- */
try {
  require $DST . '/_lp/bootstrap.php';
  ok('Base de données prête : ' . db_val('SELECT COUNT(*) FROM programmes') . ' programmes, schéma ' . db_val("SELECT v FROM meta WHERE k='schema'"));
  if ($mode === 'install') {
    mail_queue((string)cfg('mail.admin'), 'Plateforme de formation en ligne', email_wrap('La plateforme est en ligne', p_('Le calendrier, les inscriptions, la boutique et le tableau de bord sont installés sur www.leadperformant.com.') . p_('Pour vous connecter au tableau de bord : <a href="' . url('/gestion/') . '">' . url('/gestion/') . '</a>, puis « Recevoir le code ».') . p_('Le paiement en ligne restera fermé tant que la clé Stripe n’aura pas été saisie dans Paramètres.')), [], 'installation-' . date('Ymd'));
    mail_flush();
    $st = db_one("SELECT statut, erreur FROM courriels ORDER BY id DESC LIMIT 1");
    $st && $st['statut'] === 'envoye' ? ok('Message de contrôle envoyé à ' . cfg('mail.admin')) : ko('Message de contrôle non envoyé : ' . ($st['erreur'] ?? 'raison inconnue'));
  }
  info('Clé des tâches planifiées (adresse de secours) : /taches/?k=' . substr((string)cfg('cron_key'), 0, 6) . '… (complète dans _lp/config.php)');
} catch (Throwable $e) { ko('Base de données : ' . $e->getMessage()); fin(); }

ecrire($VERROU, date('Y-m-d H:i:s') . ' ' . $mode . "\n");
ok('Terminé. Sauvegarde restaurable : ' . basename($zipf));
fin();
