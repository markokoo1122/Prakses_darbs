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

    // Load data onto grid
    function loadGridData(data, width, height) {
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
