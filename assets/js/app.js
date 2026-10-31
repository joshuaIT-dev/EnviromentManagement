(() => {
  window.lucide?.createIcons();
  const savePreference = (name, value) => {
    try {
      const path = document.body.dataset.appBase || '/EnviromentManagement/';
      document.cookie = `enviro-${name}=${encodeURIComponent(value)}; Max-Age=31536000; Path=${path}; SameSite=Lax${location.protocol === 'https:' ? '; Secure' : ''}`;
      localStorage.setItem('enviro-' + name, value);
    } catch {}
  };
  const themeToggle = document.querySelector('#dark-mode-toggle');
  if (themeToggle) {
    themeToggle.checked = document.documentElement.dataset.theme === 'dark';
    themeToggle.addEventListener('change', () => {
      const theme = themeToggle.checked ? 'dark' : 'light';
      document.documentElement.dataset.theme = theme;
      savePreference('theme', theme);
    });
  }
  const fontSizeSelect = document.querySelector('#font-size-select');
  if (fontSizeSelect) {
    fontSizeSelect.value = document.documentElement.dataset.fontSize || 'default';
    fontSizeSelect.addEventListener('change', () => {
      document.documentElement.dataset.fontSize = fontSizeSelect.value;
      savePreference('font-size', fontSizeSelect.value);
    });
  }
  const motionToggle = document.querySelector('#reduce-motion-toggle');
  if (motionToggle) {
    motionToggle.checked = document.documentElement.dataset.motion === 'off';
    motionToggle.addEventListener('change', () => {
      const motion = motionToggle.checked ? 'off' : 'on';
      document.documentElement.dataset.motion = motion;
      savePreference('motion', motion);
    });
  }

  const sidebar = document.querySelector('.sidebar');
  const sidebarToggle = document.querySelector('.sidebar-toggle');
  if (sidebar && sidebarToggle) {
    if (window.matchMedia('(max-width: 700px)').matches) document.body.classList.add('sidebar-hidden');
    const syncSidebarToggle = () => {
      const hidden = document.body.classList.contains('sidebar-hidden');
      sidebar.classList.toggle('is-hidden', hidden);
      sidebarToggle.classList.toggle('is-docked', hidden);
      sidebarToggle.setAttribute('aria-label', hidden ? 'Show navigation' : 'Hide navigation');
      sidebarToggle.title = hidden ? 'Show navigation' : 'Hide navigation';
      sidebarToggle.setAttribute('aria-expanded', String(!hidden));
    };
    syncSidebarToggle();
    sidebarToggle.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-hidden');
      syncSidebarToggle();
    });
  }

  const profileMenu = document.querySelector('.profile-menu');
  document.addEventListener('click', event => {
    if (profileMenu?.open && !profileMenu.contains(event.target)) profileMenu.open = false;
  });
  document.querySelector('.mobile-more-close')?.addEventListener('click', () => {
    const moreMenu = document.querySelector('.tablet-more');
    if (moreMenu) moreMenu.open = false;
  });
  document.querySelector('.mobile-more-backdrop')?.addEventListener('click', () => {
    const moreMenu = document.querySelector('.tablet-more');
    if (moreMenu) moreMenu.open = false;
  });
  document.addEventListener('click', event => {
    const moreMenu = document.querySelector('.tablet-more');
    if (moreMenu?.open && !moreMenu.contains(event.target)) moreMenu.open = false;
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      const moreMenu = document.querySelector('.tablet-more');
      if (moreMenu) moreMenu.open = false;
    }
  });

  const cards = document.querySelectorAll('.reading-card[data-sensor]');
  if (!cards.length) return;

  const base = document.body.dataset.appBase || '/EnviromentManagement/';
  const selectedDeviceId = document.querySelector('#summary-device')?.value || '';
  const endpoint = base + 'api/summary.php' + (selectedDeviceId ? '?device_id=' + encodeURIComponent(selectedDeviceId) : '');
  const cacheKey = 'enviro-summary-' + (document.body.dataset.cacheScope || '0') + '-' + selectedDeviceId;
  const cacheMaxAge = 60 * 60 * 1000;
  const tones = new Set(['ok', 'warning', 'alert', 'neutral']);
  const validSummary = data => data && data.demo === false && typeof data.waiting === 'boolean' && typeof data.deviceEnabled === 'boolean' && Number.isInteger(data.deviceId) && typeof data.room === 'string' &&
    typeof data.updatedAt === 'string' && typeof data.online === 'boolean' &&
    Number.isFinite(data.attentionCount) && typeof data.message === 'string' &&
    (typeof data.uptime === 'string' || data.uptime === null) && Number.isFinite(data.alertCount) &&
    ['temperature', 'humidity', 'air', 'light'].every(key => {
      const reading = data.readings?.[key];
      return reading && (typeof reading.value === 'string' || typeof reading.value === 'number' || reading.value === null) &&
        typeof reading.unit === 'string' && typeof reading.status === 'string' && tones.has(reading.tone);
    });
  const readCache = () => {
    try {
      const entry = JSON.parse(localStorage.getItem(cacheKey));
      if (entry && Number.isFinite(entry.savedAt) && entry.savedAt <= Date.now() && Date.now() - entry.savedAt <= cacheMaxAge && validSummary(entry.data)) return entry;
      localStorage.removeItem(cacheKey);
    } catch {}
    return null;
  };
  const saveCache = data => {
    try { localStorage.setItem(cacheKey, JSON.stringify({ savedAt: Date.now(), data })); } catch {}
  };
  const cached = readCache();
  let lastData = cached?.data || null;
  const cacheNote = document.querySelector('#cache-note');
  const main = document.querySelector('.main-content');

  function renderSummary(data) {
    document.querySelector('#page-subtitle').textContent = data.room + ' \u00b7 ESP32 \u00b7 Updated ' + data.updatedAt;
    document.querySelector('#connection-dot').classList.toggle('online', data.online);
    document.querySelector('#overall-message').textContent = data.message;
    const pill = document.querySelector('#overall-pill');
    pill.textContent = !data.deviceEnabled ? 'Device disabled' : (data.waiting ? 'Waiting for data' : (data.attentionCount === 1 ? '1 reading needs attention' : data.attentionCount + ' readings need attention'));
    pill.className = 'status-pill ' + (!data.deviceEnabled || data.waiting ? 'neutral' : (data.attentionCount ? 'warning' : 'ok'));

    for (const [key, reading] of Object.entries(data.readings)) {
      const card = document.querySelector('[data-sensor="' + key + '"]');
      if (!card) continue;
      card.querySelector('[data-value]').textContent = reading.value ?? '\u2014';
      card.querySelector('[data-unit]').textContent = reading.unit;
      const status = card.querySelector('[data-status]');
      status.textContent = reading.status;
      status.className = 'status-pill ' + reading.tone;
    }
    const health = document.querySelector('#last-data');
    if (health) health.textContent = data.updatedAt;
    const coverage = document.querySelector('#coverage-value');
    if (coverage) coverage.textContent = data.uptime ?? '\u2014';
    const alertCount = document.querySelector('#open-alert-count');
    if (alertCount) alertCount.textContent = data.alertCount;
  }

  if (lastData) renderSummary(lastData);
  else {
    document.body.classList.add('summary-loading');
    main?.setAttribute('aria-busy', 'true');
  }

  async function refresh() {
    try {
      const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok) throw new Error('Summary request failed');
      const data = await response.json();
      if (data.demo === true) {
        try { localStorage.removeItem(cacheKey); } catch {}
        if (lastData) window.location.reload();
        return;
      }
      if (!validSummary(data)) throw new Error('Invalid summary response');
      renderSummary(data);
      lastData = data;
      saveCache(data);
      if (cacheNote) cacheNote.hidden = true;
    } catch {
      document.querySelector('#connection-dot')?.classList.remove('online');
      if (lastData && cacheNote) {
        cacheNote.textContent = 'Live refresh unavailable. Showing saved readings.';
        cacheNote.hidden = false;
      }
    } finally {
      document.body.classList.remove('summary-loading');
      main?.setAttribute('aria-busy', 'false');
    }
  }

  refresh();
  window.setInterval(refresh, 10000);
})();

