<?php
/* Documents PDF : contrat de formation, devis, convention, facture et avoir, convocation, attestation. */

const ORG = [
  'nom' => 'Lead Performant',
  'entite' => 'Abdou Fleur, entrepreneur individuel (micro-entreprise), nom commercial Lead Performant',
  'adresse' => '4 rue des Edelweiss, 91270 Vigneux-sur-Seine',
  'siret' => '987 979 044 00011',
  'tel' => '06 62 57 92 74',
  'email' => 'leadperformant@gmail.com',
  'tva' => 'TVA non applicable, article 293 B du Code général des impôts',
];
function mention_nda(): string {
  $n = trim((string)param('nda'));
  return $n === '' ? '' : 'Déclaration d’activité enregistrée sous le numéro ' . $n . ' auprès du préfet de région ' . (param('nda_region') === 'Île-de-France' ? 'd’Île-de-France' : param('nda_region')) . '. Cet enregistrement ne vaut pas agrément de l’État.';
}

function pdf_base(string $title, string $kicker): Pdf {
  $pdf = new Pdf($title);
  $pdf->setLogo(LP_ROOT . '/assets/img/logo-lead-performant.png') || $pdf->setLogo(LP_ROOT . '/assets/img/embleme-lead-performant.png');
  $pdf->onPage = function (Pdf $p, int $n) use ($title) {
    $p->rect(0, 0, $p->W, 6, '21406A'); $p->rect(0, 6, $p->W, 2, 'C9A227');
    if ($p->hasLogo()) { $w = $p->logo($p->ml, 14, 28); $p->text($p->ml + $w + 8, 33, 'LEAD PERFORMANT', 9, true, '21406A'); }
    else $p->text($p->ml, 36, 'LEAD PERFORMANT', 9, true, '21406A');
    $p->textRight($p->W - $p->mr, 36, $title, 8.5, false, '6B7280');
    $p->line($p->ml, 48, $p->W - $p->mr, 48);
    $foot = ORG['entite'] . ' · ' . ORG['adresse'] . ' · SIRET ' . ORG['siret'];
    $p->line($p->ml, $p->H - 52, $p->W - $p->mr, $p->H - 52);
    $p->text($p->ml, $p->H - 40, $foot, 7, false, '6B7280');
    $nda = mention_nda();
    $p->text($p->ml, $p->H - 30, $nda !== '' ? $nda : ORG['tva'], 7, false, '6B7280');
    $p->textRight($p->W - $p->mr, $p->H - 20, (string)$n, 7.5, false, '6B7280');
    $p->y = $p->mt;
  };
  $pdf->page();
  $pdf->para($kicker, 9, true, '2F6F73', 1.3, null, null, 2);
  $pdf->para($title, 20, false, '21406A', 1.25, null, null, 10);
  return $pdf;
}

function doc_save(Pdf $pdf, string $type, string $nom, array $links = []): array {
  $bin = $pdf->output();
  $rel = 'docs/' . date('Y/m') . '/' . $type . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
  @mkdir(dirname(LP_STORAGE . '/' . $rel), 0750, true);
  file_put_contents(LP_STORAGE . '/' . $rel, $bin);
  $id = db_insert('documents', $links + ['type' => $type, 'chemin' => $rel, 'nom' => $nom, 'sha256' => hash('sha256', $bin), 'created_at' => now()]);
  return db_one('SELECT * FROM documents WHERE id=?', [$id]);
}
function doc_piece(array $doc): array { return ['chemin' => $doc['chemin'], 'nom' => $doc['nom']]; }

