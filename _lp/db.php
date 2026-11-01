<?php
/* Accès à la base (PDO) et schéma. MySQL en production, SQLite en test. */

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $pdo = new PDO((string)cfg('db.dsn'), (string)cfg('db.user', ''), (string)cfg('db.pass', ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  if (db_is_sqlite()) { $pdo->exec('PRAGMA foreign_keys=ON'); $pdo->exec('PRAGMA busy_timeout=5000'); }
  else { $pdo->exec("SET time_zone='" . date('P') . "'"); }
  return $pdo;
}
function db_is_sqlite(): bool { return str_starts_with((string)cfg('db.dsn'), 'sqlite:'); }
function db_exec(string $sql, array $p = []): int { $s = db()->prepare($sql); $s->execute($p); return $s->rowCount(); }
function db_all(string $sql, array $p = []): array { $s = db()->prepare($sql); $s->execute($p); return $s->fetchAll(); }
function db_one(string $sql, array $p = []): ?array { $s = db()->prepare($sql); $s->execute($p); $r = $s->fetch(); return $r ?: null; }
function db_val(string $sql, array $p = []) { $s = db()->prepare($sql); $s->execute($p); $r = $s->fetchColumn(); return $r === false ? null : $r; }
function db_insert(string $table, array $row): int {
  $cols = array_keys($row);
  db_exec("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($row));
  return (int)db()->lastInsertId();
}
function db_update(string $table, int $id, array $row): void {
  $set = implode(',', array_map(fn($c) => "$c=?", array_keys($row)));
  db_exec("UPDATE $table SET $set WHERE id=?", [...array_values($row), $id]);
}

/* Transaction exclusive : garantit qu'une seule réservation à la fois décompte les places. */
function db_tx(callable $fn) {
  $pdo = db();
  if (db_is_sqlite()) { $pdo->exec('BEGIN IMMEDIATE'); }
  else { $pdo->beginTransaction(); }
  try { $r = $fn(); if (db_is_sqlite()) $pdo->exec('COMMIT'); else $pdo->commit(); return $r; }
  catch (Throwable $e) { if (db_is_sqlite()) $pdo->exec('ROLLBACK'); else $pdo->rollBack(); throw $e; }
}
/* Verrou de ligne sur la session (MySQL : SELECT … FOR UPDATE ; SQLite : la transaction IMMEDIATE suffit). */
function db_lock_session(int $sid): ?array {
  return db_one('SELECT * FROM sessions WHERE id=?' . (db_is_sqlite() ? '' : ' FOR UPDATE'), [$sid]);
}

function db_migrate(): void {
  $pdo = db();
  $pk = db_is_sqlite() ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY';
  $txt = db_is_sqlite() ? 'TEXT' : 'MEDIUMTEXT';
  $eng = db_is_sqlite() ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
  $pdo->exec("CREATE TABLE IF NOT EXISTS meta (k VARCHAR(64) PRIMARY KEY, v $txt)$eng");
  $version = (int)(db_val("SELECT v FROM meta WHERE k='schema'") ?? 0);
  $steps = [];

  $steps[1] = [
    "CREATE TABLE programmes (id $pk, slug VARCHAR(80) UNIQUE, numero VARCHAR(4), titre VARCHAR(200), sous_titre VARCHAR(300),
      duree_jours INT, duree_heures INT, public_vise $txt, prerequis $txt, objectifs $txt, programme $txt, evaluation $txt,
      formateur $txt, prix_particulier INT DEFAULT 0, fiche_url VARCHAR(200), actif INT DEFAULT 1)$eng",
    "CREATE TABLE sessions (id $pk, programme_id INT NOT NULL, slug VARCHAR(120) UNIQUE, date_debut DATE, date_fin DATE,
      heure_debut VARCHAR(5), heure_fin VARCHAR(5), modalite VARCHAR(12), lieu $txt, lien_visio VARCHAR(300),
      capacite INT DEFAULT 15, seuil INT DEFAULT 7, prix_particulier INT DEFAULT 0, etat VARCHAR(12) DEFAULT 'brouillon',
      maintenue INT DEFAULT 0, report_vers INT NULL, note $txt, created_at DATETIME)$eng",
    "CREATE TABLE clients (id $pk, email VARCHAR(190) UNIQUE, prenom VARCHAR(100), nom VARCHAR(100), telephone VARCHAR(40),
      fonction VARCHAR(160), organisation VARCHAR(200), adresse $txt, acces_token VARCHAR(64) UNIQUE, created_at DATETIME)$eng",
    "CREATE TABLE organisations (id $pk, raison_sociale VARCHAR(200), siret VARCHAR(20), adresse $txt, contact_nom VARCHAR(160),
      contact_email VARCHAR(190), contact_tel VARCHAR(40), created_at DATETIME)$eng",
    "CREATE TABLE inscriptions (id $pk, session_id INT NOT NULL, type VARCHAR(12), client_id INT NULL, organisation_id INT NULL,
      places INT DEFAULT 1, statut VARCHAR(24), prix_total INT DEFAULT 0, attentes $txt, handicap $txt, financement $txt,
      participants $txt, bon_commande VARCHAR(120), token VARCHAR(64) UNIQUE, expire_at DATETIME NULL, signe_at DATETIME NULL,
      signature_nom VARCHAR(200), signature_ip VARCHAR(64), signature_ua VARCHAR(300), contrat_hash VARCHAR(64), contrat_doc INT NULL,
      retractation_fin DATETIME NULL, assiduite_validee INT DEFAULT 0, attestation_doc INT NULL, origine_attente INT NULL,
      motif $txt, created_at DATETIME, updated_at DATETIME)$eng",
    "CREATE TABLE presences (id $pk, inscription_id INT, participant VARCHAR(200), jour DATE, demi VARCHAR(2), present INT DEFAULT 0)$eng",
    "CREATE TABLE echeances (id $pk, inscription_id INT NULL, commande_id INT NULL, libelle VARCHAR(200), montant INT, due_at DATETIME,
      expire_at DATETIME NULL, statut VARCHAR(16) DEFAULT 'prevue', mode VARCHAR(12) DEFAULT 'carte', stripe_session VARCHAR(255),
      stripe_payment_intent VARCHAR(255), paye_at DATETIME NULL, facture_id INT NULL, rappel_at DATETIME NULL, token VARCHAR(64) UNIQUE)$eng",
    "CREATE TABLE factures (id $pk, numero VARCHAR(30) UNIQUE, type VARCHAR(8) DEFAULT 'facture', client_id INT NULL, organisation_id INT NULL,
      inscription_id INT NULL, commande_id INT NULL, echeance_id INT NULL, facture_origine INT NULL, libelle $txt, montant INT,
      date_emission DATETIME, doc_id INT NULL, statut_paiement VARCHAR(12) DEFAULT 'payee')$eng",
    "CREATE TABLE documents (id $pk, type VARCHAR(20), chemin VARCHAR(255), nom VARCHAR(200), inscription_id INT NULL, commande_id INT NULL,
      sha256 VARCHAR(64), created_at DATETIME)$eng",
    "CREATE TABLE attente (id $pk, session_id INT, email VARCHAR(190), prenom VARCHAR(100), nom VARCHAR(100), telephone VARCHAR(40),
      statut VARCHAR(12) DEFAULT 'attente', token VARCHAR(64) UNIQUE, invite_at DATETIME NULL, expire_at DATETIME NULL, created_at DATETIME)$eng",
    "CREATE TABLE alertes_sessions (id $pk, programme_id INT NULL, email VARCHAR(190), created_at DATETIME)$eng",
    "CREATE TABLE produits (id $pk, slug VARCHAR(120) UNIQUE, type VARCHAR(10), titre VARCHAR(200), sous_titre VARCHAR(300), description $txt,
      sommaire $txt, prix INT DEFAULT 0, prix_unique_livre INT DEFAULT 0, fichier VARCHAR(255), extrait VARCHAR(255), couverture VARCHAR(255),
      pages INT NULL, duree_minutes INT NULL, delai_jours INT NULL, lien_visio VARCHAR(300), actif INT DEFAULT 0, ordre INT DEFAULT 0)$eng",
    "CREATE TABLE creneaux (id $pk, produit_id INT, debut DATETIME, fin DATETIME, lien_visio VARCHAR(300), statut VARCHAR(12) DEFAULT 'libre',
      commande_id INT NULL, hold_until DATETIME NULL)$eng",
    "CREATE TABLE codes_promo (id $pk, code VARCHAR(40) UNIQUE, pourcentage INT, actif INT DEFAULT 1, fin DATE NULL)$eng",
    "CREATE TABLE commandes (id $pk, produit_id INT, client_id INT, statut VARCHAR(16) DEFAULT 'attente', montant INT, code_promo VARCHAR(40),
      creneau_id INT NULL, renonciation_at DATETIME NULL, renonciation_ip VARCHAR(64), token VARCHAR(64) UNIQUE, telechargements INT DEFAULT 0,
      acces_fin DATETIME NULL, question $txt, question_fichier VARCHAR(255), question_at DATETIME NULL, reponse $txt, reponse_fichier VARCHAR(255),
      reponse_at DATETIME NULL, rappel_at DATETIME NULL, created_at DATETIME, paye_at DATETIME NULL)$eng",
    "CREATE TABLE courriels (id $pk, destinataire VARCHAR(190), sujet VARCHAR(300), corps_html $txt, corps_texte $txt, pieces $txt,
      cle VARCHAR(160) UNIQUE, statut VARCHAR(10) DEFAULT 'attente', essais INT DEFAULT 0, erreur $txt, created_at DATETIME, envoye_at DATETIME NULL)$eng",
    "CREATE TABLE enquetes (id $pk, inscription_id INT, participant VARCHAR(200), type VARCHAR(12), token VARCHAR(64) UNIQUE, reponses $txt,
      envoye_at DATETIME NULL, relance_at DATETIME NULL, repondu_at DATETIME NULL)$eng",
    "CREATE TABLE journal (id $pk, at DATETIME, acteur VARCHAR(60), action VARCHAR(80), cible VARCHAR(80), detail $txt)$eng",
    "CREATE TABLE stripe_events (id VARCHAR(255) PRIMARY KEY, type VARCHAR(80), recu_at DATETIME)$eng",
    "CREATE TABLE vues (id $pk, jour DATE, page VARCHAR(160), n INT DEFAULT 0)$eng",
    "CREATE TABLE parametres (k VARCHAR(64) PRIMARY KEY, v $txt)$eng",
    "CREATE INDEX i_insc_sess ON inscriptions(session_id, statut)",
    "CREATE INDEX i_ech ON echeances(statut, due_at)",
    "CREATE INDEX i_mail ON courriels(statut)",
    "CREATE INDEX i_vues ON vues(jour, page)",
  ];

  foreach ($steps as $v => $sqls) {
    if ($v <= $version) continue;
    foreach ($sqls as $sql) $pdo->exec($sql);
    if ($v === 1) db_seed();
    db_exec(db_is_sqlite() ? "INSERT OR REPLACE INTO meta(k,v) VALUES('schema',?)" : "REPLACE INTO meta(k,v) VALUES('schema',?)", [(string)$v]);
  }
}

