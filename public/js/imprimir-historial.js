/** RE-5.11: abrir el diálogo de impresión al cargar el historial. */
document.addEventListener('error', evento => {
    if (evento.target.matches?.('.historial-avatar img')) evento.target.hidden = true;
}, true);
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.historial-avatar img').forEach(imagen => {
        if (imagen.complete && !imagen.naturalWidth) imagen.hidden = true;
    });
    setTimeout(() => window.print(), 800);
});
