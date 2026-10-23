# Classroom Environment Monitor

PHP and MySQL web app for monitoring classroom sensors. The website and ESP32 are separate parts: the website stores and displays readings; the ESP32 firmware must send readings to the website's API.

## Install the website with XAMPP

1. Download or clone this repository into XAMPP's `htdocs` folder. Keep the folder name exactly `EnviromentManagement` because the app's base URL uses that name.
   - Expected Windows folder: `C:\xampp\htdocs\EnviromentManagement`
2. In the XAMPP Control Panel, start **Apache** and **MySQL**.
3. Open `http://localhost/phpmyadmin`, select **Import**, and import `assets/sql/environment_management.sql` from this folder. For a new installation, import this file once. Do not import `environment_management_backup.sql` for a fresh install; it is a restore dump and contains `DROP TABLE` statements.
4. Open `http://localhost/EnviromentManagement/login.php`. Select **Admin** and sign in with account ID `ADMIN-001`. Get the initial password from the project owner. Keep this setup on a trusted local network; do not expose the seeded admin account to the public internet.
5. Visit `http://localhost/EnviromentManagement/` to use the app. Create faculty/admin accounts in **Admin → Users**. New accounts need a password of at least 12 characters.

The default XAMPP database settings are database `environment_management`, user `root`, and an empty password. If MySQL credentials differ, set `ENV_DB_HOST`, `ENV_DB_NAME`, `ENV_DB_USER`, and `ENV_DB_PASS` in Apache's environment, as read by `assets/php/bootstrap.php`.

## Connect an ESP32

The current Arduino sketch serves its own `GET /data` page; it does not yet upload readings to this app. Add an HTTP `POST` request to the firmware:

1. In the app, sign in as admin and open **Admin → Devices**. Register a device with a name and location.
2. Copy the device key shown after registration. It is displayed once. Store it privately in the firmware; do not commit the key to GitHub or share it publicly. Register a separate device/key for each ESP32.
3. Make sure the XAMPP computer and ESP32 are on the same network. Find the computer's LAN IPv4 address (for example `192.168.1.20`) and use it instead of `localhost` in the ESP32 URL. The computer's firewall must allow inbound Apache traffic (usually TCP port 80).
4. Send an HTTP `POST` to `http://<COMPUTER-LAN-IP>/EnviromentManagement/api/ingest.php` with `Content-Type: application/json` and an `X-Device-Key: <DEVICE-KEY>` header.
5. Send readings in this format:

```json
{"temperature":27.4,"humidity":58,"air":142,"gasDigital":0,"light":320}
```

`temperature` must be 0–50 °C when supplied; `humidity` must be 0–100% when supplied; `air` and `light` must be integer ADC values from 0 to 4095; `gasDigital` must be integer `0` or `1`. Send at least once every 60 seconds by default; faster repeats can receive HTTP `429` (interval not reached). MQ-135 and LDR readings are raw ADC values and need calibration before treating them as ppm/AQI or lux.

The ESP32 and website must both be running for new readings to appear. The Arduino firmware must use the computer's current LAN IP; it can change when the computer reconnects to Wi-Fi. For a response other than success, check the HTTP status: `401` means the key is missing/invalid, `422` means sensor values are invalid, `429` means the request arrived too soon, and `503` usually means the website cannot reach MySQL.

## Other app behavior

- Faculty can request a password reset from the Faculty option on the login page. An admin handles requests under **Admin → Notifications** or **Admin → Users**.
- The sensor dashboard remains empty until an ESP32 posts valid readings.
- Sidebar icons load Lucide from its CDN and need internet access.

## Database files

- `assets/sql/environment_management.sql` creates or updates the app schema and seeds the initial admin account. It does not add sample sensor readings.
- `assets/sql/environment_management_backup.sql` is a full XAMPP database dump. It contains account data and `DROP TABLE` statements; keep it private and use it only when restoring the matching project database, after backing up any data that must be kept.

