<?php
/* Règles métier : places, inscriptions, échéances, paiements, liste d'attente, entreprises, boutique. */

const ENGAGES = ['reservation', 'retractation', 'acompte_attendu', 'confirmee', 'soldee', 'demande', 'devis_envoye'];
const SIGNES  = ['retractation', 'acompte_attendu', 'confirmee', 'soldee'];
const STATUTS_LIB = [
  'reservation' => 'Inscription en cours', 'retractation' => 'En délai de rétractation', 'acompte_attendu' => 'Acompte attendu',
  'confirmee' => 'Confirmée', 'soldee' => 'Soldée', 'demande' => 'Demande entreprise', 'devis_envoye' => 'Devis envoyé',
  'retractee' => 'Rétractée', 'expiree' => 'Expirée', 'annulee' => 'Annulée', 'remboursee' => 'Remboursée', 'transferee' => 'Transférée',
];

function programme(int $id): ?array { return db_one('SELECT * FROM programmes WHERE id=?', [$id]); }
function session_get(int $id): ?array { return db_one('SELECT * FROM sessions WHERE id=?', [$id]); }
function session_by_slug(string $slug): ?array { return db_one('SELECT * FROM sessions WHERE slug=?', [$slug]); }
function inscription(int $id): ?array { return db_one('SELECT * FROM inscriptions WHERE id=?', [$id]); }
function inscription_by_token(string $t): ?array { return $t === '' ? null : db_one('SELECT * FROM inscriptions WHERE token=?', [$t]); }
function client(int $id): ?array { return db_one('SELECT * FROM clients WHERE id=?', [$id]); }

function session_slug(array $prog, string $date): string { return $prog['slug'] . '-' . $date; }
function session_debut_ts(array $s): int { return ts($s['date_debut'] . ' ' . ($s['heure_debut'] ?: '09:00')); }
function jours_avant(array $s): int { return (int)floor((ts($s['date_debut']) - ts(date('Y-m-d'))) / 86400); }

/* Places engagées : réservations en cours, contrats signés, demandes d'entreprise en cours, invitations de la liste d'attente non échues. */
function places_engagees(int $sid): int {
  $in = "'" . implode("','", ENGAGES) . "'";
  $n = (int)db_val("SELECT COALESCE(SUM(places),0) FROM inscriptions WHERE session_id=? AND statut IN ($in) AND (expire_at IS NULL OR expire_at > ? OR statut NOT IN ('reservation','demande','devis_envoye','acompte_attendu'))", [$sid, now()]);
  $n += (int)db_val("SELECT COUNT(*) FROM attente WHERE session_id=? AND statut='invitee' AND expire_at > ?", [$sid, now()]);
  return $n;
}
function places_signees(int $sid): int {
  $in = "'" . implode("','", SIGNES) . "'";
  return (int)db_val("SELECT COALESCE(SUM(places),0) FROM inscriptions WHERE session_id=? AND type='particulier' AND statut IN ($in)", [$sid])
       + (int)db_val("SELECT COALESCE(SUM(places),0) FROM inscriptions WHERE session_id=? AND type='entreprise' AND statut IN ('confirmee','soldee')", [$sid]);
}
function places_confirmees(int $sid): int {
  return (int)db_val("SELECT COALESCE(SUM(places),0) FROM inscriptions WHERE session_id=? AND statut IN ('confirmee','soldee')", [$sid]);
}

