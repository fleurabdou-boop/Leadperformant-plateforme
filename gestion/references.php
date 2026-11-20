<?php
/* Tableau de bord : rubrique « Références et témoignages » (logos des organisations, témoignages).
   Inclus par gestion/index.php, après l'authentification. Chaque modification met à jour le site aussitôt (refs_publier). */

function refs_badge(string $etat, string $accord = 'x'): string {
  if ($etat === 'publie' && $accord !== '') return '<span class="g-b g-b-ok">En ligne</span>';
  if ($etat === 'a_valider') return '<span class="g-b g-b-wait">À valider</span>';
  return '<span class="g-b">Hors ligne</span>';
}
function refs_pole_court(string $p): string { return ['p1' => 'Pôle I', 'p2' => 'Pôle II'][$p] ?? 'Les deux'; }
function refs_extrait(string $s, int $n = 110): string { $s = line($s); return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s; }

/* ---------------- Actions ---------------- */
function refs_admin_actions(string $v, string $act, int $id): void {
  switch ($v . ':' . $act) {
    case 'reference_temoignage:save':
      $texte = trim(preg_replace("/\n{3,}/", "\n\n", post('texte', 1800)));
      if (mb_strlen($texte) < 20) throw new DomainException('Le témoignage est vide ou trop court (20 caractères au moins).');
      $old = $id ? db_one('SELECT * FROM ref_temoignages WHERE id=?', [$id]) : null;
      $accord = array_key_exists(post('accord'), REFS_ACCORDS) ? post('accord') : '';
      $etat = array_key_exists(post('etat'), REFS_ETATS) ? post('etat') : 'hors_ligne';
      $mois = post('date_temoignage', 10);
      $date = preg_match('/^\d{4}-\d{2}$/', $mois) ? $mois . '-01' : (preg_match('/^\d{4}-\d{2}-\d{2}$/', $mois) ? $mois : null);
      $accAt = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('accord_at', 10)) ? post('accord_at', 10) : null;
      $email = mb_strtolower(line(post('email', 190)));
      $msg = 'Témoignage enregistré.';
      if ($email !== '' && !is_email($email)) { $email = ''; $msg .= ' L’adresse de courriel, invalide, n’a pas été conservée.'; }
      $row = ['texte' => $texte, 'auteur' => line(post('auteur', 160)), 'fonction' => line(post('fonction', 200)), 'organisation' => line(post('organisation', 200)),
        'affichage' => array_key_exists(post('affichage'), REFS_AFFICHAGE) ? post('affichage') : 'complet', 'contexte' => line(post('contexte', 200)),
        'pole' => array_key_exists(post('pole'), REFS_POLES) ? post('pole') : 'tous', 'date_temoignage' => $date, 'une' => post('une') === '1' ? 1 : 0,
        'ordre' => max(-999, min(999, (int)post('ordre'))), 'etat' => $etat, 'accord' => $accord, 'accord_at' => $accAt, 'email' => $email, 'updated_at' => now()];
      if ($row['auteur'] === '' && $row['fonction'] === '' && $row['organisation'] === '') $msg .= ' Pensez à indiquer au moins une fonction ou une organisation : un témoignage sans source convainc peu.';
      if ($etat === 'publie' && $accord === '') {
        $row['etat'] = $old && $old['etat'] === 'a_valider' ? 'a_valider' : 'hors_ligne';
        $msg = 'Témoignage enregistré mais laissé hors ligne : indiquez d’abord comment l’auteur a donné son accord de publication.';
      } elseif ($accord !== '' && !$accAt) $row['accord_at'] = date('Y-m-d');
      if ($id && $old) db_update('ref_temoignages', $id, $row);
      else $id = db_insert('ref_temoignages', $row + ['origine' => 'admin', 'created_at' => now()]);
      refs_publier();
      journal('admin', 'temoignage_enregistre', 'temoignage:' . $id, $row['etat']);
      a_flash($row['etat'] === 'publie' ? $msg . ' Il est en ligne.' : $msg); a_go('v=reference_temoignage&id=' . $id);

    case 'reference_temoignage:supprimer':
      db_exec('DELETE FROM ref_temoignages WHERE id=?', [$id]);
      refs_publier(); journal('admin', 'temoignage_supprime', 'temoignage:' . $id);
      a_flash('Témoignage supprimé définitivement.'); a_go('v=references');

    case 'reference_logo:save':
      $nom = line(post('nom', 200));
      if ($nom === '') throw new DomainException('Indiquez le nom de l’organisation.');
      $old = $id ? db_one('SELECT * FROM ref_logos WHERE id=?', [$id]) : null;
      $msg = 'Logo enregistré.';
      $lien = line(post('lien', 300));
      if ($lien !== '' && !preg_match('~^https?://[^\s<>"\']+$~i', $lien)) { $lien = ''; $msg .= ' Le lien a été ignoré : il doit commencer par https://.'; }
      $row = ['nom' => $nom, 'categorie' => array_key_exists(post('categorie'), REFS_CATEGORIES) ? post('categorie') : 'entreprise',
        'pole' => array_key_exists(post('pole'), REFS_POLES) ? post('pole') : 'tous', 'fond' => post('fond') === 'sombre' ? 'sombre' : 'clair', 'lien' => $lien,
        'accord' => post('accord') === '1' ? 1 : 0, 'ordre' => max(-999, min(999, (int)post('ordre'))), 'publie' => post('publie') === '1' ? 1 : 0, 'updated_at' => now()];
      $dep = refs_logo_depot('fichier');
      if ($dep) [$row['fichier'], $row['largeur'], $row['hauteur']] = $dep;
      $aFichier = $dep || ($old && $old['fichier']);
      if ($row['publie'] && !$aFichier) { $row['publie'] = 0; $msg = 'Logo enregistré mais laissé hors ligne : ajoutez le fichier du logo.'; }
      elseif ($row['publie'] && !$row['accord']) { $row['publie'] = 0; $msg = 'Logo enregistré mais laissé hors ligne : confirmez d’abord que l’organisation a donné son accord.'; }
      if ($id && $old) { db_update('ref_logos', $id, $row); if ($dep) refs_logo_supprimer_fichier($old['fichier']); }
      else $id = db_insert('ref_logos', $row + ['created_at' => now()]);
      refs_publier();
      journal('admin', 'logo_enregistre', 'logo:' . $id, $row['publie'] ? 'en ligne' : 'hors ligne');
      a_flash($row['publie'] ? $msg . ' Il est en ligne.' : $msg); a_go('v=reference_logo&id=' . $id);

    case 'reference_logo:supprimer':
      $l = db_one('SELECT * FROM ref_logos WHERE id=?', [$id]);
      if ($l) { db_exec('DELETE FROM ref_logos WHERE id=?', [$id]); refs_logo_supprimer_fichier($l['fichier']); refs_publier(); journal('admin', 'logo_supprime', 'logo:' . $id, $l['nom']); }
      a_flash('Logo supprimé.'); a_go('v=references');

    case 'references:basculer':
      $rid = (int)post('rid');
      if (post('t') === 'logo') {
        $l = db_one('SELECT * FROM ref_logos WHERE id=?', [$rid]);
        if (!$l) a_go('v=references');
        if (!$l['publie'] && !$l['fichier']) throw new DomainException('« ' . $l['nom'] . ' » ne peut pas être mis en ligne : le fichier du logo manque.');
        if (!$l['publie'] && !(int)$l['accord']) throw new DomainException('« ' . $l['nom'] . ' » ne peut pas être mis en ligne : l’accord de l’organisation n’est pas confirmé. Ouvrez la fiche du logo pour le préciser.');
        db_update('ref_logos', $rid, ['publie' => $l['publie'] ? 0 : 1, 'updated_at' => now()]);
        refs_publier(); a_flash($l['publie'] ? 'Logo retiré du site.' : 'Logo mis en ligne.');
      } else {
        $t = db_one('SELECT * FROM ref_temoignages WHERE id=?', [$rid]);
        if (!$t) a_go('v=references');
        $en = $t['etat'] === 'publie';
        if (!$en && $t['accord'] === '') throw new DomainException('Ce témoignage ne peut pas être mis en ligne : l’accord de publication de l’auteur n’est pas indiqué. Ouvrez-le pour le préciser.');
        db_update('ref_temoignages', $rid, ['etat' => $en ? 'hors_ligne' : 'publie', 'accord_at' => $t['accord_at'] ?: date('Y-m-d'), 'updated_at' => now()]);
        refs_publier(); a_flash($en ? 'Témoignage retiré du site.' : 'Témoignage mis en ligne.');
      }
      a_go('v=references');

    case 'references:republier':
      refs_publier(); a_flash('Le site a été mis à jour.'); a_go('v=references');
  }
  a_go('v=' . $v . ($id ? '&id=' . $id : ''));
}

