<?php
/* Références et témoignages : logos des organisations et témoignages, gérés depuis le tableau de bord
   (rubrique « Références et témoignages ») et publiés sur /references/, l'accueil et les pages des pôles. */

const REFS_CATEGORIES = ['entreprise' => 'Entreprise', 'institution' => 'Institution publique', 'collectivite' => 'Collectivité territoriale',
  'ecole' => 'École et université', 'association' => 'Association et fondation', 'international' => 'Organisation internationale', 'media' => 'Média'];
const REFS_POLES = ['tous' => 'Les deux pôles', 'p1' => 'I · Art oratoire et leadership', 'p2' => 'II · Médiation et gestion des conflits'];
const REFS_AFFICHAGE = ['complet' => 'Prénom et nom', 'initiale' => 'Prénom et initiale du nom', 'fonction' => 'Fonction et organisation seulement'];
const REFS_ACCORDS = ['' => 'Pas encore recueilli', 'formulaire' => 'Formulaire du site (case cochée par l’auteur)', 'courriel' => 'Accord écrit par courriel',
  'ecrit' => 'Document signé', 'questionnaire' => 'Questionnaire de fin de formation (case cochée)'];
const REFS_ETATS = ['a_valider' => 'À valider', 'publie' => 'En ligne', 'hors_ligne' => 'Hors ligne'];
/* Offres proposées dans le formulaire public, par pôle. */
const REFS_OFFRES = [
  'p1' => ['Éloquence Élite', 'L’Éloquence comme levier professionnel', 'Parole d’Impact', 'Leadership relationnel et mobilisation', 'Leadership stratégique et situations complexes'],
  'p2' => ['Boussole relationnelle', 'L’Art du Désamorçage', 'Alliance Durable', 'Médiation professionnelle et institutionnelle', 'Formation CNV et gestion des conflits'],
  'tous' => ['Conférence ou intervention', 'Accompagnement individuel', 'Autre'],
];

function refs_dir(string $sub = ''): string {
  $d = LP_ROOT . '/references' . ($sub !== '' ? '/' . $sub : '');
  if (!is_dir($d)) @mkdir($d, 0755, true);
  return $d;
}

/* Nom affiché selon le choix de l'auteur. */
function refs_auteur(array $t): string {
  $nom = line((string)($t['auteur'] ?? ''));
  if (($t['affichage'] ?? '') === 'fonction' || $nom === '') return '';
  if (($t['affichage'] ?? '') === 'initiale') {
    $p = preg_split('/\s+/u', $nom);
    if (count($p) > 1) { $last = array_pop($p); return implode(' ', $p) . ' ' . mb_strtoupper(mb_substr($last, 0, 1)) . '.'; }
  }
  return $nom;
}
function refs_qualite(array $t): string {
  $q = implode(', ', array_filter([line((string)($t['fonction'] ?? '')), line((string)($t['organisation'] ?? ''))]));
  return $q;
}
function refs_pole_de_contexte(string $contexte): string {
  foreach (REFS_OFFRES as $pole => $offres) if (in_array($contexte, $offres, true)) return $pole;
  return 'tous';
}

/* Contenus publiés (un témoignage n'est jamais publié sans accord recueilli ; un logo jamais sans accord de l'organisation). */
function refs_logos_publies(): array {
  return db_all("SELECT * FROM ref_logos WHERE publie=1 AND accord=1 AND fichier IS NOT NULL AND fichier<>'' ORDER BY ordre, nom");
}
function refs_temoignages_publies(): array {
  return db_all("SELECT * FROM ref_temoignages WHERE etat='publie' AND accord<>'' ORDER BY une DESC, ordre, COALESCE(date_temoignage, created_at) DESC, id DESC");
}
function refs_logo_src(array $l): string { return '/references/logos/' . rawurlencode(basename((string)$l['fichier'])); }

