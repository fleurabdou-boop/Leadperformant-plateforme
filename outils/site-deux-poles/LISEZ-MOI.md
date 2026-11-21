# Site à deux pôles (novembre 2026)

Scripts qui ont produit les pages de `refonte/` à partir de la maquette Claude Design
« Lead Performant — maquette du site à deux pôles » et des pages en ligne.

- `theme.css` : couche ajoutée en fin de `assets/lp/site.css` (polices, palette, en-tête, pied de page, nouvelles pages).
- `shell.js` : module placé en tête de `assets/lp/site.js` (en-tête des pages anciennes, menu, vidéos, filtres, formulaire à deux pôles, Boussole relationnelle, contenus de l'espace de gestion).
- `pages.py` : pages de la maquette ; `legacy_pages.py` : pages conservées, envoi des formulaires, plan du site, gabarit PHP, règles serveur.

Publication du 20/11/2026 : fichiers déposés directement dans `public_html` ; sauvegarde des fichiers remplacés
dans `sauvegardes/avant-site-deux-poles-20261120.zip` (copie aussi dans `_plateforme_src/sauvegardes/` sur le serveur).
`serveur/htaccess-racine.conf` est le `.htaccess` racine en ligne après publication.
