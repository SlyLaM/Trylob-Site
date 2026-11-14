<?php
declare(strict_types=1);

const BACKGROUND_VIDEO_DIRECTORY = __DIR__ . '/uploads/background-videos';
const BACKGROUND_VIDEO_URL = 'uploads/background-videos/';
const BACKGROUND_VIDEO_MAX_BYTES = 100 * 1024 * 1024;

function backgroundVideoLimit(): int {
    if (!filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN)) return 0;

    $limit = BACKGROUND_VIDEO_MAX_BYTES;
    foreach (['upload_max_filesize', 'post_max_size'] as $key) {
        $value = strtolower(trim((string)ini_get($key)));
        $multiplier = ['g' => 1024 ** 3, 'm' => 1024 ** 2, 'k' => 1024][substr($value, -1)] ?? 1;
        $bytes = (int)$value * $multiplier;
        if ($bytes > 0) {
            // Leave room for the CSRF field and multipart headers.
            $limit = min($limit, max(0, $bytes - ($key === 'post_max_size' ? 64 * 1024 : 0)));
        }
    }
    return $limit;
}

function backgroundVideoExists(string $file): bool {
    if (!preg_match('/\A[a-f0-9]{32}--[a-zA-Z0-9][a-zA-Z0-9_-]{0,99}\.mp4\z/', $file)) return false;
    $path = BACKGROUND_VIDEO_DIRECTORY . '/' . $file;
    return !is_link($path) && is_file($path);
}

function backgroundVideoFiles(): array {
    $videos = [];
    foreach (glob(BACKGROUND_VIDEO_DIRECTORY . '/*.mp4') ?: [] as $path) {
        $file = basename($path);
        if (backgroundVideoExists($file)) $videos[$file] = substr($file, 34, -4);
    }
    asort($videos, SORT_NATURAL | SORT_FLAG_CASE);
    return $videos;
}

function uploadBackgroundVideo(array $upload): void {
    $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new DomainException('Le fichier dépasse la taille maximale autorisée.');
    }
    if ($error !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) ||
        !is_uploaded_file($upload['tmp_name']) || !is_string($upload['name'] ?? null)) {
        throw new DomainException('Choisissez un fichier et vérifiez que le transfert est terminé.');
    }

    $temporary = $upload['tmp_name'];
    $bytes = filesize($temporary);
    if ($bytes === false || $bytes > backgroundVideoLimit()) {
        throw new DomainException('Le fichier dépasse la taille maximale autorisée.');
    }
    if ($bytes < 12) throw new DomainException('Le fichier MP4 est vide ou incomplet.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
    if (strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION)) !== 'mp4' || $mime !== 'video/mp4' ||
        file_get_contents($temporary, false, null, 4, 4) !== 'ftyp') {
        throw new DomainException('Choisissez une vidéo au format MP4.');
    }

    $stem = pathinfo($upload['name'], PATHINFO_FILENAME);
    $stem = trim(substr(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $stem), 0, 100), '-_');
    $file = bin2hex(random_bytes(16)) . '--' . ($stem ?: 'video') . '.mp4';
    if (!is_dir(BACKGROUND_VIDEO_DIRECTORY) &&
        !@mkdir(BACKGROUND_VIDEO_DIRECTORY, 0755, true) && !is_dir(BACKGROUND_VIDEO_DIRECTORY)) {
        throw new DomainException('Impossible de créer le dossier des vidéos sur l’hébergement.');
    }
    if (!move_uploaded_file($temporary, BACKGROUND_VIDEO_DIRECTORY . '/' . $file)) {
        throw new DomainException('Impossible d’enregistrer la vidéo sur l’hébergement.');
    }
}

function backgroundVideoChoice(array $settings): string {
    $choice = $settings['background_video'] ?? '';
    if (in_array($choice, ['default', 'poster'], true) || backgroundVideoExists($choice)) return $choice;
    if ($choice === '' && webUrl($settings['background'] ?? '')) return 'legacy';
    return 'default';
}