function bloc_parties(Pdf $pdf, string $titreClient, array $lignesClient): void {
  $pdf->h('Entre les soussignés');
  $pdf->para(ORG['entite'] . ', ' . ORG['adresse'] . ', SIRET ' . ORG['siret'] . ', ci-après « l’organisme de formation »,', 10);
  $pdf->para('et ' . $titreClient . ' : ' . implode(', ', array_filter($lignesClient)) . ',', 10);
}
function bloc_action(Pdf $pdf, array $s, array $p, int $participants = 1): void {
  $lieu = $s['modalite'] === 'distance' ? 'À distance, en visioconférence (' . param('visio_outil', 'visioconférence') . ')' : ($s['lieu'] ?: param('parole_lieu_defaut', ''));
  $pdf->table([[150, 'L'], [333.28, 'L']], ['Rubrique', 'Détail'], [
    ['Intitulé', $p['titre'] . ' : ' . $p['sous_titre']],
    ['Nature de l’action', 'Action de formation au sens de l’article L6313-1 du Code du travail, ayant pour objet l’acquisition et le perfectionnement de compétences.'],
    ['Dates', periode_fr($s['date_debut'], $s['date_fin'])],
    ['Horaires', heure_fr($s['heure_debut']) . ' à ' . heure_fr($s['heure_fin']) . ' chaque jour'],
    ['Durée', $p['duree_jours'] . ' jours, soit ' . $p['duree_heures'] . ' heures'],
    ['Lieu', $lieu],
    ['Effectif du groupe', 'De ' . $s['seuil'] . ' à ' . $s['capacite'] . ' participants' . ($participants > 1 ? ' ; ' . $participants . ' participants inscrits au titre du présent document' : '')],
    ['Public et prérequis', $p['public_vise'] . ' ' . $p['prerequis']],
    ['Formateur', $p['formateur']],
  ]);
  $pdf->h('Objectifs pédagogiques', 11);
  $pdf->para('À l’issue de la formation, le participant sera capable de :', 10);
  $pdf->bullets(array_filter(explode("\n", (string)$p['objectifs'])));
  $pdf->h('Programme et moyens pédagogiques', 11);
  $pdf->bullets(array_filter(explode("\n", (string)$p['programme'])));
  $pdf->para('Moyens pédagogiques : apports méthodologiques, exercices pratiques, mises en situation filmées, jeux de rôle, retours individualisés à l’aide d’une grille d’observation ; supports remis aux participants.', 10);
  $pdf->h('Suivi et évaluation', 11);
  $pdf->para('Feuilles d’émargement signées par demi-journée. ' . $p['evaluation'], 10);
}

function doc_contrat(array $insc): array {
  $s = session_get((int)$insc['session_id']); $p = programme((int)$s['programme_id']); $c = client((int)$insc['client_id']);
  $pdf = pdf_base('Contrat de formation professionnelle', 'Articles L6353-3 à L6353-7 du Code du travail');
  bloc_parties($pdf, 'le stagiaire', [client_nom($c), $c['fonction'] ? 'exerçant comme ' . $c['fonction'] : '', $c['email'], $c['telephone']]);
  $pdf->para('il est conclu le présent contrat de formation professionnelle, le stagiaire finançant lui-même sa formation.', 10);
  $pdf->h('Article 1 · Objet');
  $pdf->para('L’organisme de formation organise l’action de formation décrite ci-dessous, à laquelle le stagiaire s’inscrit à titre individuel.', 10);
  bloc_action($pdf, $s, $p);
  $pdf->h('Article 2 · Prix et modalités de paiement');
  $rows = []; foreach (echeancier((int)$insc['prix_total']) as $i => [$lib, $m]) $rows[] = [$lib, $i === 0 ? 'À l’issue du délai de rétractation' : ($i === 1 ? date_fr($s['date_debut']) : date_fr($s['date_fin'])), eur($m)];
  $rows[] = ['Prix total de la formation', '', eur((int)$insc['prix_total'])];
  $pdf->table([[220, 'L'], [163.28, 'L'], [100, 'R']], ['Échéance', 'Date', 'Montant'], $rows);
  $pdf->para(ORG['tva'] . '. Aucune somme n’est exigée avant l’expiration du délai de rétractation. À son terme, le stagiaire verse un acompte qui ne peut excéder 30 % du prix ; le solde est échelonné au fur et à mesure du déroulement de la formation (article L6353-6). Les paiements s’effectuent par carte bancaire au moyen des liens sécurisés adressés par courriel ; une facture est émise pour chaque paiement.', 10);
  $pdf->h('Article 3 · Délai de rétractation');
  $pdf->para('À compter de la signature du présent contrat, le stagiaire dispose d’un délai de dix jours pour se rétracter, sans frais et sans avoir à justifier de motif (article L6353-5). Il exerce ce droit en un clic grâce au lien reçu par courriel, ou par lettre recommandée avec avis de réception adressée à l’organisme. Délai ouvert jusqu’au ' . date_fr((string)$insc['retractation_fin']) . ' inclus.', 10);
  $pdf->h('Article 4 · Interruption de la formation');
  $pdf->para('Si, par suite de force majeure dûment reconnue, le stagiaire est empêché de suivre la formation, il peut rompre le contrat ; seules les prestations effectivement dispensées sont dues au prorata temporis de leur valeur prévue au contrat (article L6354-1). En cas d’abandon sans motif de force majeure, les prestations effectivement dispensées restent dues au prorata temporis. Les sommes versées au-delà sont remboursées sous trente jours.', 10);
  $pdf->h('Article 5 · Maintien, report ou annulation par l’organisme');
  $pdf->para('La session est maintenue lorsqu’elle réunit au moins ' . $s['seuil'] . ' participants dix jours avant son début. À défaut, l’organisme peut la reporter ou l’annuler ; le stagiaire choisit alors entre la nouvelle date proposée et le remboursement intégral des sommes versées, sous quatorze jours.', 10);
  $pdf->h('Article 6 · Documents remis et règlement intérieur');
  $pdf->para('Le stagiaire reconnaît avoir reçu, avant la signature, le programme de la formation, les conditions générales de vente et le règlement intérieur applicable aux stagiaires. Une attestation de fin de formation lui est remise à l’issue de l’action, sous réserve de son assiduité.', 10);
  $pdf->h('Article 7 · Données personnelles et différends');
  $pdf->para('Les données du stagiaire sont traitées pour l’exécution du présent contrat, conformément à la politique de confidentialité publiée sur www.leadperformant.com. En cas de différend, les parties recherchent une solution amiable avant toute action ; à défaut, les juridictions compétentes sont celles déterminées par la loi.', 10);
  $pdf->h('Signatures');
  $pdf->para('Fait à Vigneux-sur-Seine, par voie électronique.', 10);
  $pdf->table([[241.64, 'L'], [241.64, 'L']], ['Pour l’organisme de formation', 'Le stagiaire'], [[
    "Abdou Fleur, dirigeant de Lead Performant\nContrat émis le " . date_fr((string)$insc['signe_at']),
    'Signé électroniquement par ' . $insc['signature_nom'] . "\nle " . date('d/m/Y à H:i', ts((string)$insc['signe_at'])) . "\nAdresse IP : " . $insc['signature_ip'] . "\nRéférence : " . strtoupper(substr($insc['token'], 0, 10)),
  ]]);
  return doc_save($pdf, 'contrat', 'Contrat de formation - ' . $p['titre'] . '.pdf', ['inscription_id' => $insc['id']]);
}