/* Vue publique d'une session : statut affiché, places restantes, inscriptions ouvertes ou non. */
function session_view(array $s): array {
  $eng = places_engagees((int)$s['id']); $rest = max(0, (int)$s['capacite'] - $eng); $j = jours_avant($s);
  $fermP = (int)param('fermeture_particuliers_jours', 14); $fermE = (int)param('fermeture_entreprises_jours', 7);
  $v = ['engagees' => $eng, 'restantes' => $rest, 'jours' => $j, 'ouvert_particulier' => false, 'ouvert_entreprise' => false, 'attente' => false];
  if ($s['etat'] === 'annulee') return $v + ['statut' => 'Session annulée', 'ton' => 'rouge'];
  if ($s['etat'] === 'reportee') return $v + ['statut' => 'Session reportée', 'ton' => 'rouge'];
  if ($s['etat'] !== 'ouverte' || $j < $fermE) return $v + ['statut' => 'Inscriptions fermées', 'ton' => 'neutre'];
  if ($rest <= 0) return array_merge($v, ['attente' => true, 'statut' => 'Complète', 'ton' => 'rouge']);
  $v['ouvert_particulier'] = $j >= $fermP; $v['ouvert_entreprise'] = true;
  $statut = $eng >= (int)param('presque_complete', 12) ? 'Presque complète' : 'Inscriptions ouvertes';
  $ton = $statut === 'Presque complète' ? 'ambre' : 'vert';
  if (!$v['ouvert_particulier']) { $statut = 'Inscriptions entreprises uniquement'; $ton = 'neutre'; }
  return $v + ['statut' => $statut, 'ton' => $ton, 'confirmee' => (int)$s['maintenue'] === 1];
}

