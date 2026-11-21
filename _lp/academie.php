<?php
/* Lead Performant Académie : espace apprenant privé, en appui aux sessions en direct.
   Accès par participant et par inscription ; connexion par lien à usage unique, sans mot de passe.
   Les jetons ne sont jamais conservés en clair : seule leur empreinte SHA-256 est stockée. */

const ACADEMIE_COOKIE = 'lp_academie';
const ACADEMIE_TEMPS = ['avant' => 'Avant la formation', 'pendant' => 'Pendant la formation', 'apres' => 'Après la formation'];

/* Réglages modifiables depuis le tableau de bord (table parametres). */
function academie_seed(): void {
  foreach ([
    ['academie_duree_mois', '3'],              // durée d'accès après le dernier jour de session
    ['academie_activation_jours', '7'],        // validité du lien joint au courriel de bienvenue
    ['academie_connexion_minutes', '30'],      // validité d'un lien de reconnexion
    ['academie_session_jours', '30'],          // durée pendant laquelle l'appareil reste reconnu
    ['academie_rappel_fin_jours', '15'],       // préavis avant fermeture des contenus
    ['academie_max_liens_15min', '3'],         // demandes de lien admises par adresse et par quart d'heure
  ] as [$k, $v]) {
    if (db_val('SELECT k FROM parametres WHERE k=?', [$k]) === null) db_exec('INSERT INTO parametres(k,v) VALUES(?,?)', [$k, $v]);
  }
}

function academie_hash(string $t): string { return hash('sha256', $t); }
function academie_email(string $e): string { return mb_strtolower(trim($e)); }

/* ---------- Accès ---------- */

/* Date de fin : dernier jour de session + durée réglée, à 23 h 59. */
function academie_fin_pour_session(array $s): string {
  $mois = max(1, (int)param('academie_duree_mois', 3));
  return date('Y-m-d 23:59:59', strtotime($s['date_fin'] . ' +' . $mois . ' months'));
}

/* Ouvre (ou rouvre) l'accès d'un participant à une inscription. Idempotent : un seul accès par inscription et par adresse. */
function academie_acces_ouvrir(int $inscriptionId, string $email, string $nom, string $origine = 'auto', bool $apresForce = false): array {
  $email = academie_email($email);
  if (!is_email($email)) throw new DomainException('Adresse de participant invalide.');
  $insc = inscription($inscriptionId);
  if (!$insc) throw new DomainException('Inscription introuvable.');
  $s = session_get((int)$insc['session_id']);
  $fin = academie_fin_pour_session($s);
  $a = db_one('SELECT * FROM academie_acces WHERE inscription_id=? AND email=?', [$inscriptionId, $email]);
  if ($a) {
    $row = ['nom' => $nom ?: $a['nom'], 'session_id' => $s['id'], 'fin_at' => $fin, 'retire_at' => null, 'motif' => null];
    if ($apresForce) $row['apres_force'] = 1;
    if ($a['retire_at']) { $row['ouvert_at'] = now(); $row['origine'] = $origine; }
    db_update('academie_acces', (int)$a['id'], $row);
    $id = (int)$a['id'];
  } else {
    $id = db_insert('academie_acces', ['email' => $email, 'nom' => $nom, 'inscription_id' => $inscriptionId, 'session_id' => $s['id'],
      'ouvert_at' => now(), 'fin_at' => $fin, 'origine' => $origine, 'apres_force' => $apresForce ? 1 : 0, 'created_at' => now()]);
  }
  journal($origine === 'manuel' ? 'admin' : 'plateforme', 'academie_acces_ouvert', 'acces:' . $id, $email);
  return db_one('SELECT * FROM academie_acces WHERE id=?', [$id]);
}

function academie_acces_retirer(int $id, string $motif): void {
  db_update('academie_acces', $id, ['retire_at' => now(), 'motif' => mb_substr($motif, 0, 300)]);
  journal('plateforme', 'academie_acces_retire', 'acces:' . $id, $motif);
}

