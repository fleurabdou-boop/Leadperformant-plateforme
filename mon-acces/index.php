<?php
/* Espace client sans mot de passe : ouvert par le lien personnel reçu par courriel. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$t = get('t', 64);
$c = $t !== '' ? db_one('SELECT * FROM clients WHERE acces_token=?', [$t]) : null;
if (!$c) {
  $sent = false;
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check() && post('site_web') === '') {
    $cl = db_one('SELECT * FROM clients WHERE email=?', [mb_strtolower(line(post('email', 190)))]);
    if ($cl) { mail_queue($cl['email'], 'Votre lien d’accès', email_wrap('Votre espace Lead Performant', p_('Bonjour ' . h($cl['prenom']) . ',') . p_('Voici votre lien personnel pour retrouver vos inscriptions, documents, paiements et achats :') . btn(acces_url((int)$cl['id']), 'Ouvrir mon espace'))); mail_flush(); }
    $sent = true;
  }
  page_message('Mon espace', $sent ? '<p>Si cette adresse est connue, un lien d’accès vient de lui être envoyé.</p>' : '<p>Saisissez l’adresse utilisée lors de votre inscription ou de votre achat : nous vous envoyons votre lien d’accès personnel.</p><form method="post" style="display:flex;flex-direction:column;gap:10px;max-width:420px">' . csrf_field() . '<label class="pf-field">Courriel<input type="email" name="email" required autocomplete="email"></label><span class="pf-hp" aria-hidden="true"><input type="text" name="site_web" tabindex="-1" autocomplete="off"></span><button class="pf-btn" type="submit">Recevoir mon lien</button></form>');
}
$msg = '';
/* Dépôt d'une question pour un conseil écrit */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'question' && csrf_check()) {
  $cmd = db_one("SELECT * FROM commandes WHERE id=? AND client_id=? AND statut='payee'", [(int)post('commande'), $c['id']]);
  if ($cmd && !$cmd['question_at'] && post('question', 8000) !== '') {
    $file = null;
    if (!empty($_FILES['fichier']['tmp_name']) && is_uploaded_file($_FILES['fichier']['tmp_name'])) {
      $ext = strtolower(pathinfo((string)$_FILES['fichier']['name'], PATHINFO_EXTENSION));
      if (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'odt', 'pptx', 'jpg', 'png'], true) && $_FILES['fichier']['size'] <= 15 * 1024 * 1024) {
        $file = 'q-' . $cmd['id'] . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        move_uploaded_file($_FILES['fichier']['tmp_name'], LP_STORAGE . '/uploads/' . $file);
      } else $msg = alert_box('Fichier refusé : formats PDF, Word, texte, PowerPoint ou image, 15 Mo au plus.');
    }
    if (!$msg) {
      db_update('commandes', (int)$cmd['id'], ['question' => post('question', 8000), 'question_fichier' => $file, 'question_at' => now()]);
      admin_notifier('Conseil écrit à traiter', p_(h(client_nom($c)) . ' a déposé sa question.') . btn(url('/gestion/?v=commande&id=' . $cmd['id']), 'Répondre'));
      mail_flush(); $msg = alert_box('Votre question est enregistrée. Vous serez averti(e) par courriel dès que la réponse sera disponible.', 'vert');
    }
  }
}
$insc = db_all("SELECT i.*, s.date_debut, s.date_fin, s.slug, p.titre FROM inscriptions i JOIN sessions s ON s.id=i.session_id JOIN programmes p ON p.id=s.programme_id WHERE i.client_id=? AND i.statut <> 'reservation' ORDER BY s.date_debut DESC", [$c['id']]);
$cmds = db_all("SELECT c.*, p.titre, p.type, p.delai_jours FROM commandes c JOIN produits p ON p.id=c.produit_id WHERE c.client_id=? AND c.statut='payee' ORDER BY c.id DESC", [$c['id']]);
$facts = db_all('SELECT * FROM factures WHERE client_id=? ORDER BY id DESC', [$c['id']]);
$doc = fn($id, $label) => $id ? '<a class="pf-link" href="/mon-acces/document.php?t=' . h($t) . '&d=' . (int)$id . '">' . h($label) . '</a>' : '';
page_start('Mon espace', 'Espace personnel', '', false);
?>
<section class="section paper"><div class="wrap">
  <span class="eyebrow">Espace personnel</span>
  <h1 class="pf-h1">Bonjour <?= h($c['prenom']) ?></h1>
  <?= $msg ?>
  <?php if ($insc): ?><h2 class="pf-h2">Mes formations</h2>
  <div class="pf-list" style="margin-bottom:36px"><?php foreach ($insc as $i): $ech = db_all('SELECT * FROM echeances WHERE inscription_id=? ORDER BY id', [$i['id']]); ?>
    <div class="pf-card">
      <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px"><div><strong style="font-size:1.1rem;color:var(--pf-navy)"><?= h($i['titre']) ?></strong><br><span class="pf-note" style="margin:0"><?= h(ucfirst(periode_fr($i['date_debut'], $i['date_fin']))) ?></span></div><div><?= chip(STATUTS_LIB[$i['statut']] ?? $i['statut'], in_array($i['statut'], ['confirmee', 'soldee'], true) ? 'vert' : (in_array($i['statut'], ['retractee', 'annulee', 'expiree', 'remboursee'], true) ? 'rouge' : 'ambre')) ?></div></div>
      <div class="pf-scroll"><table class="pf-table" style="margin-top:12px"><tr><th>Échéance</th><th>Date</th><th>Montant</th><th>État</th></tr>
      <?php foreach ($ech as $e): ?><tr><td><?= h($e['libelle']) ?></td><td><?= h(date('d/m/Y', ts((string)$e['due_at']))) ?></td><td><?= h(eur((int)$e['montant'])) ?></td><td><?= $e['statut'] === 'attendue' ? '<a class="pf-btn" style="min-height:38px;padding:0 14px" href="' . h(payer_echeance_url($e)) . '">Payer</a>' : h(['prevue' => 'À venir', 'payee' => 'Payée', 'annulee' => 'Annulée', 'remboursee' => 'Remboursée', 'expiree' => 'Expirée'][$e['statut']] ?? $e['statut']) ?></td></tr><?php endforeach; ?>
      </table></div>
      <p style="display:flex;flex-wrap:wrap;gap:14px;margin:12px 0 0"><?= $doc($i['contrat_doc'], 'Contrat signé') ?><?= $doc($i['attestation_doc'], 'Attestation de fin de formation') ?>
      <?php if ($i['statut'] === 'retractation' && ts((string)$i['retractation_fin']) > time()): ?><a class="pf-link" href="/retractation/?t=<?= h($i['token']) ?>">Me rétracter (jusqu’au <?= h(date_fr((string)$i['retractation_fin'])) ?>)</a><?php endif; ?></p>
    </div>
  <?php endforeach; ?></div><?php endif; ?>

  <?php if ($cmds): ?><h2 class="pf-h2">Mes achats</h2>
  <div class="pf-list" style="margin-bottom:36px"><?php foreach ($cmds as $m): ?>
    <div class="pf-card"><strong style="color:var(--pf-navy)"><?= h($m['titre']) ?></strong> <span class="pf-note">· <?= h(TYPES_PRODUIT[$m['type']]) ?> · <?= h(date_fr((string)$m['paye_at'])) ?></span>
    <?php if (in_array($m['type'], ['ebook', 'guide'], true)): ?><p style="margin:10px 0 0"><a class="pf-btn" style="min-height:40px" href="/telecharger/?t=<?= h($m['token']) ?>">Télécharger</a> <span class="pf-note">jusqu’au <?= h(date_fr((string)$m['acces_fin'])) ?></span></p>
    <?php elseif ($m['type'] === 'visio'): $cr = db_one('SELECT * FROM creneaux WHERE id=?', [$m['creneau_id']]); ?><p style="margin:10px 0 0">Rendez-vous le <?= h(date_fr((string)$cr['debut'], true)) ?> à <?= h(heure_fr(date('H:i', ts((string)$cr['debut'])))) ?>.</p>
    <?php else: ?>
      <?php if (!$m['question_at']): ?>
        <form method="post" enctype="multipart/form-data" style="margin-top:12px;display:flex;flex-direction:column;gap:10px"><?= csrf_field() ?><input type="hidden" name="action" value="question"><input type="hidden" name="commande" value="<?= (int)$m['id'] ?>">
          <label class="pf-field">Votre question et votre contexte<textarea name="question" required style="min-height:160px"></textarea></label>
          <label class="pf-field">Document joint (facultatif)<input type="file" name="fichier" accept=".pdf,.doc,.docx,.odt,.txt,.pptx,.jpg,.png"></label>
          <button class="pf-btn" type="submit">Envoyer ma question</button><p class="pf-note" style="margin:0">Réponse écrite sous <?= (int)$m['delai_jours'] ?> jours ouvrés.</p></form>
      <?php elseif (!$m['reponse_at']): ?><p style="margin:10px 0 0">Question reçue le <?= h(date_fr((string)$m['question_at'])) ?>. Réponse en préparation.</p>
      <?php else: ?><div class="pf-contract" style="margin-top:12px;max-height:none;color:var(--pf-ink)"><?= nl2br(h($m['reponse'])) ?></div><?php if ($m['reponse_fichier']): ?><p><a class="pf-link" href="/mon-acces/document.php?t=<?= h($t) ?>&r=<?= (int)$m['id'] ?>">Document de réponse</a></p><?php endif; ?><?php endif; ?>
    <?php endif; ?></div>
  <?php endforeach; ?></div><?php endif; ?>

  <?php if ($facts): ?><h2 class="pf-h2">Mes factures</h2><div class="pf-card pf-scroll"><table class="pf-table"><tr><th>Numéro</th><th>Date</th><th>Objet</th><th>Montant</th><th></th></tr>
  <?php foreach ($facts as $f): ?><tr><td><?= h($f['numero']) ?></td><td><?= h(date('d/m/Y', ts((string)$f['date_emission']))) ?></td><td><?= h($f['libelle']) ?></td><td><?= h(eur((int)$f['montant'])) ?></td><td><?= $doc($f['doc_id'], 'PDF') ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
  <?php if (!$insc && !$cmds): ?><div class="pf-empty">Aucune inscription ni aucun achat pour le moment.</div><?php endif; ?>
</div></section>
<?php page_end();