function doc_devis(array $insc): array {
  $s = session_get((int)$insc['session_id']); $p = programme((int)$s['programme_id']); $o = organisation((int)$insc['organisation_id']);
  $pdf = pdf_base('Devis n° D-' . str_pad((string)$insc['id'], 5, '0', STR_PAD_LEFT), 'Proposition financière');
  $pdf->para('Destinataire : ' . $o['raison_sociale'] . ($o['siret'] ? ' (SIRET ' . $o['siret'] . ')' : '') . ', ' . $o['adresse'], 10);
  $pdf->para('À l’attention de ' . $o['contact_nom'] . ' · ' . $o['contact_email'], 10);
  $pdf->para('Émis le ' . date_fr(now()) . ' · valable jusqu’au ' . date_fr((string)$insc['expire_at']), 10, false, '4A5563');
  $unit = intdiv((int)$insc['prix_total'], max(1, (int)$insc['places']));
  $pdf->table([[243.28, 'L'], [70, 'R'], [85, 'R'], [85, 'R']], ['Désignation', 'Quantité', 'Prix unitaire', 'Total'], [
    [$p['titre'] . ', ' . periode_fr($s['date_debut'], $s['date_fin']) . ', ' . $p['duree_heures'] . ' heures, formation inter-entreprises', (string)$insc['places'], eur($unit), eur((int)$insc['prix_total'])],
    ['Total net à payer', '', '', eur((int)$insc['prix_total'])],
  ]);
  $pdf->para(ORG['tva'] . '. Règlement à réception de facture, par carte bancaire ou par virement. Ce devis est accompagné de la convention de formation à signer en ligne.', 10);
  return doc_save($pdf, 'devis', 'Devis - ' . $p['titre'] . '.pdf', ['inscription_id' => $insc['id']]);
}

