<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/backup_archive.php';

$result = $db->query("SELECT COUNT(*) as count FROM user");
$row = $result->fetchArray(SQLITE3_NUM);
if ($row[0] > 0) {
    die(json_encode([
        "success" => false,
        "message" => "Denied"
    ]));
}

$setupTokenFile = '../../db/setup_token.db';
$storedToken = file_exists($setupTokenFile) ? trim(file_get_contents($setupTokenFile)) : '';
$submittedToken = $_POST['setup_token'] ?? '';
if ($storedToken === '' || !hash_equals($storedToken, $submittedToken)) {
    die(json_encode([
        "success" => false,
        "message" => "Invalid setup token"
    ]));
}

function emptyRestoreFolder() {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator('../../.tmp', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $removeFunction = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $removeFunction($fileinfo->getRealPath());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $fileTmpName = $file['tmp_name'];
        $fileError = $file['error'];

        if ($fileError === 0) {
            $fileDestination = '../../.tmp/restore.zip';
            move_uploaded_file($fileTmpName, $fileDestination);

            $zip = new ZipArchive();
            if ($zip->open($fileDestination) === true) {
                $zip->extractTo('../../.tmp/restore/');
                $zip->close();
            } else {
                die(json_encode([
                    "success" => false,
                    "message" => "Failed to extract the uploaded file"
                ]));
            }

            if (file_exists('../../.tmp/restore/wallos.db')) {
                $db->close();

                if (file_exists('../../db/wallos.db') && !unlink('../../db/wallos.db')) {
                    emptyRestoreFolder();
                    die(json_encode([
                        "success" => false,
                        "message" => "Failed to remove existing database"
                    ]));
                }

                if (!rename('../../.tmp/restore/wallos.db', '../../db/wallos.db')) {
                    emptyRestoreFolder();
                    die(json_encode([
                        "success" => false,
                        "message" => "Failed to replace database"
                    ]));
                }

                wallos_restore_uploaded_media('../../.tmp/restore/', '../../images/uploads/');

                emptyRestoreFolder();

                if (file_exists($setupTokenFile)) {
                    unlink($setupTokenFile);
                }

                $db = new SQLite3('../../db/wallos.db');
                $db->busyTimeout(5000);
                ob_start();
                require_once __DIR__ . '/../../includes/run_migrations.php';
                ob_end_clean();

                echo json_encode([
                    "success" => true,
                    "message" => translate("success", $i18n)
                ]);
            } else {
                emptyRestoreFolder();

                die(json_encode([
                    "success" => false,
                    "message" => "wallos.db does not exist in the backup file"
                ]));
            }


        } else {
            echo json_encode([
                "success" => false,
                "message" => "Failed to upload file"
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No file uploaded"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request method"
    ]);
}
?>