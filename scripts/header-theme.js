function headerThemeIcon(mode) {
  if (mode === 1) {
    return 'fa-moon';
  }
  if (mode === 0) {
    return 'fa-sun';
  }
  return 'fa-circle-half-stroke';
}

function currentAppearanceBackground() {
  const config = window.appearanceConfig;
  if (!config || !config.backgrounds) {
    return '';
  }
  const dark = document.body.classList.contains('dark');
  const mobile = window.matchMedia('(max-width: 768px)').matches;
  const preferred = (mobile ? 'bg_mobile_' : 'bg_desktop_') + (dark ? 'dark' : 'light');
  const fallback = (mobile ? 'bg_mobile_' : 'bg_desktop_') + (dark ? 'light' : 'dark');
  return (config.backgrounds[preferred] && config.backgrounds[preferred].css)
    || (config.backgrounds[fallback] && config.backgrounds[fallback].css)
    || '';
}

function applyPageBackground() {
  const css = currentAppearanceBackground();
  const root = document.documentElement;
  const dark = document.body.classList.contains('dark');
  const mobile = window.matchMedia('(max-width: 768px)').matches;
  const varName = mobile
    ? (dark ? '--page-bg-mobile-dark' : '--page-bg-mobile-light')
    : (dark ? '--page-bg-desktop-dark' : '--page-bg-desktop-light');
  if (css) {
    root.style.setProperty(varName, css);
    document.body.classList.add('has-page-bg');
  } else {
    root.style.setProperty(varName, 'none');
    const stillHasBg = Object.values((window.appearanceConfig && window.appearanceConfig.backgrounds) || {})
      .some((item) => item && item.css);
    document.body.classList.toggle('has-page-bg', stillHasBg);
  }
}

function applyResolvedTheme(mode) {
  mode = Number(mode);
  const darkThemeCss = document.querySelector('#dark-theme');
  const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
  const names = { 0: 'light', 1: 'dark', 2: 'automatic' };
  const themeName = names[mode] || 'automatic';
  const inUse = mode === 2 ? (prefersDark ? 'dark' : 'light') : themeName;

  if (darkThemeCss) {
    darkThemeCss.disabled = inUse !== 'dark';
  }
  document.body.classList.remove('light', 'dark');
  document.body.classList.add(inUse);

  document.cookie = 'theme=' + themeName + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';
  if (mode === 2) {
    document.cookie = 'inUseTheme=' + inUse + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';
  }

  const themeColor = document.getElementById('theme-color');
  if (themeColor) {
    themeColor.setAttribute('content', inUse === 'dark' ? '#12151C' : '#FFFFFF');
  }

  const button = document.getElementById('headerThemeToggle');
  if (button) {
    const icon = button.querySelector('i');
    if (icon) {
      icon.className = 'fa-solid ' + headerThemeIcon(mode);
    }
    button.dataset.mode = String(mode);
  }

  const settingsButtons = {
    0: document.getElementById('theme-light'),
    1: document.getElementById('theme-dark'),
    2: document.getElementById('theme-automatic'),
  };
  Object.keys(settingsButtons).forEach((key) => {
    const el = settingsButtons[key];
    if (el) {
      el.classList.toggle('selected', Number(key) === mode);
    }
  });

  applyPageBackground();
}

function saveThemeMode(mode) {
  window.darkThemeMode = mode;
  applyResolvedTheme(mode);
  const labels = window.themeLabels || {};
  if (typeof showSuccessMessage === 'function') {
    showSuccessMessage(labels[mode] || '');
  }
  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ theme: mode }),
  }).catch(() => {});
}

function cycleHeaderTheme() {
  const current = Number(window.darkThemeMode);
  const next = Number.isFinite(current) ? (current + 1) % 3 : 2;
  saveThemeMode(next);
}

document.addEventListener('DOMContentLoaded', function () {
  const button = document.getElementById('headerThemeToggle');
  if (button) {
    button.addEventListener('click', cycleHeaderTheme);
  }
});
