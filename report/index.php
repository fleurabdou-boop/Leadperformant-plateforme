<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$i = inscription_by_token(get('t', 64));
$choix = get('c', 20);
if (!$i || !str_starts_with((string)$i['motif'], 'report:')) page_message('Lien sans objet', '<p>Aucun choix n’est attendu pour cette inscription, ou votre réponse a déjà été enregistrée.</p>');
$cible = session_get((int)substr((string)$i['motif'], 7)); $p = programme((int)$cible['programme_id']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
  try {
    $r = choix_report($i, post('c') === 'garder'); mail_flush();
    page_message('Votre choix est enregistré', $r === 'transfert' ? '<p>Votre place est transférée sur la session du ' . h(periode_fr($cible['date_debut'], $cible['date_fin'])) . '. Votre échéancier est mis à jour.</p>' : '<p>Votre remboursement est en cours ; une confirmation vous est envoyée par courriel.</p>');
  } catch (DomainException $e) { page_message('Choix impossible', '<p>' . h($e->getMessage()) . '</p>'); }
}
$garder = $choix !== 'rembourser';
page_message('Report de votre session', '<p>Nouvelle date proposée pour <strong>' . h($p['titre']) . '</strong> : ' . h(periode_fr($cible['date_debut'], $cible['date_fin'])) . '.</p>'
  . '<form method="post">' . csrf_field() . '<input type="hidden" name="c" value="' . ($garder ? 'garder' : 'rembourser') . '"><button class="pf-btn" type="submit">' . ($garder ? 'Je garde ma place sur la nouvelle date' : 'Je demande le remboursement intégral') . '</button></form>'
  . '<p class="pf-note"><a href="?t=' . h($i['token']) . '&c=' . ($garder ? 'rembourser' : 'garder') . '">' . ($garder ? 'Je préfère être remboursé(e)' : 'Je préfère garder ma place') . '</a></p>');
