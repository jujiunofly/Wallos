<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/appearance.php';

wallos_ensure_appearance_schema($db);

$slots = ['bg_desktop_light', 'bg_desktop_dark', 'bg_mobile_light', 'bg_mobile_dark'];
$presets = array_keys(wallos_background_presets());

function wallos_sanitize_bg_value($raw, $presets)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (strpos($raw, 'preset:') === 0) {
        $name = substr($raw, 7);
        return in_array($name, $presets, true) ? 'preset:' . $name : '';
    }
    if (strpos($raw, 'color:') === 0) {
        $hex = substr($raw, 6);
        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex) ? 'color:' . $hex : '';
    }
    if (strpos($raw, 'image:') === 0) {
        $file = basename(substr($raw, 6));
        return preg_match('/^[a-zA-Z0-9._-]+$/', $file) ? 'image:' . $file : '';
    }
    return '';
}

$timezone = trim((string) ($_POST['timezone'] ?? ''));
if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

$glassEnabled = !empty($_POST['glass_enabled']) ? 1 : 0;
$glassBlur = max(4, min(40, (int) ($_POST['glass_blur'] ?? 16)));
$glassOpacity = max(20, min(95, (int) ($_POST['glass_opacity'] ?? 70)));

$existingBg = [];
$existingStmt = $db->prepare('SELECT bg_desktop_light, bg_desktop_dark, bg_mobile_light, bg_mobile_dark, app_logo, app_favicon, header_title, header_title_size FROM settings WHERE user_id = :userId');
$existingStmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
$existingRow = $existingStmt->execute()->fetchArray(SQLITE3_ASSOC);
if (is_array($existingRow)) {
    $existingBg = $existingRow;
}

$bgValues = [];
foreach ($slots as $slot) {
    $posted = array_key_exists($slot, $_POST);
    $incoming = $posted ? wallos_sanitize_bg_value($_POST[$slot], $presets) : null;
    $bgValues[$slot] = $incoming !== null ? $incoming : (string) ($existingBg[$slot] ?? '');
}

$uploadDir = __DIR__ . '/../../images/uploads/backgrounds';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
}

$allowedTypes = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

foreach ($slots as $slot) {
    $fileKey = $slot . '_file';
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        continue;
    }
    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 4 * 1024 * 1024) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n), 'detail' => 'upload']));
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowedTypes[$info['mime']])) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n), 'detail' => 'type']));
    }
    $name = 'u' . (int) $userId . '-' . $slot . '-' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $name)) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $bgValues[$slot] = 'image:' . $name;
}

$appLogo = wallos_sanitize_app_logo($existingBg['app_logo'] ?? '');
if (!empty($_POST['app_logo_reset'])) {
    $appLogo = '';
} elseif (!empty($_POST['app_logo_select'])) {
    $selected = wallos_sanitize_app_logo($_POST['app_logo_select']);
    if ($selected !== '' && is_file(__DIR__ . '/../../images/uploads/branding/' . $selected)) {
        $appLogo = $selected;
    }
} elseif (!empty($_FILES['app_logo_file']) && $_FILES['app_logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $logoFile = $_FILES['app_logo_file'];
    if ($logoFile['error'] !== UPLOAD_ERR_OK || $logoFile['size'] > 2 * 1024 * 1024) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n), 'detail' => 'logo-upload']));
    }
    $logoDir = __DIR__ . '/../../images/uploads/branding';
    if (!is_dir($logoDir) && !mkdir($logoDir, 0755, true) && !is_dir($logoDir)) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $logoTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/svg+xml' => 'svg',
    ];
    $logoMime = '';
    $info = @getimagesize($logoFile['tmp_name']);
    if ($info !== false && isset($logoTypes[$info['mime']])) {
        $logoMime = $info['mime'];
    } else {
        $raw = @file_get_contents($logoFile['tmp_name'], false, null, 0, 256);
        if (is_string($raw) && preg_match('/<svg[\s>]/i', $raw)) {
            $logoMime = 'image/svg+xml';
        }
    }
    if (!isset($logoTypes[$logoMime])) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n), 'detail' => 'logo-type']));
    }
    $logoName = 'u' . (int) $userId . '-logo-' . bin2hex(random_bytes(4)) . '.' . $logoTypes[$logoMime];
    if (!move_uploaded_file($logoFile['tmp_name'], $logoDir . DIRECTORY_SEPARATOR . $logoName)) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $appLogo = $logoName;
}

