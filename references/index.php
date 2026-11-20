<?php
/* Références et témoignages : logos des organisations, témoignages publiés et formulaire pour en proposer un.
   Contenus gérés dans le tableau de bord (Références et témoignages). */
require dirname(__DIR__) . '/_lp/bootstrap.php';
csrf_start();

$v = ['nom' => '', 'fonction' => '', 'organisation' => '', 'email' => '', 'contexte' => '', 'texte' => '', 'affichage' => 'complet', 'accord' => ''];
$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  foreach (['nom' => 160, 'fonction' => 200, 'organisation' => 200, 'email' => 190, 'contexte' => 200, 'texte' => 1600, 'affichage' => 12, 'accord' => 4] as $k => $max) $v[$k] = post($k, $max);
  $v['texte'] = trim(preg_replace("/\n{3,}/", "\n\n", $v['texte']));
  $offres = array_merge(...array_values(REFS_OFFRES));
  $debut = (int)($_SESSION['refs_t'] ?? 0);
  if (!csrf_check()) $erreur = 'La page a expiré. Votre texte est conservé ci-dessous : envoyez-le de nouveau.';
  elseif (post('site') !== '' || ($debut && time() - $debut < 4)) $erreur = 'Envoi non pris en compte. Merci de réessayer.';
  elseif (line($v['nom']) === '') $erreur = 'Indiquez votre prénom et votre nom.';
  elseif (!is_email($v['email'])) $erreur = 'Indiquez une adresse de courriel valide. Elle ne sera jamais publiée.';
  elseif (!in_array($v['contexte'], $offres, true)) $erreur = 'Choisissez la formation ou l’intervention concernée.';
  elseif (mb_strlen($v['texte']) < 40) $erreur = 'Votre témoignage est un peu court : quelques phrases suffisent, 40 caractères au moins.';
  elseif ($v['accord'] !== '1') $erreur = 'Cochez l’autorisation de publication pour envoyer votre témoignage.';
  elseif ((int)db_val('SELECT COUNT(*) FROM ref_temoignages WHERE ip=? AND created_at > ?', [client_ip(), date('Y-m-d H:i:s', time() - 3600)]) >= 3
    || (int)db_val("SELECT COUNT(*) FROM ref_temoignages WHERE origine='site' AND created_at > ?", [date('Y-m-d H:i:s', time() - 86400)]) >= 40)
    $erreur = 'Trop d’envois en peu de temps. Réessayez plus tard ou écrivez à ' . cfg('mail.reply_to') . '.';
  if ($erreur === '') {
    $id = db_insert('ref_temoignages', ['texte' => $v['texte'], 'auteur' => line($v['nom']), 'fonction' => line($v['fonction']), 'organisation' => line($v['organisation']),
      'affichage' => array_key_exists($v['affichage'], REFS_AFFICHAGE) ? $v['affichage'] : 'complet', 'contexte' => $v['contexte'], 'pole' => refs_pole_de_contexte($v['contexte']),
      'date_temoignage' => date('Y-m-01'), 'etat' => 'a_valider', 'accord' => 'formulaire', 'accord_at' => date('Y-m-d'), 'email' => mb_strtolower(line($v['email'])),
      'origine' => 'site', 'ip' => client_ip(), 'created_at' => now(), 'updated_at' => now()]);
    journal('site', 'temoignage_recu', 'temoignage:' . $id);
    try {
      mail_queue((string)cfg('mail.admin'), 'Témoignage reçu sur le site · à valider', email_wrap('Un témoignage attend votre relecture',
        p_('<strong>' . h(line($v['nom'])) . '</strong>' . (line($v['fonction'] . ' ' . $v['organisation']) !== '' ? ', ' . h(implode(', ', array_filter([line($v['fonction']), line($v['organisation'])]))) : '') . ' · ' . h($v['contexte']))
        . '<blockquote style="margin:0 0 16px;padding:12px 16px;border-left:3px solid #C9A227;background:#FBFAF7;font-style:italic">' . nl2br(h($v['texte'])) . '</blockquote>'
        . p_('Il n’est pas encore publié. Relisez-le, corrigez la forme si besoin, puis mettez-le en ligne.') . btn(url('/gestion/?v=reference_temoignage&id=' . $id), 'Relire et publier')), [], 'temoignage-' . $id);
      mail_flush();
    } catch (Throwable $e) { log_error('Références : notification non envoyée : ' . $e->getMessage()); }
    unset($_SESSION['refs_t']);
    redirect('/references/?merci=1#temoigner');
  }
}

$logos = refs_logos_publies();
$tems = refs_temoignages_publies();
$merci = get('merci') === '1';
$aContenu = (bool)($logos || $tems);
$une = null;
foreach ($tems as $k => $t) if ((int)$t['une'] === 1 && mb_strlen((string)$t['texte']) <= 420) { $une = $t; unset($tems[$k]); break; }
$poles = array_unique(array_map(fn($t) => $t['pole'], $tems));
$filtre = count($tems) >= 4 && in_array('p1', $poles, true) && in_array('p2', $poles, true);
if (empty($_SESSION['refs_t'])) $_SESSION['refs_t'] = time();

