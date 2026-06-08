<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LED Editor - 64x64 Matrix</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav>
        <a href="index.php" class="logo">LED Matrix</a>
        <ul>
            <li><a href="public.php">Gallery</a></li>
            <li><a href="editor.php" class="active">Editor</a></li>
            <li><a href="code.php">Source</a></li>
            <li id="authLink"><a href="login.php" class="btn-nav">Login</a></li>
            <li><button class="theme-toggle-btn" id="themeToggle" title="Toggle light/dark">☀</button></li>
        </ul>
    </nav>

    <div class="container editor-page">
        <h1>Design Editor</h1>

        <div class="main-editor-container">
            <!-- Controls -->
            <div class="controls">
                <h3>Settings</h3>
                <div class="control-group">
                    <label>Grid Size</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" id="gridWidth" value="16" min="8" max="64" placeholder="W">
                        <input type="number" id="gridHeight" value="16" min="8" max="64" placeholder="H">
                    </div>
                    <button id="resizeBtn" class="secondary-btn" style="margin-top: 5px;">Apply Size</button>
                </div>

                <div class="control-group">
                    <label for="colorPicker">Color</label>
                    <input type="color" id="colorPicker" value="#ff0000">
                </div>

                <div class="control-group">
                    <label>Tools</label>
                    <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                        <button id="pencilBtn" class="tool-btn active" title="Pencil">✏️</button>
                        <button id="eraserBtn" class="tool-btn" title="Eraser">🧹</button>
                        <button id="bucketBtn" class="tool-btn" title="Fill Bucket">🪣</button>
                    </div>

                    <label>Brush Size</label>
                    <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                        <button class="size-btn active" data-size="1">1x</button>
                        <button class="size-btn" data-size="2">2x</button>
                        <button class="size-btn" data-size="3">3x</button>
                    </div>

                    <button id="clearBtn" class="clear-btn">Clear Matrix</button>

                    <label style="margin-top: 10px;">Import Image</label>
                    <div class="file-input-wrapper">
                        <button class="file-input-btn">Choose File</button>
                        <input type="file" id="imageInput" accept="image/*">
                    </div>
                </div>

                <div class="control-group">
                    <label for="designName">Save Design</label>
                    <input type="text" id="designName" placeholder="Design Name...">
                    <div style="margin-top: 5px;">
                        <input type="checkbox" id="isPublic"> <label for="isPublic" style="display:inline; font-weight:normal;">Public Gallery</label>
                    </div>
                    <button id="saveBtn" style="margin-top: 10px;">Save Design</button>
                </div>

                <div class="control-group">
                    <label for="matrixIp">Matrix IP</label>
                    <input type="text" id="matrixIp" placeholder="e.g. 192.168.0.50">
                    <label for="animSpeed" style="margin-top: 8px;">Animation Speed</label>
                    <select id="animSpeed">
                        <option value="0.1">0.1× (very slow)</option>
                        <option value="0.2">0.2×</option>
                        <option value="0.33">0.33×</option>
                        <option value="0.5">0.5× (half speed)</option>
                        <option value="0.75">0.75×</option>
                        <option value="1" selected>1× (original)</option>
                        <option value="1.5">1.5×</option>
                        <option value="2">2×</option>
                        <option value="3">3×</option>
                    </select>
                    <button id="sendToMatrixBtn" style="margin-top: 10px;">Send to Matrix</button>
                </div>

                <div class="control-group">
                    <label>Clock</label>
                    <select id="tzSelect" style="margin-bottom: 6px;">
                        <option value="-12">UTC-12</option>
                        <option value="-11">UTC-11</option>
                        <option value="-10">UTC-10</option>
                        <option value="-9">UTC-9</option>
                        <option value="-8">UTC-8</option>
                        <option value="-7">UTC-7</option>
                        <option value="-6">UTC-6</option>
                        <option value="-5">UTC-5</option>
                        <option value="-4">UTC-4</option>
                        <option value="-3">UTC-3</option>
                        <option value="-2">UTC-2</option>
                        <option value="-1">UTC-1</option>
                        <option value="0">UTC+0</option>
                        <option value="1">UTC+1</option>
                        <option value="2" selected>UTC+2</option>
                        <option value="3">UTC+3</option>
                        <option value="4">UTC+4</option>
                        <option value="5">UTC+5</option>
                        <option value="6">UTC+6</option>
                        <option value="7">UTC+7</option>
                        <option value="8">UTC+8</option>
                        <option value="9">UTC+9</option>
                        <option value="10">UTC+10</option>
                        <option value="11">UTC+11</option>
                        <option value="12">UTC+12</option>
                    </select>
                    <div style="display:flex; gap:8px; margin-bottom:6px;">
                        <div style="flex:1;">
                            <label for="clockColorDigit" style="font-size:0.8em;font-weight:normal;">Numbers</label>
                            <input type="color" id="clockColorDigit" value="#ffffff" style="width:100%;height:28px;padding:1px;">
                        </div>
                        <div style="flex:1;">
                            <label for="clockColorBar" style="font-size:0.8em;font-weight:normal;">Line</label>
                            <input type="color" id="clockColorBar" value="#0044ff" style="width:100%;height:28px;padding:1px;">
                        </div>
                    </div>
                    <div style="margin-bottom:6px;">
                        <input type="checkbox" id="clockOverlay">
                        <label for="clockOverlay" style="display:inline;font-weight:normal;font-size:0.85em;">Overlay on image/GIF</label>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button id="clockOnBtn" style="flex:1;">Start Clock</button>
                        <button id="clockOffBtn" style="flex:1;">Stop Clock</button>
                    </div>
                </div>
            </div>

            <!-- LED Matrix -->
            <div class="matrix-container">
                <div class="led-grid" id="ledGrid">
                </div>
            </div>

            <!-- Saved Designs -->
            <div class="controls library-panel">
                <h3>Library</h3>
                <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                    <button id="tabMy" class="secondary-btn tab-active" style="flex:1; padding: 5px; font-size: 0.9em;">My</button>
                    <button id="tabFav" class="secondary-btn" style="flex:1; padding: 5px; font-size: 0.9em;">Favs</button>
                    <button id="tabGifs" class="secondary-btn" style="flex:1; padding: 5px; font-size: 0.9em;">GIFs</button>
                </div>
                <div id="designList" class="design-list">
                    <div class="design-item">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="background.js"></script>
    <script src="script.js"></script>
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
