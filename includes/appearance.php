<?php

function wallos_background_presets()
{
    return [
        'aurora' => [
            'label' => 'Aurora',
            'css' => 'linear-gradient(160deg, #dbeafe 0%, #c7d2fe 42%, #fce7f3 100%)',
            'css_dark' => 'linear-gradient(160deg, #0f172a 0%, #1e1b4b 48%, #312e81 100%)',
        ],
        'sunset' => [
            'label' => 'Sunset',
            'css' => 'linear-gradient(160deg, #ffedd5 0%, #fecdd3 46%, #e0e7ff 100%)',
            'css_dark' => 'linear-gradient(160deg, #1c1917 0%, #7c2d12 50%, #431407 100%)',
        ],
        'ocean' => [
            'label' => 'Ocean',
            'css' => 'linear-gradient(160deg, #cffafe 0%, #bfdbfe 50%, #ddd6fe 100%)',
            'css_dark' => 'linear-gradient(160deg, #042f2e 0%, #0c4a6e 52%, #1e1b4b 100%)',
        ],
        'forest' => [
            'label' => 'Forest',
            'css' => 'linear-gradient(160deg, #dcfce7 0%, #bbf7d0 48%, #e0f2fe 100%)',
            'css_dark' => 'linear-gradient(160deg, #052e16 0%, #14532d 50%, #0f172a 100%)',
        ],
        'midnight' => [
            'label' => 'Midnight',
            'css' => 'linear-gradient(160deg, #e2e8f0 0%, #c7d2fe 55%, #cbd5e1 100%)',
            'css_dark' => 'linear-gradient(165deg, #020617 0%, #111827 46%, #1e293b 100%)',
        ],
        'sand' => [
            'label' => 'Sand',
            'css' => 'linear-gradient(160deg, #fef3c7 0%, #fed7aa 48%, #e2e8f0 100%)',
            'css_dark' => 'linear-gradient(160deg, #1c1917 0%, #44403c 52%, #292524 100%)',
        ],
    ];
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
        return $parsed['value'];
    }
    if ($parsed['type'] === 'image') {
        $path = 'images/uploads/backgrounds/' . $parsed['value'];
        if (is_file(__DIR__ . '/../' . $path)) {
            return "url('" . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . "')";
        }
    }
    return '';
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
