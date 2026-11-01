<?php
/* Ouvre la page de paiement sécurisée Stripe pour une échéance. */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$e = db_one('SELECT * FROM echeances WHERE token=?', [get('e', 64)]);
if (!$e || !$e['inscription_id']) page_message('Lien invalide', '<p>Ce lien de paiement n’est pas valide.</p>');
if ($e['statut'] === 'payee') page_message('Déjà réglé', '<p>Ce paiement a bien été reçu le ' . h(date_fr((string)$e['paye_at'])) . '. Merci.</p>');
if (get('annule') === '1') page_message('Paiement interrompu', '<p>Le paiement n’a pas été effectué. Vous pouvez le reprendre à tout moment avant l’échéance du lien.</p><p><a class="pf-btn" href="/paiement/?e=' . h($e['token']) . '">Reprendre le paiement</a></p>');
try { redirect(demarrer_paiement_echeance($e)); }
catch (DomainException $x) { page_message('Paiement indisponible', '<p>' . h($x->getMessage()) . '</p>'); }
catch (Throwable $x) { log_error('Paiement : ' . $x->getMessage()); page_message('Paiement momentanément indisponible', '<p>Le service de paiement n’a pas répondu. Merci de réessayer dans quelques minutes ; aucun montant n’a été débité.</p>'); }
