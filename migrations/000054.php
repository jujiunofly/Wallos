<?php

// Appearance, glass, page backgrounds, and per-user timezone.
// All columns are additive with defaults so older backups keep working
// after migrate.php runs.

$settingsColumns = [
    'timezone' => "TEXT DEFAULT ''",
    'glass_enabled' => 'INTEGER DEFAULT 0',
    'glass_blur' => 'INTEGER DEFAULT 16',
    'glass_opacity' => 'INTEGER DEFAULT 70',
    'bg_desktop_light' => "TEXT DEFAULT ''",
    'bg_desktop_dark' => "TEXT DEFAULT ''",
    'bg_mobile_light' => "TEXT DEFAULT ''",
    'bg_mobile_dark' => "TEXT DEFAULT ''",
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

$db->exec("UPDATE settings SET timezone = '' WHERE timezone IS NULL");
$db->exec("UPDATE settings SET glass_enabled = 0 WHERE glass_enabled IS NULL");
$db->exec("UPDATE settings SET glass_blur = 16 WHERE glass_blur IS NULL");
$db->exec("UPDATE settings SET glass_opacity = 70 WHERE glass_opacity IS NULL");
$db->exec("UPDATE settings SET bg_desktop_light = '' WHERE bg_desktop_light IS NULL");
$db->exec("UPDATE settings SET bg_desktop_dark = '' WHERE bg_desktop_dark IS NULL");
$db->exec("UPDATE settings SET bg_mobile_light = '' WHERE bg_mobile_light IS NULL");
$db->exec("UPDATE settings SET bg_mobile_dark = '' WHERE bg_mobile_dark IS NULL");
