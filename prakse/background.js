document.addEventListener('DOMContentLoaded', () => {
    let bgContainer = document.querySelector('.bg-animation');
    
    // If container doesn't exist, create it
    if (!bgContainer) {
        bgContainer = document.createElement('div');
        bgContainer.className = 'bg-animation';
        document.body.prepend(bgContainer);
    }
    
    bgContainer.innerHTML = '';

    const spacing = 60;
    const rows = Math.ceil(window.innerHeight / spacing);
    const cols = Math.ceil(window.innerWidth / spacing);

    const colors = [
        '#990000ff', '#00ff00', '#0000ff', '#ffff00', '#00ffff', '#810c81ff', 
        '#ff9900', '#9900ff', '#2f8d67ff', '#ff0099', '#ffffff'
    ];

    for (let r = 0; r < rows; r++) {
        for (let c = 0; c < cols; c++) {
            const light = document.createElement('div');
            light.className = 'bg-square';

            const top = r * spacing;
            const left = c * spacing;
            const size = 60;
            const duration = Math.random() * 3 + 3;
            const delay = Math.random() * 5;
            const color = colors[Math.floor(Math.random() * colors.length)];

            light.style.top = `${top}px`;
            light.style.left = `${left}px`;
            light.style.width = `${size}px`;
            light.style.height = `${size}px`;
            light.style.backgroundColor = color;
            light.style.boxShadow = `0 0 6px ${color}, 0 0 12px ${color}`;
            light.style.animationDuration = `${duration}s`;
            light.style.animationDelay = `${delay}s`;
            light.style.opacity = Math.random() * 0.1 + 0.1;

            bgContainer.appendChild(light);
        }
    }
});
