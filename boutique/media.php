<?php
/* Couvertures et extraits gratuits de la boutique (fichiers rangés hors de la partie publique). */
require dirname(__DIR__) . '/_lp/bootstrap.php';
$f = basename(get('f', 200));
$p = db_one('SELECT * FROM produits WHERE actif=1 AND (couverture=? OR extrait=?)', [$f, $f]);
$path = LP_STORAGE . '/produits/' . $f;
if (!$p || $f === '' || !is_file($path)) { http_response_code(404); exit; }
$types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'pdf' => 'application/pdf'];
$ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=604800');
if ($ext === 'pdf') header('Content-Disposition: inline; filename="extrait-' . $p['slug'] . '.pdf"');
readfile($path);
