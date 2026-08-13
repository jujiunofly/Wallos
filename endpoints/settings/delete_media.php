<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/appearance.php';
require_once '../../includes/getsettings.php';

$input = json_decode(file_get_contents('php://input'), true);
$kind = ($input['kind'] ?? '') === 'background' ? 'background' : 'branding';
$filename = wallos_sanitize_app_logo($input['filename'] ?? '');

if ($filename === '') {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

if (strpos($filename, 'u' . (int) $userId . '-') !== 0) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

if (wallos_media_in_use($settings, $kind, $filename)) {
    die(json_encode(['success' => false, 'message' => translate('media_in_use', $i18n)]));
}

$dir = realpath(__DIR__ . '/../../' . wallos_media_dir($kind));
$path = $dir ? realpath($dir . DIRECTORY_SEPARATOR . $filename) : false;
if ($dir === false || $path === false || strpos($path, $dir) !== 0 || !is_file($path)) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

unlink($path);
die(json_encode([
    'success' => true,
    'message' => translate('success', $i18n),
    'media' => [
        'background' => wallos_list_media($userId, 'background'),
        'branding' => wallos_list_media($userId, 'branding'),
    ],
]));
