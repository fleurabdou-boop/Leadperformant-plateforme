<?php
/* Retour après paiement. N'enregistre rien : seule la notification Stripe vérifiée confirme un paiement. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$e = get('e', 64) ? db_one('SELECT * FROM echeances WHERE token=?', [get('e', 64)]) : null;
$c = get('c', 64) ? db_one('SELECT * FROM commandes WHERE token=?', [get('c', 64)]) : null;
$paye = ($e && $e['statut'] === 'payee') || ($c && $c['statut'] === 'payee');
$suite = '';
if ($c && $paye) { $p = produit((int)$c['produit_id']); $suite = in_array($p['type'], ['ebook', 'guide'], true) ? '<p><a class="pf-btn" href="/telecharger/?t=' . h($c['token']) . '">Télécharger maintenant</a></p>' : '<p><a class="pf-btn" href="' . h(acces_url((int)$c['client_id'])) . '">Mon espace</a></p>'; }
page_message($paye ? 'Paiement confirmé' : 'Paiement en cours de confirmation',
  ($paye ? '<p>Merci, votre paiement est confirmé. Votre facture vous est adressée par courriel.</p>' : '<p>Merci. Votre banque nous transmet la confirmation ; elle arrive en général en quelques secondes. Vous recevrez un courriel dès sa réception.</p><p class="pf-note">Cette page se met à jour toute seule.</p><script>setTimeout(function(){location.reload()},4000)</script>') . $suite, $paye ? 'vert' : 'neutre');
