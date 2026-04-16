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
    const tabMy     = document.getElementById('tabMy');
    const tabFav    = document.getElementById('tabFav');
    const tabGifs   = document.getElementById('tabGifs');
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
            led.style.backgroundColor = '';
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
        animationId++;
        const thisUploadId = animationId; // capture before any await — used to detect stale responses
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

            // If a newer upload started while this one was in-flight, discard this response
            // entirely — applying it would overwrite the new upload's grid state
            if (animationId !== thisUploadId) return;

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
            const led = leds[i];
            if (i >= frameInts.length || frameInts[i] === 0) {
                led.classList.remove('active');
                led.style.backgroundColor = '';
                led.style.boxShadow = '';
                continue;
            }
            const colorInt = frameInts[i];
            const r = (colorInt >> 16) & 0xFF;
            const g = (colorInt >> 8) & 0xFF;
            const b = colorInt & 0xFF;
            const hex = "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
            led.classList.add('active');
            led.style.backgroundColor = hex;
            led.style.boxShadow = GRID_W > 32 ? `0 0 1px ${hex}` : `0 0 4px ${hex}`;
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
    
    async function beginFrame(ip, width, height, bufIndex = -1) {
        const body = { action: 'proxy_begin_frame', ip: ip, width: width, height: height };
        if (bufIndex >= 0) body.buf_index = bufIndex;
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
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
    
    async function endFrame(ip, bufIndex = -1) {
        const body = { action: 'proxy_end_frame', ip: ip };
        if (bufIndex >= 0) body.buf_index = bufIndex;
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        return response.json();
    }

    async function animInit(ip, count) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'proxy_anim_init', ip: ip, count: count })
        });
        return response.json();
    }

    async function animPlay(ip, count, delay) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'proxy_anim_play', ip: ip, count: count, delay: delay })
        });
        return response.json();
    }

    async function animStop(ip) {
        const response = await fetch('api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'proxy_anim_stop', ip: ip })
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
        if (!matrixIpInput) { alert('Matrix IP input not found on this page.'); return; }
        const ip = matrixIpInput.value.trim();
        if (!ip) { alert('Please enter the Matrix IP address.'); return; }
        localStorage.setItem('matrixIp', ip);

        let payload = currentMatrixPayload ? currentMatrixPayload : {
            width: GRID_W, height: GRID_H,
            frames: [getGridDataAsFlatInts()],
            frame_delay: 80
        };

        try {
            const health = await checkMatrix(ip);
            if (!health || health.status !== 'success') {
                alert('Matrix not reachable. Check WiFi or IP.');
                return;
            }

            // Stop any JS-driven loop and any autonomous ESP32 animation
            isAnimating = false;
            animationId++;
            if (animationTimeout) { clearTimeout(animationTimeout); animationTimeout = null; }
            await animStop(ip).catch(() => {});

            const width  = Math.min(payload.width  || GRID_W, 64);
            const height = Math.min(payload.height || GRID_H, 64);
            const frames = payload.frames || [];
            const speedSelect = document.getElementById('animSpeed');
            const speedMult   = speedSelect ? parseFloat(speedSelect.value) || 1 : 1;
            // Clamp to 16 ms minimum (~60 fps) so the ESP32 isn't overwhelmed
            const delay = Math.max(16, Math.round((payload.frame_delay || 80) / speedMult));
            const chunk  = 32; // 32 rows × 64px × 3B = ~6 KB base64 per request — safe for ESP32

            // Helper: upload one frame via the chunked protocol
            const uploadFrame = async (frameData, bufIndex) => {
                const startResp = await beginFrame(ip, width, height, bufIndex);
                if (!startResp || startResp.status !== 'success') {
                    console.warn('beginFrame failed for index', bufIndex, startResp);
                    return false;
                }
                const rgbBytes = frameIntsToRGBBytes(frameData, width, height);
                for (let start = 0; start < height; start += chunk) {
                    const count = Math.min(chunk, height - start);
                    const base  = start * width * 3;
                    const bytes = rgbBytes.subarray(base, base + count * width * 3);
                    const r = await sendRowsB64(ip, width, height, start, count, bytes);
                    if (!r || r.status !== 'success') {
                        console.warn('sendRowsB64 failed rows', start, '-', start + count - 1, r);
                    }
                }
                const done = await endFrame(ip, bufIndex);
                if (!done || done.status !== 'success') {
                    console.warn('endFrame failed for index', bufIndex, done);
                }
                return true;
            };

            const overlayChecked = document.getElementById('clockOverlay')?.checked;

            if (frames.length <= 1 && !overlayChecked) {
                // ── Single frame, no overlay: display directly ────────────────────
                await uploadFrame(frames[0] || [], -1);
                alert('Image sent to matrix!');

            } else if (frames.length <= 1 && overlayChecked) {
                // ── Single frame + clock overlay: store as 1-frame animation so
                //    the ESP32 redraws the image every second before painting the clock
                sendToMatrixBtn.disabled = true;
                sendToMatrixBtn.textContent = 'Uploading…';
                const initResp = await animInit(ip, 1);
                if (!initResp || initResp.status !== 'success') {
                    sendToMatrixBtn.disabled = false;
                    sendToMatrixBtn.textContent = 'Send to Matrix';
                    alert('Failed to initialise buffer on matrix.');
                    return;
                }
                await uploadFrame(frames[0] || [], 0);
                await animPlay(ip, 1, 1000);
                sendToMatrixBtn.disabled = false;
                sendToMatrixBtn.textContent = 'Send to Matrix';
                alert('Image sent to matrix!');

            } else {
                // ── Multi-frame GIF: upload all frames into ESP32 RAM once,
                //    then let the ESP32 loop through them autonomously — no more
                //    per-frame HTTP overhead during playback. ──────────────────────
                sendToMatrixBtn.disabled = true;
                sendToMatrixBtn.textContent = 'Uploading 0/' + frames.length + '…';

                const initResp = await animInit(ip, frames.length);
                if (!initResp || initResp.status !== 'success') {
                    sendToMatrixBtn.disabled = false;
                    sendToMatrixBtn.textContent = 'Send to Matrix';
                    alert('Failed to initialise animation buffer on matrix.\nMake sure the firmware is up to date.');
                    return;
                }

                let uploadedCount = 0;
                for (let i = 0; i < frames.length; i++) {
                    sendToMatrixBtn.textContent = 'Uploading ' + (i + 1) + '/' + frames.length + '…';
                    const ok = await uploadFrame(frames[i], i);
                    if (ok) {
                        uploadedCount++;
                    } else {
                        // beginFrame returned OOM (507) — matrix is out of memory.
                        // Play back however many frames fit rather than aborting entirely.
                        console.warn('Frame', i, 'rejected by matrix (OOM). Playing', uploadedCount, 'frames.');
                        break;
                    }
                }

                if (uploadedCount === 0) {
                    sendToMatrixBtn.disabled = false;
                    sendToMatrixBtn.textContent = 'Send to Matrix';
                    alert('Matrix ran out of memory before storing any frames.\nTry a shorter or smaller GIF.');
                    return;
                }

                const playResp = await animPlay(ip, uploadedCount, delay);
                sendToMatrixBtn.disabled = false;
                sendToMatrixBtn.textContent = 'Send to Matrix';

                if (!playResp || playResp.status !== 'success') {
                    alert('Frames uploaded but playback failed to start.');
                } else {
                    const msg = uploadedCount < frames.length
                        ? `Matrix only had room for ${uploadedCount}/${frames.length} frames — playing those.`
                        : `Uploaded ${uploadedCount} frames — playing autonomously on matrix!`;
                    alert(msg);
                }
            }
        } catch (err) {
            console.error('Error sending to matrix', err);
            if (sendToMatrixBtn) { sendToMatrixBtn.disabled = false; sendToMatrixBtn.textContent = 'Send to Matrix'; }
            alert('Could not reach the server proxy.\nMake sure the matrix is powered and on the same network.');
        }
    }

    // API Functions
    async function fetchDesigns(type = 'my') {
        currentView = type;

        // Reset tabs
        tabMy.classList.remove('tab-active');
        tabFav.classList.remove('tab-active');
        if (tabGifs) tabGifs.classList.remove('tab-active');

        // Highlight active
        if (type === 'my') tabMy.classList.add('tab-active');
        else tabFav.classList.add('tab-active');

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

    function parseHex(hex) {
        const n = parseInt(hex.replace('#', ''), 16);
        return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
    }

    // ── GIF Library ──────────────────────────────────────────────────────────

    async function fetchGifs() {
        currentView = 'gifs';
        tabMy.classList.remove('tab-active');
        tabFav.classList.remove('tab-active');
        if (tabGifs) tabGifs.classList.add('tab-active');

        designList.innerHTML = '<div class="design-item">Loading...</div>';
        try {
            const response = await fetch('api.php?type=mygifs');
            if (response.status === 401) {
                designList.innerHTML = '<div class="design-item">Please login to view saved GIFs</div>';
                return;
            }
            const items = await response.json();
            renderGifList(items);
        } catch (err) {
            designList.innerHTML = '<div class="design-item">Error loading GIFs</div>';
        }
    }

    function buildGifCards(items, { canDelete = false, canTogglePublic = false, onAfterDelete = null } = {}) {
        const grid = document.createElement('div');
        grid.classList.add('gif-card-grid');

        items.forEach(item => {
            const card = document.createElement('div');
            card.classList.add('gif-card');
            const shortName = item.original_filename.length > 15
                ? item.original_filename.substring(0, 15) + '…'
                : item.original_filename;
            const typeLabel = item.is_gif == 1 ? `${item.frame_count} frames` : 'Image';
            const isPub = parseInt(item.is_public || 0);
            const byLine = item.username ? `<div class="gif-card-by">by ${item.username}</div>` : '';

            card.innerHTML = `
                <div class="gif-thumb-wrap">
                    ${item.file_path
                        ? `<img src="${item.file_path}" class="gif-thumb" alt="preview">`
                        : `<div class="gif-thumb-placeholder">?</div>`}
                    <div class="gif-thumb-overlay">Load</div>
                </div>
                <div class="gif-card-info">
                    <div class="gif-card-name" title="${item.original_filename}">${shortName}</div>
                    <div class="gif-card-meta">${typeLabel} · ${item.width}×${item.height}</div>
                    ${byLine}
                    <div class="gif-card-actions">
                        ${canTogglePublic ? `<button class="pub-btn${isPub ? ' pub-on' : ''}" title="${isPub ? 'Make private' : 'Share publicly'}">${isPub ? '🌐' : '🔒'}</button>` : ''}
                        ${canDelete ? `<button class="del-btn" title="Delete">✕</button>` : ''}
                    </div>
                </div>
            `;

            card.querySelector('.gif-thumb-wrap').addEventListener('click', () => loadMedia(item.id));

            if (canTogglePublic) {
                card.querySelector('.pub-btn').addEventListener('click', async (e) => {
                    e.stopPropagation();
                    const res = await fetch('api.php', {
                        method: 'POST',
                        body: JSON.stringify({ action: 'toggle_media_public', id: item.id })
                    });
                    const d = await res.json();
                    if (d.status === 'success') fetchGifs();
                });
            }

            if (canDelete) {
                card.querySelector('.del-btn').addEventListener('click', async (e) => {
                    e.stopPropagation();
                    if (!confirm(`Delete "${item.original_filename}"?`)) return;
                    const res = await fetch('api.php', {
                        method: 'POST',
                        body: JSON.stringify({ action: 'delete_media', id: item.id })
                    });
                    const d = await res.json();
                    if (d.status === 'success') (onAfterDelete || fetchGifs)();
                    else alert('Delete failed');
                });
            }

            grid.appendChild(card);
        });

        return grid;
    }

    function renderGifList(items) {
        designList.innerHTML = '';
        if (!items || items.length === 0) {
            designList.innerHTML = '<div style="padding:10px;color:#555;">No saved GIFs yet — upload one first</div>';
            return;
        }
        designList.appendChild(buildGifCards(items, { canDelete: true, canTogglePublic: true }));
    }

    async function loadMedia(id) {
        designList.querySelectorAll('.design-item').forEach(el => el.classList.remove('loading'));
        const clicked = [...designList.querySelectorAll('.design-item')]
            .find(el => el.querySelector('.del-btn') &&
                el.dataset.mediaId == id);

        isAnimating = false;
        animationId++;
        currentMatrixPayload = null;
        stopPreviewAnimation();

        designList.innerHTML = '<div class="design-item">Loading GIF…</div>';

        try {
            const response = await fetch('api.php', {
                method: 'POST',
                body: JSON.stringify({ action: 'load_media', id: id })
            });
            const result = await response.json();

            // Re-render the list so the user can pick another
            fetchGifs();

            if (result.status !== 'success') {
                alert('Error loading GIF: ' + (result.message || 'Unknown error'));
                return;
            }

            if (result.frames && result.frames.length > 0) {
                currentMatrixPayload = {
                    width: result.width,
                    height: result.height,
                    frames: result.frames,
                    frame_delay: result.frame_delay || 80
                };
                drawFrameOnGrid(result.frames[0], result.width, result.height);
                if (result.frames.length > 1) {
                    startPreviewAnimation(currentMatrixPayload);
                }
            }
        } catch (err) {
            fetchGifs();
            alert('Could not load GIF from server.');
        }
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
    if (tabGifs) tabGifs.addEventListener('click', () => fetchGifs());

    const clockOnBtn  = document.getElementById('clockOnBtn');
    const clockOffBtn = document.getElementById('clockOffBtn');
    const tzSelect    = document.getElementById('tzSelect');

    async function sendClockCommand(action) {
        const ip = matrixIpInput ? matrixIpInput.value.trim() : '';
        const tz = tzSelect ? parseInt(tzSelect.value) : 2;
        const overlayEl = document.getElementById('clockOverlay');
        const overlay = overlayEl && overlayEl.checked ? 1 : 0;

        let body;
        if (action === 'proxy_clock_on') {
            const digitHex = document.getElementById('clockColorDigit')?.value || '#ffffff';
            const barHex   = document.getElementById('clockColorBar')?.value   || '#0044ff';
            const d = parseHex(digitHex);
            const b = parseHex(barHex);
            body = { action, ip, tz, overlay, style: 99,
                     dr: d.r, dg: d.g, db: d.b,
                     br: b.r, bg: b.g, bb: b.b };
        } else {
            body = { action, ip };
        }

        try {
            const res = await fetch('api.php', {
                method: 'POST',
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.status !== 'success') alert('Clock command failed: ' + (data.response || 'no response from matrix'));
        } catch (e) {
            alert('Could not reach matrix.');
        }
    }

    if (clockOnBtn)  clockOnBtn.addEventListener('click',  () => sendClockCommand('proxy_clock_on'));
    if (clockOffBtn) clockOffBtn.addEventListener('click', () => sendClockCommand('proxy_clock_off'));

    // Initial setup
    initGrid(16, 16);
    fetchDesigns('my'); // Try to load user designs first

    // Check for load parameter (pixel design)
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
                }
            })
            .catch(err => console.error('Error loading design:', err));
    }

    // Check for gif= parameter (load a saved GIF from public gallery)
    const gifId = urlParams.get('gif');
    if (gifId) {
        loadMedia(parseInt(gifId));
    }
});
