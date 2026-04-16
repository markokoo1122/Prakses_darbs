(function () {
    const canvas = document.createElement('canvas');
    canvas.id = 'matrix-bg';
    document.body.prepend(canvas);

    const ctx = canvas.getContext('2d');

    const old = document.querySelector('.bg-animation');
    if (old) old.remove();

    // Color palette: LED pixel colors — heavily blue/deep-blue, occasional accent
    const PALETTE = [
        { rgb: [20,  80, 255], weight: 5 },  // electric blue
        { rgb: [0,  140, 255], weight: 4 },  // sky blue
        { rgb: [60,  20, 220], weight: 3 },  // deep indigo
        { rgb: [0,  220, 255], weight: 2 },  // cyan
        { rgb: [120, 60, 255], weight: 2 },  // violet
        { rgb: [200,200, 255], weight: 2 },  // cold white
        { rgb: [0,  255, 160], weight: 1 },  // teal (rare)
        { rgb: [255, 30,  60], weight: 1 },  // red (rare)
        { rgb: [255,180,   0], weight: 0.4}, // amber (very rare)
    ];

    const TOTAL_WEIGHT = PALETTE.reduce((s, c) => s + c.weight, 0);

    function pickColor() {
        let r = Math.random() * TOTAL_WEIGHT;
        for (const c of PALETTE) { r -= c.weight; if (r <= 0) return c.rgb; }
        return PALETTE[0].rgb;
    }

    let W = 0, H = 0, particles = [];

    function makeParticle() {
        return {
            x:        Math.random() * W,
            y:        Math.random() * H,
            size:     Math.pow(Math.random(), 2) * 6 + 0.8,  // core half-size
            color:    pickColor(),
            phase:    Math.random() * Math.PI * 2,
            freq:     Math.random() * 0.18 + 0.05,
            maxAlpha: Math.random() * 0.45 + 0.12,
            driftX:   (Math.random() - 0.5) * 0.06,
            driftY:   (Math.random() - 0.5) * 0.06,
        };
    }

    function resize() {
        W = canvas.width  = window.innerWidth;
        H = canvas.height = window.innerHeight;
        const target = Math.min(Math.floor(W * H / 7000), 140);
        while (particles.length < target) particles.push(makeParticle());
        if (particles.length > target) particles.length = target;
    }

    // Draw a square LED pixel with a square-bounded soft glow
    function drawSquareLed(x, y, size, r, g, b, a) {
        const glow = size * 8;

        // Outer glow — radial gradient clipped to square bounds
        const gr = ctx.createRadialGradient(x, y, 0, x, y, glow);
        gr.addColorStop(0,    `rgba(${r},${g},${b},${(a).toFixed(3)})`);
        gr.addColorStop(0.3,  `rgba(${r},${g},${b},${(a * 0.5).toFixed(3)})`);
        gr.addColorStop(1,    `rgba(${r},${g},${b},0)`);
        ctx.fillStyle = gr;
        ctx.fillRect(x - glow, y - glow, glow * 2, glow * 2);  // square, not arc

        // Bright core pixel — sharp square center
        const core = Math.max(1, size * 0.7);
        ctx.fillStyle = `rgba(${r},${g},${b},${Math.min(1, a * 1.8).toFixed(3)})`;
        ctx.fillRect(x - core, y - core, core * 2, core * 2);
    }

    // Occasional flash particles
    const flashes = [];
    function maybeSpawnFlash(now) {
        if (Math.random() < 0.004) {
            flashes.push({
                x:    Math.random() * W,
                y:    Math.random() * H,
                size: Math.random() * 4 + 1,
                rgb:  pickColor(),
                born: now,
                life: Math.random() * 0.6 + 0.3,
            });
        }
    }

    function draw(ts) {
        const now = ts * 0.001;
        ctx.clearRect(0, 0, W, H);

        maybeSpawnFlash(now);

        for (const p of particles) {
            const a = p.maxAlpha * (0.5 + 0.5 * Math.sin(2 * Math.PI * p.freq * now + p.phase));

            p.x += p.driftX;
            p.y += p.driftY;
            if (p.x < -20) p.x = W + 20;
            if (p.x > W + 20) p.x = -20;
            if (p.y < -20) p.y = H + 20;
            if (p.y > H + 20) p.y = -20;

            if (a < 0.004) continue;
            const [r, g, b] = p.color;
            drawSquareLed(p.x, p.y, p.size, r, g, b, a);
        }

        for (let i = flashes.length - 1; i >= 0; i--) {
            const f = flashes[i];
            const age = now - f.born;
            if (age > f.life) { flashes.splice(i, 1); continue; }

            const t = age / f.life;
            const a = t < 0.2 ? (t / 0.2) * 0.7 : 0.7 * (1 - (t - 0.2) / 0.8);
            const [r, g, b] = f.rgb;
            drawSquareLed(f.x, f.y, f.size, r, g, b, a);
        }

        requestAnimationFrame(draw);
    }

    resize();
    window.addEventListener('resize', resize);
    requestAnimationFrame(draw);
})();