/* ---------------- Vues ---------------- */
function refs_admin_vues(string $v, int $id): void {
  if ($v === 'reference_logo') { refs_vue_logo($id); return; }
  if ($v === 'reference_temoignage') { refs_vue_temoignage($id); return; }
  refs_vue_liste();
}

function refs_bascule(string $t, int $rid, bool $enLigne): string {
  return '<form method="post" class="g-inline" action="/gestion/?v=references">' . a_field() . '<input type="hidden" name="a" value="basculer"><input type="hidden" name="t" value="' . h($t) . '"><input type="hidden" name="rid" value="' . $rid . '"><button class="g-linkbtn">' . ($enLigne ? 'Retirer' : 'Mettre en ligne') . '</button></form>';
}
function refs_vignette(array $l, int $mh = 34): string {
  if (!$l['fichier']) return '<span class="g-small">sans fichier</span>';
  $bg = $l['fond'] === 'sombre' ? '#0B1A33' : '#FFFFFF';
  return '<span style="display:inline-flex;align-items:center;justify-content:center;min-width:120px;height:' . ($mh + 16) . 'px;padding:0 10px;background:' . $bg . ';border:1px solid #E6E1D6;border-radius:6px"><img src="' . h(refs_logo_src($l)) . '" alt="" style="max-height:' . $mh . 'px;max-width:140px"></span>';
}

