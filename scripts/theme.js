function switchTheme() {
  const darkThemeCss = document.querySelector("#dark-theme");
  darkThemeCss.disabled = !darkThemeCss.disabled;

  const themeChoice = darkThemeCss.disabled ? 'light' : 'dark';
  document.cookie = 'theme=' + themeValue + '; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax';

  document.body.classList.remove('light', 'dark');
  document.body.classList.add(themeChoice);

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

        showSuccessMessage(data.message);
        window.darkThemeMode = theme;
        if (typeof applyResolvedTheme === 'function') {
          applyResolvedTheme(theme);
        } else {
          document.body.classList.remove('light', 'dark');
          if (theme == 0) {
            darkThemeCss.disabled = true;
            document.body.classList.add('light');
            lightThemeButton.classList.add('selected');
          } else if (theme == 1) {
            darkThemeCss.disabled = false;
            document.body.classList.add('dark');
            darkThemeButton.classList.add('selected');
          } else {
            darkThemeCss.disabled = !prefersDarkMode;
            document.body.classList.add(prefersDarkMode ? 'dark' : 'light');
            automaticThemeButton.classList.add('selected');
            document.cookie = `inUseTheme=${prefersDarkMode ? 'dark' : 'light'}; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
          }
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

function setTheme(themeColor, mode) {
  const inUse = document.body.classList.contains('dark') ? 'dark' : 'light';
  const target = mode === 'dark' || mode === 'light' ? mode : inUse;
  if (!window.appearanceConfig) {
    window.appearanceConfig = {};
  }
  if (target === 'dark') {
    window.appearanceConfig.color_theme_dark = themeColor;
  } else {
    window.appearanceConfig.color_theme_light = themeColor;
  }
  if (target === inUse && typeof applyColorThemeStylesheet === 'function') {
    applyColorThemeStylesheet(themeColor);
  }

  document.querySelectorAll('input[name="theme_' + target + '"]').forEach((input) => {
    input.checked = input.value === themeColor;
    const label = document.querySelector('label[for="' + input.id + '"]');
    if (label) {
      label.classList.toggle('is-selected', input.value === themeColor);
    }
  });

  fetch('endpoints/settings/colortheme.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ color: themeColor, mode: target })
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

function backgroundImageUrl(raw) {
  if (raw && raw.indexOf('image:') === 0) {
    return 'images/uploads/backgrounds/' + raw.slice(6);
  }
  return '';
}

function syncBackgroundSlotUi(slot) {
  if (!slot) {
    return;
  }
  const type = slot.querySelector('.bg-type')?.value || '';
  const current = slot.querySelector('.bg-current')?.value || '';
  const colorRow = slot.querySelector('.bg-color-row');
  const imageRow = slot.querySelector('.bg-image-row');
  const thumb = slot.querySelector('.bg-thumb');
  if (colorRow) {
    colorRow.classList.toggle('is-hidden', type !== 'color');
  }
  if (imageRow) {
    imageRow.classList.toggle('is-hidden', type !== 'image');
  }
  const url = backgroundImageUrl(current);
  if (thumb) {
    if (url) {
      thumb.src = url + (url.indexOf('?') === -1 ? '?t=' + Date.now() : '');
      thumb.classList.remove('is-empty');
    } else {
      thumb.removeAttribute('src');
      thumb.classList.add('is-empty');
    }
  }
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
  if (type === 'color' && color) {
    return 'linear-gradient(180deg, ' + color + ' 0%, ' + color + ' 100%)';
  }
  if (type === 'image' && current && current.startsWith('image:')) {
    return "url('images/uploads/backgrounds/" + current.slice(6) + "?v=" + Date.now() + "')";
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
      : (type === 'color' ? 'color:' + color : (current || window.appearanceConfig.backgrounds[key].raw || ''));
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
  formData.append('timezone', document.getElementById('userTimezone')?.value || document.getElementById('headerTimezone')?.value || '');
  formData.append('glass_enabled', document.getElementById('glassEnabled')?.checked ? '1' : '0');
  formData.append('glass_blur', document.getElementById('glassBlur')?.value || '16');
  formData.append('glass_opacity', document.getElementById('glassOpacity')?.value || '70');
  formData.append('header_title', document.getElementById('headerTitleInput')?.value || '');
  formData.append('header_title_size', document.getElementById('headerTitleSize')?.value || '18');
  if (options.extraFile && options.extraFile.key && options.extraFile.file) {
    formData.append(options.extraFile.key, options.extraFile.file);
  }

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
      const previous = (window.appearanceConfig && window.appearanceConfig.backgrounds && window.appearanceConfig.backgrounds[key]
        ? window.appearanceConfig.backgrounds[key].raw
        : '') || '';
      const keptImage = current.startsWith('image:') ? current : (previous.startsWith('image:') ? previous : '');
      if (includeFiles && fileInput.files[0]) {
        formData.append(key + '_file', fileInput.files[0]);
        formData.append(key, keptImage);
      } else {
        formData.append(key, keptImage);
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
        if (data.backgrounds) {
          document.querySelectorAll('.appearance-slot').forEach((slot) => {
            const saved = data.backgrounds[slot.dataset.slot];
            if (!saved) {
              return;
            }
            slot.querySelector('.bg-current').value = saved.raw || '';
            if (!window.appearanceConfig.backgrounds[slot.dataset.slot]) {
              window.appearanceConfig.backgrounds[slot.dataset.slot] = {};
            }
            window.appearanceConfig.backgrounds[slot.dataset.slot].raw = saved.raw || '';
            window.appearanceConfig.backgrounds[slot.dataset.slot].css = saved.css || '';
            const typeSelect = slot.querySelector('.bg-type');
            const fileInput = slot.querySelector('.bg-file');
            if (saved.raw && saved.raw.indexOf('image:') === 0) {
              if (typeSelect) {
                typeSelect.value = 'image';
              }
              if (fileInput) {
                fileInput.value = '';
              }
            } else if (saved.raw && saved.raw.indexOf('color:') === 0) {
              if (typeSelect) {
                typeSelect.value = 'color';
              }
            }
            syncBackgroundSlotUi(slot);
          });
          applyLiveBackgrounds();
          updateAppearanceSaveVisibility();
        }
        if (data.media) {
          rebuildMediaLists(data.media);
        }
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

function applyAppLogo(url) {
  document.querySelectorAll('.app-logo-slot').forEach((slot) => {
    if (url) {
      slot.innerHTML = '<img src="' + url + (url.indexOf('?') === -1 ? '?t=' + Date.now() : '') + '" alt="Wallos">';
    }
  });
}

function applyHeaderTitle(text, size) {
  const el = document.getElementById('headerTitleText');
  if (!el) {
    return;
  }
  const value = (text || '').trim();
  el.textContent = value;
  el.classList.toggle('is-hidden', value === '');
  if (size) {
    el.style.fontSize = size + 'px';
    document.documentElement.style.setProperty('--header-title-size', size + 'px');
  }
}

function applyFavicon(url) {
  const link = document.getElementById('appFavicon');
  const preview = document.querySelector('#appFaviconPreview img');
  const href = url || 'images/icon/favicon.ico';
  if (link) {
    link.href = href + (href.indexOf('?') === -1 ? '?t=' + Date.now() : '');
  }
  if (preview) {
    preview.src = href + (href.indexOf('?') === -1 ? '?t=' + Date.now() : '');
  }
}

function persistAppLogo(options = {}) {
  const formData = new FormData();
  formData.append('timezone', document.getElementById('userTimezone')?.value || document.getElementById('headerTimezone')?.value || '');
  formData.append('glass_enabled', document.getElementById('glassEnabled')?.checked ? '1' : '0');
  formData.append('glass_blur', document.getElementById('glassBlur')?.value || '16');
  formData.append('glass_opacity', document.getElementById('glassOpacity')?.value || '70');
  formData.append('header_title', document.getElementById('headerTitleInput')?.value || '');
  formData.append('header_title_size', document.getElementById('headerTitleSize')?.value || '18');
  if (options.reset) {
    formData.append('app_logo_reset', '1');
  }
  if (options.file) {
    formData.append('app_logo_file', options.file);
  }
  if (options.faviconReset) {
    formData.append('app_favicon_reset', '1');
  }
  if (options.faviconFile) {
    formData.append('app_favicon_file', options.faviconFile);
  }
  if (options.logoSelect) {
    formData.append('app_logo_select', options.logoSelect);
  }
  if (options.faviconSelect) {
    formData.append('app_favicon_select', options.faviconSelect);
  }
  document.querySelectorAll('.appearance-slot').forEach((slot) => {
    const key = slot.dataset.slot;
    const type = slot.querySelector('.bg-type').value;
    const current = slot.querySelector('.bg-current').value;
    const color = slot.querySelector('.bg-color').value;
    if (type === '') {
      formData.append(key, '');
    } else if (type.startsWith('preset:')) {
      formData.append(key, type);
    } else if (type === 'color') {
      formData.append(key, 'color:' + color);
    } else if (type === 'image') {
      formData.append(key, current.startsWith('image:') ? current : '');
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
        if (window.appearanceConfig) {
          window.appearanceConfig.app_logo = data.app_logo || '';
          window.appearanceConfig.app_logo_url = data.app_logo_url || '';
        }
        if (data.app_logo_url) {
          applyAppLogo(data.app_logo_url);
        } else if (options.reset) {
          window.location.reload();
          return data;
        }
        if (Object.prototype.hasOwnProperty.call(data, 'app_favicon_url')) {
          applyFavicon(data.app_favicon_url || '');
          if (options.faviconReset && !data.app_favicon_url) {
            applyFavicon('');
          }
        }
        if (Object.prototype.hasOwnProperty.call(data, 'header_title')) {
          applyHeaderTitle(data.header_title, data.header_title_size);
        }
        if (data.media) {
          rebuildMediaLists(data.media);
        }
        if (typeof showSuccessMessage === 'function') {
          showSuccessMessage(data.message);
        }
      } else if (typeof showErrorMessage === 'function') {
        showErrorMessage(data.message);
      }
      return data;
    })
    .catch(() => {
      if (typeof showErrorMessage === 'function') {
        showErrorMessage(typeof translate === 'function' ? translate('unknown_error') : '');
      }
    });
}

function saveAppearance() {
  persistAppearance({ includeFiles: true, reload: false });
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
    button.classList.toggle('is-hidden', !appearanceHasPendingFile());
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
      syncBackgroundSlotUi(slot);
      applyLiveBackgrounds();
      if (this.value.startsWith('preset:')) {
        const name = this.value.slice(7);
        const preset = window.appearanceConfig && window.appearanceConfig.presets
          ? window.appearanceConfig.presets[name]
          : null;
        if (preset && preset.theme && typeof setTheme === 'function') {
          setTheme(preset.theme);
        }
      }
      if (this.value !== 'image') {
        queueAppearanceSave();
      }
      updateAppearanceSaveVisibility();
    });
  });
  document.querySelectorAll('.appearance-slot .bg-thumb-remove').forEach((button) => {
    button.addEventListener('click', () => {
      const slot = button.closest('.appearance-slot');
      const typeSelect = slot.querySelector('.bg-type');
      const current = slot.querySelector('.bg-current');
      if (typeSelect) {
        typeSelect.value = '';
      }
      if (current) {
        current.value = '';
      }
      if (window.appearanceConfig && window.appearanceConfig.backgrounds && slot.dataset.slot) {
        window.appearanceConfig.backgrounds[slot.dataset.slot] = { raw: '', css: '' };
      }
      syncBackgroundSlotUi(slot);
      applyLiveBackgrounds();
      persistAppearance({ includeFiles: false, silent: false });
    });
  });
  document.querySelectorAll('.appearance-slot .bg-color').forEach((input) => {
    const persistColor = () => {
      applyLiveBackgrounds();
      queueAppearanceSave();
    };
    input.addEventListener('input', persistColor);
    input.addEventListener('change', persistColor);
  });
  document.querySelectorAll('.appearance-slot .bg-file').forEach((input) => {
    input.addEventListener('change', () => {
      applyLiveBackgrounds();
      if (input.files && input.files[0]) {
        persistAppearance({ includeFiles: true, silent: false });
        closeAllMediaPickers();
      }
      updateAppearanceSaveVisibility();
    });
  });
  updateAppearanceSaveVisibility();

  const appLogoFile = document.getElementById('appLogoFile');
  if (appLogoFile) {
    appLogoFile.addEventListener('change', () => {
      if (appLogoFile.files && appLogoFile.files[0]) {
        persistAppLogo({ file: appLogoFile.files[0] }).finally(() => {
          appLogoFile.value = '';
        });
      }
    });
  }
  const resetAppLogo = document.getElementById('resetAppLogo');
  if (resetAppLogo) {
    resetAppLogo.addEventListener('click', () => {
      persistAppLogo({ reset: true });
    });
  }
  const appFaviconFile = document.getElementById('appFaviconFile');
  if (appFaviconFile) {
    appFaviconFile.addEventListener('change', () => {
      if (appFaviconFile.files && appFaviconFile.files[0]) {
        persistAppLogo({ faviconFile: appFaviconFile.files[0] }).finally(() => {
          appFaviconFile.value = '';
        });
      }
    });
  }
  const resetAppFavicon = document.getElementById('resetAppFavicon');
  if (resetAppFavicon) {
    resetAppFavicon.addEventListener('click', () => {
      persistAppLogo({ faviconReset: true });
    });
  }
  const headerTitleInput = document.getElementById('headerTitleInput');
  const headerTitleSize = document.getElementById('headerTitleSize');
  if (headerTitleInput) {
    headerTitleInput.addEventListener('input', () => {
      applyHeaderTitle(headerTitleInput.value, headerTitleSize ? headerTitleSize.value : 18);
      queueAppearanceSave();
    });
  }
  if (headerTitleSize) {
    headerTitleSize.addEventListener('input', () => {
      const label = document.getElementById('headerTitleSizeValue');
      if (label) {
        label.textContent = headerTitleSize.value;
      }
      applyHeaderTitle(headerTitleInput ? headerTitleInput.value : '', headerTitleSize.value);
      queueAppearanceSave();
    });
  }

  initMediaPickers();
});

function closeAllMediaPickers() {
  document.querySelectorAll('.media-picker').forEach((picker) => picker.classList.remove('is-open'));
}

function initMediaPickers() {
  document.querySelectorAll('.media-picker-toggle').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      const id = button.getAttribute('data-picker');
      const wrap = document.getElementById(id);
      const picker = wrap ? wrap.querySelector('.media-picker') : null;
      if (!picker) {
        return;
      }
      const willOpen = !picker.classList.contains('is-open');
      closeAllMediaPickers();
      if (willOpen) {
        if (button.dataset.slot) {
          picker.dataset.targetSlot = button.dataset.slot;
        }
        picker.classList.add('is-open');
      }
    });
  });
  document.querySelectorAll('.media-picker').forEach(bindMediaItems);
  document.addEventListener('click', (event) => {
    if (!event.target.closest('.media-picker') && !event.target.closest('.media-picker-toggle')) {
      closeAllMediaPickers();
    }
  });
}

function bindMediaItems(picker) {
  picker.querySelectorAll('.media-item').forEach((item) => {
    item.addEventListener('click', (event) => {
      if (event.target.closest('.media-item-remove')) {
        return;
      }
      selectMedia(picker, item.dataset.file);
    });
  });
  picker.querySelectorAll('.media-item-remove').forEach((button) => {
    button.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      deleteMedia(picker.dataset.kind, button.dataset.file);
    });
  });
}

function selectMedia(picker, filename) {
  const wrap = picker.closest('.media-picker-host') || picker.parentElement;
  const wrapId = wrap ? wrap.id : '';
  if (wrapId === 'logoPicker') {
    persistAppLogo({ logoSelect: filename });
  } else if (wrapId === 'faviconPicker') {
    persistAppLogo({ faviconSelect: filename });
  } else {
    const slot = picker.closest('.appearance-slot')
      || document.querySelector('.appearance-slot[data-slot="' + (picker.dataset.targetSlot || '') + '"]');
    if (slot) {
      const typeSelect = slot.querySelector('.bg-type');
      const current = slot.querySelector('.bg-current');
      if (typeSelect) {
        typeSelect.value = 'image';
      }
      if (current) {
        current.value = 'image:' + filename;
      }
      syncBackgroundSlotUi(slot);
      applyLiveBackgrounds();
      persistAppearance({ includeFiles: false, silent: false });
    }
  }
  picker.classList.remove('is-open');
}

function deleteMedia(kind, filename) {
  fetch('endpoints/settings/delete_media.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': window.csrfToken,
    },
    body: JSON.stringify({ kind: kind, filename: filename }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        rebuildMediaLists(data.media);
        if (typeof showSuccessMessage === 'function') {
          showSuccessMessage(data.message);
        }
      } else if (typeof showErrorMessage === 'function') {
        showErrorMessage(data.message);
      }
    })
    .catch(() => {
      if (typeof showErrorMessage === 'function') {
        showErrorMessage(translate('unknown_error'));
      }
    });
}

function rebuildMediaLists(media) {
  if (!media) {
    return;
  }
  document.querySelectorAll('.media-picker').forEach((picker) => {
    const kind = picker.dataset.kind;
    const items = media[kind] || [];
    const list = picker.querySelector('.media-picker-list');
    const add = list ? list.querySelector('.media-item-add') : null;
    if (!list || !add) {
      return;
    }
    list.querySelectorAll('.media-item').forEach((el) => el.remove());
    items.forEach((item) => {
      const div = document.createElement('div');
      div.className = 'media-item';
      div.dataset.file = item.filename;
      div.innerHTML = '<img src="' + item.url + '" alt="" class="media-item-thumb"><button type="button" class="media-item-remove" data-file="' + item.filename + '"><i class="fa-solid fa-xmark"></i></button>';
      list.insertBefore(div, add);
    });
    bindMediaItems(picker);
  });
}

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