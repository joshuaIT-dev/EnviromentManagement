# Classroom Environment Monitor

PHP and MySQL website for the ESP32 classroom monitoring project. Place this folder under XAMPP's `htdocs` as `EnviromentManagement`.

## First run

1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin and import `assets/sql/environment_management.sql`. It creates the project tables and the initial `ADMIN-001` account; it does not include sample sensor records.
3. Open `http://localhost/EnviromentManagement/login.php` and sign in with the admin credentials provided for this local database.
4. In Admin → Users, create faculty and additional administrator accounts. Each account needs its own Faculty/Admin ID.
5. In the admin panel, register the ESP32. Copy its one-time device key into the firmware configuration.
6. Point the ESP32 upload request to `/EnviromentManagement/api/ingest.php` and send JSON with the `X-Device-Key` header.

Faculty can request a password reset from the Faculty option on the sign-in page. Pending requests appear in Admin → Notifications and Admin → Users; an administrator sets a temporary password there. Account creation stays admin-only. Sidebar icons use Lucide from its CDN, so those icons require an internet connection.

## Current ESP32 payload

The provided sketch currently serves `GET /data`; it does not upload readings to XAMPP. The ingestion endpoint accepts:

```json
{"temperature":27.4,"humidity":58,"air":142,"gasDigital":0,"light":320}
```

Temperature and humidity use the configured ranges. MQ-135 and LDR values remain labeled as raw ADC until calibration. The schema stores an initial administrator password hash; it does not store plaintext passwords or default device keys.

## Configuration

XAMPP defaults are used by `assets/php/bootstrap.php`: database `environment_management`, user `root`, empty password. Set `ENV_DB_HOST`, `ENV_DB_NAME`, `ENV_DB_USER`, and `ENV_DB_PASS` in Apache's environment if your local setup differs.

The schema import only creates or updates the `environment_management` database; it does not touch other XAMPP databases.

`assets/sql/environment_management_backup.sql` is a full dump of the current XAMPP database, including the administrator account record and its password hash. Keep it private. It contains `DROP TABLE` statements, so import it only when restoring the project database; importing it over a database will replace its tables.

