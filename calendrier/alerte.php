<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check() || post('site_web') !== '') redirect('/calendrier/');
$email = mb_strtolower(line(post('email', 190)));
if (!is_email($email)) page_message('Adresse invalide', '<p>Merci de vérifier votre adresse électronique.</p><p><a class="pf-link" href="/calendrier/">Retour au calendrier</a></p>');
if (!db_val('SELECT id FROM alertes_sessions WHERE email=?', [$email])) db_insert('alertes_sessions', ['email' => $email, 'created_at' => now()]);
page_message('C’est noté', '<p>Vous recevrez un courriel dès qu’une nouvelle session sera ouverte aux inscriptions. Vous pourrez vous désinscrire à tout moment en répondant à ce message.</p><p><a class="pf-link" href="/calendrier/">Retour au calendrier</a></p>');