function doc_convention(array $insc, bool $signee = false): array {
  $s = session_get((int)$insc['session_id']); $p = programme((int)$s['programme_id']); $o = organisation((int)$insc['organisation_id']);
  $pdf = pdf_base('Convention de formation professionnelle', 'Articles L6353-1 et L6353-2 du Code du travail');
  bloc_parties($pdf, 'l’entreprise ou l’organisation', [$o['raison_sociale'], $o['siret'] ? 'SIRET ' . $o['siret'] : '', $o['adresse'], 'représentée par ' . ($signee ? $insc['signature_nom'] : $o['contact_nom'])]);
  $pdf->h('Article 1 · Objet');
  $pdf->para('La présente convention a pour objet l’inscription des participants désignés ci-dessous à l’action de formation suivante.', 10);
  bloc_action($pdf, $s, $p, (int)$insc['places']);
  $parts = json_decode((string)$insc['participants'], true) ?: [];
  $pdf->h('Article 2 · Participants', 11);
  $pdf->bullets(array_map(fn($x) => trim(($x['nom'] ?? '') . ($x['fonction'] ?? '' ? ', ' . $x['fonction'] : '') . ($x['email'] ?? '' ? ' · ' . $x['email'] : '')) ?: 'À désigner au plus tard sept jours avant la formation', $parts ?: [[]]));
  $pdf->h('Article 3 · Dispositions financières');
  $pdf->para('Le coût de la formation s’élève à ' . eur((int)$insc['prix_total']) . ' net pour ' . $insc['places'] . ' participant(s). ' . ORG['tva'] . '. Il est réglé à réception de la facture, par carte bancaire ou par virement, au plus tard le premier jour de la formation.', 10);
  $pdf->h('Article 4 · Dédit, absence et annulation');
  $pdf->para('En cas de renoncement de l’entreprise moins de sept jours avant le début de la formation, le remplacement d’un participant par un autre est accepté sans frais. Les heures non suivies du fait du participant restent dues. Si l’organisme annule ou reporte la session faute d’avoir atteint ' . $s['seuil'] . ' participants, les sommes versées sont intégralement remboursées ou reportées sur la nouvelle date, au choix de l’entreprise.', 10);
  $pdf->h('Article 5 · Documents');
  $pdf->para('Une convocation est adressée à chaque participant. À l’issue de la formation, l’organisme remet à chaque participant une attestation de fin de formation et transmet à l’entreprise la facture et la copie des attestations et des feuilles d’émargement.', 10);
  $pdf->h('Signatures');
  $pdf->table([[241.64, 'L'], [241.64, 'L']], ['Pour l’organisme de formation', 'Pour l’entreprise'], [[
    "Abdou Fleur, dirigeant de Lead Performant\nÉmise le " . date_fr(now()),
    $signee ? 'Signée électroniquement par ' . $insc['signature_nom'] . "\nle " . date('d/m/Y à H:i', ts((string)$insc['signe_at'])) . "\nAdresse IP : " . $insc['signature_ip'] : "À signer en ligne\nRéférence " . strtoupper(substr($insc['token'], 0, 10)),
  ]]);
  return doc_save($pdf, $signee ? 'convention_signee' : 'convention', 'Convention de formation - ' . $p['titre'] . '.pdf', ['inscription_id' => $insc['id']]);
}

function doc_facture(array $f): array {
  $avoir = $f['type'] === 'avoir';
  $pdf = pdf_base(($avoir ? 'Avoir n° ' : 'Facture n° ') . $f['numero'], $avoir ? 'Avoir' : 'Facture');
  if ($f['organisation_id']) { $o = organisation((int)$f['organisation_id']); $dest = [$o['raison_sociale'], $o['siret'] ? 'SIRET ' . $o['siret'] : '', $o['adresse'], $o['contact_email']]; }
  else { $c = client((int)$f['client_id']); $dest = [client_nom($c), $c['organisation'], $c['adresse'], $c['email']]; }
  $pdf->para('Émetteur : ' . ORG['entite'] . ' · ' . ORG['adresse'] . ' · SIRET ' . ORG['siret'] . ' · ' . ORG['email'], 9.5);
  $pdf->para('Client : ' . implode(' · ', array_filter($dest)), 9.5);
  $pdf->para('Date d’émission : ' . date_fr((string)$f['date_emission']) . ($f['facture_origine'] ? ' · Facture d’origine : ' . db_val('SELECT numero FROM factures WHERE id=?', [$f['facture_origine']]) : ''), 9.5, false, '4A5563');
  $pdf->table([[383.28, 'L'], [100, 'R']], ['Désignation', 'Montant'], [[(string)$f['libelle'], eur((int)$f['montant'])], ['Total ' . ($avoir ? 'de l’avoir' : 'net à payer'), eur((int)$f['montant'])]]);
  $pdf->para(ORG['tva'] . '.', 9.5);
  $pdf->para($f['statut_paiement'] === 'payee' ? 'Facture acquittée par carte bancaire le ' . date_fr((string)$f['date_emission']) . '.' : ($avoir ? 'Le montant de l’avoir est remboursé par le moyen de paiement initial.' : 'Paiement à réception, par carte bancaire au moyen du lien sécurisé ou par virement (coordonnées bancaires sur demande à ' . ORG['email'] . '). En cas de retard de paiement d’un professionnel : pénalités au taux de trois fois l’intérêt légal et indemnité forfaitaire de 40 € pour frais de recouvrement (articles L441-10 et D441-5 du Code de commerce). Pas d’escompte pour paiement anticipé.'), 9.5);
  return doc_save($pdf, $avoir ? 'avoir' : 'facture', ($avoir ? 'Avoir ' : 'Facture ') . $f['numero'] . '.pdf', ['inscription_id' => $f['inscription_id'], 'commande_id' => $f['commande_id']]);
}

