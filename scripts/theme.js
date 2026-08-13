function switchTheme() {
  const darkThemeCss = document.querySelector("#dark-theme");
  darkThemeCss.disabled = !darkThemeCss.disabled;

  const themeChoice = darkThemeCss.disabled ? 'light' : 'dark';
  document.cookie = 'theme=' + themeValue + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';

  document.body.className = themeChoice;

  const button = document.getElementById("switchTheme");
  button.disabled = true;

  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ theme: themeChoice === 'dark' })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    }).catch(error => {
      button.disabled = false;
    });
}

function setDarkTheme(theme) {
  const darkThemeButton = document.querySelector("#theme-dark");
  const lightThemeButton = document.querySelector("#theme-light");
  const automaticThemeButton = document.querySelector("#theme-automatic");
  const darkThemeCss = document.querySelector("#dark-theme");
  const themes = { 0: 'light', 1: 'dark', 2: 'automatic' };
  const themeValue = themes[theme];
  const prefersDarkMode = window.matchMedia('(prefers-color-scheme: dark)').matches;

  darkThemeButton.disabled = true;
  lightThemeButton.disabled = true;
  automaticThemeButton.disabled = true;

  fetch('endpoints/settings/theme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ theme: theme })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        darkThemeButton.disabled = false;
        lightThemeButton.disabled = false;
        automaticThemeButton.disabled = false;
        darkThemeButton.classList.remove('selected');
        lightThemeButton.classList.remove('selected');
        automaticThemeButton.classList.remove('selected');

        document.cookie = `theme=${themeValue}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;

        if (theme == 0) {
          darkThemeCss.disabled = true;
          document.body.className = 'light';
          lightThemeButton.classList.add('selected');
        }

        if (theme == 1) {
          darkThemeCss.disabled = false;
          document.body.className = 'dark';
          darkThemeButton.classList.add('selected');
        }

        if (theme == 2) {
          darkThemeCss.disabled = !prefersDarkMode;
          document.body.className = prefersDarkMode ? 'dark' : 'light';
          automaticThemeButton.classList.add('selected');
          document.cookie = `inUseTheme=${prefersDarkMode ? 'dark' : 'light'}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
        }

        showSuccessMessage(data.message);
        window.darkThemeMode = theme;
        if (typeof applyResolvedTheme === 'function') {
          applyResolvedTheme(theme);
        }
      } else {
        showErrorMessage(data.message);
        darkThemeButton.disabled = false;
        lightThemeButton.disabled = false;
        automaticThemeButton.disabled = false;
      }
    }).catch(error => {
      darkThemeButton.disabled = false;
      lightThemeButton.disabled = false;
      automaticThemeButton.disabled = false;
    });
}

