<?php
/* Modèles des courriels automatiques. Ton sobre, vouvoiement, signature Lead Performant. */

function email_wrap(string $titre, string $corps): string {
  return '<!doctype html><html lang="fr"><body style="margin:0;background:#F4F2EE;font-family:Georgia,serif;color:#1F2A37">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px">'
    . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#FFFFFF;border-top:4px solid #21406A">'
    . '<tr><td style="padding:24px 32px 6px"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="padding-right:12px"><img src="' . h(url('/assets/img/logo-lead-performant.png')) . '" width="56" height="54" alt="Lead Performant" style="display:block;border:0"></td><td style="font-family:Arial,sans-serif;font-size:12px;letter-spacing:2px;color:#21406A;font-weight:bold">LEAD PERFORMANT</td></tr></table></td></tr>'
    . '<tr><td style="padding:6px 32px 0"><h1 style="margin:0 0 14px;font-weight:normal;font-size:24px;color:#21406A">' . h($titre) . '</h1></td></tr>'
    . '<tr><td style="padding:0 32px 26px;font-size:15px;line-height:1.6">' . $corps
    . '<p style="margin:26px 0 0">Bien cordialement,<br>Abdou Fleur<br><span style="color:#6B7280">Lead Performant · Art oratoire et leadership<br>' . h(ORG['tel']) . ' · <a href="' . h(url('/')) . '" style="color:#21406A">www.leadperformant.com</a></span></p>'
    . '</td></tr><tr><td style="padding:14px 32px;background:#FBFAF7;font-family:Arial,sans-serif;font-size:11px;color:#6B7280">' . h(ORG['entite']) . ' · SIRET ' . h(ORG['siret']) . '</td></tr>'
    . '</table></td></tr></table></body></html>';
}
function btn(string $href, string $label): string {
  return '<p style="margin:22px 0"><a href="' . h($href) . '" style="display:inline-block;background:#21406A;color:#FFFFFF;text-decoration:none;padding:13px 24px;border-radius:6px;font-family:Arial,sans-serif;font-weight:bold;font-size:14px">' . h($label) . '</a></p>';
}
function p_(string $s): string { return '<p style="margin:0 0 12px">' . $s . '</p>'; }
function bloc_session_html(array $s): string {
  $p = programme((int)$s['programme_id']);
  $lieu = $s['modalite'] === 'distance' ? 'À distance, en visioconférence' : ($s['lieu'] ?: param('parole_lieu_defaut'));
  return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 16px;border-left:3px solid #C9A227;padding-left:12px"><tr><td style="padding:2px 12px;font-size:14px">'
    . '<strong>' . h($p['titre']) . '</strong><br>' . h(periode_fr($s['date_debut'], $s['date_fin'])) . '<br>' . h(heure_fr($s['heure_debut']) . ' à ' . heure_fr($s['heure_fin'])) . ' · ' . h($lieu) . '</td></tr></table>';
}
function dest_inscription(array $insc): array {
  if ($insc['type'] === 'entreprise') { $o = organisation((int)$insc['organisation_id']); return [$o['contact_email'], $o['contact_nom']]; }
  $c = client((int)$insc['client_id']); return [$c['email'], $c['prenom'] ?: client_nom($c)];
}
function participants(array $insc): array {
  if ($insc['type'] === 'particulier') { $c = client((int)$insc['client_id']); return [['nom' => client_nom($c), 'email' => $c['email']]]; }
  $list = json_decode((string)$insc['participants'], true) ?: [];
  return array_values(array_filter($list, fn($x) => !empty($x['nom'])));
}
function doc_by_id(?int $id): ?array { return $id ? db_one('SELECT * FROM documents WHERE id=?', [$id]) : null; }
function admin_notifier(string $sujet, string $corps): void { mail_queue((string)cfg('mail.admin'), '[Plateforme] ' . $sujet, email_wrap($sujet, $corps)); }

