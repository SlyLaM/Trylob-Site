<?php
require __DIR__ . '/../bootstrap.php';
function notifyTelegram(string $name, string $email, string $subject, string $message): void {
    $settings = config();
    $token = (string)($settings['telegram_bot_token'] ?? '');
    $chatId = (string)($settings['telegram_chat_id'] ?? '');
    if ($token === '' || $chatId === '') return;

    try {
        if (!preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/D', $token) ||
            !preg_match('/^-[0-9]+$/D', $chatId)) {
            throw new RuntimeException('Invalid Telegram configuration');
        }

        $heading = "Nouveau message · Trylob\nNom : {$name}\nE-mail : {$email}\nObjet : {$subject}\n\n";
        $body = json_decode(json_encode($message, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR), true);
        preg_match_all('/.{1,1600}/us', $body, $parts);
        $request = curl_init("https://api.telegram.org/bot{$token}/sendMessage");
        if ($request === false) throw new RuntimeException('Telegram request initialization failed');

        try {
            curl_setopt_array($request, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 1000,
                CURLOPT_TIMEOUT_MS => 2500,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            ]);
            foreach ($parts[0] as $index => $part) {
                $prefix = $index === 0 ? $heading : "Suite du message · Trylob\n\n";
                curl_setopt($request, CURLOPT_POSTFIELDS, json_encode([
                    'chat_id' => $chatId,
                    'text' => $prefix . $part,
                    'link_preview_options' => ['is_disabled' => true],
                ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
                $reply = curl_exec($request);
                if ($reply === false || curl_getinfo($request, CURLINFO_HTTP_CODE) !== 200 ||
                    empty(json_decode($reply, true)['ok'])) {
                    throw new RuntimeException('Telegram rejected the notification');
                }
            }
        } finally {
            curl_close($request);
        }
    } catch (Throwable $error) {
        error_log('Trylob: notification Telegram non envoyée ou incomplète.');
    }
}

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
    notifyTelegram($name, $email, $subject, $message);
    jsonResult(['ok' => true]);
} catch (Throwable $error) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    jsonResult(['error' => 'Envoi temporairement indisponible.'], 503);
}
