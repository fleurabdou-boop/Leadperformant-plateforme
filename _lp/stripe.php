<?php
/* Stripe : sessions de paiement (Checkout), remboursements, vérification des notifications signées.
   Mode « simulation » (clé commençant par sim_) : aucune connexion à Stripe, une page locale imite le paiement — réservé aux tests. */

function stripe_simulation(): bool { return str_starts_with((string)cfg('stripe.secret'), 'sim_') || (string)cfg('stripe.secret') === ''; }

function stripe_api(string $method, string $path, array $params = []): array {
  $ch = curl_init('https://api.stripe.com/v1/' . $path);
  $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => cfg('stripe.secret') . ':', CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20']];
  if ($method === 'POST') { $opts[CURLOPT_POST] = true; $opts[CURLOPT_POSTFIELDS] = http_build_query($params); }
  elseif ($params) { curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/' . $path . '?' . http_build_query($params)); }
  curl_setopt_array($ch, $opts);
  $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $err = curl_error($ch); curl_close($ch);
  if ($raw === false) throw new RuntimeException('Stripe injoignable : ' . $err);
  $j = json_decode((string)$raw, true) ?: [];
  if ($code >= 400) throw new RuntimeException('Stripe : ' . ($j['error']['message'] ?? "HTTP $code"));
  return $j;
}

/* Crée une page de paiement pour un montant donné. $ref = ['echeance' => id] ou ['commande' => id]. */
function stripe_checkout(int $amount, string $label, string $email, array $ref, string $successUrl, string $cancelUrl, int $ttlMinutes = 60): array {
  if (stripe_simulation()) {
    $id = 'cs_sim_' . token(12);
    return ['id' => $id, 'url' => url('/paiement/simulation.php?cs=' . urlencode($id) . '&ref=' . urlencode(json_encode($ref)) . '&m=' . $amount . '&l=' . urlencode($label) . '&ok=' . urlencode($successUrl) . '&ko=' . urlencode($cancelUrl))];
  }
  $p = [
    'mode' => 'payment',
    'locale' => 'fr',
    'customer_email' => $email,
    'line_items' => [['quantity' => 1, 'price_data' => ['currency' => 'eur', 'unit_amount' => $amount, 'product_data' => ['name' => mb_substr($label, 0, 250)]]]],
    'payment_method_types' => ['card'],
    'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'cs={CHECKOUT_SESSION_ID}',
    'cancel_url' => $cancelUrl,
    'expires_at' => time() + max(30, $ttlMinutes) * 60,
    'metadata' => array_map('strval', $ref),
    'payment_intent_data' => ['metadata' => array_map('strval', $ref), 'description' => mb_substr($label, 0, 250)],
  ];
  $s = stripe_api('POST', 'checkout/sessions', $p);
  return ['id' => $s['id'], 'url' => $s['url']];
}

function stripe_refund(string $paymentIntent, ?int $amount = null): string {
  if (stripe_simulation() || str_starts_with($paymentIntent, 'pi_sim_')) return 're_sim_' . token(8);
  $p = ['payment_intent' => $paymentIntent]; if ($amount !== null) $p['amount'] = $amount;
  $r = stripe_api('POST', 'refunds', $p);
  return $r['id'];
}

/* Vérifie la signature Stripe-Signature (HMAC SHA-256, tolérance 5 minutes). */
function stripe_verify(string $payload, string $header, string $secret, int $tolerance = 300): bool {
  $t = null; $sigs = [];
  foreach (explode(',', $header) as $part) { [$k, $v] = array_pad(explode('=', trim($part), 2), 2, ''); if ($k === 't') $t = (int)$v; if ($k === 'v1') $sigs[] = $v; }
  if (!$t || !$sigs || abs(time() - $t) > $tolerance) return false;
  $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
  foreach ($sigs as $s) if (hash_equals($expected, $s)) return true;
  return false;
}
