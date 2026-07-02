# Créer un API Token Proxmox pour PBO

L'authentification par API Token est le mode recommandé pour utiliser PBO.

Elle évite d'utiliser un mot de passe interactif, permet de limiter les permissions, et facilite le déploiement dans Docker ou LXC.

Il est recommandé de créer un utilisateur Proxmox dédié à PBO, par exemple `pbo@pve`, puis de créer un token rattaché à cet utilisateur.

## Principe

PBO accepte deux modes de connexion :

- utilisateur / mot de passe ;
- API Token.

Pour une utilisation durable, préférer **API Token**.

Le format attendu par Proxmox est :

```text
utilisateur@realm!tokenid
```

Dans PBO, les champs sont séparés :

- `Utilisateur token` : `utilisateur@realm`, par exemple `pbo@pve`;
- `Token ID` : le nom du token, par exemple `pbo`;
- `Secret` : le secret généré par Proxmox.

## Créer un utilisateur dédié

Dans l'interface Proxmox VE :

1. Aller dans **Datacenter**.
2. Ouvrir **Permissions**.
3. Aller dans **Users**.
4. Cliquer sur **Add**.
5. Créer un utilisateur dédié, par exemple :

```text
User name : pbo
Realm     : Proxmox VE authentication server
User ID   : pbo@pve
```

Ce compte n'a pas besoin d'être utilisé pour une connexion interactive.

## Créer le token

Dans l'interface Proxmox VE :

1. Aller dans **Datacenter**.
2. Ouvrir **Permissions**.
3. Aller dans **API Tokens**.
4. Cliquer sur **Add**.
5. Choisir l'utilisateur Proxmox.
6. Définir un **Token ID**, par exemple :

```text
pbo
```

7. Activer **Privilege Separation**.
8. Valider et copier immédiatement le **Secret**.

Le secret n'est affiché qu'une seule fois par Proxmox.

## Alternative en ligne de commande

Les mêmes opérations peuvent être faites en CLI sur un node Proxmox :

```bash
pveum user add pbo@pve --comment "PBO service account"
pveum user token add pbo@pve pbo --privsep 1
```

La commande de création du token affiche le secret une seule fois.

## Permissions recommandées

Avec **Privilege Separation** activé, le token doit recevoir ses propres permissions.

### Mode lecture seule

Pour tester PBO sans permettre de modification :

- Path : `/`
- Role : `PVEAuditor`
- Propagate : activé

Ce mode permet la découverte et la lecture des configurations.

### Mode écriture

Pour permettre à PBO de modifier `startup` et `onboot`, créer idéalement un rôle dédié avec les privilèges suivants :

```text
VM.Audit
VM.Config.Options
```

Puis attribuer ce rôle au token :

- Path : `/`
- Role : rôle dédié PBO
- Propagate : activé

Si vous voulez limiter PBO à certaines VM/LXC, attribuer la permission sur un chemin plus restrictif que `/`, selon votre organisation Proxmox.

Exemple CLI pour un rôle dédié :

```bash
pveum role add PBORole -privs "VM.Audit VM.Config.Options"
pveum aclmod / -token 'pbo@pve!pbo' -role PBORole
```

Exemple CLI lecture seule :

```bash
pveum aclmod / -token 'pbo@pve!pbo' -role PVEAuditor
```

## Connexion dans PBO

Dans PBO :

1. Sélectionner **API Token**.
2. Renseigner l'URL Proxmox, par exemple :

```text
https://proxmox.example.local:8006
```

3. Renseigner :

```text
Utilisateur token : pbo@pve
Token ID          : pbo
Secret            : <secret généré par Proxmox>
```

4. Utiliser le **mode lecture seule** pour un premier test.
5. Décocher la vérification TLS uniquement si Proxmox utilise un certificat autosigné pendant les tests.

## Bonnes pratiques

- Ne jamais publier le secret du token.
- Ne pas stocker le token dans Git.
- Créer un utilisateur dédié à PBO, par exemple `pbo@pve`.
- Créer un token dédié à PBO.
- Garder **Privilege Separation** activé.
- Donner uniquement les permissions nécessaires.
- Supprimer et recréer le token si le secret a été exposé.

Éviter d'utiliser un token rattaché à `root@pam` en production. Cela peut dépanner pendant un test rapide, mais ce n'est pas le modèle recommandé.
