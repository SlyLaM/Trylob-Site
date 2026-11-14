<?php
require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../background-videos.php';
try {
    importArchivedConcerts(db());
    $concerts = db()->query('SELECT date, venue, city, url FROM trylob_concerts ORDER BY date, id')->fetchAll(PDO::FETCH_ASSOC);
    $settings = db()->query('SELECT name, value FROM trylob_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    $choice = backgroundVideoChoice($settings);
    $publicSettings = array_filter($settings, 'webUrl');
    unset($publicSettings['background'], $publicSettings['background_video']);
    if ($choice === 'poster') {
        $publicSettings['backgroundPosterOnly'] = true;
    } elseif ($choice === 'legacy') {
        $publicSettings['background'] = $settings['background'];
    } elseif ($choice !== 'default') {
        $publicSettings['background'] = BACKGROUND_VIDEO_URL . $choice;
    }
    jsonResult(['concerts' => $concerts, 'settings' => (object)$publicSettings]);
} catch (Throwable $error) {
    jsonResult(['error' => 'Dates temporairement indisponibles.'], 503);
}
