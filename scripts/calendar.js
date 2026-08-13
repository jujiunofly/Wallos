function nextMonth(currentMonth, currentYear) {
  let nextMonth = currentMonth + 1;
  let nextYear = currentYear;
  if (nextMonth > 12) {
    nextMonth = 1;
    nextYear += 1;
  }
  window.location.href = `calendar.php?month=${nextMonth}&year=${nextYear}`;
}

function prevMonth(currentMonth, currentYear) {
  let prevMonth = currentMonth - 1;
  let prevYear = currentYear;
  if (prevMonth < 1) {
    prevMonth = 12;
    prevYear -= 1;
  }
  window.location.href = `calendar.php?month=${prevMonth}&year=${prevYear}`;
}

function currentMoth() {
    window.location.href = `calendar.php`;
}

function showExportPopup() {
  const host = window.location.href;
  const apiPath = "api/subscriptions/get_ical_feed.php";
  const apiKey = document.getElementById('apiKey').value;
  const queryParams = `?api_key=${apiKey}`;
  const fullUrl = host.replace('calendar.php', apiPath) + queryParams;
  document.getElementById('iCalendarUrl').value = fullUrl;
  document.getElementById('subscriptions_calendar').classList.add('is-open');
}

function closePopup() {
  document.getElementById('subscriptions_calendar').classList.remove('is-open');
}

function formatClockTime(date, timeZone) {
  const options = {
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
    timeZoneName: 'short',
  };
  if (timeZone) {
    options.timeZone = timeZone;
  }
  try {
    return new Intl.DateTimeFormat('en-GB', options).format(date).replace(',', '');
  } catch (e) {
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${hours}:${minutes}`;
  }
}

function startCalendarClocks() {
  const appTimeEl = document.getElementById('calendar-app-time');
  const localTimeEl = document.getElementById('calendar-local-time');
  if (!appTimeEl || !localTimeEl) {
    return;
  }

  const serverEpoch = Number(appTimeEl.dataset.serverEpoch);
  const timezone = appTimeEl.dataset.timezone || 'UTC';
  const pageLoadMs = Date.now();
  const hasServerEpoch = Number.isFinite(serverEpoch) && serverEpoch > 0;

  const tick = () => {
    const elapsedMs = Date.now() - pageLoadMs;
    const appNow = hasServerEpoch
      ? new Date((serverEpoch * 1000) + elapsedMs)
      : new Date();
    appTimeEl.textContent = formatClockTime(appNow, timezone);
    localTimeEl.textContent = formatClockTime(new Date());
  };

  tick();
  window.setInterval(tick, 15000);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', startCalendarClocks);
} else {
  startCalendarClocks();
}

function copyToClipboard() {
  const urlField = document.getElementById('iCalendarUrl');
  urlField.select();
  urlField.setSelectionRange(0, 99999); // For mobile devices
  navigator.clipboard.writeText(urlField.value)
      .then(() => {
          showSuccessMessage(translate('copied_to_clipboard'));
      })
      .catch(() => {
          showErrorMessage(translate('unknown_error'));
      });
}