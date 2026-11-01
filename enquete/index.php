<?php
/* Questionnaires : satisfaction à chaud (J+1) et transfert des acquis (J+30). */
require dirname(__DIR__) . '/_lp/bootstrap.php';
header('X-Robots-Tag: noindex');
$q = db_one('SELECT * FROM enquetes WHERE token=?', [get('t', 64)]);
if (!$q) page_message('Lien invalide', '<p>Ce questionnaire n’existe pas.</p>');
if ($q['repondu_at']) page_message('Merci', '<p>Vos réponses ont déjà été enregistrées. Merci pour votre retour.</p>', 'vert');
$chaud = $q['type'] === 'chaud';
$questions = $chaud
  ? ['global' => 'Satisfaction globale', 'objectifs' => 'Atteinte des objectifs annoncés', 'contenu' => 'Qualité des contenus et des exercices', 'animation' => 'Qualité de l’animation', 'organisation' => 'Organisation et conditions d’accueil']
  : ['utilisation' => 'J’ai mis en pratique ce que j’ai appris', 'aisance' => 'Je me sens plus à l’aise pour prendre la parole', 'impact' => 'Cela a eu un effet visible dans mon activité'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
  $r = [];
  foreach ($questions as $k => $l) $r[$k] = max(1, min(5, (int)post($k)));
  $r['recommande'] = post('recommande') === 'oui' ? 'oui' : 'non';
  $r['commentaire'] = post('commentaire', 3000);
  db_update('enquetes', (int)$q['id'], ['reponses' => json_encode($r, JSON_UNESCAPED_UNICODE), 'repondu_at' => now()]);
  page_message('Merci pour votre retour', '<p>Vos réponses sont enregistrées. Elles nous aident à faire progresser nos formations.</p>', 'vert');
}
$i = inscription((int)$q['inscription_id']); $s = session_get((int)$i['session_id']); $p = programme((int)$s['programme_id']);
$html = '<p>' . h($p['titre']) . ' · ' . h(periode_fr($s['date_debut'], $s['date_fin'])) . '</p><form method="post">' . csrf_field();
foreach ($questions as $k => $l) {
  $html .= '<fieldset style="border:0;padding:0;margin:0 0 16px"><legend style="font-weight:600;margin-bottom:6px">' . h($l) . '</legend><div style="display:flex;gap:8px;flex-wrap:wrap">';
  for ($n = 1; $n <= 5; $n++) $html .= '<label class="pf-check" style="border:1px solid #CFC7B5;border-radius:8px;padding:8px 12px"><input type="radio" name="' . $k . '" value="' . $n . '" required> ' . $n . '</label>';
  $html .= '</div></fieldset>';
}
$html .= '<p class="pf-note">1 = pas du tout, 5 = tout à fait</p><fieldset style="border:0;padding:0;margin:0 0 16px"><legend style="font-weight:600;margin-bottom:6px">Recommanderiez-vous cette formation ?</legend><label class="pf-check"><input type="radio" name="recommande" value="oui" required> Oui</label> <label class="pf-check"><input type="radio" name="recommande" value="non"> Non</label></fieldset>'
  . '<label class="pf-field">Commentaire (facultatif)<textarea name="commentaire"></textarea></label><p><button class="pf-btn" type="submit">Envoyer</button></p></form>';
page_message($chaud ? 'Votre avis sur la formation' : 'Trente jours après la formation', $html);
