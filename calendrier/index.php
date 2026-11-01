<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
track('calendrier');

$mois = preg_match('/^\d{4}-\d{2}$/', get('m')) ? get('m') : '';
$prog = get('programme', 80); $mod = get('format', 20);
$progs = db_all('SELECT * FROM programmes WHERE actif=1 ORDER BY numero');

$where = "s.etat IN ('ouverte','fermee') AND s.date_fin >= ?"; $p = [date('Y-m-d')];
if ($mois) { $where .= ' AND substr(s.date_debut,1,7)=?'; $p[] = $mois; }
if ($prog) { $where .= ' AND pr.slug=?'; $p[] = $prog; }
if (in_array($mod, ['presentiel', 'distance'], true)) { $where .= ' AND s.modalite=?'; $p[] = $mod; }
$sessions = db_all("SELECT s.*, pr.titre, pr.numero, pr.duree_jours, pr.duree_heures, pr.slug AS pslug FROM sessions s JOIN programmes pr ON pr.id=s.programme_id WHERE $where ORDER BY s.date_debut", $p);

$moisDispo = array_column(db_all("SELECT DISTINCT substr(date_debut,1,7) AS m FROM sessions WHERE etat IN ('ouverte','fermee') AND date_fin >= ? ORDER BY m", [date('Y-m-d')]), 'm');
$idx = $mois ? array_search($mois, $moisDispo, true) : false;
$prev = $idx !== false && $idx > 0 ? $moisDispo[$idx - 1] : null;
$next = $idx !== false && $idx < count($moisDispo) - 1 ? $moisDispo[$idx + 1] : ($mois === '' && $moisDispo ? $moisDispo[0] : null);
$qs = fn($m) => '?' . http_build_query(array_filter(['m' => $m, 'programme' => $prog, 'format' => $mod]));

// Données structurées : chaque session datée est une instance de cours.
$ld = [];
foreach ($sessions as $s) {
  $ld[] = ['@type' => 'Course', 'name' => $s['titre'], 'provider' => ['@type' => 'Organization', 'name' => 'Lead Performant', 'url' => url('/')],
    'hasCourseInstance' => ['@type' => 'CourseInstance', 'courseMode' => $s['modalite'] === 'distance' ? 'online' : 'onsite', 'startDate' => $s['date_debut'], 'endDate' => $s['date_fin'],
      'location' => $s['modalite'] === 'distance' ? 'En ligne' : ($s['lieu'] ?: 'Île-de-France')], 'url' => url('/sessions/' . $s['slug'] . '/')];
}

