<?php
/* Tableau de bord : authentification et gabarit. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
session_name('lp_gestion');
session_set_cookie_params(['lifetime' => 0, 'path' => '/gestion/', 'secure' => !empty($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Strict']);
session_start();

function a_csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = token(16); return $_SESSION['csrf']; }
function a_field(): string { return '<input type="hidden" name="csrf" value="' . h(a_csrf()) . '">'; }
function a_ok(): bool { return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']); }
function a_logged(): bool { return !empty($_SESSION['ok']) && ($_SESSION['t'] ?? 0) > time() - 7200; }
function a_flash(?string $m = null) { if ($m !== null) { $_SESSION['flash'] = $m; return null; } $f = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $f; }
function a_go(string $q): void { redirect('/gestion/?' . $q); }

/* Connexion : mot de passe défini via un code envoyé à l'adresse du cabinet. */
function a_login_page(): void {
  $hash = (string)param('admin_hash');
  $msg = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && a_ok()) {
    $fails = json_decode((string)param('admin_echecs', '[]'), true) ?: [];
    $fails = array_values(array_filter($fails, fn($t) => $t > time() - 900));
    if (count($fails) >= 8) { $msg = 'Trop de tentatives. Réessayez dans quinze minutes.'; }
    elseif (post('a') === 'code') {
      $last = (int)param('admin_code_at', '0');
      if ($last > time() - 600) $msg = 'Un code a déjà été envoyé il y a moins de dix minutes à ' . cfg('mail.admin') . '.';
      else {
        $code = (string)random_int(100000, 999999);
        param_set('admin_code', password_hash($code, PASSWORD_DEFAULT)); param_set('admin_code_at', (string)time());
        mail_queue((string)cfg('mail.admin'), 'Code d’accès au tableau de bord', email_wrap('Votre code', p_('Code à saisir pour définir le mot de passe du tableau de bord : <strong style="font-size:22px;letter-spacing:3px">' . $code . '</strong>') . p_('Valable une heure. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.')));
        mail_flush(); $msg = 'Un code vient d’être envoyé à ' . cfg('mail.admin') . '.';
      }
    } elseif (post('a') === 'definir') {
      $ok = (int)param('admin_code_at', '0') > time() - 3600 && password_verify(post('code', 10), (string)param('admin_code'));
      if (!$ok) { $fails[] = time(); param_set('admin_echecs', json_encode($fails)); $msg = 'Code incorrect ou expiré.'; }
      elseif (mb_strlen(post('mdp', 200)) < 12 || post('mdp', 200) !== post('mdp2', 200)) $msg = 'Le mot de passe doit comporter douze caractères au moins et être confirmé à l’identique.';
      else { param_set('admin_hash', password_hash(post('mdp', 200), PASSWORD_DEFAULT)); param_set('admin_code', ''); session_regenerate_id(true); $_SESSION['ok'] = 1; $_SESSION['t'] = time(); journal('admin', 'mot_de_passe_defini'); a_go('v=accueil'); }
    } elseif (post('a') === 'login' && $hash !== '') {
      if (password_verify(post('mdp', 200), $hash)) { session_regenerate_id(true); $_SESSION['ok'] = 1; $_SESSION['t'] = time(); param_set('admin_echecs', '[]'); a_go('v=accueil'); }
      $fails[] = time(); param_set('admin_echecs', json_encode($fails)); sleep(1); $msg = 'Mot de passe incorrect.';
    }
  }
  $reset = get('reset') === '1' || $hash === '';
  a_head('Connexion');
  echo '<main class="g-login"><div class="g-card" style="max-width:420px;margin:60px auto"><p style="text-align:center;margin:0 0 10px"><img src="/assets/img/logo-lead-performant.png" alt="Lead Performant" width="96" height="93"></p><h1 class="g-h1" style="text-align:center">Tableau de bord</h1>' . ($msg ? '<p class="g-msg">' . h($msg) . '</p>' : '');
  if (!$reset) {
    echo '<form method="post">' . a_field() . '<input type="hidden" name="a" value="login"><label class="g-f">Mot de passe<input type="password" name="mdp" required autocomplete="current-password" autofocus></label><button class="g-btn">Se connecter</button></form><p class="g-small"><a href="?reset=1">Mot de passe oublié</a></p>';
  } else {
    echo '<p class="g-small">Un code est envoyé à ' . h((string)cfg('mail.admin')) . ' pour définir le mot de passe.</p><form method="post">' . a_field() . '<input type="hidden" name="a" value="code"><button class="g-btn g-ghost">Recevoir le code</button></form>'
      . '<form method="post" style="margin-top:18px">' . a_field() . '<input type="hidden" name="a" value="definir"><label class="g-f">Code reçu<input name="code" inputmode="numeric" required></label><label class="g-f">Nouveau mot de passe (12 caractères au moins)<input type="password" name="mdp" required autocomplete="new-password"></label><label class="g-f">Confirmation<input type="password" name="mdp2" required autocomplete="new-password"></label><button class="g-btn">Définir et se connecter</button></form>' . ($hash ? '<p class="g-small"><a href="?">Retour</a></p>' : '');
  }
  echo '</div></main></body></html>';
  exit;
}

