# Changelog

## 0.1.0 - 2026-07-01

Premiere release publique du MVP PBO.

### Ajoute

- Interface web pour visualiser les VM QEMU et conteneurs LXC Proxmox.
- Connexion a Proxmox par mot de passe ou API Token.
- Mode lecture seule.
- Decouverte automatique via l'API officielle Proxmox.
- Lecture et modification de `startup`.
- Lecture et modification de `onboot` pour le demarrage automatique.
- Reorganisation par drag and drop.
- Previsualisation de l'etat actuel et de l'etat apres modification.
- Confirmation avant application des changements.
- Resultat detaille par ressource apres application.
- Affichage de la version dans l'interface depuis le fichier `VERSION`.
- Affichage explicite du mode actif : lecture seule ou ecriture.
- Captures d'ecran de presentation dans le README.
- Recherche et filtres par type, node et demarrage automatique.
- Support Docker et Docker Compose.
- Documentation d'installation Windows/XAMPP, Docker et LXC.
- Roadmap publique du projet.

### Notes

- Aucun secret n'est stocke dans le depot.
- Les identifiants et tickets Proxmox sont conserves uniquement en session PHP.
- Les sessions locales sont stockees dans `var/sessions`.
- PBO envoie uniquement les champs reellement modifies a l'API Proxmox.
- Les echecs partiels sont remontes par ressource.
- La procedure d'installation LXC a ete validee sur un conteneur Debian/Ubuntu.
- La procedure Docker est documentee mais reste a valider.
