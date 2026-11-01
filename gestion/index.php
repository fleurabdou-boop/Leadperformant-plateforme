<?php
/* Tableau de bord de Lead Performant : une seule page, une vue par rubrique (?v=). */
require __DIR__ . '/_admin.php';

$v = get('v', 30) ?: 'accueil';
$id = (int)get('id');
$P = $_SERVER['REQUEST_METHOD'] === 'POST';
if ($P && !a_ok()) { a_flash('La page a expiré : action non effectuée.'); a_go('v=' . $v . ($id ? '&id=' . $id : '')); }
$act = post('a', 40);

try {
/* ================= Actions ================= */
if ($P) switch ($v . ':' . $act) {
  case 'logout:': session_destroy(); redirect('/gestion/');

  case 'programme:save':
    $row = ['titre' => post('titre', 200), 'sous_titre' => post('sous_titre', 300), 'numero' => post('numero', 4), 'duree_jours' => (int)post('duree_jours'), 'duree_heures' => (int)post('duree_heures'),
      'public_vise' => post('public_vise', 2000), 'prerequis' => post('prerequis', 1000), 'objectifs' => post('objectifs', 4000), 'programme' => post('programme', 8000), 'evaluation' => post('evaluation', 2000),
      'formateur' => post('formateur', 2000), 'prix_particulier' => to_cents(post('prix_particulier', 20)), 'fiche_url' => post('fiche_url', 200), 'actif' => post('actif') === '1' ? 1 : 0];
    if ($id) db_update('programmes', $id, $row); else { $row['slug'] = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $row['titre']))), '-'); $id = db_insert('programmes', $row); }
    journal('admin', 'programme_enregistre', 'programme:' . $id); a_flash('Programme enregistré.'); a_go('v=programme&id=' . $id);

  case 'session:save':
    $pr = programme((int)post('programme_id'));
    $d1 = post('date_debut', 10); $d2 = post('date_fin', 10) ?: $d1;
    if (!$pr || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d1) || $d2 < $d1) throw new DomainException('Programme et dates valides requis.');
    $row = ['programme_id' => $pr['id'], 'date_debut' => $d1, 'date_fin' => $d2, 'heure_debut' => post('heure_debut', 5) ?: '09:00', 'heure_fin' => post('heure_fin', 5) ?: '17:00',
      'modalite' => post('modalite') === 'distance' ? 'distance' : 'presentiel', 'lieu' => post('lieu', 600), 'lien_visio' => post('lien_visio', 300),
      'capacite' => max(1, min(15, (int)post('capacite') ?: 15)), 'seuil' => max(1, (int)post('seuil') ?: 7), 'prix_particulier' => to_cents(post('prix_particulier', 20)), 'etat' => in_array(post('etat'), ['brouillon', 'ouverte', 'fermee'], true) ? post('etat') : 'brouillon', 'note' => post('note', 2000)];
    if ($id) { $old = session_get($id); if (in_array($old['etat'], ['annulee', 'reportee'], true)) unset($row['etat']); db_update('sessions', $id, $row); }
    else { $slug = session_slug($pr, $d1); $k = 2; while (session_by_slug($slug)) $slug = session_slug($pr, $d1) . '-' . $k++; $id = db_insert('sessions', $row + ['slug' => $slug, 'created_at' => now()]); }
    journal('admin', 'session_enregistree', 'session:' . $id); a_flash('Session enregistrée.'); a_go('v=session&id=' . $id);

  case 'session:maintenir': db_update('sessions', $id, ['maintenue' => 1]); journal('admin', 'session_maintenue', 'session:' . $id); a_flash('Session maintenue.'); a_go('v=session&id=' . $id);
  case 'session:annuler':
    $b = annuler_session(session_get($id), post('motif', 300)); mail_flush();
    a_flash('Session annulée : ' . $b['inscriptions'] . ' inscription(s) traitée(s), ' . eur($b['carte']) . ' remboursés par carte' . ($b['virement'] ? ', ' . eur($b['virement']) . ' à rembourser par virement' : '') . '.'); a_go('v=session&id=' . $id);
  case 'session:reporter':
    $cible = session_get((int)post('cible')); if (!$cible || (int)$cible['id'] === $id) throw new DomainException('Choisissez la nouvelle session (créez-la d’abord si besoin).');
    $n = reporter_session(session_get($id), $cible, post('motif', 300)); mail_flush(); a_flash($n . ' participant(s) invité(s) à choisir entre la nouvelle date et le remboursement.'); a_go('v=session&id=' . $id);
  case 'session:message':
    $s = session_get($id); $n = 0;
    foreach (db_all("SELECT * FROM inscriptions WHERE session_id=? AND statut IN ('retractation','acompte_attendu','confirmee','soldee')", [$id]) as $i)
      foreach (participants($i) as $pt) if (!empty($pt['email'])) { mail_queue($pt['email'], post('sujet', 200), email_wrap(post('sujet', 200), p_('Bonjour ' . h($pt['nom']) . ',') . nl2br(h(post('corps', 6000))) . bloc_session_html($s))); $n++; }
    mail_flush(); journal('admin', 'message_groupe', 'session:' . $id, post('sujet', 200)); a_flash($n . ' message(s) envoyé(s).'); a_go('v=session&id=' . $id);
  case 'session:emargement':
    $s = session_get($id);
    db_exec('DELETE FROM presences WHERE inscription_id IN (SELECT id FROM inscriptions WHERE session_id=?)', [$id]);
    foreach ((array)($_POST['p'] ?? []) as $key => $val) { [$iid, $k, $jour, $demi] = explode('|', (string)$key); $pt = participants(inscription((int)$iid))[(int)$k] ?? null; if ($pt) db_insert('presences', ['inscription_id' => (int)$iid, 'participant' => $pt['nom'], 'jour' => $jour, 'demi' => $demi, 'present' => 1]); }
    journal('admin', 'emargement', 'session:' . $id); a_flash('Émargement enregistré.'); a_go('v=session&id=' . $id . '#emargement');
  case 'session:attestations':
    $s = session_get($id); $p = programme((int)$s['programme_id']); $n = 0;
    $demiH = $p['duree_heures'] / max(1, 2 * $p['duree_jours']);
    foreach (db_all("SELECT * FROM inscriptions WHERE session_id=? AND statut IN ('confirmee','soldee')", [$id]) as $i) {
      foreach (participants($i) as $k => $pt) {
        $nb = (int)db_val('SELECT COUNT(*) FROM presences WHERE inscription_id=? AND participant=? AND present=1', [$i['id'], $pt['nom']]);
        if ($nb === 0) continue;
        $doc = doc_attestation($i, $pt['nom'], (int)round($nb * $demiH));
        if ($i['type'] === 'particulier') db_update('inscriptions', (int)$i['id'], ['attestation_doc' => $doc['id'], 'assiduite_validee' => 1]);
        if (!empty($pt['email'])) mail_queue($pt['email'], 'Votre attestation de fin de formation', email_wrap('Votre attestation', p_('Bonjour ' . h($pt['nom']) . ',') . p_('Veuillez trouver ci-joint votre attestation de fin de formation. Merci encore pour votre engagement.')), [doc_piece($doc)], 'attestation-' . $i['id'] . '-' . $k);
        if ($i['type'] === 'entreprise') { $o = organisation((int)$i['organisation_id']); mail_queue($o['contact_email'], 'Attestation · ' . $pt['nom'], email_wrap('Attestation de fin de formation', p_('Veuillez trouver ci-joint l’attestation de ' . h($pt['nom']) . '.')), [doc_piece($doc)], 'attestation-ent-' . $i['id'] . '-' . $k); }
        $n++;
      }
    }
    mail_flush(); a_flash($n . ' attestation(s) établie(s) et envoyée(s), au prorata des demi-journées émargées.'); a_go('v=session&id=' . $id);

  case 'inscription:devis':
    $prix = to_cents(post('prix_place', 20)); if ($prix <= 0) throw new DomainException('Indiquez le prix par participant.');
    envoyer_devis(inscription($id), $prix); mail_flush(); a_flash('Devis et convention envoyés.'); a_go('v=inscription&id=' . $id);
  case 'inscription:participants':
    $parts = []; foreach ((array)($_POST['pn'] ?? []) as $k => $n) $parts[] = ['nom' => line((string)$n), 'fonction' => line((string)($_POST['pf'][$k] ?? '')), 'email' => mb_strtolower(line((string)($_POST['pe'][$k] ?? '')))];
    db_update('inscriptions', $id, ['participants' => json_encode($parts, JSON_UNESCAPED_UNICODE)]); a_flash('Participants enregistrés.'); a_go('v=inscription&id=' . $id);
  case 'inscription:virement':
    $e = db_one('SELECT * FROM echeances WHERE id=? AND inscription_id=?', [(int)post('e'), $id]); if ($e && $e['statut'] !== 'payee') { virement_recu($e); a_flash('Règlement par virement enregistré.'); } a_go('v=inscription&id=' . $id);
  case 'inscription:relancer':
    $e = db_one('SELECT * FROM echeances WHERE id=? AND inscription_id=?', [(int)post('e'), $id]);
    if ($e && in_array($e['statut'], ['prevue', 'attendue', 'expiree'], true)) { ouvrir_echeance($e, (int)post('heures') ?: 72, 'relance-' . $e['id'] . '-' . time()); mail_flush(); a_flash('Lien de paiement envoyé.'); }
    a_go('v=inscription&id=' . $id);
  case 'inscription:annuler':
    $i = inscription($id); $r = rembourser_inscription($i, post('motif', 300));
    db_update('inscriptions', $id, ['statut' => $r['carte'] + $r['virement'] > 0 ? 'remboursee' : 'annulee', 'motif' => post('motif', 300), 'updated_at' => now()]);
    if (post('prevenir') === '1') { email_rembourse($i, $r); mail_flush(); }
    liberer_place((int)$i['session_id']); journal('admin', 'inscription_annulee', 'inscription:' . $id, post('motif', 300));
    a_flash('Inscription annulée' . ($r['carte'] ? ', ' . eur($r['carte']) . ' remboursés par carte' : '') . ($r['virement'] ? ', ' . eur($r['virement']) . ' à rembourser par virement' : '') . '.'); a_go('v=inscription&id=' . $id);

  case 'produit:save':
    $row = ['type' => array_key_exists(post('type'), TYPES_PRODUIT) ? post('type') : 'ebook', 'titre' => post('titre', 200), 'sous_titre' => post('sous_titre', 300), 'description' => post('description', 8000),
      'sommaire' => post('sommaire', 4000), 'prix' => to_cents(post('prix', 20)), 'prix_unique_livre' => post('prix_unique_livre') === '1' ? 1 : 0, 'pages' => (int)post('pages') ?: null,
      'duree_minutes' => (int)post('duree_minutes') ?: null, 'delai_jours' => (int)post('delai_jours') ?: null, 'lien_visio' => post('lien_visio', 300), 'actif' => post('actif') === '1' ? 1 : 0, 'ordre' => (int)post('ordre')];
    if ($f = upload_to('fichier', 'produits', ['pdf'], 80)) $row['fichier'] = $f;
    if ($f = upload_to('extrait', 'produits', ['pdf'], 20)) $row['extrait'] = $f;
    if ($f = upload_to('couverture', 'produits', ['jpg', 'jpeg', 'png', 'webp'], 8)) $row['couverture'] = $f;
    if ($row['actif'] && $row['prix'] <= 0) throw new DomainException('Un produit en vente doit avoir un prix.');
    if ($id) db_update('produits', $id, $row);
    else { $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $row['titre']))), '-') ?: 'produit'; $b = $slug; $k = 2; while (db_val('SELECT id FROM produits WHERE slug=?', [$slug])) $slug = $b . '-' . $k++; $id = db_insert('produits', $row + ['slug' => $slug]); }
    $p = produit($id);
    if ($p['actif'] && in_array($p['type'], ['ebook', 'guide'], true) && !$p['fichier']) { db_update('produits', $id, ['actif' => 0]); a_flash('Produit enregistré mais laissé hors ligne : ajoutez le fichier PDF avant de le mettre en vente.'); }
    else a_flash('Produit enregistré.');
    journal('admin', 'produit_enregistre', 'produit:' . $id); a_go('v=produit&id=' . $id);
  case 'produit:creneaux':
    $p = produit($id); $n = 0; $dur = (int)$p['duree_minutes'] ?: 60;
    foreach (preg_split('/\R/', post('liste', 4000)) as $l) {
      if (!preg_match('/^(\d{4}-\d{2}-\d{2})\s+(\d{1,2})[:h](\d{2})$/', trim($l), $m)) continue;
      $deb = $m[1] . ' ' . str_pad($m[2], 2, '0', STR_PAD_LEFT) . ':' . $m[3] . ':00';
      if (ts($deb) < time()) continue;
      db_insert('creneaux', ['produit_id' => $id, 'debut' => $deb, 'fin' => date('Y-m-d H:i:s', ts($deb) + $dur * 60), 'lien_visio' => post('lien', 300), 'statut' => 'libre']); $n++;
    }
    a_flash($n . ' créneau(x) ajouté(s).'); a_go('v=produit&id=' . $id);
  case 'produit:suppr_creneau': db_exec("DELETE FROM creneaux WHERE id=? AND produit_id=? AND statut='libre'", [(int)post('c'), $id]); a_go('v=produit&id=' . $id);
  case 'boutique:code':
    $code = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', post('code', 40)));
    if ($code === '' || (int)post('pourcentage') < 1 || (int)post('pourcentage') > 90) throw new DomainException('Code et pourcentage (1 à 90) requis.');
    db_insert('codes_promo', ['code' => $code, 'pourcentage' => (int)post('pourcentage'), 'actif' => 1, 'fin' => post('fin', 10) ?: null]); a_flash('Code créé.'); a_go('v=boutique');
  case 'boutique:code_off': db_exec('UPDATE codes_promo SET actif=0 WHERE id=?', [(int)post('c')]); a_go('v=boutique');

  case 'commande:reponse':
    $c = db_one('SELECT * FROM commandes WHERE id=?', [$id]); $row = ['reponse' => post('reponse', 20000), 'reponse_at' => now()];
    if ($f = upload_to('fichier', 'uploads', ['pdf', 'docx', 'doc', 'odt', 'pptx'], 20)) $row['reponse_fichier'] = $f;
    db_update('commandes', $id, $row); $cl = client((int)$c['client_id']);
    mail_queue($cl['email'], 'Votre réponse est disponible', email_wrap('Votre conseil écrit', p_('Bonjour ' . h($cl['prenom']) . ',') . p_('La réponse à votre question est disponible dans votre espace.') . btn(acces_url((int)$cl['id']), 'Lire la réponse')), [], 'reponse-' . $id);
    mail_flush(); a_flash('Réponse transmise.'); a_go('v=commande&id=' . $id);
  case 'commande:rembourser':
    $c = db_one('SELECT * FROM commandes WHERE id=?', [$id]); $e = db_one("SELECT * FROM echeances WHERE commande_id=? AND statut='payee'", [$id]);
    if ($e && $e['stripe_payment_intent']) { stripe_refund($e['stripe_payment_intent']); db_update('echeances', (int)$e['id'], ['statut' => 'remboursee']); }
    $f = db_one("SELECT * FROM factures WHERE commande_id=? AND type='facture'", [$id]); if ($f) avoir_creer((int)$f['id'], post('motif', 200));
    db_update('commandes', $id, ['statut' => 'remboursee']); if ($c['creneau_id']) db_update('creneaux', (int)$c['creneau_id'], ['statut' => 'libre', 'commande_id' => null]);
    journal('admin', 'commande_remboursee', 'commande:' . $id); a_flash('Commande remboursée, avoir émis.'); a_go('v=commande&id=' . $id);

  case 'courriels:renvoyer': db_exec("UPDATE courriels SET statut='attente', essais=0 WHERE id=?", [(int)post('m')]); mail_flush(); a_flash('Courriel renvoyé.'); a_go('v=courriels');

  case 'parametres:save':
    foreach (['nda', 'nda_region', 'parole_lieu_defaut', 'visio_outil', 'fermeture_particuliers_jours', 'fermeture_entreprises_jours', 'decision_maintien_jours', 'presque_complete', 'delai_retractation_jours', 'delai_acompte_heures', 'delai_entreprise_jours', 'delai_invitation_heures', 'acces_telechargement_jours', 'max_telechargements'] as $k) param_set($k, post($k, 600));
    journal('admin', 'parametres'); a_flash('Paramètres enregistrés.'); a_go('v=parametres');
}
} catch (DomainException $e) { a_flash($e->getMessage()); a_go('v=' . $v . ($id ? '&id=' . $id : '')); }

