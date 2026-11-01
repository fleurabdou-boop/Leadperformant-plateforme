<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
track('boutique');
$type = get('type', 10);
$ok = ['ebook', 'guide', 'conseils'];
$where = 'actif=1' . ($type === 'ebook' ? " AND type='ebook'" : ($type === 'guide' ? " AND type='guide'" : ($type === 'conseils' ? " AND type IN ('visio','ecrit')" : '')));
$prods = db_all("SELECT * FROM produits WHERE $where ORDER BY ordre, id");
$livres = array_filter($prods, fn($p) => in_array($p['type'], ['ebook', 'guide'], true));
$conseils = array_filter($prods, fn($p) => in_array($p['type'], ['visio', 'ecrit'], true));
$ld = array_values(array_map(fn($p) => ['@type' => 'Product', 'name' => $p['titre'], 'description' => $p['sous_titre'], 'url' => url('/boutique/' . $p['slug'] . '/'),
  'offers' => ['@type' => 'Offer', 'price' => number_format($p['prix'] / 100, 2, '.', ''), 'priceCurrency' => 'EUR', 'availability' => 'https://schema.org/InStock']], $prods));
page_start('Boutique', 'E-books et guides à télécharger, conseils personnalisés en visioconférence ou par écrit, en art oratoire et en leadership.', 'boutique', true, '/boutique/', $ld ? json_encode(['@context' => 'https://schema.org', '@graph' => $ld], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');
$tab = fn($k, $l) => '<a href="/boutique/' . ($k ? '?type=' . $k : '') . '"' . ($type === $k ? ' aria-current="page"' : '') . '>' . $l . '</a>';
?>
<section class="page-hero"><div class="wrap">
  <?= crumbs(['Boutique' => null]) ?>
  <span class="eyebrow">Boutique</span>
  <h1 class="pf-h1">Progresser à votre rythme</h1>
  <p class="pf-lede">E-books et guides à télécharger immédiatement, conseils personnalisés en visioconférence ou par écrit. Paiement sécurisé, accès par courriel, sans création de compte.</p>
  <nav class="pf-tabs" aria-label="Catégories"><?= $tab('', 'Tout') . $tab('ebook', 'E-books') . $tab('guide', 'Guides') . $tab('conseils', 'Conseils') ?></nav>
</div></section>
<section class="section paper"><div class="wrap">
<?php if (!$prods): ?><div class="pf-empty">La boutique ouvre prochainement. <a class="pf-link" href="/calendrier/">Voir les formations</a></div><?php endif; ?>
<?php if ($livres): ?>
  <h2 class="pf-h2">E-books et guides</h2>
  <div class="pf-grid" style="margin-bottom:44px">
  <?php foreach ($livres as $p): ?>
    <a class="pf-prod" href="/boutique/<?= h($p['slug']) ?>/">
      <div class="pf-cover <?= $p['type'] === 'guide' ? 'guide' : '' ?>"<?= $p['couverture'] ? ' style="background-image:url(\'/boutique/media.php?f=' . h(rawurlencode($p['couverture'])) . '\')"' : '' ?>><?php if (!$p['couverture']): ?><span class="b"><?= $p['type'] === 'guide' ? 'GUIDE PRATIQUE' : 'LEAD PERFORMANT' ?></span><span class="t"><?= h($p['titre']) ?></span><span class="r"></span><?php endif; ?></div>
      <div class="pf-prod-b"><span class="pf-kind"><?= h(TYPES_PRODUIT[$p['type']]) ?> · PDF</span><strong><?= h($p['titre']) ?></strong><span class="pf-note" style="margin:0"><?= $p['pages'] ? (int)$p['pages'] . ' pages · ' : '' ?>téléchargement immédiat</span>
        <span style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:8px"><span class="pf-price"><?= h(eur((int)$p['prix'])) ?></span><span class="pf-btn" style="min-height:40px;padding:0 16px">Voir</span></span></div>
    </a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php if ($conseils): ?>
  <h2 class="pf-h2">Conseils personnalisés</h2>
  <div class="pf-grid" style="grid-template-columns:repeat(auto-fit,minmax(300px,1fr));margin-bottom:44px">
  <?php foreach ($conseils as $p): ?>
    <div class="pf-svc"><span class="pf-kind"><?= $p['type'] === 'visio' ? 'Visioconférence · ' . (int)$p['duree_minutes'] . ' minutes' : 'Par écrit · réponse sous ' . (int)$p['delai_jours'] . ' jours ouvrés' ?></span>
      <h3 class="pf-h2" style="font-size:1.6rem;margin:0"><?= h($p['titre']) ?></h3><p style="margin:0;color:var(--pf-muted)"><?= h($p['sous_titre']) ?></p>
      <span style="display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:10px"><span class="pf-price"><?= h(eur((int)$p['prix'])) ?></span><a class="pf-btn" href="/boutique/<?= h($p['slug']) ?>/"><?= $p['type'] === 'visio' ? 'Choisir un créneau' : 'Commander' ?></a></span></div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
  <div class="pf-trust"><div><strong>Paiement sécurisé</strong>Carte bancaire sur la page de paiement Stripe ; aucune donnée de carte n’est conservée par le site.</div><div><strong>Accès immédiat</strong>Lien de téléchargement à l’écran et par courriel, valable <?= (int)param('acces_telechargement_jours', 30) ?> jours.</div><div><strong>Facture</strong>Émise par Lead Performant et envoyée automatiquement. <?= h(ORG['tva']) ?>.</div></div>
</div></section>
<?php page_end();
