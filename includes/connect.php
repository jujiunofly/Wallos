<?php

require_once __DIR__ . '/set_app_timezone.php';

$databaseFile = 'db/wallos.db';

$db = new SQLite3($databaseFile);
$db->busyTimeout(5000);

if (!$db) {
    die('Connection to the database failed.');
}

?>