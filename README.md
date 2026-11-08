# Trylob sur OVH Starter

1. Dans phpMyAdmin, sélectionnez la base OVH existante et importez `schema.sql`. Le script crée quatre tables préfixées `trylob_` et ne supprime aucune table.
2. Déposez le contenu de cette archive à la racine du dépôt destiné à OVH. Depuis Windows, GitHub permet d’ajouter les fichiers via **Add file → Upload files**. Les fichiers doivent se trouver directement à la racine de la branche déployée. Pour garder les sources Cloudflare dans `main`, créez une branche dédiée `ovh` avec uniquement le contenu de cette archive, puis sélectionnez-la dans OVH.
3. Dans OVH, associez le dépôt à un nouveau dossier racine vide, par exemple `trylob-web`. Gardez une copie de l’ancien site dans `www`. Pour un dépôt privé, utilisez la clé SSH d’OVH dans les Deploy keys du dépôt GitHub.
4. Copiez `trylob-config.example.php` sur votre ordinateur et renommez la copie en `trylob-config.php`. Renseignez les coordonnées MySQL. Générez une clé d’accès aléatoire de 32 octets ou plus et inscrivez son SHA-256 hexadécimal dans `admin_hash`. Conservez la clé originale pour vous connecter. Sur Windows, PowerShell permet de générer la paire avec les commandes ci-dessous.
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

L’espace groupe utilise une clé partagée. Les messages sont enregistrés en base et consultables dans cet espace. Il n’y a pas d’envoi d’e-mail. Les dates et messages éventuellement enregistrés sur Cloudflare restent dans D1 et doivent être repris séparément si nécessaire. Les deux dates historiques visibles dans le site sont déjà dans la page.
#
