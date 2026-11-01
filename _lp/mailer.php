<?php
/* Courriels : file d'attente en base, envoi SMTP authentifié (SSL 465 ou STARTTLS 587), journalisation. */

/* Met un courriel en file. $cle évite les doublons (ex. "rappel-j7-insc-12") : un même événement n'envoie qu'une fois. */
function mail_queue(string $to, string $subject, string $html, array $pieces = [], ?string $cle = null): void {
  if (!is_email($to)) return;
  if ($cle !== null && db_val('SELECT id FROM courriels WHERE cle=?', [$cle])) return;
  db_insert('courriels', ['destinataire' => $to, 'sujet' => $subject, 'corps_html' => $html, 'corps_texte' => html_to_text($html),
    'pieces' => json_encode($pieces), 'cle' => $cle, 'statut' => 'attente', 'created_at' => now()]);
}

function html_to_text(string $html): string {
  $t = preg_replace('#<a [^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html);
  $t = preg_replace('#<(br|/p|/h[1-6]|/li|/tr|/div)[^>]*>#i', "\n", $t);
  $t = preg_replace('#<li[^>]*>#i', '- ', $t);
  $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');
  return trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $t)));
}

/* Envoie la file (appelé par les tâches planifiées et juste après les actions importantes). */
function mail_flush(int $max = 30): int {
  $sent = 0;
  foreach (db_all("SELECT * FROM courriels WHERE statut='attente' AND essais < 5 ORDER BY id LIMIT $max") as $m) {
    try {
      mail_send($m['destinataire'], $m['sujet'], $m['corps_html'], $m['corps_texte'], json_decode((string)$m['pieces'], true) ?: []);
      db_update('courriels', (int)$m['id'], ['statut' => 'envoye', 'envoye_at' => now(), 'erreur' => null]);
      $sent++;
    } catch (Throwable $e) {
      db_update('courriels', (int)$m['id'], ['essais' => (int)$m['essais'] + 1, 'erreur' => mb_substr($e->getMessage(), 0, 500), 'statut' => (int)$m['essais'] >= 4 ? 'echec' : 'attente']);
      log_error('Courriel #' . $m['id'] . ' : ' . $e->getMessage());
    }
  }
  return $sent;
}

function mail_send(string $to, string $subject, string $html, string $text, array $pieces): void {
  $from = (string)cfg('mail.from'); $name = (string)cfg('mail.from_name');
  $enc = fn($s) => '=?UTF-8?B?' . base64_encode($s) . '?=';
  $bAlt = 'alt' . bin2hex(random_bytes(8)); $bMix = 'mix' . bin2hex(random_bytes(8));
  $headers = [
    'Date: ' . date('r'),
    'From: ' . $enc($name) . " <$from>",
    'To: <' . $to . '>',
    'Reply-To: ' . (string)cfg('mail.reply_to'),
    'Subject: ' . $enc($subject),
    'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($from, '@'), 1) . '>',
    'MIME-Version: 1.0',
  ];
  $alt = "--$bAlt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
       . "--$bAlt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$bAlt--\r\n";
  if ($pieces) {
    $headers[] = "Content-Type: multipart/mixed; boundary=\"$bMix\"";
    $body = "--$bMix\r\nContent-Type: multipart/alternative; boundary=\"$bAlt\"\r\n\r\n$alt";
    foreach ($pieces as $p) {
      $path = LP_STORAGE . '/' . ltrim((string)($p['chemin'] ?? ''), '/');
      $data = isset($p['contenu']) ? (string)$p['contenu'] : (is_file($path) ? (string)file_get_contents($path) : '');
      if ($data === '') continue;
      $type = $p['type'] ?? 'application/pdf';
      $body .= "--$bMix\r\nContent-Type: $type; name=\"" . $p['nom'] . "\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $p['nom'] . "\"\r\n\r\n" . chunk_split(base64_encode($data));
    }
    $body .= "--$bMix--\r\n";
  } else {
    $headers[] = "Content-Type: multipart/alternative; boundary=\"$bAlt\"";
    $body = $alt;
  }

  $transport = (string)cfg('mail.transport', 'smtp');
  if ($transport === 'file') {
    file_put_contents(LP_STORAGE . '/outbox/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml', implode("\r\n", $headers) . "\r\n\r\n" . $body);
    return;
  }
  if ($transport === 'mail') {
    $h = array_filter($headers, fn($l) => !str_starts_with($l, 'To:') && !str_starts_with($l, 'Subject:'));
    if (!mail($to, $enc($subject), $body, implode("\r\n", $h), '-f' . $from)) throw new RuntimeException('mail() a échoué');
    return;
  }
  smtp_send($from, $to, implode("\r\n", $headers) . "\r\n\r\n" . $body);
}

function smtp_send(string $from, string $to, string $data): void {
  $host = (string)cfg('mail.smtp_host'); $port = (int)cfg('mail.smtp_port', 465);
  $ssl = $port === 465;
  $fp = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $err, 20, STREAM_CLIENT_CONNECT,
    stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]));
  if (!$fp) throw new RuntimeException("Connexion SMTP impossible : $err");
  stream_set_timeout($fp, 30);
  $read = function () use ($fp) { $r = ''; while (($l = fgets($fp, 1024)) !== false) { $r .= $l; if (isset($l[3]) && $l[3] === ' ') break; } return $r; };
  $cmd = function (string $c, array $ok) use ($fp, $read) { if ($c !== '') fwrite($fp, $c . "\r\n"); $r = $read(); if (!in_array((int)substr($r, 0, 3), $ok, true)) throw new RuntimeException('SMTP : ' . trim($r)); return $r; };
  $cmd('', [220]);
  $ehlo = 'EHLO ' . (parse_url((string)cfg('site_url'), PHP_URL_HOST) ?: 'localhost');
  $cmd($ehlo, [250]);
  if (!$ssl) { $cmd('STARTTLS', [220]); stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT); $cmd($ehlo, [250]); }
  $cmd('AUTH LOGIN', [334]); $cmd(base64_encode((string)cfg('mail.smtp_user')), [334]); $cmd(base64_encode((string)cfg('mail.smtp_pass')), [235]);
  $cmd("MAIL FROM:<$from>", [250]); $cmd("RCPT TO:<$to>", [250, 251]); $cmd('DATA', [354]);
  $data = preg_replace('/^\./m', '..', $data);
  $cmd($data . "\r\n.", [250]);
  $cmd('QUIT', [221]);
  fclose($fp);
}
