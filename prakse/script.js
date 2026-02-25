document.addEventListener('DOMContentLoaded', () => {
    const grid = document.getElementById('ledGrid');
    const colorPicker = document.getElementById('colorPicker');
    const clearBtn = document.getElementById('clearBtn');
    const saveBtn = document.getElementById('saveBtn');
    const designNameInput = document.getElementById('designName');
    const designList = document.getElementById('designList');
    
    // New Inputs
    const gridWidthInput = document.getElementById('gridWidth');
    const gridHeightInput = document.getElementById('gridHeight');
    const resizeBtn = document.getElementById('resizeBtn');
    const imageInput = document.getElementById('imageInput');
    const isPublicInput = document.getElementById('isPublic');
    const tabMy = document.getElementById('tabMy');
    const tabFav = document.getElementById('tabFav');
    const tabPublic = document.getElementById('tabPublic');
    const matrixIpInput = document.getElementById('matrixIp');
    const sendToMatrixBtn = document.getElementById('sendToMatrixBtn');

    let GRID_W = 16;
    let GRID_H = 16;
    let isDrawing = false;
    let currentView = 'my'; // 'my' or 'public'
    let currentTool = 'pencil'; // pencil, eraser, bucket
    let brushSize = 1;

    // Tool Elements
    const pencilBtn = document.getElementById('pencilBtn');
    const eraserBtn = document.getElementById('eraserBtn');
    const bucketBtn = document.getElementById('bucketBtn');
    const sizeBtns = document.querySelectorAll('.size-btn');

    if (matrixIpInput) {
        const savedIp = localStorage.getItem('matrixIp');
        if (savedIp) {
            matrixIpInput.value = savedIp;
        }
    }

    let currentMatrixPayload = null; // Stores uploaded animation data
    let isAnimating = false;
    let animationId = 0; // Unique ID for current animation loop
    let animationTimeout = null;
    let isPreviewAnimating = false;
    let previewTimeout = null;

    // Tool Event Listeners
    pencilBtn.addEventListener('click', () => setTool('pencil'));
    eraserBtn.addEventListener('click', () => setTool('eraser'));
    bucketBtn.addEventListener('click', () => setTool('bucket'));

    sizeBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            sizeBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            brushSize = parseInt(btn.dataset.size);
        });
    });

    function setTool(tool) {
        currentTool = tool;
        pencilBtn.classList.remove('active');
        eraserBtn.classList.remove('active');
        bucketBtn.classList.remove('active');
        
        if (tool === 'pencil') pencilBtn.classList.add('active');
        else if (tool === 'eraser') eraserBtn.classList.add('active');
        else if (tool === 'bucket') bucketBtn.classList.add('active');
    }

    function initGrid(width, height) {
        GRID_W = parseInt(width);
        GRID_H = parseInt(height);
        
        grid.innerHTML = '';
        const container = document.querySelector('.matrix-container');
        const availableWidth = container.clientWidth - 40;
        const availableHeight = container.clientHeight - 40;
        const gap = 1;
        const maxLedW = Math.floor((availableWidth - (GRID_W - 1) * gap) / GRID_W);
        const maxLedH = Math.floor((availableHeight - (GRID_H - 1) * gap) / GRID_H);
        let ledSize = Math.min(maxLedW, maxLedH);
        if (ledSize < 4) ledSize = 4;
        if (ledSize > 30) ledSize = 30;

        grid.style.gridTemplateColumns = `repeat(${GRID_W}, ${ledSize}px)`;
        grid.style.gridTemplateRows = `repeat(${GRID_H}, ${ledSize}px)`;

        const fragment = document.createDocumentFragment();

        for (let i = 0; i < GRID_W * GRID_H; i++) {
            const led = document.createElement('div');
            led.classList.add('led');
            
            led.style.width = `${ledSize}px`;
            led.style.height = `${ledSize}px`;
            
            // Mouse events for drawing
            led.addEventListener('mousedown', (e) => {
                e.preventDefault(); // Prevent drag behavior
                if (currentTool === 'bucket') {
                    floodFill(i);
                } else {
                    isDrawing = true;
                    applyTool(i);
                }
            });
            
            led.addEventListener('mouseenter', () => {
                if (isDrawing) {
                    applyTool(i);
                }
            });

            fragment.appendChild(led);
        }
        grid.appendChild(fragment);
    }

    window.addEventListener('resize', () => {
        const leds = document.querySelectorAll('.led');
        if (!leds.length) return;

        const container = document.querySelector('.matrix-container');
        const availableWidth = container.clientWidth - 40;
        const availableHeight = container.clientHeight - 40;
        const gap = 1;
        const maxLedW = Math.floor((availableWidth - (GRID_W - 1) * gap) / GRID_W);
        const maxLedH = Math.floor((availableHeight - (GRID_H - 1) * gap) / GRID_H);
        let ledSize = Math.min(maxLedW, maxLedH);
        if (ledSize < 4) ledSize = 4;
        if (ledSize > 30) ledSize = 30;

        grid.style.gridTemplateColumns = `repeat(${GRID_W}, ${ledSize}px)`;
        grid.style.gridTemplateRows = `repeat(${GRID_H}, ${ledSize}px)`;

        leds.forEach(led => {
            led.style.width = `${ledSize}px`;
            led.style.height = `${ledSize}px`;
        });
    });

    // Stop drawing when mouse is released anywhere
    document.addEventListener('mouseup', () => {
        isDrawing = false;
    });

    function applyTool(index) {
        // If user manually draws, clear the uploaded animation payload
        currentMatrixPayload = null;
        // Also stop any running animation loop immediately
        isAnimating = false;
        animationId++; // Invalidate loops
        if (animationTimeout) {
            clearTimeout(animationTimeout);
            animationTimeout = null;
        }
        stopPreviewAnimation();
        
        const x = index % GRID_W;
        const y = Math.floor(index / GRID_W);
        const leds = document.querySelectorAll('.led');
        const color = currentTool === 'eraser' ? '#222' : colorPicker.value;
        const isEraser = currentTool === 'eraser';

        // Apply based on brush size
        // Size 1: 1x1, Size 2: 2x2, Size 3: 3x3
        
        let offsetStart = 0;
        let offsetEnd = 0;

        if (brushSize === 2) {
            offsetEnd = 1;
        } else if (brushSize === 3) {
            offsetStart = -1;
            offsetEnd = 1;
        }

        for (let dy = offsetStart; dy <= offsetEnd; dy++) {
            for (let dx = offsetStart; dx <= offsetEnd; dx++) {
                const nx = x + dx;
                const ny = y + dy;

                if (nx >= 0 && nx < GRID_W && ny >= 0 && ny < GRID_H) {
                    const nIndex = ny * GRID_W + nx;
                    const led = leds[nIndex];
                    if (led) {
                        if (isEraser) {
                            led.classList.remove('active');
                            led.style.backgroundColor = '#222';
                            led.style.boxShadow = '';
                        } else {
                            turnOnLed(led, color);
                        }
                    }
                }
            }
        }
    }

    function toggleLed(led) {
        // Deprecated
    }

    function turnOnLed(led, color = null) {
        const finalColor = color || colorPicker.value;
        if (led.style.backgroundColor !== finalColor) {
            led.classList.add('active');
            led.style.backgroundColor = finalColor;
            if (GRID_W > 32) {
                led.style.boxShadow = `0 0 1px ${finalColor}`; 
            } else {
                led.style.boxShadow = `0 0 4px ${finalColor}`;
            }
        }
    }

    function floodFill(startIndex) {
        const leds = document.querySelectorAll('.led');
        const targetLed = leds[startIndex];
        
        // Determine target state
        const isTargetActive = targetLed.classList.contains('active');
        const targetColor = isTargetActive ? targetLed.style.backgroundColor : null;
        
        const replaceColor = colorPicker.value;
        // Check if we are trying to paint same color
        if (isTargetActive && hexToRgb(replaceColor) === targetColor) return;

        // BFS
        const queue = [startIndex];
        const visited = new Set();
        
        while (queue.length > 0) {
            const idx = queue.shift();
            if (visited.has(idx)) continue;
            visited.add(idx);

            const led = leds[idx];
            const isActive = led.classList.contains('active');
            const color = isActive ? led.style.backgroundColor : null;

            let match = false;
            if (!isTargetActive) {
                // Target was OFF, so we fill all connected OFF cells
                if (!isActive) match = true;
            } else {
                // Target was ON with specific color
                if (isActive && color === targetColor) match = true;
            }

            if (match) {
                turnOnLed(led, replaceColor);
                
                const x = idx % GRID_W;
                const y = Math.floor(idx / GRID_W);
                const neighbors = [
                    { nx: x + 1, ny: y },
                    { nx: x - 1, ny: y },
                    { nx: x, ny: y + 1 },
                    { nx: x, ny: y - 1 }
                ];

                neighbors.forEach(n => {
                    if (n.nx >= 0 && n.nx < GRID_W && n.ny >= 0 && n.ny < GRID_H) {
                        const nIdx = n.ny * GRID_W + n.nx;
                        if (!visited.has(nIdx)) queue.push(nIdx);
                    }
                });
            }
        }
    }

    function clearGrid() {
        const leds = document.querySelectorAll('.led');
        leds.forEach(led => {
            led.classList.remove('active');
            led.style.backgroundColor = '#222';
            led.style.boxShadow = '';
        });
    }

    // Convert grid to array for saving
    function getGridData() {
        const leds = document.querySelectorAll('.led');
        const data = [];
        let row = [];
        
        leds.forEach((led, index) => {
            if (led.classList.contains('active')) {
                row.push(rgbToHex(led.style.backgroundColor) || colorPicker.value); 
            } else {
                row.push(0);
            }
            
            if ((index + 1) % GRID_W === 0) {
                data.push(row);
                row = [];
            }
        });
        return data;
    }

    // Helper to get grid as flat array of integers (0xRRGGBB)
    function getGridDataAsFlatInts() {
        const leds = document.querySelectorAll('.led');
        const data = [];
        
        leds.forEach(led => {
            let color = 0;
            if (led.classList.contains('active')) {
                const hex = led.style.backgroundColor;
                // Convert rgb(r, g, b) or #hex to integer
                if (hex.startsWith('rgb')) {
                    const rgb = hex.match(/\d+/g);
                    if (rgb) {
                        color = (parseInt(rgb[0]) << 16) | (parseInt(rgb[1]) << 8) | parseInt(rgb[2]);
                    }
                } else if (hex.startsWith('#')) {
                    color = parseInt(hex.slice(1), 16);
                }
            }
            data.push(color);
        });
        return data;
    }

    // Load data onto grid
    function loadGridData(data, width, height) {
        currentMatrixPayload = null; // Clear any uploaded animation
        // Resize if needed
        if (width && height && (width !== GRID_W || height !== GRID_H)) {
            gridWidthInput.value = width;
            gridHeightInput.value = height;
            initGrid(width, height);
        } else {
            clearGrid();
        }

        const leds = document.querySelectorAll('.led');
        
        // Handle if data is flat or 2D
        let flatData = data.flat ? data.flat() : data; 
        
        flatData.forEach((val, index) => {
            if (val !== 0 && val !== '0' && val !== null) {
                const led = leds[index];
                if (led) {
                    led.classList.add('active');
                    const color = (val === 1 || val === '1') ? '#ff0000' : val;
                    led.style.backgroundColor = color;
                    if (GRID_W > 32) {
                        led.style.boxShadow = `0 0 1px ${color}`;
                    } else {
                        led.style.boxShadow = `0 0 4px ${color}`;
                    }
                }
            }
        });
    }

    // Helper to convert RGB to Hex
    function rgbToHex(rgb) {
        if (!rgb || rgb === 'rgba(0, 0, 0, 0)') return null;
        if (rgb.startsWith('#')) return rgb;
        
        const rgbValues = rgb.match(/\d+/g);
        if (!rgbValues) return null;
        
        return "#" + 
            ("0" + parseInt(rgbValues[0], 10).toString(16)).slice(-2) +
            ("0" + parseInt(rgbValues[1], 10).toString(16)).slice(-2) +
            ("0" + parseInt(rgbValues[2], 10).toString(16)).slice(-2);
    }
    
    function hexToRgb(hex) {
        // Expand shorthand form (e.g. "03F") to full form (e.g. "0033FF")
        var shorthandRegex = /^#?([a-f\d])([a-f\d])([a-f\d])$/i;
        hex = hex.replace(shorthandRegex, function(m, r, g, b) {
            return r + r + g + g + b + b;
        });

        var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? `rgb(${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)})` : null;
    }

    // Image Import Logic
    imageInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        
        uploadMediaForBoard(file);

        const reader = new FileReader();
        reader.onload = (event) => {
            const img = new Image();
            img.onload = () => {
                // Create off-screen canvas
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Set canvas size to current grid size (downsample)
                canvas.width = GRID_W;
                canvas.height = GRID_H;
                
                // Draw image to canvas
                ctx.drawImage(img, 0, 0, GRID_W, GRID_H);
                
                // Get pixel data
                const imageData = ctx.getImageData(0, 0, GRID_W, GRID_H).data;
                
                // Clear current grid
                clearGrid();
                const leds = document.querySelectorAll('.led');
                
                for (let i = 0; i < leds.length; i++) {
                    const r = imageData[i * 4];
                    const g = imageData[i * 4 + 1];
                    const b = imageData[i * 4 + 2];
                    const a = imageData[i * 4 + 3];
                    
                    if (a > 128) { // Only draw opaque-ish pixels
                        const hex = "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
                        const led = leds[i];
                        led.classList.add('active');
                        led.style.backgroundColor = hex;
                        if (GRID_W > 32) {
                            led.style.boxShadow = `0 0 1px ${hex}`;
                        } else {
                            led.style.boxShadow = `0 0 4px ${hex}`;
                        }
                    }
                }
                imageInput.value = ''; // Reset
            };
            img.src = event.target.result;
        };
        reader.readAsDataURL(file);
    });

    async function uploadMediaForBoard(file) {
        // Clear any previous animation state/payload first
        isAnimating = false;
        animationId++; // Invalidate any running loops
        currentMatrixPayload = null;
        stopPreviewAnimation();
        
        const formData = new FormData();
        formData.append('image', file);
        
        try {
            const response = await fetch('api.php?action=upload_media', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.status === 'success') {
                console.log('Media converted and saved for board use', result);
                
                // Store the full animation payload for sending to matrix
                if (result.frames && result.frames.length > 0) {
                    currentMatrixPayload = {
                        width: result.width || GRID_W,
                        height: result.height || GRID_H,
                        frames: result.frames,
                        frame_delay: result.frame_delay || 80
                    };
                    // Auto-start preview
                    if (result.frames.length > 1) {
                        if (typeof startPreviewAnimation === 'function') {
                             startPreviewAnimation(currentMatrixPayload);
                        } else {
                            console.warn('startPreviewAnimation not defined');
                        }
                    }
                }
                
                alert('Image/GIF processed successfully! Click "Send to Matrix" to display it.');
            } else if (result.status === 'error' && result.message === 'Unauthorized') {
                console.warn('Login required to save media conversions');
                alert('Please log in to save converted GIF/image code for the LED board.');
            } else {
                console.error('Error saving media conversion', result);
                alert('There was an error converting your image/GIF for the LED board.\n\n' +
                      'Details: ' + (result.message || 'Unknown error. ' +
                      'Very large images or GIFs with many frames can fail. Try a smaller file.'));
            }
        } catch (err) {
            console.error('Error uploading media for conversion', err);
            alert('Could not contact the server to convert your image/GIF. Check that the server is running.');
        }
    }
    
    function startPreviewAnimation(payload) {
        stopPreviewAnimation();
        const width = Math.min(payload.width || GRID_W, 64);
        const height = Math.min(payload.height || GRID_H, 64);
        const frames = payload.frames || [];
        const delay = payload.frame_delay || 80;
        if (!frames.length) return;
        
        isPreviewAnimating = true;
        let idx = 0;
        
        const tick = () => {
            if (!isPreviewAnimating) return;
            // Draw frame to grid
            drawFrameOnGrid(frames[idx], width, height);
            idx = (idx + 1) % frames.length;
            previewTimeout = setTimeout(tick, delay);
        };
        tick();
    }
    
    function stopPreviewAnimation() {
        isPreviewAnimating = false;
        if (previewTimeout) {
            clearTimeout(previewTimeout);
            previewTimeout = null;
        }
    }
    
    function drawFrameOnGrid(frameInts, width, height) {
        // If grid size mismatch, re-init (optional, but good for safety)
        if (width !== GRID_W || height !== GRID_H) {
            initGrid(width, height);
        }
        
        const leds = document.querySelectorAll('.led');
        for (let i = 0; i < leds.length; i++) {
            if (i >= frameInts.length) break;
            const colorInt = frameInts[i];
            const led = leds[i];
            
            if (colorInt === 0) {
                led.classList.remove('active');
                led.style.backgroundColor = '#222';
                led.style.boxShadow = '';
            } else {
                // int to hex
                const r = (colorInt >> 16) & 0xFF;
                const g = (colorInt >> 8) & 0xFF;
                const b = colorInt & 0xFF;
                const hex = "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
                
                led.classList.add('active');
                led.style.backgroundColor = hex;
                if (GRID_W > 32) {
                    led.style.boxShadow = `0 0 1px ${hex}`;
                } else {
                    led.style.boxShadow = `0 0 4px ${hex}`;
                }
            }
        }
    }

    function frameIntsToRGBBytes(frame, width, height) {
        const total = width * height;
        const arr = new Uint8Array(total * 3);
        for (let i = 0; i < total; i++) {
            const c = frame[i] || 0;
            arr[i*3+0] = (c >> 16) & 0xFF;
            arr[i*3+1] = (c >> 8) & 0xFF;
            arr[i*3+2] = c & 0xFF;
        }
        return arr;
    }

    async function sendB64Frame(ip, width, height, rgbBytes, overlay=false) {
        const b64 = btoa(String.fromCharCode.apply(null, rgbBytes));
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'proxy_b64',
                ip: ip,
                width: width,
                height: height,
                data: b64,
                overlay: overlay ? 1 : 0
            })
        });
        return response.json();
    }
    
    async function beginFrame(ip, width, height) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'proxy_begin_frame',
                ip: ip,
                width: width,
                height: height
            })
        });
        return response.json();
    }
    
    async function sendRowB64(ip, width, height, rowIndex, rowBytes) {
        const b64 = btoa(String.fromCharCode.apply(null, rowBytes));
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'proxy_b64_row',
                ip: ip,
                width: width,
                height: height,
                row: rowIndex,
                data: b64
            })
        });
        return response.json();
    }
    
    async function sendRowsB64(ip, width, height, start, count, bytes) {
        const b64 = btoa(String.fromCharCode.apply(null, bytes));
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'proxy_b64_rows',
                ip: ip,
                width: width,
                height: height,
                start: start,
                count: count,
                data: b64
            })
        });
        return response.json();
    }
    
    async function endFrame(ip) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'proxy_end_frame',
                ip: ip
            })
        });
        return response.json();
    }
    
    async function sendFrameFullBase64(ip, width, height, rgbBytes, overlay=false) {
        const r = await sendB64Frame(ip, width, height, rgbBytes, overlay);
        return r;
    }

    async function checkMatrix(ip) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'proxy_health',
                ip: ip
            })
        });
        return response.json();
    }

    async function sendCurrentToMatrix() {
        if (!matrixIpInput) {
            alert('Matrix IP input not found on this page.');
            return;
        }
        const ip = matrixIpInput.value.trim();
        if (!ip) {
            alert('Please enter the Matrix IP address.');
            return;
        }
        localStorage.setItem('matrixIp', ip);

        let payload = currentMatrixPayload ? currentMatrixPayload : {
            width: GRID_W,
            height: GRID_H,
            frames: [getGridDataAsFlatInts()],
            frame_delay: 80
        };

        try {
            const health = await checkMatrix(ip);
            if (!health || health.status !== 'success') {
                alert('Matrix not reachable. Check WiFi or IP.');
                return;
            }
            const width = Math.min(payload.width || GRID_W, 64);
            const height = Math.min(payload.height || GRID_H, 64);
            const frames = payload.frames || [];
            const delay = payload.frame_delay || 80;
            // Force chunked mode for all transmissions to avoid large body issues on ESP32
            const useFullFrame = false; 
            
            // Animation loop function
            const animate = async () => {
                if (!isAnimating) return; // Stop if flag cleared
                
                for (let i = 0; i < frames.length; i++) {
                    if (!isAnimating) break; // Check flag inside loop
                    
                    const rgbBytes = frameIntsToRGBBytes(frames[i], width, height);
                    if (useFullFrame) {
                        const resp = await sendFrameFullBase64(ip, width, height, rgbBytes, i > 0);
                        // Ignore error on first frame if overlay is problematic, or just log
                        if (!resp || (resp.status !== 'success' && resp.status !== 'ok')) {
                            console.warn('Frame ' + i + ' warning:', resp);
                            // Continue anyway to see if next frames work
                        }
                    } else {
                        // Manual chunking
                        const startResp = await beginFrame(ip, width, height);
                        if (!startResp || startResp.status !== 'success') {
                            console.warn('Error starting frame: ' + (startResp && startResp.message ? startResp.message : 'Unknown'));
                            // If begin fails, maybe skip this frame or retry?
                            // For now, continue to next frame to keep loop alive
                        } else {
                            const chunk = 8;
                            for (let start = 0; start < height; start += chunk) {
                                const count = Math.min(chunk, height - start);
                                const base = start * width * 3;
                                const len = count * width * 3;
                                const bytes = rgbBytes.subarray(base, base + len);
                                const r = await sendRowsB64(ip, width, height, start, count, bytes);
                                if (!r || r.status !== 'success') {
                                    console.warn('Error sending rows ' + start + '-' + (start+count-1) + ': ' + (r && r.message ? r.message : 'Unknown'));
                                    // Try to continue
                                }
                            }
                            const done = await endFrame(ip);
                            if (!done || done.status !== 'success') {
                                console.warn('Error finishing frame: ' + (done && done.message ? done.message : 'Unknown'));
                            }
                        }
                    }
                    
                    if (isAnimating) {
                        await new Promise(res => setTimeout(res, delay));
                    }
                }
                
                // Recursively call animate if still enabled
                if (isAnimating) {
                    animate();
                }
            };
            
            // Start the loop
            isAnimating = true;
            animate();
            
            alert('Animation started on matrix! (Looping)');
        } catch (err) {
            console.error('Error sending to matrix', err);
            alert('Could not reach the server proxy.\n' +
                  'Make sure the matrix is powered and connected to the same network.');
        }
    }

    // API Functions
    async function fetchDesigns(type = 'my') {
        currentView = type;
        
        // Reset tabs
        tabMy.style.background = '#333';
        tabFav.style.background = '#333';
        tabPublic.style.background = '#333';
        
        // Highlight active
        if (type === 'my') tabMy.style.background = '#2196F3';
        else if (type === 'favorites') tabFav.style.background = '#2196F3';
        else tabPublic.style.background = '#2196F3';

        designList.innerHTML = '<div class="design-item">Loading...</div>';
        
        try {
            const response = await fetch(`api.php?type=${type}`);
            const designs = await response.json();
            
            if (response.status === 401 && type === 'my') {
                designList.innerHTML = '<div class="design-item">Please Login to view your designs</div>';
                return;
            }
            
            renderDesignList(designs);
        } catch (error) {
            console.error('Error fetching designs:', error);
            designList.innerHTML = '<div class="design-item">Error loading designs</div>';
        }
    }

    async function saveDesign() {
        const name = designNameInput.value.trim();
        if (!name) {
            alert('Please enter a design name');
            return;
        }

        const gridData = getGridData();
        const isPublic = isPublicInput.checked ? 1 : 0;
        
        try {
            const response = await fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    name: name,
                    grid_data: gridData,
                    width: GRID_W,
                    height: GRID_H,
                    is_public: isPublic
                })
            });
            
            const result = await response.json();
            if (result.status === 'success') {
                alert('Design saved!');
                designNameInput.value = '';
                if (currentView === 'my') fetchDesigns('my');
            } else if (result.status === 'error' && result.message === 'Unauthorized') {
                alert('Please login to save designs');
            } else {
                alert('Error saving: ' + result.message);
            }
        } catch (error) {
            console.error('Error saving design:', error);
            alert('Error saving design');
        }
    }

    function renderDesignList(designs) {
        designList.innerHTML = '';
        if (!designs || designs.length === 0) {
            designList.innerHTML = '<div style="padding:10px; color:#aaa;">No designs found</div>';
            return;
        }

        designs.forEach(design => {
            const div = document.createElement('div');
            div.classList.add('design-item');
            
            let dimInfo = '';
            if (design.width && design.height) {
                dimInfo = `<small style="color:#888;">${design.width}x${design.height}</small>`;
            }
            
            div.innerHTML = `
                <div style="flex:1;">
                    <span>${design.name}</span> <br>
                    ${dimInfo}
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end;">
                    <small>${new Date(design.created_at).toLocaleDateString()}</small>
                    ${currentView === 'public' ? `<button class="fav-btn" style="padding:2px 5px; margin-top:5px; width:auto; font-size:12px;">★ Save</button>` : ''}
                </div>
            `;
            
            // Load on click (except if clicking button)
            div.addEventListener('click', (e) => {
                if (e.target.classList.contains('fav-btn')) return;
                loadGridData(design.grid_data, design.width, design.height);
            });

            // Fav button click
            const favBtn = div.querySelector('.fav-btn');
            if (favBtn) {
                favBtn.addEventListener('click', async () => {
                    try {
                        const res = await fetch('api.php', {
                            method: 'POST',
                            body: JSON.stringify({ action: 'favorite', design_id: design.id })
                        });
                        const data = await res.json();
                        alert(data.message);
                    } catch (err) {
                        alert('Error saving favorite');
                    }
                });
            }

            designList.appendChild(div);
        });
    }

    // Event Listeners
    clearBtn.addEventListener('click', clearGrid);
    saveBtn.addEventListener('click', saveDesign);
    resizeBtn.addEventListener('click', () => initGrid(gridWidthInput.value, gridHeightInput.value));
    if (sendToMatrixBtn) {
        sendToMatrixBtn.addEventListener('click', sendCurrentToMatrix);
    }
    
    tabMy.addEventListener('click', () => fetchDesigns('my'));
    tabFav.addEventListener('click', () => fetchDesigns('favorites'));
    tabPublic.addEventListener('click', () => fetchDesigns('public'));

    // Initial setup
    initGrid(16, 16);
    fetchDesigns('my'); // Try to load user designs first

    // Check for load parameter
    const urlParams = new URLSearchParams(window.location.search);
    const loadId = urlParams.get('load');
    if (loadId) {
        fetch(`api.php?id=${loadId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'error') {
                    alert(data.message);
                } else {
                    designNameInput.value = data.name;
                    loadGridData(data.grid_data, data.width, data.height);
                    // Also switch to Public view if not my design, but for now just load it
                }
            })
            .catch(err => console.error('Error loading design:', err));
    }
});