/* Programmes du catalogue (textes repris des fiches du site ; prix laissés à zéro, fixés dans le tableau de bord). */
function db_seed(): void {
  $common_eval = "Grille d'observation au premier et au dernier jour, comparaison des prises de parole filmées, questionnaire de satisfaction à chaud et à trente jours, attestation de fin de formation.";
  $formateur = "Abdou Fleur, fondateur de Lead Performant. Master 2 Diplomatie de crise et négociations stratégiques et Master 2 Droit international public (Université Paris-Sud), médiateur diplômé ; ancien conseiller diplomatique à la Délégation du Sénégal auprès de l'UNESCO.";
  db_insert('programmes', ['slug' => 'eloquence-elite', 'numero' => '01', 'titre' => 'Éloquence Élite',
    'sous_titre' => "L'art de prendre la parole avec assurance, présence et impact.", 'duree_jours' => 3, 'duree_heures' => 18,
    'public_vise' => 'Grand public, professionnels, cadres, étudiants et toute personne qui souhaite mieux s’exprimer à l’oral.',
    'prerequis' => 'Aucun prérequis.',
    'objectifs' => "Réguler son trac et s'exprimer avec une voix posée et une présence assumée.\nConstruire un discours structuré en trois temps, avec transitions et argumentation.\nCaptiver un auditoire par le récit et les images mentales.\nImproviser, répondre aux questions et conclure une intervention de manière marquante.",
    'programme' => "Jour 1 · Maîtriser l'instrument : respiration et ancrage, gestion du trac, exercices vocaux, communication non verbale, première prise de parole filmée.\nJour 2 · Structurer le discours : méthode en trois temps, transitions, argumentation des faits aux convictions, art du récit, étude de cas de discours historiques.\nJour 3 · Improviser, répondre, marquer les esprits : improvisation sans notes, répartie et gestion des questions, discours signature filmé, retours individualisés et plan de progrès.",
    'evaluation' => $common_eval, 'formateur' => $formateur, 'prix_particulier' => 0, 'fiche_url' => '/formations/eloquence-elite/', 'actif' => 1]);
  db_insert('programmes', ['slug' => 'eloquence-levier-professionnel', 'numero' => '02', 'titre' => "L'Éloquence comme levier professionnel",
    'sous_titre' => "Faire de sa parole un atout pour accéder à l'emploi et évoluer professionnellement.", 'duree_jours' => 3, 'duree_heures' => 18,
    'public_vise' => 'Personnes en transition professionnelle, en recherche d’emploi, en reconversion ou en prise de poste.',
    'prerequis' => 'Aucun prérequis.',
    'objectifs' => "Se présenter de façon claire, structurée et convaincante en quelques minutes.\nValoriser son parcours et ses compétences face à un recruteur ou un jury.\nGérer le stress et les questions difficiles en entretien.\nAdopter une posture et une communication non verbale professionnelles.",
    'programme' => "Jour 1 · Se connaître pour se présenter : diagnostic de départ, posture, voix, gestion du stress, pitch de présentation filmé.\nJour 2 · Valoriser son parcours : récit professionnel, preuves et réalisations, adaptation au poste et à l'interlocuteur, entretiens simulés.\nJour 3 · Convaincre en situation : questions difficiles, entretien de recrutement ou de jury filmé, retours individualisés et plan de progrès.",
    'evaluation' => $common_eval, 'formateur' => $formateur, 'prix_particulier' => 0, 'fiche_url' => '/formations/eloquence-levier-professionnel/', 'actif' => 1]);
  foreach ([
    ['parole_lieu_defaut', ''], ['nda', ''], ['nda_region', 'Île-de-France'], ['fermeture_particuliers_jours', '14'],
    ['fermeture_entreprises_jours', '7'], ['decision_maintien_jours', '10'], ['presque_complete', '12'],
    ['delai_retractation_jours', '10'], ['delai_acompte_heures', '72'], ['delai_entreprise_jours', '7'],
    ['delai_invitation_heures', '48'], ['acces_telechargement_jours', '30'], ['max_telechargements', '5'],
    ['facture_prefixe', 'LP'], ['visio_outil', 'Google Meet'],
  ] as [$k, $v]) db_exec('INSERT INTO parametres(k,v) VALUES(?,?)', [$k, $v]);
}

function param(string $k, $default = '') {
  if (!isset($GLOBALS['LP_PARAMS'])) { $GLOBALS['LP_PARAMS'] = []; foreach (db_all('SELECT k,v FROM parametres') as $r) $GLOBALS['LP_PARAMS'][$r['k']] = $r['v']; }
  return $GLOBALS['LP_PARAMS'][$k] ?? $default;
}
function param_set(string $k, string $v): void {
  if (db_val('SELECT k FROM parametres WHERE k=?', [$k]) !== null) db_exec('UPDATE parametres SET v=? WHERE k=?', [$v, $k]);
  else db_exec('INSERT INTO parametres(k,v) VALUES(?,?)', [$k, $v]);
  $GLOBALS['LP_PARAMS'][$k] = $v;
}
function journal(string $acteur, string $action, string $cible = '', string $detail = ''): void {
  db_insert('journal', ['at' => now(), 'acteur' => $acteur, 'action' => $action, 'cible' => $cible, 'detail' => mb_substr($detail, 0, 2000)]);
}