function email_inscription_signee(array $insc): void {
  [$to, $nom] = dest_inscription($insc); $s = session_get((int)$insc['session_id']);
  $ech = db_all('SELECT * FROM echeances WHERE inscription_id=? ORDER BY id', [$insc['id']]);
  $rows = implode('', array_map(fn($e) => '<li>' . h($e['libelle']) . ' : ' . h(eur((int)$e['montant'])) . '</li>', $ech));
  $corps = p_('Bonjour ' . h($nom) . ',') . p_('Votre inscription est enregistrée et votre place est réservée pour la formation suivante :') . bloc_session_html($s)
    . p_('Vous trouverez ci-joint votre contrat de formation signé, ainsi que le programme. <strong>Vous disposez de dix jours, jusqu’au ' . h(date_fr((string)$insc['retractation_fin'])) . ' inclus, pour vous rétracter sans frais.</strong> Aucun paiement ne vous est demandé pendant ce délai.')
    . p_('Votre échéancier :') . '<ul style="margin:0 0 14px;padding-left:20px">' . $rows . '</ul>'
    . p_('À l’issue du délai, vous recevrez le lien de paiement sécurisé de l’acompte.')
    . btn(acces_url((int)$insc['client_id']), 'Mon espace : documents et paiements')
    . p_('<span style="font-size:13px;color:#6B7280">Pour vous rétracter : <a href="' . h(url('/retractation/?t=' . $insc['token'])) . '" style="color:#6B7280">je renonce à mon inscription</a>.</span>');
  $pieces = [doc_piece(doc_by_id((int)$insc['contrat_doc']))];
  mail_queue($to, 'Votre inscription · ' . programme((int)$s['programme_id'])['titre'], email_wrap('Votre place est réservée', $corps), $pieces, 'signee-' . $insc['id']);
  admin_notifier('Nouvelle inscription', p_(h(client_nom(client((int)$insc['client_id'])))) . bloc_session_html($s) . p_('Contrat signé ; délai de rétractation jusqu’au ' . h(date_fr((string)$insc['retractation_fin'])) . '.'));
}

function email_retractation(array $insc): void {
  [$to, $nom] = dest_inscription($insc);
  mail_queue($to, 'Confirmation de votre rétractation', email_wrap('Votre rétractation est enregistrée',
    p_('Bonjour ' . h($nom) . ',') . p_('Nous vous confirmons la prise en compte de votre rétractation. Votre inscription est annulée, sans frais : aucun paiement ne vous sera demandé.') . p_('Nous restons à votre disposition pour une prochaine session.')), [], 'retract-' . $insc['id']);
  admin_notifier('Rétractation', p_(h(client_nom(client((int)$insc['client_id'])))) . bloc_session_html(session_get((int)$insc['session_id'])));
}

function email_echeance(array $e, string $cle): void {
  $insc = inscription((int)$e['inscription_id']); [$to, $nom] = dest_inscription($insc); $s = session_get((int)$insc['session_id']);
  $intro = str_starts_with($e['libelle'], 'Acompte') ? 'Votre délai de rétractation est arrivé à son terme. Pour confirmer définitivement votre place, merci de régler l’acompte de ' . h(eur((int)$e['montant'])) . '.'
    : 'Conformément à votre échéancier, voici le lien de paiement de l’échéance « ' . h($e['libelle']) . ' », d’un montant de ' . h(eur((int)$e['montant'])) . '.';
  $limite = $e['expire_at'] ? p_('Ce lien est valable jusqu’au ' . h(date_fr((string)$e['expire_at'])) . ' à ' . date('H:i', ts((string)$e['expire_at'])) . '.') : '';
  mail_queue($to, 'Paiement · ' . $e['libelle'], email_wrap('Lien de paiement', p_('Bonjour ' . h($nom) . ',') . p_($intro) . bloc_session_html($s) . btn(payer_echeance_url($e), 'Payer ' . eur((int)$e['montant'])) . $limite . p_('<span style="font-size:13px;color:#6B7280">Paiement par carte bancaire sur la page sécurisée de Stripe. Aucune donnée de carte n’est conservée par Lead Performant.</span>')), [], $cle);
}

