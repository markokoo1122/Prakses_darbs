#include <WiFi.h>
#include <WebServer.h>
#include <ESP32-HUB75-MatrixPanel-I2S-DMA.h>
#include <time.h>

const char* WIFI_SSID = "Note10+";
const char* WIFI_PASSWORD = "parole135";

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

// Autonomous animation state
// Frames are stored as raw RGB bytes (3 per pixel) so drawAnimFrame can call
// color565() fresh — the same path as live display.  Storing pre-baked uint16
// values and re-passing them to drawPixel double-applies the internal colour
// transform, which scrambles the colours.
uint8_t** animFrames   = nullptr;  // array of per-frame raw-RGB buffers (w*h*3 bytes)
int        animCapacity = 0;       // slots allocated
int        animCount    = 0;       // frames uploaded so far
int        animDelay    = 80;      // ms between frames
bool       animPlaying  = false;
int        animIdx      = 0;
unsigned long animLastMs = 0;

// Which PSRAM slot the current /begin_frame + /rows_b64 + /end_frame is writing into.
// -1  → live display (drawPixel directly, flip in end_frame)
// >=0 → buffering into animFrames[bufWriteIdx]
int bufWriteIdx = -1;

// ── Clock ────────────────────────────────────────────────────────────────────
bool          clockMode      = false;
bool          clockOverlay   = false;   // draw clock on top of image/GIF without clearing screen
int           tzOffset       = 2;       // hours offset from UTC
int           clockStyle     = 99;      // 99 = custom RGB
uint16_t      customDigitCol = 0xFFFF;  // white default
uint16_t      customBarCol   = 0x0008;  // dark blue default
unsigned long clockLastMs    = 0;

struct ClockTheme {
    uint8_t digitR, digitG, digitB;
    uint8_t colonR, colonG, colonB;
    uint8_t barR,   barG,   barB;
};

const ClockTheme CLOCK_THEMES[] PROGMEM = {
    {220,220,255,  80,120,255,  20, 60,220}, // 0: Minimal (default)
    {  0,255,200,   0,220,255,   0,180,255}, // 1: Neon
    {255,160,  0, 180, 80,  0, 200, 80,  0}, // 2: Retro
    {  0,255, 65,   0,200, 40,   0,160, 20}, // 3: Matrix
    {255, 80,  0, 255, 60,  0, 255,180,  0}, // 4: Fire
    {180,230,255, 140,200,255, 100,200,255}, // 5: Ice
    {200, 80,255, 180, 60,255, 120, 20,255}, // 6: Violet
    {255,200, 20, 200,160, 10, 220,140,  0}, // 7: Gold
    {255,150,180, 255,100,150, 120,220,100}, // 8: Spring
    {255,230,  0, 255,200,  0, 255,120,  0}, // 9: Summer
    {255,100,  0, 220, 70,  0, 180, 40,  0}, // 10: Autumn
    {200,240,255, 160,220,255,  80,160,220}, // 11: Winter
    {180,  0,255, 220, 80,255, 255,220,  0}, // 12: Storm
    {255,160, 80, 255,120, 40, 220, 60, 20}, // 13: Sunrise
    {255,220,  0, 255,200,  0,   0, 80,220}, // 14: Pac-Man
    {255,  0,180, 200,  0,255,   0,220,255}, // 15: Cyberpunk
    { 80,220,  0,  60,180,  0,  30,100,  0}, // 16: Creeper
    {180,100,255, 140, 60,255,  60,  0,180}, // 17: Galaxy
};

void getClockColors(int style, uint16_t& colDigit, uint16_t& colColon, uint16_t& colBar) {
    if (style == 99) {
        colDigit = customDigitCol;
        colColon = customDigitCol;
        colBar   = customBarCol;
        return;
    }
    int idx = (style >= 1 && style <= 17) ? style : 0;
    ClockTheme t;
    memcpy_P(&t, &CLOCK_THEMES[idx], sizeof(t));
    colDigit = dma_display->color565(t.digitR, t.digitG, t.digitB);
    colColon = dma_display->color565(t.colonR, t.colonG, t.colonB);
    colBar   = dma_display->color565(t.barR,   t.barG,   t.barB);
}

