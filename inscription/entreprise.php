<?php
/* Demande d'inscription financée par l'employeur : bloque les places sept jours, devis et convention établis ensuite. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$s = session_by_slug(get('s', 120));
if (!$s) redirect('/calendrier/');
$p = programme((int)$s['programme_id']); $v = session_view($s);
if (!$v['ouvert_entreprise']) page_message('Inscriptions fermées', '<p>Les inscriptions sont fermées pour cette session. Pour une session dédiée à votre organisation, <a class="pf-link" href="/contact/?besoin=devis&profil=entreprise">demandez une proposition</a>.</p>');
$err = ''; $d = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $places = max(1, min(15, (int)post('places')));
  $parts = [];
  for ($k = 0; $k < $places; $k++) $parts[] = ['nom' => line(post("p_nom_$k", 160)), 'fonction' => line(post("p_fonction_$k", 160)), 'email' => mb_strtolower(line(post("p_email_$k", 190)))];
  $org = ['raison_sociale' => line(post('raison_sociale', 200)), 'siret' => preg_replace('/\D/', '', post('siret', 20)), 'adresse' => post('adresse', 400),
    'contact_nom' => line(post('contact_nom', 160)), 'contact_email' => mb_strtolower(line(post('contact_email', 190))), 'contact_tel' => line(post('contact_tel', 40))];
  if (!csrf_check() || post('site_web') !== '') $err = 'La page a expiré. Merci de recommencer.';
  elseif ($org['raison_sociale'] === '' || trim($org['adresse']) === '' || $org['contact_nom'] === '' || !is_email($org['contact_email'])) $err = 'Merci de compléter les informations de l’organisation et du contact.';
  elseif (post('accepte') !== 'oui') $err = 'Merci de confirmer avoir pris connaissance des conditions générales de vente.';
  else {
    foreach ($parts as $pt) if ($pt['email'] !== '' && !is_email($pt['email'])) { $err = 'Une adresse de participant n’est pas valide.'; break; }
    if (!$err) {
      try { demande_entreprise((int)$s['id'], $org, $places, $parts, line(post('financement', 120)), post('commentaire', 2000)); mail_flush(); track('inscription:entreprise');
        page_message('Demande enregistrée', '<p>Merci. ' . $places . ' place(s) sont bloquées pour votre organisation pendant sept jours.</p><p>Vous recevrez sous deux jours ouvrés, à ' . h($org['contact_email']) . ', le devis et la convention de formation à signer en ligne.</p><p><a class="pf-link" href="/calendrier/">Retour au calendrier</a></p>', 'vert'); }
      catch (DomainException $e) { $err = $e->getMessage(); }
    }
  }
}
$n = max(1, min(15, (int)($d['places'] ?? 1)));
page_start('Inscription entreprise · ' . $p['titre'], 'Demande d’inscription financée par l’employeur', 'calendrier', false);
?>
<section class="section paper"><div class="wrap pf-narrow">
  <p class="pf-note" style="margin:0"><?= h($p['titre']) ?> · <?= h(periode_fr($s['date_debut'], $s['date_fin'])) ?> · <?= (int)$v['restantes'] ?> place(s) disponible(s)</p>
  <h1 class="pf-h1">Inscription financée par l’employeur</h1>
  <p class="pf-lede">Les places sont bloquées sept jours. Nous vous adressons le devis et la convention de formation, à signer en ligne ; le règlement se fait ensuite par carte ou par virement.</p>
  <?= $err ? alert_box(h($err)) : '' ?>
  <form method="post" class="pf-card"><?= csrf_field() ?>
    <div class="pf-form">
      <label class="pf-field">Raison sociale<input name="raison_sociale" required value="<?= h($d['raison_sociale'] ?? '') ?>" autocomplete="organization"></label>
      <label class="pf-field">SIRET (facultatif)<input name="siret" inputmode="numeric" value="<?= h($d['siret'] ?? '') ?>"></label>
      <label class="pf-field full">Adresse de facturation<textarea name="adresse" required style="min-height:70px"><?= h($d['adresse'] ?? '') ?></textarea></label>
      <label class="pf-field">Nom du contact<input name="contact_nom" required value="<?= h($d['contact_nom'] ?? '') ?>" autocomplete="name"></label>
      <label class="pf-field">Courriel du contact<input type="email" name="contact_email" required value="<?= h($d['contact_email'] ?? '') ?>" autocomplete="email"></label>
      <label class="pf-field">Téléphone<input type="tel" name="contact_tel" value="<?= h($d['contact_tel'] ?? '') ?>" autocomplete="tel"></label>
      <label class="pf-field">Nombre de participants<select name="places" onchange="this.form.querySelectorAll('[data-p]').forEach(function(e){e.hidden=+e.dataset.p>=+this.value}.bind(this))"><?php for ($k = 1; $k <= max(1, $v['restantes']); $k++): ?><option<?= $k === $n ? ' selected' : '' ?>><?= $k ?></option><?php endfor; ?></select></label>
      <label class="pf-field full">Financement envisagé<select name="financement"><option>Budget formation de l’entreprise</option><option>Autre (préciser en commentaire)</option></select></label>
    </div>
    <h2 class="pf-h2" style="margin-top:26px;font-size:1.4rem">Participants</h2>
    <p class="pf-note" style="margin-top:-6px">À compléter maintenant ou au plus tard sept jours avant la formation.</p>
    <?php for ($k = 0; $k < max(1, $v['restantes']); $k++): ?>
      <fieldset data-p="<?= $k ?>" <?= $k >= $n ? 'hidden' : '' ?> style="border:0;padding:0;margin:0 0 12px"><legend class="pf-note" style="margin-bottom:6px">Participant <?= $k + 1 ?></legend>
        <div class="pf-form"><label class="pf-field">Prénom et nom<input name="p_nom_<?= $k ?>" value="<?= h($d["p_nom_$k"] ?? '') ?>"></label><label class="pf-field">Fonction<input name="p_fonction_<?= $k ?>" value="<?= h($d["p_fonction_$k"] ?? '') ?>"></label><label class="pf-field">Courriel<input type="email" name="p_email_<?= $k ?>" value="<?= h($d["p_email_$k"] ?? '') ?>"></label></div>
      </fieldset>
    <?php endfor; ?>
    <label class="pf-field" style="margin-top:8px">Commentaire (facultatif)<textarea name="commentaire"><?= h($d['commentaire'] ?? '') ?></textarea></label>
    <span class="pf-hp" aria-hidden="true"><label>Ne pas remplir<input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></span>
    <label class="pf-check" style="margin-top:16px"><input type="checkbox" name="accepte" value="oui" required> J’ai pris connaissance du <a href="<?= h($p['fiche_url']) ?>" target="_blank" rel="noopener">programme</a> et des <a href="/cgv/" target="_blank" rel="noopener">conditions générales de vente</a>.</label>
    <div class="pf-actions"><a class="pf-btn pf-btn-ghost" href="/sessions/<?= h($s['slug']) ?>/">Retour</a><button class="pf-btn" type="submit">Envoyer la demande</button></div>
  </form>
</div></section>
<?php page_end();
