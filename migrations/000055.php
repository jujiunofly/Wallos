<?php

// App branding, header title, progress style, and per-mode color theme.
// Additive columns with defaults so official Wallos backups keep working.

$settingsColumns = [
    'app_logo' => "TEXT DEFAULT ''",
    'app_favicon' => "TEXT DEFAULT ''",
    'header_title' => "TEXT DEFAULT ''",
    'header_title_size' => 'INTEGER DEFAULT 18',
    'subscription_progress_style' => "TEXT DEFAULT 'bar'",
    'color_theme_dark' => "TEXT DEFAULT ''",
];

$existing = [];
$info = $db->query("PRAGMA table_info('settings')");
while ($row = $info->fetchArray(SQLITE3_ASSOC)) {
    $existing[$row['name']] = true;
}

foreach ($settingsColumns as $name => $definition) {
    if (!isset($existing[$name])) {
        $db->exec("ALTER TABLE settings ADD COLUMN {$name} {$definition}");
    }
}

$db->exec("UPDATE settings SET app_logo = '' WHERE app_logo IS NULL");
$db->exec("UPDATE settings SET app_favicon = '' WHERE app_favicon IS NULL");
$db->exec("UPDATE settings SET header_title = '' WHERE header_title IS NULL");
$db->exec("UPDATE settings SET header_title_size = 18 WHERE header_title_size IS NULL");
$db->exec("UPDATE settings SET subscription_progress_style = 'bar' WHERE subscription_progress_style IS NULL OR subscription_progress_style = ''");
$db->exec("UPDATE settings SET color_theme_dark = '' WHERE color_theme_dark IS NULL");
