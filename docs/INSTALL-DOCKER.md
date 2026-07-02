# Installation Docker

Ce mode est recommandé pour un déploiement simple et reproductible.

Statut : procédure documentée, pas encore validée.

## Prérequis

- Docker.
- Docker Compose.
- Un accès réseau depuis le conteneur vers l'API Proxmox VE sur le port `8006`.

## Démarrage

Depuis la racine du projet :

```bash
docker compose up --build -d
```

L'application est disponible sur :

```text
http://localhost:8080
```

## Arrêt

```bash
docker compose down
```

## Mise à jour

```bash
git pull
docker compose up --build -d
```

## Port exposé

Le fichier `docker-compose.yml` expose par défaut :

```yaml
ports:
  - "8080:80"
```

Pour publier PBO sur un autre port local, modifier la partie gauche.

Exemple :

```yaml
ports:
  - "8090:80"
```

## Production

Pour une exposition en production, placer idéalement PBO derrière un reverse proxy HTTPS comme Nginx, Traefik, Caddy ou Apache.

Points recommandés :

- HTTPS obligatoire côté utilisateur.
- API Token Proxmox dédié.
- Permissions Proxmox limitées aux ressources nécessaires.
- Vérification TLS Proxmox activée avec un certificat valide.

Guide recommandé : [PROXMOX-API-TOKEN.md](PROXMOX-API-TOKEN.md).
