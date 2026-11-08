<?php
require __DIR__ . '/../bootstrap.php';
try {
    importArchivedConcerts(db());
    $concerts = db()->query('SELECT date, venue, city, url FROM trylob_concerts ORDER BY date, id')->fetchAll(PDO::FETCH_ASSOC);
    $settings = db()->query('SELECT name, value FROM trylob_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    jsonResult(['concerts' => $concerts, 'settings' => array_filter($settings, 'webUrl') ?: new stdClass()]);
} catch (Throwable $error) {
    jsonResult(['error' => 'Dates temporairement indisponibles.'], 503);
}