// 5×9 pixel font for digits 0-9 (each byte = one row, bit4=leftmost pixel)
const uint8_t DIGIT_FONT[10][9] PROGMEM = {
    {14,17,17,17,17,17,17,17,14}, // 0
    { 4,12, 4, 4, 4, 4, 4, 4,14}, // 1
    {14,17, 1, 1,14,16,16,16,31}, // 2
    {14,17, 1, 1, 6, 1, 1,17,14}, // 3
    { 2, 6,10,18,31, 2, 2, 2, 2}, // 4
    {31,16,16,16,30, 1, 1,17,14}, // 5
    {14,16,16,16,30,17,17,17,14}, // 6
    {31, 1, 2, 2, 4, 4, 8, 8, 8}, // 7
    {14,17,17,17,14,17,17,17,14}, // 8
    {14,17,17,17,15, 1, 1, 2,12}, // 9
};

void drawDigit(int d, int x, int y, uint16_t color) {
    if (d < 0 || d > 9) return;
    for (int row = 0; row < 9; row++) {
        uint8_t bits = pgm_read_byte(&DIGIT_FONT[d][row]);
        for (int col = 0; col < 5; col++) {
            if (bits & (1 << (4 - col))) {
                dma_display->fillRect(x + col*2, y + row*2, 2, 2, color);
            }
        }
    }
}

void drawClock() {
    struct tm ti;
    if (!getLocalTime(&ti)) return;

    int h = ti.tm_hour;
    int m = ti.tm_min;
    int s = ti.tm_sec;

    // Digit / colon / bar colours per style
    uint16_t colDigit, colColon, colBar;
    getClockColors(clockStyle, colDigit, colColon, colBar);

    // Layout: DW=10, DH=18, total = 10+2+10+6+10+2+10 = 50px → startX=7
    const int SC = 2, DW = 5*SC, GAP = 2, COL = 6;
    const int totalW = DW*4 + GAP*3 + COL;           // 50
    const int startX = (PANEL_RES_X - totalW) / 2;   // 7
    const int startY = (PANEL_RES_Y - 9*SC) / 2 - 2; // ~21

    dma_display->fillRect(0, 0, PANEL_RES_X, PANEL_RES_Y, 0);

    // HH
    drawDigit(h / 10, startX,            startY, colDigit);
    drawDigit(h % 10, startX + DW + GAP, startY, colDigit);

    // Colon dots
    int cx = startX + DW*2 + GAP + 2;
    dma_display->fillRect(cx, startY + 5,  SC, SC, colColon);
    dma_display->fillRect(cx, startY + 11, SC, SC, colColon);

    // MM
    drawDigit(m / 10, startX + DW*2 + GAP + COL,    startY, colDigit);
    drawDigit(m % 10, startX + DW*3 + GAP*2 + COL,  startY, colDigit);

    // Seconds progress bar
    int barW = (s * PANEL_RES_X) / 60;
    dma_display->fillRect(0, PANEL_RES_Y - 2, barW, 2, colBar);

    dma_display->flipDMABuffer();
}

// Same as drawClock() but does NOT clear the screen first — for overlay mode
void drawClockOverlay() {
    struct tm ti;
    if (!getLocalTime(&ti)) return;

    int h = ti.tm_hour;
    int m = ti.tm_min;
    int s = ti.tm_sec;

    uint16_t colDigit, colColon, colBar;
    getClockColors(clockStyle, colDigit, colColon, colBar);

    const int SC = 2, DW = 5*SC, GAP = 2, COL = 6;
    const int totalW = DW*4 + GAP*3 + COL;
    const int startX = (PANEL_RES_X - totalW) / 2;
    const int startY = (PANEL_RES_Y - 9*SC) / 2 - 2;

    // No fillRect clear here — just draw on top of whatever is already on screen
    drawDigit(h / 10, startX,            startY, colDigit);
    drawDigit(h % 10, startX + DW + GAP, startY, colDigit);

    int cx = startX + DW*2 + GAP + 2;
    dma_display->fillRect(cx, startY + 5,  SC, SC, colColon);
    dma_display->fillRect(cx, startY + 11, SC, SC, colColon);

    drawDigit(m / 10, startX + DW*2 + GAP + COL,   startY, colDigit);
    drawDigit(m % 10, startX + DW*3 + GAP*2 + COL, startY, colDigit);

    int barW = (s * PANEL_RES_X) / 60;
    dma_display->fillRect(0,    PANEL_RES_Y - 2, PANEL_RES_X, 2, 0);  // clear bar row
    dma_display->fillRect(0,    PANEL_RES_Y - 2, barW,        2, colBar);

    dma_display->flipDMABuffer();
}

