<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 Source Code - LED Matrix</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css" id="hljs-theme">
    <style>
        .code-page-header {
            padding: 48px 0 28px;
        }
        .container > h3 {
            color: #ccc;
        }
        .code-page-header h1 {
            font-size: 1.8em;
            color: #fff;
            margin-bottom: 10px;
        }
        .code-page-header p {
            color: #bbb;
            font-size: 0.92em;
            line-height: 1.8;
            max-width: 620px;
        }
        .code-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 18px 0 0;
        }
        .code-badge {
            display: inline-block;
            padding: 4px 12px;
            border: 1px solid #2a2a2a;
            font-size: 0.72em;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            font-family: 'Courier New', monospace;
            color: #aaa;
        }
        .code-badge.highlight {
            border-color: rgba(255,255,255,0.25);
            color: #bbb;
        }
        .code-block-wrapper {
            position: relative;
            margin-bottom: 40px;
        }
        .code-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #0d0d0d;
            border: 1px solid #1a1a1a;
            border-bottom: none;
            padding: 8px 16px;
        }
        .code-toolbar span {
            font-family: 'Courier New', monospace;
            font-size: 0.72em;
            letter-spacing: 0.1em;
            color: #888;
            text-transform: uppercase;
        }
        .copy-btn {
            background: transparent;
            border: 1px solid #333;
            color: #aaa;
            padding: 5px 16px;
            font-size: 0.72em;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            font-family: 'Courier New', monospace;
            cursor: pointer;
            transition: border-color 0.2s, color 0.2s;
            width: auto;
            margin: 0;
        }
        .copy-btn:hover {
            border-color: #fff;
            color: #fff;
        }
        .copy-btn.copied {
            border-color: rgba(33,150,243,0.6);
            color: #2196f3;
        }
        pre {
            margin: 0;
            border: 1px solid #1a1a1a;
            border-radius: 0;
            font-size: 0.82em;
            line-height: 1.6;
            max-height: 70vh;
            overflow-y: auto;
        }
        pre code.hljs {
            padding: 20px 24px;
            border-radius: 0;
        }
        .wifi-notice {
            background: rgba(33, 150, 243, 0.06);
            border: 1px solid rgba(33, 150, 243, 0.2);
            padding: 16px 20px;
            margin-bottom: 28px;
            font-size: 0.85em;
            line-height: 1.7;
            color: #aaa;
        }
        .wifi-notice strong {
            color: #2196f3;
            font-family: 'Courier New', monospace;
        }
        .wifi-notice code {
            background: rgba(255,255,255,0.06);
            padding: 1px 6px;
            font-family: 'Courier New', monospace;
            font-size: 0.95em;
            color: #ccc;
        }
        .deps-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1px;
            background: #0d0d0d;
            border: 1px solid #0d0d0d;
            margin-bottom: 40px;
        }
        .dep-item {
            background: #000;
            padding: 18px 20px;
        }
        .dep-item h4 {
            font-family: 'Courier New', monospace;
            font-size: 0.78em;
            color: #ccc;
            letter-spacing: 0.08em;
            margin-bottom: 4px;
        }
        .dep-item p {
            font-size: 0.78em;
            color: #aaa;
            margin: 0;
        }

        /* Light mode — only override elements that actually get a white/light background.
           Anything on the black body (h1, badges, wifi-notice) keeps its dark-mode colors. */

        /* code-toolbar gets a light bg → its children need dark text */
        [data-theme="light"] .code-toolbar { background: #f0f0f0; border-color: #ddd; }
        [data-theme="light"] .code-toolbar span { color: #666; }
        [data-theme="light"] .copy-btn { border-color: #bbb; color: #444; }
        [data-theme="light"] .copy-btn:hover { border-color: #333; color: #111; }
        [data-theme="light"] pre { border-color: #ddd; }

        /* dep-items get a white bg → their children need dark text */
        [data-theme="light"] .deps-list { background: #e0e0e0; border-color: #e0e0e0; }
        [data-theme="light"] .dep-item { background: #fff; }
        [data-theme="light"] .dep-item h4 { color: #222; }
        [data-theme="light"] .dep-item p { color: #888; }
    </style>
</head>
<body>
    <nav>
        <a href="index.php" class="logo">LED Matrix</a>
        <ul>
            <li><a href="public.php">Gallery</a></li>
            <li><a href="editor.php">Editor</a></li>
            <li><a href="code.php" class="active">Source</a></li>
            <li id="authLink"><a href="login.php" class="btn-nav">Login</a></li>
            <li><button class="theme-toggle-btn" id="themeToggle" title="Toggle light/dark">☀</button></li>
        </ul>
    </nav>

    <div class="container">
        <div class="code-page-header">
            <h1>ESP32 Source Code</h1>
            <p>The full firmware for the LED matrix controller. Flash this to your ESP32, replace the Wi-Fi credentials, and it will connect to the web editor automatically.</p>
            <div class="code-meta">
                <span class="code-badge highlight">homer.ino</span>
                <span class="code-badge">Arduino / ESP32</span>
                <span class="code-badge">64×64 HUB75 Panel</span>
                <span class="code-badge">Open Source</span>
            </div>
        </div>

        <div class="wifi-notice">
            <strong>Before flashing</strong> — replace the two lines at the top of the file with your own network:<br>
            <code>const char* WIFI_SSID = "YOUR_WIFI_NAME";</code><br>
            <code>const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";</code><br>
            If the ESP32 cannot connect to your network it will fall back to its own access point (<code>ESP32-LED-Matrix</code> / password <code>12345678</code>).
        </div>

        <h3>Required Libraries</h3>
        <div class="deps-list">
            <div class="dep-item">
                <h4>ESP32-HUB75-MatrixPanel-I2S-DMA</h4>
                <p>Drives the HUB75 64×64 RGB panel via DMA. Install via Arduino Library Manager.</p>
            </div>
            <div class="dep-item">
                <h4>WiFi.h / WebServer.h</h4>
                <p>Built into the ESP32 Arduino core. No extra install needed.</p>
            </div>
            <div class="dep-item">
                <h4>time.h</h4>
                <p>Standard C library for NTP clock sync. Built-in.</p>
            </div>
        </div>

        <h3>Firmware</h3>
        <div class="code-block-wrapper">
            <div class="code-toolbar">
                <span>homer.ino</span>
                <button class="copy-btn" id="copyBtn">Copy</button>
            </div>
            <pre><code class="language-cpp" id="codeBlock"><?php
$code = file_get_contents(__DIR__ . '/homer/homer.ino');
// Sanitise the real credentials
$code = preg_replace('/const char\* WIFI_SSID\s*=\s*"[^"]*"/', 'const char* WIFI_SSID     = "YOUR_WIFI_NAME"', $code);
$code = preg_replace('/const char\* WIFI_PASSWORD\s*=\s*"[^"]*"/', 'const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD"', $code);
echo htmlspecialchars($code);
?></code></pre>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script src="background.js"></script>
    <script>
        // Theme toggle
        (function() {
            const root = document.documentElement;
            const saved = localStorage.getItem('theme') || 'dark';
            root.setAttribute('data-theme', saved);
            const btn = document.getElementById('themeToggle');
            btn.textContent = saved === 'dark' ? '☀' : '☾';
            const hljsTheme = document.getElementById('hljs-theme');
            hljsTheme.href = saved === 'dark'
                ? 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css'
                : 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-light.min.css';
            btn.addEventListener('click', () => {
                const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                localStorage.setItem('theme', next);
                btn.textContent = next === 'dark' ? '☀' : '☾';
                hljsTheme.href = next === 'dark'
                    ? 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-dark.min.css'
                    : 'https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-light.min.css';
            });
        })();

        // Syntax highlight
        hljs.highlightAll();

        // Copy button
        document.getElementById('copyBtn').addEventListener('click', function() {
            const code = document.getElementById('codeBlock').innerText;
            navigator.clipboard.writeText(code).then(() => {
                this.textContent = 'Copied!';
                this.classList.add('copied');
                setTimeout(() => { this.textContent = 'Copy'; this.classList.remove('copied'); }, 2000);
            });
        });

        // Auth check
        fetch('auth.php?action=check')
            .then(res => res.json())
            .then(data => {
                if (data.logged_in) {
                    document.getElementById('authLink').innerHTML =
                        `<a href="#" onclick="logout()" class="btn-nav">Logout (${data.user.username})</a>`;
                }
            });
        function logout() {
            fetch('auth.php?action=logout', { method: 'POST' })
                .then(() => window.location.reload());
        }
    </script>
</body>
</html>
