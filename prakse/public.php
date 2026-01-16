<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Gallery - LED Matrix</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .gallery-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 40px;
            padding: 40px;
        }

        .design-card {
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.5);
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #333;
            display: flex;
            flex-direction: column;
            position: relative;
        }

        .design-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.7);
            border-color: #2196F3;
        }

        .size-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.7);
            color: #2196F3;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            font-weight: bold;
            border: 1px solid #2196F3;
            z-index: 10;
        }

        .preview-container {
            width: 100%;
            height: 300px;
            background: #000;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            box-sizing: border-box;
            border-bottom: 1px solid #333;
        }

        canvas {
            width: 100%;
            height: 100%;
            object-fit: contain;
            image-rendering: pixelated; /* Keeps pixels sharp */
        }

        .card-info {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .card-title {
            font-size: 1.2em;
            margin: 0 0 5px 0;
            color: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .card-meta {
            font-size: 0.9em;
            color: #888;
            margin-bottom: 15px;
        }

        .card-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .open-btn {
            background: #2196F3;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
            transition: background 0.2s;
        }

        .open-btn:hover {
            background: #1976D2;
        }
        
        /* Specific override requested by user:
           "backround is not like showing a few square ligts like shining"
           We ensure the canvas background is pure black and only lit pixels show.
        */
    </style>
</head>
<body>
    <!-- Background Animation -->
    <div class="bg-animation">
        <div class="bg-square" style="top: 10%; left: 10%; width: 100px; height: 100px; animation-duration: 8s;"></div>
        <div class="bg-square" style="top: 70%; left: 80%; width: 150px; height: 150px; animation-duration: 12s;"></div>
        <div class="bg-square" style="top: 40%; left: 40%; width: 80px; height: 80px; animation-duration: 6s;"></div>
        <div class="bg-square" style="top: 20%; left: 60%; width: 120px; height: 120px; animation-duration: 15s;"></div>
        <div class="bg-square" style="top: 80%; left: 20%; width: 60px; height: 60px; animation-duration: 9s;"></div>
    </div>

    <nav>
        <a href="index.php" class="logo">LED Matrix</a>
        <ul>
            <li><a href="public.php" style="color: #2196F3;">Gallery</a></li>
            <li><a href="editor.php">Editor</a></li>
            <li id="authLink"><a href="login.php" class="btn-nav">Login</a></li>
        </ul>
    </nav>

    <div class="container">
        <div class="hero" style="padding: 20px 0;">
            <h1>Public Gallery</h1>
            <p>Explore designs created by the community</p>
        </div>

        <div id="gallery" class="gallery-container">
            <!-- Designs will be loaded here -->
            <div style="color: #888; grid-column: 1/-1; text-align: center;">Loading designs...</div>
        </div>
    </div>

    <script src="background.js"></script>
    <script>
        // Auth check
        fetch('auth.php?action=check')
            .then(res => res.json())
            .then(data => {
                if (data.logged_in) {
                    const authLink = document.getElementById('authLink');
                    authLink.innerHTML = `<a href="#" onclick="logout()" class="btn-nav">Logout</a>`;
                }
            });

        function logout() {
            fetch('auth.php?action=logout', { method: 'POST' })
                .then(() => window.location.reload());
        }

        // Fetch and Render Designs
        async function loadGallery() {
            try {
                const response = await fetch('api.php?type=public');
                const designs = await response.json();
                
                const gallery = document.getElementById('gallery');
                gallery.innerHTML = '';

                if (!designs || designs.length === 0) {
                    gallery.innerHTML = '<div style="color: #888; grid-column: 1/-1; text-align: center;">No public designs found. Be the first to share one!</div>';
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
                                <div class="card-meta">${width}x${height} • ${new Date(design.created_at).toLocaleDateString()}</div>
                            </div>
                            <div class="card-actions">
                                <a href="editor.php?load=${design.id}" class="open-btn">Open in Editor</a>
                            </div>
                        </div>
                    `;
                    
                    gallery.appendChild(card);
                    
                    // Render to canvas
                    renderPreview(design.grid_data, `canvas-${design.id}`, width, height);
                });

            } catch (error) {
                console.error('Error loading gallery:', error);
                document.getElementById('gallery').innerHTML = '<div style="color: red; text-align: center;">Error loading gallery.</div>';
            }
        }

        function renderPreview(gridData, canvasId, width, height) {
            const canvas = document.getElementById(canvasId);
            if (!canvas) return;
            
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0, 0, width, height);
            
            // Fill background with black (not "square lights")
            ctx.fillStyle = '#000000';
            ctx.fillRect(0, 0, width, height);

            // Handle data format (flat array or 2D array)
            let flatData = [];
            if (Array.isArray(gridData)) {
                if (Array.isArray(gridData[0])) {
                    // 2D array
                    flatData = gridData.flat();
                } else {
                    // Flat array
                    flatData = gridData;
                }
            }

            // Draw pixels
            flatData.forEach((val, index) => {
                if (val && val !== 0 && val !== '0') {
                    const x = index % width;
                    const y = Math.floor(index / width);
                    
                    // Convert 1 to red (legacy support) or use color value
                    let color = (val === 1 || val === '1') ? '#ff0000' : val;
                    
                    ctx.fillStyle = color;
                    ctx.fillRect(x, y, 1, 1);
                }
            });
        }

        // Initialize
        loadGallery();

        function filterGallery() {
            const filterValue = document.getElementById('sizeFilter').value;
            const cards = document.querySelectorAll('.design-card');
            
            cards.forEach(card => {
                const size = card.getAttribute('data-size');
                if (filterValue === 'all') {
                    card.style.display = 'flex';
                } else {
                    // Match specific size (assuming square or taking max dimension)
                    // If filter is 16, show sizes around 16 (e.g., 8-16) or exact match
                    // For simplicity, let's do exact match on max dimension
                    if (size == filterValue) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
        }
    </script>
</body>
</html>