// ── base64 decoder ──────────────────────────────────────────────────────────
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
    int o = 0, i = 0;
    while (i < len) {
        int a=-1,b=-1,c=-1,d=-1;
        while (i<len && a<0) { a=b64v(s[i++]); if(a==-1) continue; }
        while (i<len && b<0) { b=b64v(s[i++]); if(b==-1) continue; }
        while (i<len && c<0) { c=b64v(s[i++]); if(c==-1) continue; }
        while (i<len && d<0) { d=b64v(s[i++]); if(d==-1) continue; }
        if (a<0 || b<0) break;
        out[o++] = (uint8_t)((a<<2)|(b>>4));
        if (c>=0) {
            out[o++] = (uint8_t)(((b&0x0F)<<4)|(c>>2));
            if (d>=0) out[o++] = (uint8_t)(((c&0x03)<<6)|d);
        } else if (c==-2) break;
        if (d==-2) break;
    }
    outLen = o;
    return o > 0;
}

// ── helpers ──────────────────────────────────────────────────────────────────
static inline uint8_t clamp8(int v) {
    if (v > 255) return 255;
    if (v < 0)   return 0;
    return (uint8_t)v;
}

void drawTestPattern() {
    for (int y=0; y<PANEL_RES_Y; y++)
        for (int x=0; x<PANEL_RES_X; x++) {
            uint8_t r = clamp8(x * 4);
            uint8_t g = clamp8(y * 4);
            dma_display->drawPixel(x, y, dma_display->color565(r, g, 0));
        }
    dma_display->flipDMABuffer();
}

// Draw a buffered animation frame directly to the display.
// When the stored frame is smaller than the panel (e.g. 32×32 stored to save
// memory) it is nearest-neighbour upscaled to fill all 64×64 pixels so the
// image still looks correct instead of appearing in a corner.
void drawAnimFrame(int idx) {
    if (!animFrames || idx<0 || idx>=animCount || !animFrames[idx]) return;
    uint8_t* buf = animFrames[idx];

    if (frameW == PANEL_RES_X && frameH == PANEL_RES_Y) {
        // 1:1 — no scaling, fastest path
        for (int y=0;y<PANEL_RES_Y;y++) {
            for (int x=0;x<PANEL_RES_X;x++) {
                int p = (y*frameW + x)*3;
                dma_display->drawPixel(x, y, dma_display->color565(buf[p], buf[p+1], buf[p+2]));
            }
        }
    } else {
        // Nearest-neighbour upscale/downscale to fill the panel
        for (int y=0;y<PANEL_RES_Y;y++) {
            int srcY = y * frameH / PANEL_RES_Y;
            for (int x=0;x<PANEL_RES_X;x++) {
                int srcX = x * frameW / PANEL_RES_X;
                int p = (srcY * frameW + srcX)*3;
                dma_display->drawPixel(x, y, dma_display->color565(buf[p], buf[p+1], buf[p+2]));
            }
        }
    }
    // Caller is responsible for flipDMABuffer()
}

