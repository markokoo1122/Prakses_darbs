#include <WiFi.h>
#include <WebServer.h>
#include <ESP32-HUB75-MatrixPanel-I2S-DMA.h>

const char* WIFI_SSID = "ZTE_124715";
const char* WIFI_PASSWORD = "6W5DEHF447";

#define PANEL_RES_X 64
#define PANEL_RES_Y 64
#define PANEL_CHAIN 1

#define R1_PIN 25
#define G1_PIN 26
#define B1_PIN 27
#define R2_PIN 14
#define G2_PIN 12
#define B2_PIN 13
#define A_PIN 23
#define B_PIN 19
#define C_PIN 5
#define D_PIN 18
#define E_PIN 32
#define LAT_PIN 4
#define OE_PIN 15
#define CLK_PIN 21

MatrixPanel_I2S_DMA* dma_display = nullptr;
WebServer server(80);

uint16_t* frameBuf = nullptr;
int frameW = PANEL_RES_X;
int frameH = PANEL_RES_Y;

static inline int b64v(char c) {
    if (c >= 'A' && c <= 'Z') return c - 'A';
    if (c >= 'a' && c <= 'z') return c - 'a' + 26;
    if (c >= '0' && c <= '9') return c - '0' + 52;
    if (c == '+') return 62;
    if (c == '/') return 63;
    if (c == '=') return -2;
    return -1;
}

bool decodeB64(const String& s, uint8_t*& out, int& outLen) {
    int len = s.length();
    if (len < 4) { out = nullptr; outLen = 0; return false; }
    int est = (len / 4) * 3;
    out = new uint8_t[est];
    int o = 0;
    int i = 0;
    while (i < len) {
        int a = -1, b = -1, c = -1, d = -1;
        while (i < len && a < 0) { a = b64v(s[i++]); if (a == -1) continue; }
        while (i < len && b < 0) { b = b64v(s[i++]); if (b == -1) continue; }
        while (i < len && c < 0) { c = b64v(s[i++]); if (c == -1) continue; }
        while (i < len && d < 0) { d = b64v(s[i++]); if (d == -1) continue; }
        if (a < 0 || b < 0) break;
        out[o++] = (uint8_t)((a << 2) | (b >> 4));
        if (c >= 0) {
            out[o++] = (uint8_t)(((b & 0x0F) << 4) | (c >> 2));
            if (d >= 0) {
                out[o++] = (uint8_t)(((c & 0x03) << 6) | d);
            }
        } else if (c == -2) {
            break;
        }
        if (d == -2) {
            break;
        }
    }
    outLen = o;
    return o > 0;
}

void drawTestPattern() {
    int maxW = PANEL_RES_X;
    int maxH = PANEL_RES_Y;
    for (int y = 0; y < maxH; y++) {
        for (int x = 0; x < maxW; x++) {
            uint8_t r = (x * 4) > 255 ? 255 : (x * 4);
            uint8_t g = (y * 4) > 255 ? 255 : (y * 4);
            uint8_t b = 0;
            uint16_t c = dma_display->color565(r, g, b);
            dma_display->drawPixel(x, y, c);
        }
    }
    dma_display->flipDMABuffer();
}

