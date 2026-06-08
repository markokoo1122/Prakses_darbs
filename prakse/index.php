<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LED Matrix Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <a href="index.php" class="logo">LED Matrix</a>
        <ul>
            <li><a href="public.php">Gallery</a></li>
            <li><a href="editor.php">Editor</a></li>
            <li><a href="code.php">Source</a></li>
            <li id="authLink"><a href="login.php" class="btn-nav">Login</a></li>
            <li><button class="theme-toggle-btn" id="themeToggle" title="Toggle light/dark">☀</button></li>
        </ul>
    </nav>

    <div class="container">
        <div class="hero">
            <h1>Create LED Matrix Designs</h1>
            <p>Design, save, and control your 64x64 LED Matrix project. Import images, draw pixel art, and bring your hardware to life.</p>
            <a href="editor.php" class="btn-nav" style="font-size: 1.2em; padding: 15px 30px;">Start Designing</a>
        </div>

        <div id="about" class="project-details">
            <div class="detail-card">
                <h3>Hardware Used</h3>
                <img src="img/matrix.jpg" alt="LED Matrix">
                <p>We use a high-density 64x64 RGB LED Matrix panel driven by an ESP32 microcontroller.</p>
                <a target="_blank" href="https://www.aliexpress.com/item/1005004050009522.html?spm=a2g0o.order_list.order_list_main.53.52921802PsCs6j">Link</a>
            </div>
            <div class="detail-card">
                <h3>Controller</h3>
                <img src="img/esp32.jpg" alt="Controller">
                <p>The brain of the operation is an ESP32, handling Wi-Fi connectivity and driving the display.</p>
                <a target="_blank" href="https://www.aliexpress.com/item/1005004476867346.html?spm=a2g0o.order_list.order_list_main.47.52921802PsCs6j">Link</a>
            </div>
            <div class="detail-card">
                <h3>Power Supply</h3>
                <img src="img/psu.jpg" alt="Power Supply">
                <p>A robust 5V 10A power supply ensures consistent brightness across all 4096 LEDs.</p>
                <a target="_blank" href="https://www.aliexpress.com/item/4000521124523.html?spm=a2g0o.order_list.order_list_main.11.52921802PsCs6j">link</a>
            </div>
        </div>
    </div>

    <script src="background.js"></script>
    <script>
        (function() {
            const root = document.documentElement;
            const saved = localStorage.getItem('theme') || 'dark';
            root.setAttribute('data-theme', saved);
            const btn = document.getElementById('themeToggle');
            btn.textContent = saved === 'dark' ? '☀' : '☾';
            btn.addEventListener('click', () => {
                const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
                root.setAttribute('data-theme', next);
                localStorage.setItem('theme', next);
                btn.textContent = next === 'dark' ? '☀' : '☾';
            });
        })();
    </script>
    <script>
        // Check if user is logged in
        fetch('auth.php?action=check')
            .then(res => res.json())
            .then(data => {
                if (data.logged_in) {
                    const authLink = document.getElementById('authLink');
                    authLink.innerHTML = `<a href="#" onclick="logout()" class="btn-nav">Logout (${data.user.username})</a>`;
                }
            });

        function logout() {
            fetch('auth.php?action=logout', { method: 'POST' })
                .then(() => window.location.reload());
        }
    </script>
</body>
</html>