/* Taille d'affichage équilibrée : surface constante, bornée en largeur et en hauteur. */
function refs_logo_taille(int $w, int $h, float $aire = 4200, int $maxW = 168, int $maxH = 60): array {
  if ($w <= 0 || $h <= 0) return [120, 48];
  $r = $w / $h; $dw = sqrt($aire * $r); $dh = $dw / $r;
  if ($dw > $maxW) { $dw = $maxW; $dh = $dw / $r; }
  if ($dh > $maxH) { $dh = $maxH; $dw = $dh * $r; }
  return [(int)round($dw), (int)round($dh)];
}

/* Flux public lu par l'accueil et les pages des pôles : réécrit à chaque modification dans le tableau de bord. */
function refs_publier(): void {
  $logos = array_map(fn($l) => ['nom' => $l['nom'], 'src' => refs_logo_src($l), 'w' => (int)$l['largeur'], 'h' => (int)$l['hauteur'],
    'pole' => $l['pole'], 'sombre' => $l['fond'] === 'sombre', 'lien' => (string)$l['lien']], refs_logos_publies());
  $tems = array_map(fn($t) => ['texte' => $t['texte'], 'auteur' => refs_auteur($t), 'qualite' => refs_qualite($t), 'contexte' => (string)$t['contexte'],
    'pole' => $t['pole'], 'une' => (int)$t['une'] === 1, 'date' => $t['date_temoignage'] ? substr((string)$t['date_temoignage'], 0, 7) : ''], refs_temoignages_publies());
  $json = json_encode(['maj' => date('c'), 'page' => '/references/', 'logos' => $logos, 'temoignages' => $tems], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
  $f = refs_dir() . '/flux.json'; $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (@file_put_contents($tmp, $json, LOCK_EX) === false || !@rename($tmp, $f)) { @unlink($tmp); log_error('Références : écriture du flux impossible (' . $f . ').'); throw new DomainException('Enregistré, mais la mise à jour du site a échoué : réessayez dans un instant.'); }
}

/* Dépôt d'un logo : vérifié, recadré sur son contenu, réduit et réencodé (aucun fichier déposé n'est servi tel quel). */
function refs_logo_depot(string $field): ?array {
  $f = $_FILES[$field] ?? null;
  $err = (int)($f['error'] ?? UPLOAD_ERR_NO_FILE);
  if (!$f || $err === UPLOAD_ERR_NO_FILE) return null;
  if (in_array($err, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new DomainException('Logo trop lourd pour l’hébergement (' . ini_get('upload_max_filesize') . ' au plus).');
  if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name'])) throw new DomainException('Le logo n’a pas pu être reçu. Réessayez.');
  if ((int)$f['size'] > 6 * 1024 * 1024) throw new DomainException('Logo trop lourd : 6 Mo au plus.');
  $info = @getimagesize((string)$f['tmp_name']);
  $types = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
  if (!$info || !isset($types[$info[2]])) throw new DomainException('Format non accepté : déposez le logo en PNG (fond transparent de préférence), JPEG ou WebP. Les fichiers SVG ne sont pas acceptés, pour des raisons de sécurité.');
  [$w, $h] = $info;
  if ($w < 60 || $h < 20) throw new DomainException('Logo trop petit : 200 pixels de large au moins pour un rendu net.');
  if ($w * $h > 36000000) throw new DomainException('Image trop grande : 6 000 × 6 000 pixels au plus.');
  $dir = refs_dir('logos'); $base = date('Ymd') . '-' . bin2hex(random_bytes(6));
  if (!function_exists('imagecreatefromstring')) {
    $file = $base . '.' . $types[$info[2]];
    if (!move_uploaded_file((string)$f['tmp_name'], $dir . '/' . $file)) throw new DomainException('Enregistrement du logo impossible.');
    return [$file, $w, $h];
  }
  $src = @imagecreatefromstring((string)file_get_contents((string)$f['tmp_name']));
  if (!$src) throw new DomainException('Image illisible : enregistrez-la de nouveau en PNG et réessayez.');
  if (!imageistruecolor($src)) imagepalettetotruecolor($src);
  imagealphablending($src, false); imagesavealpha($src, true);
  $crop = @imagecropauto($src, IMG_CROP_SIDES);
  if ($crop && imagesx($crop) >= 40 && imagesy($crop) >= 12) { imagedestroy($src); $src = $crop; }
  $w = imagesx($src); $h = imagesy($src);
  $s = min(1, 600 / $w, 240 / $h); $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
  $dst = imagecreatetruecolor($nw, $nh);
  imagealphablending($dst, false); imagesavealpha($dst, true);
  imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));
  imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
  $ok = function_exists('imagewebp') ? imagewebp($dst, $dir . '/' . ($file = $base . '.webp'), 90) : imagepng($dst, $dir . '/' . ($file = $base . '.png'), 9);
  imagedestroy($src); imagedestroy($dst);
  if (!$ok) throw new DomainException('Enregistrement du logo impossible.');
  return [$file, $nw, $nh];
}
function refs_logo_supprimer_fichier(?string $file): void {
  $file = basename((string)$file);
  if ($file !== '' && preg_match('/^[0-9]{8}-[0-9a-f]{12}\.(webp|png|jpg)$/', $file)) @unlink(refs_dir('logos') . '/' . $file);
}

/* Rendu HTML commun (page /references/) — même structure que le rendu de l'accueil (site.js). */
function refs_logo_html(array $l): string {
  [$dw, $dh] = refs_logo_taille((int)$l['largeur'], (int)$l['hauteur']);
  $img = '<img src="' . h(refs_logo_src($l)) . '" alt="' . h($l['nom']) . '" width="' . $dw . '" height="' . $dh . '" loading="lazy" decoding="async">';
  $cls = 'lpr-logo' . ($l['fond'] === 'sombre' ? ' lpr-logo-sombre' : '');
  $lien = (string)$l['lien'];
  return '<li class="' . $cls . '" data-item data-cat="' . h($l['pole'] === 'tous' ? 'p1 p2' : $l['pole']) . '">'
    . ($lien !== '' ? '<a href="' . h($lien) . '" target="_blank" rel="noopener nofollow" title="' . h($l['nom']) . '">' . $img . '</a>' : $img) . '</li>';
}
function refs_tem_html(array $t, bool $grand = false): string {
  $auteur = refs_auteur($t); $q = refs_qualite($t);
  $pole = $t['pole'] === 'p1' ? '<span class="lpr-pole" title="Art oratoire et leadership">I</span>' : ($t['pole'] === 'p2' ? '<span class="lpr-pole" title="Médiation et gestion des conflits">II</span>' : '');
  $date = $t['date_temoignage'] ? MOIS[(int)substr((string)$t['date_temoignage'], 5, 2)] . ' ' . substr((string)$t['date_temoignage'], 0, 4) : '';
  $ctx = implode(' · ', array_filter([(string)$t['contexte'], $date]));
  $paras = implode('', array_map(fn($p) => '<p>' . h($p) . '</p>', array_filter(array_map('trim', preg_split('/\n\s*\n/', (string)$t['texte'])))));
  return '<figure class="lpr-tem' . ($grand ? ' lpr-tem-grand' : '') . '" data-item data-cat="' . h($t['pole'] === 'tous' ? 'p1 p2' : $t['pole']) . '">'
    . '<span class="lpr-q" aria-hidden="true">«</span><blockquote>' . $paras . '</blockquote>'
    . '<figcaption>' . ($auteur !== '' ? '<strong>' . h($auteur) . '</strong>' : '') . ($q !== '' ? '<span>' . h($q) . '</span>' : '')
    . ($ctx !== '' || $pole ? '<em>' . $pole . h($ctx) . '</em>' : '') . '</figcaption></figure>';
}
