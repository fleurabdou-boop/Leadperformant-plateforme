<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$i = inscription_by_token(get('t', 64));
if (!$i || $i['type'] !== 'particulier') page_message('Lien invalide', '<p>Ce lien de rétractation n’est pas valide.</p>');
$s = session_get((int)$i['session_id']); $p = programme((int)$s['programme_id']);
if ($i['statut'] === 'retractee') page_message('Rétractation déjà enregistrée', '<p>Votre rétractation a bien été prise en compte. Aucun paiement ne vous sera demandé.</p>');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check() && post('confirme') === 'oui') {
  try { retracter($i); mail_flush(); page_message('Rétractation enregistrée', '<p>Votre inscription à « ' . h($p['titre']) . ' » est annulée, sans frais. Une confirmation vous est envoyée par courriel.</p>'); }
  catch (DomainException $e) { page_message('Rétractation impossible', '<p>' . h($e->getMessage()) . '</p><p>Pour toute question : ' . h(ORG['email']) . ' ou ' . h(ORG['tel']) . '.</p>'); }
}
if ($i['statut'] !== 'retractation' || ts((string)$i['retractation_fin']) < time()) page_message('Délai échu', '<p>Le délai de rétractation de cette inscription est arrivé à son terme. Pour toute demande, écrivez à ' . h(ORG['email']) . '.</p>');
page_message('Confirmer votre rétractation', '<p>Vous êtes sur le point de renoncer à votre inscription à <strong>' . h($p['titre']) . '</strong>, ' . h(periode_fr($s['date_debut'], $s['date_fin'])) . '. Cette démarche est gratuite et ne nécessite aucun motif.</p>'
  . '<form method="post">' . csrf_field() . '<input type="hidden" name="confirme" value="oui"><button class="pf-btn" type="submit">Je confirme ma rétractation</button></form>');
