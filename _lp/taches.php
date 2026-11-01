<?php
/* Tâches planifiées : à lancer toutes les 15 minutes. Chaque tâche est idempotente (clé de courriel unique, statuts). */

function taches_run(): array {
  $log = [];
  $n = now();

  // 1. Réservations du tunnel non signées après 30 minutes
  $exp = db_all("SELECT * FROM inscriptions WHERE statut='reservation' AND expire_at < ?", [$n]);
  foreach ($exp as $i) { db_update('inscriptions', (int)$i['id'], ['statut' => 'expiree']); liberer_place((int)$i['session_id']); }
  $log[] = count($exp) . ' réservation(s) expirée(s)';

  // 2. Créneaux de visioconférence bloqués par un paiement abandonné
  db_exec("UPDATE creneaux SET statut='libre', commande_id=NULL, hold_until=NULL WHERE statut='reserve' AND hold_until < ?", [$n]);
  db_exec("UPDATE commandes SET statut='abandonnee' WHERE statut='attente' AND created_at < ?", [date('Y-m-d H:i:s', time() - 7200)]);

  // 3. Fin du délai de rétractation : ouverture de l'acompte (72 h)
  foreach (db_all("SELECT * FROM inscriptions WHERE statut='retractation' AND retractation_fin < ?", [$n]) as $i) {
    db_update('inscriptions', (int)$i['id'], ['statut' => 'acompte_attendu', 'updated_at' => $n]);
    $e = db_one("SELECT * FROM echeances WHERE inscription_id=? AND libelle LIKE 'Acompte%' AND statut='prevue'", [$i['id']]);
    if ($e) ouvrir_echeance($e, (int)param('delai_acompte_heures', 72), 'acompte-' . $e['id']);
    $log[] = 'acompte ouvert : inscription ' . $i['id'];
  }

  // 4. Acompte : rappel à 24 h de l'échéance, puis libération de la place
  foreach (db_all("SELECT e.*, i.session_id FROM echeances e JOIN inscriptions i ON i.id=e.inscription_id WHERE i.statut='acompte_attendu' AND e.statut='attendue' AND e.libelle LIKE 'Acompte%'") as $e) {
    if (ts((string)$e['expire_at']) < time()) {
      db_update('echeances', (int)$e['id'], ['statut' => 'expiree']);
      db_exec("UPDATE echeances SET statut='annulee' WHERE inscription_id=? AND statut='prevue'", [$e['inscription_id']]);
      db_update('inscriptions', (int)$e['inscription_id'], ['statut' => 'expiree', 'motif' => 'acompte non réglé', 'updated_at' => $n]);
      $i = inscription((int)$e['inscription_id']); [$to, $nom] = dest_inscription($i);
      mail_queue($to, 'Votre place a été libérée', email_wrap('Place libérée', p_('Bonjour ' . h($nom) . ',') . p_('Faute de règlement de l’acompte dans le délai prévu, votre place a été remise à disposition. Si vous souhaitez toujours suivre la formation, écrivez-nous : nous verrons ensemble les places disponibles.')), [], 'liberee-' . $i['id']);
      liberer_place((int)$e['session_id']);
      $log[] = 'acompte échu : inscription ' . $e['inscription_id'];
    } elseif (ts((string)$e['expire_at']) - time() < 86400) {
      email_echeance($e, 'acompte-rappel-' . $e['id']);
    }
  }

  // 5. Échéances de solde : ouverture le jour prévu (lien valable 7 jours) et rappel à J+3
  foreach (db_all("SELECT e.* FROM echeances e JOIN inscriptions i ON i.id=e.inscription_id WHERE e.statut='prevue' AND e.due_at <= ? AND i.statut IN ('confirmee')", [$n]) as $e) {
    ouvrir_echeance($e, 7 * 24, 'solde-' . $e['id']);
  }
  foreach (db_all("SELECT * FROM echeances WHERE statut='attendue' AND inscription_id IS NOT NULL AND libelle LIKE 'Solde%' AND due_at < ?", [date('Y-m-d H:i:s', time() - 3 * 86400)]) as $e) {
    email_echeance($e, 'solde-rappel-' . $e['id']);
    admin_notifier('Solde non réglé', p_('Échéance « ' . h($e['libelle']) . ' » de ' . h(eur((int)$e['montant'])) . ' non réglée depuis trois jours.') . btn(url('/gestion/?v=inscription&id=' . $e['inscription_id']), 'Voir l’inscription'));
  }

  // 6. Demandes et devis d'entreprise non signés dans le délai
  foreach (db_all("SELECT * FROM inscriptions WHERE statut IN ('demande','devis_envoye') AND expire_at < ?", [$n]) as $i) {
    db_update('inscriptions', (int)$i['id'], ['statut' => 'expiree', 'updated_at' => $n]);
    admin_notifier('Demande entreprise expirée', p_('Les places bloquées ont été libérées.') . bloc_session_html(session_get((int)$i['session_id'])));
    liberer_place((int)$i['session_id']);
  }

  // 7. Invitations de la liste d'attente échues : invitation suivante
  foreach (db_all("SELECT * FROM attente WHERE statut='invitee' AND expire_at < ?", [$n]) as $a) {
    db_update('attente', (int)$a['id'], ['statut' => 'expiree']);
    liberer_place((int)$a['session_id']);
  }

  // 8. Sessions : décision de maintien, rappels, suivi après formation
  $dm = (int)param('decision_maintien_jours', 10);
  foreach (db_all("SELECT * FROM sessions WHERE etat IN ('ouverte','fermee')") as $s) {
    $j = jours_avant($s); $sid = (int)$s['id'];
    $actifs = db_all("SELECT * FROM inscriptions WHERE session_id=? AND statut IN ('retractation','acompte_attendu','confirmee','soldee')", [$sid]);
    if ($j <= $dm && $j >= 0 && !(int)$s['maintenue']) {
      $sig = places_signees($sid);
      if ($sig >= (int)$s['seuil']) {
        db_update('sessions', $sid, ['maintenue' => 1]);
        foreach ($actifs as $i) { [$to, $nom] = dest_inscription($i); mail_queue($to, 'Votre session est confirmée', email_wrap('Session confirmée', p_('Bonjour ' . h($nom) . ',') . p_('Bonne nouvelle : la session réunit le nombre de participants requis, elle est confirmée.') . bloc_session_html($s)), [], 'maintenue-' . $i['id']); }
        admin_notifier('Session confirmée', bloc_session_html($s) . p_($sig . ' participant(s) engagé(s).'));
      } else {
        mail_queue((string)cfg('mail.admin'), '[Plateforme] Décision de maintien à prendre', email_wrap('Session sous le seuil', bloc_session_html($s) . p_($sig . ' participant(s) engagé(s) pour un seuil de ' . (int)$s['seuil'] . '. Maintenir, reporter ou annuler depuis le tableau de bord.') . btn(url('/gestion/?v=session&id=' . $sid), 'Décider')), [], 'seuil-' . $sid . '-' . date('Y-m-d'));
      }
    }
    foreach ([7, 2] as $jr) {
      if ($j === $jr) foreach ($actifs as $i) {
        if (!in_array($i['statut'], ['confirmee', 'soldee'], true)) continue;
        foreach (participants($i) as $k => $pt) if (!empty($pt['email'])) {
          $lieu = $s['modalite'] === 'distance' ? 'En visioconférence' . ($s['lien_visio'] ? ' : <a href="' . h($s['lien_visio']) . '">rejoindre la session</a>' : ' ; le lien vous sera envoyé la veille.') : nl2br(h($s['lieu'] ?: param('parole_lieu_defaut')));
          mail_queue($pt['email'], ($jr === 7 ? 'Dans une semaine' : 'Après-demain') . ' · ' . programme((int)$s['programme_id'])['titre'], email_wrap($jr === 7 ? 'Votre formation approche' : 'Rendez-vous après-demain', p_('Bonjour ' . h($pt['nom']) . ',') . p_('Nous vous rappelons votre formation :') . bloc_session_html($s) . p_($lieu) . p_('Merci de prévoir une courte prise de parole de deux minutes sur un sujet de votre choix. Accueil quinze minutes avant le début.')), [], "rappel-$jr-" . $i['id'] . "-$k");
        }
      }
    }
    // Lendemain de la formation : remerciement et questionnaire ; relance à J+7 ; questionnaire de transfert à J+30
    $apres = (int)floor((ts(date('Y-m-d')) - ts($s['date_fin'])) / 86400);
    foreach ($actifs as $i) {
      if (!in_array($i['statut'], ['confirmee', 'soldee'], true)) continue;
      foreach (participants($i) as $k => $pt) {
        if (empty($pt['email'])) continue;
        foreach ([['chaud', 1, 7], ['froid', 30, 37]] as [$type, $jEnvoi, $jRelance]) {
          if ($apres < $jEnvoi) continue;
          $q = db_one('SELECT * FROM enquetes WHERE inscription_id=? AND participant=? AND type=?', [$i['id'], $pt['nom'], $type]);
          if (!$q) { $id = db_insert('enquetes', ['inscription_id' => $i['id'], 'participant' => $pt['nom'], 'type' => $type, 'token' => token(20), 'envoye_at' => $n]); $q = db_one('SELECT * FROM enquetes WHERE id=?', [$id]);
            $titre = $type === 'chaud' ? 'Merci pour votre participation' : 'Trente jours après';
            $txt = $type === 'chaud' ? p_('Merci pour ces journées de travail. Votre avis compte : il ne vous prendra que deux minutes.') : p_('Un mois a passé depuis la formation. Où en êtes-vous ? Trois questions pour mesurer ce qui a changé dans votre pratique.');
            mail_queue($pt['email'], $titre, email_wrap($titre, p_('Bonjour ' . h($pt['nom']) . ',') . $txt . btn(url('/enquete/?t=' . $q['token']), 'Répondre au questionnaire') . ($type === 'froid' ? p_('Pour continuer à progresser : <a href="' . h(url('/boutique/')) . '">la boutique</a> et le <a href="' . h(url('/laboratoire.html')) . '">Laboratoire de la parole</a>.') : '')), [], "enquete-$type-" . $i['id'] . "-$k");
          } elseif (!$q['repondu_at'] && !$q['relance_at'] && $apres >= $jRelance) {
            db_update('enquetes', (int)$q['id'], ['relance_at' => $n]);
            mail_queue($pt['email'], 'Votre avis compte', email_wrap('Deux minutes pour votre avis', p_('Bonjour ' . h($pt['nom']) . ',') . p_('Si vous n’avez pas encore eu le temps de répondre, votre retour nous serait précieux.') . btn(url('/enquete/?t=' . $q['token']), 'Répondre')), [], "relance-$type-" . $q['id']);
          }
        }
      }
    }
    if ($apres >= 0 && $s['etat'] === 'ouverte') db_update('sessions', $sid, ['etat' => 'fermee']);
  }

  // 9. Conseils en visioconférence : rappel la veille
  foreach (db_all("SELECT c.*, cr.debut FROM commandes c JOIN creneaux cr ON cr.id=c.creneau_id WHERE c.statut='payee' AND c.rappel_at IS NULL AND cr.debut BETWEEN ? AND ?", [$n, date('Y-m-d H:i:s', time() + 86400)]) as $c) {
    $cl = client((int)$c['client_id']); $p = produit((int)$c['produit_id']); $cr = db_one('SELECT * FROM creneaux WHERE id=?', [$c['creneau_id']]);
    $lien = $cr['lien_visio'] ?: $p['lien_visio'];
    mail_queue($cl['email'], 'Rappel · votre rendez-vous de demain', email_wrap('Rendez-vous demain', p_('Bonjour ' . h($cl['prenom']) . ',') . p_('Nous vous rappelons votre rendez-vous : ' . h(date_fr($cr['debut'], true)) . ' à ' . h(heure_fr(date('H:i', ts($cr['debut'])))) . '.') . ($lien ? btn($lien, 'Rejoindre la visioconférence') : '')), [], 'rappel-visio-' . $c['id']);
    db_update('commandes', (int)$c['id'], ['rappel_at' => $n]);
  }

  // 10. Alertes « nouvelle session » : une seule fois par session ouverte
  foreach (db_all("SELECT * FROM sessions WHERE etat='ouverte' AND date_debut > ?", [date('Y-m-d')]) as $s) {
    foreach (db_all('SELECT * FROM alertes_sessions') as $a) mail_queue($a['email'], 'Nouvelle session ouverte', email_wrap('Une nouvelle session est ouverte', p_('Bonjour,') . p_('Une nouvelle session vient d’ouvrir aux inscriptions :') . bloc_session_html($s) . btn(url('/sessions/' . $s['slug'] . '/'), 'Voir la session') . p_('<span style="font-size:12px;color:#6B7280">Pour ne plus recevoir ces alertes, répondez simplement « stop » à ce message.</span>')), [], 'alerte-' . $s['id'] . '-' . $a['id']);
  }

  $log[] = mail_flush(80) . ' courriel(s) envoyé(s)';
  param_set('taches_derniere', $n);
  return $log;
}