function refs_vue_liste(): void {
  $tems = db_all("SELECT * FROM ref_temoignages ORDER BY CASE etat WHEN 'a_valider' THEN 0 WHEN 'publie' THEN 1 ELSE 2 END, une DESC, ordre, id DESC");
  $logos = db_all('SELECT * FROM ref_logos ORDER BY publie DESC, ordre, nom');
  $tl = count(array_filter($tems, fn($t) => $t['etat'] === 'publie' && $t['accord'] !== ''));
  $av = count(array_filter($tems, fn($t) => $t['etat'] === 'a_valider'));
  $ll = count(array_filter($logos, fn($l) => (int)$l['publie'] === 1 && (int)$l['accord'] === 1 && $l['fichier']));
  a_layout_start('Références et témoignages', 'references');
  echo '<div class="g-head"><div><h1 class="g-h1">Références et témoignages</h1><p class="g-small" style="max-width:720px;margin:0">Ce qui est en ligne apparaît aussitôt sur la page <a href="/references/" target="_blank" rel="noopener">Références et témoignages</a>, sur l’accueil et sur la page du pôle concerné. Ces sections restent masquées sur le site tant que rien n’est publié.</p></div>'
    . '<div class="g-actions"><a class="g-btn" href="?v=reference_temoignage">Ajouter un témoignage</a><a class="g-btn g-ghost" href="?v=reference_logo">Ajouter un logo</a><a class="g-btn g-ghost" href="/references/" target="_blank" rel="noopener">Voir la page</a></div></div>';
  echo '<div class="g-kpis"><div class="g-kpi"><span>Témoignages en ligne</span><strong>' . $tl . '</strong><small>sur ' . count($tems) . ' enregistré(s)</small></div>'
    . '<div class="g-kpi"><span>À valider</span><strong>' . $av . '</strong><small>reçus par le formulaire du site</small></div>'
    . '<div class="g-kpi"><span>Logos en ligne</span><strong>' . $ll . '</strong><small>sur ' . count($logos) . ' enregistré(s)</small></div></div>';

  $rows = [];
  foreach ($tems as $t) {
    $aff = refs_auteur($t); $q = refs_qualite($t);
    $rows[] = ['<a href="?v=reference_temoignage&id=' . $t['id'] . '">« ' . h(refs_extrait((string)$t['texte'])) . ' »</a>',
      h($aff !== '' ? $aff : '—') . ($q !== '' ? '<br><span class="g-small">' . h($q) . '</span>' : ''),
      h(refs_pole_court((string)$t['pole'])),
      $t['accord'] !== '' ? '<span class="g-b g-b-ok">Recueilli</span>' : '<span class="g-b g-b-ko">À recueillir</span>',
      refs_badge((string)$t['etat'], (string)$t['accord']) . ((int)$t['une'] ? ' <span class="g-b">À la une</span>' : ''),
      (string)(int)$t['ordre'],
      refs_bascule('tem', (int)$t['id'], $t['etat'] === 'publie')];
  }
  echo '<section class="g-card"><h2 class="g-h2">Témoignages</h2>' . a_table(['Témoignage', 'Auteur affiché', 'Pôle', 'Accord', 'État', 'Ordre', ''], $rows, 'Aucun témoignage pour le moment. Ajoutez le premier, ou transmettez le lien du formulaire ci-dessous à vos participants.') . '</section>';

  $rows = [];
  foreach ($logos as $l) {
    $rows[] = [refs_vignette($l), '<a href="?v=reference_logo&id=' . $l['id'] . '">' . h($l['nom']) . '</a><br><span class="g-small">' . h(REFS_CATEGORIES[$l['categorie']] ?? '') . '</span>',
      h(refs_pole_court((string)$l['pole'])),
      (int)$l['accord'] ? '<span class="g-b g-b-ok">Confirmé</span>' : '<span class="g-b g-b-ko">À confirmer</span>',
      (int)$l['publie'] && (int)$l['accord'] && $l['fichier'] ? '<span class="g-b g-b-ok">En ligne</span>' : '<span class="g-b">Hors ligne</span>',
      (string)(int)$l['ordre'], refs_bascule('logo', (int)$l['id'], (bool)(int)$l['publie'])];
  }
  echo '<section class="g-card"><h2 class="g-h2">Logos des organisations</h2>' . a_table(['Logo', 'Organisation', 'Pôle', 'Accord', 'État', 'Ordre', ''], $rows, 'Aucun logo pour le moment.') . '</section>';

  $lien = url('/references/#temoigner');
  echo '<section class="g-card"><h2 class="g-h2">Recueillir des témoignages</h2><p>Transmettez ce lien à vos participants et clients, par exemple à la fin d’une session : <a href="' . h($lien) . '" target="_blank" rel="noopener"><strong>' . h($lien) . '</strong></a></p>'
    . '<p class="g-small">Les témoignages reçus arrivent ici avec l’état « À valider » (vous êtes prévenu par courriel) : rien n’est publié sans votre relecture. L’auteur a coché l’autorisation de publication ; vous pouvez corriger la forme, jamais le sens, et retirer un témoignage à sa demande à tout moment.</p>'
    . '<form method="post" action="/gestion/?v=references">' . a_field() . '<input type="hidden" name="a" value="republier"><button class="g-linkbtn">Mettre à jour le site maintenant</button></form></section>';
  a_layout_end();
}

