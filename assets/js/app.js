(() => {
  window.lucide?.createIcons();
  const themeToggle = document.querySelector('#dark-mode-toggle');
  if (themeToggle) {
    themeToggle.checked = document.documentElement.dataset.theme === 'dark';
    themeToggle.addEventListener('change', () => {
      const theme = themeToggle.checked ? 'dark' : 'light';
      document.documentElement.dataset.theme = theme;
      try { localStorage.setItem('enviro-theme', theme); } catch {}
    });
  }
  const fontSizeSelect = document.querySelector('#font-size-select');
  if (fontSizeSelect) {
    fontSizeSelect.value = document.documentElement.dataset.fontSize || 'default';
    fontSizeSelect.addEventListener('change', () => {
      document.documentElement.dataset.fontSize = fontSizeSelect.value;
      try { localStorage.setItem('enviro-font-size', fontSizeSelect.value); } catch {}
    });
  }
  const motionToggle = document.querySelector('#reduce-motion-toggle');
  if (motionToggle) {
    motionToggle.checked = document.documentElement.dataset.motion === 'off';
    motionToggle.addEventListener('change', () => {
      const motion = motionToggle.checked ? 'off' : 'on';
      document.documentElement.dataset.motion = motion;
      try { localStorage.setItem('enviro-motion', motion); } catch {}
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

  const cards = document.querySelectorAll('.reading-card[data-sensor]');
  if (!cards.length) return;

  const base = document.body.dataset.appBase || '/EnviromentManagement/';
  const endpoint = base + 'api/summary.php';

  async function refresh() {
    try {
      const response = await fetch(endpoint, { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok) return;
      const data = await response.json();
      if (data.demo) return;

      document.querySelector('#page-subtitle').textContent =
        `${data.room} · ESP32 · Updated ${data.updatedAt}`;
      document.querySelector('#connection-dot').classList.toggle('online', data.online === true);
      document.querySelector('#overall-message').textContent = data.message;

      const pill = document.querySelector('#overall-pill');
      pill.textContent = data.attentionCount === 1 ? '1 reading needs attention' : `${data.attentionCount} readings need attention`;
      pill.className = `status-pill ${data.attentionCount ? 'warning' : 'ok'}`;

      for (const [key, reading] of Object.entries(data.readings || {})) {
        const card = document.querySelector(`[data-sensor="${key}"]`);
        if (!card) continue;
        card.querySelector('[data-value]').textContent = reading.value ?? '—';
        card.querySelector('[data-unit]').textContent = reading.unit ?? '';
        const status = card.querySelector('[data-status]');
        status.textContent = reading.status;
        status.className = `status-pill ${reading.tone || 'neutral'}`;
      }
      const health = document.querySelector('#last-data');
      if (health) health.textContent = data.updatedAt;
      const coverage = document.querySelector('#coverage-value');
      if (coverage) coverage.textContent = data.uptime ?? '—';
      const alertCount = document.querySelector('#open-alert-count');
      if (alertCount) alertCount.textContent = data.alertCount ?? 0;
    } catch {
      document.querySelector('#connection-dot')?.classList.remove('online');
    }
  }

  refresh();
  window.setInterval(refresh, 10000);
})();