/* Accès d'une adresse, avec la session et le programme, les plus récents d'abord. Les accès retirés sont exclus. */
function academie_acces_pour(string $email): array {
  return db_all("SELECT a.*, i.statut AS insc_statut, i.type AS insc_type, s.date_debut, s.date_fin, s.heure_debut, s.heure_fin, s.modalite, s.lieu,
      s.lien_visio, s.etat AS session_etat, s.slug AS session_slug, p.id AS programme_id, p.titre, p.sous_titre, p.duree_heures
    FROM academie_acces a JOIN inscriptions i ON i.id=a.inscription_id JOIN sessions s ON s.id=a.session_id JOIN programmes p ON p.id=s.programme_id
    WHERE a.email=? AND a.retire_at IS NULL ORDER BY s.date_debut DESC", [academie_email($email)]);
}
function academie_acces_un(int $id, string $email): ?array {
  foreach (academie_acces_pour($email) as $a) if ((int)$a['id'] === $id) return $a;
  return null;
}

function academie_actif(array $a): bool { return empty($a['retire_at']) && ts((string)$a['fin_at']) >= time(); }

/* Volets ouverts : « avant » et « pendant » dès l'ouverture ; « après » (replays) pour les dossiers soldés ou ouverts par l'administration. */
function academie_volets(array $a): array {
  $on = academie_actif($a);
  $apres = $on && (($a['insc_statut'] ?? '') === 'soldee' || (int)$a['apres_force'] === 1);
  return ['avant' => $on, 'pendant' => $on, 'apres' => $apres];
}

/* État affiché de la session pour l'apprenant. */
function academie_phase(array $a): array {
  $today = date('Y-m-d');
  if (!academie_actif($a)) return ['Accès terminé', 'neutre'];
  if ($a['session_etat'] === 'reportee') return ['Session reportée', 'ambre'];
  if ($a['session_etat'] === 'annulee') return ['Session annulée', 'rouge'];
  if ($today < $a['date_debut']) return ['À venir', 'bleu'];
  if ($today <= $a['date_fin']) return ['En cours', 'vert'];
  return ['Terminée', 'neutre'];
}

/* ---------- Liens et sessions de connexion ---------- */

/* Crée un lien à usage unique et renvoie le jeton en clair (à placer dans le courriel, jamais ailleurs). */
function academie_lien_creer(string $email, string $type): string {
  $t = token(32);
  $ttl = $type === 'activation' ? (int)param('academie_activation_jours', 7) * 86400 : (int)param('academie_connexion_minutes', 30) * 60;
  db_insert('academie_liens', ['email' => academie_email($email), 'type' => $type, 'hash' => academie_hash($t),
    'expire_at' => date('Y-m-d H:i:s', time() + $ttl), 'created_at' => now(), 'ip' => client_ip()]);
  return $t;
}

/* Vérifie un lien sans le consommer (page de confirmation, à l'abri des robots de messagerie qui ouvrent les liens). */
function academie_lien_valide(string $t): ?array {
  if ($t === '') return null;
  return db_one("SELECT * FROM academie_liens WHERE hash=? AND type IN ('activation','connexion') AND utilise_at IS NULL AND expire_at > ?", [academie_hash($t), now()]);
}

/* Consomme le lien et ouvre une session sur l'appareil. Renvoie l'adresse connectée, ou null. */
function academie_lien_utiliser(string $t): ?string {
  return db_tx(function () use ($t) {
    $l = academie_lien_valide($t);
    if (!$l) return null;
    if (db_exec('UPDATE academie_liens SET utilise_at=? WHERE id=? AND utilise_at IS NULL', [now(), $l['id']]) !== 1) return null;
    academie_session_ouvrir($l['email']);
    journal('apprenant', 'academie_connexion', 'email:' . $l['email'], $l['type']);
    return $l['email'];
  });
}

function academie_cookie(string $value, int $expires): void {
  if (PHP_SAPI === 'cli' || headers_sent()) return;
  setcookie(ACADEMIE_COOKIE, $value, ['expires' => $expires, 'path' => '/academie/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
}
function academie_session_ouvrir(string $email): void {
  $t = token(32); $exp = time() + (int)param('academie_session_jours', 30) * 86400;
  db_insert('academie_liens', ['email' => academie_email($email), 'type' => 'session', 'hash' => academie_hash($t),
    'expire_at' => date('Y-m-d H:i:s', $exp), 'created_at' => now(), 'ip' => client_ip()]);
  academie_cookie($t, $exp);
  $_COOKIE[ACADEMIE_COOKIE] = $t;
}

/* Apprenant connecté sur cet appareil : ['email' => …, 'nom' => …] ou null. */
function academie_utilisateur(): ?array {
  static $u = false;
  if ($u !== false) return $u;
  $t = (string)($_COOKIE[ACADEMIE_COOKIE] ?? '');
  $l = $t !== '' ? db_one("SELECT * FROM academie_liens WHERE hash=? AND type='session' AND utilise_at IS NULL AND expire_at > ?", [academie_hash($t), now()]) : null;
  if (!$l) return $u = null;
  $nom = (string)db_val("SELECT nom FROM academie_acces WHERE email=? AND nom <> '' ORDER BY id DESC LIMIT 1", [$l['email']]);
  if ($nom === '') { $c = db_one('SELECT * FROM clients WHERE email=?', [$l['email']]); $nom = $c ? client_nom($c) : ''; }
  return $u = ['email' => $l['email'], 'nom' => $nom, 'session_id' => (int)$l['id']];
}

function academie_deconnecter(): void {
  $t = (string)($_COOKIE[ACADEMIE_COOKIE] ?? '');
  if ($t !== '') db_exec("UPDATE academie_liens SET utilise_at=? WHERE hash=? AND type='session'", [now(), academie_hash($t)]);
  academie_cookie('', time() - 3600);
  unset($_COOKIE[ACADEMIE_COOKIE]);
}

/* Limite les demandes de lien : au plus N par adresse et par quart d'heure. */
function academie_demande_admise(string $email): bool {
  $n = (int)db_val("SELECT COUNT(*) FROM academie_liens WHERE email=? AND type='connexion' AND created_at > ?", [academie_email($email), date('Y-m-d H:i:s', time() - 900)]);
  return $n < (int)param('academie_max_liens_15min', 3);
}

/* ---------- Courriels ---------- */

function academie_prenom(string $nom): string { $p = trim(strtok(trim($nom), ' ') ?: ''); return $p !== '' ? $p : 'Madame, Monsieur'; }

/* Demande de connexion. Réponse identique que l'adresse soit connue ou non (traitée par l'appelant). */
function academie_demande_lien(string $email): void {
  $email = academie_email($email);
  if (!is_email($email) || !academie_demande_admise($email)) return;
  $acces = academie_acces_pour($email);
  if ($acces) {
    $t = academie_lien_creer($email, 'connexion');
    $min = (int)param('academie_connexion_minutes', 30);
    mail_queue($email, 'Votre lien de connexion à l’Académie', email_wrap('Lead Performant Académie',
      p_('Bonjour ' . h(academie_prenom((string)$acces[0]['nom'])) . ',')
      . p_('Voici votre lien personnel pour ouvrir votre espace de formation. Il est valable ' . $min . ' minutes et ne sert qu’une fois.')
      . btn(url('/academie/?l=' . $t), 'Ouvrir mon espace')
      . p_('<span style="font-size:12px;color:#6B7280">Si vous n’êtes pas à l’origine de cette demande, ignorez ce message : personne ne peut accéder à votre espace sans ce lien.</span>')));
  } else {
    // Client sans formation suivie (achat en boutique, inscription en cours) : on lui renvoie son espace administratif.
    // Une ligne déjà consommée compte la demande dans la limite de fréquence.
    $c = db_one('SELECT * FROM clients WHERE email=?', [$email]);
    if ($c) mail_queue($c['email'], 'Votre lien d’accès', email_wrap('Votre espace Lead Performant', p_('Bonjour ' . h($c['prenom']) . ',')
      . p_('Voici votre lien personnel pour retrouver vos inscriptions, documents, paiements et achats :') . btn(acces_url((int)$c['id']), 'Ouvrir mon espace')));
    db_insert('academie_liens', ['email' => $email, 'type' => 'connexion', 'hash' => academie_hash(token(8)), 'expire_at' => now(), 'utilise_at' => now(), 'created_at' => now(), 'ip' => client_ip()]);
  }
  mail_flush();
}

/* Courriel de bienvenue à l'ouverture d'un accès (appelé par l'automatisation du lot 3 ou par l'administration). */
function academie_email_bienvenue(array $acces): void {
  $a = academie_acces_un((int)$acces['id'], (string)$acces['email']);
  if (!$a) return;
  $t = academie_lien_creer($a['email'], 'activation');
  $jours = (int)param('academie_activation_jours', 7);
  mail_queue($a['email'], 'Bienvenue dans Lead Performant Académie', email_wrap('Bienvenue dans l’Académie',
    p_('Bonjour ' . h(academie_prenom((string)$a['nom'])) . ',')
    . p_('Votre espace de formation est ouvert pour <strong>' . h($a['titre']) . '</strong>, ' . h(periode_fr($a['date_debut'], $a['date_fin'])) . '.')
    . p_('Vous y trouverez le programme, le travail préparatoire, puis les supports de chaque journée et, après la session, les ressources pour poursuivre.')
    . btn(url('/academie/?l=' . $t), 'Activer mon espace')
    . p_('Ce lien est valable ' . $jours . ' jours et ne sert qu’une fois. Ensuite, il vous suffira de saisir votre adresse sur ' . h(url('/academie/')) . ' pour recevoir un nouveau lien.')
    . p_('Votre accès reste ouvert jusqu’au ' . h(date_fr((string)$a['fin_at'])) . '.')), [], 'academie-bienvenue-' . $a['id']);
  mail_flush();
}

/* ---------- Gabarit ---------- */

function academie_entete(string $titre, string $eyebrow = 'Lead Performant Académie'): void {
  header('X-Robots-Tag: noindex, nofollow');
  header('Cache-Control: private, no-store');
  header('Referrer-Policy: same-origin');
  page_start($titre, 'Espace de formation réservé aux apprenants de Lead Performant.', '', false);
  echo '<section class="section paper"><div class="wrap"><span class="eyebrow">' . h($eyebrow) . '</span>';
}
function academie_pied(): void { echo '</div></section>'; page_end(); }

/* =====================================================================
   Lot 2 : ressources pédagogiques et consultations
   ===================================================================== */

const ACADEMIE_TYPES = ['fichier' => 'Document', 'video' => 'Vidéo', 'lien' => 'Lien'];
const ACADEMIE_EXTS = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'odp', 'jpg', 'jpeg', 'png', 'mp3', 'm4a'];
const ACADEMIE_MIME = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4',
  'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'odt' => 'application/vnd.oasis.opendocument.text', 'odp' => 'application/vnd.oasis.opendocument.presentation'];

function academie_dossier(): string {
  $d = LP_STORAGE . '/academie';
  if (!is_dir($d)) @mkdir($d, 0750, true);
  return $d;
}

/* Ressources publiées visibles pour un accès : celles du programme (communes à toutes ses sessions) puis celles de la session. */
function academie_ressources(array $a, ?string $temps = null, bool $publieesSeulement = true): array {
  $sql = 'SELECT * FROM academie_ressources WHERE (programme_id=? OR session_id=?)' . ($publieesSeulement ? ' AND publie=1' : '') . ($temps ? ' AND temps=?' : '')
    . ' ORDER BY temps, CASE WHEN session_id IS NULL THEN 0 ELSE 1 END, ordre, id';
  $p = [(int)$a['programme_id'], (int)$a['session_id']];
  if ($temps) $p[] = $temps;
  return db_all($sql, $p);
}

/* Une ressource n'est remise qu'à un apprenant dont l'accès est actif, rattaché au même programme ou à la même session, et dont le volet est ouvert. */
function academie_ressource_autorisee(array $a, array $r): bool {
  if ((int)$r['publie'] !== 1 || !isset(ACADEMIE_TEMPS[$r['temps']])) return false;
  $rattachee = ($r['programme_id'] && (int)$r['programme_id'] === (int)$a['programme_id']) || ($r['session_id'] && (int)$r['session_id'] === (int)$a['session_id']);
  return $rattachee && academie_volets($a)[$r['temps']];
}

function academie_consulter(array $a, array $r): void {
  $n = db_exec('UPDATE academie_consultations SET derniere_at=?, n=n+1 WHERE acces_id=? AND ressource_id=?', [now(), $a['id'], $r['id']]);
  if ($n === 0) {
    try { db_insert('academie_consultations', ['acces_id' => $a['id'], 'ressource_id' => $r['id'], 'premiere_at' => now(), 'derniere_at' => now(), 'n' => 1]); }
    catch (Throwable $e) { /* consultation simultanée : déjà comptée */ }
  }
}

/* Adresse de lecteur intégrable pour les services courants ; null si l'adresse doit s'ouvrir hors de la page. */
function academie_video_embed(string $url): ?string {
  $u = trim($url);
  if (!preg_match('~^https://~i', $u)) return null;
  if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,})~', $u, $m)) return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
  if (preg_match('~vimeo\.com/(?:video/)?(\d+)(?:/([0-9a-f]+))?~', $u, $m)) return 'https://player.vimeo.com/video/' . $m[1] . (isset($m[2]) ? '?h=' . $m[2] : '');
  if (preg_match('~^https://(?:iframe|player)\.mediadelivery\.net/(?:embed|play)/(\d+)/([0-9a-f-]+)~i', $u, $m)) return 'https://iframe.mediadelivery.net/embed/' . $m[1] . '/' . $m[2] . '?autoplay=false&preload=true';
  if (preg_match('~\.sharepoint\.com/.*(?:embed\.aspx|/_layouts/15/embed)~i', $u)) return $u;   // Microsoft Stream : code d'intégration
  return null;
}