function refs_vue_temoignage(int $id): void {
  $t = $id ? db_one('SELECT * FROM ref_temoignages WHERE id=?', [$id]) : null;
  if ($id && !$t) a_go('v=references');
  $tv = $t ?? ['texte' => '', 'auteur' => '', 'fonction' => '', 'organisation' => '', 'affichage' => 'complet', 'contexte' => '', 'pole' => 'p1', 'date_temoignage' => '',
    'une' => 0, 'ordre' => 0, 'etat' => 'hors_ligne', 'accord' => '', 'accord_at' => '', 'email' => '', 'origine' => 'admin', 'created_at' => ''];
  a_layout_start($t ? 'Témoignage' : 'Nouveau témoignage', 'references');
  $titre = $t ? 'Témoignage' . (refs_auteur($t) !== '' ? ' de ' . refs_auteur($t) : '') : 'Nouveau témoignage';
  echo '<div class="g-head"><div><p class="g-small"><a href="?v=references">‹ Références et témoignages</a></p><h1 class="g-h1">' . h($titre) . '</h1></div>' . ($t ? '<div>' . refs_badge((string)$t['etat'], (string)$t['accord']) . '</div>' : '') . '</div>';
  if ($t && $t['origine'] === 'site') echo '<p class="g-msg">Reçu par le formulaire du site le ' . h(date('d/m/Y à H:i', ts((string)$t['created_at']))) . '. L’auteur a coché l’autorisation de publication' . ($t['email'] ? ' ; vous pouvez lui écrire à <a href="mailto:' . h($t['email']) . '">' . h($t['email']) . '</a>' : '') . '. Relisez, corrigez la forme si besoin, puis passez l’état à « En ligne ».</p>';
  if ($t) {
    $aff = refs_auteur($t); $q = refs_qualite($t);
    $date = $t['date_temoignage'] ? MOIS[(int)substr((string)$t['date_temoignage'], 5, 2)] . ' ' . substr((string)$t['date_temoignage'], 0, 4) : '';
    echo '<section class="g-card"><h2 class="g-h2">Aperçu, tel qu’il s’affiche sur le site</h2><div class="g-quote" style="font-family:Georgia,serif;font-size:17px;line-height:1.55">« ' . nl2br(h((string)$t['texte'])) . ' »</div>'
      . '<p style="margin:12px 0 0"><strong>' . h($aff) . '</strong>' . ($q !== '' ? ($aff !== '' ? ' · ' : '') . h($q) : '') . '</p><p class="g-small" style="margin:2px 0 0">' . h(implode(' · ', array_filter([(string)$t['contexte'], $date, REFS_POLES[$t['pole']] ?? '']))) . '</p></section>';
  }
  $offres = '<datalist id="refs-offres">'; foreach (REFS_OFFRES as $l) foreach ($l as $o) $offres .= '<option value="' . h($o) . '">'; $offres .= '</datalist>';
  echo '<section class="g-card"><form method="post" class="g-grid">' . a_field() . '<input type="hidden" name="a" value="save">'
    . '<div class="g-full">' . a_input('texte', 'Témoignage (idéalement 200 à 600 caractères : une idée forte, un résultat concret ; une ligne vide sépare deux paragraphes)', $tv['texte'], 'textarea', 'rows="7" required maxlength="1800"') . '</div>'
    . a_input('auteur', 'Prénom et nom de l’auteur', $tv['auteur'], 'text', 'maxlength="160"')
    . a_select('affichage', 'Nom affiché sur le site', REFS_AFFICHAGE, $tv['affichage'])
    . a_input('fonction', 'Fonction', $tv['fonction'], 'text', 'maxlength="200" placeholder="Directrice des ressources humaines"')
    . a_input('organisation', 'Organisation', $tv['organisation'], 'text', 'maxlength="200"')
    . a_input('contexte', 'Formation ou intervention concernée', $tv['contexte'], 'text', 'maxlength="200" list="refs-offres" placeholder="Éloquence Élite"') . $offres
    . a_select('pole', 'Pôle', REFS_POLES, $tv['pole'])
    . a_input('date_temoignage', 'Mois du témoignage (affiché)', $tv['date_temoignage'] ? substr((string)$tv['date_temoignage'], 0, 7) : '', 'month')
    . a_input('email', 'Courriel de l’auteur (jamais affiché)', $tv['email'], 'email', 'maxlength="190"')
    . a_select('accord', 'Accord de publication de l’auteur', REFS_ACCORDS, $tv['accord'])
    . a_input('accord_at', 'Date de l’accord', (string)$tv['accord_at'], 'date')
    . a_select('etat', 'État', REFS_ETATS, $tv['etat'])
    . a_select('une', 'À la une (affiché en priorité sur l’accueil)', ['0' => 'Non', '1' => 'Oui'], (string)(int)$tv['une'])
    . a_input('ordre', 'Ordre d’affichage (le plus petit en premier)', (string)(int)$tv['ordre'], 'number', 'min="-999" max="999"')
    . '<p class="g-small g-full">Un témoignage n’est mis en ligne que si l’accord de son auteur est indiqué. Corrigez la forme si besoin, jamais le sens ; à la demande de l’auteur, retirez-le ou supprimez-le.</p>'
    . '<div class="g-full"><button class="g-btn">Enregistrer</button></div></form></section>';
  if ($t) echo '<section class="g-card g-danger"><h2 class="g-h2">Supprimer</h2><form method="post" onsubmit="return confirm(\'Supprimer définitivement ce témoignage ?\')">' . a_field() . '<input type="hidden" name="a" value="supprimer"><p class="g-small">À faire si l’auteur retire son accord. Pour une simple pause, passez plutôt l’état à « Hors ligne ».</p><button class="g-btn g-red">Supprimer définitivement</button></form></section>';
  a_layout_end();
}

