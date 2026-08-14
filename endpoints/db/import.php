<?php
ini_set('display_errors', '0');
ob_start();

require_once '../../includes/connect_endpoint.php';
require_once '../../includes/backup_archive.php';

try {
    $result = $db->query('SELECT COUNT(*) as count FROM user');
    $row = $result->fetchArray(SQLITE3_NUM);
    if ($row[0] > 0) {
        wallos_json_exit([
            'success' => false,
            'message' => 'Denied',
        ]);
    }

    $setupTokenFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'setup_token.db';
    $storedToken = file_exists($setupTokenFile) ? trim((string) file_get_contents($setupTokenFile)) : '';
    $submittedToken = $_POST['setup_token'] ?? '';
    if ($storedToken === '' || !hash_equals($storedToken, $submittedToken)) {
        wallos_json_exit([
            'success' => false,
            'message' => 'Invalid setup token',
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wallos_json_exit([
            'success' => false,
            'message' => 'Invalid request method',
        ]);
    }

    if (!isset($_FILES['file'])) {
        wallos_json_exit([
            'success' => false,
            'message' => 'No file uploaded',
        ]);
    }

    $file = $_FILES['file'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        wallos_json_exit([
            'success' => false,
            'message' => 'Failed to upload file',
        ]);
    }

    $tmpDir = wallos_project_tmp_dir();
    $restoreDir = $tmpDir . DIRECTORY_SEPARATOR . 'restore';
    if (!wallos_ensure_dir($tmpDir) || !wallos_ensure_dir($restoreDir)) {
        wallos_json_exit([
            'success' => false,
            'message' => 'Failed to create temporary directory',
        ]);
    }

    $fileDestination = $tmpDir . DIRECTORY_SEPARATOR . 'restore.zip';
    if (!move_uploaded_file($file['tmp_name'], $fileDestination)) {
        wallos_json_exit([
            'success' => false,
            'message' => 'Failed to upload file',
        ]);
    }

    $zip = new ZipArchive();
    if ($zip->open($fileDestination) !== true) {
        wallos_empty_dir($tmpDir);
        wallos_json_exit([
            'success' => false,
            'message' => 'Failed to extract the uploaded file',
        ]);
    }

    if (wallos_zip_has_unsafe_entry($zip)) {
        $zip->close();
        wallos_empty_dir($tmpDir);
        wallos_json_exit([
            'success' => false,
            'message' => 'Invalid backup file: unsafe file path detected.',
        ]);
    }

    $extracted = $zip->extractTo($restoreDir . DIRECTORY_SEPARATOR);
    $zip->close();
    if ($extracted !== true) {
        wallos_empty_dir($tmpDir);
        wallos_json_exit([
            'success' => false,
            'message' => 'Failed to extract the uploaded file',
        ]);
    }

    $restoredDb = $restoreDir . DIRECTORY_SEPARATOR . 'wallos.db';
    if (!file_exists($restoredDb)) {
        wallos_empty_dir($tmpDir);
        wallos_json_exit([
            'success' => false,
            'message' => 'wallos.db does not exist in the backup file',
        ]);
    }

    wallos_close_sqlite($db);

    $replaced = wallos_replace_sqlite_file($restoredDb, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'wallos.db');
    if (empty($replaced['ok'])) {
        wallos_empty_dir($tmpDir);
        wallos_json_exit([
            'success' => false,
            'message' => $replaced['message'] ?? 'Failed to replace database',
        ]);
    }

    wallos_restore_uploaded_media($restoreDir . DIRECTORY_SEPARATOR, dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);
    wallos_empty_dir($tmpDir);

    if (file_exists($setupTokenFile)) {
        unlink($setupTokenFile);
    }

    $db = new SQLite3(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . 'wallos.db');
    $db->busyTimeout(5000);
    require_once __DIR__ . '/../../includes/run_migrations.php';

    wallos_json_exit([
        'success' => true,
        'message' => translate('success', $i18n),
    ]);
} catch (Throwable $e) {
    wallos_json_exit([
        'success' => false,
        'message' => $e->getMessage(),
    ]);
}