$ld = json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
  ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => url('/')],
  ['@type' => 'ListItem', 'position' => 2, 'name' => 'Références et témoignages', 'item' => url('/references/')]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
page_start('Références et témoignages', 'Les organisations qui ont confié à Lead Performant une formation, une intervention ou une médiation, et les témoignages de celles et ceux qui les ont suivies.', 'references', $aContenu, '/references/', $ld);

$btnOn = 'min-height: 44px; padding: 0 18px; font-family: inherit; font-size: 14px; cursor: pointer; border: 1px solid #0B1A33; background: #0B1A33; color: #F6F3EC';
$btnOff = 'min-height: 44px; padding: 0 18px; font-family: inherit; font-size: 14px; cursor: pointer; border: 1px solid #CFC5B1; background: transparent; color: #0B1A33';
?>
<div class="lpx lpr-page">
<section class="lpr-hero" aria-labelledby="refs-h1">
<div class="lpr-in lpr-hero-in">
<nav aria-label="Fil d'Ariane" class="lpr-crumbs"><a href="/">Accueil</a><span aria-hidden="true">/</span><span>Références et témoignages</span></nav>
<div class="lpr-hero-cols">
<div class="lpr-hero-t">
<p class="lpr-eyebrow rise">Références et témoignages</p>
<h1 id="refs-h1" class="rise d1">Ils nous ont fait confiance. <em>Ils en témoignent.</em></h1>
<p class="lpr-lede rise d2"><?= $aContenu
  ? 'Entreprises, institutions, collectivités, écoles et associations : les organisations qui ont confié à Lead Performant une formation, une intervention ou une médiation, et la parole de celles et ceux qui les ont suivies.'
  : 'Cette page réunit les organisations qui ont confié à Lead Performant une formation, une intervention ou une médiation, et la parole de celles et ceux qui les ont suivies.' ?></p>
<?php if (!$merci): ?><a class="ulink lpr-hero-link rise d2" href="#temoigner">Vous avez suivi une formation ? Partagez votre témoignage <span aria-hidden="true">→</span></a><?php endif; ?>
</div>
<?php if ($une): ?><div class="lpr-hero-q rise d2"><?= refs_tem_html($une, true) ?></div><?php endif; ?>
</div>
</div>
</section>

<?php if ($logos): ?>
<section class="lpr-sec" aria-labelledby="refs-orgs">
<div class="lpr-in">
<div class="lpr-head"><div class="lpr-head-t"><p class="lpr-eyebrow lpr-eyebrow-d">Organisations</p><h2 id="refs-orgs" class="lpr-h2">Elles nous ont confié leurs équipes, leurs dirigeants, leurs différends.</h2></div><p class="lpr-count"><?= count($logos) ?> organisation<?= count($logos) > 1 ? 's' : '' ?></p></div>
<ul class="lpr-grid" aria-label="Organisations qui ont fait confiance à Lead Performant"><?= implode('', array_map('refs_logo_html', $logos)) ?></ul>
</div>
</section>
<?php endif; ?>

<?php if ($tems): ?>
<section class="lpr-sec lpr-sec-tems" id="lpr-tems" aria-labelledby="refs-tems">
<div class="lpr-in">
<div class="lpr-head"><div class="lpr-head-t"><p class="lpr-eyebrow lpr-eyebrow-d">Témoignages</p><h2 id="refs-tems" class="lpr-h2">Ce qu’en disent celles et ceux qui ont pris la parole.</h2></div>
<?php if ($filtre): ?><div role="group" aria-label="Filtrer par pôle" data-lp2-filter="lpr-tems" class="lpr-filter">
<?php foreach (['tout' => 'Tous', 'p1' => 'Art oratoire et leadership', 'p2' => 'Médiation et gestion des conflits'] as $k => $l): ?><button type="button" data-f="<?= $k ?>" data-on="<?= h($btnOn) ?>" data-off="<?= h($btnOff) ?>" aria-pressed="<?= $k === 'tout' ? 'true' : 'false' ?>" style="<?= h($k === 'tout' ? $btnOn : $btnOff) ?>"><?= h($l) ?></button><?php endforeach; ?>
</div><?php endif; ?>
</div>
<div class="lpr-tems lpr-tems-cols"><?= implode('', array_map(fn($t) => refs_tem_html($t), $tems)) ?></div>
</div>
</section>
<?php endif; ?>