function refs_vue_logo(int $id): void {
  $l = $id ? db_one('SELECT * FROM ref_logos WHERE id=?', [$id]) : null;
  if ($id && !$l) a_go('v=references');
  $lv = $l ?? ['nom' => '', 'categorie' => 'entreprise', 'pole' => 'tous', 'fichier' => '', 'fond' => 'clair', 'lien' => '', 'accord' => 0, 'ordre' => 0, 'publie' => 1];
  a_layout_start($l ? 'Logo' : 'Nouveau logo', 'references');
  echo '<div class="g-head"><div><p class="g-small"><a href="?v=references">‹ Références et témoignages</a></p><h1 class="g-h1">' . h($l ? $l['nom'] : 'Nouveau logo') . '</h1></div>'
    . ($l ? '<div>' . ((int)$l['publie'] && (int)$l['accord'] && $l['fichier'] ? '<span class="g-b g-b-ok">En ligne</span>' : '<span class="g-b">Hors ligne</span>') . '</div>' : '') . '</div>';
  if ($l && $l['fichier']) {
    $src = h(refs_logo_src($l));
    echo '<section class="g-card"><h2 class="g-h2">Aperçu</h2><div class="g-cols">'
      . '<div><p class="g-small">Sur le site (gris, couleur au survol)</p><div style="display:flex;align-items:center;justify-content:center;height:120px;background:' . ($l['fond'] === 'sombre' ? '#0B1A33' : '#FBF9F4') . ';border:1px solid #E6E1D6;border-radius:8px"><img src="' . $src . '" alt="" style="max-height:56px;max-width:170px;filter:grayscale(1);opacity:.75"></div></div>'
      . '<div><p class="g-small">Fichier en couleur</p><div style="display:flex;align-items:center;justify-content:center;height:120px;background:' . ($l['fond'] === 'sombre' ? '#0B1A33' : '#FFFFFF') . ';border:1px solid #E6E1D6;border-radius:8px"><img src="' . $src . '" alt="" style="max-height:56px;max-width:170px"></div></div>'
      . '</div></section>';
  }
  echo '<section class="g-card"><form method="post" class="g-grid" enctype="multipart/form-data">' . a_field() . '<input type="hidden" name="a" value="save">'
    . a_input('nom', 'Nom de l’organisation', $lv['nom'], 'text', 'required maxlength="200"')
    . a_select('categorie', 'Catégorie', REFS_CATEGORIES, $lv['categorie'])
    . '<div class="g-full"><label class="g-f">' . ($l && $l['fichier'] ? 'Remplacer le logo' : 'Fichier du logo') . ' (PNG à fond transparent de préférence, JPEG ou WebP ; 6 Mo au plus)<input type="file" name="fichier" accept="image/png,image/jpeg,image/webp"' . ($l && $l['fichier'] ? '' : ' required') . '></label>'
    . '<p class="g-small" style="margin:0">Les marges inutiles sont retirées et le logo est réduit automatiquement. Les fichiers SVG ne sont pas acceptés : exportez-les en PNG.</p></div>'
    . a_select('fond', 'Couleur du logo', ['clair' => 'Logo foncé ou en couleur (affiché sur fond clair)', 'sombre' => 'Logo blanc ou très clair (affiché sur fond bleu nuit)'], $lv['fond'])
    . a_select('pole', 'Pôle', REFS_POLES, $lv['pole'])
    . a_input('lien', 'Site de l’organisation (facultatif)', $lv['lien'], 'url', 'maxlength="300" placeholder="https://"')
    . a_input('ordre', 'Ordre d’affichage (le plus petit en premier)', (string)(int)$lv['ordre'], 'number', 'min="-999" max="999"')
    . a_select('accord', 'Accord de l’organisation pour afficher son logo', ['0' => 'Pas encore confirmé', '1' => 'Oui, accord obtenu'], (string)(int)$lv['accord'])
    . a_select('publie', 'Afficher sur le site', ['1' => 'Oui', '0' => 'Non'], (string)(int)$lv['publie'])
    . '<p class="g-small g-full">Un logo est une marque : ne l’affichez qu’avec l’accord de l’organisation (un courriel de son service communication suffit). Sans cet accord confirmé, le logo reste hors ligne.</p>'
    . '<div class="g-full"><button class="g-btn">Enregistrer</button></div></form></section>';
  if ($l) echo '<section class="g-card g-danger"><h2 class="g-h2">Supprimer</h2><form method="post" onsubmit="return confirm(\'Supprimer ce logo ?\')">' . a_field() . '<input type="hidden" name="a" value="supprimer"><button class="g-btn g-red">Supprimer le logo</button></form></section>';
  a_layout_end();
}
