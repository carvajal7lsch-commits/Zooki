/**
 * CSRF Interceptor
 * Lee el token desde una meta tag y lo adjunta a todas las peticiones fetch POST/PUT/DELETE.
 * Requiere: <meta name="csrf-token" content="TOKEN"> en el <head>.
 */
(function() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return;
    const token = meta.getAttribute('content');
    if (!token) return;

    const originalFetch = window.fetch;
    window.fetch = function(url, options) {
        options = options || {};
        const method = (options.method || 'GET').toUpperCase();
        if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
            // C2: la misma carga reenviada conserva su clave, independiente de CSRF.
            if (/[?&]action=(registrar_cita_ajax|portal_agendar_cita_ajax|reprogramar_cita_ajax)(?:&|$)/.test(String(url))
                && (options.body instanceof FormData || options.body instanceof URLSearchParams) && !options.body.has('clave_operacion')) {
                options.body.append('clave_operacion', Array.from(window.crypto.getRandomValues(new Uint8Array(32)), n => n.toString(16).padStart(2, '0')).join(''));
            }
            // Si el body es FormData o URLSearchParams, agregar el token
            if (options.body instanceof FormData) {
                options.body.append('csrf_token', token);
            } else if (options.body instanceof URLSearchParams) {
                options.body.append('csrf_token', token);
            } else if (typeof options.body === 'string') {
                const params = new URLSearchParams(options.body);
                params.append('csrf_token', token);
                options.body = params.toString();
            }
        }
        return originalFetch(url, options);
    };
})();