function client_upsert(array $d): int {
  $email = mb_strtolower(trim($d['email']));
  $c = db_one('SELECT * FROM clients WHERE email=?', [$email]);
  $row = array_filter(['prenom' => $d['prenom'] ?? null, 'nom' => $d['nom'] ?? null, 'telephone' => $d['telephone'] ?? null,
    'fonction' => $d['fonction'] ?? null, 'organisation' => $d['organisation'] ?? null, 'adresse' => $d['adresse'] ?? null], fn($v) => $v !== null && $v !== '');
  if ($c) { if ($row) db_update('clients', (int)$c['id'], $row); return (int)$c['id']; }
  return db_insert('clients', $row + ['email' => $email, 'acces_token' => token(24), 'created_at' => now()]);
}
function client_nom(array $c): string { return trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')); }
function acces_url(int $clientId): string { $c = client($clientId); return url('/mon-acces/?t=' . $c['acces_token']); }

/* ---------- Parcours particulier ---------- */

/* Réserve une place pendant 30 minutes (étape récapitulatif). Lève une exception si la session est complète ou fermée. */
function reserver_particulier(int $sid, int $clientId, array $extra, ?string $invitation = null): array {
  return db_tx(function () use ($sid, $clientId, $extra, $invitation) {
    $s = db_lock_session($sid);
    if (!$s) throw new DomainException('Session introuvable.');
    $inv = $invitation ? db_one("SELECT * FROM attente WHERE token=? AND session_id=? AND statut='invitee' AND expire_at > ?", [$invitation, $sid, now()]) : null;
    $v = session_view($s);
    if (!$inv) {
      if ($v['restantes'] < 1 && $s['etat'] === 'ouverte') throw new DomainException('Cette session vient d’être complétée. Vous pouvez rejoindre la liste d’attente.');
      if (!$v['ouvert_particulier']) throw new DomainException('Les inscriptions individuelles sont fermées pour cette session.');
    }
    // Une inscription en cours pour ce client sur cette session est réutilisée.
    $exist = db_one("SELECT * FROM inscriptions WHERE session_id=? AND client_id=? AND statut='reservation'", [$sid, $clientId]);
    if ($exist) { db_update('inscriptions', (int)$exist['id'], ['expire_at' => date('Y-m-d H:i:s', time() + 1800), 'attentes' => $extra['attentes'] ?? '', 'handicap' => $extra['handicap'] ?? '', 'updated_at' => now()]); return inscription((int)$exist['id']); }
    $prix = (int)($s['prix_particulier'] ?: (programme((int)$s['programme_id'])['prix_particulier'] ?? 0));
    if ($prix <= 0) throw new DomainException('Le tarif de cette session n’est pas encore publié. Merci de nous contacter.');
    $id = db_insert('inscriptions', ['session_id' => $sid, 'type' => 'particulier', 'client_id' => $clientId, 'places' => 1, 'statut' => 'reservation',
      'prix_total' => $prix, 'attentes' => $extra['attentes'] ?? '', 'handicap' => $extra['handicap'] ?? '', 'token' => token(24),
      'expire_at' => date('Y-m-d H:i:s', time() + 1800), 'origine_attente' => $inv['id'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
    if ($inv) db_update('attente', (int)$inv['id'], ['statut' => 'convertie']);
    return inscription($id);
  });
}

/* Échéancier : 30 % au plus après rétractation (arrondi inférieur), solde en deux parts aux premier et dernier jours. */
function echeancier(int $prix): array {
  $a = intdiv($prix * 30, 100); $reste = $prix - $a; $b = intdiv($reste, 2); $c = $reste - $b;
  return [['Acompte (30 %)', $a], ['Solde, premier jour de formation', $b], ['Solde, dernier jour de formation', $c]];
}

function signer_contrat(array $insc, string $nomSigne): array {
  if ($insc['statut'] !== 'reservation') throw new DomainException('Cette inscription ne peut plus être signée.');
  if ($insc['expire_at'] && ts($insc['expire_at']) < time()) throw new DomainException('Le délai de réservation de 30 minutes est dépassé. Merci de recommencer.');
  $s = session_get((int)$insc['session_id']); $c = client((int)$insc['client_id']);
  $signeAt = now();
  $fin = date('Y-m-d 23:59:59', strtotime($signeAt . ' +' . (int)param('delai_retractation_jours', 10) . ' days'));
  db_update('inscriptions', (int)$insc['id'], ['statut' => 'retractation', 'signe_at' => $signeAt, 'signature_nom' => $nomSigne,
    'signature_ip' => client_ip(), 'signature_ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300),
    'retractation_fin' => $fin, 'expire_at' => null, 'updated_at' => now()]);
  $insc = inscription((int)$insc['id']);
  $doc = doc_contrat($insc);
  db_update('inscriptions', (int)$insc['id'], ['contrat_doc' => $doc['id'], 'contrat_hash' => $doc['sha256']]);
  foreach (echeancier((int)$insc['prix_total']) as $i => [$lib, $m]) {
    $due = $i === 0 ? $fin : ($i === 1 ? $s['date_debut'] . ' ' . ($s['heure_debut'] ?: '09:00') . ':00' : $s['date_fin'] . ' ' . ($s['heure_debut'] ?: '09:00') . ':00');
    db_insert('echeances', ['inscription_id' => $insc['id'], 'libelle' => $lib, 'montant' => $m, 'due_at' => $due, 'statut' => 'prevue', 'token' => token(20)]);
  }
  journal('client', 'contrat_signe', 'inscription:' . $insc['id'], $nomSigne);
  email_inscription_signee(inscription((int)$insc['id']));
  return inscription((int)$insc['id']);
}

function retracter(array $insc): void {
  if ($insc['statut'] !== 'retractation' || ts((string)$insc['retractation_fin']) < time()) throw new DomainException('Le délai de rétractation est échu ou l’inscription n’est pas concernée.');
  db_update('inscriptions', (int)$insc['id'], ['statut' => 'retractee', 'updated_at' => now()]);
  db_exec("UPDATE echeances SET statut='annulee' WHERE inscription_id=? AND statut IN ('prevue','attendue')", [$insc['id']]);
  journal('client', 'retractation', 'inscription:' . $insc['id']);
  email_retractation(inscription((int)$insc['id']));
  liberer_place((int)$insc['session_id']);
}

/* Ouvre une échéance au paiement (lien valable $heures) et envoie le courriel. */
function ouvrir_echeance(array $e, int $heures, string $cleMail): void {
  db_update('echeances', (int)$e['id'], ['statut' => 'attendue', 'expire_at' => date('Y-m-d H:i:s', time() + $heures * 3600)]);
  email_echeance(db_one('SELECT * FROM echeances WHERE id=?', [$e['id']]), $cleMail);
}

/* ---------- Paiements ---------- */

function payer_echeance_url(array $e): string { return url('/paiement/?e=' . $e['token']); }

function demarrer_paiement_echeance(array $e): string {
  if ($e['statut'] === 'payee') throw new DomainException('Cette échéance est déjà réglée.');
  if ($e['statut'] !== 'attendue') throw new DomainException('Cette échéance n’est pas encore ouverte au paiement.');
  if ($e['expire_at'] && ts($e['expire_at']) < time()) throw new DomainException('Ce lien de paiement a expiré. Écrivez-nous pour en recevoir un nouveau.');
  $insc = inscription((int)$e['inscription_id']); $s = session_get((int)$insc['session_id']); $p = programme((int)$s['programme_id']);
  $email = $insc['type'] === 'entreprise' ? db_val('SELECT contact_email FROM organisations WHERE id=?', [$insc['organisation_id']]) : client((int)$insc['client_id'])['email'];
  $label = $p['titre'] . ' · ' . periode_fr($s['date_debut'], $s['date_fin']) . ' · ' . $e['libelle'];
  $cs = stripe_checkout((int)$e['montant'], $label, (string)$email, ['echeance' => (int)$e['id']], url('/paiement/merci.php?e=' . $e['token']), url('/paiement/?e=' . $e['token'] . '&annule=1'), 60);
  db_update('echeances', (int)$e['id'], ['stripe_session' => $cs['id']]);
  return $cs['url'];
}

/* Point unique d'enregistrement d'un paiement, appelé UNIQUEMENT par la notification Stripe vérifiée (ou la simulation de test). */
function paiement_confirme(array $ref, string $sessionId, string $paymentIntent, int $amount): void {
  db_tx(function () use ($ref, $sessionId, $paymentIntent, $amount) {
    if (!empty($ref['echeance'])) {
      $e = db_one('SELECT * FROM echeances WHERE id=?', [(int)$ref['echeance']]);
      if (!$e || $e['statut'] === 'payee') return;
      if ($amount !== (int)$e['montant']) { log_error("Montant inattendu pour l'échéance {$e['id']} : $amount"); return; }
      db_update('echeances', (int)$e['id'], ['statut' => 'payee', 'paye_at' => now(), 'stripe_session' => $sessionId, 'stripe_payment_intent' => $paymentIntent, 'mode' => 'carte']);
      apres_paiement_echeance(db_one('SELECT * FROM echeances WHERE id=?', [$e['id']]));
    }
    if (!empty($ref['commande'])) {
      $c = db_one('SELECT * FROM commandes WHERE id=?', [(int)$ref['commande']]);
      if (!$c || $c['statut'] === 'payee') return;
      if ($amount !== (int)$c['montant']) { log_error("Montant inattendu pour la commande {$c['id']} : $amount"); return; }
      commande_payee($c, $sessionId, $paymentIntent);
    }
  });
  mail_flush();
}

function apres_paiement_echeance(array $e): void {
  $insc = inscription((int)$e['inscription_id']);
  if ($e['facture_id']) {
    // Facture déjà émise (convention d'entreprise) : elle devient acquittée, sans nouvelle numérotation.
    db_update('factures', (int)$e['facture_id'], ['statut_paiement' => 'payee']);
    $f = db_one('SELECT * FROM factures WHERE id=?', [$e['facture_id']]);
  } else {
    $f = facture_creer(['client_id' => $insc['client_id'], 'organisation_id' => $insc['organisation_id'], 'inscription_id' => $insc['id'], 'echeance_id' => $e['id'],
      'libelle' => libelle_inscription($insc) . ' · ' . $e['libelle'], 'montant' => (int)$e['montant'], 'statut_paiement' => 'payee']);
    db_update('echeances', (int)$e['id'], ['facture_id' => $f['id']]);
  }
  if ($insc['type'] === 'particulier' && $insc['statut'] === 'acompte_attendu') {
    db_update('inscriptions', (int)$insc['id'], ['statut' => 'confirmee', 'expire_at' => null, 'updated_at' => now()]);
    email_confirmation_definitive(inscription((int)$insc['id']), $f);
  } else {
    email_recu_paiement($insc, $e, $f);
  }
  $reste = (int)db_val("SELECT COUNT(*) FROM echeances WHERE inscription_id=? AND statut IN ('prevue','attendue')", [$insc['id']]);
  if ($reste === 0 && in_array(inscription((int)$insc['id'])['statut'], ['confirmee'], true)) db_update('inscriptions', (int)$insc['id'], ['statut' => 'soldee']);
  journal('stripe', 'paiement', 'echeance:' . $e['id'], eur((int)$e['montant']));
}

function libelle_inscription(array $insc): string {
  $s = session_get((int)$insc['session_id']); $p = programme((int)$s['programme_id']);
  return 'Formation ' . $p['titre'] . ', ' . periode_fr($s['date_debut'], $s['date_fin']) . ($insc['places'] > 1 ? ' · ' . $insc['places'] . ' participants' : '');
}

/* Rembourse tout ce qui a été payé par carte sur une inscription ; les virements sont signalés pour traitement manuel. */
function rembourser_inscription(array $insc, string $motif): array {
  $res = ['carte' => 0, 'virement' => 0];
  foreach (db_all("SELECT * FROM echeances WHERE inscription_id=? AND statut='payee'", [$insc['id']]) as $e) {
    if ($e['mode'] === 'carte' && $e['stripe_payment_intent']) { stripe_refund($e['stripe_payment_intent']); $res['carte'] += (int)$e['montant']; }
    else { $res['virement'] += (int)$e['montant']; }
    db_update('echeances', (int)$e['id'], ['statut' => 'remboursee']);
    if ($e['facture_id']) avoir_creer((int)$e['facture_id'], $motif);
  }
  db_exec("UPDATE echeances SET statut='annulee' WHERE inscription_id=? AND statut IN ('prevue','attendue')", [$insc['id']]);
  return $res;
}

/* ---------- Factures ---------- */
function facture_numero(string $type = 'facture'): string {
  $prefix = param('facture_prefixe', 'LP') . ($type === 'avoir' ? '-AV' : '') . '-' . date('Y') . '-';
  $last = db_val('SELECT numero FROM factures WHERE numero LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . '%']);
  $n = $last ? (int)substr((string)$last, strlen($prefix)) + 1 : 1;
  return $prefix . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}
function facture_creer(array $d, string $type = 'facture'): array {
  $id = db_insert('factures', $d + ['numero' => facture_numero($type), 'type' => $type, 'date_emission' => now()]);
  $f = db_one('SELECT * FROM factures WHERE id=?', [$id]);
  $doc = doc_facture($f);
  db_update('factures', $id, ['doc_id' => $doc['id']]);
  return db_one('SELECT * FROM factures WHERE id=?', [$id]);
}
function avoir_creer(int $factureId, string $motif): array {
  $f = db_one('SELECT * FROM factures WHERE id=?', [$factureId]);
  return facture_creer(['client_id' => $f['client_id'], 'organisation_id' => $f['organisation_id'], 'inscription_id' => $f['inscription_id'],
    'commande_id' => $f['commande_id'], 'facture_origine' => $f['id'], 'libelle' => 'Avoir sur la facture ' . $f['numero'] . ($motif ? ' · ' . $motif : ''),
    'montant' => -abs((int)$f['montant']), 'statut_paiement' => 'remboursee'], 'avoir');
}

/* ---------- Liste d'attente ---------- */
function attente_ajouter(int $sid, array $d): void {
  if (db_val("SELECT id FROM attente WHERE session_id=? AND email=? AND statut IN ('attente','invitee')", [$sid, $d['email']])) return;
  db_insert('attente', ['session_id' => $sid, 'email' => $d['email'], 'prenom' => $d['prenom'], 'nom' => $d['nom'], 'telephone' => $d['telephone'] ?? '',
    'statut' => 'attente', 'token' => token(20), 'created_at' => now()]);
  email_attente_confirmation($sid, $d);
}
/* Lorsqu'une place se libère : invitation de la première personne en attente (48 h). */
function liberer_place(int $sid): void {
  $s = session_get($sid);
  if (!$s || $s['etat'] !== 'ouverte' || jours_avant($s) < (int)param('fermeture_particuliers_jours', 14)) return;
  while (session_view($s)['restantes'] > 0) {
    $a = db_one("SELECT * FROM attente WHERE session_id=? AND statut='attente' ORDER BY id LIMIT 1", [$sid]);
    if (!$a) return;
    db_update('attente', (int)$a['id'], ['statut' => 'invitee', 'invite_at' => now(), 'expire_at' => date('Y-m-d H:i:s', time() + (int)param('delai_invitation_heures', 48) * 3600)]);
    email_attente_invitation(db_one('SELECT * FROM attente WHERE id=?', [$a['id']]));
  }
}

/* ---------- Entreprises ---------- */
function demande_entreprise(int $sid, array $org, int $places, array $participants, string $financement, string $commentaire): array {
  return db_tx(function () use ($sid, $org, $places, $participants, $financement, $commentaire) {
    $s = db_lock_session($sid); $v = session_view($s);
    if (!$v['ouvert_entreprise']) throw new DomainException('Les inscriptions sont fermées pour cette session.');
    if ($places < 1 || $places > $v['restantes']) throw new DomainException('Il reste ' . $v['restantes'] . ' place(s) sur cette session.');
    $oid = db_insert('organisations', $org + ['created_at' => now()]);
    $id = db_insert('inscriptions', ['session_id' => $sid, 'type' => 'entreprise', 'organisation_id' => $oid, 'places' => $places, 'statut' => 'demande',
      'participants' => json_encode($participants, JSON_UNESCAPED_UNICODE), 'financement' => $financement, 'attentes' => $commentaire, 'token' => token(24),
      'expire_at' => date('Y-m-d H:i:s', time() + (int)param('delai_entreprise_jours', 7) * 86400), 'created_at' => now(), 'updated_at' => now()]);
    $i = inscription($id);
    email_demande_entreprise($i);
    return $i;
  });
}
function organisation(int $id): ?array { return db_one('SELECT * FROM organisations WHERE id=?', [$id]); }

function envoyer_devis(array $insc, int $prixParPlace): void {
  $total = $prixParPlace * (int)$insc['places'];
  db_update('inscriptions', (int)$insc['id'], ['prix_total' => $total, 'statut' => 'devis_envoye',
    'expire_at' => date('Y-m-d H:i:s', max(ts((string)$insc['expire_at']), time() + 7 * 86400)), 'updated_at' => now()]);
  $insc = inscription((int)$insc['id']);
  $devis = doc_devis($insc); $conv = doc_convention($insc);
  email_devis($insc, [$devis, $conv]);
  journal('admin', 'devis_envoye', 'inscription:' . $insc['id'], eur($total));
}
function signer_convention(array $insc, string $nom, string $qualite, string $mode): void {
  if ($insc['statut'] !== 'devis_envoye') throw new DomainException('Cette convention ne peut plus être signée.');
  if (ts((string)$insc['expire_at']) < time()) throw new DomainException('Le délai de validité du devis est dépassé. Écrivez-nous pour le renouveler.');
  db_update('inscriptions', (int)$insc['id'], ['statut' => 'confirmee', 'signe_at' => now(), 'signature_nom' => $nom . ($qualite ? ', ' . $qualite : ''),
    'signature_ip' => client_ip(), 'signature_ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 300), 'expire_at' => null, 'updated_at' => now()]);
  $insc = inscription((int)$insc['id']);
  $conv = doc_convention($insc, true);
  db_update('inscriptions', (int)$insc['id'], ['contrat_doc' => $conv['id'], 'contrat_hash' => $conv['sha256']]);
  $f = facture_creer(['organisation_id' => $insc['organisation_id'], 'inscription_id' => $insc['id'], 'libelle' => libelle_inscription($insc),
    'montant' => (int)$insc['prix_total'], 'statut_paiement' => 'attente']);
  $e = db_insert('echeances', ['inscription_id' => $insc['id'], 'libelle' => 'Règlement de la facture ' . $f['numero'], 'montant' => (int)$insc['prix_total'],
    'due_at' => now(), 'expire_at' => date('Y-m-d H:i:s', session_debut_ts(session_get((int)$insc['session_id']))), 'statut' => 'attendue',
    'mode' => $mode === 'virement' ? 'virement' : 'carte', 'facture_id' => $f['id'], 'token' => token(20)]);
  journal('client', 'convention_signee', 'inscription:' . $insc['id'], $nom);
  email_convention_signee(inscription((int)$insc['id']), $f, db_one('SELECT * FROM echeances WHERE id=?', [$e]), $conv);
}
/* Virement reçu, saisi dans le tableau de bord. La facture existe déjà : on ne la duplique pas. */
function virement_recu(array $e): void {
  db_update('echeances', (int)$e['id'], ['statut' => 'payee', 'paye_at' => now(), 'mode' => 'virement']);
  $insc = inscription((int)$e['inscription_id']);
  if ($e['facture_id']) db_update('factures', (int)$e['facture_id'], ['statut_paiement' => 'payee']);
  if ($insc['statut'] === 'confirmee') db_update('inscriptions', (int)$insc['id'], ['statut' => 'soldee']);
  journal('admin', 'virement_recu', 'echeance:' . $e['id'], eur((int)$e['montant']));
}

/* ---------- Report et annulation de session ---------- */
function annuler_session(array $s, string $motif): array {
  $bilan = ['inscriptions' => 0, 'carte' => 0, 'virement' => 0];
  db_update('sessions', (int)$s['id'], ['etat' => 'annulee']);
  foreach (db_all("SELECT * FROM inscriptions WHERE session_id=? AND statut IN ('" . implode("','", ENGAGES) . "')", [$s['id']]) as $i) {
    $r = rembourser_inscription($i, 'Annulation de la session');
    db_update('inscriptions', (int)$i['id'], ['statut' => $r['carte'] + $r['virement'] > 0 ? 'remboursee' : 'annulee', 'motif' => $motif, 'updated_at' => now()]);
    $bilan['inscriptions']++; $bilan['carte'] += $r['carte']; $bilan['virement'] += $r['virement'];
    email_session_annulee(inscription((int)$i['id']), $r, $motif);
  }
  db_exec("UPDATE attente SET statut='close' WHERE session_id=?", [$s['id']]);
  journal('admin', 'session_annulee', 'session:' . $s['id'], $motif);
  return $bilan;
}
function reporter_session(array $s, array $cible, string $motif): int {
  db_update('sessions', (int)$s['id'], ['etat' => 'reportee', 'report_vers' => $cible['id']]);
  $n = 0;
  foreach (db_all("SELECT * FROM inscriptions WHERE session_id=? AND statut IN ('" . implode("','", SIGNES) . "','demande','devis_envoye')", [$s['id']]) as $i) {
    db_update('inscriptions', (int)$i['id'], ['motif' => 'report:' . $cible['id'], 'updated_at' => now()]);
    email_session_reportee($i, $cible, $motif); $n++;
  }
  journal('admin', 'session_reportee', 'session:' . $s['id'], 'vers ' . $cible['id'] . ' · ' . $motif);
  return $n;
}
/* Choix du participant après un report : garder sa place sur la nouvelle date, ou être remboursé. */
function choix_report(array $insc, bool $garder): string {
  if (!str_starts_with((string)$insc['motif'], 'report:')) throw new DomainException('Aucun report en attente pour cette inscription.');
  $cible = session_get((int)substr((string)$insc['motif'], 7));
  if ($garder) {
    return db_tx(function () use ($insc, $cible) {
      $c = db_lock_session((int)$cible['id']);
      if (places_engagees((int)$c['id']) + (int)$insc['places'] > (int)$c['capacite']) throw new DomainException('La nouvelle session est complète : le remboursement intégral vous est proposé.');
      db_update('inscriptions', (int)$insc['id'], ['session_id' => $c['id'], 'motif' => 'transfert depuis la session ' . $insc['session_id'], 'updated_at' => now()]);
      foreach (db_all("SELECT * FROM echeances WHERE inscription_id=? AND statut IN ('prevue','attendue') AND libelle LIKE 'Solde%'", [$insc['id']]) as $e) {
        db_update('echeances', (int)$e['id'], ['due_at' => (str_contains($e['libelle'], 'premier') ? $c['date_debut'] : $c['date_fin']) . ' ' . ($c['heure_debut'] ?: '09:00') . ':00']);
      }
      journal('client', 'report_accepte', 'inscription:' . $insc['id']);
      return 'transfert';
    });
  }
  $r = rembourser_inscription($insc, 'Report de la session');
  db_update('inscriptions', (int)$insc['id'], ['statut' => 'remboursee', 'motif' => 'remboursement après report', 'updated_at' => now()]);
  email_rembourse($insc, $r);
  journal('client', 'report_refuse', 'inscription:' . $insc['id']);
  return 'rembourse';
}

/* ---------- Boutique ---------- */
function produit(int $id): ?array { return db_one('SELECT * FROM produits WHERE id=?', [$id]); }
const TYPES_PRODUIT = ['ebook' => 'E-book', 'guide' => 'Guide', 'visio' => 'Conseil en visioconférence', 'ecrit' => 'Conseil écrit'];

function prix_avec_code(array $p, string $code): array {
  $code = mb_strtoupper(trim($code));
  if ($code === '' || (int)$p['prix_unique_livre'] === 1) return [(int)$p['prix'], ''];
  $c = db_one('SELECT * FROM codes_promo WHERE code=? AND actif=1 AND (fin IS NULL OR fin >= ?)', [$code, date('Y-m-d')]);
  if (!$c) return [(int)$p['prix'], ''];
  return [max(50, (int)round((int)$p['prix'] * (100 - (int)$c['pourcentage']) / 100)), $code];
}

function commande_creer(array $p, int $clientId, string $code, ?int $creneauId): array {
  return db_tx(function () use ($p, $clientId, $code, $creneauId) {
    [$montant, $code] = prix_avec_code($p, $code);
    if ($p['type'] === 'visio') {
      $cr = db_one("SELECT * FROM creneaux WHERE id=? AND produit_id=? AND (statut='libre' OR (statut='reserve' AND hold_until < ?)) AND debut > ?", [$creneauId, $p['id'], now(), now()]);
      if (!$cr) throw new DomainException('Ce créneau n’est plus disponible. Merci d’en choisir un autre.');
    }
    $id = db_insert('commandes', ['produit_id' => $p['id'], 'client_id' => $clientId, 'statut' => 'attente', 'montant' => $montant, 'code_promo' => $code,
      'creneau_id' => $creneauId, 'renonciation_at' => now(), 'renonciation_ip' => client_ip(), 'token' => token(24), 'created_at' => now()]);
    if ($p['type'] === 'visio') db_update('creneaux', (int)$creneauId, ['statut' => 'reserve', 'commande_id' => $id, 'hold_until' => date('Y-m-d H:i:s', time() + 1800)]);
    return db_one('SELECT * FROM commandes WHERE id=?', [$id]);
  });
}
function commande_payee(array $c, string $sessionId, string $pi): void {
  $p = produit((int)$c['produit_id']);
  db_update('commandes', (int)$c['id'], ['statut' => 'payee', 'paye_at' => now(),
    'acces_fin' => in_array($p['type'], ['ebook', 'guide'], true) ? date('Y-m-d H:i:s', time() + (int)param('acces_telechargement_jours', 30) * 86400) : null]);
  // Le paiement est conservé en échéance pour les remboursements et le chiffre d'affaires.
  db_insert('echeances', ['commande_id' => $c['id'], 'libelle' => $p['titre'], 'montant' => (int)$c['montant'], 'due_at' => now(), 'statut' => 'payee',
    'mode' => 'carte', 'stripe_session' => $sessionId, 'stripe_payment_intent' => $pi, 'paye_at' => now(), 'token' => token(20)]);
  if ($c['creneau_id']) db_update('creneaux', (int)$c['creneau_id'], ['statut' => 'vendu', 'hold_until' => null]);
  $f = facture_creer(['client_id' => $c['client_id'], 'commande_id' => $c['id'], 'libelle' => TYPES_PRODUIT[$p['type']] . ' · ' . $p['titre'], 'montant' => (int)$c['montant'], 'statut_paiement' => 'payee']);
  email_commande(db_one('SELECT * FROM commandes WHERE id=?', [$c['id']]), $f);
  journal('stripe', 'commande_payee', 'commande:' . $c['id'], eur((int)$c['montant']));
}

function pole(string $numero): string { return (int)$numero <= 3 ? 'Pôle I · Art oratoire' : 'Pôle II · Leadership'; }
