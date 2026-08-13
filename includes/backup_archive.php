<?php

function wallos_add_folder_to_zip($dir, $zipArchive, $zipdir = '')
{
    if (!is_dir($dir)) {
        return false;
    }

    $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
    $handle = opendir($dir);
    if ($handle === false) {
        return false;
    }

    if ($zipdir !== '') {
        $zipArchive->addEmptyDir($zipdir);
    }

    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $path = $dir . $file;
        if (is_dir($path)) {
            wallos_add_folder_to_zip($path, $zipArchive, $zipdir . $file . '/');
        } else {
            $zipArchive->addFile($path, $zipdir . $file);
        }
    }

    closedir($handle);
    return true;
}

function wallos_create_backup_archive()
{
    $root = dirname(__DIR__);
    $tmpDir = $root . DIRECTORY_SEPARATOR . '.tmp';
    if (!is_dir($tmpDir) && !mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
        return [
            'success' => false,
            'message' => 'Failed to create temporary directory',
        ];
    }

    $filename = 'backup_' . uniqid() . '.zip';
    $zipPath = $tmpDir . DIRECTORY_SEPARATOR . $filename;
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        return [
            'success' => false,
            'message' => 'cannot_open_zip',
        ];
    }

    $dbAdded = wallos_add_folder_to_zip($root . DIRECTORY_SEPARATOR . 'db', $zip);
    $uploadsAdded = wallos_add_folder_to_zip($root . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'uploads', $zip);
    if (!$dbAdded || !$uploadsAdded) {
        $zip->close();
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        return [
            'success' => false,
            'message' => 'Directory does not exist',
        ];
    }

    $numFiles = $zip->numFiles;
    if ($zip->close() === false) {
        if (file_exists($zipPath)) {
            unlink($zipPath);
        }
        return [
            'success' => false,
            'message' => 'Failed to finalize the zip file',
        ];
    }

    return [
        'success' => true,
        'file' => $filename,
        'path' => $zipPath,
        'numFiles' => $numFiles,
    ];
}
