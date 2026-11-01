<?php
/* Signature en ligne de la convention par l'entreprise. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$i = inscription_by_token(get('t', 64));
if (!$i || $i['type'] !== 'entreprise') page_message('Lien invalide', '<p>Ce lien n’est pas valide.</p>');
$s = session_get((int)$i['session_id']); $p = programme((int)$s['programme_id']); $o = organisation((int)$i['organisation_id']);
if (in_array($i['statut'], ['confirmee', 'soldee'], true)) page_message('Convention déjà signée', '<p>La convention a été signée le ' . h(date_fr((string)$i['signe_at'])) . '. Les documents ont été adressés à ' . h($o['contact_email']) . '.</p>');
if ($i['statut'] !== 'devis_envoye') page_message('Convention indisponible', '<p>Cette convention n’est pas, ou plus, ouverte à la signature. Écrivez-nous à ' . h(ORG['email']) . '.</p>');
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrf_check()) $err = 'La page a expiré.';
  elseif (post('accepte') !== 'oui' || line(post('nom', 160)) === '') $err = 'Merci d’indiquer le nom du signataire et de cocher la case d’acceptation.';
  else {
    try { signer_convention($i, line(post('nom', 160)), line(post('qualite', 120)), post('mode') === 'virement' ? 'virement' : 'carte'); mail_flush();
      $e = db_one("SELECT * FROM echeances WHERE inscription_id=? AND statut='attendue' ORDER BY id DESC LIMIT 1", [$i['id']]);
      page_message('Convention signée', '<p>Merci. Les places sont confirmées ; la convention signée et la facture ont été adressées à ' . h($o['contact_email']) . '.</p>' . ($e && $e['mode'] === 'carte' ? '<p><a class="pf-btn" href="' . h(payer_echeance_url($e)) . '">Régler ' . h(eur((int)$e['montant'])) . ' par carte</a></p>' : '<p>Règlement par virement : merci de rappeler le numéro de facture.</p>'), 'vert'); }
    catch (DomainException $e) { $err = $e->getMessage(); }
  }
}
$docs = db_all("SELECT * FROM documents WHERE inscription_id=? AND type IN ('devis','convention') ORDER BY id DESC", [$i['id']]);
$liens = '';
foreach ($docs as $doc) $liens .= '<a class="pf-link" href="/mon-acces/document.php?t=' . h($i['token']) . '&d=' . (int)$doc['id'] . '">' . h($doc['type'] === 'devis' ? 'Devis (PDF)' : 'Convention (PDF)') . '</a> ';
page_message('Convention de formation', ($err ? alert_box(h($err)) : '')
  . '<p><strong>' . h($o['raison_sociale']) . '</strong> · ' . h($p['titre']) . ', ' . h(periode_fr($s['date_debut'], $s['date_fin'])) . ' · ' . (int)$i['places'] . ' participant(s)</p>'
  . '<p>Montant : <strong>' . h(eur((int)$i['prix_total'])) . '</strong> net. ' . h(ORG['tva']) . '. Offre valable jusqu’au ' . h(date_fr((string)$i['expire_at'])) . '.</p>'
  . '<p style="display:flex;gap:16px;flex-wrap:wrap">' . $liens . '</p>'
  . '<form method="post" class="pf-form" style="margin-top:16px">' . csrf_field()
  . '<label class="pf-field">Nom du signataire<input name="nom" required value="' . h($o['contact_nom']) . '"></label>'
  . '<label class="pf-field">Qualité<input name="qualite" placeholder="Responsable formation, dirigeant…"></label>'
  . '<label class="pf-field full">Mode de règlement<select name="mode"><option value="carte">Carte bancaire (lien sécurisé)</option><option value="virement">Virement à réception de facture</option></select></label>'
  . '<label class="pf-check full"><input type="checkbox" name="accepte" value="oui" required> J’ai lu la convention et les conditions générales de vente, je suis habilité(e) à engager l’organisation et je signe électroniquement.</label>'
  . '<div class="full"><button class="pf-btn" type="submit">Signer la convention</button></div></form>');