function doc_convocation(array $insc, string $nomParticipant): array {
  $s = session_get((int)$insc['session_id']); $p = programme((int)$s['programme_id']);
  $pdf = pdf_base('Convocation', $p['titre']);
  $pdf->para('Madame, Monsieur ' . $nomParticipant . ',', 10.5);
  $pdf->para('Nous avons le plaisir de vous convoquer à la formation « ' . $p['titre'] . ' ».', 10.5);
  $lieu = $s['modalite'] === 'distance' ? 'En visioconférence. Lien de connexion : ' . ($s['lien_visio'] ?: 'communiqué par courriel la veille') : ($s['lieu'] ?: param('parole_lieu_defaut'));
  $pdf->table([[150, 'L'], [333.28, 'L']], ['', ''], [['Dates', periode_fr($s['date_debut'], $s['date_fin'])], ['Horaires', 'Accueil 15 minutes avant le début. ' . heure_fr($s['heure_debut']) . ' à ' . heure_fr($s['heure_fin'])], ['Lieu', $lieu], ['Durée', $p['duree_heures'] . ' heures']]);
  $pdf->para('Merci de vous munir de quoi prendre des notes et de préparer une courte prise de parole de deux minutes sur un sujet de votre choix. Le règlement intérieur applicable est disponible sur www.leadperformant.com/reglement-interieur/. Toute absence doit être signalée au ' . ORG['tel'] . '.', 10);
  $pdf->para('Si vous avez besoin d’une adaptation liée à une situation de handicap, merci de nous l’indiquer au plus tôt.', 10);
  $pdf->para('Abdou Fleur, Lead Performant', 10, true);
  return doc_save($pdf, 'convocation', 'Convocation - ' . $p['titre'] . '.pdf', ['inscription_id' => $insc['id']]);
}

function doc_attestation(array $insc, string $nomParticipant, int $heures): array {
  $s = session_get((int)$insc['session_id']); $p = programme((int)$s['programme_id']);
  $pdf = pdf_base('Attestation de fin de formation', 'Article L6353-1 du Code du travail');
  $pdf->para('Je soussigné, Abdou Fleur, dirigeant de Lead Performant, atteste que :', 10.5);
  $pdf->para($nomParticipant, 14, true, '21406A', 1.6);
  $pdf->para('a suivi la formation « ' . $p['titre'] . ' », du ' . date_fr($s['date_debut']) . ' au ' . date_fr($s['date_fin']) . ', d’une durée de ' . $heures . ' heures sur ' . $p['duree_heures'] . ' heures prévues.', 10.5);
  $pdf->h('Objectifs de la formation', 11);
  $pdf->bullets(array_filter(explode("\n", (string)$p['objectifs'])));
  $pdf->h('Résultats de l’évaluation', 11);
  $pdf->para('Les acquis ont été évalués par mises en situation filmées et grille d’observation au premier et au dernier jour. Les résultats individuels ont été restitués au participant.', 10);
  $pdf->para('Fait à Vigneux-sur-Seine, le ' . date_fr(now()) . '.', 10);
  $pdf->para('Abdou Fleur, Lead Performant', 10, true);
  return doc_save($pdf, 'attestation', 'Attestation - ' . $p['titre'] . ' - ' . $nomParticipant . '.pdf', ['inscription_id' => $insc['id']]);
}
