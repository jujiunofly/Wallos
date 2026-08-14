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

function wallos_json_exit(array $payload): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($payload);
    exit;
}

function wallos_project_tmp_dir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . '.tmp';
}

function wallos_ensure_dir(string $dir): bool
{
    return is_dir($dir) || (mkdir($dir, 0777, true) && is_dir($dir));
}

function wallos_empty_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $path = $fileinfo->getPathname();
        if ($fileinfo->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

function wallos_close_sqlite(?SQLite3 &$db): void
{
    if ($db instanceof SQLite3) {
        $db->close();
    }
    $db = null;
    gc_collect_cycles();
}

function wallos_replace_sqlite_file(string $source, string $dest): array
{
    foreach ([$dest . '-wal', $dest . '-shm', $dest . '-journal'] as $sidecar) {
        if (file_exists($sidecar)) {
            @unlink($sidecar);
        }
    }

    if (file_exists($dest)) {
        $unlinked = false;
        for ($i = 0; $i < 10; $i++) {
            if (@unlink($dest)) {
                $unlinked = true;
                break;
            }
            usleep(100000);
        }
        if (!$unlinked) {
            return ['ok' => false, 'message' => 'Failed to remove existing database'];
        }
    }

    if (@rename($source, $dest)) {
        return ['ok' => true];
    }

    if (@copy($source, $dest)) {
        @unlink($source);
        return ['ok' => true];
    }

    return ['ok' => false, 'message' => 'Failed to replace database'];
}

function wallos_zip_has_unsafe_entry(ZipArchive $zip): bool
{
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = str_replace('\\', '/', (string) $zip->getNameIndex($i));
        if ($entry === '') {
            continue;
        }
        if ($entry[0] === '/' || in_array('..', explode('/', $entry), true)) {
            return true;
        }
    }
    return false;
}

function wallos_restore_uploaded_media($restoreRoot, $uploadsRoot)
{
    $folders = ['logos', 'branding', 'backgrounds'];
    $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'];
    $restoreRoot = rtrim(str_replace('\\', '/', $restoreRoot), '/') . '/';
    $uploadsRoot = rtrim(str_replace('\\', '/', $uploadsRoot), '/') . '/';

    foreach ($folders as $folder) {
        $source = $restoreRoot . $folder;
        if (!is_dir($source)) {
            continue;
        }

        $destinationRoot = $uploadsRoot . $folder;
        if (!is_dir($destinationRoot)) {
            mkdir($destinationRoot, 0755, true);
        }

        $existing = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($destinationRoot, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($existing as $file) {
            if ($file->getFilename() === '.gitkeep') {
                continue;
            }
            if ($file->isDir()) {
                @rmdir($file->getPathname());
            } else {
                @unlink($file->getPathname());
            }
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $filePath) {
            if (!$filePath->isFile()) {
                continue;
            }
            $ext = strtolower(pathinfo($filePath->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                continue;
            }
            $relative = ltrim(str_replace($restoreRoot, '', str_replace('\\', '/', $filePath->getPathname())), '/');
            $destination = $uploadsRoot . $relative;
            $destinationDir = dirname($destination);
            if (!is_dir($destinationDir)) {
                mkdir($destinationDir, 0755, true);
            }
            copy($filePath->getPathname(), $destination);
        }
    }
}