page_start('Calendrier des sessions', 'Dates des formations ouvertes en art oratoire : Éloquence Élite et L’Éloquence comme levier professionnel, trois jours, dix-huit heures, de 7 à 15 participants.', 'calendrier', true, '/calendrier/', $ld ? json_encode(['@context' => 'https://schema.org', '@graph' => $ld], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
?>
<section class="page-hero"><div class="wrap">
  <?= crumbs(['Calendrier' => null]) ?>
  <span class="eyebrow">Formations ouvertes</span>
  <h1 class="pf-h1">Calendrier des sessions</h1>
  <p class="pf-lede">Deux programmes d’art oratoire en trois jours, dix-huit heures, en groupe de sept à quinze participants. Choisissez une date ; le tarif vous est présenté à l’étape d’inscription, avant tout engagement.</p>
</div></section>
<section class="section paper"><div class="wrap">
  <form class="pf-toolbar" method="get" action="/calendrier/">
    <div class="pf-month">
      <?php if ($prev): ?><a href="<?= h($qs($prev)) ?>" aria-label="Mois précédent">‹</a><?php else: ?><a href="<?= h($qs('')) ?>" aria-label="Toutes les dates">‹</a><?php endif; ?>
      <strong><?= $mois ? h(MOIS[(int)substr($mois, 5, 2)] . ' ' . substr($mois, 0, 4)) : 'Toutes les dates' ?></strong>
      <?php if ($next): ?><a href="<?= h($qs($next)) ?>" aria-label="Mois suivant">›</a><?php endif; ?>
    </div>
    <div class="pf-filters">
      <?php if ($mois): ?><input type="hidden" name="m" value="<?= h($mois) ?>"><?php endif; ?>
      <label>Programme<select name="programme" onchange="this.form.submit()"><option value="">Tous les programmes</option><?php foreach ($progs as $pr): ?><option value="<?= h($pr['slug']) ?>"<?= $prog === $pr['slug'] ? ' selected' : '' ?>><?= h($pr['numero'] . ' · ' . $pr['titre']) ?></option><?php endforeach; ?></select></label>
      <label>Format<select name="format" onchange="this.form.submit()"><option value="">Présentiel et distance</option><option value="presentiel"<?= $mod === 'presentiel' ? ' selected' : '' ?>>Présentiel</option><option value="distance"<?= $mod === 'distance' ? ' selected' : '' ?>>À distance</option></select></label>
      <noscript><button class="pf-btn" type="submit">Filtrer</button></noscript>
    </div>
  </form>

  <div class="pf-list">
  <?php if (!$sessions): ?>
    <div class="pf-empty"><p style="margin:0 0 10px"><strong>Aucune session n’est programmée pour ces critères.</strong></p><p style="margin:0">Laissez votre adresse ci-dessous : vous serez prévenu(e) dès l’ouverture d’une nouvelle date.</p></div>
  <?php endif; ?>
  <?php foreach ($sessions as $s): $v = session_view($s); $t = ts($s['date_debut']); ?>
    <article class="pf-sess">
      <div class="pf-date"><div class="m"><?= h(['', 'janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'][(int)date('n', $t)]) ?></div><div class="d"><?= (int)date('j', $t) ?><?= $s['date_fin'] !== $s['date_debut'] ? '-' . (int)date('j', ts($s['date_fin'])) : '' ?></div><div class="w"><?= h(date('Y', $t)) ?></div></div>
      <div class="pf-sess-body">
        <span class="k"><?= h($s['numero'] . ' · ' . pole($s['numero'])) ?></span>
        <h2><?= h($s['titre']) ?></h2>
        <p><?= h(periode_fr($s['date_debut'], $s['date_fin'])) ?> · <?= h(heure_fr($s['heure_debut']) . ' - ' . heure_fr($s['heure_fin'])) ?> · <?= (int)$s['duree_heures'] ?> heures · <?= $s['modalite'] === 'distance' ? 'À distance' : 'Présentiel' . ($s['lieu'] ? ', ' . h(explode("\n", $s['lieu'])[0]) : '') ?></p>
        <div class="pf-chips"><?= chip($v['statut'], $v['ton']) ?><?php if ($v['restantes'] > 0 && $s['etat'] === 'ouverte'): ?><?= chip($v['restantes'] . ' place' . ($v['restantes'] > 1 ? 's' : '') . ' restante' . ($v['restantes'] > 1 ? 's' : '') . ' sur ' . $s['capacite'], 'neutre') ?><?php endif; ?><?php if (!empty($v['confirmee'])): ?><?= chip('Session confirmée', 'bleu') ?><?php endif; ?></div>
      </div>
      <a class="pf-btn<?= $v['attente'] ? ' pf-btn-ghost' : '' ?>" href="/sessions/<?= h($s['slug']) ?>/"><?= $v['attente'] ? 'Liste d’attente' : 'Voir la session' ?></a>
    </article>
  <?php endforeach; ?>
  </div>

  <div class="pf-info3">
    <div><h3>Vous financez vous-même</h3><p>Contrat signé en ligne, dix jours pour vous rétracter sans frais, puis un acompte de 30 % ; le solde est réglé pendant la formation.</p></div>
    <div><h3>Votre employeur finance</h3><p>Demande de convention en ligne, devis sous deux jours ouvrés, places bloquées sept jours. Règlement par carte ou virement.</p></div>
    <div><h3>Être prévenu des prochaines dates</h3>
      <form method="post" action="/calendrier/alerte.php" style="display:flex;flex-direction:column;gap:8px;margin-top:6px"><?= csrf_field() ?>
        <label class="pf-field">Votre courriel<input type="email" name="email" required autocomplete="email"></label>
        <span class="pf-hp" aria-hidden="true"><label>Ne pas remplir<input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></span>
        <button class="pf-btn pf-btn-ghost" type="submit">M’avertir</button>
      </form>
    </div>
  </div>
</div></section>
<?php page_end();