function academie_ressource_url(array $a, array $r): string { return '/academie/ressource/?a=' . (int)$a['id'] . '&r=' . (int)$r['id']; }

function academie_taille(string $fichier): string {
  $p = academie_dossier() . '/' . basename($fichier);
  if (!is_file($p)) return '';
  $o = filesize($p);
  return $o >= 1048576 ? number_format($o / 1048576, 1, ',', '') . ' Mo' : max(1, (int)round($o / 1024)) . ' Ko';
}

/* =====================================================================
   Lot 3 : ouverture et retrait automatiques, rappels
   ===================================================================== */

const ACADEMIE_RETRAIT = ['retractee', 'annulee', 'remboursee', 'expiree', 'abandonnee'];

/* Aligne les accès d'une inscription sur son état. Appelée après chaque paiement, virement, rétractation, annulation ou report, et par les tâches planifiées.
   Particulier : accès dès l'acompte réglé (confirmée), replays au solde. Entreprise : accès au règlement complet (soldée), ou à l'ouverture manuelle.
   Un accès fermé à la main n'est jamais rouvert automatiquement. Renvoie le nombre d'accès ouverts pour la première fois. */
function academie_synchroniser(int $inscId, bool $courriel = true): int {
  $i = inscription($inscId);
  if (!$i) return 0;
  if (in_array($i['statut'], ACADEMIE_RETRAIT, true)) {
    foreach (db_all('SELECT id FROM academie_acces WHERE inscription_id=? AND retire_at IS NULL', [$inscId]) as $a) academie_acces_retirer((int)$a['id'], 'auto:' . $i['statut']);
    return 0;
  }
  $ouvrir = $i['type'] === 'particulier' ? in_array($i['statut'], ['confirmee', 'soldee'], true) : $i['statut'] === 'soldee';
  $existants = db_all('SELECT * FROM academie_acces WHERE inscription_id=?', [$inscId]);
  // Les accès déjà ouverts suivent l'inscription (report vers une autre session, nouvelle date de fin).
  $s = session_get((int)$i['session_id']);
  foreach ($existants as $a) {
    if ($a['retire_at'] === null && ((int)$a['session_id'] !== (int)$i['session_id'] || $a['fin_at'] !== academie_fin_pour_session($s))) {
      db_update('academie_acces', (int)$a['id'], ['session_id' => $s['id'], 'fin_at' => academie_fin_pour_session($s), 'rappel_fin_at' => null]);
    }
  }
  if (!$ouvrir) return 0;
  $deja = [];
  foreach ($existants as $a) $deja[$a['email']] = $a;
  $nouveaux = 0;
  foreach (participants($i) as $pt) {
    $email = academie_email((string)($pt['email'] ?? ''));
    if (!is_email($email)) continue;
    $a = $deja[$email] ?? null;
    if ($a && $a['retire_at'] === null) continue;
    if ($a && !str_starts_with((string)$a['motif'], 'auto:')) continue;   // fermé à la main : décision de l'administrateur
    $acc = academie_acces_ouvrir($inscId, $email, (string)$pt['nom'], 'auto');
    $nouveaux++;
    if ($courriel) academie_email_bienvenue($acc);
  }
  return $nouveaux;
}

