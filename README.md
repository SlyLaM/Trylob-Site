# Trylob sur OVH Starter

1. Dans phpMyAdmin, sélectionnez la base OVH existante et importez `schema.sql`. Le script crée quatre tables préfixées `trylob_` et ne supprime aucune table.
2. Déposez le contenu de cette archive à la racine du dépôt destiné à OVH. Depuis Windows, GitHub permet d’ajouter les fichiers via **Add file → Upload files**. Les fichiers doivent se trouver directement à la racine de la branche déployée. Pour garder les sources Cloudflare dans `main`, créez une branche dédiée `ovh` avec uniquement le contenu de cette archive, puis sélectionnez-la dans OVH.
3. Dans OVH, associez le dépôt à un nouveau dossier racine vide, par exemple `trylob-web`. Gardez une copie de l’ancien site dans `www`. Pour un dépôt privé, utilisez la clé SSH d’OVH dans les Deploy keys du dépôt GitHub.
4. Copiez `trylob-config.template.php` sur votre ordinateur et renommez la copie en `trylob-config.php`. Renseignez les coordonnées MySQL. Générez une clé d’accès aléatoire de 32 octets ou plus et inscrivez son SHA-256 hexadécimal dans `admin_hash`. Conservez la clé originale pour vous connecter. Sur Windows, PowerShell permet de générer la paire avec les commandes ci-dessous.
5. Envoyez uniquement le fichier de configuration rempli par FTP au niveau parent de `trylob-web`, à côté de `www`. Il doit rester hors de tous les dossiers racines des sites et hors de GitHub.
6. Déployez Git et ouvrez le site en HTTPS. Cliquez sur **Espace groupe** pour ajouter une date, configurer Bandcamp et lire les demandes de contact. Envoyez un message de test et vérifiez qu’il apparaît dans l’espace groupe.

PowerShell 5.1 ou ultérieur

```powershell
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
$rng.Dispose()
$key = [Convert]::ToBase64String($bytes)
$sha = [System.Security.Cryptography.SHA256]::Create()
$hash = [BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($key))).Replace('-', '').ToLowerInvariant()
$sha.Dispose()
Write-Output "Clé à conserver : $key"
Write-Output "Valeur admin_hash : $hash"
```

L’espace groupe utilise une clé partagée. Les messages sont enregistrés en base et consultables dans cet espace. Les dates et messages éventuellement enregistrés sur Cloudflare restent dans D1 et doivent être repris séparément si nécessaire. Les deux dates historiques visibles dans le site sont déjà dans la page.

## CAPTCHA du formulaire de contact

Avant de fusionner la PR ou de déployer le site sur OVH, créez un widget [Cloudflare Turnstile](https://dash.cloudflare.com/?to=/:account/turnstile) en mode **Non-interactive** pour le domaine `trylob.band`. La case du formulaire lance la vérification.

Ajoutez ces entrées au tableau du fichier `trylob-config.php` existant sur OVH, dans le dossier parent de la racine du site.

```php
'turnstile_site_key' => 'clé publique du widget',
'turnstile_secret_key' => 'clé secrète du widget',
'turnstile_hostname' => 'trylob.band',
```

Indiquez le nom d’hôte utilisé par les visiteurs, sans protocole ni chemin. Si l’hébergement répond aussi sur un alias, redirigez-le vers le nom d’hôte configuré. Le fichier de configuration rempli doit rester hors de la racine publique et de Git. PHP utilise l’extension cURL pour joindre `challenges.cloudflare.com` en HTTPS. Le formulaire refuse les envois lorsque la vérification est indisponible.

Après déploiement, ouvrez le formulaire sans cocher la case et vérifiez que l’envoi est bloqué. Cochez ensuite la case, attendez la confirmation de la vérification et envoyez un message de test. Vérifiez sa présence dans l’espace groupe et dans Telegram si la notification est activée.

## Notifications du formulaire

Après enregistrement, une copie est envoyée à `trylobmusic@gmail.com` via la fonction PHP `mail()` d’OVH, en plus de la notification Telegram si elle est configurée. Le bouton « Répondre » dans Gmail utilise l’adresse saisie par le visiteur.

L’expéditeur par défaut est `no-reply@trylob.band`. Si vous utilisez une autre adresse d’envoi pour ce domaine, ajoutez `'contact_mail_from' => 'adresse@trylob.band'` dans `trylob-config.php` sur OVH.

Après déploiement, envoyez un message de test et vérifiez sa réception dans Gmail et Telegram ainsi que sa présence dans l’espace groupe. En cas de problème de réception, consultez le dossier Spam et le suivi des e-mails automatisés de l’hébergement OVH. Un échec de notification laisse le message accessible dans l’espace groupe.

## Vidéo de fond

Dans **Espace groupe → Vidéo de fond**, importez un fichier MP4 (H.264 recommandé), choisissez-le dans le menu et cliquez sur **Appliquer au site**. La taille maximale est affichée dans le formulaire à partir des limites PHP de l’hébergement, avec un plafond de 100 Mo. Le formulaire permet aussi de revenir à la vidéo d’origine ou d’afficher l’image fixe.

La suppression retire le fichier importé du serveur. Si la vidéo était active, le site revient à la vidéo d’origine. Le choix utilise la table de réglages existante. Le serveur crée le dossier `uploads/background-videos/` au premier import, et les fichiers sont stockés sur OVH. Incluez ce dossier dans les sauvegardes et conservez-le si vous remplacez le dossier de déploiement.

Après déploiement, connectez-vous à l’espace groupe, importez un petit MP4, appliquez-le puis ouvrez la page publique. Supprimez ensuite cette vidéo et vérifiez que la vidéo d’origine revient.
