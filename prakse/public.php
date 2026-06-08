<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Gallery - LED Matrix</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .gallery-tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 24px;
        }
        .gallery-tab {
            background: transparent;
            border: 1px solid #1e1e1e;
            color: #444;
            padding: 8px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.85em;
            letter-spacing: 0.05em;
            transition: border-color 0.15s, color 0.15s;
        }
        .gallery-tab:hover {
            border-color: #333;
            color: #777;
        }
        .gallery-tab.tab-active {
            border-color: #444;
            color: #ccc;
        }
        .gif-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1px;
        }
        /* In the public gallery, match the 260px preview height of design cards */
        .gif-gallery-grid .gif-thumb-wrap {
            height: 260px;
            aspect-ratio: unset;
        }
        .gif-gallery-grid .gif-card {
            background: #000;
            border: none;
            border-radius: 0;
        }
        .gif-gallery-grid .gif-card:hover {
            background: #030303;
            border-color: transparent;
        }
        .gif-gallery-grid .gif-card-info {
            padding: 14px 16px;
        }
        .gif-gallery-grid .gif-card-name {
            font-size: 0.9em;
            color: #888;
        }
        .gif-gallery-grid .gif-card-meta {
            font-size: 0.8em;
            color: #444;
            margin-top: 3px;
        }
        .gif-gallery-grid .gif-card-by {
            font-size: 0.75em;
            color: #333;
            margin-top: 2px;
        }
    </style>
</head>
<body>

    <nav>
        <a href="index.php" class="logo">LED Matrix</a>
        <ul>
            <li><a href="public.php" class="active">Gallery</a></li>
            <li><a href="editor.php">Editor</a></li>
            <li><a href="code.php">Source</a></li>
            <li id="authLink"><a href="login.php" class="btn-nav">Login</a></li>
            <li><button class="theme-toggle-btn" id="themeToggle" title="Toggle light/dark">☀</button></li>
        </ul>
    </nav>

    <div class="container">
        <div class="hero" style="padding: 20px 0 16px;">
            <h1>Public Gallery</h1>
            <p>Explore designs created by the community</p>
        </div>

        <div class="gallery-tabs">
            <button class="gallery-tab tab-active" id="tabDesigns" onclick="showTab('designs')">Designs</button>
            <button class="gallery-tab" id="tabGifs" onclick="showTab('gifs')">GIFs</button>
        </div>

        <!-- Designs tab -->
        <div id="panelDesigns">
            <div id="gallery" class="gallery-container">
                <div style="color: #888; grid-column: 1/-1; text-align: center;">Loading designs...</div>
            </div>
        </div>

        <!-- GIFs tab -->
        <div id="panelGifs" style="display:none;">
            <div id="gifGallery" class="gif-gallery-grid">
                <div style="color: #888;">Loading GIFs...</div>
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

        function showTab(tab) {
            document.getElementById('tabDesigns').classList.toggle('tab-active', tab === 'designs');
            document.getElementById('tabGifs').classList.toggle('tab-active', tab === 'gifs');
            document.getElementById('panelDesigns').style.display = tab === 'designs' ? '' : 'none';
            document.getElementById('panelGifs').style.display  = tab === 'gifs'    ? '' : 'none';
        }

        // ── Designs ──────────────────────────────────────────────────────────

        async function loadGallery() {
            try {
                const response = await fetch('api.php?type=public');
                const designs = await response.json();
                const gallery = document.getElementById('gallery');
                gallery.innerHTML = '';

                if (!designs || designs.length === 0) {
                    gallery.innerHTML = '<div style="color:#888;grid-column:1/-1;text-align:center;">No public designs yet.</div>';
                    return;
                }

                designs.forEach(design => {
                    const card = document.createElement('div');
                    card.className = 'design-card';
                    const width = design.width || 16;
                    const height = design.height || 16;
                    card.innerHTML = `
                        <div class="preview-container">
                            <canvas id="canvas-${design.id}" width="${width}" height="${height}"></canvas>
                        </div>
                        <div class="card-info">
                            <div>
                                <h3 class="card-title" title="${design.name}">${design.name}</h3>
                                <div class="card-meta">${width}×${height} · ${new Date(design.created_at).toLocaleDateString()}</div>
                            </div>
                            <div class="card-actions">
                                <a href="editor.php?load=${design.id}" class="open-btn">Open in Editor</a>
                            </div>
                        </div>
                    `;
                    gallery.appendChild(card);
                    renderPreview(design.grid_data, `canvas-${design.id}`, width, height);
                });
            } catch (error) {
                document.getElementById('gallery').innerHTML =
                    '<div style="color:red;text-align:center;">Error loading designs.</div>';
            }
        }

        function renderPreview(gridData, canvasId, width, height) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#000';
            ctx.fillRect(0, 0, width, height);
            let flatData = Array.isArray(gridData)
                ? (Array.isArray(gridData[0]) ? gridData.flat() : gridData)
                : [];
            flatData.forEach((val, i) => {
                if (val && val !== 0 && val !== '0') {
                    const color = (val === 1 || val === '1') ? '#ff0000' : val;
                    ctx.fillStyle = color;
                    ctx.fillRect(i % width, Math.floor(i / width), 1, 1);
                }
            });
        }

        // ── GIFs ─────────────────────────────────────────────────────────────

        async function loadGifs() {
            try {
                const res = await fetch('api.php?type=publicgifs');
                const gifs = res.ok ? await res.json() : [];
                const container = document.getElementById('gifGallery');
                container.innerHTML = '';

                if (!gifs || gifs.length === 0) {
                    container.innerHTML = '<div style="color:#888;">No public GIFs yet. Upload one and toggle it public in the editor!</div>';
                    return;
                }

                gifs.forEach(item => {
                    const shortName = item.original_filename.length > 20
                        ? item.original_filename.substring(0, 20) + '…'
                        : item.original_filename;
                    const typeLabel = item.is_gif == 1 ? `${item.frame_count} frames` : 'Image';
                    const byLine = item.username ? `<div class="gif-card-by">by ${item.username}</div>` : '';

                    const card = document.createElement('div');
                    card.className = 'gif-card';
                    card.innerHTML = `
                        <div class="gif-thumb-wrap">
                            <img src="${item.file_path}" class="gif-thumb" alt="${shortName}">
                            <div class="gif-thumb-overlay">Open in Editor</div>
                        </div>
                        <div class="gif-card-info">
                            <div class="gif-card-name" title="${item.original_filename}">${shortName}</div>
                            <div class="gif-card-meta">${typeLabel} · ${item.width}×${item.height}</div>
                            ${byLine}
                        </div>
                    `;

                    card.querySelector('.gif-thumb-wrap').addEventListener('click', () => {
                        window.location.href = `editor.php?gif=${item.id}`;
                    });

                    container.appendChild(card);
                });
            } catch (e) {
                console.error('Error loading GIFs', e);
            }
        }

        // Init
        loadGallery();
        loadGifs();
    </script>
</body>
</html>
