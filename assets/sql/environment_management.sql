CREATE DATABASE IF NOT EXISTS environment_management
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE environment_management;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  account_id VARCHAR(40) NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('faculty','admin') NOT NULL DEFAULT 'faculty',
  is_enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_account_id (account_id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','resolved') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  handled_at DATETIME NULL,
  handled_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_reset_requests_status (status, created_at),
  CONSTRAINT fk_reset_request_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_reset_request_admin FOREIGN KEY (handled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS devices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  location VARCHAR(160) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  api_key_prefix CHAR(8) NULL,
  api_key_hash CHAR(64) NULL,
  sample_interval_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  offline_timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
  alert_cooldown_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  temp_min DECIMAL(5,2) NOT NULL DEFAULT 18.00,
  temp_max DECIMAL(5,2) NOT NULL DEFAULT 30.00,
  humidity_min DECIMAL(5,2) NOT NULL DEFAULT 30.00,
  humidity_max DECIMAL(5,2) NOT NULL DEFAULT 70.00,
  air_warning_adc SMALLINT UNSIGNED NULL,
  air_alert_adc SMALLINT UNSIGNED NULL,
  light_on_adc SMALLINT UNSIGNED NULL,
  light_night_start TIME NOT NULL DEFAULT '21:00:00',
  light_night_end TIME NOT NULL DEFAULT '05:00:00',
  light_min_duration_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  last_seen_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_device_key_prefix (api_key_prefix),
  KEY ix_devices_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_enrollment_requests (
  mac_address CHAR(17) NOT NULL,
  device_label VARCHAR(100) NOT NULL,
  client_ip VARCHAR(45) NOT NULL,
  status ENUM('pending','registered') NOT NULL DEFAULT 'pending',
  first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  registered_device_id BIGINT UNSIGNED NULL,
  PRIMARY KEY (mac_address),
  KEY ix_enrollment_pending (status, last_seen_at),
  CONSTRAINT fk_enrollment_device FOREIGN KEY (registered_device_id) REFERENCES devices(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_display_settings (
  device_id BIGINT UNSIGNED NOT NULL,
  show_temperature TINYINT(1) NOT NULL DEFAULT 1,
  show_humidity TINYINT(1) NOT NULL DEFAULT 1,
  show_air TINYINT(1) NOT NULL DEFAULT 1,
  show_light TINYINT(1) NOT NULL DEFAULT 1,
  seconds_per_screen TINYINT UNSIGNED NOT NULL DEFAULT 5,
  PRIMARY KEY (device_id),
  CONSTRAINT fk_display_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS device_alert_policies (
  device_id BIGINT UNSIGNED NOT NULL,
  readings_required TINYINT UNSIGNED NOT NULL DEFAULT 2,
  window_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  PRIMARY KEY (device_id),
  CONSTRAINT fk_alert_policy_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_notification_settings (
  id TINYINT UNSIGNED NOT NULL,
  email_enabled TINYINT(1) NOT NULL DEFAULT 0,
  recipient_emails VARCHAR(1000) NOT NULL DEFAULT '',
  warning_enabled TINYINT(1) NOT NULL DEFAULT 1,
  alert_enabled TINYINT(1) NOT NULL DEFAULT 1,
  device_offline_enabled TINYINT(1) NOT NULL DEFAULT 1,
  light_at_night_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_notification_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO admin_notification_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS data_retention_settings (
  id TINYINT UNSIGNED NOT NULL,
  raw_readings_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_retention_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO data_retention_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS readings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id BIGINT UNSIGNED NOT NULL,
  temperature_c DECIMAL(5,2) NULL,
  humidity_pct DECIMAL(5,2) NULL,
  air_adc SMALLINT UNSIGNED NOT NULL,
  gas_digital TINYINT(1) NOT NULL,
  light_adc SMALLINT UNSIGNED NOT NULL,
  measured_at DATETIME NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reading_time (device_id, measured_at),
  KEY ix_readings_device_time (device_id, measured_at),
  CONSTRAINT fk_readings_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alerts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  device_id BIGINT UNSIGNED NOT NULL,
  sensor ENUM('temperature','humidity','air','light','device') NOT NULL,
  severity ENUM('warning','alert') NOT NULL DEFAULT 'warning',
  message VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  acknowledged_at DATETIME NULL,
  acknowledged_by BIGINT UNSIGNED NULL,
  closed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY ix_alerts_device_open (device_id, closed_at, acknowledged_at, created_at),
  CONSTRAINT fk_alerts_device FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
  CONSTRAINT fk_alerts_user FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  details VARCHAR(1000) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_created (created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO users (account_id, name, email, password_hash, role)
VALUES ('ADMIN-001', 'Administrator', 'admin@environment.local', '$2y$10$L6wUKRft0PVS8gM.eoKCyeY/S6B0nmDR17CXSvsAWzdK04Wp3GewS', 'admin');