/* ================= Téléchargements ================= */
if ($v === 'doc') { $d = db_one('SELECT * FROM documents WHERE id=?', [$id]); if (!$d || !is_file(LP_STORAGE . '/' . $d['chemin'])) { http_response_code(404); exit; } header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="' . str_replace('"', '', $d['nom']) . '"'); readfile(LP_STORAGE . '/' . $d['chemin']); exit; }
if ($v === 'fichier') { $f = basename(get('f', 200)); $path = LP_STORAGE . '/' . (get('d') === 'u' ? 'uploads' : 'produits') . '/' . $f; if (!is_file($path)) { http_response_code(404); exit; } header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . $f . '"'); readfile($path); exit; }
if ($v === 'csv_session') {
  $s = session_get($id); $rows = [];
  foreach (db_all("SELECT * FROM inscriptions WHERE session_id=? AND statut IN ('retractation','acompte_attendu','confirmee','soldee','demande','devis_envoye')", [$id]) as $i)
    foreach (participants($i) as $pt) $rows[] = [$pt['nom'], $pt['email'] ?? '', $i['type'] === 'entreprise' ? organisation((int)$i['organisation_id'])['raison_sociale'] : (client((int)$i['client_id'])['organisation'] ?? ''), STATUTS_LIB[$i['statut']] ?? $i['statut'], $i['type'] === 'particulier' ? client((int)$i['client_id'])['telephone'] : organisation((int)$i['organisation_id'])['contact_tel'], $i['handicap']];
  csv_out('inscrits-' . $s['slug'], ['Participant', 'Courriel', 'Organisation', 'Statut', 'Téléphone', 'Adaptation'], $rows);
}
if ($v === 'csv_recettes') {
  $an = (int)get('an') ?: (int)date('Y');
  $rows = array_map(fn($f) => [date('d/m/Y', ts($f['date_emission'])), $f['numero'], $f['type'], $f['libelle'], number_format($f['montant'] / 100, 2, ',', ''), $f['statut_paiement']], db_all('SELECT * FROM factures WHERE substr(date_emission,1,4)=? ORDER BY id', [(string)$an]));
  csv_out('registre-recettes-' . $an, ['Date', 'Numéro', 'Type', 'Objet', 'Montant (€)', 'Paiement'], $rows);
}
if ($v === 'csv_enquetes') {
  $rows = []; foreach (db_all('SELECT q.*, s.date_debut, p.titre FROM enquetes q JOIN inscriptions i ON i.id=q.inscription_id JOIN sessions s ON s.id=i.session_id JOIN programmes p ON p.id=s.programme_id WHERE q.repondu_at IS NOT NULL ORDER BY q.id') as $q) { $r = json_decode((string)$q['reponses'], true) ?: []; $rows[] = [$q['titre'], $q['date_debut'], $q['participant'], $q['type'], json_encode(array_diff_key($r, ['commentaire' => 1]), JSON_UNESCAPED_UNICODE), $r['recommande'] ?? '', $r['commentaire'] ?? '']; }
  csv_out('enquetes', ['Programme', 'Session', 'Participant', 'Type', 'Notes', 'Recommande', 'Commentaire'], $rows);
}

