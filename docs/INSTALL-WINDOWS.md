# Installation locale Windows avec XAMPP

Ce mode correspond a l'environnement de developpement local principal du projet.

## Contexte local

- OS : Windows.
- Serveur local : XAMPP.
- Chemin projet utilise en developpement :

```text
C:\dev\xampp\htdocs\PBO
```

- Nom local declare dans le fichier `hosts` Windows :

```text
127.0.0.1 pbo.local
```

## Prerequis

- XAMPP avec Apache et PHP.
- Extension PHP `curl` activee.
- Acces reseau vers l'API Proxmox VE sur le port `8006`.

Verifier l'extension `curl` :

```powershell
php -m
```

La liste doit contenir :

```text
curl
```

## Configuration Apache XAMPP

Apache doit servir le dossier `public/`, pas la racine du projet.

Ajouter ou adapter un VirtualHost Apache :

```apache
<VirtualHost *:80>
    ServerName pbo.local
    DocumentRoot "C:/dev/xampp/htdocs/PBO/public"

    <Directory "C:/dev/xampp/htdocs/PBO/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Selon l'installation XAMPP, ce bloc peut etre place dans :

```text
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

ou dans le chemin equivalent de ton installation XAMPP.

Verifier aussi que cette ligne est active dans `httpd.conf` :

```apache
Include conf/extra/httpd-vhosts.conf
```

Redemarrer Apache depuis le panneau XAMPP apres modification.

## Acces local

L'application est disponible sur :

```text
http://pbo.local
```

## Depannage

### `http://pbo.local` affiche l'arborescence du projet

Apache sert la racine du projet au lieu du dossier `public/`.

La configuration attendue est :

```apache
DocumentRoot "C:/dev/xampp/htdocs/PBO/public"
```

et non :

```apache
DocumentRoot "C:/dev/xampp/htdocs/PBO"
```

Apres correction du VirtualHost, redemarrer Apache depuis XAMPP.

Si l'arborescence reste affichee, verifier que le fichier suivant est bien inclus par Apache :

```text
C:\xampp\apache\conf\extra\httpd-vhosts.conf
```

Dans `httpd.conf`, la ligne suivante ne doit pas etre commentee :

```apache
Include conf/extra/httpd-vhosts.conf
```

## Sessions PHP

PBO force le stockage des sessions dans le projet :

```text
var/sessions
```

Cela evite les problemes de permissions avec le dossier temporaire global de XAMPP.

## Alternative : serveur PHP integre

Pour tester sans Apache :

```powershell
php -S 127.0.0.1:8080 -t public
```

Acces :

```text
http://127.0.0.1:8080
```

## Notes de developpement

- Le code ne depend pas de Composer pour le MVP.
- Les assets frontend sont servis directement depuis `public/`.
- Les appels a Proxmox passent exclusivement par l'API officielle `/api2/json`.
- L'identifiant Proxmox doit inclure le realm, par exemple `root@pam` et non `root`.
- En cas de certificat autosigne Proxmox, decocher la verification TLS dans l'interface pendant les tests locaux.
