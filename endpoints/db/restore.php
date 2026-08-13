<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/backup_archive.php';

function emptyRestoreFolder()
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator('../../.tmp', RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $removeFunction = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $removeFunction($fileinfo->getRealPath());
    }
}

if (isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $fileTmpName = $file['tmp_name'];
    $fileError = $file['error'];

    if ($fileError === 0) {
        $fileDestination = '../../.tmp/restore.zip';
        move_uploaded_file($fileTmpName, $fileDestination);

        $zip = new ZipArchive();
        if ($zip->open($fileDestination) === true) {
            // Validate all entries before extracting — ZipArchive::extractTo() does not
            // guarantee protection against path traversal (Zip Slip).
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = str_replace('\\', '/', $zip->getNameIndex($i));
                if ($entry[0] === '/' || in_array('..', explode('/', $entry), true)) {
                    $zip->close();
                    emptyRestoreFolder();
                    die(json_encode([
                        "success" => false,
                        "message" => "Invalid backup file: unsafe file path detected."
                    ]));
                }
            }
            $zip->extractTo('../../.tmp/restore/');
            $zip->close();
        } else {
            die(json_encode([
                "success" => false,
                "message" => "Failed to extract the uploaded file"
            ]));
        }

        if (file_exists('../../.tmp/restore/wallos.db')) {
            if (file_exists('../../db/wallos.db')) {
                unlink('../../db/wallos.db');
            }
            rename('../../.tmp/restore/wallos.db', '../../db/wallos.db');

            wallos_restore_uploaded_media('../../.tmp/restore/', '../../images/uploads/');

            emptyRestoreFolder();

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