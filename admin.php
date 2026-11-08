<?php
require __DIR__ . '/bootstrap.php';
header('Cache-Control: no-store');
session_set_cookie_params(['secure' => true, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];
$loggedIn = $_SESSION['authenticated'] ?? false;
$error = '';
$concerts = $messages = $settings = [];
$links = ['clip' => 'Clip YouTube', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'bandcamp' => 'Bandcamp', 'background' => 'Vidéo de fond'];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($csrf, field('csrf'))) {
            http_response_code(403);
            exit('Session expirée. Rechargez la page.');
        }
        $action = field('action');
        if (!$loggedIn) {
            $secret = field('password');
            $adminKey = config()['admin_hash'];
            $valid = $adminKey !== '' && strlen($secret) <= 128 && hash_equals($adminKey, hash('sha256', $secret));
            if (!$valid) throw new DomainException('Clé incorrecte.');
            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            unset($_SESSION['csrf']);
        } elseif ($action === 'logout') {
            session_destroy();
        } elseif ($action === 'save') {
            $date = field('date');
            $venue = field('venue');
            $city = field('city');
            $url = field('url');
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (!$parsed || $parsed->format('Y-m-d') !== $date || $date < '1000-01-01' ||
                $venue === '' || strlen($venue) > 160 || $city === '' || strlen($city) > 160 ||
                ($url !== '' && (!webUrl($url) || strlen($url) > 2048))) {
                throw new DomainException('Vérifiez la date, le lieu, la ville et le lien.');
            }
            $id = (int)field('id');
            if ($id > 0) {
                db()->prepare('UPDATE trylob_concerts SET date=?, venue=?, city=?, url=? WHERE id=?')->execute([$date, $venue, $city, $url, $id]);
            } else {
                db()->prepare('INSERT INTO trylob_concerts (date, venue, city, url) VALUES (?, ?, ?, ?)')->execute([$date, $venue, $city, $url]);
            }
        } elseif ($action === 'remove') {
            db()->prepare('DELETE FROM trylob_concerts WHERE id=?')->execute([(int)field('id')]);
        } elseif ($action === 'remove_message') {
            db()->prepare('DELETE FROM trylob_messages WHERE id=?')->execute([(int)field('id')]);
        } elseif ($action === 'settings') {
            $values = [];
            foreach ($links as $key => $label) {
                $value = field($key);
                if ($value !== '' && (!webUrl($value) || strlen($value) > 2048)) {
                    throw new DomainException('Lien invalide pour ' . $label . '.');
                }
                $values[$key] = $value;
            }
            $db = db();
            $db->beginTransaction();
            $query = $db->prepare('INSERT INTO trylob_settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value=?');
            foreach ($values as $key => $value) $query->execute([$key, $value, $value]);
            $db->commit();
        }
        header($action === 'remove_message' ? 'Location: admin.php#messages' : 'Location: admin.php', true, 303);
        exit;
    }
} catch (DomainException $problem) {
    $error = $problem->getMessage();
} catch (Throwable $problem) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    $error = 'Action impossible. Vérifiez la configuration.';
}
if ($loggedIn) {
    try {
        $concerts = db()->query('SELECT * FROM trylob_concerts ORDER BY date DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $messages = db()->query('SELECT * FROM trylob_messages ORDER BY id DESC')->fetchAll(PDO::FETCH_ASSOC);
        $settings = db()->query('SELECT name, value FROM trylob_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Throwable $problem) {
        $error = 'Connexion à la base impossible. Vérifiez la configuration et le schéma SQL.';
    }
}
?>
<!doctype html>
<html lang="fr">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Espace groupe · Trylob</title>
<style>
body{background:#101010;color:#eee;font:16px system-ui;margin:2rem auto;max-width:62rem;padding:0 1rem}
a{color:#ffda2d} form,article{border:1px solid #444;padding:1rem;margin:1rem 0}
label{display:block;margin-bottom:.65rem} input{display:block;width:min(100%,36rem);padding:.4rem;box-sizing:border-box}
button{padding:.5rem .8rem;cursor:pointer} .message{white-space:pre-wrap;overflow-wrap:anywhere}
</style>
<a href="./">Retour au site</a>
<h1>Espace groupe</h1>
<?php if ($error !== ''): ?><p role="alert"><?= h($error) ?></p><?php endif ?>
<?php if (!$loggedIn): ?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <label>Clé d’accès <input type="password" name="password" required maxlength="128" autocomplete="current-password"></label>
    <button>Connexion</button>
</form>
<?php else: ?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <button name="action" value="logout">Déconnexion</button>
</form>
<h2>Concerts</h2>
<?php foreach (array_merge([['id'=>0,'date'=>'','venue'=>'','city'=>'','url'=>'']], $concerts) as $concert): ?>
<form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <input type="hidden" name="id" value="<?= (int)$concert['id'] ?>">
    <label>Date <input type="date" name="date" required value="<?= h($concert['date']) ?>"></label>
    <label>Lieu <input name="venue" required maxlength="160" value="<?= h($concert['venue']) ?>"></label>
    <label>Ville <input name="city" required maxlength="160" value="<?= h($concert['city']) ?>"></label>
    <label>Lien <input type="url" name="url" maxlength="2048" value="<?= h($concert['url']) ?>"></label>
    <button name="action" value="save"><?= $concert['id'] ? 'Enregistrer' : 'Ajouter un concert' ?></button>
    <?php if ($concert['id']): ?>
    <button name="action" value="remove" formnovalidate onclick="return confirm('Supprimer cette date ?')">Supprimer</button>
    <?php endif ?>
</form>
<?php endforeach ?>
<h2>Liens du site</h2>
<form method="post">
    <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
    <?php foreach ($links as $key => $label): ?>
    <label><?= h($label) ?><input type="url" name="<?= h($key) ?>" maxlength="2048" value="<?= h($settings[$key] ?? '') ?>"></label>
    <?php endforeach ?>
    <button name="action" value="settings">Enregistrer les liens</button>
</form>
<h2 id="messages">Messages reçus</h2>
<?php if (!$messages): ?><p>Aucun message.</p><?php endif ?>
<?php foreach ($messages as $message): ?>
<article>
    <h3><?= h($message['subject']) ?></h3>
    <p><?= h($message['created']) ?> · <?= h($message['name']) ?> · <a href="mailto:<?= h($message['email']) ?>"><?= h($message['email']) ?></a></p>
    <p class="message"><?= h($message['message']) ?></p>
    <form method="post" onsubmit="return confirm('Supprimer définitivement ce message ?')">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
        <button type="submit" name="action" value="remove_message">Supprimer le message</button>
    </form>
</article>
<?php endforeach ?>
<?php endif ?>
</html>
