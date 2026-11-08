<?php
declare(strict_types=1);

function config(): array {
    static $config;
    return $config ??= require dirname(__DIR__) . '/trylob-config.php';
}
function db(): PDO {
    static $connection;
    if ($connection === null) {
        $c = config();
        $connection = new PDO(
            "mysql:host={$c['host']};dbname={$c['database']};charset=utf8mb4",
            $c['user'], $c['password'], [PDO::ATTR_EMULATE_PREPARES => false]
        );
    }
    return $connection;
}
function importArchivedConcerts(PDO $db): void {
    $marker = 'archived_concerts_imported';
    $check = $db->prepare('SELECT 1 FROM trylob_settings WHERE name=?');
    $check->execute([$marker]);
    if ($check->fetchColumn()) return;

    $db->beginTransaction();
    try {
        $claim = $db->prepare("INSERT IGNORE INTO trylob_settings (name, value) VALUES (?, '1')");
        $claim->execute([$marker]);
        if ($claim->rowCount() === 1) {
            $insert = $db->prepare(
                'INSERT INTO trylob_concerts (date, venue, city, url)
                 SELECT ?, ?, ?, ? FROM DUAL
                 WHERE NOT EXISTS (SELECT 1 FROM trylob_concerts WHERE date=? AND venue=?)'
            );
            $insert->execute([
                '2026-06-06', "L'Établi", 'Ouverture de saison · Massongy',
                'https://lac-chablais.fr/event/ouverture-de-letabli-2/', '2026-06-06', "L'Établi"
            ]);
            $insert->execute([
                '2024-08-24', 'HL Fest', 'Habère-Lullin · Vallée Verte',
                'https://www.alpesduleman.com/medias/documents/newsletter/2024/Trame.pdf', '2024-08-24', 'HL Fest'
            ]);
        }
        $db->commit();
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}
function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function field(string $name): string {
    return is_string($_POST[$name] ?? null) ? trim($_POST[$name]) : '';
}
function webUrl(string $url): bool {
    return filter_var($url, FILTER_VALIDATE_URL) &&
        in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true);
}
function jsonResult(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