/* Appel protégé depuis le moteur : une anomalie de l'Académie ne doit jamais interrompre un paiement ou une annulation. */
function academie_apres(int $inscId): void {
  try { academie_synchroniser($inscId); }
  catch (Throwable $e) { error_log('Académie : ' . $e->getMessage()); try { journal('plateforme', 'academie_erreur', 'inscription:' . $inscId, mb_substr($e->getMessage(), 0, 250)); } catch (Throwable $e2) {} }
}

/* Ouverture manuelle par l'administrateur (collectivité réglant à service fait, invité) : volet « après » compris. */
function academie_ouvrir_manuellement(int $inscId): int {
  $i = inscription($inscId);
  if (!$i) throw new DomainException('Inscription introuvable.');
  $n = 0;
  foreach (participants($i) as $pt) {
    $email = academie_email((string)($pt['email'] ?? ''));
    if (!is_email($email)) continue;
    $avant = db_one('SELECT * FROM academie_acces WHERE inscription_id=? AND email=?', [$inscId, $email]);
    $acc = academie_acces_ouvrir($inscId, $email, (string)$pt['nom'], 'manuel', true);
    if (!$avant || $avant['retire_at']) { academie_email_bienvenue($acc); $n++; }
  }
  if ($n === 0 && !db_val('SELECT COUNT(*) FROM academie_acces WHERE inscription_id=? AND retire_at IS NULL', [$inscId]))
    throw new DomainException('Aucun participant de ce dossier n’a d’adresse électronique : complétez-la d’abord.');
  return $n;
}

