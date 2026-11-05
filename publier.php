<?php
/* Lead Performant — publication de la refonte des pages (audit de novembre 2026).
   Appel : /_plateforme_src/publier.php?k=<jeton>&mode=verif|appliquer|restaurer
   Le jeton n'est pas dans le dépôt : seule son empreinte SHA-256 y figure.
   « appliquer » sauvegarde d'abord chaque fichier remplacé (zip hors du dossier public), puis copie
   les fichiers de refonte/ vers le site ; il s'arrête si un fichier en ligne a changé depuis la préparation.
   « restaurer » remet en place la dernière sauvegarde de refonte. */
declare(strict_types=1);
const JETON_SHA256 = '3576e5607ca85ec93ca7b7b82e09e6fe589fec27ff75ed37621e5996b228bd8f';
const FICHIERS = [
  "art-oratoire/index.html" => "6cff4bb9176986c69f7eb2a942f72f106917cde71bbb233b436a791d91040ef7",
  "assets/lp/accueil.js" => null,
  "assets/lp/site.css" => "9767f6db592cea85a831541f82a3abe50e287a119cbc7a9782398cbc8003826d",
  "assets/lp/site.js" => "1978b3b8a7b8e4bc2581f7b6d96bfb681346149f674dca77bd0ac8f2159628c9",
  "contact/envoyer.php" => "7a4f82fd092fcb0f6119328e105af49028000b1567eee2c35136d776d1005f25",
  "contact/index.html" => "5a45c318d9f0e2363afecc58119eaff54638ffde3dffc182d4d49c0fb7cc07fe",
  "formations/eloquence-elite/index.html" => "fa588a2b012b1eab20dc4112f407381674a3d5a59927e2b181984969475aa9e0",
  "formations/eloquence-levier-professionnel/index.html" => "9e57a6e9a3423ca9467620d7ec9320e2369779bba37aca34cd75ef5e652aea1a",
  "formations/leadership-relationnel/index.html" => "a375e8f2870a65f14128a0b1e39703eb250f09e3857b7f2dc0cf488fccca6997",
  "formations/leadership-strategique/index.html" => "3a24fb0071c2cc6d676aeae853bc8dbd80e41bb1decf8cc2c7cacdf6019aa937",
  "formations/parole-d-impact/index.html" => "b763e03b1ac91a08c7de64e045a3984044ba3eaebd8dbeb015c7fa214ce4cad9",
  "index.html" => "2c8f5bd481fb51d499fd72337a14741ca45c2a9c7940f02f6461d48979f1edad",
  "sitemap.xml" => "dfd520c007439130498d24598d39c607bb1a5e688668068311ed294fe4775e12"
];
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
if (!hash_equals(JETON_SHA256, hash('sha256', (string)($_GET['k'] ?? '')))) { http_response_code(404); echo "Introuvable.\n"; exit; }
@set_time_limit(300);
$SRC = __DIR__ . '/refonte';
$DST = dirname(__DIR__);
$SAUV = dirname($DST) . '/sauvegardes-lp';
$mode = (string)($_GET['mode'] ?? 'verif');
function empreinte(string $f): string { return hash('sha256', rtrim((string)file_get_contents($f), "\n")); }
echo "Refonte Lead Performant — mode $mode — " . date('Y-m-d H:i:s') . "\n";

if ($mode === 'restaurer') {
  $zips = glob($SAUV . '/refonte-*.zip') ?: [];
  if (!$zips) { echo "ERREUR  aucune sauvegarde de refonte.\n"; exit; }
  sort($zips); $zipf = end($zips);
  $z = new ZipArchive(); if ($z->open($zipf) !== true) { echo "ERREUR  sauvegarde illisible.\n"; exit; }
  $dans = [];
  for ($i = 0; $i < $z->numFiles; $i++) { $n = $z->getNameIndex($i); $dans[$n] = true; file_put_contents($DST . '/' . $n . '.tmp-lp', $z->getFromIndex($i)); rename($DST . '/' . $n . '.tmp-lp', $DST . '/' . $n); echo "OK      restauré $n\n"; }
  foreach (FICHIERS as $rel => $att) if ($att === null && !isset($dans[$rel]) && is_file($DST . '/' . $rel)) { unlink($DST . '/' . $rel); echo "OK      retiré $rel (fichier ajouté par la refonte)\n"; }
  echo "Terminé : " . basename($zipf) . "\n"; exit;
}

$conflits = []; $deja = 0;
foreach (FICHIERS as $rel => $att) {
  $src = $SRC . '/' . $rel; $cible = $DST . '/' . $rel;
  if (!is_file($src)) { echo "ERREUR  source absente : $rel\n"; exit; }
  $etat = is_file($cible) ? empreinte($cible) : null;
  if ($etat !== null && $etat === empreinte($src)) { $deja++; echo "        déjà à jour : $rel\n"; continue; }
  if ($etat !== $att) { $conflits[] = $rel; echo "ATTENTION modifié en ligne depuis la préparation : $rel\n"; continue; }
  echo "        à publier : $rel\n";
}
if ($mode === 'verif') { echo ($conflits ? "Conflits : " . count($conflits) : "Aucun conflit") . " ; déjà à jour : $deja\n"; exit; }
if ($mode !== 'appliquer') { echo "ERREUR  mode inconnu.\n"; exit; }
if ($conflits && ($_GET['force'] ?? '') !== '1') { echo "Publication interrompue : rien n'a été modifié.\n"; exit; }

if (!is_dir($SAUV) && !@mkdir($SAUV, 0750, true)) { echo "ERREUR  dossier de sauvegarde impossible.\n"; exit; }
$zipf = $SAUV . '/refonte-' . date('Ymd-His') . '.zip';
$z = new ZipArchive();
if ($z->open($zipf, ZipArchive::CREATE | ZipArchive::EXCL) !== true) { echo "ERREUR  sauvegarde impossible.\n"; exit; }
$n = 0;
foreach (FICHIERS as $rel => $att) if (is_file($DST . '/' . $rel)) { $z->addFile($DST . '/' . $rel, $rel); $n++; }
$z->close();
$zz = new ZipArchive(); if ($zz->open($zipf) !== true || $zz->numFiles !== $n) { echo "ERREUR  sauvegarde incomplète : rien n'a été modifié.\n"; exit; } $zz->close();
echo "OK      sauvegarde de $n fichiers : " . $zipf . "\n";
foreach (FICHIERS as $rel => $att) {
  $src = $SRC . '/' . $rel; $cible = $DST . '/' . $rel;
  if (!is_dir(dirname($cible))) @mkdir(dirname($cible), 0755, true);
  if (!copy($src, $cible . '.tmp-lp') || !rename($cible . '.tmp-lp', $cible)) { echo "ERREUR  copie impossible : $rel (restaurer avec mode=restaurer)\n"; exit; }
  echo "OK      publié $rel\n";
}
echo "Terminé.\n";
