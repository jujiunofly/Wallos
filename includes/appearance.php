<?php

function wallos_background_presets()
{
    return [
        'aurora' => [
            'label' => 'Aurora',
            'theme' => 'purple',
            'css' => 'linear-gradient(160deg, #dbeafe 0%, #c7d2fe 42%, #fce7f3 100%)',
            'css_dark' => 'linear-gradient(160deg, #0f172a 0%, #1e1b4b 48%, #312e81 100%)',
        ],
        'sunset' => [
            'label' => 'Sunset',
            'theme' => 'red',
            'css' => 'linear-gradient(160deg, #ffedd5 0%, #fecdd3 46%, #e0e7ff 100%)',
            'css_dark' => 'linear-gradient(160deg, #1c1917 0%, #7c2d12 50%, #431407 100%)',
        ],
        'ocean' => [
            'label' => 'Ocean',
            'theme' => 'blue',
            'css' => 'linear-gradient(160deg, #cffafe 0%, #bfdbfe 50%, #ddd6fe 100%)',
            'css_dark' => 'linear-gradient(160deg, #042f2e 0%, #0c4a6e 52%, #1e1b4b 100%)',
        ],
        'forest' => [
            'label' => 'Forest',
            'theme' => 'green',
            'css' => 'linear-gradient(160deg, #dcfce7 0%, #bbf7d0 48%, #e0f2fe 100%)',
            'css_dark' => 'linear-gradient(160deg, #052e16 0%, #14532d 50%, #0f172a 100%)',
        ],
        'midnight' => [
            'label' => 'Midnight',
            'theme' => 'purple',
            'css' => 'linear-gradient(160deg, #e2e8f0 0%, #c7d2fe 55%, #cbd5e1 100%)',
            'css_dark' => 'linear-gradient(165deg, #020617 0%, #111827 46%, #1e293b 100%)',
        ],
        'sand' => [
            'label' => 'Sand',
            'theme' => 'yellow',
            'css' => 'linear-gradient(160deg, #fef3c7 0%, #fed7aa 48%, #e2e8f0 100%)',
            'css_dark' => 'linear-gradient(160deg, #1c1917 0%, #44403c 52%, #292524 100%)',
        ],
        'blossom' => [
            'label' => 'Blossom',
            'theme' => 'pink',
            'css' => 'linear-gradient(160deg, #fce7f3 0%, #fbcfe8 46%, #fae8ff 100%)',
            'css_dark' => 'linear-gradient(160deg, #1c1018 0%, #831843 52%, #4a044e 100%)',
        ],
    ];
}

function wallos_ensure_appearance_schema($db)
{
    static $ensured = false;
    if ($ensured || !$db) {
        return;
    }
    $ensured = true;
    $existing = [];
    $info = $db->query("PRAGMA table_info('settings')");
    if ($info) {
        while ($row = $info->fetchArray(SQLITE3_ASSOC)) {
            $existing[$row['name']] = true;
        }
    }
    if (!isset($existing['app_logo'])) {
        $db->exec("ALTER TABLE settings ADD COLUMN app_logo TEXT DEFAULT ''");
    }
    if (!isset($existing['app_favicon'])) {
        $db->exec("ALTER TABLE settings ADD COLUMN app_favicon TEXT DEFAULT ''");
    }
    if (!isset($existing['header_title'])) {
        $db->exec("ALTER TABLE settings ADD COLUMN header_title TEXT DEFAULT ''");
    }
    if (!isset($existing['header_title_size'])) {
        $db->exec("ALTER TABLE settings ADD COLUMN header_title_size INTEGER DEFAULT 18");
    }
    if (!isset($existing['subscription_progress_style'])) {
        $db->exec("ALTER TABLE settings ADD COLUMN subscription_progress_style TEXT DEFAULT 'bar'");
    }
    if (!isset($existing['color_theme_dark'])) {
        $db->exec("ALTER TABLE settings ADD COLUMN color_theme_dark TEXT DEFAULT ''");
    }
}

function wallos_color_themes()
{
    return ['blue', 'green', 'red', 'yellow', 'purple', 'pink'];
}

function wallos_sanitize_color_theme($raw, $fallback = 'blue')
{
    $value = strtolower(trim((string) $raw));
    return in_array($value, wallos_color_themes(), true) ? $value : $fallback;
}

