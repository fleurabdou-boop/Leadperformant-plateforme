<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
$s = session_by_slug(get('s', 120));
if (!$s || $s['etat'] === 'brouillon') { http_response_code(404); page_message('Session introuvable', '<p>Cette session n’existe pas ou n’est plus proposée.</p><p><a class="pf-link" href="/calendrier/">Voir le calendrier</a></p>'); }
track('session:' . $s['slug']);
$p = programme((int)$s['programme_id']); $v = session_view($s);
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'attente') {
  if (!csrf_check() || post('site_web') !== '') redirect('/sessions/' . $s['slug'] . '/');
  $d = ['email' => mb_strtolower(line(post('email', 190))), 'prenom' => line(post('prenom', 100)), 'nom' => line(post('nom', 100)), 'telephone' => line(post('telephone', 40))];
  if (!is_email($d['email']) || $d['prenom'] === '' || $d['nom'] === '') $msg = alert_box('Merci de renseigner votre prénom, votre nom et une adresse électronique valide.');
  else { attente_ajouter((int)$s['id'], $d); mail_flush(); $msg = alert_box('Vous êtes inscrit(e) sur la liste d’attente. Une confirmation vient de vous être envoyée.', 'vert'); }
}
$lieu = $s['modalite'] === 'distance' ? 'À distance, en visioconférence' : ($s['lieu'] ?: param('parole_lieu_defaut', 'Île-de-France'));
$fermP = date('Y-m-d', strtotime($s['date_debut'] . ' -' . (int)param('fermeture_particuliers_jours', 14) . ' days'));
$ld = json_encode(['@context' => 'https://schema.org', '@type' => 'Course', 'name' => $p['titre'], 'description' => $p['sous_titre'],
  'provider' => ['@type' => 'Organization', 'name' => 'Lead Performant', 'url' => url('/')],
  'hasCourseInstance' => ['@type' => 'CourseInstance', 'courseMode' => $s['modalite'] === 'distance' ? 'online' : 'onsite', 'startDate' => $s['date_debut'], 'endDate' => $s['date_fin'], 'location' => $lieu,
    'courseWorkload' => 'PT' . (int)$p['duree_heures'] . 'H']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
page_start($p['titre'] . ', ' . periode_fr($s['date_debut'], $s['date_fin']), $p['sous_titre'] . ' Session du ' . periode_fr($s['date_debut'], $s['date_fin']) . ', ' . $p['duree_heures'] . ' heures.', 'calendrier', true, '/sessions/' . $s['slug'] . '/', $ld);
?>
<section class="pf-hero"><div class="wrap">
  <div class="pf-hero-main">
    <?= crumbs(['Calendrier' => '/calendrier/', $p['titre'] => null]) ?>
    <span class="eyebrow">Programme <?= h($p['numero'] . ' · ' . pole($p['numero'])) ?></span>
    <h1><?= h($p['titre']) ?></h1>
    <p><?= h($p['sous_titre']) ?></p>
    <p style="margin-top:18px"><a href="<?= h($p['fiche_url']) ?>" style="color:#EBC852">Lire la fiche complète du programme →</a></p>
  </div>
  <aside class="pf-box" aria-label="Informations pratiques">
    <div class="pf-chips"><?= chip($v['statut'], $v['ton']) ?><?php if ($s['etat'] === 'ouverte' && $v['restantes'] > 0): ?><?= chip($v['restantes'] . ' place' . ($v['restantes'] > 1 ? 's' : '') . ' sur ' . $s['capacite'], 'neutre') ?><?php endif; ?><?php if (!empty($v['confirmee'])): ?><?= chip('Session confirmée', 'bleu') ?><?php endif; ?></div>
    <dl class="pf-dl">
      <dt>Dates</dt><dd><?= h(ucfirst(periode_fr($s['date_debut'], $s['date_fin']))) ?></dd>
      <dt>Horaires</dt><dd><?= h(heure_fr($s['heure_debut']) . ' - ' . heure_fr($s['heure_fin'])) ?>, soit <?= (int)$p['duree_heures'] ?> heures</dd>
      <dt>Lieu</dt><dd><?= nl2br(h($lieu)) ?></dd>
      <dt>Groupe</dt><dd><?= (int)$s['seuil'] ?> à <?= (int)$s['capacite'] ?> participants</dd>
      <dt>Tarif</dt><dd>Présenté à l’étape d’inscription</dd>
      <dt>Clôture</dt><dd><?= $v['jours'] >= 0 ? h(date_fr($fermP)) . ' (particuliers)' : '–' ?></dd>
    </dl>
    <div class="pf-stack">
      <?php if ($v['ouvert_particulier']): ?><a class="pf-btn pf-btn-block" href="/inscription/?s=<?= h($s['slug']) ?>">Je m’inscris</a><?php endif; ?>
      <?php if ($v['ouvert_entreprise']): ?><a class="pf-btn pf-btn-ghost pf-btn-block" href="/inscription/entreprise.php?s=<?= h($s['slug']) ?>">Mon employeur finance</a><?php endif; ?>
    </div>
    <?php if ($v['ouvert_particulier']): ?><p class="pf-note">Particuliers : aucun paiement pendant les dix jours de rétractation qui suivent la signature du contrat.</p><?php endif; ?>
    <?php if ($v['attente']): ?>
      <?= $msg ?>
      <form method="post" style="margin-top:16px;display:flex;flex-direction:column;gap:10px"><?= csrf_field() ?><input type="hidden" name="action" value="attente">
        <p style="margin:0;font-weight:600;color:var(--pf-navy)">Rejoindre la liste d’attente</p>
        <label class="pf-field">Prénom<input name="prenom" required autocomplete="given-name"></label>
        <label class="pf-field">Nom<input name="nom" required autocomplete="family-name"></label>
        <label class="pf-field">Courriel<input type="email" name="email" required autocomplete="email"></label>
        <label class="pf-field">Téléphone (facultatif)<input type="tel" name="telephone" autocomplete="tel"></label>
        <span class="pf-hp" aria-hidden="true"><label>Ne pas remplir<input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></span>
        <button class="pf-btn" type="submit">M’inscrire sur la liste</button>
        <p class="pf-note" style="margin:0">Si une place se libère, vous serez invité(e) par courriel, dans l’ordre d’arrivée, pendant 48 heures.</p>
      </form>
    <?php endif; ?>
  </aside>
</div></section>
<section class="section paper"><div class="wrap pf-cols">
  <div class="pf-main">
    <h2 class="pf-h2">Objectifs pédagogiques</h2>
    <p>À l’issue de la formation, vous serez capable de :</p>
    <ul><?php foreach (array_filter(explode("\n", (string)$p['objectifs'])) as $o): ?><li><?= h($o) ?></li><?php endforeach; ?></ul>
    <h2 class="pf-h2" style="margin-top:36px">Programme</h2>
    <div class="pf-steps-prog"><?php foreach (array_values(array_filter(explode("\n", (string)$p['programme']))) as $i => $j): [$t, $d] = array_pad(explode(' : ', $j, 2), 2, ''); ?>
      <div><span class="n"><?= $i + 1 ?></span><div><h3 style="margin:0 0 6px;font-size:1rem;color:var(--pf-navy)"><?= h($t) ?></h3><p><?= h($d) ?></p></div></div>
    <?php endforeach; ?></div>
    <h2 class="pf-h2" style="margin-top:36px">Évaluation et attestation</h2>
    <p><?= h($p['evaluation']) ?></p>
  </div>
  <aside class="pf-aside">
    <div><h3>Public et prérequis</h3><p><?= h($p['public_vise']) ?> <?= h($p['prerequis']) ?></p></div>
    <div><h3>Formateur</h3><p><?= h($p['formateur']) ?></p></div>
    <div><h3>Accessibilité</h3><p>Signalez un besoin d’adaptation lié à une situation de handicap lors de l’inscription : les conditions d’accueil et les supports sont ajustés.</p></div>
    <div><h3>Si la session n’atteint pas <?= (int)$s['seuil'] ?> participants</h3><p>Vous êtes prévenu(e) dix jours avant. Vous choisissez entre la nouvelle date proposée et le remboursement intégral.</p></div>
  </aside>
</div></section>
<?php page_end();
