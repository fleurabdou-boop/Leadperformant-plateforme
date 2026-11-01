<?php
/* Lead Performant — plateforme de formation : amorçage commun. */
declare(strict_types=1);

define('LP_ROOT', dirname(__DIR__));
define('LP_DIR', __DIR__);
define('LP_STORAGE', __DIR__ . '/storage');

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

$GLOBALS['LP_CFG'] = require (is_file(__DIR__ . '/config.php') ? __DIR__ . '/config.php' : __DIR__ . '/config.sample.php');

function cfg(string $path, $default = null) {
  $v = $GLOBALS['LP_CFG'];
  foreach (explode('.', $path) as $k) { if (!is_array($v) || !array_key_exists($k, $v)) return $default; $v = $v[$k]; }
  return $v;
}

require __DIR__ . '/db.php';
require __DIR__ . '/pdf.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/stripe.php';
require __DIR__ . '/domain.php';
require __DIR__ . '/documents.php';
require __DIR__ . '/emails.php';
require __DIR__ . '/layout.php';

/* ---------- Utilitaires ---------- */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function ts(string $d): int { return (int)strtotime($d); }
function token(int $bytes = 24): string { return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '='); }
function url(string $path = '/'): string { return rtrim((string)cfg('site_url'), '/') . $path; }
function eur(int $cents): string { return number_format($cents / 100, 2, ',', "\u{202F}") . "\u{00A0}€"; }
function to_cents($v): int { $v = str_replace([' ', "\u{202F}", "\u{00A0}", '€'], '', (string)$v); $v = str_replace(',', '.', $v); return (int)round(((float)$v) * 100); }
function redirect(string $to, int $code = 303): void { header('Location: ' . $to, true, $code); exit; }
function post(string $k, int $max = 2000): string { $v = isset($_POST[$k]) ? (string)$_POST[$k] : ''; return mb_substr(trim(str_replace(["\r\n", "\r"], "\n", $v)), 0, $max); }
function get(string $k, int $max = 200): string { $v = isset($_GET[$k]) ? (string)$_GET[$k] : ''; return mb_substr(trim($v), 0, $max); }
function line(string $s): string { return trim(preg_replace('/[\r\n]+/', ' ', $s)); }
function is_email(string $e): bool { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }
function client_ip(): string { return (string)($_SERVER['REMOTE_ADDR'] ?? ''); }

const MOIS = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
const JOURS = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
function date_fr(string $d, bool $withDay = false): string {
  $t = ts($d);
  return ($withDay ? JOURS[(int)date('w', $t)] . ' ' : '') . (int)date('j', $t) . (date('j', $t) === '1' ? 'er' : '') . ' ' . MOIS[(int)date('n', $t)] . ' ' . date('Y', $t);
}
function periode_fr(string $d1, string $d2): string {
  $a = ts($d1); $b = ts($d2);
  if (date('Y-m-d', $a) === date('Y-m-d', $b)) return date_fr($d1, true);
  if (date('Y-m', $a) === date('Y-m', $b)) return JOURS[(int)date('w', $a)] . ' ' . (int)date('j', $a) . ' au ' . date_fr($d2, true);
  return date_fr($d1, true) . ' au ' . date_fr($d2, true);
}
function heure_fr(string $hm): string { [$h, $m] = array_map('intval', explode(':', $hm . ':0')); return $h . ' h ' . str_pad((string)$m, 2, '0', STR_PAD_LEFT); }

/* Jeton anti-falsification de formulaire (session PHP courte, sans cookie tiers) */
function csrf_start(string $name = 'lp_pub'): void {
  if (session_status() === PHP_SESSION_ACTIVE) return;
  session_name($name);
  session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
  session_start();
}
function csrf_token(): string { csrf_start(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = token(16); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_check(): bool { csrf_start(); return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']); }

/* Mesure d'audience sans cookie ni donnée personnelle : un compteur par page et par jour. */
function track(string $page): void {
  $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
  if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|monitor/i', $ua)) return;
  try {
    $day = date('Y-m-d');
    $n = db_val('SELECT id FROM vues WHERE jour=? AND page=?', [$day, $page]);
    if ($n) db_exec('UPDATE vues SET n=n+1 WHERE id=?', [$n]); else db_exec('INSERT INTO vues(jour,page,n) VALUES(?,?,1)', [$day, $page]);
  } catch (Throwable $e) { /* jamais bloquant */ }
}

function log_error(string $msg): void {
  @file_put_contents(LP_STORAGE . '/logs/erreurs-' . date('Y-m') . '.log', '[' . now() . '] ' . $msg . "\n", FILE_APPEND | LOCK_EX);
}
set_exception_handler(function (Throwable $e) {
  log_error(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
  if (PHP_SAPI === 'cli') { fwrite(STDERR, $e . "\n"); exit(1); }
  http_response_code(500);
  echo '<!doctype html><meta charset="utf-8"><title>Erreur</title><p style="font-family:sans-serif;padding:40px">Une erreur est survenue. Elle a été enregistrée ; merci de réessayer dans quelques instants ou d’écrire à ' . h(cfg('mail.reply_to')) . '.</p>';
});

foreach (['logs', 'docs', 'produits', 'uploads', 'outbox'] as $d) { if (!is_dir(LP_STORAGE . "/$d")) @mkdir(LP_STORAGE . "/$d", 0750, true); }
db_migrate();
