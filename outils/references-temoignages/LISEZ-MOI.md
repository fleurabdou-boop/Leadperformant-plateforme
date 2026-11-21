# Références et témoignages ; Inscription aux sessions et Boutique (20/11/2026)

- Tableau de bord : rubrique « Références et témoignages » (`gestion/references.php`), tables `ref_logos` et `ref_temoignages` (étape 3 de `_lp/db.php`), fonctions communes dans `_lp/references.php`.
- Site : page `/references/` (`references/index.php`, formulaire de témoignage modéré) ; section masquée tant que rien n'est publié sur l'accueil et les pages des pôles, alimentée par `/references/flux.json`, réécrit à chaque modification.
- Logos déposés : vérifiés, recadrés, réduits et réencodés en WebP dans `references/logos/` (exécution interdite par `.htaccess`, SVG refusés).
- En-tête : « Inscription aux sessions » et « Boutique » passent dans la barre supérieure (ordinateur) et dans un bandeau à deux cases (mobile, tablette) ; la navigation garde les cinq rubriques validées.
- `appliquer.py` reproduit les modifications des pages statiques (idempotent) ; `references.css` et `references.js` sont ajoutés en fin de `site.css` et `site.js`.
- Publication directe dans `public_html` le 20/11/2026 ; sauvegarde : `sauvegardes/avant-references-20261120.zip` (copie dans `_plateforme_src/sauvegardes/` sur le serveur).
