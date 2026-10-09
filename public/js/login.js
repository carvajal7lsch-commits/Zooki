/**
 * login.js — Lógica del formulario de inicio de sesión de Zooki.
 * Según ZOOKI_REGLAS.md: CERO JS en línea. Todo va en public/js/.
 */

// ── Lógica de Google Sign-In & One Tap ──
// D1: el client_id llega en data-google-client-id del <body> (sin JS en línea).
const GOOGLE_CLIENT_ID = (document.body && document.body.dataset.googleClientId) || '';

// HU-5.8: si Google se inicia desde el registro, viaja la clínica elegida.
let googleDesdeRegistro = false;

function clinicaDelRegistro() {
    const select = document.getElementById('id_clinica_reg');
    return googleDesdeRegistro && select ? select.value : '';
}

/**
 * Respuesta de google_login_ajax. Con una cuenta existente entra; con un
 * correo nuevo pide clínica y aceptación de la política antes de crear la
 * cuenta (RE-T.18.2).
 */
function responderGoogle(data) {
    if (!data.success) {
        Swal.fire({
            icon: 'error',
            title: 'Error de autenticación',
            text: data.message || 'No se pudo iniciar sesión con Google.',
            confirmButtonColor: '#0052FF'
        });
        return;
    }
    const extra = data.extra || {};
    if (extra.action === 'login') {
        window.location.href = extra.redirect;
    } else if (extra.action === 'aceptar_registro' && typeof window.abrirGoogleModal === 'function') {
        window.abrirGoogleModal(extra.email, extra.id_clinica);
    }
}

window.handleGoogleCredentialResponse = async (response) => {
    try {
        const formData = new FormData();
        // Con initTokenClient recibimos access_token en lugar de credential (JWT).
        formData.append('access_token', response.access_token);
        formData.append('csrf_token', document.querySelector('#loginForm input[name="csrf_token"]').value);
        formData.append('cf-turnstile-response', document.querySelector((googleDesdeRegistro ? '#registerForm' : '#loginForm') + ' input[name="cf-turnstile-response"]')?.value || '');
        formData.append('id_clinica', clinicaDelRegistro());
        const res = await fetch('index.php?action=google_login_ajax', { method: 'POST', body: formData });
        responderGoogle(await res.json());
    } catch (error) {
        console.error('Error validando Google Token:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error de red',
            text: 'Hubo un problema de conexión al validar con Google.',
            confirmButtonColor: '#0052FF'
        });
    }
};

window.handleGoogleOneTapResponse = async (response) => {
    try {
        const formData = new FormData();
        // Con One Tap recibimos credential (JWT) en lugar de access_token.
        formData.append('credential', response.credential);
        formData.append('csrf_token', document.querySelector('#loginForm input[name="csrf_token"]').value);
        formData.append('cf-turnstile-response', document.querySelector('#loginForm input[name="cf-turnstile-response"]')?.value || '');
        const res = await fetch('index.php?action=google_login_ajax', { method: 'POST', body: formData });
        responderGoogle(await res.json());
    } catch (error) {
        console.error('Error validando Google One Tap Token:', error);
    }
};

let googleTokenClient = null;

// Cuando Google termine de cargar, inicializamos los clientes
window.initGoogleAuth = function() {
    console.log("initGoogleAuth convocado por Google Identity Services.");
    if (!GOOGLE_CLIENT_ID) {
        console.error("No se encontró el Client ID de Google en data-google-client-id.");
        return;
    }

    try {
        // Inicializar cliente de Token (para nuestros botones personalizados)
        googleTokenClient = google.accounts.oauth2.initTokenClient({
            client_id: GOOGLE_CLIENT_ID,
            scope: 'email profile openid',
            callback: window.handleGoogleCredentialResponse
        });
        console.log("googleTokenClient inicializado correctamente.");

        // Inicializar cliente de Identity (para Google One Tap)
        google.accounts.id.initialize({
            client_id: GOOGLE_CLIENT_ID,
            callback: window.handleGoogleOneTapResponse,
            auto_select: false,
            cancel_on_tap_outside: false
        });

        // Mostrar el popup de One Tap
        google.accounts.id.prompt((notification) => {
            if (notification.isNotDisplayed() || notification.isSkippedMoment()) {
                console.log("One Tap no se mostró o fue saltado.");
                console.log("Razón de no mostrar:", notification.getNotDisplayedReason());
                console.log("Razón de salto:", notification.getSkippedReason());
            }
        });
        console.log("Google One Tap invocado.");

    } catch (e) {
        console.error("Error inicializando Google Auth:", e);
    }
};