function a_head(string $title): void {
  echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . h($title) . ' · Gestion Lead Performant</title><meta name="robots" content="noindex,nofollow">'
    . '<link rel="icon" href="/assets/img/favicon-lead-performant.png"><link rel="stylesheet" href="/gestion/gestion.css?v=1"></head><body>';
}
const A_MENU = ['accueil' => 'Accueil', 'sessions' => 'Sessions', 'programmes' => 'Programmes', 'inscriptions' => 'Inscrits', 'paiements' => 'Paiements et factures', 'boutique' => 'Boutique', 'commandes' => 'Commandes et conseils', 'enquetes' => 'Enquêtes', 'courriels' => 'Courriels', 'exports' => 'Exports et BPF', 'parametres' => 'Paramètres'];
function a_layout_start(string $title, string $actif): void {
  a_head($title);
  echo '<div class="g-app"><aside class="g-side"><a class="g-brand" href="/gestion/"><img src="/assets/img/embleme-lead-performant.png" alt="" width="34" height="33"><span>LEAD PERFORMANT<small>Gestion</small></span></a><nav aria-label="Rubriques">';
  foreach (A_MENU as $k => $l) echo '<a href="/gestion/?v=' . $k . '"' . ($actif === $k ? ' aria-current="page"' : '') . '>' . h($l) . '</a>';
  echo '<a href="/espace-admin/">Interventions et médias</a><a href="/admin/">Textes du site</a><a href="/" target="_blank" rel="noopener">Voir le site</a><form method="post" action="/gestion/?v=logout">' . a_field() . '<button class="g-linkbtn">Se déconnecter</button></form></nav></aside><main class="g-main">';
  if ($f = a_flash()) echo '<p class="g-msg g-ok">' . h($f) . '</p>';
  $last = param('taches_derniere', '');
  if ($last === '' || ts($last) < time() - 3600) echo '<p class="g-msg">Les tâches planifiées n’ont pas tourné depuis plus d’une heure' . ($last ? ' (dernière exécution : ' . h(date('d/m H:i', ts($last))) . ')' : '') . ' : rappels et relances sont en attente.</p>';
}
function a_layout_end(): void { echo '</main></div></body></html>'; }
function a_table(array $head, array $rows, string $empty = 'Aucun élément.'): string {
  if (!$rows) return '<p class="g-empty">' . h($empty) . '</p>';
  $o = '<div class="g-scroll"><table class="g-t"><thead><tr>'; foreach ($head as $hh) $o .= '<th>' . h($hh) . '</th>'; $o .= '</tr></thead><tbody>';
  foreach ($rows as $r) { $o .= '<tr>'; foreach ($r as $c) $o .= '<td>' . $c . '</td>'; $o .= '</tr>'; }
  return $o . '</tbody></table></div>';
}
function a_input(string $name, string $label, $value = '', string $type = 'text', string $extra = ''): string {
  if ($type === 'textarea') return '<label class="g-f">' . h($label) . '<textarea name="' . h($name) . '" ' . $extra . '>' . h((string)$value) . '</textarea></label>';
  return '<label class="g-f">' . h($label) . '<input type="' . h($type) . '" name="' . h($name) . '" value="' . h((string)$value) . '" ' . $extra . '></label>';
}
function a_select(string $name, string $label, array $opts, $value): string {
  $o = '<label class="g-f">' . h($label) . '<select name="' . h($name) . '">';
  foreach ($opts as $k => $l) $o .= '<option value="' . h((string)$k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . h($l) . '</option>';
  return $o . '</select></label>';
}
function a_badge(string $statut): string {
  $ton = in_array($statut, ['confirmee', 'soldee', 'payee', 'ouverte', 'envoye'], true) ? 'ok' : (in_array($statut, ['retractee', 'expiree', 'annulee', 'remboursee', 'echec', 'abandonnee'], true) ? 'ko' : 'wait');
  return '<span class="g-b g-b-' . $ton . '">' . h(STATUTS_LIB[$statut] ?? ucfirst(str_replace('_', ' ', $statut))) . '</span>';
}
function csv_out(string $name, array $head, array $rows): void {
  header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="' . $name . '-' . date('Ymd') . '.csv"');
  $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF"); fputcsv($out, $head, ';');
  foreach ($rows as $r) fputcsv($out, $r, ';');
  exit;
}
function upload_to(string $field, string $dir, array $exts, int $maxMo): ?string {
  if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) return null;
  $ext = strtolower(pathinfo((string)$_FILES[$field]['name'], PATHINFO_EXTENSION));
  if (!in_array($ext, $exts, true) || $_FILES[$field]['size'] > $maxMo * 1024 * 1024) throw new DomainException('Fichier refusé (formats : ' . implode(', ', $exts) . ' ; ' . $maxMo . ' Mo au plus).');
  $name = date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
  move_uploaded_file($_FILES[$field]['tmp_name'], LP_STORAGE . '/' . $dir . '/' . $name);
  return $name;
}

if (get('reset') === '1' && a_logged()) { $_SESSION = []; }
if (!a_logged()) a_login_page();
$_SESSION['t'] = time();
