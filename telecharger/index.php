<?php
/* Téléchargement d'un e-book ou d'un guide acheté : lien personnel, durée et nombre limités. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$c = db_one('SELECT * FROM commandes WHERE token=?', [get('t', 64)]);
if (!$c || $c['statut'] !== 'payee') page_message('Lien invalide', '<p>Ce lien de téléchargement n’est pas valide.</p>');
$p = produit((int)$c['produit_id']);
$max = (int)param('max_telechargements', 5);
if (ts((string)$c['acces_fin']) < time()) page_message('Lien expiré', '<p>La période de téléchargement est terminée. Écrivez-nous à ' . h(ORG['email']) . ' en rappelant votre adresse d’achat : nous vous renverrons le fichier.</p>');
if ((int)$c['telechargements'] >= $max) page_message('Nombre de téléchargements atteint', '<p>Ce lien a été utilisé ' . $max . ' fois. Écrivez-nous à ' . h(ORG['email']) . ' si vous avez besoin d’un nouvel accès.</p>');
$path = LP_STORAGE . '/produits/' . basename((string)$p['fichier']);
if (!$p['fichier'] || !is_file($path)) { log_error('Fichier manquant pour le produit ' . $p['id']); page_message('Fichier momentanément indisponible', '<p>Nous avons été prévenus et vous l’adressons au plus vite par courriel.</p>'); }
if (get('go') !== '1') page_message($p['titre'], '<p>Téléchargements utilisés : ' . (int)$c['telechargements'] . ' sur ' . $max . ', jusqu’au ' . h(date_fr((string)$c['acces_fin'])) . '.</p><p><a class="pf-btn" href="?t=' . h($c['token']) . '&go=1">Télécharger le PDF</a></p>', 'vert');
db_exec('UPDATE commandes SET telechargements=telechargements+1 WHERE id=?', [$c['id']]);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9-]+/i', '-', $p['slug']) . '.pdf"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
readfile($path);
