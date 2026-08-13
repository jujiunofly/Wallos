<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/appearance.php';

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

$bgValues = [];
foreach ($slots as $slot) {
    $bgValues[$slot] = wallos_sanitize_bg_value($_POST[$slot] ?? '', $presets);
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
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $info = @getimagesize($file['tmp_name']);
    if ($info === false || !isset($allowedTypes[$info['mime']])) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $name = 'u' . (int) $userId . '-' . $slot . '-' . bin2hex(random_bytes(4)) . '.' . $allowedTypes[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $name)) {
        die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
    }
    $bgValues[$slot] = 'image:' . $name;
}

$stmt = $db->prepare('UPDATE settings SET
    timezone = :timezone,
    glass_enabled = :glass_enabled,
    glass_blur = :glass_blur,
    glass_opacity = :glass_opacity,
    bg_desktop_light = :bg_desktop_light,
    bg_desktop_dark = :bg_desktop_dark,
    bg_mobile_light = :bg_mobile_light,
    bg_mobile_dark = :bg_mobile_dark
    WHERE user_id = :userId');
$stmt->bindValue(':timezone', $timezone, SQLITE3_TEXT);
$stmt->bindValue(':glass_enabled', $glassEnabled, SQLITE3_INTEGER);
$stmt->bindValue(':glass_blur', $glassBlur, SQLITE3_INTEGER);
$stmt->bindValue(':glass_opacity', $glassOpacity, SQLITE3_INTEGER);
$stmt->bindValue(':bg_desktop_light', $bgValues['bg_desktop_light'], SQLITE3_TEXT);
$stmt->bindValue(':bg_desktop_dark', $bgValues['bg_desktop_dark'], SQLITE3_TEXT);
$stmt->bindValue(':bg_mobile_light', $bgValues['bg_mobile_light'], SQLITE3_TEXT);
$stmt->bindValue(':bg_mobile_dark', $bgValues['bg_mobile_dark'], SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode(['success' => true, 'message' => translate('success', $i18n)]));
}

die(json_encode(['success' => false, 'message' => translate('error', $i18n)]));