$appFavicon = wallos_sanitize_app_logo($existingBg['app_favicon'] ?? '');
if (!empty($_POST['app_favicon_reset'])) {
    $appFavicon = '';
} elseif (!empty($_POST['app_favicon_select'])) {
    $selectedFav = wallos_sanitize_app_logo($_POST['app_favicon_select']);
    if ($selectedFav !== '' && is_file(__DIR__ . '/../../images/uploads/branding/' . $selectedFav)) {
        $appFavicon = $selectedFav;
    }
} elseif (!empty($_FILES['app_favicon_file']) && $_FILES['app_favicon_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $favFile = $_FILES['app_favicon_file'];
    if ($favFile['error'] !== UPLOAD_ERR_OK || $favFile['size'] > 1024 * 1024) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n), 'detail' => 'favicon-upload']));
    }
    $logoDir = __DIR__ . '/../../images/uploads/branding';
    if (!is_dir($logoDir) && !mkdir($logoDir, 0755, true) && !is_dir($logoDir)) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $favTypes = [
        'image/png' => 'png',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/jpeg' => 'jpg',
    ];
    $favMime = '';
    $info = @getimagesize($favFile['tmp_name']);
    if ($info !== false && isset($favTypes[$info['mime']])) {
        $favMime = $info['mime'];
    } else {
        $raw = @file_get_contents($favFile['tmp_name'], false, null, 0, 256);
        if (is_string($raw) && preg_match('/<svg[\s>]/i', $raw)) {
            $favMime = 'image/svg+xml';
        }
    }
    if (!isset($favTypes[$favMime])) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n), 'detail' => 'favicon-type']));
    }
    $favName = 'u' . (int) $userId . '-favicon-' . bin2hex(random_bytes(4)) . '.' . $favTypes[$favMime];
    if (!move_uploaded_file($favFile['tmp_name'], $logoDir . DIRECTORY_SEPARATOR . $favName)) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $appFavicon = $favName;
}

$headerTitle = array_key_exists('header_title', $_POST)
    ? mb_substr(trim((string) $_POST['header_title']), 0, 40)
    : (string) ($existingBg['header_title'] ?? '');
$headerTitleSize = array_key_exists('header_title_size', $_POST)
    ? max(12, min(32, (int) $_POST['header_title_size']))
    : (int) ($existingBg['header_title_size'] ?? 18);

$stmt = $db->prepare('UPDATE settings SET
    timezone = :timezone,
    glass_enabled = :glass_enabled,
    glass_blur = :glass_blur,
    glass_opacity = :glass_opacity,
    app_logo = :app_logo,
    app_favicon = :app_favicon,
    header_title = :header_title,
    header_title_size = :header_title_size,
    bg_desktop_light = :bg_desktop_light,
    bg_desktop_dark = :bg_desktop_dark,
    bg_mobile_light = :bg_mobile_light,
    bg_mobile_dark = :bg_mobile_dark
    WHERE user_id = :userId');
$stmt->bindValue(':timezone', $timezone, SQLITE3_TEXT);
$stmt->bindValue(':glass_enabled', $glassEnabled, SQLITE3_INTEGER);
$stmt->bindValue(':glass_blur', $glassBlur, SQLITE3_INTEGER);
$stmt->bindValue(':glass_opacity', $glassOpacity, SQLITE3_INTEGER);
$stmt->bindValue(':app_logo', $appLogo, SQLITE3_TEXT);
$stmt->bindValue(':app_favicon', $appFavicon, SQLITE3_TEXT);
$stmt->bindValue(':header_title', $headerTitle, SQLITE3_TEXT);
$stmt->bindValue(':header_title_size', $headerTitleSize, SQLITE3_INTEGER);
$stmt->bindValue(':bg_desktop_light', $bgValues['bg_desktop_light'], SQLITE3_TEXT);
$stmt->bindValue(':bg_desktop_dark', $bgValues['bg_desktop_dark'], SQLITE3_TEXT);
$stmt->bindValue(':bg_mobile_light', $bgValues['bg_mobile_light'], SQLITE3_TEXT);
$stmt->bindValue(':bg_mobile_dark', $bgValues['bg_mobile_dark'], SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    $resolved = [];
    foreach ($bgValues as $slot => $raw) {
        $resolved[$slot] = [
            'raw' => $raw,
            'css' => wallos_background_css($raw, strpos($slot, 'dark') !== false),
        ];
    }
    die(json_encode([
        'success' => true,
        'message' => translate('success', $i18n),
        'backgrounds' => $resolved,
        'app_logo' => $appLogo,
        'app_logo_url' => wallos_app_logo_url($appLogo),
        'app_favicon' => $appFavicon,
        'app_favicon_url' => wallos_app_favicon_url($appFavicon),
        'header_title' => $headerTitle,
        'header_title_size' => $headerTitleSize,
        'media' => [
            'background' => wallos_list_media($userId, 'background'),
            'branding' => wallos_list_media($userId, 'branding'),
        ],
    ]));
}

die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
