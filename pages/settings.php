<?php
require_once __DIR__ . '/../assets/php/layout.php';
layout_start('Settings', 'settings');
?>
<section class="content-card">
  <h2>Appearance</h2>
  <label class="setting-row" for="dark-mode-toggle">
    <span><strong>Dark mode</strong><small>Use a darker color theme on this device.</small></span>
    <input id="dark-mode-toggle" type="checkbox" role="switch">
  </label>
  <label class="setting-row" for="font-size-select">
    <span><strong>Text size</strong><small>Choose a comfortable reading size.</small></span>
    <select id="font-size-select">
      <option value="small">Small</option>
      <option value="default">Default</option>
      <option value="large">Large</option>
    </select>
  </label>
  <label class="setting-row" for="reduce-motion-toggle">
    <span><strong>Reduce animations</strong><small>Stop interface animations and transitions.</small></span>
    <input id="reduce-motion-toggle" type="checkbox" role="switch">
  </label>
</section>
<?php layout_end(); ?>
