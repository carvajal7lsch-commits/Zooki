/** C9: dibujo común de los indicadores administrativos. */
function dibujarIndicador(elementId, value, max, color) {
    const container = document.getElementById(elementId);
    if(!container) return;

    if (max === 0) max = 1;
    const normalizedHeight = 28 - ((value / max) * 15); // Y bounds: 28 to 13 (softer curve)
    
    let pathData;
    if (value === 0) {
        // Línea plana abajo si el valor es cero
        pathData = `M0,28 L25,28 L50,28 L75,28 L100,28`;
    } else {
        // La altura máxima de los puntos intermedios debe ser proporcional al valor final
        const heightDiff = 28 - normalizedHeight;
        
        // Puntos aleatorios pero escalados al tamaño del dato para mantener coherencia visual
        const p1 = 28 - Math.random() * (heightDiff * 0.5);
        const p2 = 28 - Math.random() * (heightDiff * 1.2); // Permite un pequeño pico antes del final
        const p3 = 28 - Math.random() * (heightDiff * 0.8);
        
        pathData = `M0,28 L25,${p1} L50,${p2} L75,${p3} L100,${normalizedHeight}`;
    }
    
    const fillPath = `${pathData} L100,30 L0,30 Z`;
    
    container.innerHTML = `
        <defs>
            <linearGradient id="grad-${elementId}" x1="0%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="${color}" stop-opacity="0.15" />
                <stop offset="100%" stop-color="${color}" stop-opacity="0" />
            </linearGradient>
        </defs>
        <path d="${pathData}" fill="none" stroke="${color}" stroke-width="2" vector-effect="non-scaling-stroke"></path>
        <path d="${fillPath}" fill="url(#grad-${elementId})" stroke="none"></path>
        <circle cx="100" cy="${normalizedHeight}" r="2.5" fill="${color}" />
    `;
}
