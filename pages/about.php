<?php
require_once __DIR__ . '/../assets/php/layout.php';
layout_start('About', 'about');
?>
<section class="content-card"><h2>Classroom Environment Monitor</h2><p>This system receives classroom temperature, humidity, air quality, and light readings from an ESP32 and presents current conditions, history, and alerts.</p><p>Readings are stored in the local MySQL database. Sensor calibration and thresholds are managed by an administrator.</p></section>
<?php layout_end(); ?>