function email_confirmation_definitive(array $insc, array $f): void {
  [$to, $nom] = dest_inscription($insc); $s = session_get((int)$insc['session_id']);
  $conv = doc_convocation($insc, client_nom(client((int)$insc['client_id'])));
  mail_queue($to, 'Votre place est confirmée', email_wrap('Votre place est confirmée',
    p_('Bonjour ' . h($nom) . ',') . p_('Nous avons bien reçu votre acompte. Votre place est définitivement confirmée.') . bloc_session_html($s)
    . p_('Vous trouverez ci-joint votre convocation et votre facture. Le solde sera réglé en deux fois, le premier et le dernier jour de la formation ; vous recevrez les liens le moment venu.')
    . btn(acces_url((int)$insc['client_id']), 'Mon espace')), [doc_piece($conv), doc_piece(doc_by_id((int)$f['doc_id']))], 'confirm-' . $insc['id']);
  admin_notifier('Acompte reçu', p_(h(client_nom(client((int)$insc['client_id']))) . ' · ' . h(eur((int)$f['montant']))) . bloc_session_html($s));
}

function email_recu_paiement(array $insc, array $e, array $f): void {
  [$to, $nom] = dest_inscription($insc);
  mail_queue($to, 'Paiement reçu · facture ' . $f['numero'], email_wrap('Paiement reçu', p_('Bonjour ' . h($nom) . ',') . p_('Nous accusons réception de votre paiement de ' . h(eur((int)$e['montant'])) . ' (' . h($e['libelle']) . '). Votre facture est jointe.')), [doc_piece(doc_by_id((int)$f['doc_id']))], 'recu-' . $e['id']);
}

function email_attente_confirmation(int $sid, array $d): void {
  $s = session_get($sid);
  mail_queue($d['email'], 'Liste d’attente', email_wrap('Vous êtes sur la liste d’attente', p_('Bonjour ' . h($d['prenom']) . ',') . p_('Vous êtes inscrit(e) sur la liste d’attente de la session suivante :') . bloc_session_html($s) . p_('Si une place se libère, vous recevrez une invitation par courriel, valable 48 heures. Les invitations sont adressées dans l’ordre d’arrivée.')));
}
function email_attente_invitation(array $a): void {
  $s = session_get((int)$a['session_id']);
  mail_queue($a['email'], 'Une place s’est libérée', email_wrap('Une place vous attend', p_('Bonjour ' . h($a['prenom']) . ',') . p_('Une place vient de se libérer pour la session suivante :') . bloc_session_html($s) . p_('Elle vous est réservée jusqu’au ' . h(date_fr((string)$a['expire_at'])) . ' à ' . date('H:i', ts((string)$a['expire_at'])) . '.') . btn(url('/inscription/?s=' . $s['slug'] . '&inv=' . $a['token']), 'Je m’inscris')), [], 'invit-' . $a['id']);
}