// ── setup ────────────────────────────────────────────────────────────────────
void setup() {
    Serial.begin(115200);

    HUB75_I2S_CFG mxconfig(PANEL_RES_X, PANEL_RES_Y, PANEL_CHAIN);
    mxconfig.double_buff = true;
    mxconfig.gpio.r1=R1_PIN; mxconfig.gpio.g1=G1_PIN; mxconfig.gpio.b1=B1_PIN;
    mxconfig.gpio.r2=R2_PIN; mxconfig.gpio.g2=B2_PIN; mxconfig.gpio.b2=G2_PIN;
    mxconfig.gpio.a=A_PIN;   mxconfig.gpio.b=B_PIN;   mxconfig.gpio.c=C_PIN;
    mxconfig.gpio.d=D_PIN;   mxconfig.gpio.e=E_PIN;
    mxconfig.gpio.lat=LAT_PIN; mxconfig.gpio.oe=OE_PIN; mxconfig.gpio.clk=CLK_PIN;
    mxconfig.i2sspeed = HUB75_I2S_CFG::HZ_8M;

    dma_display = new MatrixPanel_I2S_DMA(mxconfig);
    dma_display->begin();
    dma_display->setBrightness8(64);
    dma_display->clearScreen();

    Serial.println("WiFi start");
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    int retries = 0;
    while (WiFi.status()!=WL_CONNECTED && retries<40) { delay(500); retries++; }
    if (WiFi.status()!=WL_CONNECTED) {
        Serial.println("WiFi STA failed, starting AP");
        WiFi.mode(WIFI_AP); delay(500);
        WiFi.softAP("ESP32-LED-Matrix","12345678"); delay(1000);
        Serial.print("AP IP: "); Serial.println(WiFi.softAPIP());
    } else {
        Serial.print("STA IP: "); Serial.println(WiFi.localIP());
    }

    // Sync time via NTP
    configTime(tzOffset * 3600, 0, "pool.ntp.org", "time.nist.gov");
    {
        struct tm ti; int tries = 0;
        while (!getLocalTime(&ti) && tries++ < 10) delay(500);
        if (getLocalTime(&ti)) Serial.println("NTP synced");
        else                   Serial.println("NTP sync failed (clock may be wrong)");
    }

    drawTestPattern();

    // ── /upload_bin ──────────────────────────────────────────────────────────
    server.on("/upload_bin", HTTP_POST, []() {
        String body = server.arg("plain");
        int w=0,h=0;
        if (server.hasArg("width"))  w=server.arg("width").toInt();
        if (server.hasArg("height")) h=server.arg("height").toInt();
        if (w<=0) w=PANEL_RES_X; if (h<=0) h=PANEL_RES_Y;
        frameW=w; frameH=h;
        if (frameBuf) { delete[] frameBuf; frameBuf=nullptr; }
        frameBuf = new uint16_t[w*h];
        int maxBytes=w*h*3, toCopy=min((int)body.length(),maxBytes);
        const uint8_t* src=(const uint8_t*)body.c_str();
        for (int i=0;i<w*h;i++) {
            int idx=i*3;
            frameBuf[i]=dma_display->color565(
                idx<toCopy?src[idx]:0, idx+1<toCopy?src[idx+1]:0, idx+2<toCopy?src[idx+2]:0);
        }
        dma_display->clearScreen();
        int maxW=w<PANEL_RES_X?w:PANEL_RES_X, maxH=h<PANEL_RES_Y?h:PANEL_RES_Y;
        for (int y=0;y<maxH;y++) for (int x=0;x<maxW;x++)
            dma_display->drawPixel(x,y,frameBuf[y*w+x]);
        dma_display->flipDMABuffer();
        server.send(200,"text/plain","OK");
    });

    // ── /upload_b64 ──────────────────────────────────────────────────────────
    server.on("/upload_b64", HTTP_POST, []() {
        if (!server.hasArg("plain")) { server.send(400,"text/plain","EMPTY"); return; }
        String body=server.arg("plain");
        int w=0,h=0;
        if (server.hasArg("width"))  w=server.arg("width").toInt();
        if (server.hasArg("height")) h=server.arg("height").toInt();
        bool overlay=false;
        if (server.hasArg("overlay")) { String ov=server.arg("overlay"); overlay=(ov=="1"||ov=="true"); }
        if (w<=0) w=PANEL_RES_X; if (h<=0) h=PANEL_RES_Y;
        uint8_t* raw=nullptr; int rawLen=0;
        if (!decodeB64(body,raw,rawLen)||rawLen<=0) { server.send(400,"text/plain","BAD"); return; }
        frameW=w; frameH=h;
        int maxBytes=w*h*3, toCopy=rawLen<maxBytes?rawLen:maxBytes;
        if (!frameBuf||!overlay) { if (frameBuf){delete[] frameBuf;frameBuf=nullptr;} frameBuf=new uint16_t[w*h]; }
        int maxW=w<PANEL_RES_X?w:PANEL_RES_X, maxH=h<PANEL_RES_Y?h:PANEL_RES_Y;
        if (!overlay) {
            for (int y=0;y<maxH;y++) for (int x=0;x<maxW;x++) {
                int idx=(y*w+x)*3;
                frameBuf[y*w+x]=dma_display->color565(
                    idx<toCopy?raw[idx]:0, idx+1<toCopy?raw[idx+1]:0, idx+2<toCopy?raw[idx+2]:0);
            }
            dma_display->clearScreen();
        } else {
            for (int y=0;y<maxH;y++) for (int x=0;x<maxW;x++) {
                int idx=(y*w+x)*3;
                uint8_t r=idx<toCopy?raw[idx]:0, g=idx+1<toCopy?raw[idx+1]:0, b=idx+2<toCopy?raw[idx+2]:0;
                if (r||g||b) frameBuf[y*w+x]=dma_display->color565(r,g,b);
            }
        }
        for (int y=0;y<maxH;y++) for (int x=0;x<maxW;x++)
            dma_display->drawPixel(x,y,frameBuf[y*w+x]);
        delete[] raw;
        dma_display->flipDMABuffer();
        server.send(200,"text/plain","OK");
    });

    // ── /begin_frame ─────────────────────────────────────────────────────────
    // If ?buf=N is present, writes into animFrames[N] instead of the live display.
    server.on("/begin_frame", HTTP_POST, []() {
        int w=0,h=0;
        if (server.hasArg("width"))  w=server.arg("width").toInt();
        if (server.hasArg("height")) h=server.arg("height").toInt();
        if (w<=0) w=PANEL_RES_X; if (h<=0) h=PANEL_RES_Y;
        frameW=w; frameH=h;

        if (server.hasArg("buf")) {
            int bi=server.arg("buf").toInt();
            if (bi>=0 && bi<animCapacity && animFrames) {
                if (!animFrames[bi]) {
                    size_t sz = (size_t)w*h*3;
                    animFrames[bi]=(uint8_t*)ps_malloc(sz);
                    if (!animFrames[bi]) animFrames[bi]=(uint8_t*)malloc(sz);
                    if (animFrames[bi]) {
                        memset(animFrames[bi], 0, sz);
                    } else {
                        // Both PSRAM and heap exhausted — tell the browser so it
                        // can stop uploading and play back however many frames fit.
                        Serial.printf("OOM: cannot alloc %u bytes for frame %d (heap free: %u)\n",
                                      sz, bi, ESP.getFreeHeap());
                        server.send(507, "text/plain", "OOM");
                        return;
                    }
                }
                bufWriteIdx = bi;
            } else {
                bufWriteIdx = -1;
            }
        } else {
            bufWriteIdx = -1;
            // Fill the back buffer with black before new pixels arrive.
            // fillRect only writes to the back (draw) buffer — the front buffer
            // keeps displaying the old image with no flash until end_frame flips.
            // This prevents old pixels outside the new image's bounds from showing.
            dma_display->fillRect(0, 0, PANEL_RES_X, PANEL_RES_Y, 0);
        }
        server.send(200,"text/plain","OK");
    });

    // ── /row_b64 (single row) ────────────────────────────────────────────────
    server.on("/row_b64", HTTP_POST, []() {
        String body=server.arg("plain");
        int w=frameW,h=frameH;
        if (server.hasArg("width"))  w=server.arg("width").toInt();
        if (server.hasArg("height")) h=server.arg("height").toInt();
        int row=-1;
        if (server.hasArg("row")) row=server.arg("row").toInt();
        if (w<=0) w=PANEL_RES_X; if (h<=0) h=PANEL_RES_Y;
        if (row<0||row>=h) { server.send(400,"text/plain","ROW"); return; }
        uint8_t* raw=nullptr; int rawLen=0;
        if (!decodeB64(body,raw,rawLen)||rawLen<=0) { server.send(400,"text/plain","BAD"); return; }
        int bpr=w*3, toCopy=rawLen<bpr?rawLen:bpr;
        int maxW=w<PANEL_RES_X?w:PANEL_RES_X;
        for (int x=0;x<maxW;x++) {
            int idx=x*3;
            dma_display->drawPixel(x,row,dma_display->color565(
                idx<toCopy?raw[idx]:0, idx+1<toCopy?raw[idx+1]:0, idx+2<toCopy?raw[idx+2]:0));
        }
        delete[] raw;
        server.send(200,"text/plain","OK");
    });

    // ── /rows_b64 (multiple rows) ─────────────────────────────────────────────
    // Writes into animFrames[bufWriteIdx] when in buffer mode, else draws live.
    server.on("/rows_b64", HTTP_POST, []() {
        String body=server.arg("plain");
        int w=frameW,h=frameH;
        if (server.hasArg("width"))  w=server.arg("width").toInt();
        if (server.hasArg("height")) h=server.arg("height").toInt();
        int start=-1,count=-1;
        if (server.hasArg("start")) start=server.arg("start").toInt();
        if (server.hasArg("count")) count=server.arg("count").toInt();
        if (w<=0) w=PANEL_RES_X; if (h<=0) h=PANEL_RES_Y;
        if (start<0||count<=0||start>=h) { server.send(400,"text/plain","RANGE"); return; }
        if (start+count>h) count=h-start;
        uint8_t* raw=nullptr; int rawLen=0;
        if (!decodeB64(body,raw,rawLen)||rawLen<=0) { server.send(400,"text/plain","BAD"); return; }
        int bpr=w*3, expected=bpr*count, toCopy=rawLen<expected?rawLen:expected;
        int maxW=w<PANEL_RES_X?w:PANEL_RES_X;

        if (bufWriteIdx>=0 && animFrames && animFrames[bufWriteIdx]) {
            // Buffer mode — store raw RGB bytes; color565() is applied at draw time
            // so the live and buffered paths are identical.
            uint8_t* buf=animFrames[bufWriteIdx];
            for (int r=0;r<count;r++) {
                int rowY=start+r; if (rowY>=h) break;
                int base=r*bpr;
                for (int x=0;x<maxW;x++) {
                    int src=base+x*3;
                    int dst=(rowY*w+x)*3;
                    buf[dst+0]=src<toCopy     ? raw[src]   : 0;
                    buf[dst+1]=src+1<toCopy   ? raw[src+1] : 0;
                    buf[dst+2]=src+2<toCopy   ? raw[src+2] : 0;
                }
            }
        } else {
            // Live display mode — draw directly
            for (int r=0;r<count;r++) {
                int rowY=start+r;
                int base=r*bpr;
                for (int x=0;x<maxW;x++) {
                    int idx=base+x*3;
                    dma_display->drawPixel(x,rowY,dma_display->color565(
                        idx<toCopy?raw[idx]:0, idx+1<toCopy?raw[idx+1]:0, idx+2<toCopy?raw[idx+2]:0));
                }
            }
        }
        delete[] raw;
        server.send(200,"text/plain","OK");
    });

    // ── /end_frame ────────────────────────────────────────────────────────────
    server.on("/end_frame", HTTP_POST, []() {
        if (bufWriteIdx>=0) {
            // Finalise buffer slot — update animCount, stay invisible until anim_play
            if (bufWriteIdx+1 > animCount) animCount=bufWriteIdx+1;
            bufWriteIdx=-1;
        } else {
            // Live display — flip to show assembled frame atomically
            dma_display->flipDMABuffer();
        }
        server.send(200,"text/plain","OK");
    });

    // ── /anim_init?count=N ────────────────────────────────────────────────────
    // Allocate N frame slots.  Must be called before uploading frames.
    server.on("/anim_init", HTTP_POST, []() {
        int count=0;
        if (server.hasArg("count")) count=server.arg("count").toInt();
        if (count<=0||count>512) { server.send(400,"text/plain","BAD"); return; }

        animPlaying=false;
        bufWriteIdx=-1;

        // Free any existing buffers
        if (animFrames) {
            for (int i=0;i<animCapacity;i++) { if (animFrames[i]) { free(animFrames[i]); animFrames[i]=nullptr; } }
            free(animFrames); animFrames=nullptr;
        }
        animCapacity=0; animCount=0;

        animFrames=(uint8_t**)calloc(count,sizeof(uint8_t*));
        if (!animFrames) { server.send(500,"text/plain","MEM"); return; }
        animCapacity=count;

        Serial.printf("/anim_init: capacity=%d frames\n",count);
        server.send(200,"text/plain","OK");
    });

    // ── /anim_play?count=N&delay=M ────────────────────────────────────────────
    // Start autonomous playback of the buffered frames.
    server.on("/anim_play", HTTP_POST, []() {
        if (server.hasArg("delay")) {
            animDelay=server.arg("delay").toInt();
            if (animDelay<16) animDelay=16;   // hard floor ~60 fps
        }
        if (server.hasArg("count")) {
            int c=server.arg("count").toInt();
            if (c>0 && c<=animCapacity) animCount=c;
        }
        if (animCount<=0) { server.send(400,"text/plain","NOFRAMES"); return; }
        animIdx=0;
        animLastMs=millis();
        animPlaying=true;
        Serial.printf("/anim_play: count=%d delay=%dms\n",animCount,animDelay);
        server.send(200,"text/plain","OK");
    });

    // ── /anim_stop ────────────────────────────────────────────────────────────
    server.on("/anim_stop", HTTP_POST, []() {
        animPlaying=false;
        Serial.println("/anim_stop");
        server.send(200,"text/plain","OK");
    });

    // ── /clock_on?tz=N&style=N&overlay=0|1&dr=R&dg=G&db=B&br=R&bg=G&bb=B ────
    server.on("/clock_on", HTTP_POST, []() {
        if (server.hasArg("tz"))      tzOffset     = server.arg("tz").toInt();
        if (server.hasArg("style"))   clockStyle   = server.arg("style").toInt();
        // Custom RGB colors — if provided, force style=99
        if (server.hasArg("dr")) {
            int dr = server.arg("dr").toInt(), dg = server.arg("dg").toInt(), db = server.arg("db").toInt();
            int br = server.arg("br").toInt(), bg = server.arg("bg").toInt(), bb = server.arg("bb").toInt();
            customDigitCol = dma_display->color565(dr, dg, db);
            customBarCol   = dma_display->color565(br, bg, bb);
            clockStyle = 99;
        }
        bool overlay = server.hasArg("overlay") && server.arg("overlay") == "1";
        configTime(tzOffset * 3600, 0, "pool.ntp.org", "time.nist.gov");
        if (overlay) {
            // Overlay mode: keep animation running, draw clock on top
            clockMode    = false;
            clockOverlay = true;
        } else {
            // Normal mode: stop animation, show clock only
            animPlaying  = false;
            clockMode    = true;
            clockOverlay = false;
        }
        clockLastMs = 0;
        Serial.printf("/clock_on tz=%d style=%d overlay=%d\n", tzOffset, clockStyle, overlay);
        server.send(200, "text/plain", "OK");
    });

    // ── /clock_off ────────────────────────────────────────────────────────────
    server.on("/clock_off", HTTP_POST, []() {
        clockMode    = false;
        clockOverlay = false;
        dma_display->clearScreen();
        Serial.println("/clock_off");
        server.send(200, "text/plain", "OK");
    });

    // ── /health ───────────────────────────────────────────────────────────────
    server.on("/", HTTP_GET, []() {
        server.sendHeader("Access-Control-Allow-Origin","*");
        server.send(200,"text/plain","OK");
    });
    server.on("/health", HTTP_GET, []() {
        String mode=(WiFi.getMode()==WIFI_AP)?"AP":"STA";
        IPAddress ip=(WiFi.getMode()==WIFI_AP)?WiFi.softAPIP():WiFi.localIP();
        String s=String("{\"mode\":\"")+mode+"\",\"ip\":\""+ip.toString()+"\"}";
        server.sendHeader("Access-Control-Allow-Origin","*");
        server.send(200,"application/json",s);
    });

    server.begin();
    Serial.println("HTTP server started");
}

// ── loop ──────────────────────────────────────────────────────────────────────
void loop() {
    server.handleClient();

    // Clock tick — update every second (full-screen clock, no animation)
    if (clockMode) {
        unsigned long now = millis();
        if (now - clockLastMs >= 1000) {
            clockLastMs = now;
            drawClock();
        }
        return; // don't run animation while clock is on
    }

    // Autonomous animation tick — runs entirely in RAM, no network involved
    if (animPlaying && animCount > 0) {
        unsigned long now = millis();
        if (now - animLastMs >= (unsigned long)animDelay) {
            animLastMs = now;
            drawAnimFrame(animIdx);           // draws to back buffer, no flip
            if (clockOverlay) {
                drawClockOverlay();           // draws clock on same back buffer, then flips once
            } else {
                dma_display->flipDMABuffer(); // no overlay — just flip
            }
            animIdx = (animIdx + 1) % animCount;
        }
    }
}


