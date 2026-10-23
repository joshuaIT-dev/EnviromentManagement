<?php
require_once __DIR__ . '/../assets/php/layout.php';
require_admin();
$pdo=null;$message='';$devices=[];$keyOnce=$_SESSION['new_device_key']??null;unset($_SESSION['new_device_key']);
try{$pdo=db();if($_SERVER['REQUEST_METHOD']==='POST'){verify_csrf();$name=trim((string)($_POST['name']??''));$location=trim((string)($_POST['location']??''));if($name===''||$location==='')$message='Enter a device name and location.';else{$key=bin2hex(random_bytes(24));$stmt=$pdo->prepare('INSERT INTO devices (name,location,api_key_prefix,api_key_hash) VALUES (?,?,?,?)');$stmt->execute([$name,$location,substr($key,0,8),hash('sha256',$key)]);log_activity($pdo,(int)$_SESSION['user_id'],'device_created',$name);$_SESSION['new_device_key']=$key;header('Location: '.app_url('pages/admin_devices.php'));exit;}}$devices=$pdo->query('SELECT * FROM devices ORDER BY id DESC')->fetchAll();}catch(Throwable $exception){$message=database_error_message();}
layout_start('Manage devices','admin');
?>
<?php if($message): ?><p class="inline-notice"><?= e($message) ?></p><?php endif; ?>
<?php if($keyOnce): ?><section class="content-card key-once"><h2>Copy this device key now</h2><p>It is shown once. Store it in the ESP32 firmware as a secret and send it in the <code>X-Device-Key</code> header.</p><code><?= e($keyOnce) ?></code></section><?php endif; ?>
<section class="content-card"><h2>Register a device</h2><form class="filter-form" method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Device name<input name="name" maxlength="120" required placeholder="ESP32-301"></label><label>Location<input name="location" maxlength="160" required placeholder="Room 301"></label><button class="button-primary small" type="submit">Register device</button></form></section>
<section class="content-card"><h2>Devices</h2><?php if(!$devices): ?><p class="empty-state">No devices registered.</p><?php else: ?><div class="table-scroll"><table><thead><tr><th>Name</th><th>Location</th><th>Sampling interval</th><th>Last seen</th><th>Key</th></tr></thead><tbody><?php foreach($devices as $d): ?><tr><td><?= e($d['name']) ?></td><td><?= e($d['location']) ?></td><td><?= (int)$d['sample_interval_seconds'] ?> sec</td><td><?= e(time_ago($d['last_seen_at'])) ?></td><td><?= $d['api_key_hash']?'Set':'Missing' ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?php layout_end(); ?>