function setTheme(themeColor) {
  var currentTheme = 'blue';
  var themeIds = ['red-theme', 'green-theme', 'yellow-theme', 'purple-theme'];

  themeIds.forEach(function (id) {
    var themeStylesheet = document.getElementById(id);
    if (themeStylesheet && !themeStylesheet.disabled) {
      currentTheme = id.replace('-theme', '');
      themeStylesheet.disabled = true;
    }
  });

  if (themeColor !== "blue") {
    var enableTheme = document.getElementById(themeColor + '-theme');
    enableTheme.disabled = false;
  }

  var images = document.querySelectorAll('img');
  images.forEach(function (img) {
    if (img.src.includes('siteicons/' + currentTheme)) {
      img.src = img.src.replace(currentTheme, themeColor);
    }
  });

  var labels = document.querySelectorAll('.theme-preview');
  labels.forEach(function (label) {
    label.classList.remove('is-selected');
  });

  var targetLabel = document.querySelector(`.theme-preview.${themeColor}`);
  if (targetLabel) {
    targetLabel.classList.add('is-selected');
  }

  document.cookie = `colorTheme=${themeColor}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;

  fetch('endpoints/settings/colortheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ color: themeColor })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
    });

}

function resetCustomColors() {
  const button = document.getElementById("reset-colors");
  button.disabled = true;

  fetch("endpoints/settings/resettheme.php", {
    method: "POST",
    headers: {
      "X-CSRF-Token": window.csrfToken,
    },
    body: new URLSearchParams({
      action: "reset",
    }),
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);

        const customThemeColors = document.getElementById("custom_theme_colors");
        if (customThemeColors) {
          customThemeColors.remove();
        }

        document.documentElement.style.removeProperty("--main-color");
        document.documentElement.style.removeProperty("--accent-color");
        document.documentElement.style.removeProperty("--hover-color");

        document.getElementById("mainColor").value = "#FFFFFF";
        document.getElementById("accentColor").value = "#FFFFFF";
        document.getElementById("hoverColor").value = "#FFFFFF";
      } else {
        showErrorMessage(data.message || translate("failed_reset_colors"));
      }
    })
    .catch(error => {
      console.error(error);
      showErrorMessage(translate("unknown_error"));
    })
    .finally(() => {
      button.disabled = false;
    });
}


function saveCustomColors() {
  const button = document.getElementById("save-colors");
  button.disabled = true;

  const mainColor = document.getElementById("mainColor").value;
  const accentColor = document.getElementById("accentColor").value;
  const hoverColor = document.getElementById("hoverColor").value;

  fetch('endpoints/settings/customtheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ mainColor: mainColor, accentColor: accentColor, hoverColor: hoverColor })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
        document.documentElement.style.setProperty('--main-color', mainColor);
        document.documentElement.style.setProperty('--accent-color', accentColor);
        document.documentElement.style.setProperty('--hover-color', hoverColor);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });

}

function appearanceHasPendingFile() {
  return Array.from(document.querySelectorAll('.appearance-slot .bg-file')).some((input) => input.files && input.files[0]);
}

function cssForBackgroundChoice(type, color, current, isDark) {
  if (!type) {
    return '';
  }
  if (type.startsWith('preset:')) {
    const name = type.slice(7);
    const preset = window.appearanceConfig && window.appearanceConfig.presets
      ? window.appearanceConfig.presets[name]
      : null;
    if (!preset) {
      return '';
    }
    return isDark ? preset.css_dark : preset.css;
  }
  if (type === 'color') {
    return color;
  }
  if (type === 'image' && current && current.startsWith('image:')) {
    return "url('images/uploads/backgrounds/" + current.slice(6) + "')";
  }
  return '';
}

function applyLiveGlass() {
  const enabled = document.getElementById('glassEnabled')?.checked;
  const blur = document.getElementById('glassBlur')?.value || '16';
  const opacity = document.getElementById('glassOpacity')?.value || '70';
  document.body.classList.toggle('glass-enabled', !!enabled);
  document.documentElement.style.setProperty('--glass-blur', blur + 'px');
  document.documentElement.style.setProperty('--glass-alpha', String(Number(opacity) / 100));
  if (window.appearanceConfig) {
    window.appearanceConfig.glass_enabled = enabled ? 1 : 0;
    window.appearanceConfig.glass_blur = Number(blur);
    window.appearanceConfig.glass_opacity = Number(opacity);
  }
}

function applyLiveBackgrounds() {
  document.querySelectorAll('.appearance-slot').forEach((slot) => {
    const key = slot.dataset.slot;
    const type = slot.querySelector('.bg-type').value;
    const color = slot.querySelector('.bg-color').value;
    const current = slot.querySelector('.bg-current').value;
    const fileInput = slot.querySelector('.bg-file');
    const isDark = key.includes('dark');
    let css = cssForBackgroundChoice(type, color, current, isDark);
    if (type === 'image' && fileInput.files[0]) {
      css = "url('" + URL.createObjectURL(fileInput.files[0]) + "')";
    }
    if (!window.appearanceConfig.backgrounds[key]) {
      window.appearanceConfig.backgrounds[key] = {};
    }
    window.appearanceConfig.backgrounds[key].css = css;
    window.appearanceConfig.backgrounds[key].raw = type.startsWith('preset:') || type === ''
      ? type
      : (type === 'color' ? 'color:' + color : current);
    const preview = slot.querySelector('.appearance-preview');
    if (preview) {
      preview.style.background = css || 'transparent';
    }
    const varMap = {
      bg_desktop_light: '--page-bg-desktop-light',
      bg_desktop_dark: '--page-bg-desktop-dark',
      bg_mobile_light: '--page-bg-mobile-light',
      bg_mobile_dark: '--page-bg-mobile-dark',
    };
    document.documentElement.style.setProperty(varMap[key], css || 'none');
  });
  if (typeof applyPageBackground === 'function') {
    applyPageBackground();
  }
}

function persistAppearance(options = {}) {
  const includeFiles = !!options.includeFiles;
  const reload = !!options.reload;
  const silent = !!options.silent;
  const button = document.getElementById('saveAppearance');
  if (button && includeFiles) {
    button.disabled = true;
  }

  const formData = new FormData();
  formData.append('timezone', document.getElementById('userTimezone')?.value || '');
  formData.append('glass_enabled', document.getElementById('glassEnabled')?.checked ? '1' : '0');
  formData.append('glass_blur', document.getElementById('glassBlur')?.value || '16');
  formData.append('glass_opacity', document.getElementById('glassOpacity')?.value || '70');

  document.querySelectorAll('.appearance-slot').forEach((slot) => {
    const key = slot.dataset.slot;
    const type = slot.querySelector('.bg-type').value;
    const current = slot.querySelector('.bg-current').value;
    const color = slot.querySelector('.bg-color').value;
    const fileInput = slot.querySelector('.bg-file');
    if (type === '') {
      formData.append(key, '');
    } else if (type.startsWith('preset:')) {
      formData.append(key, type);
    } else if (type === 'color') {
      formData.append(key, 'color:' + color);
    } else if (type === 'image') {
      if (includeFiles && fileInput.files[0]) {
        formData.append(key + '_file', fileInput.files[0]);
        formData.append(key, current);
      } else {
        formData.append(key, current.startsWith('image:') ? current : '');
      }
    }
  });

  return fetch('endpoints/settings/appearance.php', {
    method: 'POST',
    headers: {
      'X-CSRF-Token': window.csrfToken,
    },
    body: formData,
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        if (!silent) {
          showSuccessMessage(data.message);
        }
        if (reload) {
          window.setTimeout(() => window.location.reload(), 300);
        }
      } else if (!silent) {
        showErrorMessage(data.message);
      }
      return data;
    })
    .catch(() => {
      if (!silent) {
        showErrorMessage(translate('unknown_error'));
      }
    })
    .finally(() => {
      if (button) {
        button.disabled = false;
      }
    });
}

function saveAppearance() {
  persistAppearance({ includeFiles: true, reload: appearanceHasPendingFile() });
}

let appearanceSaveTimer = null;
function queueAppearanceSave() {
  window.clearTimeout(appearanceSaveTimer);
  appearanceSaveTimer = window.setTimeout(() => {
    persistAppearance({ includeFiles: false, silent: true });
  }, 350);
}

function updateAppearanceSaveVisibility() {
  const button = document.getElementById('saveAppearance');
  if (button) {
    button.classList.toggle('hide', !appearanceHasPendingFile());
  }
}

document.addEventListener('DOMContentLoaded', function () {
  const blur = document.getElementById('glassBlur');
  const opacity = document.getElementById('glassOpacity');
  const glassEnabled = document.getElementById('glassEnabled');
  if (blur) {
    blur.addEventListener('input', () => {
      const label = document.getElementById('glassBlurValue');
      if (label) label.textContent = blur.value;
      applyLiveGlass();
      queueAppearanceSave();
    });
  }
  if (opacity) {
    opacity.addEventListener('input', () => {
      const label = document.getElementById('glassOpacityValue');
      if (label) label.textContent = opacity.value;
      applyLiveGlass();
      queueAppearanceSave();
    });
  }
  if (glassEnabled) {
    glassEnabled.addEventListener('change', () => {
      applyLiveGlass();
      queueAppearanceSave();
    });
  }
  document.querySelectorAll('.appearance-slot .bg-type').forEach((select) => {
    select.addEventListener('change', function () {
      const slot = this.closest('.appearance-slot');
      slot.querySelector('.bg-color').classList.toggle('hide', this.value !== 'color');
      const fileButton = slot.querySelector('.bg-file-button');
      if (fileButton) {
        fileButton.classList.toggle('hide', this.value !== 'image');
      }
      applyLiveBackgrounds();
      if (this.value !== 'image') {
        queueAppearanceSave();
      }
      updateAppearanceSaveVisibility();
    });
  });
  document.querySelectorAll('.appearance-slot .bg-color').forEach((input) => {
    input.addEventListener('input', () => {
      applyLiveBackgrounds();
      queueAppearanceSave();
    });
  });
  document.querySelectorAll('.appearance-slot .bg-file').forEach((input) => {
    input.addEventListener('change', () => {
      applyLiveBackgrounds();
      updateAppearanceSaveVisibility();
    });
  });
  updateAppearanceSaveVisibility();
});

function saveCustomCss() {
  const button = document.getElementById("save-css");
  button.disabled = true;

  const customCss = document.getElementById("customCss").value;

  fetch('endpoints/settings/customcss.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ customCss: customCss })
  })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        showSuccessMessage(data.message);
      } else {
        showErrorMessage(data.message);
      }
      button.disabled = false;
    })
    .catch(error => {
      showErrorMessage(translate('unknown_error'));
      button.disabled = false;
    });
}