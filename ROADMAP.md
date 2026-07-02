# Roadmap PBO

## V1 - MVP - 0.1.0

Statut : premiere release MVP publiee.

Objectif : fournir une interface stable et légère pour gérer l'ordre de démarrage natif Proxmox.

- Connexion à un cluster Proxmox.
- Affichage conditionnel des champs selon le mode d'authentification.
- Documentation de création d'un API Token Proxmox recommandé.
- Recommandation d'un utilisateur Proxmox dédié pour porter le token.
- Clarification des ACL utilisateur/token Proxmox.
- Captures d'écran pour la création du token Proxmox.
- Support VM QEMU.
- Support conteneurs LXC.
- Découverte automatique des ressources.
- Vue globale de l'ordre de démarrage.
- Modification des paramètres `startup`.
- Modification du paramètre `onboot`.
- Réorganisation par drag & drop.
- Prévisualisation de l'état actuel et de l'état après modification.
- Confirmation avant application des changements.
- Résultat détaillé par ressource après application.
- Annulation des modifications locales avant application.
- Confirmation avant annulation des modifications locales.
- Application via l'API officielle Proxmox.
- Recherche et filtres simples.
- Mode lecture seule.
- Docker et Docker Compose.
- Documentation d'installation.

## V1.1 - Ergonomie

- Édition multiple.
- Sélection multiple.
- Sauvegarde automatique des modifications.
- Amélioration des filtres.
- Amélioration de la recherche.
- Tri avancé.

## V1.2 - Visualisation

- Vue Timeline.
- Vue Kanban.
- Statistiques.
- Historique des modifications.
- Tableau de bord.

## V1.3 - Notifications

- Discord.
- Slack.
- Teams.
- Mattermost.
- Email.
- Webhooks.

## V1.4 - Profils

- Profil Production.
- Profil Maintenance.
- Profil Laboratoire.
- Export et import JSON.
- Sauvegarde des profils.

## V2.0 - Gestion des dépendances

PBO permettra de déclarer des dépendances logiques entre VM et conteneurs, puis de calculer automatiquement un ordre de démarrage cohérent par tri topologique.

Exigences principales :

- Détecter les dépendances circulaires.
- Afficher les erreurs de configuration.
- Visualiser le graphe de dépendances.
- Recalculer automatiquement l'ordre optimal.
- Générer les paramètres `startup` compatibles Proxmox.

## V2.1 - Démarrage intelligent

- Attendre qu'une VM soit réellement démarrée avant de lancer la suivante.
- Vérifier la disponibilité réseau par ping.
- Vérifier un port TCP.
- Vérifier une URL HTTP/HTTPS.
- Vérifier un service personnalisé.

## V2.2 - Cluster avancé

- Gestion avancée des clusters multi-nodes.
- Compatibilité HA.
- Contraintes de démarrage.
- Gestion des groupes de ressources.

## V3.0 - Orchestration complète

Évolution vers un orchestrateur complet de séquence de démarrage Proxmox.

- Dépendances avancées.
- Conditions de démarrage.
- Scénarios de boot.
- Profils d'infrastructure.
- API publique complète.
- Intégration avec MSM, Home Assistant, Grafana et autres outils.
- Plugins communautaires.