document.addEventListener('DOMContentLoaded', () => {
    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    const loginForm = document.querySelector('#loginForm');
    const loginBtn = loginForm ? loginForm.querySelector('button[type="submit"]') : null;
    // RE-T.1.1: se entra con el documento o con el correo.
    const documentoInput = document.querySelector('#identificador');
    const rememberMeCheckbox = document.querySelector('#rememberMe');
    const forgotPasswordBtn = document.querySelector('#forgotPasswordBtn');
    const resetModal = document.querySelector('#resetPasswordModal');
    const closeResetModalBtn = document.querySelector('#closeResetModal');
    const resetRequestForm = document.querySelector('#resetRequestForm');
    const resetRequestBtn = document.querySelector('#resetRequestBtn');
    const resetEmailInput = document.querySelector('#resetEmail');
    const btnGoogleInfo = document.querySelector('#btnGoogleInfo');

    // ── Lógica de Botones Personalizados de Google ──
    const btnGoogleLogin = document.querySelector('#btnGoogleLogin');
    const btnGoogleRegister = document.querySelector('#btnGoogleRegister');

    const handleGoogleClick = (e) => {
        e.preventDefault();
        googleDesdeRegistro = e.currentTarget === btnGoogleRegister;

        // Efecto visual de carga en el botón
        const btn = e.currentTarget;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<span>Conectando...</span> <i class="ri-loader-4-line animate-spin"></i>';
        btn.style.pointerEvents = 'none';

        // Inicialización de respaldo en caso de que el onload del script de Google haya fallado
        if (!googleTokenClient && typeof google !== 'undefined' && google.accounts && GOOGLE_CLIENT_ID) {
            console.log("Inicializando cliente de Google de forma manual (fallback)...");
            try {
                googleTokenClient = google.accounts.oauth2.initTokenClient({
                    client_id: GOOGLE_CLIENT_ID,
                    scope: 'email profile openid',
                    callback: window.handleGoogleCredentialResponse
                });
            } catch (err) {
                console.error("Error en fallback initTokenClient:", err);
            }
        }

        if (typeof googleTokenClient !== 'undefined' && googleTokenClient) {
            try {
                googleTokenClient.requestAccessToken();
            } catch (err) {
                console.error("Error al solicitar token:", err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error interno al conectar con Google.',
                    confirmButtonColor: '#0052FF'
                });
            }
            // Restaurar botón después de unos segundos por si cierran la ventana
            setTimeout(() => {
                btn.innerHTML = originalHtml;
                btn.style.pointerEvents = 'auto';
            }, 5000);
        } else {
            btn.innerHTML = originalHtml;
            btn.style.pointerEvents = 'auto';

            let diag = "googleTokenClient is null.";
            if (!GOOGLE_CLIENT_ID) diag = "GOOGLE_CLIENT_ID is empty.";

            Swal.fire({
                icon: 'error',
                title: 'No conectado',
                text: 'No se ha podido conectar con Google. (' + diag + ') Por favor, recarga la página.',
                confirmButtonColor: '#0052FF'
            });
        }
    };

    if (btnGoogleLogin) btnGoogleLogin.addEventListener('click', handleGoogleClick);
    if (btnGoogleRegister) btnGoogleRegister.addEventListener('click', handleGoogleClick);

    // ── Lógica de Animación Flip (Login/Registro) ──
    const authFlipper = document.querySelector('#authFlipper');
    const showRegisterBtn = document.querySelector('#showRegisterBtn');
    const showLoginBtn = document.querySelector('#showLoginBtn');
    const btnBackToLoginTop = document.querySelector('#btnBackToLoginTop');

    if (showRegisterBtn && authFlipper) {
        showRegisterBtn.addEventListener('click', (e) => {
            e.preventDefault();
            authFlipper.classList.add('flipped');
        });
    }

    if (showLoginBtn && authFlipper) {
        showLoginBtn.addEventListener('click', (e) => {
            e.preventDefault();
            authFlipper.classList.remove('flipped');
        });
    }

    if (btnBackToLoginTop && authFlipper) {
        btnBackToLoginTop.addEventListener('click', (e) => {
            e.preventDefault();
            authFlipper.classList.remove('flipped');
        });
    }

    // Si hay un error de registro, o se llegó por el enlace de una clínica
    // (action=register&clinica=ID, HU-5.8), se abre directo el registro.
    const registerError = document.querySelector('.back .alert-error');
    if (authFlipper && (registerError || authFlipper.dataset.abrirRegistro === '1')) {
        authFlipper.classList.add('flipped');
    }

    // ── Recordarme: solo el documento, nunca la contraseña ──
    // El almacenamiento del navegador no es un lugar seguro para credenciales:
    // es legible por cualquier script de la página y por quien abra las
    // herramientas de desarrollo en un equipo compartido.
    // La preferencia se guarda aparte del documento: si se marcara solo el
    // documento, activar la casilla antes de escribir nada guardaría una cadena
    // vacía y la casilla volvería a aparecer desmarcada.
    const REMEMBER_FLAG_KEY = 'zooki_remember';
    const REMEMBER_DOC_KEY = 'zooki_remember_doc';

    // Purga contraseñas guardadas por versiones anteriores de este archivo.
    localStorage.removeItem('zooki_remember_pass');

    if (rememberMeCheckbox && localStorage.getItem(REMEMBER_FLAG_KEY) === '1') {
        rememberMeCheckbox.checked = true;

        const savedDoc = localStorage.getItem(REMEMBER_DOC_KEY);
        if (savedDoc && documentoInput) {
            documentoInput.value = savedDoc;
        }
    }

    // El estado se guarda al instante, no solo al enviar el formulario: antes
    // se perdía si el usuario marcaba la casilla y abandonaba la página.
    const guardarPreferenciaRecordarme = () => {
        if (!rememberMeCheckbox) return;
        if (rememberMeCheckbox.checked) {
            localStorage.setItem(REMEMBER_FLAG_KEY, '1');
            localStorage.setItem(REMEMBER_DOC_KEY, documentoInput ? documentoInput.value : '');
        } else {
            localStorage.removeItem(REMEMBER_FLAG_KEY);
            localStorage.removeItem(REMEMBER_DOC_KEY);
        }
    };

    if (rememberMeCheckbox) {
        rememberMeCheckbox.addEventListener('change', guardarPreferenciaRecordarme);
    }

    // Mantiene sincronizado el documento mientras se escribe con la casilla activa.
    if (documentoInput) {
        documentoInput.addEventListener('input', guardarPreferenciaRecordarme);
    }

    // ── Mostrar / ocultar contraseña ──
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            this.innerHTML = type === 'password'
                ? '<i class="ri-eye-off-line"></i>'
                : '<i class="ri-eye-line"></i>';
        });
    }

    // ── Lógica al enviar el formulario ──
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function () {
            // Deja registrado el documento definitivo al enviar el formulario.
            guardarPreferenciaRecordarme();

            loginBtn.disabled = true;
            loginBtn.innerHTML = '<span>Ingresando...</span> <i class="ri-loader-4-line animate-spin"></i>';
        });
    }

    // ── Modal ¿Olvidaste tu contraseña? ──
    const abrirResetModal = (e) => {
        if (e) e.preventDefault();
        if (!resetModal || !resetEmailInput) return;
        resetEmailInput.value = '';
        resetModal.removeAttribute('hidden');
        document.body.classList.add('modal-open');
        setTimeout(() => resetModal.classList.add('active'), 10);
        resetEmailInput.focus();
    };

    const cerrarResetModal = () => {
        if (!resetModal) return;
        resetModal.classList.remove('active');
        document.body.classList.remove('modal-open');
        setTimeout(() => resetModal.setAttribute('hidden', ''), 200);
    };

    if (forgotPasswordBtn) forgotPasswordBtn.addEventListener('click', abrirResetModal);
    if (closeResetModalBtn) closeResetModalBtn.addEventListener('click', cerrarResetModal);
    if (resetModal) {
        resetModal.addEventListener('click', (event) => {
            if (event.target === resetModal) cerrarResetModal();
        });
    }

    if (resetRequestForm && resetRequestBtn && resetEmailInput) {
        resetRequestForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const email = resetEmailInput.value.trim();

            if (!email) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ups…',
                    text: 'Ingresa el correo electrónico registrado.',
                    confirmButtonColor: '#0052FF'
                });
                return;
            }

            resetRequestBtn.disabled = true;
            resetRequestBtn.innerHTML = '<span>Enviando...</span> <i class="ri-loader-4-line animate-spin"></i>';

            try {
                const response = await fetch('index.php?action=solicitar_reset_password_ajax', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: new URLSearchParams({ email })
                });
                const data = await response.json();

                Swal.fire({
                    icon: data.success ? 'success' : 'error',
                    title: data.success ? '¡Listo!' : 'No pudimos enviarlo',
                    text: data.message,
                    confirmButtonColor: '#0052FF'
                });

                if (data.success) cerrarResetModal();
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Algo salió mal',
                    text: 'No pudimos procesar tu solicitud. Intenta de nuevo en unos minutos.',
                    confirmButtonColor: '#0052FF'
                });
            } finally {
                resetRequestBtn.disabled = false;
                resetRequestBtn.innerHTML = '<span>Enviar enlace</span> <i class="ri-send-plane-2-line"></i>';
            }
        });
    }

    // El campo de acceso ya no filtra a solo números: también admite el correo.



    // ── RE-T.18.2: clínica y aceptación antes de crear la cuenta de Google ──
    const completeGoogleModal = document.getElementById('completeGoogleRegisterModal');
    const closeGoogleModalBtn = document.getElementById('closeGoogleModal');
    const completeGoogleForm = document.getElementById('completeGoogleForm');
    const completeGoogleBtn = document.getElementById('completeGoogleBtn');
    const googleUserEmailSpan = document.getElementById('googleUserEmail');
    const googleClinica = document.getElementById('google_id_clinica');
    const textoBotonGoogle = '<span>Crear mi cuenta</span> <i class="ri-check-line"></i>';

    window.abrirGoogleModal = (email, idClinica) => {
        if (!completeGoogleModal) return;
        if (googleUserEmailSpan) googleUserEmailSpan.textContent = email;
        if (googleClinica && idClinica) googleClinica.value = String(idClinica);
        completeGoogleModal.removeAttribute('hidden');
        document.body.classList.add('modal-open');
        setTimeout(() => completeGoogleModal.classList.add('active'), 10);
    };

    const cerrarGoogleModal = () => {
        if (!completeGoogleModal) return;
        completeGoogleModal.classList.remove('active');
        document.body.classList.remove('modal-open');
        setTimeout(() => completeGoogleModal.setAttribute('hidden', ''), 200);
    };

    if (closeGoogleModalBtn) closeGoogleModalBtn.addEventListener('click', cerrarGoogleModal);

    if (completeGoogleForm) {
        completeGoogleForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!completeGoogleForm.reportValidity()) return;
            completeGoogleBtn.disabled = true;
            completeGoogleBtn.innerHTML = '<span>Creando cuenta...</span> <i class="ri-loader-4-line animate-spin"></i>';

            try {
                const res = await fetch('index.php?action=complete_google_register_ajax', {
                    method: 'POST',
                    body: new FormData(completeGoogleForm)
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = data.extra.redirect;
                    return;
                }
                Swal.fire({ icon: 'error', title: 'No pudimos crear la cuenta', text: data.message, confirmButtonColor: '#0052FF' });
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión', confirmButtonColor: '#0052FF' });
            }
            completeGoogleBtn.disabled = false;
            completeGoogleBtn.innerHTML = textoBotonGoogle;
        });
    }
});
