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
