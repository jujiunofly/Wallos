<?php
require_once 'connect.php';
require_once 'checkuser.php';
require_once 'checksession.php';
require_once 'checkredirect.php';
require_once 'currency_formatter.php';

require_once 'libs/csrf.php';

require_once 'i18n/languages.php';
require_once 'i18n/getlang.php';
require_once 'i18n/' . $lang . '.php';

require_once 'getsettings.php';

require_once 'version.php';

if ($userCount == 0) {
  $db->close();
  header("Location: registration.php");
  exit();
}

$demoMode = getenv('DEMO_MODE');

$theme = "automatic";
if (isset($settings['theme'])) {
  $theme = $settings['theme'];
}

$updateThemeSettings = false;
if (isset($settings['update_theme_setttings'])) {
  $updateThemeSettings = $settings['update_theme_setttings'];
}

$colorThemeLight = wallos_sanitize_color_theme($settings['color_theme'] ?? 'blue');
$colorThemeDark = wallos_sanitize_color_theme($settings['color_theme_dark'] ?? '', $colorThemeLight);
$colorTheme = ($theme === 'dark') ? $colorThemeDark : $colorThemeLight;

$customCss = "";
if (isset($settings['customCss'])) {
  $customCss = $settings['customCss'];
}

if (isset($themeValue)) {
  $cookieExpire = time() + (30 * 24 * 60 * 60);
  setcookie('theme', $themeValue, [
    'expires' => $cookieExpire,
    'samesite' => 'Lax'
  ]);
}

$isAdmin = $_SESSION['userId'] == 1;

$locale = isset($_COOKIE['user_locale']) ? $_COOKIE['user_locale'] : 'en_US';
$formatter = new IntlDateFormatter(
  $locale, 
  IntlDateFormatter::MEDIUM,
  IntlDateFormatter::NONE
);

function hex2rgb($hex)
{
  $hex = str_replace("#", "", $hex);
  if (strlen($hex) == 3) {
    $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
    $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
    $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
  } else {
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
  }
  return "$r, $g, $b";
}

$mobileNavigation = $settings['mobile_nav'] ? "mobile-navigation" : "";

require_once __DIR__ . '/appearance.php';
$appearance = wallos_appearance_from_settings($settings);
$bgDesktopLight = wallos_background_css($appearance['bg_desktop_light'], false);
$bgDesktopDark = wallos_background_css($appearance['bg_desktop_dark'], true);
$bgMobileLight = $bgDesktopLight;
$bgMobileDark = $bgDesktopDark;
$isDarkTheme = $theme === 'dark';
$activePageBg = $isDarkTheme ? $bgDesktopDark : $bgDesktopLight;
$hasPageBg = $activePageBg !== '';
$showAppSidebar = !isset($_COOKIE['wallos_sidebar']) || $_COOKIE['wallos_sidebar'] !== '0';
$appearanceClasses = trim(
    ($appearance['glass_enabled'] ? 'glass-enabled' : '') . ' ' .
    ($hasPageBg ? 'has-page-bg' : '') . ' ' .
    ($showAppSidebar ? 'has-app-sidebar' : '')
);
$darkThemeMode = isset($settings['dark_theme']) ? (int) $settings['dark_theme'] : 2;