function wallos_render_color_theme_picker($inputName, $mode, $current, $i18n)
{
    foreach (wallos_color_themes() as $color) {
        $id = $inputName . '-' . $color;
        $selected = $current === $color;
        ?>
        <div class="theme">
            <input type="radio" name="<?= htmlspecialchars($inputName) ?>" id="<?= htmlspecialchars($id) ?>" value="<?= htmlspecialchars($color) ?>"
                onClick="setTheme('<?= htmlspecialchars($color) ?>', '<?= htmlspecialchars($mode) ?>')"
                <?= $selected ? 'checked' : '' ?>>
            <label for="<?= htmlspecialchars($id) ?>" class="theme-preview <?= htmlspecialchars($color) ?><?= $selected ? ' is-selected' : '' ?>">
                <span class="main-color"></span>
                <span class="accent-color"></span>
                <span class="hover-color"></span>
            </label>
        </div>
        <?php
    }
}

function wallos_sanitize_app_logo($raw)
{
    $file = basename(trim((string) $raw));
    if ($file !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
        return $file;
    }
    return '';
}

function wallos_app_logo_url($raw)
{
    $file = wallos_sanitize_app_logo($raw);
    if ($file === '') {
        return '';
    }
    $path = 'images/uploads/branding/' . $file;
    if (is_file(__DIR__ . '/../' . $path)) {
        return $path;
    }
    return '';
}

function wallos_app_favicon_url($raw)
{
    $file = wallos_sanitize_app_logo($raw);
    if ($file === '') {
        return '';
    }
    $path = 'images/uploads/branding/' . $file;
    if (is_file(__DIR__ . '/../' . $path)) {
        return $path;
    }
    return '';
}

function wallos_media_dir($kind)
{
    return $kind === 'background' ? 'images/uploads/backgrounds' : 'images/uploads/branding';
}

function wallos_list_media($userId, $kind)
{
    $dirRel = wallos_media_dir($kind);
    $dir = __DIR__ . '/../' . $dirRel;
    $items = [];
    if (!is_dir($dir)) {
        return $items;
    }
    $prefix = 'u' . (int) $userId . '-';
    $files = scandir($dir);
    if ($files === false) {
        return $items;
    }
    rsort($files);
    foreach ($files as $file) {
        if (strpos($file, $prefix) !== 0 || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            continue;
        }
        if (!is_file($dir . DIRECTORY_SEPARATOR . $file)) {
            continue;
        }
        $items[] = [
            'filename' => $file,
            'url' => $dirRel . '/' . $file,
        ];
    }
    return $items;
}

function wallos_media_in_use($settings, $kind, $filename)
{
    if ($kind === 'background') {
        foreach (['bg_desktop_light', 'bg_desktop_dark', 'bg_mobile_light', 'bg_mobile_dark'] as $slot) {
            if (($settings[$slot] ?? '') === 'image:' . $filename) {
                return true;
            }
        }
        return false;
    }
    return ($settings['app_logo'] ?? '') === $filename || ($settings['app_favicon'] ?? '') === $filename;
}

function wallos_render_media_picker($kind, $userId, $current, $fileInputId, $accept, $i18n)
{
    $items = wallos_list_media($userId, $kind);
    ?>
    <div class="media-picker" data-kind="<?= htmlspecialchars($kind) ?>">
        <div class="media-picker-panel">
            <div class="media-picker-list">
                <?php foreach ($items as $item) { ?>
                    <div class="media-item<?= $current === $item['filename'] ? ' is-selected' : '' ?>" data-file="<?= htmlspecialchars($item['filename']) ?>">
                        <img src="<?= htmlspecialchars($item['url']) ?>" alt="" class="media-item-thumb">
                        <button type="button" class="media-item-remove" title="<?= translate('delete', $i18n) ?>" data-file="<?= htmlspecialchars($item['filename']) ?>">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                <?php } ?>
                <label class="media-item-add" for="<?= htmlspecialchars($fileInputId) ?>" title="<?= translate('upload_image', $i18n) ?>">
                    <i class="fa-solid fa-arrow-up-from-bracket"></i>
                </label>
            </div>
        </div>
        <input type="file" id="<?= htmlspecialchars($fileInputId) ?>" class="media-picker-file is-hidden<?= $kind === 'background' ? ' bg-file' : '' ?>" accept="<?= htmlspecialchars($accept) ?>">
    </div>
    <?php
}