function email_demande_entreprise(array $i): void {
  $o = organisation((int)$i['organisation_id']); $s = session_get((int)$i['session_id']);
  mail_queue($o['contact_email'], 'Votre demande d’inscription', email_wrap('Demande bien reçue', p_('Bonjour ' . h($o['contact_nom']) . ',') . p_('Nous avons bien reçu votre demande pour ' . (int)$i['places'] . ' participant(s) :') . bloc_session_html($s) . p_('Les places sont bloquées jusqu’au ' . h(date_fr((string)$i['expire_at'])) . '. Vous recevrez sous deux jours ouvrés le devis et la convention de formation, à signer en ligne.')), [], 'demande-' . $i['id']);
  admin_notifier('Demande entreprise à chiffrer', p_(h($o['raison_sociale']) . ' · ' . (int)$i['places'] . ' place(s)') . bloc_session_html($s) . btn(url('/gestion/?v=inscription&id=' . $i['id']), 'Établir le devis'));
}
function email_devis(array $insc, array $docs): void {
  $o = organisation((int)$insc['organisation_id']); $s = session_get((int)$insc['session_id']);
  mail_queue($o['contact_email'], 'Devis et convention de formation', email_wrap('Votre devis et votre convention', p_('Bonjour ' . h($o['contact_nom']) . ',') . p_('Veuillez trouver ci-joint le devis et la convention de formation pour :') . bloc_session_html($s) . p_('Montant : <strong>' . h(eur((int)$insc['prix_total'])) . '</strong> pour ' . (int)$insc['places'] . ' participant(s). ' . h(ORG['tva']) . '.') . p_('Pour confirmer, signez la convention en ligne avant le ' . h(date_fr((string)$insc['expire_at'])) . ' :') . btn(url('/convention/?t=' . $insc['token']), 'Signer la convention')), array_map('doc_piece', $docs), 'devis-' . $insc['id'] . '-' . $insc['prix_total']);
}
function email_convention_signee(array $insc, array $f, array $e, array $conv): void {
  $o = organisation((int)$insc['organisation_id']); $s = session_get((int)$insc['session_id']);
  $pay = $e['mode'] === 'virement' ? p_('Vous avez choisi le règlement par virement : les coordonnées bancaires figurent sur demande à ' . h(ORG['email']) . ' ; merci de rappeler le numéro de facture ' . h($f['numero']) . '.') : btn(payer_echeance_url($e), 'Régler ' . eur((int)$e['montant']) . ' par carte');
  mail_queue($o['contact_email'], 'Convention signée · facture ' . $f['numero'], email_wrap('Inscription confirmée', p_('Bonjour ' . h($o['contact_nom']) . ',') . p_('La convention est signée : les places sont confirmées.') . bloc_session_html($s) . p_('Vous trouverez ci-joint la convention signée et la facture.') . $pay . p_('Merci de nous communiquer, au plus tard sept jours avant la formation, les noms et adresses électroniques des participants s’ils ne figurent pas encore dans votre demande : chacun recevra sa convocation.')), [doc_piece($conv), doc_piece(doc_by_id((int)$f['doc_id']))], 'conv-' . $insc['id']);
  admin_notifier('Convention signée', p_(h($o['raison_sociale']) . ' · ' . h(eur((int)$insc['prix_total']))) . bloc_session_html($s));
  foreach (participants($insc) as $k => $pt) {
    if (empty($pt['email'])) continue;
    $d = doc_convocation($insc, $pt['nom']);
    mail_queue($pt['email'], 'Convocation · ' . programme((int)$s['programme_id'])['titre'], email_wrap('Votre convocation', p_('Bonjour ' . h($pt['nom']) . ',') . p_('Votre employeur vous a inscrit(e) à la formation suivante :') . bloc_session_html($s) . p_('Votre convocation est jointe.')), [doc_piece($d)], 'convoc-' . $insc['id'] . '-' . $k);
  }
}

function email_session_annulee(array $insc, array $r, string $motif): void {
  [$to, $nom] = dest_inscription($insc); $s = session_get((int)$insc['session_id']);
  $remb = $r['carte'] + $r['virement'] > 0 ? p_('Les sommes versées, soit ' . h(eur($r['carte'] + $r['virement'])) . ', vous sont intégralement remboursées' . ($r['carte'] ? ' sur la carte utilisée (délai bancaire de cinq à dix jours)' : '') . ($r['virement'] ? ' ; le remboursement par virement vous sera adressé sous quatorze jours' : '') . '.') : p_('Aucun paiement n’avait encore été effectué : vous n’avez rien à faire.');
  mail_queue($to, 'Annulation de la session', email_wrap('Session annulée', p_('Bonjour ' . h($nom) . ',') . p_('Nous sommes au regret de vous informer de l’annulation de la session suivante' . ($motif ? ' (' . h($motif) . ')' : '') . ' :') . bloc_session_html($s) . $remb . p_('Nous vous prions de nous excuser pour ce contretemps et vous tiendrons informé(e) des prochaines dates.')), [], 'annul-' . $insc['id']);
}
function email_session_reportee(array $insc, array $cible, string $motif): void {
  [$to, $nom] = dest_inscription($insc); $s = session_get((int)$insc['session_id']);
  mail_queue($to, 'Report de la session · votre choix', email_wrap('La session est reportée', p_('Bonjour ' . h($nom) . ',') . p_('La session à laquelle vous êtes inscrit(e) est reportée' . ($motif ? ' (' . h($motif) . ')' : '') . ' :') . bloc_session_html($s) . p_('Nouvelle date proposée :') . bloc_session_html($cible)
    . btn(url('/report/?t=' . $insc['token'] . '&c=garder'), 'Je garde ma place sur la nouvelle date') . p_('<a href="' . h(url('/report/?t=' . $insc['token'] . '&c=rembourser')) . '" style="color:#21406A">Je préfère être remboursé(e) intégralement</a>')), [], 'report-' . $insc['id'] . '-' . $cible['id']);
}
function email_rembourse(array $insc, array $r): void {
  [$to, $nom] = dest_inscription($insc);
  mail_queue($to, 'Votre remboursement', email_wrap('Remboursement enregistré', p_('Bonjour ' . h($nom) . ',') . p_('Votre demande est prise en compte. ' . ($r['carte'] + $r['virement'] > 0 ? 'Le remboursement de ' . h(eur($r['carte'] + $r['virement'])) . ' est en cours ; un avoir vous est adressé.' : 'Aucun paiement n’avait été effectué.'))), [], 'remb-' . $insc['id']);
}

