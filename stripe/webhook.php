<?php
/* Notifications Stripe : seule source de confirmation des paiements. Signature vérifiée, traitement idempotent. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
$payload = (string)file_get_contents('php://input');
if (!stripe_verify($payload, (string)($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''), (string)cfg('stripe.webhook_secret'))) { http_response_code(400); exit('signature'); }
$ev = json_decode($payload, true);
if (!is_array($ev) || empty($ev['id'])) { http_response_code(400); exit; }
if (db_val('SELECT id FROM stripe_events WHERE id=?', [$ev['id']])) { http_response_code(200); exit('deja'); }
$o = $ev['data']['object'] ?? [];
switch ($ev['type']) {
  case 'checkout.session.completed':
  case 'checkout.session.async_payment_succeeded':
    if (($o['payment_status'] ?? '') === 'paid') paiement_confirme($o['metadata'] ?? [], (string)$o['id'], (string)($o['payment_intent'] ?? ''), (int)($o['amount_total'] ?? 0));
    break;
  case 'checkout.session.expired':
    if (!empty($o['metadata']['commande'])) {
      $c = db_one("SELECT * FROM commandes WHERE id=? AND statut='attente'", [(int)$o['metadata']['commande']]);
      if ($c) { db_update('commandes', (int)$c['id'], ['statut' => 'abandonnee']); if ($c['creneau_id']) db_exec("UPDATE creneaux SET statut='libre', commande_id=NULL, hold_until=NULL WHERE id=? AND statut='reserve'", [$c['creneau_id']]); }
    }
    break;
  case 'checkout.session.async_payment_failed':
  case 'payment_intent.payment_failed':
    journal('stripe', 'paiement_echoue', (string)($o['id'] ?? ''), json_encode($o['metadata'] ?? []));
    break;
  case 'charge.dispute.created':
    admin_notifier('Contestation de paiement', p_('Un paiement fait l’objet d’une contestation bancaire. Consultez votre tableau de bord Stripe.'));
    break;
}
db_exec('INSERT INTO stripe_events(id,type,recu_at) VALUES(?,?,?)', [$ev['id'], $ev['type'], now()]);
mail_flush();
http_response_code(200); echo 'ok';