<section class="lpr-sec lpr-form-sec" id="temoigner" aria-labelledby="refs-form">
<div class="lpr-in lpr-form-cols">
<div class="lpr-form-t">
<p class="lpr-eyebrow lpr-eyebrow-d">Votre retour</p>
<h2 id="refs-form" class="lpr-h2">Vous avez suivi une formation ou une intervention ?</h2>
<p>Quelques phrases suffisent : ce que vous êtes venu chercher, ce qui a changé ensuite. Votre témoignage est relu avant toute publication.</p>
<div class="lpr-note">
<h3>Comment nous publions les témoignages</h3>
<p>Ils proviennent de participants et de clients de Lead Performant. Chacun est publié avec l’accord de son auteur, après relecture, et sans contrepartie d’aucune sorte. Seule la forme peut être corrigée (orthographe, longueur), jamais le sens ; chaque témoignage porte le mois où il a été recueilli.</p>
<p>Ne sont pas publiés les textes sans lien avec une prestation de Lead Performant, ou qui contiennent des propos injurieux ou des informations sur des tiers. L’ordre de présentation est choisi par Lead Performant. Chaque auteur peut faire retirer son témoignage à tout moment.</p>
</div>
</div>
<div class="lpr-form-box">
<?php if ($merci): ?>
<div class="lpr-merci" role="status"><span class="lpr-q" aria-hidden="true">«</span><h3>Merci pour votre témoignage.</h3><p>Il nous est bien parvenu. Nous le relisons avant de le publier ; si nous avons besoin d’une précision, nous vous écrirons à l’adresse indiquée.</p><a class="ulink" href="/">Retour à l’accueil <span aria-hidden="true">→</span></a></div>
<?php else: ?>
<form method="post" action="/references/#temoigner" class="lpr-form" aria-label="Partager votre témoignage">
<?= csrf_field() ?>
<?php if ($erreur): ?><p class="lpr-err" role="alert"><?= h($erreur) ?></p><?php endif; ?>
<div class="lpr-hp" aria-hidden="true"><label>Ne pas remplir<input name="site" tabindex="-1" autocomplete="off"></label></div>
<div class="lpr-row">
<label class="lpr-f"><span>Prénom et nom <b aria-hidden="true">*</b></span><input name="nom" required maxlength="160" autocomplete="name" value="<?= h($v['nom']) ?>"></label>
<label class="lpr-f"><span>Courriel <b aria-hidden="true">*</b> <small>jamais publié</small></span><input name="email" type="email" required maxlength="190" autocomplete="email" value="<?= h($v['email']) ?>"></label>
</div>
<div class="lpr-row">
<label class="lpr-f"><span>Fonction</span><input name="fonction" maxlength="200" autocomplete="organization-title" value="<?= h($v['fonction']) ?>"></label>
<label class="lpr-f"><span>Organisation</span><input name="organisation" maxlength="200" autocomplete="organization" value="<?= h($v['organisation']) ?>"></label>
</div>
<label class="lpr-f"><span>Formation ou intervention suivie <b aria-hidden="true">*</b></span><select name="contexte" required>
<option value="">Choisir</option>
<?php foreach (['p1' => 'Art oratoire et leadership', 'p2' => 'Médiation et gestion des conflits', 'tous' => 'Autre'] as $p => $lib): ?><optgroup label="<?= h($lib) ?>"><?php foreach (REFS_OFFRES[$p] as $o): ?><option<?= $v['contexte'] === $o ? ' selected' : '' ?>><?= h($o) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
</select></label>
<label class="lpr-f"><span>Votre témoignage <b aria-hidden="true">*</b></span><textarea name="texte" required rows="6" minlength="40" maxlength="1500"><?= h($v['texte']) ?></textarea></label>
<fieldset class="lpr-f lpr-radios"><legend>Nom affiché avec votre témoignage</legend>
<?php foreach (REFS_AFFICHAGE as $k => $lib): ?><label><input type="radio" name="affichage" value="<?= $k ?>"<?= $v['affichage'] === $k ? ' checked' : '' ?>><?= h($lib) ?></label><?php endforeach; ?>
</fieldset>
<label class="lpr-check"><input type="checkbox" name="accord" value="1" required<?= $v['accord'] === '1' ? ' checked' : '' ?>><span>J’autorise Lead Performant à publier ce témoignage sur leadperformant.com, avec le nom affiché selon mon choix, ma fonction et mon organisation. Je peux retirer mon accord à tout moment en écrivant à <?= h((string)cfg('mail.reply_to')) ?>.</span></label>
<p class="lpr-rgpd">Vos données servent uniquement à relire, publier et, si besoin, vous recontacter au sujet de ce témoignage. Elles sont conservées tant que le témoignage est publié, puis supprimées. Vous disposez d’un droit d’accès, de rectification et d’effacement : <a href="/confidentialite/">politique de confidentialité</a>.</p>
<button type="submit" class="lpb lpb-navy lpr-submit">Envoyer mon témoignage <span aria-hidden="true">→</span></button>
</form>
<?php endif; ?>
</div>
</div>
</section>
</div>
<?php
page_end();