/* Tâches planifiées de l'Académie : rattrapage des ouvertures, rappel avant fermeture. */
function academie_taches(): array {
  $log = [];
  $depuis = date('Y-m-d', strtotime('-' . ((int)param('academie_duree_mois', 3) + 1) . ' months'));
  $n = 0;
  foreach (db_all("SELECT i.id FROM inscriptions i JOIN sessions s ON s.id=i.session_id WHERE s.date_fin >= ? AND (i.statut IN ('confirmee','soldee') OR i.id IN (SELECT inscription_id FROM academie_acces WHERE retire_at IS NULL))", [$depuis]) as $r)
    $n += academie_synchroniser((int)$r['id']);
  if ($n) $log[] = $n . ' accès Académie ouvert(s)';
  $jours = (int)param('academie_rappel_fin_jours', 15);
  $rap = 0;
  foreach (db_all('SELECT * FROM academie_acces WHERE retire_at IS NULL AND rappel_fin_at IS NULL AND fin_at BETWEEN ? AND ?', [now(), date('Y-m-d H:i:s', time() + $jours * 86400)]) as $a) {
    $x = academie_acces_un((int)$a['id'], $a['email']);
    if (!$x) continue;
    mail_queue($a['email'], 'Votre accès à l’Académie se termine le ' . date_fr((string)$a['fin_at']), email_wrap('Lead Performant Académie',
      p_('Bonjour ' . h(academie_prenom((string)$a['nom'])) . ',')
      . p_('Votre accès aux contenus de la formation <strong>' . h($x['titre']) . '</strong> se termine le ' . h(date_fr((string)$a['fin_at'])) . '. Pensez à enregistrer les documents que vous souhaitez conserver.')
      . p_('Vos contrats, factures et attestations restent consultables au-delà de cette date.')
      . btn(url('/academie/'), 'Ouvrir mon espace')), [], 'academie-fin-' . $a['id']);
    db_update('academie_acces', (int)$a['id'], ['rappel_fin_at' => now()]);
    $rap++;
  }
  if ($rap) $log[] = $rap . ' rappel(s) de fin d’accès Académie';
  // Conservation : liens et sessions échus effacés après 30 jours ; suivi des consultations effacé un an après la fin de l'accès.
  db_exec('DELETE FROM academie_liens WHERE expire_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
  db_exec('DELETE FROM academie_consultations WHERE acces_id IN (SELECT id FROM academie_acces WHERE fin_at < ?)', [date('Y-m-d H:i:s', strtotime('-12 months'))]);
  return $log;
}

/* Annonce d'un nouveau contenu aux apprenants actifs concernés. */
function academie_annoncer(array $r, string $message = ''): int {
  $sql = 'SELECT a.* FROM academie_acces a JOIN sessions s ON s.id=a.session_id WHERE a.retire_at IS NULL AND a.fin_at > ? AND ' . ($r['session_id'] ? 'a.session_id=?' : 's.programme_id=?');
  $n = 0;
  foreach (db_all($sql, [now(), $r['session_id'] ?: $r['programme_id']]) as $a) {
    $x = academie_acces_un((int)$a['id'], $a['email']);
    if (!$x || !academie_ressource_autorisee($x, $r)) continue;
    mail_queue($a['email'], 'Nouveau dans votre espace : ' . $r['titre'], email_wrap('Lead Performant Académie',
      p_('Bonjour ' . h(academie_prenom((string)$a['nom'])) . ',')
      . p_('Un nouveau contenu est disponible pour la formation <strong>' . h($x['titre']) . '</strong> : ' . h($r['titre']) . '.')
      . ($message !== '' ? p_(nl2br(h($message))) : '')
      . btn(url('/academie/formation/?a=' . $a['id']), 'Ouvrir la formation')), [], 'academie-annonce-' . $r['id'] . '-' . $a['id']);
    $n++;
  }
  mail_flush();
  return $n;
}
