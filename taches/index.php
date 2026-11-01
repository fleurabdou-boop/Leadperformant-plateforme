<?php
/* Point d'entrée des tâches planifiées. À appeler toutes les 15 minutes :
   - par le planificateur Hostinger : php /home/<compte>/domains/leadperformant.com/public_html/taches/index.php
   - ou par URL : https://www.leadperformant.com/taches/?k=<cron_key> */
require dirname(__DIR__) . '/_lp/bootstrap.php';
require LP_DIR . '/taches.php';
if (PHP_SAPI !== 'cli' && (!cfg('cron_key') || !hash_equals((string)cfg('cron_key'), get('k', 100)))) { http_response_code(404); exit; }
$lock = LP_STORAGE . '/logs/taches.lock';
$fp = fopen($lock, 'c');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) { echo "déjà en cours\n"; exit; }
$log = taches_run();
flock($fp, LOCK_UN);
header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $log), "\n";
