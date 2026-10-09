/** D2: crear/restablecer/cambiar contraseña, sin código en línea ni avisos de campo modales. */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-accion]').forEach(form => {
        form.addEventListener('submit', async evento => {
            evento.preventDefault();
            const boton = evento.submitter || form.querySelector('button[type="submit"]');
            const salida = form.querySelector('[data-password-resultado]');
            boton.disabled = true;
            try {
                const cuerpo = new FormData(form);
                if (form.id === 'portalChangePasswordForm') {
                    cuerpo.set('nueva_password', cuerpo.get('new_password'));
                    cuerpo.set('password_actual', cuerpo.get('current_password') || '');
                }
                const respuesta = await fetch('index.php?action=' + form.dataset.passwordAccion, { method: 'POST', body: cuerpo });
                const datos = await respuesta.json();
                salida.textContent = datos.message;
                if (datos.success) {
                    if (form.id === 'portalChangePasswordForm') {
                        if (!form.elements.current_password) {
                            window.zookiRecargarConAviso('Contraseña creada.');
                        } else {
                            form.reset();
                        }
                    } else {
                        window.location.href = 'index.php?action=' + (form.dataset.passwordAccion === 'cambiar_password_ajax' ? 'dashboard' : 'login');
                    }
                }
            } catch (error) {
                salida.textContent = 'No se pudo completar el cambio. Inténtalo nuevamente.';
            } finally {
                boton.disabled = false;
            }
        });
    });
    document.querySelectorAll('[data-ojito], .toggle-password').forEach(boton => {
        boton.addEventListener('click', () => {
            const campo = boton.dataset.ojito ? document.getElementById(boton.dataset.ojito) : boton.parentElement.querySelector('input');
            if (!campo) return;
            const visible = campo.type === 'password';
            campo.type = visible ? 'text' : 'password';
            boton.setAttribute('aria-pressed', String(visible));
        });
    });
});
