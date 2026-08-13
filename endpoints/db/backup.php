<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint_admin.php';
require_once '../../includes/backup_archive.php';

$backup = wallos_create_backup_archive();
if (empty($backup['success'])) {
    $message = $backup['message'] ?? translate('unknown_error', $i18n);
    if ($message === 'cannot_open_zip') {
        $message = translate('cannot_open_zip', $i18n);
    }
    die(json_encode([
        'success' => false,
        'message' => $message,
    ]));
}

flush();
die(json_encode([
    'success' => true,
    'message' => 'Zip file created successfully',
    'numFiles' => $backup['numFiles'],
    'file' => $backup['file'],
]));