/* ================= Vues ================= */
$progs = db_all('SELECT * FROM programmes ORDER BY numero');
$progNom = array_column($progs, 'titre', 'id');
$slib = fn($s) => h(($progNom[$s['programme_id']] ?? '') . ' · ' . date('d/m/Y', ts($s['date_debut'])));

switch ($v) {
case 'accueil':
  $m = preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') : date('Y-m');
  $sessM = db_all("SELECT * FROM sessions WHERE substr(date_debut,1,7)=? AND etat NOT IN ('brouillon','annulee','reportee')", [$m]);
  $conf = 0; $cap = 0; $compl = 0; foreach ($sessM as $s) { $c = places_confirmees((int)$s['id']); $conf += $c; $cap += (int)$s['capacite']; if (places_engagees((int)$s['id']) >= (int)$s['capacite']) $compl++; }
  $retr = (int)db_val("SELECT COALESCE(SUM(i.places),0) FROM inscriptions i JOIN sessions s ON s.id=i.session_id WHERE substr(s.date_debut,1,7)=? AND i.statut IN ('retractation','acompte_attendu')", [$m]);
  $caF = (int)db_val("SELECT COALESCE(SUM(montant),0) FROM echeances WHERE statut='payee' AND inscription_id IS NOT NULL AND substr(paye_at,1,7)=?", [$m]);
  $caB = (int)db_val("SELECT COALESCE(SUM(montant),0) FROM echeances WHERE statut='payee' AND commande_id IS NOT NULL AND substr(paye_at,1,7)=?", [$m]);
  $vues = (int)db_val("SELECT COALESCE(SUM(n),0) FROM vues WHERE substr(jour,1,7)=? AND page LIKE 'session:%'", [$m]);
  $signes = (int)db_val("SELECT COALESCE(SUM(n),0) FROM vues WHERE substr(jour,1,7)=? AND page IN ('inscription:signee','inscription:entreprise')", [$m]);
  $pres = db_one("SELECT COUNT(*) AS t, COALESCE(SUM(present),0) AS p FROM presences WHERE substr(jour,1,7)=?", [$m]);
  $prevu = 0; foreach ($sessM as $s) if ($s['date_fin'] < date('Y-m-d')) { $pr = programme((int)$s['programme_id']); $prevu += places_confirmees((int)$s['id']) * 2 * (int)$pr['duree_jours']; }
  $kpi = [['Inscrits confirmés', (string)$conf, $retr ? '+ ' . $retr . ' en rétractation ou acompte attendu' : 'places payées'],
    ['Taux de remplissage', $cap ? round(100 * $conf / $cap) . ' %' : '–', count($sessM) . ' session(s) du mois'],
    ['Chiffre d’affaires encaissé', eur($caF + $caB), 'formations ' . eur($caF) . ' · boutique ' . eur($caB)],
    ['Sessions complètes', (string)$compl, 'sur ' . count($sessM)],
    ['Conversion', $vues ? number_format(min(100, 100 * $signes / $vues), 1, ',', '') . ' %' : '–', $signes . ' inscription(s) en ligne pour ' . $vues . ' visite(s) de pages de session'],
    ['Taux de présence', $prevu ? round(100 * (int)$pres['p'] / $prevu) . ' %' : '–', 'demi-journées émargées']];
  $alertes = [];
  foreach (db_all("SELECT * FROM sessions WHERE etat='ouverte' AND maintenue=0 AND date_debut BETWEEN ? AND ?", [date('Y-m-d'), date('Y-m-d', time() + ((int)param('decision_maintien_jours', 10) + 3) * 86400)]) as $s) { $sg = places_signees((int)$s['id']); if ($sg < (int)$s['seuil']) $alertes[] = ['rouge', 'Sous le seuil : ' . $slib($s) . ' (' . $sg . ' / ' . $s['seuil'] . ')', 'v=session&id=' . $s['id']]; }
  foreach (db_all("SELECT * FROM inscriptions WHERE statut='demande'") as $i) $alertes[] = ['ambre', 'Devis à établir : ' . organisation((int)$i['organisation_id'])['raison_sociale'] . ', ' . $i['places'] . ' place(s)', 'v=inscription&id=' . $i['id']];
  foreach (db_all("SELECT e.* FROM echeances e WHERE e.statut='attendue' AND e.mode='virement'") as $e) $alertes[] = ['ambre', 'Virement attendu : ' . $e['libelle'] . ' · ' . eur((int)$e['montant']), 'v=inscription&id=' . $e['inscription_id']];
  foreach (db_all("SELECT * FROM echeances WHERE statut='expiree' AND inscription_id IS NOT NULL ORDER BY id DESC LIMIT 5") as $e) $alertes[] = ['ambre', 'Paiement non effectué : ' . $e['libelle'], 'v=inscription&id=' . $e['inscription_id']];
  foreach (db_all("SELECT c.*, p.titre FROM commandes c JOIN produits p ON p.id=c.produit_id WHERE p.type='ecrit' AND c.statut='payee' AND c.question_at IS NOT NULL AND c.reponse_at IS NULL") as $c) $alertes[] = ['bleu', 'Conseil écrit à rendre : ' . $c['titre'] . ' (question du ' . date('d/m', ts($c['question_at'])) . ')', 'v=commande&id=' . $c['id']];
  foreach (db_all("SELECT * FROM courriels WHERE statut='echec' ORDER BY id DESC LIMIT 3") as $c) $alertes[] = ['rouge', 'Courriel non remis : ' . $c['sujet'] . ' → ' . $c['destinataire'], 'v=courriels'];
  a_layout_start('Accueil', 'accueil');
  $pm = date('Y-m', strtotime($m . '-01 -1 month')); $nm = date('Y-m', strtotime($m . '-01 +1 month'));
  echo '<div class="g-head"><div><p class="g-small"><a href="?v=accueil&m=' . $pm . '">‹</a> ' . h(MOIS[(int)substr($m, 5)] . ' ' . substr($m, 0, 4)) . ' <a href="?v=accueil&m=' . $nm . '">›</a></p><h1 class="g-h1">Tableau de bord</h1></div><div class="g-actions"><a class="g-btn" href="?v=session">Ajouter une session</a><a class="g-btn g-ghost" href="?v=produit">Ajouter un produit</a></div></div>';
  echo '<div class="g-kpis">'; foreach ($kpi as [$l, $val, $sub]) echo '<div class="g-kpi"><span>' . h($l) . '</span><strong>' . h($val) . '</strong><small>' . h($sub) . '</small></div>'; echo '</div>';
  echo '<div class="g-cols"><section class="g-card g-wide"><h2 class="g-h2">Prochaines sessions</h2>';
  $rows = []; foreach (db_all("SELECT * FROM sessions WHERE date_fin >= ? AND etat NOT IN ('annulee','reportee') ORDER BY date_debut LIMIT 12", [date('Y-m-d')]) as $s) { $e = places_engagees((int)$s['id']); $sg = places_signees((int)$s['id']); $c = places_confirmees((int)$s['id']);
    $rows[] = ['<a href="?v=session&id=' . $s['id'] . '">' . h($progNom[$s['programme_id']] ?? '') . '</a>', h(date('d/m/Y', ts($s['date_debut']))), '<div class="g-bar"><i style="width:' . min(100, round(100 * $e / max(1, $s['capacite']))) . '%"></i></div> ' . $c . ' confirmé(s) · ' . $sg . ' signé(s) · ' . $e . ' / ' . $s['capacite'], a_badge($s['etat']) . ((int)$s['maintenue'] ? ' <span class="g-b g-b-ok">Maintenue</span>' : '')]; }
  echo a_table(['Session', 'Début', 'Remplissage', 'État'], $rows, 'Aucune session programmée.') . '</section><section class="g-card"><h2 class="g-h2">À traiter</h2>';
  echo $alertes ? '<ul class="g-alerts">' . implode('', array_map(fn($a) => '<li class="g-al-' . $a[0] . '"><a href="?' . $a[2] . '">' . h($a[1]) . '</a></li>', $alertes)) . '</ul>' : '<p class="g-empty">Rien en attente.</p>';
  echo '</section></div>';
  echo '<section class="g-card"><h2 class="g-h2">Chiffre d’affaires par formation et par mois (12 derniers mois)</h2>';
  $rows = []; $mm = [];
  for ($k = 11; $k >= 0; $k--) $mm[] = date('Y-m', strtotime(date('Y-m-01') . " -$k month"));
  $lignes = ['Boutique' => array_fill_keys($mm, 0)];
  foreach (db_all("SELECT e.montant, substr(e.paye_at,1,7) AS m, p.titre FROM echeances e JOIN inscriptions i ON i.id=e.inscription_id JOIN sessions s ON s.id=i.session_id JOIN programmes p ON p.id=s.programme_id WHERE e.statut='payee' AND e.paye_at >= ?", [$mm[0] . '-01']) as $r) { $lignes[$r['titre']] ??= array_fill_keys($mm, 0); $lignes[$r['titre']][$r['m']] += (int)$r['montant']; }
  foreach (db_all("SELECT montant, substr(paye_at,1,7) AS m FROM echeances WHERE statut='payee' AND commande_id IS NOT NULL AND paye_at >= ?", [$mm[0] . '-01']) as $r) $lignes['Boutique'][$r['m']] += (int)$r['montant'];
  foreach ($lignes as $t => $vals) $rows[] = array_merge([h($t)], array_map(fn($x) => $x ? h(number_format($x / 100, 0, ',', ' ')) : '·', array_values($vals)), [h(eur(array_sum($vals)))]);
  echo a_table(array_merge(['En €'], array_map(fn($x) => substr($x, 5) . '/' . substr($x, 2, 2), $mm), ['Total']), $rows) . '</section>';
  a_layout_end(); break;

case 'sessions':
  a_layout_start('Sessions', 'sessions');
  echo '<div class="g-head"><h1 class="g-h1">Sessions</h1><a class="g-btn" href="?v=session">Ajouter une session</a></div>';
  $rows = []; foreach (db_all('SELECT * FROM sessions ORDER BY date_debut DESC LIMIT 200') as $s) $rows[] = ['<a href="?v=session&id=' . $s['id'] . '">' . h($progNom[$s['programme_id']] ?? '') . '</a>', h(periode_fr($s['date_debut'], $s['date_fin'])), h($s['modalite'] === 'distance' ? 'Distance' : 'Présentiel'), places_confirmees((int)$s['id']) . ' / ' . places_engagees((int)$s['id']) . ' / ' . $s['capacite'], a_badge($s['etat'])];
  echo '<section class="g-card">' . a_table(['Programme', 'Dates', 'Format', 'Confirmés / engagés / places', 'État'], $rows, 'Aucune session.') . '</section>';
  a_layout_end(); break;

case 'session':
  $s = $id ? session_get($id) : null;
  a_layout_start($s ? 'Session' : 'Nouvelle session', 'sessions');
  $sv = $s ?? ['programme_id' => $progs[0]['id'] ?? 0, 'date_debut' => '', 'date_fin' => '', 'heure_debut' => '09:00', 'heure_fin' => '17:00', 'modalite' => 'presentiel', 'lieu' => param('parole_lieu_defaut'), 'lien_visio' => '', 'capacite' => 15, 'seuil' => 7, 'prix_particulier' => 0, 'etat' => 'brouillon', 'note' => ''];
  echo '<div class="g-head"><h1 class="g-h1">' . ($s ? $slib($s) : 'Nouvelle session') . '</h1>' . ($s ? '<div class="g-actions"><a class="g-btn g-ghost" href="/sessions/' . h($s['slug']) . '/" target="_blank" rel="noopener">Voir la page publique</a><a class="g-btn g-ghost" href="?v=csv_session&id=' . $id . '">Exporter les inscrits</a></div>' : '') . '</div>';
  if ($s) { $vv = session_view($s); echo '<div class="g-kpis"><div class="g-kpi"><span>Confirmés</span><strong>' . places_confirmees($id) . '</strong></div><div class="g-kpi"><span>Contrats signés</span><strong>' . places_signees($id) . '</strong><small>seuil ' . (int)$s['seuil'] . '</small></div><div class="g-kpi"><span>Places engagées</span><strong>' . $vv['engagees'] . ' / ' . $s['capacite'] . '</strong></div><div class="g-kpi"><span>Statut public</span><strong style="font-size:1.1rem">' . h($vv['statut']) . '</strong><small>J' . ($vv['jours'] >= 0 ? '-' : '+') . abs($vv['jours']) . '</small></div></div>'; }
  $popts = array_column($progs, 'titre', 'id');
  echo '<section class="g-card"><h2 class="g-h2">Paramètres de la session</h2><form method="post" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="save">'
    . a_select('programme_id', 'Programme', $popts, $sv['programme_id']) . a_select('etat', 'État', ['brouillon' => 'Brouillon (non publiée)', 'ouverte' => 'Ouverte aux inscriptions', 'fermee' => 'Inscriptions fermées'], in_array($sv['etat'], ['brouillon', 'ouverte', 'fermee'], true) ? $sv['etat'] : 'fermee')
    . a_input('date_debut', 'Premier jour', $sv['date_debut'], 'date', 'required') . a_input('date_fin', 'Dernier jour', $sv['date_fin'], 'date', 'required')
    . a_input('heure_debut', 'Heure de début', $sv['heure_debut'], 'time') . a_input('heure_fin', 'Heure de fin', $sv['heure_fin'], 'time')
    . a_select('modalite', 'Format', ['presentiel' => 'Présentiel', 'distance' => 'À distance'], $sv['modalite']) . a_input('lien_visio', 'Lien de visioconférence (distance)', $sv['lien_visio'], 'url')
    . '<div class="g-full">' . a_input('lieu', 'Lieu (adresse complète, présentiel)', $sv['lieu'], 'textarea', 'rows="2"') . '</div>'
    . a_input('capacite', 'Places (15 au plus)', $sv['capacite'], 'number', 'min="1" max="15"') . a_input('seuil', 'Seuil de maintien', $sv['seuil'], 'number', 'min="1" max="15"')
    . a_input('prix_particulier', 'Prix particulier (€) · vide = prix du programme', $sv['prix_particulier'] ? number_format($sv['prix_particulier'] / 100, 2, ',', '') : '', 'text', 'inputmode="decimal"')
    . '<div class="g-full">' . a_input('note', 'Note interne', $sv['note'], 'textarea', 'rows="2"') . '</div><div class="g-full"><button class="g-btn">Enregistrer</button></div></form></section>';
  if ($s) {
    $ins = db_all('SELECT * FROM inscriptions WHERE session_id=? ORDER BY id', [$id]);
    $rows = []; foreach ($ins as $i) { $nom = $i['type'] === 'entreprise' ? organisation((int)$i['organisation_id'])['raison_sociale'] . ' (' . $i['places'] . ')' : client_nom(client((int)$i['client_id']));
      $paye = (int)db_val("SELECT COALESCE(SUM(montant),0) FROM echeances WHERE inscription_id=? AND statut='payee'", [$i['id']]);
      $rows[] = ['<a href="?v=inscription&id=' . $i['id'] . '">' . h($nom) . '</a>', h($i['type'] === 'entreprise' ? 'Entreprise' : 'Particulier'), a_badge($i['statut']), h(eur($paye)) . ' / ' . h(eur((int)$i['prix_total'])), h($i['handicap'] ? 'Adaptation : ' . $i['handicap'] : '')]; }
    echo '<section class="g-card"><h2 class="g-h2">Inscriptions</h2>' . a_table(['Participant', 'Type', 'Statut', 'Payé / total', 'Note'], $rows, 'Aucune inscription.') . '</section>';
    $att = db_all('SELECT * FROM attente WHERE session_id=? ORDER BY id', [$id]);
    if ($att) echo '<section class="g-card"><h2 class="g-h2">Liste d’attente</h2>' . a_table(['Nom', 'Courriel', 'Statut', 'Inscrit le'], array_map(fn($a) => [h($a['prenom'] . ' ' . $a['nom']), h($a['email']), a_badge($a['statut']), h(date('d/m/Y', ts($a['created_at'])))], $att)) . '</section>';
    // Émargement par demi-journée
    $p = programme((int)$s['programme_id']); $jours = []; for ($t = ts($s['date_debut']); $t <= ts($s['date_fin']); $t += 86400) $jours[] = date('Y-m-d', $t);
    $pres = []; foreach (db_all('SELECT p.* FROM presences p JOIN inscriptions i ON i.id=p.inscription_id WHERE i.session_id=?', [$id]) as $r) $pres[$r['inscription_id'] . '|' . $r['participant'] . '|' . $r['jour'] . '|' . $r['demi']] = 1;
    $head = ['Participant']; foreach ($jours as $j) { $head[] = date('d/m', ts($j)) . ' matin'; $head[] = date('d/m', ts($j)) . ' après-midi'; }
    $rows = [];
    foreach ($ins as $i) { if (!in_array($i['statut'], ['confirmee', 'soldee'], true)) continue; foreach (participants($i) as $k => $pt) { $r = [h($pt['nom'])]; foreach ($jours as $j) foreach (['am', 'pm'] as $dm) { $key = $i['id'] . '|' . $k . '|' . $j . '|' . $dm; $r[] = '<input type="checkbox" name="p[' . h($key) . ']" value="1" aria-label="Présent"' . (isset($pres[$i['id'] . '|' . $pt['nom'] . '|' . $j . '|' . $dm]) ? ' checked' : '') . '>'; } $rows[] = $r; } }
    echo '<section class="g-card" id="emargement"><h2 class="g-h2">Émargement</h2><form method="post">' . a_field() . '<input type="hidden" name="a" value="emargement">' . a_table($head, $rows, 'Aucun participant confirmé.') . ($rows ? '<p><button class="g-btn">Enregistrer l’émargement</button></p>' : '') . '</form>'
      . ($rows ? '<form method="post">' . a_field() . '<input type="hidden" name="a" value="attestations"><p class="g-small">Les attestations indiquent les heures effectivement suivies d’après l’émargement, et sont envoyées aux participants (et à l’employeur le cas échéant).</p><button class="g-btn g-ghost">Établir et envoyer les attestations</button></form>' : '') . '</section>';
    echo '<section class="g-card"><h2 class="g-h2">Écrire aux participants</h2><form method="post" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="message"><div class="g-full">' . a_input('sujet', 'Objet', '', 'text', 'required') . '</div><div class="g-full">' . a_input('corps', 'Message', '', 'textarea', 'rows="5" required') . '</div><div class="g-full"><button class="g-btn">Envoyer</button></div></form></section>';
    if (!in_array($s['etat'], ['annulee', 'reportee'], true)) {
      $autres = []; foreach (db_all("SELECT * FROM sessions WHERE id<>? AND programme_id=? AND date_debut > ? AND etat IN ('brouillon','ouverte')", [$id, $s['programme_id'], date('Y-m-d')]) as $o) $autres[$o['id']] = periode_fr($o['date_debut'], $o['date_fin']);
      echo '<section class="g-card g-danger"><h2 class="g-h2">Maintien, report ou annulation</h2><div class="g-cols">'
        . '<form method="post">' . a_field() . '<input type="hidden" name="a" value="maintenir"><p class="g-small">Confirme la session même sous le seuil ; les participants en sont informés lors de la tâche suivante.</p><button class="g-btn g-ghost"' . ((int)$s['maintenue'] ? ' disabled' : '') . '>Maintenir la session</button></form>'
        . '<form method="post" onsubmit="return confirm(\'Reporter la session et inviter chaque participant à choisir ?\')">' . a_field() . '<input type="hidden" name="a" value="reporter">' . ($autres ? a_select('cible', 'Nouvelle session', $autres, '') : '<p class="g-small">Créez d’abord la nouvelle session du même programme.</p>') . a_input('motif', 'Motif communiqué', '') . '<button class="g-btn g-ghost"' . ($autres ? '' : ' disabled') . '>Reporter</button></form>'
        . '<form method="post" onsubmit="return confirm(\'Annuler la session et rembourser tous les paiements ?\')">' . a_field() . '<input type="hidden" name="a" value="annuler">' . a_input('motif', 'Motif communiqué', '') . '<button class="g-btn g-red">Annuler et rembourser</button></form>'
        . '</div></section>';
    }
  }
  a_layout_end(); break;

case 'programmes':
  a_layout_start('Programmes', 'programmes');
  echo '<div class="g-head"><h1 class="g-h1">Programmes</h1><a class="g-btn" href="?v=programme">Ajouter un programme</a></div>';
  echo '<section class="g-card">' . a_table(['N°', 'Programme', 'Durée', 'Prix particulier', 'Actif'], array_map(fn($p) => [h($p['numero']), '<a href="?v=programme&id=' . $p['id'] . '">' . h($p['titre']) . '</a>', (int)$p['duree_jours'] . ' j · ' . (int)$p['duree_heures'] . ' h', $p['prix_particulier'] ? h(eur((int)$p['prix_particulier'])) : '<span class="g-b g-b-ko">À fixer</span>', $p['actif'] ? 'Oui' : 'Non'], $progs)) . '</section>';
  a_layout_end(); break;

case 'programme':
  $p = $id ? programme($id) : ['titre' => '', 'sous_titre' => '', 'numero' => '', 'duree_jours' => 3, 'duree_heures' => 18, 'public_vise' => '', 'prerequis' => 'Aucun prérequis.', 'objectifs' => '', 'programme' => '', 'evaluation' => '', 'formateur' => '', 'prix_particulier' => 0, 'fiche_url' => '', 'actif' => 1];
  a_layout_start('Programme', 'programmes');
  echo '<h1 class="g-h1">' . h($p['titre'] ?: 'Nouveau programme') . '</h1><section class="g-card"><form method="post" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="save">'
    . a_input('titre', 'Intitulé', $p['titre'], 'text', 'required') . a_input('numero', 'Numéro', $p['numero']) . '<div class="g-full">' . a_input('sous_titre', 'Accroche', $p['sous_titre']) . '</div>'
    . a_input('duree_jours', 'Jours', $p['duree_jours'], 'number') . a_input('duree_heures', 'Heures', $p['duree_heures'], 'number')
    . a_input('prix_particulier', 'Prix particulier (€)', $p['prix_particulier'] ? number_format($p['prix_particulier'] / 100, 2, ',', '') : '', 'text', 'inputmode="decimal"') . a_input('fiche_url', 'Page de la fiche sur le site', $p['fiche_url'])
    . '<div class="g-full">' . a_input('public_vise', 'Public', $p['public_vise'], 'textarea', 'rows="2"') . a_input('prerequis', 'Prérequis', $p['prerequis'], 'textarea', 'rows="2"')
    . a_input('objectifs', 'Objectifs pédagogiques (un par ligne)', $p['objectifs'], 'textarea', 'rows="5"') . a_input('programme', 'Programme (une ligne par jour, « Titre : contenu »)', $p['programme'], 'textarea', 'rows="5"')
    . a_input('evaluation', 'Modalités d’évaluation', $p['evaluation'], 'textarea', 'rows="3"') . a_input('formateur', 'Formateur (titres et qualités)', $p['formateur'], 'textarea', 'rows="3"') . '</div>'
    . a_select('actif', 'Proposé à la vente', ['1' => 'Oui', '0' => 'Non'], (string)$p['actif']) . '<div class="g-full"><button class="g-btn">Enregistrer</button></div></form></section>';
  a_layout_end(); break;

case 'inscriptions':
  $f = get('statut', 30);
  a_layout_start('Inscrits', 'inscriptions');
  echo '<div class="g-head"><h1 class="g-h1">Inscrits</h1><form class="g-actions">' . '<input type="hidden" name="v" value="inscriptions">' . a_select('statut', 'Statut', ['' => 'Tous'] + STATUTS_LIB, $f) . '<button class="g-btn g-ghost">Filtrer</button></form></div>';
  $rows = []; foreach (db_all('SELECT * FROM inscriptions' . ($f ? ' WHERE statut=?' : " WHERE statut<>'reservation'") . ' ORDER BY id DESC LIMIT 300', $f ? [$f] : []) as $i) { $s = session_get((int)$i['session_id']);
    $nom = $i['type'] === 'entreprise' ? organisation((int)$i['organisation_id'])['raison_sociale'] . ' (' . $i['places'] . ')' : client_nom(client((int)$i['client_id']));
    $rows[] = ['<a href="?v=inscription&id=' . $i['id'] . '">' . h($nom) . '</a>', $slib($s), a_badge($i['statut']), h(eur((int)$i['prix_total'])), h(date('d/m/Y', ts($i['created_at'])))]; }
  echo '<section class="g-card">' . a_table(['Inscrit', 'Session', 'Statut', 'Montant', 'Créée le'], $rows) . '</section>';
  a_layout_end(); break;

case 'inscription':
  $i = inscription($id); if (!$i) a_go('v=inscriptions'); $s = session_get((int)$i['session_id']);
  a_layout_start('Inscription', 'inscriptions');
  echo '<div class="g-head"><div><p class="g-small"><a href="?v=session&id=' . $s['id'] . '">' . $slib($s) . '</a></p><h1 class="g-h1">' . h($i['type'] === 'entreprise' ? organisation((int)$i['organisation_id'])['raison_sociale'] : client_nom(client((int)$i['client_id']))) . '</h1></div><div>' . a_badge($i['statut']) . '</div></div>';
  if ($i['type'] === 'particulier') { $c = client((int)$i['client_id']); $info = [['Courriel', $c['email']], ['Téléphone', $c['telephone']], ['Fonction', $c['fonction']], ['Organisation', $c['organisation']], ['Adresse', $c['adresse']], ['Attentes', $i['attentes']], ['Adaptation', $i['handicap']], ['Signature', $i['signe_at'] ? $i['signature_nom'] . ' · ' . date('d/m/Y H:i', ts($i['signe_at'])) . ' · IP ' . $i['signature_ip'] : '–'], ['Fin de rétractation', $i['retractation_fin'] ? date('d/m/Y', ts($i['retractation_fin'])) : '–']]; }
  else { $o = organisation((int)$i['organisation_id']); $info = [['Contact', $o['contact_nom'] . ' · ' . $o['contact_email'] . ' · ' . $o['contact_tel']], ['SIRET', $o['siret']], ['Adresse', $o['adresse']], ['Places', $i['places']], ['Financement', $i['financement']], ['Commentaire', $i['attentes']], ['Validité', $i['expire_at'] ? date('d/m/Y', ts($i['expire_at'])) : '–'], ['Signature', $i['signe_at'] ? $i['signature_nom'] . ' · ' . date('d/m/Y H:i', ts($i['signe_at'])) : '–']]; }
  echo '<section class="g-card"><dl class="g-dl">' . implode('', array_map(fn($r) => '<dt>' . h($r[0]) . '</dt><dd>' . nl2br(h((string)$r[1])) . '</dd>', $info)) . '</dl></section>';
  if ($i['type'] === 'entreprise' && in_array($i['statut'], ['demande', 'devis_envoye'], true)) echo '<section class="g-card"><h2 class="g-h2">' . ($i['statut'] === 'demande' ? 'Établir le devis' : 'Modifier et renvoyer le devis') . '</h2><form method="post" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="devis">' . a_input('prix_place', 'Prix par participant (€)', $i['prix_total'] ? number_format($i['prix_total'] / $i['places'] / 100, 2, ',', '') : '', 'text', 'inputmode="decimal" required') . '<div><button class="g-btn">Générer et envoyer devis et convention</button></div></form></section>';
  if ($i['type'] === 'entreprise') { $parts = json_decode((string)$i['participants'], true) ?: []; $parts = array_pad($parts, (int)$i['places'], ['nom' => '', 'fonction' => '', 'email' => '']);
    echo '<section class="g-card"><h2 class="g-h2">Participants</h2><form method="post">' . a_field() . '<input type="hidden" name="a" value="participants">';
    foreach ($parts as $k => $pt) echo '<div class="g-grid">' . a_input("pn[$k]", 'Nom', $pt['nom'] ?? '') . a_input("pf[$k]", 'Fonction', $pt['fonction'] ?? '') . a_input("pe[$k]", 'Courriel', $pt['email'] ?? '', 'email') . '</div>';
    echo '<button class="g-btn g-ghost">Enregistrer</button></form></section>'; }
  $rows = []; foreach (db_all('SELECT * FROM echeances WHERE inscription_id=? ORDER BY id', [$id]) as $e) {
    $act2 = '';
    if (in_array($e['statut'], ['prevue', 'attendue', 'expiree'], true)) $act2 .= '<form method="post" class="g-inline">' . a_field() . '<input type="hidden" name="a" value="relancer"><input type="hidden" name="e" value="' . $e['id'] . '"><input type="hidden" name="heures" value="72"><button class="g-linkbtn">Envoyer le lien</button></form>';
    if ($e['statut'] !== 'payee' && $e['statut'] !== 'annulee') $act2 .= '<form method="post" class="g-inline" onsubmit="return confirm(\'Confirmer la réception du virement ?\')">' . a_field() . '<input type="hidden" name="a" value="virement"><input type="hidden" name="e" value="' . $e['id'] . '"><button class="g-linkbtn">Virement reçu</button></form>';
    $rows[] = [h($e['libelle']), h(date('d/m/Y', ts($e['due_at']))), h(eur((int)$e['montant'])), a_badge($e['statut']) . ($e['paye_at'] ? ' ' . h(date('d/m', ts($e['paye_at'])) . ' · ' . $e['mode']) : ''), $act2]; }
  echo '<section class="g-card"><h2 class="g-h2">Échéances et paiements</h2>' . a_table(['Échéance', 'Date', 'Montant', 'État', ''], $rows, 'Aucune échéance.') . '</section>';
  $docs = db_all('SELECT * FROM documents WHERE inscription_id=? ORDER BY id DESC', [$id]);
  echo '<section class="g-card"><h2 class="g-h2">Documents</h2>' . a_table(['Document', 'Créé le'], array_map(fn($d) => ['<a href="?v=doc&id=' . $d['id'] . '" target="_blank">' . h($d['nom']) . '</a>', h(date('d/m/Y H:i', ts($d['created_at'])))], $docs), 'Aucun document.') . '</section>';
  $mails = db_all("SELECT * FROM courriels WHERE cle LIKE ? OR cle LIKE ? ORDER BY id DESC", ['%-' . $id, '%-' . $id . '-%']);
  echo '<section class="g-card"><h2 class="g-h2">Courriels envoyés</h2>' . a_table(['Objet', 'Destinataire', 'État', 'Date'], array_map(fn($m) => [h($m['sujet']), h($m['destinataire']), a_badge($m['statut']), h(date('d/m H:i', ts($m['created_at'])))], $mails), 'Aucun courriel.') . '</section>';
  if (!in_array($i['statut'], ['annulee', 'remboursee', 'retractee', 'expiree'], true)) echo '<section class="g-card g-danger"><h2 class="g-h2">Annuler l’inscription</h2><form method="post" class="g-grid" onsubmit="return confirm(\'Annuler et rembourser les paiements par carte ?\')">' . a_field() . '<input type="hidden" name="a" value="annuler">' . a_input('motif', 'Motif', '') . a_select('prevenir', 'Prévenir par courriel', ['1' => 'Oui', '0' => 'Non'], '1') . '<div class="g-full"><button class="g-btn g-red">Annuler et rembourser</button><p class="g-small">Les paiements par carte sont remboursés automatiquement, avec avoir. Appliquez vos conditions (prorata en cas d’abandon) en remboursant partiellement depuis Stripe si nécessaire.</p></div></form></section>';
  a_layout_end(); break;

case 'paiements':
  $an = (int)get('an') ?: (int)date('Y');
  a_layout_start('Paiements et factures', 'paiements');
  echo '<div class="g-head"><h1 class="g-h1">Paiements et factures</h1><div class="g-actions"><a class="g-btn g-ghost" href="?v=csv_recettes&an=' . $an . '">Registre des recettes ' . $an . ' (CSV)</a></div></div>';
  $att = db_all("SELECT * FROM echeances WHERE statut='attendue' ORDER BY due_at");
  echo '<section class="g-card"><h2 class="g-h2">Paiements attendus</h2>' . a_table(['Échéance', 'Montant', 'Mode', 'Échéance du lien', ''], array_map(fn($e) => [h($e['libelle']), h(eur((int)$e['montant'])), h($e['mode']), $e['expire_at'] ? h(date('d/m/Y H:i', ts($e['expire_at']))) : '–', $e['inscription_id'] ? '<a href="?v=inscription&id=' . $e['inscription_id'] . '">Ouvrir</a>' : ''], $att), 'Aucun paiement attendu.') . '</section>';
  $f = db_all('SELECT * FROM factures WHERE substr(date_emission,1,4)=? ORDER BY id DESC', [(string)$an]);
  $tot = array_sum(array_map(fn($x) => (int)$x['montant'], array_filter($f, fn($x) => $x['statut_paiement'] !== 'attente')));
  echo '<section class="g-card"><h2 class="g-h2">Factures et avoirs ' . $an . ' · ' . h(eur($tot)) . ' encaissés nets</h2><p class="g-small">Seuil de franchise en base de TVA : surveillez le cumul annuel avec votre comptable.</p>' . a_table(['Numéro', 'Date', 'Objet', 'Montant', 'Paiement', ''], array_map(fn($x) => [h($x['numero']), h(date('d/m/Y', ts($x['date_emission']))), h($x['libelle']), h(eur((int)$x['montant'])), a_badge($x['statut_paiement']), $x['doc_id'] ? '<a href="?v=doc&id=' . $x['doc_id'] . '" target="_blank">PDF</a>' : ''], $f), 'Aucune facture.') . '</section>';
  a_layout_end(); break;

case 'boutique':
  a_layout_start('Boutique', 'boutique');
  echo '<div class="g-head"><h1 class="g-h1">Boutique</h1><a class="g-btn" href="?v=produit">Ajouter un produit</a></div>';
  echo '<section class="g-card">' . a_table(['Produit', 'Type', 'Prix', 'Ventes', 'En ligne'], array_map(fn($p) => ['<a href="?v=produit&id=' . $p['id'] . '">' . h($p['titre']) . '</a>', h(TYPES_PRODUIT[$p['type']]), h(eur((int)$p['prix'])), (string)db_val("SELECT COUNT(*) FROM commandes WHERE produit_id=? AND statut='payee'", [$p['id']]), $p['actif'] ? 'Oui' : 'Non'], db_all('SELECT * FROM produits ORDER BY ordre, id')), 'Aucun produit. Ajoutez vos e-books, guides et conseils.') . '</section>';
  echo '<section class="g-card"><h2 class="g-h2">Codes promotionnels</h2><p class="g-small">Ne s’appliquent jamais aux livres numériques soumis au prix unique du livre.</p>' . a_table(['Code', 'Remise', 'Fin', ''], array_map(fn($c) => [h($c['code']), (int)$c['pourcentage'] . ' %', $c['fin'] ? h(date('d/m/Y', ts($c['fin']))) : '–', $c['actif'] ? '<form method="post" class="g-inline">' . a_field() . '<input type="hidden" name="a" value="code_off"><input type="hidden" name="c" value="' . $c['id'] . '"><button class="g-linkbtn">Désactiver</button></form>' : 'Inactif'], db_all('SELECT * FROM codes_promo ORDER BY id DESC')), 'Aucun code.')
    . '<form method="post" class="g-grid" style="margin-top:14px">' . a_field() . '<input type="hidden" name="a" value="code">' . a_input('code', 'Code', '', 'text', 'required') . a_input('pourcentage', 'Remise (%)', '', 'number', 'min="1" max="90" required') . a_input('fin', 'Valable jusqu’au', '', 'date') . '<div><button class="g-btn g-ghost">Créer</button></div></form></section>';
  a_layout_end(); break;

case 'produit':
  $p = $id ? produit($id) : ['type' => 'ebook', 'titre' => '', 'sous_titre' => '', 'description' => '', 'sommaire' => '', 'prix' => 0, 'prix_unique_livre' => 1, 'pages' => '', 'duree_minutes' => 60, 'delai_jours' => 5, 'lien_visio' => '', 'actif' => 0, 'ordre' => 0, 'fichier' => '', 'extrait' => '', 'couverture' => '', 'slug' => ''];
  a_layout_start('Produit', 'boutique');
  echo '<div class="g-head"><h1 class="g-h1">' . h($p['titre'] ?: 'Nouveau produit') . '</h1>' . ($id ? '<a class="g-btn g-ghost" href="/boutique/' . h($p['slug']) . '/" target="_blank" rel="noopener">Voir la fiche</a>' : '') . '</div>';
  echo '<section class="g-card"><form method="post" enctype="multipart/form-data" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="save">'
    . a_select('type', 'Type', TYPES_PRODUIT, $p['type']) . a_input('titre', 'Titre', $p['titre'], 'text', 'required') . '<div class="g-full">' . a_input('sous_titre', 'Accroche', $p['sous_titre']) . '</div>'
    . a_input('prix', 'Prix (€)', $p['prix'] ? number_format($p['prix'] / 100, 2, ',', '') : '', 'text', 'inputmode="decimal"') . a_select('prix_unique_livre', 'Livre soumis au prix unique (pas de remise)', ['1' => 'Oui', '0' => 'Non'], (string)$p['prix_unique_livre'])
    . a_input('pages', 'Pages (e-book, guide)', $p['pages'], 'number') . a_input('duree_minutes', 'Durée en minutes (visioconférence)', $p['duree_minutes'], 'number') . a_input('delai_jours', 'Délai de réponse en jours ouvrés (conseil écrit)', $p['delai_jours'], 'number') . a_input('lien_visio', 'Lien de visioconférence par défaut', $p['lien_visio'], 'url')
    . '<div class="g-full">' . a_input('description', 'Présentation', $p['description'], 'textarea', 'rows="5"') . a_input('sommaire', 'Points clés (un par ligne)', $p['sommaire'], 'textarea', 'rows="4"') . '</div>'
    . '<label class="g-f">Fichier vendu (PDF)' . ($p['fichier'] ? ' · <a href="?v=fichier&f=' . h($p['fichier']) . '">actuel</a>' : '') . '<input type="file" name="fichier" accept="application/pdf"></label>'
    . '<label class="g-f">Extrait gratuit (PDF, facultatif)' . ($p['extrait'] ? ' · présent' : '') . '<input type="file" name="extrait" accept="application/pdf"></label>'
    . '<label class="g-f">Couverture (JPG, PNG, WebP, format 3:4)' . ($p['couverture'] ? ' · présente' : '') . '<input type="file" name="couverture" accept="image/*"></label>'
    . a_input('ordre', 'Ordre d’affichage', $p['ordre'], 'number') . a_select('actif', 'En vente', ['0' => 'Non', '1' => 'Oui'], (string)$p['actif']) . '<div class="g-full"><button class="g-btn">Enregistrer</button></div></form></section>';
  if ($id && $p['type'] === 'visio') {
    $cr = db_all('SELECT * FROM creneaux WHERE produit_id=? AND debut > ? ORDER BY debut', [$id, now()]);
    echo '<section class="g-card"><h2 class="g-h2">Créneaux</h2>' . a_table(['Date', 'Heure', 'État', ''], array_map(fn($c) => [h(date_fr($c['debut'], true)), h(heure_fr(date('H:i', ts($c['debut'])))), a_badge($c['statut']), $c['statut'] === 'libre' ? '<form method="post" class="g-inline">' . a_field() . '<input type="hidden" name="a" value="suppr_creneau"><input type="hidden" name="c" value="' . $c['id'] . '"><button class="g-linkbtn">Retirer</button></form>' : ($c['commande_id'] ? '<a href="?v=commande&id=' . $c['commande_id'] . '">Commande</a>' : '')], $cr), 'Aucun créneau à venir.')
      . '<form method="post" class="g-grid" style="margin-top:14px">' . a_field() . '<input type="hidden" name="a" value="creneaux"><div class="g-full">' . a_input('liste', 'Ajouter des créneaux : une ligne par créneau, au format 2027-01-15 14:00', '', 'textarea', 'rows="4"') . '</div>' . a_input('lien', 'Lien de visioconférence de ces créneaux (facultatif)', '', 'url') . '<div><button class="g-btn g-ghost">Ajouter</button></div></form></section>';
  }
  a_layout_end(); break;

case 'commandes':
  a_layout_start('Commandes et conseils', 'commandes');
  echo '<h1 class="g-h1">Commandes et conseils</h1><section class="g-card">' . a_table(['Date', 'Client', 'Produit', 'Montant', 'État', ''], array_map(fn($c) => [h(date('d/m/Y', ts($c['created_at']))), h(client_nom(client((int)$c['client_id']))), h($c['titre']), h(eur((int)$c['montant'])), a_badge($c['statut']) . ($c['type'] === 'ecrit' && $c['statut'] === 'payee' ? ($c['reponse_at'] ? ' <span class="g-b g-b-ok">Répondu</span>' : ($c['question_at'] ? ' <span class="g-b g-b-wait">À répondre</span>' : ' <span class="g-b">Question attendue</span>')) : ''), '<a href="?v=commande&id=' . $c['id'] . '">Ouvrir</a>'], db_all("SELECT c.*, p.titre, p.type FROM commandes c JOIN produits p ON p.id=c.produit_id WHERE c.statut<>'attente' ORDER BY c.id DESC LIMIT 300")), 'Aucune commande.') . '</section>';
  a_layout_end(); break;

case 'commande':
  $c = db_one('SELECT * FROM commandes WHERE id=?', [$id]); if (!$c) a_go('v=commandes'); $p = produit((int)$c['produit_id']); $cl = client((int)$c['client_id']);
  a_layout_start('Commande', 'commandes');
  echo '<div class="g-head"><h1 class="g-h1">' . h($p['titre']) . '</h1>' . a_badge($c['statut']) . '</div><section class="g-card"><dl class="g-dl"><dt>Client</dt><dd>' . h(client_nom($cl) . ' · ' . $cl['email']) . '</dd><dt>Montant</dt><dd>' . h(eur((int)$c['montant'])) . ($c['code_promo'] ? ' (code ' . h($c['code_promo']) . ')' : '') . '</dd><dt>Payée le</dt><dd>' . ($c['paye_at'] ? h(date('d/m/Y H:i', ts($c['paye_at']))) : '–') . '</dd><dt>Renonciation</dt><dd>' . ($c['renonciation_at'] ? 'Recueillie le ' . h(date('d/m/Y H:i', ts($c['renonciation_at']))) . ' · IP ' . h($c['renonciation_ip']) : '–') . '</dd>'
    . (in_array($p['type'], ['ebook', 'guide'], true) ? '<dt>Téléchargements</dt><dd>' . (int)$c['telechargements'] . ' · accès jusqu’au ' . ($c['acces_fin'] ? h(date('d/m/Y', ts($c['acces_fin']))) : '–') . '</dd>' : '')
    . ($c['creneau_id'] ? '<dt>Rendez-vous</dt><dd>' . h(date_fr((string)db_val('SELECT debut FROM creneaux WHERE id=?', [$c['creneau_id']]), true) . ' à ' . date('H:i', ts((string)db_val('SELECT debut FROM creneaux WHERE id=?', [$c['creneau_id']])))) . '</dd>' : '') . '</dl></section>';
  if ($p['type'] === 'ecrit' && $c['statut'] === 'payee') {
    echo '<section class="g-card"><h2 class="g-h2">Question</h2>' . ($c['question_at'] ? '<p class="g-small">Déposée le ' . h(date('d/m/Y H:i', ts($c['question_at']))) . '</p><div class="g-quote">' . nl2br(h($c['question'])) . '</div>' . ($c['question_fichier'] ? '<p><a href="?v=fichier&d=u&f=' . h($c['question_fichier']) . '">Document joint</a></p>' : '') : '<p class="g-empty">Le client n’a pas encore déposé sa question.</p>') . '</section>';
    if ($c['question_at']) echo '<section class="g-card"><h2 class="g-h2">Réponse</h2><form method="post" enctype="multipart/form-data">' . a_field() . '<input type="hidden" name="a" value="reponse">' . a_input('reponse', 'Votre réponse', $c['reponse'], 'textarea', 'rows="12" required') . '<label class="g-f">Document joint (facultatif)<input type="file" name="fichier"></label><button class="g-btn">' . ($c['reponse_at'] ? 'Mettre à jour et prévenir' : 'Transmettre la réponse') . '</button></form></section>';
  }
  if ($c['statut'] === 'payee') echo '<section class="g-card g-danger"><h2 class="g-h2">Rembourser</h2><form method="post" class="g-grid" onsubmit="return confirm(\'Rembourser intégralement cette commande ?\')">' . a_field() . '<input type="hidden" name="a" value="rembourser">' . a_input('motif', 'Motif', '') . '<div><button class="g-btn g-red">Rembourser et émettre un avoir</button></div></form></section>';
  a_layout_end(); break;

case 'enquetes':
  a_layout_start('Enquêtes', 'enquetes');
  echo '<div class="g-head"><h1 class="g-h1">Enquêtes de satisfaction</h1><a class="g-btn g-ghost" href="?v=csv_enquetes">Exporter (CSV)</a></div>';
  $rows = [];
  foreach (db_all("SELECT s.id, s.date_debut, s.programme_id FROM sessions s WHERE EXISTS (SELECT 1 FROM enquetes q JOIN inscriptions i ON i.id=q.inscription_id WHERE i.session_id=s.id) ORDER BY s.date_debut DESC") as $s) {
    $q = db_all("SELECT q.* FROM enquetes q JOIN inscriptions i ON i.id=q.inscription_id WHERE i.session_id=? AND q.type='chaud'", [$s['id']]);
    $rep = array_filter($q, fn($x) => $x['repondu_at']); $notes = array_map(fn($x) => (json_decode((string)$x['reponses'], true)['global'] ?? 0), $rep);
    $reco = count(array_filter($rep, fn($x) => (json_decode((string)$x['reponses'], true)['recommande'] ?? '') === 'oui'));
    $rows[] = [$slib($s), count($rep) . ' / ' . count($q), $notes ? number_format(array_sum($notes) / count($notes), 1, ',', '') . ' / 5' : '–', $rep ? round(100 * $reco / count($rep)) . ' %' : '–'];
  }
  echo '<section class="g-card">' . a_table(['Session', 'Réponses', 'Satisfaction globale', 'Recommandent'], $rows, 'Aucune enquête envoyée pour l’instant.') . '</section>';
  $com = db_all("SELECT * FROM enquetes WHERE repondu_at IS NOT NULL ORDER BY repondu_at DESC LIMIT 20");
  echo '<section class="g-card"><h2 class="g-h2">Derniers commentaires</h2>' . a_table(['Date', 'Participant', 'Commentaire'], array_values(array_filter(array_map(fn($q) => ($c = trim((string)(json_decode((string)$q['reponses'], true)['commentaire'] ?? ''))) !== '' ? [h(date('d/m/Y', ts($q['repondu_at']))), h($q['participant']), nl2br(h($c))] : null, $com))), 'Aucun commentaire.') . '</section>';
  a_layout_end(); break;

case 'courriels':
  a_layout_start('Courriels', 'courriels');
  echo '<h1 class="g-h1">Courriels envoyés</h1><section class="g-card">' . a_table(['Date', 'Destinataire', 'Objet', 'État', ''], array_map(fn($m) => [h(date('d/m H:i', ts($m['created_at']))), h($m['destinataire']), h($m['sujet']), a_badge($m['statut']) . ($m['erreur'] ? '<br><small>' . h($m['erreur']) . '</small>' : ''), $m['statut'] !== 'envoye' ? '<form method="post" class="g-inline">' . a_field() . '<input type="hidden" name="a" value="renvoyer"><input type="hidden" name="m" value="' . $m['id'] . '"><button class="g-linkbtn">Renvoyer</button></form>' : ''], db_all('SELECT * FROM courriels ORDER BY id DESC LIMIT 200'))) . '</section>';
  a_layout_end(); break;

case 'exports':
  $an = (int)get('an') ?: (int)date('Y') - 1;
  $ins = db_all("SELECT i.*, s.date_debut, s.programme_id FROM inscriptions i JOIN sessions s ON s.id=i.session_id WHERE substr(s.date_debut,1,4)=? AND i.statut IN ('confirmee','soldee')", [(string)$an]);
  $stag = ['particulier' => 0, 'entreprise' => 0]; $heures = 0;
  foreach ($ins as $i) foreach (participants($i) as $pt) { $stag[$i['type']]++; $heures += (int)db_val('SELECT COUNT(*) FROM presences WHERE inscription_id=? AND participant=? AND present=1', [$i['id'], $pt['nom']]) * (programme((int)$i['programme_id'])['duree_heures'] / max(1, 2 * programme((int)$i['programme_id'])['duree_jours'])); }
  $prodP = (int)db_val("SELECT COALESCE(SUM(f.montant),0) FROM factures f JOIN inscriptions i ON i.id=f.inscription_id WHERE i.type='particulier' AND substr(f.date_emission,1,4)=?", [(string)$an]);
  $prodE = (int)db_val("SELECT COALESCE(SUM(f.montant),0) FROM factures f JOIN inscriptions i ON i.id=f.inscription_id WHERE i.type='entreprise' AND substr(f.date_emission,1,4)=?", [(string)$an]);
  a_layout_start('Exports et BPF', 'exports');
  echo '<div class="g-head"><h1 class="g-h1">Exports et bilan pédagogique et financier</h1><form class="g-actions"><input type="hidden" name="v" value="exports">' . a_input('an', 'Exercice', $an, 'number') . '<button class="g-btn g-ghost">Afficher</button></form></div>';
  echo '<section class="g-card"><h2 class="g-h2">Données préparatoires au BPF ' . $an . '</h2><p class="g-small">À reporter sur « Mon activité formation » une fois la déclaration d’activité enregistrée. Vérifiez-les avec votre comptable.</p><dl class="g-dl">'
    . '<dt>Stagiaires particuliers (fonds propres)</dt><dd>' . $stag['particulier'] . '</dd><dt>Stagiaires salariés (financement employeur)</dt><dd>' . $stag['entreprise'] . '</dd><dt>Heures-stagiaires réalisées (émargement)</dt><dd>' . round($heures) . '</dd>'
    . '<dt>Produits : particuliers</dt><dd>' . h(eur($prodP)) . '</dd><dt>Produits : entreprises</dt><dd>' . h(eur($prodE)) . '</dd><dt>Formateur</dt><dd>1 (le dirigeant)</dd></dl></section>';
  echo '<section class="g-card"><h2 class="g-h2">Exports</h2><p><a class="g-btn g-ghost" href="?v=csv_recettes&an=' . $an . '">Registre des recettes ' . $an . '</a> <a class="g-btn g-ghost" href="?v=csv_enquetes">Enquêtes</a></p><p class="g-small">Listes d’inscrits : depuis la page de chaque session.</p></section>';
  a_layout_end(); break;

case 'parametres':
  a_layout_start('Paramètres', 'parametres');
  $f = fn($k, $l, $t = 'text') => a_input($k, $l, param($k), $t);
  echo '<h1 class="g-h1">Paramètres</h1><section class="g-card"><form method="post" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="save">'
    . $f('nda', 'Numéro de déclaration d’activité (vide tant qu’il n’est pas attribué)') . $f('nda_region', 'Région de la déclaration')
    . '<div class="g-full">' . a_input('parole_lieu_defaut', 'Lieu habituel des formations en présentiel', param('parole_lieu_defaut'), 'textarea', 'rows="2"') . '</div>' . $f('visio_outil', 'Outil de visioconférence')
    . $f('fermeture_particuliers_jours', 'Clôture des inscriptions particuliers (jours avant)', 'number') . $f('fermeture_entreprises_jours', 'Clôture des inscriptions entreprises (jours avant)', 'number')
    . $f('decision_maintien_jours', 'Décision de maintien (jours avant)', 'number') . $f('presque_complete', 'Seuil « presque complète » (places engagées)', 'number')
    . $f('delai_retractation_jours', 'Délai de rétractation particuliers (jours)', 'number') . $f('delai_acompte_heures', 'Validité du lien d’acompte (heures)', 'number')
    . $f('delai_entreprise_jours', 'Blocage des places d’une demande entreprise (jours)', 'number') . $f('delai_invitation_heures', 'Validité d’une invitation de la liste d’attente (heures)', 'number')
    . $f('acces_telechargement_jours', 'Durée d’accès aux téléchargements (jours)', 'number') . $f('max_telechargements', 'Nombre de téléchargements autorisés', 'number')
    . '<div class="g-full"><button class="g-btn">Enregistrer</button></div></form></section>'
    . '<section class="g-card"><h2 class="g-h2">Sécurité</h2><p><a class="g-btn g-ghost" href="/gestion/?reset=1" onclick="return confirm(\'Vous serez déconnecté et recevrez un code par courriel.\')">Changer le mot de passe</a></p><p class="g-small">Dernière exécution des tâches planifiées : ' . h(param('taches_derniere', 'jamais')) . '</p></section>';
  a_layout_end(); break;

default: a_go('v=accueil');
}
