/**
 * Extras: Notificaciones Push del Navegador
 */

// ── NOTIFICACIONES PUSH ─────────────────────────────────
(function(){
    if (!('Notification' in window)) return;

    function requestPermission() {
        if (Notification.permission === 'default') {
            Notification.requestPermission().then(perm => {
                if (perm === 'granted') {
                    new Notification('Zooki', {
                        body: 'Notificaciones activadas. Recibirás alertas de citas y vacunas.',
                        icon: '../public/img/favicon.png'
                    });
                }
            });
        }
    }

    // Solicitar permiso al cargar (solo si aún no se ha decidido)
    setTimeout(requestPermission, 3000);

    // Notificar si hay citas hoy (solo si el usuario está en la app)
    function checkCitasHoy() {
        if (Notification.permission !== 'granted') return;
        fetch('index.php?action=get_pendientes_ajax')
            .then(r => r.json())
            .then(data => {
                if (data.success && data.pendientes && data.pendientes.citas_hoy && data.pendientes.citas_hoy.length > 0) {
                    const count = data.pendientes.citas_hoy.length;
                    new Notification('Zooki - Citas de hoy', {
                        body: `Tienes ${count} cita${count > 1 ? 's' : ''} programada${count > 1 ? 's' : ''} para hoy.`,
                        icon: '../public/img/favicon.png',
                        tag: 'citas-hoy'
                    });
                }
            })
            .catch(() => {});
    }

    // Verificar citas cada 30 min si la pestaña está activa
    let lastCheck = 0;
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - lastCheck > 30 * 60 * 1000) {
            lastCheck = Date.now();
            checkCitasHoy();
        }
    });
})();
