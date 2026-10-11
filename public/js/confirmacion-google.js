/** RE-T.2.4, RE-T.5.7/8: solicitar otra prueba de identidad; el servidor verifica el token. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirmar-google]').forEach(boton => {
        const form = boton.closest('form');
        const salida = form.querySelector('[data-google-resultado], [data-identidad-resultado]');
        boton.addEventListener('click', () => {
            const clientId = boton.dataset.googleClient || form.dataset.googleClient;
            form.elements.access_token.value = '';
            if (!window.google?.accounts?.oauth2 || !clientId) {
                salida.textContent = 'No se pudo cargar Google. Vuelve a intentarlo o usa el enlace de restablecimiento.';
                return;
            }
            boton.disabled = true;
            const fallo = () => {
                form.elements.access_token.value = '';
                salida.textContent = 'No se pudo confirmar tu identidad con Google.';
                boton.disabled = false;
            };
            try {
                window.google.accounts.oauth2.initTokenClient({
                    client_id: clientId,
                    scope: 'openid email',
                    error_callback: fallo,
                    callback: resultado => {
                        form.elements.access_token.value = resultado.error ? '' : (resultado.access_token || '');
                        salida.textContent = form.elements.access_token.value ? 'Confirmación recibida. Envía el formulario para verificarla.' : 'No se pudo confirmar tu identidad con Google.';
                        boton.disabled = false;
                    }
                }).requestAccessToken({ prompt: 'select_account' });
            } catch (error) {
                fallo();
            }
        });
    });
});
