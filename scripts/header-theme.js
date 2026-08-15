function headerThemeIcon(mode) {
  if (mode === 1) {
    return 'fa-moon';
  }
  if (mode === 0) {
    return 'fa-sun';
  }
  return 'fa-circle-half-stroke';
}

function slotBackground(key) {
  const item = window.appearanceConfig && window.appearanceConfig.backgrounds
    ? window.appearanceConfig.backgrounds[key]
    : null;
  if (!item) {
    return { raw: '', css: '' };
  }
  return { raw: item.raw || '', css: item.css || '' };
}

function currentAppearanceBackground() {
  const dark = document.body.classList.contains('dark');
  return slotBackground('bg_desktop_' + (dark ? 'dark' : 'light')).css || '';
}

function applyPageBackground() {
  const css = currentAppearanceBackground();
  const layer = document.getElementById('page-bg-layer');
  document.documentElement.style.setProperty('--page-bg', css || 'none');
  document.body.classList.toggle('has-page-bg', !!css);
  if (layer) {
    layer.style.backgroundImage = css || 'none';
  }
}

function applyColorThemeStylesheet(themeColor) {
  const next = themeColor || 'blue';
  const previous = window.colorTheme || 'blue';
  ['red', 'green', 'yellow', 'purple', 'pink'].forEach((id) => {
    const el = document.getElementById(id + '-theme');
    if (el) {
      el.disabled = next !== id;
    }
  });
  if (previous && previous !== next) {
    document.querySelectorAll('img').forEach((img) => {
      if (img.src.includes('siteicons/' + previous)) {
        img.src = img.src.replace(previous, next);
      }
    });
  }
  window.colorTheme = next;
  document.cookie = 'colorTheme=' + next + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';
}

function colorThemeForMode(mode) {
  const cfg = window.appearanceConfig || {};
  const light = cfg.color_theme_light || window.colorTheme || 'blue';
  const dark = cfg.color_theme_dark || light;
  return mode === 'dark' ? dark : light;
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
  applyColorThemeStylesheet(colorThemeForMode(inUse));

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

function saveTimezone(value) {
  const headerSelect = document.getElementById('headerTimezone');
  const settingsSelect = document.getElementById('userTimezone');
  if (headerSelect && headerSelect.value !== value) {
    headerSelect.value = value;
  }
  if (settingsSelect && settingsSelect.value !== value) {
    settingsSelect.value = value;
  }

  fetch('endpoints/settings/timezone.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
      'X-CSRF-Token': window.csrfToken,
    },
    body: 'timezone=' + encodeURIComponent(value || ''),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (typeof showSuccessMessage === 'function') {
          showSuccessMessage(data.message);
        }
        window.setTimeout(() => window.location.reload(), 250);
      } else if (typeof showErrorMessage === 'function') {
        showErrorMessage(data.message);
      }
    })
    .catch(() => {
      if (typeof showErrorMessage === 'function') {
        showErrorMessage(typeof translate === 'function' ? translate('unknown_error') : '');
      }
    });
}

function setSidebarVisible(on) {
  document.body.classList.toggle('has-app-sidebar', !!on);
  document.cookie = 'wallos_sidebar=' + (on ? '1' : '0') + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';
  const toggle = document.getElementById('headerSidebarToggle');
  if (toggle) {
    const icon = toggle.querySelector('i');
    if (icon) {
      icon.className = 'fa-solid ' + (on ? 'fa-table-columns' : 'fa-bars');
    }
    toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
  }
  if (typeof pinPageNav === 'function') {
    window.setTimeout(pinPageNav, 50);
  }
}

document.addEventListener('DOMContentLoaded', function () {
  applyPageBackground();
  document.querySelectorAll('.app-sidebar-link').forEach((link) => {
    const href = link.getAttribute('href');
    if (href && href !== '#') {
      link.addEventListener('mouseenter', () => {
        if (document.querySelector('link[rel="prefetch"][href="' + href + '"]')) {
          return;
        }
        const prefetch = document.createElement('link');
        prefetch.rel = 'prefetch';
        prefetch.href = href;
        document.head.appendChild(prefetch);
      });
      link.addEventListener('click', () => {
        document.querySelectorAll('.app-sidebar-link').forEach((item) => item.classList.remove('active'));
        link.classList.add('active');
      });
    }
  });
  const sidebarToggle = document.getElementById('headerSidebarToggle');
  if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
      setSidebarVisible(!document.body.classList.contains('has-app-sidebar'));
    });
  }
  const button = document.getElementById('headerThemeToggle');
  if (button) {
    button.addEventListener('click', cycleHeaderTheme);
  }

  const timezoneToggle = document.getElementById('headerTimezoneToggle');
  const timezonePanel = document.getElementById('headerTimezonePanel');
  const timezoneSelect = document.getElementById('headerTimezone');
  if (timezoneToggle && timezonePanel) {
    timezoneToggle.addEventListener('click', (event) => {
      event.stopPropagation();
      timezonePanel.classList.toggle('is-open');
    });
    document.addEventListener('click', (event) => {
      if (!timezonePanel.contains(event.target) && !timezoneToggle.contains(event.target)) {
        timezonePanel.classList.remove('is-open');
      }
    });
  }
  if (timezoneSelect) {
    timezoneSelect.addEventListener('change', () => {
      saveTimezone(timezoneSelect.value);
    });
  }
});