?>
<!DOCTYPE html>
<html dir="<?= $languages[$lang]['dir'] ?>">
<head>
  <meta charset="UTF-8">
  <meta name="view-transition" content="same-origin">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= $appearance['header_title'] !== '' ? htmlspecialchars($appearance['header_title']) : 'Wallos - Subscription Tracker' ?></title>
  <meta name="apple-mobile-web-app-title" content="<?= $appearance['header_title'] !== '' ? htmlspecialchars($appearance['header_title']) : 'Wallos' ?>">
  <meta name="theme-color" content="<?= $theme == "light" ? "#FFFFFF" : "#12151C" ?>" id="theme-color" />
  <meta name="referrer" content="no-referrer">
  <?php
  $faviconUrl = wallos_app_favicon_url($appearance['app_favicon'] ?? '');
  if ($faviconUrl !== '') {
      ?>
      <link rel="icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>" id="appFavicon">
      <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconUrl, ENT_QUOTES, 'UTF-8') ?>">
      <?php
  } else {
      ?>
      <link rel="icon" type="image/png" href="images/icon/favicon.ico" sizes="16x16" id="appFavicon">
      <link rel="apple-touch-icon" href="images/icon/apple-touch-icon.png">
      <?php
  }
  ?>
  <link rel="apple-touch-icon" sizes="152x152" href="images/icon/apple-touch-icon-152.png">
  <link rel="apple-touch-icon" sizes="180x180" href="images/icon/apple-touch-icon-180.png">
  <link rel="manifest" href="manifest.json" crossorigin="use-credentials">
  <link rel="stylesheet" href="styles/theme.css?<?= $version ?>">
  <link rel="stylesheet" href="styles/styles.css?<?= $version ?>">
  <link rel="stylesheet" href="styles/dark-theme.css?<?= $version ?>" id="dark-theme" <?= $theme != "dark" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/red.css?<?= $version ?>" id="red-theme" <?= $colorTheme != "red" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/green.css?<?= $version ?>" id="green-theme" <?= $colorTheme != "green" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/yellow.css?<?= $version ?>" id="yellow-theme" <?= $colorTheme != "yellow" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/purple.css?<?= $version ?>" id="purple-theme" <?= $colorTheme != "purple" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/themes/pink.css?<?= $version ?>" id="pink-theme" <?= $colorTheme != "pink" ? "disabled" : "" ?>>
  <link rel="stylesheet" href="styles/barlow.css">
  <link rel="stylesheet" href="styles/font-awesome.min.css">
  <link rel="stylesheet" href="styles/brands.css">
  <link rel="stylesheet" href="styles/appearance.css?<?= $version ?>">
  <script type="text/javascript" src="scripts/all.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/common.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/header-theme.js?<?= $version ?>"></script>
  <script type="text/javascript">
    window.theme = "<?= $theme ?>";
    window.update_theme_settings = "<?= $updateThemeSettings ?>";
    window.lang = "<?= $lang ?>";
    window.colorTheme = "<?= $colorTheme ?>";
    window.mobileNavigation = "<?= $settings['mobileNavigation'] == "true" ?>";
    window.csrfToken = "<?= htmlspecialchars(generate_csrf_token()) ?>";
    window.darkThemeMode = <?= $darkThemeMode ?>;
    window.pageNavTitle = <?= json_encode(translate('contents', $i18n), JSON_UNESCAPED_UNICODE) ?>;
    window.themeLabels = {
      0: <?= json_encode(translate('light_theme', $i18n), JSON_UNESCAPED_UNICODE) ?>,
      1: <?= json_encode(translate('dark_theme', $i18n), JSON_UNESCAPED_UNICODE) ?>,
      2: <?= json_encode(translate('automatic', $i18n), JSON_UNESCAPED_UNICODE) ?>
    };
    window.appearanceConfig = <?= json_encode([
        'glass_enabled' => (int) $appearance['glass_enabled'],
        'glass_blur' => (int) $appearance['glass_blur'],
        'glass_opacity' => (int) $appearance['glass_opacity'],
        'app_logo' => $appearance['app_logo'] ?? '',
        'app_logo_url' => wallos_app_logo_url($appearance['app_logo'] ?? ''),
        'app_favicon' => $appearance['app_favicon'] ?? '',
        'app_favicon_url' => wallos_app_favicon_url($appearance['app_favicon'] ?? ''),
        'header_title' => $appearance['header_title'] ?? '',
        'header_title_size' => (int) ($appearance['header_title_size'] ?? 18),
        'color_theme_light' => $colorThemeLight,
        'color_theme_dark' => $colorThemeDark,
        'backgrounds' => [
            'bg_desktop_light' => ['raw' => $appearance['bg_desktop_light'], 'css' => $bgDesktopLight],
            'bg_desktop_dark' => ['raw' => $appearance['bg_desktop_dark'], 'css' => $bgDesktopDark],
            'bg_mobile_light' => ['raw' => $appearance['bg_mobile_light'], 'css' => $bgMobileLight],
            'bg_mobile_dark' => ['raw' => $appearance['bg_mobile_dark'], 'css' => $bgMobileDark],
        ],
        'presets' => array_map(function ($preset) {
            return [
                'css' => $preset['css'],
                'css_dark' => $preset['css_dark'],
                'theme' => $preset['theme'] ?? '',
            ];
        }, wallos_background_presets()),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  </script>
  <script type="text/javascript" src="scripts/page-nav.js?<?= $version ?>"></script>
  <style>
    :root {
      --glass-blur: <?= (int) $appearance['glass_blur'] ?>px;
      --glass-alpha: <?= number_format($appearance['glass_opacity'] / 100, 2, '.', '') ?>;
      --page-bg: <?= $activePageBg !== '' ? $activePageBg : 'none' ?>;
      --page-bg-desktop-light: <?= $bgDesktopLight !== '' ? $bgDesktopLight : 'none' ?>;
      --page-bg-desktop-dark: <?= $bgDesktopDark !== '' ? $bgDesktopDark : 'none' ?>;
      --page-bg-mobile-light: <?= $bgMobileLight !== '' ? $bgMobileLight : 'none' ?>;
      --page-bg-mobile-dark: <?= $bgMobileDark !== '' ? $bgMobileDark : 'none' ?>;
      --app-sidebar-width: 0px;
      --app-header-height: 64px;
      --header-title-size: <?= (int) ($appearance['header_title_size'] ?? 18) ?>px;
    }
    <?= htmlspecialchars($customCss, ENT_QUOTES, 'UTF-8') ?>
  </style>
  <?php
  if (isset($settings['customColors'])) {
    ?>
    <style id="custom_theme_colors">
      :root {
        <?php if (isset($settings['customColors']['main_color']) && !empty($settings['customColors']['main_color'])): ?>
          --main-color:
            <?= $settings['customColors']['main_color'] ?>
          ;
          --main-color-rgb:
            <?= hex2rgb($settings['customColors']['main_color']) ?>
          ;
        <?php endif; ?>
        <?php if (isset($settings['customColors']['accent_color']) && !empty($settings['customColors']['accent_color'])): ?>
          --accent-color:
            <?= $settings['customColors']['accent_color'] ?>
          ;
          --accent-color-rgb:
            <?= hex2rgb($settings['customColors']['accent_color']) ?>
          ;
        <?php endif; ?>
        <?php if (isset($settings['customColors']['hover_color']) && !empty($settings['customColors']['hover_color'])): ?>
          --hover-color:
            <?= $settings['customColors']['hover_color'] ?>
          ;
          --hover-color-rgb:
            <?= hex2rgb($settings['customColors']['hover_color']) ?>
          ;
        <?php endif; ?>
      }
    </style>
    <?php
  }
  ?>
  <script type="text/javascript" src="scripts/i18n/<?= $lang ?>.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/i18n/getlang.js?<?= $version ?>"></script>
  <script type="text/javascript" src="scripts/password-toggle.js?<?= $version ?>"></script>
  <script>
    if ('serviceWorker' in navigator && navigator.serviceWorker.controller) {
      if (!sessionStorage.getItem('sw_prefetched')) {
        navigator.serviceWorker.controller.postMessage({ type: 'PREFETCH_PAGES' });
        sessionStorage.setItem('sw_prefetched', '1');
      }
    }
  </script>
</head>

<body class="<?= $theme ?> <?= $languages[$lang]['dir'] ?> <?= $mobileNavigation ?> <?= $appearanceClasses ?>">
  <div id="page-bg-layer" class="page-bg-layer" aria-hidden="true"<?php if ($activePageBg !== ''): ?> style="background-image: <?= htmlspecialchars($activePageBg, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>></div>
  <header>
    <div class="contain">
      <div class="header-left">
        <div class="logo header-logo">
          <a href=".">
            <div class="logo-image app-logo-slot" title="Wallos - Subscription Tracker">
              <?php wallos_render_app_logo($appearance['app_logo'] ?? ''); ?>
            </div>
          </a>
        </div>
        <div class="header-title<?= ($appearance['header_title'] ?? '') === '' ? ' is-hidden' : '' ?>" id="headerTitleText"><?= htmlspecialchars($appearance['header_title'] ?? '') ?></div>
      </div>
      <nav class="header-nav">
        <button type="button" class="header-theme-toggle" id="headerThemeToggle"
          title="<?= translate('theme', $i18n) ?>" aria-label="<?= translate('theme', $i18n) ?>">
          <i class="fa-solid <?= $darkThemeMode === 1 ? 'fa-moon' : ($darkThemeMode === 0 ? 'fa-sun' : 'fa-circle-half-stroke') ?>"></i>
        </button>
        <div class="header-timezone">
          <button type="button" class="header-theme-toggle" id="headerTimezoneToggle"
            title="<?= translate('timezone', $i18n) ?>" aria-label="<?= translate('timezone', $i18n) ?>">
            <i class="fa-solid fa-earth-asia"></i>
          </button>
          <div class="header-timezone-panel" id="headerTimezonePanel">
            <label for="headerTimezone"><?= translate('timezone', $i18n) ?></label>
            <select id="headerTimezone">
              <?= wallos_timezone_options_html($appearance['timezone'] ?? '', $i18n) ?>
            </select>
          </div>
        </div>
        <button type="button" class="header-theme-toggle header-sidebar-toggle" id="headerSidebarToggle"
          title="<?= translate('toggle_sidebar', $i18n) ?>" aria-label="<?= translate('toggle_sidebar', $i18n) ?>">
          <i class="fa-solid <?= $showAppSidebar ? 'fa-table-columns' : 'fa-bars' ?>"></i>
        </button>
        <div class="dropdown">
          <button class="dropbtn" onClick="toggleDropdown()">
            <img src="<?= htmlspecialchars($userData['avatar'], ENT_QUOTES, 'UTF-8') ?>" alt="me" id="avatar">
            <span id="user" class="mobileNavigationHideOnMobile"><?= $userData['username'] ?></span>
          </button>
          <div class="dropdown-content">
            <a href="." class="mobileNavigationHideOnMobile app-nav-in-dropdown">
              <?php include "images/siteicons/svg/mobile-menu/home.php"; ?>
              <?= translate('dashboard', $i18n) ?></a>
            <a href="subscriptions.php" class="mobileNavigationHideOnMobile app-nav-in-dropdown">
              <?php include "images/siteicons/svg/mobile-menu/subscriptions.php"; ?>
              <?= translate('subscriptions', $i18n) ?></a>  
            <a href="calendar.php" class="mobileNavigationHideOnMobile app-nav-in-dropdown">
                <?php include "images/siteicons/svg/mobile-menu/calendar.php"; ?>
                <?= translate('calendar', $i18n) ?></a>
            <a href="stats.php" class="mobileNavigationHideOnMobile app-nav-in-dropdown">
              <?php include "images/siteicons/svg/mobile-menu/statistics.php"; ?>
              <?= translate('stats', $i18n) ?></a>
            <a href="settings.php" class="mobileNavigationHideOnMobile app-nav-in-dropdown">
              <?php include "images/siteicons/svg/mobile-menu/settings.php"; ?>
              <?= translate('settings', $i18n) ?></a>
            <a href="profile.php">
              <?php include "images/siteicons/svg/mobile-menu/profile.php"; ?>
              <?= translate('profile', $i18n) ?></a>  
            <?php if ($isAdmin): ?>
              <a href="admin.php">
                <?php include "images/siteicons/svg/mobile-menu/admin.php"; ?>
                <?= translate('admin', $i18n) ?>
              </a>
            <?php endif; ?>
            <a href="about.php">
              <?php include "images/siteicons/svg/mobile-menu/about.php"; ?>
              <?= translate('about', $i18n) ?>
            </a>
            <?php
            if ($settings['disableLogin'] == 0) {
              ?>
              <a href="logout.php">
                <?php include "images/siteicons/svg/mobile-menu/logout.php"; ?>
                <?= translate('logout', $i18n) ?></a>
              <?php
            }
            ?>
          </div>
        </div>
      </nav>
    </div>
  </header>

  <?php
  // find out which page is being viewed
  $page = basename($_SERVER['PHP_SELF']);
  $dashboardClass = $page === 'index.php' ? 'active' : '';
  $subscriptionsClass = $page === 'subscriptions.php' ? 'active' : '';
  $calendarClass = $page === 'calendar.php' ? 'active' : '';
  $statsClass = $page === 'stats.php' ? 'active' : '';
  $settingsClass = $page === 'settings.php' ? 'active' : '';
  $profileClass = $page === 'profile.php' ? 'active' : '';
  ?>

  <aside class="app-sidebar" aria-label="<?= translate('dashboard', $i18n) ?>">
    <a href="." class="app-sidebar-brand" title="Wallos">
      <span class="app-sidebar-logo app-logo-slot">
        <?php wallos_render_app_logo($appearance['app_logo'] ?? ''); ?>
      </span>
    </a>
    <nav class="app-sidebar-nav">
    <a href="." class="app-sidebar-link <?= $dashboardClass ?>" title="<?= translate('dashboard', $i18n) ?>">
      <span class="app-sidebar-icon"><?php include "images/siteicons/svg/mobile-menu/home.php"; ?></span>
      <span class="app-sidebar-label"><?= translate('dashboard', $i18n) ?></span>
    </a>
    <a href="subscriptions.php" class="app-sidebar-link <?= $subscriptionsClass ?>" title="<?= translate('subscriptions', $i18n) ?>">
      <span class="app-sidebar-icon"><?php include "images/siteicons/svg/mobile-menu/subscriptions.php"; ?></span>
      <span class="app-sidebar-label"><?= translate('subscriptions', $i18n) ?></span>
    </a>
    <a href="calendar.php" class="app-sidebar-link <?= $calendarClass ?>" title="<?= translate('calendar', $i18n) ?>">
      <span class="app-sidebar-icon"><?php include "images/siteicons/svg/mobile-menu/calendar.php"; ?></span>
      <span class="app-sidebar-label"><?= translate('calendar', $i18n) ?></span>
    </a>
    <a href="stats.php" class="app-sidebar-link <?= $statsClass ?>" title="<?= translate('stats', $i18n) ?>">
      <span class="app-sidebar-icon"><?php include "images/siteicons/svg/mobile-menu/statistics.php"; ?></span>
      <span class="app-sidebar-label"><?= translate('stats', $i18n) ?></span>
    </a>
    <a href="settings.php" class="app-sidebar-link <?= $settingsClass ?>" title="<?= translate('settings', $i18n) ?>">
      <span class="app-sidebar-icon"><?php include "images/siteicons/svg/mobile-menu/settings.php"; ?></span>
      <span class="app-sidebar-label"><?= translate('settings', $i18n) ?></span>
    </a>
    </nav>
  </aside>

  <?php
  if ($settings['mobile_nav'] == 1) {
    ?>
    <nav class="mobile-nav">
        <a href="." class="nav-link <?= $dashboardClass ?>" title="<?= translate('dashboard', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/home.php"; ?>
          <?= translate('dashboard', $i18n) ?>
        </a>
        <a href="subscriptions.php" class="nav-link <?= $subscriptionsClass ?>" title="<?= translate('subscriptions', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/subscriptions.php"; ?>
          <?= translate('subscriptions', $i18n) ?>
        </a>
        <a href="calendar.php" class="nav-link <?= $calendarClass ?>" title="<?= translate('calendar', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/calendar.php"; ?>
          <?= translate('calendar', $i18n) ?>
        </a>
        <a href="stats.php" class="nav-link <?= $statsClass ?>" title="<?= translate('stats', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/statistics.php"; ?>
          <?= translate('stats', $i18n) ?>
        </a>
        <a href="settings.php" class="nav-link <?= $settingsClass ?>" title="<?= translate('settings', $i18n) ?>">
          <?php include "images/siteicons/svg/mobile-menu/settings.php"; ?>
          <?= translate('settings', $i18n) ?>
        </a>
    </nav>
    <?php
  }
  ?>

  <main>