function email_commande(array $c, array $f): void {
  $cl = client((int)$c['client_id']); $p = produit((int)$c['produit_id']);
  $corps = p_('Bonjour ' . h($cl['prenom']) . ',') . p_('Merci pour votre commande : <strong>' . h($p['titre']) . '</strong>. Votre facture est jointe.');
  $pieces = [doc_piece(doc_by_id((int)$f['doc_id']))];
  if (in_array($p['type'], ['ebook', 'guide'], true)) {
    $corps .= p_('Votre fichier est disponible dès maintenant, pendant ' . (int)param('acces_telechargement_jours', 30) . ' jours (' . (int)param('max_telechargements', 5) . ' téléchargements).') . btn(url('/telecharger/?t=' . $c['token']), 'Télécharger');
  } elseif ($p['type'] === 'visio') {
    $cr = db_one('SELECT * FROM creneaux WHERE id=?', [$c['creneau_id']]);
    $lien = $cr['lien_visio'] ?: $p['lien_visio'];
    $corps .= p_('Votre rendez-vous : <strong>' . h(date_fr($cr['debut'], true)) . ' à ' . h(heure_fr(date('H:i', ts($cr['debut'])))) . '</strong>, durée ' . (int)$p['duree_minutes'] . ' minutes.') . ($lien ? btn($lien, 'Lien de visioconférence') : p_('Le lien de connexion vous sera adressé la veille.')) . p_('L’invitation de calendrier est jointe.');
    $pieces[] = ['nom' => 'rendez-vous.ics', 'type' => 'text/calendar; charset=UTF-8; method=PUBLISH', 'contenu' => ics($cr, $p, $lien)];
  } else {
    $corps .= p_('Pour que nous puissions vous répondre, déposez votre question et vos documents dans votre espace. Réponse écrite sous ' . (int)$p['delai_jours'] . ' jours ouvrés à compter du dépôt.') . btn(url('/mon-acces/?t=' . $cl['acces_token'] . '&c=' . $c['id']), 'Déposer ma question');
  }
  mail_queue($cl['email'], 'Votre commande · ' . $p['titre'], email_wrap('Merci pour votre commande', $corps), $pieces, 'commande-' . $c['id']);
  admin_notifier('Vente boutique', p_(h($p['titre']) . ' · ' . h(eur((int)$c['montant'])) . ' · ' . h(client_nom($cl))));
}
function ics(array $cr, array $p, string $lien): string {
  $f = fn($d) => gmdate('Ymd\THis\Z', ts($d));
  return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Lead Performant//FR\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:creneau-{$cr['id']}@leadperformant.com\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART:" . $f($cr['debut']) . "\r\nDTEND:" . $f($cr['fin']) . "\r\nSUMMARY:" . str_replace([',', ';'], ['\\,', '\\;'], $p['titre'] . ' · Lead Performant') . "\r\nLOCATION:" . str_replace([',', ';'], ['\\,', '\\;'], $lien ?: 'Visioconférence') . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}
