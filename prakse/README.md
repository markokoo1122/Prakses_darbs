# 64×64 RGB LED Matricas Vadības Sistēma

Tīmekļa lietojumprogramma ESP32 mikrokontroliera vadītai 64×64 RGB LED matricai ar pikseļu zīmēšanas redaktoru, galeriju un attēlu/GIF konvertētāju.

---

## Uzstādīšanas instrukcija (latviski)

### 1. Vides sagatavošana

Veikt šādus soļus **noteiktā secībā** pirms jebkuras citas darbības:

1. **XAMPP instalēšana**
   - Lejupielādēt un instalēt XAMPP no [https://www.apachefriends.org](https://www.apachefriends.org).
   - XAMPP ietver Apache tīmekļa serveri, MySQL datu bāzi, PHP un phpMyAdmin.

2. **Arduino IDE instalēšana**
   - Lejupielādēt un instalēt Arduino IDE no [https://www.arduino.cc/en/software](https://www.arduino.cc/en/software).

3. **ESP32 plates atbalsta pievienošana Arduino IDE**
   - Atvērt `File → Preferences`, laukā *Additional boards manager URLs* ievietot:
     ```
     https://raw.githubusercontent.com/espressif/arduino-esp32/gh-pages/package_esp32_index.json
     ```
   - Atvērt `Tools → Board → Boards Manager`, meklēt **esp32** (Espressif Systems) un nospiest **Install**.

4. **Arduino bibliotēku instalēšana** (`Tools → Manage Libraries`, `Ctrl+Shift+I`)
   - **ESP32-HUB75-MatrixPanel-I2S-DMA** (autors: mrcodetastic) — LED paneļa vadībai.
   - **ArduinoJson** (autors: Benoit Blanchon) — JSON apstrādei mikrokontrolierī.

5. **Python 3 instalēšana** (attēlu/GIF konvertēšanai)
   - Lejupielādēt no [https://www.python.org](https://www.python.org).
   - Pēc instalēšanas terminālī palaist:
     ```
     pip install Pillow numpy
     ```

6. **Projekta failu izvietošana**
   - Lejupielādēt projekta arhīvu un izpakot.
   - Mapi `prakse/` pārvietot uz `C:\xampp\htdocs\` (Windows) vai `/opt/lampp/htdocs/` (Linux).
   - Failu `homer.ino` atvērt ar Arduino IDE.

---

### 2. Programmaparatūras augšupielāde uz ESP32

> **Priekšnosacījums:** visi 1. punkta soļi ir pabeigti, īpaši bibliotēku instalēšana.

1. Savienot ESP32 ar datoru ar USB kabeli.
   - Ja nepieciešams, instalēt CH340 vai CP210x USB-UART draiverus (no plates ražotāja vietnes).
2. Atvērt failu `homer.ino` Arduino IDE.
3. Faila sākumā aizpildīt Wi-Fi iestatījumus:
   ```cpp
   const char* ssid = "TAVS_WIFI_NOSAUKUMS";
   const char* password = "TAVS_WIFI_PAROLE";
   ```
4. Izvēlēties plati: `Tools → Board → ESP32 Arduino → ESP32 Dev Module`.
5. Izvēlēties COM portu: `Tools → Port → COMx`.
6. Nospiest **Upload** (augšupielāde ilgst ~30–60 s; beigās statusa joslā: *Done uploading.*).
7. Atvērt `Tools → Serial Monitor`, iestatīt ātrumu **115200 baud**, restartēt plati (poga **RST**).
   - Serial Monitorā parādīsies piešķirtā IP adrese (piem., `192.168.1.45`) — **saglabāt to**.
   - Ja Wi-Fi savienojums neizdodas, ESP32 izveido piekļuves punktu **ESP32-LED-Matrix** ar IP `192.168.4.1`.

---

### 3. Tīmekļa servera palaišana

1. Atvērt **XAMPP Control Panel**.
2. Nospiest **Start** pie **Apache** (ports 80/443) un **MySQL** (ports 3306).
3. **Datu bāzes izveide** (tikai pirmo reizi):
   - Pārlūkā atvērt [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/).
   - Nospiest **New**, ievadīt nosaukumu `led_matrix_db`, nospiest **Create**.
   - Atvērt cilni **Import**, izvēlēties projekta failu `database.sql`, nospiest **Go**.
   - Tiks izveidotas tabulas: `users`, `designs`, `favorites`, `media_conversions`.
4. Pārlūkā atvērt [http://localhost/prakse/](http://localhost/prakse/).
5. Pirmajā reizē nospiest **Register**, izveidot kontu, tad pieteikties.

---

### 4. Savienojuma izveidošana ar LED matricu

1. Redaktora lapas augšējā kreisajā stūrī atrast lauku **Matrix IP**.
2. Ievadīt ESP32 IP adresi (no Serial Monitor) vai `192.168.4.1` (AP režīms).
3. Nospiest **Connect** — statusa indikators kļūst zaļš ar tekstu *Connected*.

> **Aparatūras prasības:** LED matricai nepieciešams atsevišķs 5 V strāvas avots ar jaudu 3–5 A.

---

### 5. Redaktora lietošana

| Elements | Apraksts |
|---|---|
| Zīmēšanas režģis | Klikšķis/vilkšana ar kreiso pogu uzliek krāsu; labā poga nodzēš pikseli |
| **Pencil** | Zīmuļa rīks |
| **Eraser** | Dzēšgumija |
| **Fill Bucket** | Aizpilda savienotu vienādas krāsas zonu (flood-fill) |
| **Brush 1×/2×/3×** | Otas izmērs: 1, 4 vai 9 pikseļi |
| Krāsu izvēles rīks | Atver krāsu dialogu precīzai RGB izvēlei |
| Krāsu vēsture | Pēdējās 8 izmantotās krāsas ātrajai izvēlei |
| **Grid Size** | Režģa izmērs no 8×8 līdz 64×64 pikseļiem |

---

### 6. Aparatūras savienojumu shēma

ESP32 HUB75 savienojums ar 64×64 LED paneli — sīkāk aprakstīts atskaites pielikumā.

---

## Failu struktūra

```
prakse/
├── index.php          # Galvenā lapa / redaktors
├── api.php            # PHP starpniekserveris (proxy uz ESP32)
├── db.php             # Datu bāzes savienojums
├── style.css          # Izskats
├── script.js          # Redaktora loģika un API izsaukumi
├── database.sql       # Datu bāzes shēma
├── uploads/           # Augšupielādēti attēli un GIF
├── image_to_led_converter.py  # Attēlu/GIF konvertētājs
└── homer/
    └── homer.ino      # ESP32 programmaparatūra (Arduino)
```

---

## Setup Instructions (English)

### 1. Prerequisites

1. Install **XAMPP** from [https://www.apachefriends.org](https://www.apachefriends.org).
2. Install **Arduino IDE** from [https://www.arduino.cc/en/software](https://www.arduino.cc/en/software).
3. Add ESP32 board support in Arduino IDE via Boards Manager (Espressif Systems).
4. Install Arduino libraries: **ESP32-HUB75-MatrixPanel-I2S-DMA** and **ArduinoJson**.
5. Install **Python 3** with `pip install Pillow numpy`.
6. Place the `prakse/` folder inside `C:\xampp\htdocs\`.

### 2. Firmware Upload

1. Open `homer.ino` in Arduino IDE.
2. Set your Wi-Fi `ssid` and `password` at the top of the file.
3. Select your board (`ESP32 Dev Module`) and COM port, then click **Upload**.
4. Open Serial Monitor at 115200 baud — note the IP address printed after boot.

### 3. Web App Setup

1. Start Apache and MySQL in XAMPP Control Panel.
2. In phpMyAdmin create database `led_matrix_db` and import `database.sql`.
3. Open [http://localhost/prakse/](http://localhost/prakse/), register an account and log in.
4. Enter the ESP32 IP in the **Matrix IP** field and click **Connect**.
