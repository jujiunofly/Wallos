<?php

$timezone = getenv('TZ');
if ($timezone == '') {
    $timezone = date_default_timezone_get();
    if ($timezone == '') {
        $timezone = 'UTC';
    }
}

$databaseFile = __DIR__ . '/../../db/wallos.db';
if (is_file($databaseFile)) {
    try {
        $timezoneDb = new SQLite3($databaseFile);
        $timezoneDb->busyTimeout(2000);
        $adminTimezone = $timezoneDb->querySingle("SELECT timezone FROM settings WHERE user_id = 1");
        $timezoneDb->close();
        if (is_string($adminTimezone) && $adminTimezone !== '' && in_array($adminTimezone, timezone_identifiers_list(), true)) {
            $timezone = $adminTimezone;
        }
    } catch (Throwable $e) {
        // Fall back to TZ / PHP default when the column is missing.
    }
}

date_default_timezone_set($timezone);
