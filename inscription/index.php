<?php
/* Inscription d'un particulier finançant lui-même sa formation : quatre étapes. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
csrf_start();
$s = session_by_slug(get('s', 120));
if (!$s) redirect('/calendrier/');
$p = programme((int)$s['programme_id']);
$inv = get('inv', 64);
$invRow = $inv ? db_one("SELECT * FROM attente WHERE token=? AND session_id=? AND statut='invitee' AND expire_at > ?", [$inv, $s['id'], now()]) : null;
$v = session_view($s);
if (!$invRow && !$v['ouvert_particulier']) page_message('Inscriptions fermées', '<p>Les inscriptions individuelles ne sont pas ouvertes pour cette session.</p><p><a class="pf-link" href="/sessions/' . h($s['slug']) . '/">Retour à la session</a></p>');

$key = 'insc_' . $s['id'];
$st = $_SESSION[$key] ?? ['step' => 1, 'data' => [], 'token' => null];
if ($invRow && empty($st['data'])) $st['data'] = ['prenom' => $invRow['prenom'], 'nom' => $invRow['nom'], 'email' => $invRow['email'], 'telephone' => $invRow['telephone']];
$step = max(1, min(4, (int)(get('etape') ?: $st['step'])));
if ($step > 2 && empty($st['token'])) $step = 2;
$err = '';
$base = '/inscription/?s=' . urlencode($s['slug']) . ($inv ? '&inv=' . urlencode($inv) : '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check()) { $err = 'La page a expiré. Merci de recommencer.'; }
  elseif (post('etape') === '2') {
    $d = ['prenom' => line(post('prenom', 100)), 'nom' => line(post('nom', 100)), 'email' => mb_strtolower(line(post('email', 190))), 'telephone' => line(post('telephone', 40)),
      'fonction' => line(post('fonction', 160)), 'organisation' => line(post('organisation', 200)), 'adresse' => post('adresse', 400), 'attentes' => post('attentes', 2000), 'handicap' => post('handicap', 1000)];
    $st['data'] = $d;
    if ($d['prenom'] === '' || $d['nom'] === '' || !is_email($d['email']) || $d['telephone'] === '' || $d['fonction'] === '' || trim($d['adresse']) === '') $err = 'Merci de compléter les champs obligatoires et de vérifier votre adresse électronique.';
    else {
      try {
        $cid = client_upsert($d);
        $i = reserver_particulier((int)$s['id'], $cid, $d, $invRow ? $inv : null);
        $st['token'] = $i['token']; $st['step'] = 3; $_SESSION[$key] = $st;
        track('inscription:recap'); redirect($base . '&etape=3');
      } catch (DomainException $e) { $err = $e->getMessage(); }
    }
  } elseif (post('etape') === '3') {
    if (post('lu') !== 'oui') $err = 'Merci de confirmer avoir pris connaissance du programme, des conditions générales de vente et du règlement intérieur.';
    else { $st['step'] = 4; $_SESSION[$key] = $st; redirect($base . '&etape=4'); }
  } elseif (post('etape') === '4') {
    $i = inscription_by_token((string)$st['token']);
    $nomSigne = line(post('signature', 200));
    $attendu = mb_strtolower(trim(($st['data']['prenom'] ?? '') . ' ' . ($st['data']['nom'] ?? '')));
    if (!$i) $err = 'Votre réservation a expiré. Merci de recommencer.';
    elseif (post('accepte') !== 'oui') $err = 'Merci de cocher la case d’acceptation pour signer.';
    elseif (mb_strtolower($nomSigne) !== $attendu) $err = 'Pour signer, saisissez exactement vos prénom et nom : ' . h(trim(($st['data']['prenom'] ?? '') . ' ' . ($st['data']['nom'] ?? ''))) . '.';
    else {
      try { $i = signer_contrat($i, $nomSigne); mail_flush(); unset($_SESSION[$key]); track('inscription:signee'); redirect('/inscription/merci.php?t=' . urlencode($i['token'])); }
      catch (DomainException $e) { $err = $e->getMessage(); $st['step'] = 2; $st['token'] = null; $_SESSION[$key] = $st; }
    }
  }
  $_SESSION[$key] = $st;
}
if (get('etape') === '2' || $step === 2) track('inscription:coordonnees');
$i = !empty($st['token']) ? inscription_by_token((string)$st['token']) : null;
if ($step >= 3 && (!$i || $i['statut'] !== 'reservation' || ts((string)$i['expire_at']) < time())) { $step = 2; $err = $err ?: 'Votre réservation de 30 minutes a expiré : la place a été remise en jeu. Merci de valider à nouveau vos coordonnées.'; }
$d = $st['data'];

page_start('Inscription · ' . $p['titre'], 'Inscription en ligne', 'calendrier', false);
?>
<section class="section paper"><div class="wrap pf-narrow">
  <p class="pf-note" style="margin:0"><?= h($p['titre']) ?> · <?= h(periode_fr($s['date_debut'], $s['date_fin'])) ?> · <?= $s['modalite'] === 'distance' ? 'À distance' : h(explode("\n", (string)($s['lieu'] ?: 'Présentiel'))[0]) ?></p>
  <h1 class="pf-h1">Votre inscription</h1>
  <ol class="pf-progress" aria-label="Étapes"><?php foreach (['Financement', 'Coordonnées', 'Récapitulatif', 'Signature'] as $k => $l): ?><li class="<?= $k + 1 <= $step ? 'on' : '' ?>"<?= $k + 1 === $step ? ' aria-current="step"' : '' ?>><?= ($k + 1) . '. ' . $l ?></li><?php endforeach; ?></ol>
  <?= $err ? alert_box($err) : '' ?>
  <?php if ($invRow): ?><?= alert_box('Une place vous est réservée grâce à la liste d’attente jusqu’au ' . h(date_fr((string)$invRow['expire_at'])) . ' à ' . date('H:i', ts((string)$invRow['expire_at'])) . '.', 'vert') ?><?php endif; ?>
  <div class="pf-card">

<?php if ($step === 1): ?>
    <h2 class="pf-h2">Qui finance la formation ?</h2>
    <p class="pf-lede" style="margin-top:-6px">Le parcours et les documents dépendent de votre réponse.</p>
    <div class="pf-choice">
      <a href="<?= h($base . '&etape=2') ?>"><strong>Je finance moi-même</strong><span>Contrat de formation signé en ligne, dix jours de rétractation, acompte de 30 %, solde pendant la formation.</span></a>
      <a href="/inscription/entreprise.php?s=<?= h($s['slug']) ?>"><strong>Mon employeur finance</strong><span>Demande de convention, devis sous deux jours ouvrés, paiement par carte ou par virement.</span></a>
    </div>

<?php elseif ($step === 2): ?>
    <h2 class="pf-h2">Vos coordonnées</h2>
    <form method="post" action="<?= h($base) ?>" class="pf-form"><?= csrf_field() ?><input type="hidden" name="etape" value="2">
      <label class="pf-field">Prénom<input name="prenom" required autocomplete="given-name" value="<?= h($d['prenom'] ?? '') ?>"></label>
      <label class="pf-field">Nom<input name="nom" required autocomplete="family-name" value="<?= h($d['nom'] ?? '') ?>"></label>
      <label class="pf-field">Courriel<input type="email" name="email" required autocomplete="email" value="<?= h($d['email'] ?? '') ?>"></label>
      <label class="pf-field">Téléphone<input type="tel" name="telephone" required autocomplete="tel" value="<?= h($d['telephone'] ?? '') ?>"></label>
      <label class="pf-field">Fonction ou situation professionnelle<input name="fonction" required value="<?= h($d['fonction'] ?? '') ?>"></label>
      <label class="pf-field">Organisation (facultatif)<input name="organisation" autocomplete="organization" value="<?= h($d['organisation'] ?? '') ?>"></label>
      <label class="pf-field full">Adresse postale (pour le contrat et les factures)<textarea name="adresse" required autocomplete="street-address" style="min-height:70px"><?= h($d['adresse'] ?? '') ?></textarea></label>
      <label class="pf-field full">Vos attentes pour cette formation (facultatif)<textarea name="attentes" placeholder="Situations que vous souhaitez travailler, échéance importante…"><?= h($d['attentes'] ?? '') ?></textarea></label>
      <label class="pf-field full">Besoin d’adaptation lié à une situation de handicap (facultatif)<input name="handicap" value="<?= h($d['handicap'] ?? '') ?>"></label>
      <div class="full pf-actions"><a class="pf-btn pf-btn-ghost" href="<?= h($base . '&etape=1') ?>">Retour</a><button class="pf-btn" type="submit">Voir le récapitulatif</button></div>
    </form>
    <p class="pf-note">En continuant, une place vous est réservée pendant 30 minutes. Vos données servent uniquement à votre inscription : <a href="/confidentialite/">politique de confidentialité</a>.</p>

<?php elseif ($step === 3): $ech = echeancier((int)$i['prix_total']); ?>
    <h2 class="pf-h2">Récapitulatif et tarif</h2>
    <p class="pf-note" style="margin:-4px 0 16px">Place réservée jusqu’à <?= date('H:i', ts((string)$i['expire_at'])) ?>.</p>
    <table class="pf-rows">
      <tr><td><?= h($p['titre']) ?>, <?= (int)$p['duree_jours'] ?> jours, <?= (int)$p['duree_heures'] ?> heures<br><span class="pf-note"><?= h(ucfirst(periode_fr($s['date_debut'], $s['date_fin']))) ?></span></td><td><?= h(eur((int)$i['prix_total'])) ?></td></tr>
      <tr><td colspan="2" class="pf-note" style="text-align:left;font-weight:400"><?= h(ORG['tva']) ?></td></tr>
    </table>
    <h3 style="margin:24px 0 6px;font-size:1rem;color:var(--pf-navy)">Échéancier</h3>
    <table class="pf-rows">
      <tr><td>Pendant les dix jours de rétractation</td><td style="color:var(--pf-green)"><?= h(eur(0)) ?></td></tr>
      <tr><td><?= h($ech[0][0]) ?>, à l’issue du délai</td><td><?= h(eur($ech[0][1])) ?></td></tr>
      <tr><td><?= h($ech[1][0]) ?> (<?= h(date_fr($s['date_debut'])) ?>)</td><td><?= h(eur($ech[1][1])) ?></td></tr>
      <tr><td><?= h($ech[2][0]) ?> (<?= h(date_fr($s['date_fin'])) ?>)</td><td><?= h(eur($ech[2][1])) ?></td></tr>
    </table>
    <form method="post" action="<?= h($base) ?>"><?= csrf_field() ?><input type="hidden" name="etape" value="3">
      <p style="display:flex;flex-wrap:wrap;gap:14px;margin:22px 0 14px;font-size:.95rem"><a class="pf-link" href="<?= h($p['fiche_url']) ?>" target="_blank" rel="noopener">Programme détaillé</a><a class="pf-link" href="/cgv/" target="_blank" rel="noopener">Conditions générales de vente</a><a class="pf-link" href="/reglement-interieur/" target="_blank" rel="noopener">Règlement intérieur</a></p>
      <label class="pf-check"><input type="checkbox" name="lu" value="oui" required> J’ai pris connaissance du programme, des conditions générales de vente et du règlement intérieur.</label>
      <div class="pf-actions"><a class="pf-btn pf-btn-ghost" href="<?= h($base . '&etape=2') ?>">Modifier mes coordonnées</a><button class="pf-btn" type="submit">Passer à la signature</button></div>
    </form>

<?php else: $ech = echeancier((int)$i['prix_total']); $fin = date_fr(date('Y-m-d', strtotime('+' . (int)param('delai_retractation_jours', 10) . ' days'))); ?>
    <h2 class="pf-h2">Signature du contrat de formation</h2>
    <p style="margin-top:-6px">Relisez les clauses essentielles, puis signez. <strong>Aucun paiement n’est demandé aujourd’hui.</strong></p>
    <div class="pf-contract" tabindex="0" aria-label="Clauses essentielles du contrat">
      <p><strong>Contrat de formation professionnelle</strong> (articles L6353-3 à L6353-7 du Code du travail), entre <?= h(ORG['entite']) ?>, SIRET <?= h(ORG['siret']) ?>, et <?= h(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? '')) ?>.</p>
      <p><strong>Objet.</strong> <?= h($p['titre']) ?>, <?= h(periode_fr($s['date_debut'], $s['date_fin'])) ?>, <?= h(heure_fr($s['heure_debut']) . ' à ' . heure_fr($s['heure_fin'])) ?>, <?= (int)$p['duree_heures'] ?> heures, <?= $s['modalite'] === 'distance' ? 'à distance' : h($s['lieu'] ?: param('parole_lieu_defaut')) ?>. Groupe de <?= (int)$s['seuil'] ?> à <?= (int)$s['capacite'] ?> participants.</p>
      <p><strong>Prix.</strong> <?= h(eur((int)$i['prix_total'])) ?>, <?= h(ORG['tva']) ?>. Acompte de <?= h(eur($ech[0][1])) ?> exigible à l’issue du délai de rétractation, puis <?= h(eur($ech[1][1])) ?> le premier jour et <?= h(eur($ech[2][1])) ?> le dernier jour de la formation, par carte bancaire.</p>
      <p><strong>Rétractation.</strong> Vous disposez de dix jours à compter de la signature, soit jusqu’au <?= h($fin) ?> inclus, pour vous rétracter sans frais ni motif, en un clic depuis le courriel de confirmation.</p>
      <p><strong>Interruption.</strong> En cas de force majeure, seules les prestations effectivement dispensées sont dues, au prorata temporis.</p>
      <p><strong>Maintien.</strong> Si la session n’atteint pas <?= (int)$s['seuil'] ?> participants dix jours avant, vous choisissez entre la nouvelle date et le remboursement intégral.</p>
      <p>Le contrat complet en PDF vous est adressé par courriel dès la signature.</p>
    </div>
    <form method="post" action="<?= h($base) ?>" style="margin-top:18px"><?= csrf_field() ?><input type="hidden" name="etape" value="4">
      <label class="pf-field">Pour signer, saisissez vos prénom et nom : <?= h(($d['prenom'] ?? '') . ' ' . ($d['nom'] ?? '')) ?><input name="signature" required autocomplete="off"></label>
      <label class="pf-check" style="margin-top:14px"><input type="checkbox" name="accepte" value="oui" required> J’ai lu et j’accepte le contrat de formation, les conditions générales de vente et le règlement intérieur. Je signe électroniquement ce contrat.</label>
      <div class="pf-actions"><a class="pf-btn pf-btn-ghost" href="<?= h($base . '&etape=3') ?>">Retour</a><button class="pf-btn" type="submit">Signer et réserver ma place</button></div>
    </form>
<?php endif; ?>
  </div>
</div></section>
<?php page_end();
