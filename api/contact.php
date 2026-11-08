<?php
require __DIR__ . '/../bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    jsonResult(['error' => 'Méthode non autorisée.'], 405);
}
if (field('website') !== '') jsonResult(['ok' => true]);
$name = field('name');
$email = field('email');
$subject = field('subject');
$message = field('message');
if ($name === '' || strlen($name) > 120 || strlen($email) > 254 ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) || $subject === '' || strlen($subject) > 160 ||
    strlen($message) < 10 || strlen($message) > 5000) {
    jsonResult(['error' => 'Vérifiez les champs du formulaire.'], 422);
}
try {
    $db = db();
    $now = time();
    $ip = hash_hmac('sha256', $_SERVER['REMOTE_ADDR'] ?? '', config()['admin_hash']);
    $db->beginTransaction();
    $db->prepare('DELETE FROM trylob_limits WHERE expires < ?')->execute([$now]);
    $db->prepare('INSERT INTO trylob_limits (token, expires, hits) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE hits = hits + 1')->execute([$ip, $now + 3600]);
    $query = $db->prepare('SELECT hits FROM trylob_limits WHERE token = ?');
    $query->execute([$ip]);
    if ($query->fetchColumn() > 5) {
        $db->rollBack();
        jsonResult(['error' => 'Trop de demandes. Réessayez plus tard.'], 429);
    }
    $db->prepare('INSERT INTO trylob_messages (name, email, subject, message) VALUES (?, ?, ?, ?)')->execute([$name, $email, $subject, $message]);
    $db->commit();
    jsonResult(['ok' => true]);
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    jsonResult(['error' => 'Envoi temporairement indisponible.'], 503);
}
