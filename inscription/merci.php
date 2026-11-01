<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
$i = inscription_by_token(get('t', 64));
if (!$i) redirect('/calendrier/');
$s = session_get((int)$i['session_id']); $p = programme((int)$s['programme_id']); $c = client((int)$i['client_id']);
page_message('Votre place est réservée',
  '<p>Merci ' . h($c['prenom']) . '. Votre contrat de formation est signé pour <strong>' . h($p['titre']) . '</strong>, ' . h(periode_fr($s['date_debut'], $s['date_fin'])) . '.</p>'
  . '<p>Il vient de vous être envoyé par courriel à ' . h($c['email']) . ', avec votre échéancier.</p>'
  . '<p><strong>Vous disposez de dix jours, jusqu’au ' . h(date_fr((string)$i['retractation_fin'])) . ', pour vous rétracter sans frais.</strong> Aucun paiement n’est demandé pendant ce délai ; le lien de paiement de l’acompte vous parviendra ensuite.</p>'
  . '<p style="display:flex;flex-wrap:wrap;gap:10px;margin-top:22px"><a class="pf-btn" href="' . h(acces_url((int)$i['client_id'])) . '">Mon espace</a><a class="pf-btn pf-btn-ghost" href="/boutique/">Préparer la formation : la boutique</a></p>', 'vert');
