<?php
/* Lead Performant — réception des demandes (formulaire Contact et parcours Parole d'Impact).
   Envoie la demande au cabinet et un accusé de réception au demandeur.
   Réponse JSON : {"ok":true} ou {"ok":false,"error":"…"} */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$DESTINATAIRE = 'leadperformant@gmail.com';
$EXPEDITEUR   = 'site@leadperformant.com';    // adresse du domaine, déjà utilisée par l'ancien formulaire

function fin($ok, $err = '') { echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $err]); exit; }
function champ($k, $max = 4000) {
  $v = isset($_POST[$k]) ? (string)$_POST[$k] : '';
  $v = trim(str_replace(["\r\n", "\r"], "\n", $v));
  return mb_substr($v, 0, $max, 'UTF-8');
}
function ligne($s) { return preg_replace('/[\r\n]+/', ' ', $s); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); fin(false, 'methode'); }

/* Pot de miel anti-robots : le champ caché doit rester vide */
if (champ('site_web') !== '') fin(true);

/* Limitation simple : une demande toutes les 30 secondes par adresse IP */
$ip = $_SERVER['REMOTE_ADDR'] ?? '0';
$verrou = sys_get_temp_dir() . '/lp_' . md5($ip);
if (is_file($verrou) && time() - filemtime($verrou) < 30) { http_response_code(429); fin(false, 'trop-rapide'); }
@touch($verrou);

$nom     = ligne(champ('nom', 120));
$email   = ligne(champ('email', 160));
$message = champ('message', 6000);
if ($nom === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || champ('consentement') !== 'oui') { http_response_code(422); fin(false, 'champs'); }

$typeBrut = champ('type', 60);
$type = $typeBrut === 'parcours-parole-d-impact' ? 'Parcours Parole d\'Impact' : ($typeBrut === 'diagnostic' ? 'Diagnostic d\'aisance orale' : ($typeBrut === 'programme' ? 'Fiche programme' : 'Formulaire de contact'));
$lignes = [
  'Origine'        => $type,
  'Nom'            => $nom,
  'Courriel'       => $email,
  'Téléphone'      => ligne(champ('telephone', 40)),
  'Fonction'       => ligne(champ('fonction', 80)),
  'Organisation'   => ligne(champ('organisation', 160)),
  'Profil'         => ligne(champ('profil', 120)),
  'Besoin'         => ligne(champ('besoin', 120)),
  'Programme'      => ligne(champ('programme', 160)),
  'Disponibilités' => ligne(champ('disponibilites', 120)),
];
$corps = "Nouvelle demande reçue sur leadperformant.com\n\n";
foreach ($lignes as $k => $v) { if ($v !== '') $corps .= str_pad($k, 16) . ': ' . $v . "\n"; }
$corps .= "\nMessage\n-------\n" . $message . "\n\nReçu le " . date('d/m/Y à H:i') . "\n";

$enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };
$entetes = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n"
         . "From: " . $enc('Site Lead Performant') . " <$EXPEDITEUR>\r\nReply-To: " . $enc($nom) . " <$email>\r\n";

$sujet = $enc('Nouvelle demande · ' . $type . ' · ' . $nom);
$envoye = mail($DESTINATAIRE, $sujet, $corps, $entetes, '-f' . $EXPEDITEUR);
if (!$envoye) { http_response_code(500); fin(false, 'envoi'); }

/* Accusé de réception */
$prenom = explode(' ', $nom)[0];
if ($typeBrut === 'diagnostic') {
  $ar = "Bonjour $prenom,\n\nVoici la restitution de votre diagnostic d'aisance orale, réalisé sur leadperformant.com.\n\n"
      . $message . "\n\n"
      . "Ce diagnostic traduit vos propres réponses : il oriente votre travail, il ne mesure pas votre éloquence.\n\n"
      . "Pour en parler trente minutes, sans engagement : répondez simplement à ce message, ou appelez le 06 62 57 92 74.\n\n"
      . "Bien cordialement,\n\nAbdou Fleur\nLead Performant\nArt oratoire et leadership\nwww.leadperformant.com\n";
  $entetesAR = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n"
             . "From: " . $enc('Lead Performant') . " <$EXPEDITEUR>\r\nReply-To: $DESTINATAIRE\r\n";
  @mail($email, $enc('Votre diagnostic d\'aisance orale · Lead Performant'), $ar, $entetesAR, '-f' . $EXPEDITEUR);
  fin(true);
}
$ar = "Bonjour $prenom,\n\nNous avons bien reçu votre demande et vous en remercions.\n\n"
    . "Nous vous répondons sous deux jours ouvrés pour convenir d'un premier échange de trente minutes, gratuit et sans engagement.\n\n"
    . "Pour toute précision, vous pouvez répondre directement à ce message ou nous appeler au 06 62 57 92 74.\n\n"
    . "Bien cordialement,\n\nLead Performant\nArt oratoire et leadership\nwww.leadperformant.com\n";
$entetesAR = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n"
           . "From: " . $enc('Lead Performant') . " <$EXPEDITEUR>\r\nReply-To: $DESTINATAIRE\r\n";
@mail($email, $enc('Votre demande a bien été reçue · Lead Performant'), $ar, $entetesAR, '-f' . $EXPEDITEUR);

fin(true);