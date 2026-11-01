<?php
require dirname(__DIR__) . '/_lp/bootstrap.php';
$r = (int)param('delai_retractation_jours', 10); $fp = (int)param('fermeture_particuliers_jours', 14); $dm = (int)param('decision_maintien_jours', 10);
page_start('Conditions générales de vente', 'Conditions générales de vente des formations et de la boutique de Lead Performant.', '', true, '/cgv/');
$nda = mention_nda();
?>
<section class="page-hero"><div class="wrap"><?= crumbs(['Conditions générales de vente' => null]) ?><h1 class="pf-h1">Conditions générales de vente</h1><p class="pf-note">En vigueur au <?= h(date_fr('2026-11-01')) ?>.</p></div></section>
<section class="section paper"><div class="wrap pf-narrow pf-card" style="line-height:1.7">
<h2 class="pf-h2">1. Vendeur</h2>
<p><?= h(ORG['entite']) ?>, <?= h(ORG['adresse']) ?>, SIRET <?= h(ORG['siret']) ?>, immatriculé au Registre national des entreprises. <?= h(ORG['tva']) ?>. Contact : <?= h(ORG['email']) ?>, <?= h(ORG['tel']) ?>. <?= h($nda) ?> Toutes les ventes réalisées sur le site sont conclues et facturées par Lead Performant.</p>
<h2 class="pf-h2">2. Objet</h2>
<p>Les présentes conditions s’appliquent aux formations interentreprises proposées sur le calendrier du site, ainsi qu’aux produits et services de la boutique (livres numériques, guides, conseils en visioconférence et conseils écrits). Les formations organisées pour une seule organisation font l’objet d’une proposition et d’une convention particulières.</p>
<h2 class="pf-h2">3. Formations : inscription d’un particulier qui finance lui-même sa formation</h2>
<p>L’inscription s’effectue en ligne et donne lieu à un contrat de formation professionnelle conforme aux articles L6353-3 à L6353-7 du Code du travail, signé électroniquement. La signature réserve la place. Le stagiaire dispose d’un délai de <?= $r ?> jours à compter de la signature pour se rétracter, sans frais ni motif, au moyen du lien reçu par courriel ou par lettre recommandée avec avis de réception. Aucune somme n’est exigée avant l’expiration de ce délai. À son terme, un acompte égal à 30 % du prix est payable dans les soixante-douze heures par carte bancaire ; à défaut, la place est libérée. Le solde est réglé en deux échéances, le premier et le dernier jour de la formation. Les inscriptions individuelles ferment <?= $fp ?> jours avant le début de la session.</p>
<h2 class="pf-h2">4. Formations : inscription financée par un employeur</h2>
<p>La demande en ligne bloque les places pendant sept jours. Lead Performant adresse un devis et une convention de formation, signée en ligne par une personne habilitée. La facture est émise à la signature et réglée par carte bancaire ou par virement, au plus tard le premier jour de la formation. Le remplacement d’un participant est possible sans frais jusqu’au début de la formation.</p>
<h2 class="pf-h2">5. Prix</h2>
<p>Les prix des formations sont communiqués au cours de l’inscription, avant tout engagement, puis dans le contrat ou la convention. Les prix de la boutique sont affichés sur chaque fiche produit. Ils s’entendent nets, <?= h(mb_strtolower(ORG['tva'])) ?>. Les supports pédagogiques remis pendant la formation sont compris dans le prix.</p>
<h2 class="pf-h2">6. Paiement</h2>
<p>Les paiements par carte s’effectuent sur la page sécurisée du prestataire Stripe ; Lead Performant n’a jamais connaissance des données de carte. Une inscription ou une commande n’est considérée comme payée qu’après confirmation du prestataire de paiement. Une facture est adressée par courriel pour chaque paiement. Pour les professionnels, tout retard de paiement entraîne des pénalités au taux de trois fois l’intérêt légal et une indemnité forfaitaire de 40 € pour frais de recouvrement.</p>
<h2 class="pf-h2">7. Maintien, report et annulation par Lead Performant</h2>
<p>Une session est maintenue lorsqu’elle réunit au moins sept participants ayant signé leur contrat ou leur convention <?= $dm ?> jours avant son début ; elle accueille quinze participants au plus. À défaut, Lead Performant peut la reporter ou l’annuler. En cas de report, chaque participant choisit entre la nouvelle date et le remboursement intégral ; en cas d’annulation, les sommes versées sont intégralement remboursées sous quatorze jours. Aucune autre indemnité n’est due.</p>
<h2 class="pf-h2">8. Absence, abandon et force majeure</h2>
<p>Si le stagiaire est empêché de suivre la formation par un cas de force majeure dûment reconnu, seules les prestations effectivement dispensées sont dues, au prorata temporis. En cas d’abandon pour un autre motif, les prestations effectivement dispensées restent dues au prorata temporis ; les sommes versées au-delà sont remboursées. Pour les employeurs, les heures non suivies du fait du participant restent dues.</p>
<h2 class="pf-h2">9. Boutique : livraison et droit de rétractation</h2>
<p>Les livres numériques et guides sont livrés par un lien de téléchargement personnel, affiché après le paiement et envoyé par courriel, valable <?= (int)param('acces_telechargement_jours', 30) ?> jours pour <?= (int)param('max_telechargements', 5) ?> téléchargements. Conformément à l’article L221-28 du Code de la consommation, l’acheteur qui demande l’accès immédiat au contenu numérique et renonce expressément à son droit de rétractation ne peut plus l’exercer une fois l’accès fourni. Pour les conseils, l’acheteur dispose d’un délai de rétractation de quatorze jours ; s’il demande que la prestation commence avant la fin de ce délai, il perd ce droit une fois la prestation entièrement exécutée et, s’il se rétracte avant, il reste redevable de la part déjà exécutée. Un rendez-vous de visioconférence peut être déplacé une fois, sur demande adressée au moins quarante-huit heures à l’avance.</p>
<h2 class="pf-h2">10. Propriété intellectuelle</h2>
<p>Les contenus des formations, les livres numériques, les guides et les méthodes de Lead Performant sont protégés. Ils sont concédés pour un usage strictement personnel ; toute reproduction, diffusion ou revente est interdite sans autorisation écrite.</p>
<h2 class="pf-h2">11. Données personnelles</h2>
<p>Les données recueillies sont nécessaires à l’inscription, à la facturation et au suivi pédagogique. Leur traitement est décrit dans la <a class="pf-link" href="/confidentialite/">politique de confidentialité</a>.</p>
<h2 class="pf-h2">12. Réclamations et droit applicable</h2>
<p>Toute réclamation est à adresser à <?= h(ORG['email']) ?> ; une réponse est apportée sous quinze jours. Les présentes conditions sont soumises au droit français. À défaut d’accord amiable, le litige est porté devant la juridiction compétente.</p>
</div></section>
<?php page_end();
