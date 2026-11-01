<?php
/* Paiement simulé — uniquement en environnement de test, sans clé Stripe. Désactivé en production. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
if (!stripe_simulation() || cfg('env') === 'prod') { http_response_code(404); exit; }
$ref = json_decode(get('ref', 300), true) ?: []; $m = (int)get('m', 20);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
  if (post('ok') === '1') { paiement_confirme($ref, get('cs', 80), 'pi_sim_' . token(8), $m); redirect(get('ok', 400) . (str_contains(get('ok', 400), '?') ? '&' : '?') . 'cs=' . urlencode(get('cs', 80))); }
  redirect(get('ko', 400));
}
page_message('Paiement de test', '<p><strong>Mode test : aucun paiement réel.</strong></p><p>' . h(get('l', 300)) . '</p><p style="font-size:1.6rem;font-family:Georgia,serif;color:#21406A">' . h(eur($m)) . '</p>'
  . '<form method="post" style="display:flex;gap:10px;flex-wrap:wrap">' . csrf_field() . '<button class="pf-btn" name="ok" value="1">Simuler un paiement réussi</button><button class="pf-btn pf-btn-ghost" name="ok" value="0">Simuler un abandon</button></form>');
