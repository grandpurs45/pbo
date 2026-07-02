# Créer un API Token Proxmox pour PBO

L'authentification par API Token est le mode recommandé pour PBO.

Le modèle recommandé est :

```text
Utilisateur dédié : pbo@pve
Token dédié       : pbo
Token complet     : pbo@pve!pbo
```

Avec **Privilege Separation** activé, Proxmox vérifie les permissions de l'utilisateur **et** du token. Les deux doivent donc recevoir les ACL.

## Procédure via l'interface Proxmox

### 1. Créer l'utilisateur dédié

Aller dans **Datacenter > Permissions > Users > Add**.

Créer :

```text
User name : pbo
Realm     : Proxmox VE authentication server
User ID   : pbo@pve
```

### 2. Créer le rôle PBO

Aller dans **Datacenter > Permissions > Roles > Create**.

Créer un rôle `PBORole` avec :

```text
VM.Audit
VM.Config.Options
```

### 3. Donner le rôle à l'utilisateur

Aller dans **Datacenter > Permissions > Add > User Permission**.

Configurer :

```text
Path      : /
User      : pbo@pve
Role      : PBORole
Propagate : activé
```

### 4. Créer le token

Aller dans **Datacenter > Permissions > API Tokens > Add**.

Configurer :

```text
User                 : pbo@pve
Token ID             : pbo
Privilege Separation : activé
```

Copier immédiatement le **Secret**. Proxmox ne l'affiche qu'une seule fois.

### 5. Donner le rôle au token

Aller dans **Datacenter > Permissions > Add > API Token Permission**.

Configurer :

```text
Path      : /
API Token : pbo@pve!pbo
Role      : PBORole
Propagate : activé
```

## Alternative CLI

Sur un node Proxmox :

```bash
pveum user add pbo@pve --comment "PBO service account"
pveum role add PBORole -privs "VM.Audit VM.Config.Options"
pveum aclmod / -user pbo@pve -role PBORole
pveum user token add pbo@pve pbo --privsep 1
pveum aclmod / -token 'pbo@pve!pbo' -role PBORole
```

La commande de création du token affiche le secret une seule fois.

## Connexion dans PBO

Dans PBO, choisir **API Token**.

Renseigner :

```text
Utilisateur token : pbo@pve
Token ID          : pbo
Secret            : <secret généré par Proxmox>
```

Pour le premier test, vous pouvez cocher **Mode lecture seule** dans PBO. Cela bloque les écritures côté PBO, même si le token possède les droits.

## Dépannage

### Connexion OK mais 0 ressource

Vérifier que les ACL existent sur les deux entrées :

```text
pbo@pve
pbo@pve!pbo
```

Si seule l'ACL du token existe, PBO peut se connecter mais ne rien voir.

### Mode lecture seule Proxmox

Pour un token strictement lecture seule, utiliser `PVEAuditor` à la place de `PBORole` sur l'utilisateur et le token.

CLI :

```bash
pveum aclmod / -user pbo@pve -role PVEAuditor
pveum aclmod / -token 'pbo@pve!pbo' -role PVEAuditor
```

## Bonnes pratiques

- Ne pas utiliser `root@pam` en production.
- Ne jamais publier le secret du token.
- Ne pas stocker le token dans Git.
- Garder **Privilege Separation** activé.
- Donner uniquement les permissions nécessaires.
- Supprimer et recréer le token si le secret a été exposé.
