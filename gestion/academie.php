<?php
/* Tableau de bord : onglet Académie (contenus, accès, suivi). Inclus par gestion/index.php, après l'authentification. */

function ac_go(string $q): void { a_go($q); }
function ac_session_lib(array $s): string { $p = programme((int)$s['programme_id']); return ($p['titre'] ?? '') . ' · ' . date('d/m/Y', ts($s['date_debut'])); }

/* ---------------- Actions ---------------- */
function academie_admin_actions(string $v, string $act, int $id): void {
  $retour = fn(array $r) => $r['session_id'] ? 'v=academie_session&id=' . $r['session_id'] : 'v=academie_programme&id=' . $r['programme_id'];
  switch ($v . ':' . $act) {
    case 'academie:reglages':
      $m = (int)post('academie_duree_mois');
      if ($m < 1 || $m > 24) throw new DomainException('Durée d’accès : entre 1 et 24 mois.');
      param_set('academie_duree_mois', (string)$m);
      journal('admin', 'academie_reglages', 'duree:' . $m);
      a_flash('Durée d’accès enregistrée. Elle s’applique aux accès ouverts à partir de maintenant.'); ac_go('v=academie');

    case 'academie_programme:ressource':
    case 'academie_session:ressource':
      $rid = (int)post('rid');
      $old = $rid ? db_one('SELECT * FROM academie_ressources WHERE id=?', [$rid]) : null;
      $type = array_key_exists(post('type'), ACADEMIE_TYPES) ? post('type') : 'fichier';
      $temps = array_key_exists(post('temps'), ACADEMIE_TEMPS) ? post('temps') : 'avant';
      $titre = line(post('titre', 200));
      if ($titre === '') throw new DomainException('Donnez un titre à la ressource.');
      $row = ['titre' => $titre, 'description' => post('description', 600), 'temps' => $temps, 'type' => $type, 'ordre' => (int)post('ordre'),
        'publie' => post('publie') === '1' ? 1 : 0, 'updated_at' => now()];
      if ($type === 'fichier') {
        $err = (int)($_FILES['fichier']['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new DomainException('Fichier trop volumineux pour l’hébergement (' . ini_get('upload_max_filesize') . ' au plus). Pour une vidéo, utilisez le type « Vidéo ».');
        academie_dossier();
        $f = upload_to('fichier', 'academie', ACADEMIE_EXTS, 100);
        if ($f) { $row['fichier'] = $f; if ($old && $old['fichier']) @unlink(academie_dossier() . '/' . basename((string)$old['fichier'])); }
        elseif (!$old || !$old['fichier']) throw new DomainException('Choisissez le fichier à déposer.');
        $row['url'] = null;
      } else {
        $url = line(post('url', 500));
        if (!preg_match('~^https://[^\s]+$~i', $url)) throw new DomainException('Indiquez une adresse complète commençant par https://.');
        $row['url'] = $url;
      }
      if ($old) { db_update('academie_ressources', $rid, $row); $r = db_one('SELECT * FROM academie_ressources WHERE id=?', [$rid]); }
      else {
        $row += ['created_at' => now(), $v === 'academie_session' ? 'session_id' : 'programme_id' => $id];
        if ($v === 'academie_session' ? !session_get($id) : !programme($id)) throw new DomainException('Rattachement introuvable.');
        $r = db_one('SELECT * FROM academie_ressources WHERE id=?', [db_insert('academie_ressources', $row)]);
      }
      journal('admin', 'academie_ressource', 'ressource:' . $r['id'], $titre);
      a_flash($old ? 'Ressource mise à jour.' : ($r['publie'] ? 'Ressource publiée.' : 'Ressource enregistrée en brouillon.')); ac_go($retour($r));

    case 'academie_programme:publier': case 'academie_session:publier':
    case 'academie_programme:retirer': case 'academie_session:retirer':
    case 'academie_programme:supprimer': case 'academie_session:supprimer':
    case 'academie_programme:annoncer': case 'academie_session:annoncer':
      $r = db_one('SELECT * FROM academie_ressources WHERE id=?', [(int)post('rid')]);
      if (!$r) throw new DomainException('Ressource introuvable.');
      if ($act === 'publier') { db_update('academie_ressources', (int)$r['id'], ['publie' => 1, 'updated_at' => now()]); a_flash('Ressource publiée. Pensez à prévenir les apprenants si besoin.'); }
      if ($act === 'retirer') { db_update('academie_ressources', (int)$r['id'], ['publie' => 0, 'updated_at' => now()]); a_flash('Ressource retirée de l’Académie (conservée en brouillon).'); }
      if ($act === 'supprimer') {
        if ($r['fichier']) @unlink(academie_dossier() . '/' . basename((string)$r['fichier']));
        db_exec('DELETE FROM academie_consultations WHERE ressource_id=?', [$r['id']]);
        db_exec('DELETE FROM academie_ressources WHERE id=?', [$r['id']]);
        a_flash('Ressource supprimée.');
      }
      if ($act === 'annoncer') {
        if (!(int)$r['publie']) throw new DomainException('Publiez la ressource avant de prévenir les apprenants.');
        $n = academie_annoncer($r, post('message', 1500));
        a_flash($n ? $n . ' apprenant(s) prévenu(s).' : 'Aucun apprenant n’a encore accès à ce contenu.');
      }
      journal('admin', 'academie_ressource_' . $act, 'ressource:' . $r['id']);
      ac_go($retour($r));

    case 'academie_session:ouvrir':
      $i = inscription((int)post('iid'));
      if (!$i || (int)$i['session_id'] !== $id) throw new DomainException('Inscription introuvable.');
      $n = academie_ouvrir_manuellement((int)$i['id']); mail_flush();
      a_flash($n ? 'Accès ouvert pour ' . $n . ' participant(s) ; le courriel de bienvenue est parti.' : 'Les accès de ce dossier étaient déjà ouverts ; volet « après » débloqué.');
      ac_go('v=academie_session&id=' . $id);

    case 'academie_session:fermer': case 'academie_session:rouvrir': case 'academie_session:prolonger': case 'academie_session:renvoyer':
      $a = db_one('SELECT * FROM academie_acces WHERE id=?', [(int)post('aid')]);
      if (!$a || (int)$a['session_id'] !== $id) throw new DomainException('Accès introuvable.');
      if ($act === 'fermer') { academie_acces_retirer((int)$a['id'], 'manuel:fermé depuis le tableau de bord'); a_flash('Accès fermé. Il ne sera pas rouvert automatiquement.'); }
      if ($act === 'rouvrir') { academie_acces_ouvrir((int)$a['inscription_id'], $a['email'], (string)$a['nom'], 'manuel', (int)$a['apres_force'] === 1); a_flash('Accès rouvert.'); }
      if ($act === 'prolonger') {
        $base = max(time(), ts((string)$a['fin_at']));
        db_update('academie_acces', (int)$a['id'], ['fin_at' => date('Y-m-d 23:59:59', strtotime('+1 month', $base)), 'rappel_fin_at' => null]);
        journal('admin', 'academie_prolonge', 'acces:' . $a['id']); a_flash('Accès prolongé d’un mois.');
      }
      if ($act === 'renvoyer') {
        if ($a['retire_at']) throw new DomainException('Rouvrez d’abord cet accès.');
        academie_email_bienvenue($a); a_flash('Nouveau lien envoyé à ' . $a['email'] . '.');
      }
      ac_go('v=academie_session&id=' . $id);
  }
}

/* ---------------- Vues ---------------- */
function ac_form_ressource(string $v, int $id, ?array $r = null): string {
  $r = $r ?? ['id' => 0, 'titre' => '', 'description' => '', 'temps' => 'avant', 'type' => 'fichier', 'url' => '', 'ordre' => 0, 'publie' => 1, 'fichier' => ''];
  $o = '<form method="post" enctype="multipart/form-data" action="/gestion/?v=' . $v . '&id=' . $id . '" class="g-grid">' . a_field()
    . '<input type="hidden" name="a" value="ressource"><input type="hidden" name="rid" value="' . (int)$r['id'] . '">'
    . a_input('titre', 'Titre', $r['titre'], 'text', 'required maxlength="200"')
    . a_select('temps', 'Temps de la formation', ACADEMIE_TEMPS, $r['temps'])
    . a_select('type', 'Type', ACADEMIE_TYPES, $r['type'])
    . a_input('ordre', 'Ordre d’affichage', (string)$r['ordre'], 'number', 'min="0" max="999"')
    . '<label class="g-f g-full">Fichier (PDF, Word, PowerPoint, Excel, image, audio ; 100 Mo au plus)' . ($r['fichier'] ? ' : remplace « ' . h((string)$r['fichier']) . ' »' : '') . '<input type="file" name="fichier" accept=".' . implode(',.', ACADEMIE_EXTS) . '"></label>'
    . '<label class="g-f g-full">Adresse de la vidéo ou du lien (https://…)<input type="url" name="url" value="' . h((string)$r['url']) . '" placeholder="Bunny Stream, Vimeo, YouTube non répertorié, Microsoft Stream, page externe"></label>'
    . '<label class="g-f g-full">Courte description (facultatif)<textarea name="description" rows="2" maxlength="600">' . h((string)$r['description']) . '</textarea></label>'
    . a_select('publie', 'État', ['1' => 'Publiée', '0' => 'Brouillon'], (string)$r['publie'])
    . '<div class="g-full"><button class="g-btn">' . ($r['id'] ? 'Enregistrer les modifications' : 'Ajouter la ressource') . '</button></div></form>';
  return $o;
}

function ac_liste_ressources(string $v, int $id, array $rs, bool $communes = false): string {
  if (!$rs) return '<p class="g-empty">' . ($communes ? 'Aucune ressource commune au programme.' : 'Aucune ressource pour l’instant.') . '</p>';
  $vues = [];
  foreach (db_all('SELECT ressource_id, COUNT(*) AS n FROM academie_consultations GROUP BY ressource_id') as $c) $vues[$c['ressource_id']] = (int)$c['n'];
  $rows = [];
  foreach ($rs as $r) {
    $btn = fn(string $a, string $l, string $cl = 'g-ghost', string $extra = '') => '<form method="post" action="/gestion/?v=' . $v . '&id=' . $id . '" class="g-inline">' . a_field() . '<input type="hidden" name="a" value="' . $a . '"><input type="hidden" name="rid" value="' . (int)$r['id'] . '">' . $extra . '<button class="g-btn ' . $cl . '"' . ($a === 'supprimer' ? ' onclick="return confirm(\'Supprimer définitivement cette ressource ?\')"' : '') . '>' . $l . '</button></form>';
    $quoi = $r['type'] === 'fichier' ? h(strtoupper(pathinfo((string)$r['fichier'], PATHINFO_EXTENSION)) . ' · ' . academie_taille((string)$r['fichier'])) : h(ACADEMIE_TYPES[$r['type']]) . (academie_video_embed((string)$r['url']) ? ' intégrée' : '');
    $rows[] = [h(ACADEMIE_TEMPS[$r['temps']] ?? $r['temps']), '<strong>' . h($r['titre']) . '</strong>' . ($r['description'] ? '<br><span class="g-small">' . h($r['description']) . '</span>' : ''), $quoi,
      (int)$r['publie'] ? '<span class="g-b g-b-ok">Publiée</span>' : '<span class="g-b g-b-wait">Brouillon</span>', (string)($vues[$r['id']] ?? 0),
      '<div class="g-actions">' . ((int)$r['publie'] ? $btn('retirer', 'Retirer') . $btn('annoncer', 'Prévenir') : $btn('publier', 'Publier', '')) . '<a class="g-btn g-ghost" href="/gestion/?v=' . $v . '&id=' . $id . '&edit=' . (int)$r['id'] . '#form">Modifier</a>' . $btn('supprimer', 'Supprimer', 'g-red') . '</div>'];
  }
  return a_table(['Temps', 'Ressource', 'Format', 'État', 'Vues', ''], $rows);
}

function academie_admin_vues(string $v, int $id): void {
  if ($v === 'academie') {
    a_layout_start('Académie', 'academie');
    echo '<div class="g-head"><h1 class="g-h1">Lead Performant Académie</h1><div class="g-actions"><a class="g-btn g-ghost" href="/academie/" target="_blank" rel="noopener">Voir la porte d’entrée</a></div></div>';
    $actifs = (int)db_val('SELECT COUNT(*) FROM academie_acces WHERE retire_at IS NULL AND fin_at > ?', [now()]);
    $semaine = (int)db_val("SELECT COUNT(DISTINCT email) FROM academie_liens WHERE type='session' AND created_at > ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
    $res = (int)db_val('SELECT COUNT(*) FROM academie_ressources WHERE publie=1');
    echo '<div class="g-kpis"><div class="g-kpi"><span>Accès actifs</span><strong>' . $actifs . '</strong></div><div class="g-kpi"><span>Apprenants connectés (7 jours)</span><strong>' . $semaine . '</strong></div><div class="g-kpi"><span>Ressources publiées</span><strong>' . $res . '</strong></div><div class="g-kpi"><span>Durée d’accès</span><strong>' . (int)param('academie_duree_mois', 3) . ' mois</strong><small>après la session</small></div></div>';
    $rows = [];
    foreach (db_all("SELECT * FROM sessions WHERE etat NOT IN ('brouillon','annulee') AND date_fin >= ? ORDER BY date_debut", [date('Y-m-d', strtotime('-' . ((int)param('academie_duree_mois', 3) + 1) . ' months'))]) as $s) {
      $na = (int)db_val('SELECT COUNT(*) FROM academie_acces WHERE session_id=? AND retire_at IS NULL', [$s['id']]);
      $nr = (int)db_val('SELECT COUNT(*) FROM academie_ressources WHERE publie=1 AND (session_id=? OR programme_id=?)', [$s['id'], $s['programme_id']]);
      $rows[] = ['<a href="/gestion/?v=academie_session&id=' . $s['id'] . '"><strong>' . h(ac_session_lib($s)) . '</strong></a>', h(ucfirst(periode_fr($s['date_debut'], $s['date_fin']))), (string)$na, (string)$nr];
    }
    echo '<section class="g-card"><h2 class="g-h2">Sessions</h2>' . a_table(['Session', 'Dates', 'Accès ouverts', 'Ressources visibles'], $rows, 'Aucune session en cours ou à venir.') . '</section>';
    $rows = [];
    foreach (db_all('SELECT * FROM programmes ORDER BY numero') as $p) {
      $n = (int)db_val('SELECT COUNT(*) FROM academie_ressources WHERE programme_id=?', [$p['id']]);
      $rows[] = ['<a href="/gestion/?v=academie_programme&id=' . $p['id'] . '"><strong>' . h($p['titre']) . '</strong></a>', (string)$n, (int)$p['actif'] ? 'Actif' : 'Inactif'];
    }
    echo '<section class="g-card"><h2 class="g-h2">Ressources communes par programme</h2><p class="g-small">Déposées une fois, elles apparaissent dans toutes les sessions du programme : programme détaillé, règlement intérieur, supports, fiches d’exercices.</p>' . a_table(['Programme', 'Ressources', 'État'], $rows) . '</section>';
    echo '<section class="g-card"><h2 class="g-h2">Réglages</h2><form method="post" action="/gestion/?v=academie" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="reglages">'
      . a_input('academie_duree_mois', 'Durée d’accès après le dernier jour de session (mois)', (string)param('academie_duree_mois', 3), 'number', 'min="1" max="24"')
      . '<div><button class="g-btn">Enregistrer</button></div></form><p class="g-small">Règles en vigueur : accès dès l’acompte réglé pour un particulier, au règlement complet pour une entreprise ; replays (volet « après ») réservés aux dossiers soldés ou ouverts à la main ; retrait automatique en cas de rétractation, d’annulation ou de remboursement.</p></section>';
    a_layout_end(); return;
  }

  if ($v === 'academie_programme') {
    $p = programme($id); if (!$p) ac_go('v=academie');
    a_layout_start('Académie · ' . $p['titre'], 'academie');
    echo '<p><a href="/gestion/?v=academie">← Académie</a></p><div class="g-head"><h1 class="g-h1">' . h($p['titre']) . '</h1></div>';
    $rs = db_all('SELECT * FROM academie_ressources WHERE programme_id=? ORDER BY temps, ordre, id', [$id]);
    echo '<section class="g-card"><h2 class="g-h2">Ressources communes à toutes les sessions</h2>' . ac_liste_ressources('academie_programme', $id, $rs, true) . '</section>';
    $edit = (int)get('edit') ? db_one('SELECT * FROM academie_ressources WHERE id=? AND programme_id=?', [(int)get('edit'), $id]) : null;
    echo '<section class="g-card" id="form"><h2 class="g-h2">' . ($edit ? 'Modifier la ressource' : 'Ajouter une ressource') . '</h2>' . ac_form_ressource('academie_programme', $id, $edit) . '</section>';
    a_layout_end(); return;
  }

  if ($v === 'academie_session') {
    $s = session_get($id); if (!$s) ac_go('v=academie');
    a_layout_start('Académie · ' . ac_session_lib($s), 'academie');
    echo '<p><a href="/gestion/?v=academie">← Académie</a> · <a href="/gestion/?v=session&id=' . $id . '">Fiche de la session</a></p>';
    echo '<div class="g-head"><h1 class="g-h1">' . h(ac_session_lib($s)) . '</h1><div class="g-actions"><a class="g-btn g-ghost" href="/gestion/?v=academie_programme&id=' . (int)$s['programme_id'] . '">Ressources du programme</a></div></div>';

    // Accès des participants, dossier par dossier
    $rows = [];
    foreach (db_all("SELECT * FROM inscriptions WHERE session_id=? AND statut IN ('" . implode("','", ENGAGES) . "','annulee','remboursee','retractee') ORDER BY id", [$id]) as $i) {
      $qui = $i['type'] === 'entreprise' ? (organisation((int)$i['organisation_id'])['raison_sociale'] ?? 'Entreprise') : 'Particulier';
      $acc = []; foreach (db_all('SELECT * FROM academie_acces WHERE inscription_id=?', [$i['id']]) as $a) $acc[$a['email']] = $a;
      foreach (participants($i) as $pt) {
        $email = academie_email((string)($pt['email'] ?? ''));
        $a = $acc[$email] ?? null;
        $btn = fn(string $act, string $l, string $cl = 'g-ghost') => '<form method="post" action="/gestion/?v=academie_session&id=' . $id . '" class="g-inline">' . a_field() . '<input type="hidden" name="a" value="' . $act . '"><input type="hidden" name="aid" value="' . (int)($a['id'] ?? 0) . '"><button class="g-btn ' . $cl . '">' . $l . '</button></form>';
        if ($a && !$a['retire_at']) {
          $etat = academie_actif($a) ? '<span class="g-b g-b-ok">Ouvert</span><br><span class="g-small">jusqu’au ' . h(date('d/m/Y', ts((string)$a['fin_at']))) . ((int)$a['apres_force'] ? ' · replays débloqués' : '') . '</span>' : '<span class="g-b g-b-wait">Échu</span>';
          $der = db_val("SELECT MAX(created_at) FROM academie_liens WHERE email=? AND type='session'", [$email]);
          $nb = (int)db_val('SELECT COUNT(*) FROM academie_consultations WHERE acces_id=?', [$a['id']]);
          $suivi = $der ? 'connexion le ' . date('d/m H:i', ts((string)$der)) . '<br>' . $nb . ' ressource(s) consultée(s)' : 'jamais connecté';
          $actions = $btn('renvoyer', 'Renvoyer le lien') . $btn('prolonger', '+1 mois') . $btn('fermer', 'Fermer', 'g-red');
        } elseif ($a) {
          $etat = '<span class="g-b g-b-ko">Fermé</span><br><span class="g-small">' . h(str_replace(['manuel:', 'auto:'], ['', 'automatique : '], (string)$a['motif'])) . '</span>'; $suivi = '–';
          $actions = $btn('rouvrir', 'Rouvrir');
        } else {
          $etat = '<span class="g-b g-b-wait">Non ouvert</span>'; $suivi = '–'; $actions = '';
        }
        $rows[] = ['<strong>' . h((string)$pt['nom']) . '</strong><br><span class="g-small">' . h($email ?: 'adresse manquante') . '</span>', h($qui) . '<br>' . a_badge($i['statut']), $etat, $suivi, '<div class="g-actions">' . $actions . '</div>'];
      }
      $avecEmail = count(array_filter(participants($i), fn($x) => is_email(academie_email((string)($x['email'] ?? '')))));
      $ouverts = array_filter($acc, fn($x) => !$x['retire_at']);
      $sansReplay = array_filter($ouverts, fn($x) => !(int)$x['apres_force']);
      if (in_array($i['statut'], ['confirmee', 'soldee'], true) && (count($ouverts) < $avecEmail || ($i['statut'] !== 'soldee' && $sansReplay))) {
        $rows[] = ['', '<span class="g-small">Dossier ' . h($qui) . '</span>', '', '', '<form method="post" action="/gestion/?v=academie_session&id=' . $id . '" class="g-inline">' . a_field() . '<input type="hidden" name="a" value="ouvrir"><input type="hidden" name="iid" value="' . (int)$i['id'] . '"><button class="g-btn">Ouvrir l’accès du dossier</button></form>'];
      }
    }
    echo '<section class="g-card"><h2 class="g-h2">Accès des participants</h2><p class="g-small">Ouverture automatique : à l’acompte pour un particulier, au règlement complet pour une entreprise. « Ouvrir l’accès du dossier » sert aux règlements à service fait et aux invités ; il débloque aussi les replays.</p>' . a_table(['Participant', 'Dossier', 'Accès', 'Suivi', ''], $rows, 'Aucun inscrit pour cette session.') . '</section>';

    $rs = db_all('SELECT * FROM academie_ressources WHERE session_id=? ORDER BY temps, ordre, id', [$id]);
    echo '<section class="g-card"><h2 class="g-h2">Ressources propres à cette session</h2><p class="g-small">Replays, documents produits en séance, informations pratiques du groupe. Les ressources communes du programme s’affichent aussi aux apprenants.</p>' . ac_liste_ressources('academie_session', $id, $rs) . '</section>';
    $edit = (int)get('edit') ? db_one('SELECT * FROM academie_ressources WHERE id=? AND session_id=?', [(int)get('edit'), $id]) : null;
    echo '<section class="g-card" id="form"><h2 class="g-h2">' . ($edit ? 'Modifier la ressource' : 'Ajouter une ressource à la session') . '</h2>' . ac_form_ressource('academie_session', $id, $edit) . '</section>';
    a_layout_end(); return;
  }
  ac_go('v=academie');
}
