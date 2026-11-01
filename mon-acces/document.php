<?php
/* Remise d'un document au seul titulaire (jeton client, ou jeton d'inscription pour les entreprises). */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$t = get('t', 64);
$c = db_one('SELECT * FROM clients WHERE acces_token=?', [$t]);
$i = $c ? null : inscription_by_token($t);
if (!$c && !$i) { http_response_code(404); exit; }
if (get('r') !== '') {
  $m = db_one('SELECT * FROM commandes WHERE id=? AND client_id=?', [(int)get('r'), $c['id'] ?? 0]);
  $path = $m && $m['reponse_fichier'] ? LP_STORAGE . '/uploads/' . basename($m['reponse_fichier']) : '';
  if (!$path || !is_file($path)) { http_response_code(404); exit; }
  header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="' . basename($path) . '"'); readfile($path); exit;
}
$d = db_one('SELECT * FROM documents WHERE id=?', [(int)get('d')]);
$ok = false;
if ($d && $c) {
  $ok = ($d['inscription_id'] && db_val('SELECT id FROM inscriptions WHERE id=? AND client_id=?', [$d['inscription_id'], $c['id']]))
     || ($d['commande_id'] && db_val('SELECT id FROM commandes WHERE id=? AND client_id=?', [$d['commande_id'], $c['id']]))
     || db_val('SELECT id FROM factures WHERE doc_id=? AND client_id=?', [$d['id'], $c['id']]);
}
if ($d && $i) $ok = (int)$d['inscription_id'] === (int)$i['id'];
$path = $d ? LP_STORAGE . '/' . $d['chemin'] : '';
if (!$ok || !is_file($path)) { http_response_code(404); exit; }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . str_replace('"', '', $d['nom']) . '"');
header('Cache-Control: private, no-store');
readfile($path);
