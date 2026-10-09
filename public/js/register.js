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
                Swal.fire({
                    icon: 'error',
                    title: 'No pudimos crear la cuenta',
                    text: datos.message || 'Intenta de nuevo en unos minutos.',
                    confirmButtonColor: '#0052FF'
                });
                return;
            }

            if (datos.esperando_confirmacion) {
                mostrarEspera(datos.email);
            } else {
                // HU-5.8: el correo ya tenía cuenta. O se le envió la
                // confirmación del vínculo, o ya estaba vinculado y solo
                // tiene que iniciar sesión.
                restaurarBoton();
                await Swal.fire({
                    icon: datos.iniciar_sesion ? 'info' : 'success',
                    text: datos.message,
                    confirmButtonColor: '#0052FF'
                });
                window.location.href = 'index.php?action=login';
            }
        } catch (e) {
            restaurarBoton();
            Swal.fire({
                icon: 'error',
                title: 'Sin conexión',
                text: 'No pudimos comunicarnos con el servidor. Revisa tu conexión e intenta de nuevo.',
                confirmButtonColor: '#0052FF'
            });
        }
    }

    // ── Pantalla de espera + sondeo de la confirmación ──
    function mostrarEspera(email) {
        const espera = document.getElementById('registroEspera');
        if (!espera) {                       // vista sin la pantalla: se cae al login
            window.location.href = 'index.php?action=login';
            return;
        }

        const correo = document.getElementById('registroEsperaEmail');
        if (correo) correo.textContent = email || '';

        registerForm.hidden = true;
        espera.hidden = false;

        const volver = document.getElementById('registroEsperaVolver');
        if (volver) {
            volver.addEventListener('click', function () {
                window.location.href = 'index.php?action=login';
            });
        }

        sondearConfirmacion();
    }

    function sondearConfirmacion() {
        const estado = document.getElementById('registroEsperaEstado');
        const INTERVALO = 4000;              // cada 4 s
        const LIMITE = 15 * 60 * 1000;       // se rinde a los 15 min
        const inicio = Date.now();

        async function consultar() {
            if (Date.now() - inicio > LIMITE) {
                if (estado) {
                    estado.textContent = 'Seguimos esperando. Abre el enlace del correo y vuelve a iniciar sesión.';
                    estado.className = 'registro-espera-estado';
                }
                return;
            }

            try {
                const r = await fetch('index.php?action=estado_verificacion_ajax', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: 'no-store'
                });
                const d = await r.json();

                if (d.estado === 'confirmado') {
                    if (estado) {
                        estado.textContent = '¡Correo confirmado! Entrando a tu portal...';
                        estado.className = 'registro-espera-estado ok';
                    }
                    setTimeout(function () {
                        window.location.href = d.redirect || 'index.php?action=portal_propietario';
                    }, 900);
                    return;
                }

                if (d.estado === 'expirado' || d.estado === 'sin_registro') {
                    if (estado) {
                        estado.textContent = 'La confirmación ya no está disponible. Inicia sesión para continuar.';
                        estado.className = 'registro-espera-estado error';
                    }
                    return;
                }
            } catch (e) {
                // Un fallo puntual de red no debe cortar la espera.
            }

            setTimeout(consultar, INTERVALO);
        }

        setTimeout(consultar, INTERVALO);
    }
});
