# Classroom Environment Monitor

A PHP and MySQL web application for monitoring classroom environmental conditions. It provides separate faculty and administrator views, sensor history and reports, alert management, and an API for ESP32 devices.

> The web application and ESP32 firmware are separate projects. This repository contains the website and its API; the Arduino sketch is maintained separately.

## Features

- Faculty dashboard for temperature, humidity, air quality, and light readings
- Reading history, reports, device comparison, and alerts
- Administrator tools for users, devices, thresholds, notifications, data storage, and activity logs
- Device enrollment and authenticated sensor data ingestion
- Responsive layouts for desktop, laptop, tablet, and mobile screens
- Account settings, including display preferences

## Requirements

- XAMPP with Apache, MySQL, and PHP with PDO MySQL enabled
- Arduino IDE and an ESP32 board, if connecting physical sensors
- Adafruit DHT Sensor Library and Adafruit Unified Sensor Library, if using the separately maintained firmware

## Local installation with XAMPP

1. Clone or copy this repository into XAMPP's `htdocs` directory. Keep the folder name `EnviromentManagement`, since the application currently uses that name in its base URL.

   ```text
   C:\xampp\htdocs\EnviromentManagement
   ```

2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open [phpMyAdmin](http://localhost/phpmyadmin), choose **Import**, and import `assets/sql/environment_management.sql`.
4. Open [http://localhost/EnviromentManagement/login.php](http://localhost/EnviromentManagement/login.php). Select **Admin** and sign in with account ID `ADMIN-001`. Obtain the initial password from the project owner.
5. Open [http://localhost/EnviromentManagement/](http://localhost/EnviromentManagement/) to use the application. Create and manage accounts under **Admin → Users**.

The default database configuration is:

| Setting | Default |
| --- | --- |
| Host | `127.0.0.1` |
| Database | `environment_management` |
| Username | `root` |
| Password | *(empty)* |

For a different MySQL setup, configure these environment variables for Apache:

```text
ENV_DB_HOST
ENV_DB_NAME
ENV_DB_USER
ENV_DB_PASS
```

The application reads them in `assets/php/bootstrap.php`. New user passwords must be at least 12 characters.

## Connect an ESP32

The separately maintained Arduino sketch provides a local `GET /data` dashboard and can send readings to this website. Before registration, it sends only the device MAC address to the discovery endpoint; readings are accepted only after an administrator registers the device and the sketch is configured with its device key.

1. In the Arduino sketch, set the website computer's current LAN IP address in `ingestUrl` and `discoveryUrl`, and configure Wi-Fi credentials.
2. Connect the XAMPP computer and ESP32 to the same Wi-Fi network. Allow inbound Apache traffic through the computer's firewall (usually TCP port 80).
3. Upload the sketch to the ESP32 and open Serial Monitor at 115200 baud. With `deviceKey` set to `UNREGISTERED`, the device should appear in **Admin → Devices** as awaiting registration.
4. Register the device with a name and location. Copy its one-time key into `deviceKey` in the sketch, then upload the sketch again. Keep the key private and use a separate key for each device.

The API expects JSON like this:

```json
{
  "temperature": 27.4,
  "humidity": 58,
  "air": 142,
  "gasDigital": 0,
  "light": 320
}
```

Send readings to `api/ingest.php` with the device key in the `X-Device-Key` header. Temperature, when supplied, must be 0–50 °C; humidity must be 0–100%; `air` and `light` must be integer ADC values from 0 to 4095; and `gasDigital` must be integer `0` or `1`. The default minimum interval between readings is 60 seconds. MQ-135 and LDR values are raw ADC readings and require hardware calibration before they can be interpreted as ppm/AQI or lux.

Common ingestion responses:

| Status | Meaning |
| --- | --- |
| `202` | Device discovery received; register the device in the admin panel |
| `401` | Missing or invalid device key |
| `422` | Invalid sensor values |
| `429` | Reading arrived before the device's minimum interval elapsed |
| `503` | The website could not reach the database |

The website and ESP32 must both be running for new readings to appear. The computer's LAN IP can change when it reconnects to Wi-Fi.

## Database files

- `assets/sql/environment_management.sql` creates or updates the application schema and seeds the initial administrator account. It does not add sample sensor readings. For an existing installation, re-import it to apply additive schema updates; it uses `CREATE TABLE IF NOT EXISTS` and does not drop existing tables.
- `assets/sql/environment_management_backup.sql` is a full XAMPP database dump. It contains account data and `DROP TABLE` statements. Keep it private and use it only to restore the matching project database, after backing up any data that must be kept.

## Project structure

```text
api/                 ESP32 discovery, ingestion, and dashboard summary endpoints
assets/css/          Application styles
assets/images/       Interface illustrations and icons
assets/js/           Client-side application and login behavior
assets/php/          Shared database, session, and layout code
assets/sql/          Database setup script and backup dump
pages/               Faculty and administrator pages
responsiveness/      Desktop, laptop, tablet, and mobile stylesheets
```

## Notes

- The application base URL is currently `/EnviromentManagement/`. If you use another folder name, update `APP_BASE` in `assets/php/bootstrap.php`.
- Faculty can request password resets from the Faculty option on the login page; an administrator handles requests in **Admin → Notifications** or **Admin → Users**.
- The dashboard has no live sensor readings until a registered ESP32 sends them.
- Lucide icons are loaded from a CDN and require internet access in the browser.
- Do not expose the seeded administrator account or a development XAMPP installation directly to the public internet.
