#include <WiFi.h>
#include <WebServer.h>
#include <HTTPClient.h>
#include <DHT.h>
#include <math.h>
#include <string.h>

// ===== Wi-Fi =====
const char* ssid = "OPPO F9";
const char* password = "123456789";

// ===== Sensor pins =====
#define DHTPIN 4
#define DHTTYPE DHT11

#define MQ135_AO 34
#define MQ135_DO 27
#define LDR_PIN 35

DHT dht(DHTPIN, DHTTYPE);
WebServer server(80);

// Website upload target and enrollment key.
const char* ingestUrl = "http://ipadress/EnviromentManagement/api/ingest.php";
const char* discoveryUrl = "http://ipadress/EnviromentManagement/api/discover.php";
const char* deviceKey = "website code"; // Replace with the key shown after registration.
const unsigned long uploadIntervalMs = 60000;
unsigned long lastUploadMs = 0;

// ===== Website =====
const char webpage[] PROGMEM = R"rawliteral(
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Smart Environment Monitor</title>
<style>
* { box-sizing: border-box; }
body {
  margin: 0;
  font-family: Arial, sans-serif;
  background: #f1f5f9;
  color: #172033;
}
header {
  background: linear-gradient(135deg, #075985, #2563eb);
  color: white;
  text-align: center;
  padding: 26px 14px;
}
header h1 { margin: 0 0 8px; font-size: 25px; }
header p { margin: 0; opacity: .9; }
main { max-width: 850px; margin: 22px auto; padding: 0 14px; }
.status {
  background: white;
  border-radius: 12px;
  padding: 14px;
  margin-bottom: 16px;
  text-align: center;
  box-shadow: 0 2px 8px #0001;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
}
.card {
  background: white;
  padding: 22px 18px;
  border-radius: 14px;
  box-shadow: 0 3px 10px #0001;
}
.icon { font-size: 27px; }
.label { color: #64748b; margin-top: 12px; }
.value {
  color: #075985;
  font-size: 30px;
  font-weight: bold;
  margin: 10px 0;
  overflow-wrap: anywhere;
}
.note { color: #64748b; font-size: 12px; line-height: 1.5; }
footer {
  text-align: center;
  padding: 22px 10px;
  color: #64748b;
  font-size: 12px;
}
</style>
</head>
<body>
<header>
  <h1>Smart Environment Monitor</h1>
  <p>ESP32 · DHT11 · MQ-135 · LDR</p>
</header>

<main>
  <div class="status">
    <strong>System status:</strong> <span id="status">Connecting...</span>
    <div class="note" id="updated">Waiting for sensor data</div>
  </div>

  <div class="grid">
    <div class="card">
      <div class="icon">🌡️</div>
      <div class="label">Temperature</div>
      <div class="value" id="temp">--</div>
      <div class="note">Degrees Celsius</div>
    </div>

    <div class="card">
      <div class="icon">💧</div>
      <div class="label">Humidity</div>
      <div class="value" id="humidity">--</div>
      <div class="note">Relative humidity</div>
    </div>

    <div class="card">
      <div class="icon">🌫️</div>
      <div class="label">MQ-135 Analog Reading</div>
      <div class="value" id="air">--</div>
      <div class="note">Raw ADC value, 0–4095; not AQI or ppm</div>
    </div>

    <div class="card">
      <div class="icon">⚠️</div>
      <div class="label">MQ-135 Digital Output</div>
      <div class="value" id="gas">--</div>
      <div class="note" id="gasNote">Depends on module comparator setting</div>
    </div>

    <div class="card">
      <div class="icon">💡</div>
      <div class="label">Light Level</div>
      <div class="value" id="light">--</div>
      <div class="note">Raw LDR ADC value, 0–4095</div>
    </div>
  </div>
</main>

<footer>
  ESP32 Live Dashboard · Updates every 2 seconds
</footer>

<script>
async function updateDashboard() {
  try {
    const response = await fetch('/data');
    if (!response.ok) throw new Error('Request failed');

    const d = await response.json();

    document.getElementById('temp').textContent =
      d.temperature === null ? 'Sensor error' : d.temperature + ' °C';

    document.getElementById('humidity').textContent =
      d.humidity === null ? 'Sensor error' : d.humidity + ' %';

    document.getElementById('air').textContent = d.air;
    document.getElementById('light').textContent = d.light;

    document.getElementById('gas').textContent =
      d.gasDigital === 0 ? 'LOW' : 'HIGH';

    document.getElementById('gasNote').textContent =
      d.gasDigital === 0
      ? 'Digital output is LOW'
      : 'Digital output is HIGH';

    document.getElementById('status').textContent = 'Online';
    document.getElementById('updated').textContent =
      'Last updated: ' + new Date().toLocaleTimeString();
  } catch (error) {
    document.getElementById('status').textContent = 'Connection lost';
  }
}

updateDashboard();
setInterval(updateDashboard, 2500);
</script>
</body>
</html>
)rawliteral";

String readSensorJson(bool logDht = false) {
  float temperature = dht.readTemperature();
  float humidity = dht.readHumidity();
  if (logDht) {
    if (isnan(temperature) || isnan(humidity)) Serial.println("DHT11 read failed: check DATA on GPIO 4, 3V3, GND, and sensor type.");
    else Serial.printf("DHT11: %.1f C, %.1f%% humidity\n", temperature, humidity);
  }

  int air = analogRead(MQ135_AO);
  int gasDigital = digitalRead(MQ135_DO);
  int light = analogRead(LDR_PIN);

  String json = "{";

  json += "\"temperature\":";
  if (isnan(temperature)) {
    json += "null";
  } else {
    json += String(temperature, 1);
  }

  json += ",\"humidity\":";
  if (isnan(humidity)) {
    json += "null";
  } else {
    json += String(humidity, 1);
  }

  json += ",\"air\":" + String(air);
  json += ",\"gasDigital\":" + String(gasDigital);
  json += ",\"light\":" + String(light);
  json += "}";

  return json;
}

// ===== Website handlers =====
void handleRoot() {
  server.send_P(200, "text/html", webpage);
}

void handleData() {
  server.send(200, "application/json", readSensorJson());
}

void uploadReading() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Website upload skipped: Wi-Fi is disconnected.");
    return;
  }

  WiFiClient client;
  HTTPClient http;
  http.setTimeout(5000);
  const bool unregistered = strcmp(deviceKey, "UNREGISTERED") == 0;
  const char* targetUrl = unregistered ? discoveryUrl : ingestUrl;
  if (!http.begin(client, targetUrl)) {
    Serial.println("Website request failed: could not open the server URL.");
    return;
  }

  http.addHeader("Content-Type", "application/json");
  String payload;
  if (unregistered) {
    String mac = WiFi.macAddress();
    String compactMac = mac;
    compactMac.replace(":", "");
    payload = "{\"macAddress\":\"" + mac + "\",\"deviceName\":\"ESP32-" + compactMac.substring(compactMac.length() - 6) + "\"}";
  } else {
    http.addHeader("X-Device-Key", deviceKey);
    payload = readSensorJson(true);
  }
  int status = http.POST(payload);
  Serial.printf("Website upload HTTP %d\n", status);
  if (status > 0) {
    Serial.print("Server response: ");
    Serial.println(http.getString());
  } else {
    Serial.printf("Upload connection error: %s\n", http.errorToString(status).c_str());
  }
  http.end();
}

// ===== Setup =====
void setup() {
  Serial.begin(115200);

  pinMode(MQ135_DO, INPUT);
  analogReadResolution(12);

  dht.begin();

  WiFi.mode(WIFI_STA);
  WiFi.begin(ssid, password);

  Serial.print("Connecting to Wi-Fi");

  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
  }

  Serial.println();
  Serial.println("Wi-Fi connected!");
  Serial.print("Dashboard URL: http://");
  Serial.println(WiFi.localIP());

  server.on("/", HTTP_GET, handleRoot);
  server.on("/data", HTTP_GET, handleData);
  server.begin();

  Serial.println("Web server started!");
  Serial.print("Website endpoint: ");
  Serial.println(strcmp(deviceKey, "UNREGISTERED") == 0 ? discoveryUrl : ingestUrl);
  Serial.println(strcmp(deviceKey, "UNREGISTERED") == 0
    ? "Unregistered: sending device identity for admin approval."
    : "Registered: sending sensor readings with the device key.");
  uploadReading();
  lastUploadMs = millis();
}

void loop() {
  server.handleClient();
  if (millis() - lastUploadMs >= uploadIntervalMs) {
    lastUploadMs = millis();
    uploadReading();
  }
}