void setup() {
    Serial.begin(115200);

    HUB75_I2S_CFG mxconfig(PANEL_RES_X, PANEL_RES_Y, PANEL_CHAIN);
    mxconfig.double_buff = true;

    mxconfig.gpio.r1 = R1_PIN;
    mxconfig.gpio.g1 = G1_PIN;
    mxconfig.gpio.b1 = B1_PIN;
    mxconfig.gpio.r2 = R2_PIN;
    mxconfig.gpio.g2 = B2_PIN;
    mxconfig.gpio.b2 = G2_PIN;
    mxconfig.gpio.a = A_PIN;
    mxconfig.gpio.b = B_PIN;
    mxconfig.gpio.c = C_PIN;
    mxconfig.gpio.d = D_PIN;
    mxconfig.gpio.e = E_PIN;
    mxconfig.gpio.lat = LAT_PIN;
    mxconfig.gpio.oe = OE_PIN;
    mxconfig.gpio.clk = CLK_PIN;

    dma_display = new MatrixPanel_I2S_DMA(mxconfig);
    dma_display->begin();
    dma_display->setBrightness8(64);
    dma_display->clearScreen();

    Serial.println("WiFi start");
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    int retries = 0;
    while (WiFi.status() != WL_CONNECTED && retries < 40) {
        delay(500);
        retries++;
    }
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("WiFi STA failed, starting AP");
        WiFi.mode(WIFI_AP);
        delay(500);
        WiFi.softAP("ESP32-LED-Matrix", "12345678");
        delay(1000);
        Serial.print("AP IP: ");
        Serial.println(WiFi.softAPIP());
    } else {
        Serial.print("STA IP: ");
        Serial.println(WiFi.localIP());
    }

    drawTestPattern();

    server.on("/upload_bin", HTTP_POST, []() {
        String body = server.arg("plain");
        Serial.printf("/upload_bin: got %d bytes\n", body.length());
        int w = 0, h = 0;
        if (server.hasArg("width")) w = server.arg("width").toInt();
        if (server.hasArg("height")) h = server.arg("height").toInt();
        if (w <= 0) w = PANEL_RES_X;
        if (h <= 0) h = PANEL_RES_Y;
        frameW = w; frameH = h;
        if (frameBuf) { delete[] frameBuf; frameBuf = nullptr; }
        frameBuf = new uint16_t[w * h];
        int maxBytes = w*h*3;
        int toCopy = min((int)body.length(), maxBytes);
        const uint8_t* src = (const uint8_t*)body.c_str();
        Serial.printf("/upload_bin: dims=%dx%d, bytes=%d\n", w, h, toCopy);
        if (toCopy >= 3) {
            Serial.printf("/upload_bin: first=%d,%d,%d\n", src[0], src[1], src[2]);
        }
        for (int i=0;i<w*h;i++) {
            int idx = i*3;
            uint8_t r = (idx+0 < toCopy) ? src[idx+0] : 0;
            uint8_t g = (idx+1 < toCopy) ? src[idx+1] : 0;
            uint8_t b = (idx+2 < toCopy) ? src[idx+2] : 0;
            frameBuf[i] = dma_display->color565(r,g,b);
        }
        dma_display->clearScreen();
        int maxW = w < PANEL_RES_X ? w : PANEL_RES_X;
        int maxH = h < PANEL_RES_Y ? h : PANEL_RES_Y;
        for (int y = 0; y < maxH; y++) {
            for (int x = 0; x < maxW; x++) {
                dma_display->drawPixel(x, y, frameBuf[y * w + x]);
            }
        }
        dma_display->flipDMABuffer();
        server.send(200, "text/plain", "OK");
    });
    
    server.on("/upload_b64", HTTP_POST, []() {
        String body = server.arg("plain");
        int w = 0, h = 0;
        if (server.hasArg("width")) w = server.arg("width").toInt();
        if (server.hasArg("height")) h = server.arg("height").toInt();
        bool overlay = false;
        if (server.hasArg("overlay")) {
            String ov = server.arg("overlay");
            overlay = (ov == "1" || ov == "true");
        }
        if (w <= 0) w = PANEL_RES_X;
        if (h <= 0) h = PANEL_RES_Y;
        uint8_t* raw = nullptr; int rawLen = 0;
        bool ok = decodeB64(body, raw, rawLen);
        if (!ok || rawLen <= 0) { server.send(400, "text/plain", "BAD"); return; }
        frameW = w; frameH = h;
        int maxBytes = w*h*3;
        int toCopy = rawLen < maxBytes ? rawLen : maxBytes;
        if (!frameBuf || !overlay) {
            if (!overlay && frameBuf) { delete[] frameBuf; frameBuf = nullptr; }
            if (!frameBuf) frameBuf = new uint16_t[frameW * frameH];
        }
        int maxW = w < PANEL_RES_X ? w : PANEL_RES_X;
        int maxH = h < PANEL_RES_Y ? h : PANEL_RES_Y;
        if (!overlay) {
            for (int y = 0; y < maxH; y++) {
                for (int x = 0; x < maxW; x++) {
                    int idx = (y*w + x)*3;
                    uint8_t r = (idx+0 < toCopy) ? raw[idx+0] : 0;
                    uint8_t g = (idx+1 < toCopy) ? raw[idx+1] : 0;
                    uint8_t b = (idx+2 < toCopy) ? raw[idx+2] : 0;
                    frameBuf[y * w + x] = dma_display->color565(r,g,b);
                }
            }
            dma_display->clearScreen();
        } else {
            for (int y = 0; y < maxH; y++) {
                for (int x = 0; x < maxW; x++) {
                    int idx = (y*w + x)*3;
                    uint8_t r = (idx+0 < toCopy) ? raw[idx+0] : 0;
                    uint8_t g = (idx+1 < toCopy) ? raw[idx+1] : 0;
                    uint8_t b = (idx+2 < toCopy) ? raw[idx+2] : 0;
                    if (r == 0 && g == 0 && b == 0) {
                        continue;
                    }
                    frameBuf[y * w + x] = dma_display->color565(r,g,b);
                }
            }
        }
        for (int y = 0; y < maxH; y++) {
            for (int x = 0; x < maxW; x++) {
                dma_display->drawPixel(x, y, frameBuf[y * w + x]);
            }
        }
        delete[] raw;
        dma_display->flipDMABuffer();
        server.send(200, "text/plain", "OK");
    });
    
    server.on("/begin_frame", HTTP_POST, []() {
        int w = 0, h = 0;
        if (server.hasArg("width")) w = server.arg("width").toInt();
        if (server.hasArg("height")) h = server.arg("height").toInt();
        if (w <= 0) w = PANEL_RES_X;
        if (h <= 0) h = PANEL_RES_Y;
        frameW = w; frameH = h;
        dma_display->clearScreen();
        server.send(200, "text/plain", "OK");
    });
    
    server.on("/row_b64", HTTP_POST, []() {
        String body = server.arg("plain");
        int w = frameW, h = frameH;
        if (server.hasArg("width")) w = server.arg("width").toInt();
        if (server.hasArg("height")) h = server.arg("height").toInt();
        int row = -1;
        if (server.hasArg("row")) row = server.arg("row").toInt();
        if (w <= 0) w = PANEL_RES_X;
        if (h <= 0) h = PANEL_RES_Y;
        if (row < 0 || row >= h) { server.send(400, "text/plain", "ROW"); return; }
        uint8_t* raw = nullptr; int rawLen = 0;
        bool ok = decodeB64(body, raw, rawLen);
        if (!ok || rawLen <= 0) { server.send(400, "text/plain", "BAD"); return; }
        int bytesPerRow = w * 3;
        int toCopy = rawLen < bytesPerRow ? rawLen : bytesPerRow;
        int maxW = w < PANEL_RES_X ? w : PANEL_RES_X;
        for (int x = 0; x < maxW; x++) {
            int idx = x * 3;
            uint8_t r = (idx+0 < toCopy) ? raw[idx+0] : 0;
            uint8_t g = (idx+1 < toCopy) ? raw[idx+1] : 0;
            uint8_t b = (idx+2 < toCopy) ? raw[idx+2] : 0;
            dma_display->drawPixel(x, row, dma_display->color565(r,g,b));
        }
        delete[] raw;
        server.send(200, "text/plain", "OK");
    });
    
    server.on("/rows_b64", HTTP_POST, []() {
        String body = server.arg("plain");
        int w = frameW, h = frameH;
        if (server.hasArg("width")) w = server.arg("width").toInt();
        if (server.hasArg("height")) h = server.arg("height").toInt();
        int start = -1, count = -1;
        if (server.hasArg("start")) start = server.arg("start").toInt();
        if (server.hasArg("count")) count = server.arg("count").toInt();
        if (w <= 0) w = PANEL_RES_X;
        if (h <= 0) h = PANEL_RES_Y;
        if (start < 0 || count <= 0 || start >= h) { server.send(400, "text/plain", "RANGE"); return; }
        if (start + count > h) count = h - start;
        uint8_t* raw = nullptr; int rawLen = 0;
        bool ok = decodeB64(body, raw, rawLen);
        if (!ok || rawLen <= 0) { server.send(400, "text/plain", "BAD"); return; }
        int bytesPerRow = w * 3;
        int expected = bytesPerRow * count;
        int toCopy = rawLen < expected ? rawLen : expected;
        int maxW = w < PANEL_RES_X ? w : PANEL_RES_X;
        int maxRows = count;
        for (int r = 0; r < maxRows; r++) {
            int rowY = start + r;
            int base = r * bytesPerRow;
            for (int x = 0; x < maxW; x++) {
                int idx = base + x * 3;
                uint8_t rr = (idx+0 < toCopy) ? raw[idx+0] : 0;
                uint8_t gg = (idx+1 < toCopy) ? raw[idx+1] : 0;
                uint8_t bb = (idx+2 < toCopy) ? raw[idx+2] : 0;
                dma_display->drawPixel(x, rowY, dma_display->color565(rr,gg,bb));
            }
        }
        delete[] raw;
        server.send(200, "text/plain", "OK");
    });
    
    server.on("/end_frame", HTTP_POST, []() {
        dma_display->flipDMABuffer();
        server.send(200, "text/plain", "OK");
    });
    
    server.on("/", HTTP_GET, []() {
        server.sendHeader("Access-Control-Allow-Origin", "*");
        server.send(200, "text/plain", "OK");
    });
    
    server.on("/health", HTTP_GET, []() {
        String mode = (WiFi.getMode() == WIFI_AP) ? "AP" : "STA";
        IPAddress ip = (WiFi.getMode() == WIFI_AP) ? WiFi.softAPIP() : WiFi.localIP();
        String s = String("{\"mode\":\"") + mode + "\",\"ip\":\"" + ip.toString() + "\"}";
        server.sendHeader("Access-Control-Allow-Origin", "*");
        server.send(200, "application/json", s);
    });
    
    server.begin();
    Serial.println("HTTP server started");
}

void loop() {
    server.handleClient();
}