function wallos_render_app_logo($raw, $title = 'Wallos')
{
    $url = wallos_app_logo_url($raw);
    if ($url !== '') {
        echo '<img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">';
        return;
    }
    include __DIR__ . '/../images/siteicons/svg/logo.php';
}

function wallos_appearance_from_settings($settings)
{
    if (!is_array($settings)) {
        $settings = [];
    }
    $blur = isset($settings['glass_blur']) ? (int) $settings['glass_blur'] : 16;
    $opacity = isset($settings['glass_opacity']) ? (int) $settings['glass_opacity'] : 70;

    return [
        'timezone' => isset($settings['timezone']) ? (string) $settings['timezone'] : '',
        'glass_enabled' => !empty($settings['glass_enabled']) ? 1 : 0,
        'glass_blur' => max(4, min(40, $blur)),
        'glass_opacity' => max(20, min(95, $opacity)),
        'app_logo' => wallos_sanitize_app_logo($settings['app_logo'] ?? ''),
        'app_favicon' => wallos_sanitize_app_logo($settings['app_favicon'] ?? ''),
        'header_title' => trim((string) ($settings['header_title'] ?? '')),
        'header_title_size' => max(12, min(36, (int) ($settings['header_title_size'] ?? 18))),
        'bg_desktop_light' => $settings['bg_desktop_light'] ?? '',
        'bg_desktop_dark' => $settings['bg_desktop_dark'] ?? '',
        'bg_mobile_light' => $settings['bg_mobile_light'] ?? '',
        'bg_mobile_dark' => $settings['bg_mobile_dark'] ?? '',
    ];
}

function wallos_parse_background_value($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return ['type' => 'none', 'value' => ''];
    }
    if (strpos($raw, 'preset:') === 0) {
        $name = substr($raw, 7);
        $presets = wallos_background_presets();
        if (isset($presets[$name])) {
            return ['type' => 'preset', 'value' => $name];
        }
        return ['type' => 'none', 'value' => ''];
    }
    if (strpos($raw, 'color:') === 0) {
        $hex = substr($raw, 6);
        if (preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $hex)) {
            return ['type' => 'color', 'value' => $hex];
        }
        return ['type' => 'none', 'value' => ''];
    }
    if (strpos($raw, 'image:') === 0) {
        $file = basename(substr($raw, 6));
        if ($file !== '' && preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
            return ['type' => 'image', 'value' => $file];
        }
    }
    return ['type' => 'none', 'value' => ''];
}

function wallos_background_css($raw, $isDark)
{
    $parsed = wallos_parse_background_value($raw);
    if ($parsed['type'] === 'preset') {
        $preset = wallos_background_presets()[$parsed['value']];
        return $isDark ? $preset['css_dark'] : $preset['css'];
    }
    if ($parsed['type'] === 'color') {
        $hex = $parsed['value'];
        return 'linear-gradient(180deg, ' . $hex . ' 0%, ' . $hex . ' 100%)';
    }
    if ($parsed['type'] === 'image') {
        $path = 'images/uploads/backgrounds/' . $parsed['value'];
        $full = __DIR__ . '/../' . $path;
        if (is_file($full)) {
            return "url('" . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '?v=' . filemtime($full) . "')";
        }
    }
    return '';
}

function wallos_timezone_options_html($currentTimezone, $i18n)
{
    $current = is_string($currentTimezone) ? $currentTimezone : '';
    $html = '<option value="">' . htmlspecialchars(translate('use_server_timezone', $i18n)) . '</option>';
    foreach (DateTimeZone::listIdentifiers() as $tzId) {
        $selected = $current === $tzId ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($tzId, ENT_QUOTES, 'UTF-8') . '"' . $selected . '>'
            . htmlspecialchars($tzId) . '</option>';
    }
    return $html;
}

function wallos_apply_user_timezone($timezone)
{
    if (!is_string($timezone) || $timezone === '') {
        return false;
    }
    if (!in_array($timezone, timezone_identifiers_list(), true)) {
        return false;
    }
    date_default_timezone_set($timezone);
    return true;
}

function wallos_sort_direction($sortField)
{
    $cookie = isset($_COOKIE['sortDirection']) ? strtoupper((string) $_COOKIE['sortDirection']) : '';
    if ($cookie === 'ASC' || $cookie === 'DESC') {
        return $cookie;
    }
    return ($sortField === 'price' || $sortField === 'id') ? 'DESC' : 'ASC';
}
