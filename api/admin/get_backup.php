<?php
/*
This API Endpoint accepts both POST and GET requests.
It receives the following parameters:
- api_key: the API key of the user (must be user ID 1 / admin).

It returns a downloadable ZIP backup of the Wallos database and uploaded files.
On error it returns a JSON object:
{
  "success": false,
  "title": "..."
}
*/

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/backup_archive.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'title' => 'Method not allowed',
    ]);
    exit;
}

$apiKey = $_REQUEST['api_key'] ?? $_REQUEST['apiKey'] ?? null;

if (!$apiKey) {
    http_response_code(400);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'title' => 'Missing parameters',
    ]);
    exit;
}

$sql = 'SELECT * FROM user WHERE api_key = :apiKey';
$stmt = $db->prepare($sql);
$stmt->bindValue(':apiKey', $apiKey, SQLITE3_TEXT);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'title' => 'Invalid API key',
    ]);
    exit;
}

if ((int) $user['id'] !== 1) {
    http_response_code(403);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'title' => 'Invalid user',
    ]);
    exit;
}

$backup = wallos_create_backup_archive();
if (empty($backup['success']) || empty($backup['path']) || !is_file($backup['path'])) {
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success' => false,
        'title' => $backup['message'] ?? 'Backup failed',
    ]);
    exit;
}

$downloadName = 'Wallos-Backup-' . date('Ymd-Hi') . '.zip';
$fileSize = filesize($backup['path']);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($backup['path']);
unlink($backup['path']);
exit;
