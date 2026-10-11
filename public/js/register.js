document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const documentoInput = document.getElementById('documento_reg'); // ID updated in login.php
    const passwordInput = document.getElementById('password_reg');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const emailInput = document.getElementById('email_reg');

    // Este script es de la cara de registro de login.php (D1: la única página de registro).
    if (!registerForm || !documentoInput || !passwordInput || !emailInput) return;

    const submitBtn = registerForm.querySelector('button[type="submit"]');


    // D2: validacion-cuenta.js intercepta primero el envío y muestra los errores en cada campo.
    registerForm.addEventListener('submit', event => {
        event.preventDefault();
        submitBtn.disabled = true;
        submitBtn.textContent = 'Creando cuenta…';
        enviarRegistro();
    });
    [
        ['togglePasswordReg', passwordInput],
        ['toggleConfirmPasswordReg', confirmPasswordInput]
    ].forEach(([id, campo]) => {
        document.getElementById(id)?.addEventListener('click', () => {
            campo.type = campo.type === 'password' ? 'text' : 'password';
        });
    });

    function restaurarBoton() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Registrarse</span>';
    }

    async function enviarRegistro() {
        try {
            const respuesta = await fetch('index.php?action=process_register', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(registerForm)
            });

            // Si el servidor respondiera HTML (por ejemplo tras expirar la
            // sesión), se recarga en vez de fallar en silencio.
            const tipo = respuesta.headers.get('content-type') || '';
            if (!tipo.includes('application/json')) {
                window.location.href = 'index.php?action=login';
                return;
            }

            const datos = await respuesta.json();

            if (!datos.success) {
                restaurarBoton();
                const salida = document.getElementById('registroFormResultado');
                if (salida) salida.textContent = datos.message || 'No se pudo completar la solicitud.';
                return;
            }

            restaurarBoton();
            mostrarEspera(datos.message);
        } catch (e) {
            restaurarBoton();
            const salida = document.getElementById('registroFormResultado');
            if (salida) salida.textContent = 'No pudimos comunicarnos con el servidor. Inténtalo nuevamente.';
        } finally {
            // RE-5.8.6: Cloudflare consume el token incluso si otro campo fue rechazado.
            window.ZookiTurnstile?.reiniciar(registerForm);
        }
    }

    // RE-T.13.2: todos los registros muestran la misma pantalla, sin sondeo de identidad.
    function mostrarEspera(mensaje) {
        const espera = document.getElementById('registroEspera');
        if (!espera) {                       // vista sin la pantalla: se cae al login
            window.location.href = 'index.php?action=login';
            return;
        }

        const estado = document.getElementById('registroEsperaEstado');
        if (estado) estado.textContent = mensaje;

        registerForm.hidden = true;
        espera.hidden = false;

        const volver = document.getElementById('registroEsperaVolver');
        if (volver) {
            volver.addEventListener('click', function () {
                window.location.href = 'index.php?action=login';
            });
        }

    }

});
