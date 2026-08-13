<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';

$timezone = trim((string) ($_POST['timezone'] ?? ''));
if ($timezone !== '' && !in_array($timezone, timezone_identifiers_list(), true)) {
    die(json_encode([
        'success' => false,
        'message' => translate('error', $i18n),
    ]));
}

$stmt = $db->prepare('UPDATE settings SET timezone = :timezone WHERE user_id = :userId');
$stmt->bindValue(':timezone', $timezone, SQLITE3_TEXT);
$stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        'success' => true,
        'message' => translate('success', $i18n),
        'timezone' => $timezone,
    ]));
}

die(json_encode([
    'success' => false,
    'message' => translate('error', $i18n),
]));
