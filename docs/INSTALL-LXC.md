# Installation dans un conteneur LXC

Ce mode permet de tester ou déployer PBO dans un conteneur Linux léger, par exemple sur Proxmox VE.

Statut : procédure validée sur LXC.

Les commandes ci-dessous ciblent Debian ou Ubuntu dans le conteneur.

## Prérequis LXC

- Conteneur Debian ou Ubuntu.
- Accès réseau vers le cluster Proxmox VE.
- Accès HTTP/HTTPS depuis les postes clients vers le conteneur.

## Installation des paquets

```bash
apt update
apt install -y apache2 php php-curl git
```

## Récupération du projet

```bash
cd /opt
git clone https://github.com/grandpurs45/pbo.git pbo
```

## Configuration Apache

Créer le fichier :

```text
/etc/apache2/sites-available/pbo.conf
```

Contenu :

```apache
<VirtualHost *:80>
    ServerName pbo.local
    DocumentRoot /opt/pbo/public

    <Directory /opt/pbo/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/pbo-error.log
    CustomLog ${APACHE_LOG_DIR}/pbo-access.log combined
</VirtualHost>
```

Activer le site :

```bash
a2enmod rewrite
a2ensite pbo.conf
a2dissite 000-default.conf
systemctl reload apache2
```

## Permissions

Apache doit pouvoir écrire les sessions applicatives :

```bash
mkdir -p /opt/pbo/var/sessions
chown -R www-data:www-data /opt/pbo/var
```

## Accès

L'application est disponible sur :

```text
http://<ip-du-conteneur>
```

## Mise à jour

```bash
cd /opt/pbo
git pull
chown -R www-data:www-data /opt/pbo/var
systemctl reload apache2
```

## Notes Proxmox

Le conteneur LXC n'a pas besoin d'accès privilégié pour exécuter PBO. Il doit seulement pouvoir joindre l'API Proxmox VE en HTTPS, généralement :

```text
https://<proxmox-host>:8006/api2/json
```

En production, utiliser un API Token Proxmox dédié avec des permissions minimales.
