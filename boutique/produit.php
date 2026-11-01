<?php
/* Fiche produit et achat direct (prix affichés, paiement immédiat). */
require dirname(__DIR__) . '/_lp/bootstrap.php';
$p = db_one('SELECT * FROM produits WHERE slug=? AND actif=1', [get('p', 120)]);
if (!$p) { http_response_code(404); page_message('Produit introuvable', '<p><a class="pf-link" href="/boutique/">Retour à la boutique</a></p>'); }
track('produit:' . $p['slug']);
$numerique = in_array($p['type'], ['ebook', 'guide'], true);
$err = ''; $d = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = mb_strtolower(line(post('email', 190)));
  $cr = $p['type'] === 'visio' ? (int)post('creneau') : null;
  if (!csrf_check() || post('site_web') !== '') $err = 'La page a expiré. Merci de recommencer.';
  elseif (!paiement_actif()) $err = PAIEMENT_INDISPONIBLE;
  elseif (line(post('prenom', 100)) === '' || line(post('nom', 100)) === '' || !is_email($email)) $err = 'Merci d’indiquer vos prénom, nom et une adresse électronique valide.';
  elseif ($p['type'] === 'visio' && !$cr) $err = 'Merci de choisir un créneau.';
  elseif (post('accord') !== 'oui' || post('renonce') !== 'oui') $err = 'Merci de cocher les deux cases pour continuer.';
  else {
    try {
      $cid = client_upsert(['email' => $email, 'prenom' => line(post('prenom', 100)), 'nom' => line(post('nom', 100)), 'adresse' => post('adresse', 400)]);
      $c = commande_creer($p, $cid, post('code', 40), $cr);
      $cs = stripe_checkout((int)$c['montant'], TYPES_PRODUIT[$p['type']] . ' · ' . $p['titre'], $email, ['commande' => (int)$c['id']], url('/paiement/merci.php?c=' . $c['token']), url('/boutique/' . $p['slug'] . '/?annule=1'), 30);
      track('boutique:paiement');
      redirect($cs['url']);
    } catch (DomainException $e) { $err = $e->getMessage(); }
    catch (Throwable $e) { log_error('Boutique : ' . $e->getMessage()); $err = 'Le service de paiement n’a pas répondu. Merci de réessayer dans quelques minutes.'; }
  }
}
$creneaux = $p['type'] === 'visio' ? db_all("SELECT * FROM creneaux WHERE produit_id=? AND debut > ? AND (statut='libre' OR (statut='reserve' AND hold_until < ?)) ORDER BY debut LIMIT 40", [$p['id'], date('Y-m-d H:i:s', time() + 86400), now()]) : [];
$ld = json_encode(['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['titre'], 'description' => $p['sous_titre'], 'brand' => 'Lead Performant',
  'offers' => ['@type' => 'Offer', 'price' => number_format($p['prix'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock', 'url' => url('/boutique/' . $p['slug'] . '/')]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
page_start($p['titre'], (string)$p['sous_titre'], 'boutique', true, '/boutique/' . $p['slug'] . '/', $ld);
?>
<section class="section paper"><div class="wrap">
  <?= crumbs(['Boutique' => '/boutique/', $p['titre'] => null]) ?>
  <?= get('annule') === '1' ? alert_box('Le paiement a été interrompu ; aucun montant n’a été débité.', 'neutre') : '' ?>
  <div class="pf-cols" style="margin-top:22px">
    <?php if ($numerique): ?>
    <div style="flex:1 1 280px;max-width:400px">
      <div class="pf-cover <?= $p['type'] === 'guide' ? 'guide' : '' ?>" style="border-radius:6px;box-shadow:0 18px 40px rgba(33,64,106,.18)<?= $p['couverture'] ? ';background-image:url(\'/boutique/media.php?f=' . h(rawurlencode($p['couverture'])) . '\')' : '' ?>"><?php if (!$p['couverture']): ?><span class="b">LEAD PERFORMANT</span><span class="t" style="font-size:2.2rem"><?= h($p['titre']) ?></span><span class="r"></span><?php endif; ?></div>
      <?php if ($p['extrait']): ?><p class="pf-note" style="text-align:center"><a class="pf-link" href="/boutique/media.php?f=<?= h(rawurlencode($p['extrait'])) ?>" target="_blank" rel="noopener">Lire un extrait gratuit (PDF)</a></p><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="pf-main">
      <span class="pf-kind"><?= h(TYPES_PRODUIT[$p['type']]) ?><?= $numerique ? ' · PDF' . ($p['pages'] ? ' · ' . (int)$p['pages'] . ' pages' : '') : ($p['type'] === 'visio' ? ' · ' . (int)$p['duree_minutes'] . ' minutes' : ' · réponse sous ' . (int)$p['delai_jours'] . ' jours ouvrés') ?></span>
      <h1 class="pf-h1" style="margin-top:.2em"><?= h($p['titre']) ?></h1>
      <p class="pf-lede"><?= h($p['sous_titre']) ?></p>
      <?php if ($p['description']): ?><div style="line-height:1.7;color:#374151"><?= nl2br(h($p['description'])) ?></div><?php endif; ?>
      <?php if ($p['sommaire']): ?><h2 style="font-size:1.05rem;color:var(--pf-navy);margin:26px 0 8px">Ce que vous y trouverez</h2><ul><?php foreach (array_filter(explode("\n", (string)$p['sommaire'])) as $l): ?><li><?= h($l) ?></li><?php endforeach; ?></ul><?php endif; ?>

      <form method="post" class="pf-card" style="margin-top:26px"><?= csrf_field() ?>
        <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:8px"><span class="pf-price" style="font-size:2.2rem"><?= h(eur((int)$p['prix'])) ?></span><span class="pf-note" style="margin:0"><?= h(ORG['tva']) ?></span></div>
        <?= $err ? alert_box(h($err)) : '' ?>
        <?php if ($p['type'] === 'visio'): ?>
          <fieldset style="border:0;padding:0;margin:16px 0 0"><legend style="font-weight:600;color:var(--pf-navy);margin-bottom:8px">Choisissez votre créneau</legend>
          <?php if (!$creneaux): ?><p class="pf-note">Aucun créneau n’est ouvert pour le moment. <a class="pf-link" href="/contact/">Contactez-nous</a> pour convenir d’un rendez-vous.</p>
          <?php else: ?><div class="pf-slots"><?php foreach ($creneaux as $c): ?><label><input type="radio" name="creneau" value="<?= (int)$c['id'] ?>" required<?= (int)($d['creneau'] ?? 0) === (int)$c['id'] ? ' checked' : '' ?>><span><?= h(ucfirst(JOURS[(int)date('w', ts($c['debut']))])) ?> <?= date('d/m', ts($c['debut'])) ?><br><strong><?= h(heure_fr(date('H:i', ts($c['debut'])))) ?></strong></span></label><?php endforeach; ?></div><?php endif; ?>
          </fieldset>
        <?php endif; ?>
        <div class="pf-form" style="margin-top:16px">
          <label class="pf-field">Prénom<input name="prenom" required autocomplete="given-name" value="<?= h($d['prenom'] ?? '') ?>"></label>
          <label class="pf-field">Nom<input name="nom" required autocomplete="family-name" value="<?= h($d['nom'] ?? '') ?>"></label>
          <label class="pf-field full">Courriel <?= $numerique ? 'de livraison' : '' ?><input type="email" name="email" required autocomplete="email" value="<?= h($d['email'] ?? '') ?>"></label>
          <label class="pf-field full">Adresse postale (facultatif, pour la facture)<input name="adresse" autocomplete="street-address" value="<?= h($d['adresse'] ?? '') ?>"></label>
          <?php if (!(int)$p['prix_unique_livre']): ?><label class="pf-field">Code promotionnel (facultatif)<input name="code" value="<?= h($d['code'] ?? '') ?>" autocomplete="off"></label><?php endif; ?>
        </div>
        <span class="pf-hp" aria-hidden="true"><label>Ne pas remplir<input type="text" name="site_web" tabindex="-1" autocomplete="off"></label></span>
        <?php if ($numerique): ?>
          <label class="pf-check" style="margin-top:16px"><input type="checkbox" name="accord" value="oui" required> Je demande à accéder au fichier immédiatement, avant la fin du délai de rétractation de quatorze jours.</label>
          <label class="pf-check" style="margin-top:10px"><input type="checkbox" name="renonce" value="oui" required> Je reconnais perdre mon droit de rétractation dès que l’accès au fichier m’est fourni.</label>
        <?php else: ?>
          <label class="pf-check" style="margin-top:16px"><input type="checkbox" name="accord" value="oui" required> Je demande que la prestation commence avant la fin du délai de rétractation de quatorze jours.</label>
          <label class="pf-check" style="margin-top:10px"><input type="checkbox" name="renonce" value="oui" required> Je reconnais perdre mon droit de rétractation une fois la prestation entièrement exécutée.</label>
        <?php endif; ?>
        <button class="pf-btn pf-btn-block" type="submit" style="margin-top:18px">Payer <?= h(eur((int)$p['prix'])) ?></button>
        <p class="pf-note">Paiement par carte sur la page sécurisée de Stripe. <?= $numerique ? 'Lien de téléchargement valable ' . (int)param('acces_telechargement_jours', 30) . ' jours, ' . (int)param('max_telechargements', 5) . ' téléchargements.' : '' ?> Facture envoyée par courriel. <a href="/cgv/">Conditions générales de vente</a>.</p>
      </form>
      <div class="pf-aside" style="margin-top:22px"><div><h3>Aller plus loin</h3><p>Mettez la méthode en pratique avec une formation de trois jours en petit groupe : <a class="pf-link" href="/calendrier/">voir les prochaines dates</a>.</p></div></div>
    </div>
  </div>
</div></section>
<?php page_end();
