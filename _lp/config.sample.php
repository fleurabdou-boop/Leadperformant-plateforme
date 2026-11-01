<?php
/* Lead Performant — configuration de la plateforme.
   Copier en config.php sur le serveur et compléter. Ce fichier n'est jamais servi (dossier _lp protégé). */
return [
  'site_url'      => 'https://www.leadperformant.com',   // sans barre finale
  'env'           => 'prod',                              // prod | test

  // Base de données : MySQL en production, SQLite pour les tests locaux
  'db' => [
    'dsn'  => 'mysql:host=127.0.0.1;port=3306;dbname=u201807450_lp;charset=utf8mb4',
    'user' => 'u201807450_lp',
    'pass' => '',
  ],

  // Stripe (clés du compte Lead Performant ; commencer par les clés de test sk_test_ / whsec_)
  'stripe' => [
    'secret'         => '',
    'webhook_secret' => '',
  ],

  // Courriels sortants (boîte du domaine)
  'mail' => [
    'from'      => 'inscriptions@leadperformant.com',
    'from_name' => 'Lead Performant',
    'reply_to'  => 'leadperformant@gmail.com',
    'admin'     => 'leadperformant@gmail.com',   // alertes et lien d'activation de l'administration
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => 465,                           // 465 = SSL
    'smtp_user' => 'inscriptions@leadperformant.com',
    'smtp_pass' => '',
    'transport' => 'smtp',                        // smtp | mail | file (tests : écrit les messages dans storage/outbox)
  ],

  // Jeton secret des tâches planifiées (appel de /_lp/cron.php?k=...) et sel des jetons
  'cron_key' => '',
  'secret'   => '',
];
