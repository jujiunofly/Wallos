<?php

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/appearance.php';

wallos_ensure_appearance_schema($db);

$postData = file_get_contents("php://input");
$data = json_decode($postData, true);

$color = wallos_sanitize_color_theme($data['color'] ?? '', '');
if ($color === '') {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}

$mode = ($data['mode'] ?? '') === 'dark' ? 'dark' : 'light';
$column = $mode === 'dark' ? 'color_theme_dark' : 'color_theme';

$stmt = $db->prepare("UPDATE settings SET {$column} = :color WHERE user_id = :userId");
$stmt->bindParam(':color', $color, SQLITE3_TEXT);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    die(json_encode([
        "success" => true,
        "message" => translate("success", $i18n)
    ]));
} else {
    die(json_encode([
        "success" => false,
        "message" => translate("error", $i18n)
    ]));
}