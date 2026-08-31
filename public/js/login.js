/**
 * login.js — Lógica del formulario de inicio de sesión de Zooki.
 * Según ZOOKI_REGLAS.md: CERO JS en línea. Todo va en public/js/.
 */

// ── Lógica de Google Sign-In & One Tap ──
const GOOGLE_CLIENT_ID = (window.ZookiConfig && window.ZookiConfig.googleClientId) ? window.ZookiConfig.googleClientId : ""; 

window.handleGoogleCredentialResponse = async (response) => {
    try {
        const formData = new FormData();
        // Con initTokenClient recibimos access_token en lugar de credential(JWT)
        formData.append('access_token', response.access_token);

        const res = await fetch('index.php?action=google_login_ajax', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        console.log("Respuesta de Google Login AJAX:", data);

        if (data.success) {
            if (data.extra && data.extra.action === 'login') {
                console.log("Login exitoso, redirigiendo a", data.extra.redirect);
                window.location.href = data.extra.redirect;
            } else if (data.extra && data.extra.action === 'complete_profile') {
                console.log("Perfil nuevo detectado. Intentando abrir modal con email:", data.extra.email);
                console.log("typeof window.abrirGoogleModal =", typeof window.abrirGoogleModal);
                
                if (typeof window.abrirGoogleModal === 'function') {
                    window.abrirGoogleModal(data.extra.email);
                    console.log("window.abrirGoogleModal ejecutada.");
                } else {
                    console.error("No se encontró la función abrirGoogleModal!");
                }
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error de autenticación',
                text: data.message || 'No se pudo iniciar sesión con Google.',
                confirmButtonColor: '#0052FF'
            });
        }
    } catch (error) {
        console.error("Error validando Google Token:", error);
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
        // Con One Tap recibimos credential (JWT) en lugar de access_token
        formData.append('credential', response.credential);

        const res = await fetch('index.php?action=google_login_ajax', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        console.log("Respuesta de One Tap AJAX:", data);

        if (data.success) {
            if (data.extra && data.extra.action === 'login') {
                window.location.href = data.extra.redirect;
            } else if (data.extra && data.extra.action === 'complete_profile') {
                if (typeof window.abrirGoogleModal === 'function') {
                    window.abrirGoogleModal(data.extra.email);
                }
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error de autenticación',
                text: data.message || 'No se pudo iniciar sesión con Google One Tap.',
                confirmButtonColor: '#0052FF'
            });
        }
    } catch (error) {
        console.error("Error validando Google One Tap Token:", error);
    }
};

let googleTokenClient = null;

// Cuando Google termine de cargar, inicializamos los clientes
window.initGoogleAuth = function() {
    console.log("initGoogleAuth convocado por Google Identity Services.");
    if (!GOOGLE_CLIENT_ID) {
        console.error("No se encontró el Client ID de Google en ZookiConfig.");
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
    const documentoInput = document.querySelector('#documento');
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

    // Si hay un error de registro, voltear automáticamente la tarjeta
    const registerError = document.querySelector('.back .alert-error');
    if (registerError && authFlipper) {
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

    // ── Filtrar solo números en campo documento ──
    if (documentoInput) {
        documentoInput.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }



    // ── Modal Completar Registro Google ──
    const completeGoogleModal = document.getElementById('completeGoogleRegisterModal');
    const closeGoogleModalBtn = document.getElementById('closeGoogleModal');
    const completeGoogleForm = document.getElementById('completeGoogleForm');
    const completeGoogleBtn = document.getElementById('completeGoogleBtn');
    const googleUserEmailSpan = document.getElementById('googleUserEmail');
    const googleDocumentoInput = document.getElementById('google_documento');
    const googleTelefonoInput = document.getElementById('google_telefono');

    // Exponer globalmente para que handleGoogleCredentialResponse lo pueda llamar
    window.abrirGoogleModal = (email) => {
        console.log("abrirGoogleModal ejecutándose para:", email);
        if (!completeGoogleModal) {
            console.error("No se encontró el elemento completeGoogleRegisterModal en el HTML.");
            return;
        }
        if (googleUserEmailSpan) googleUserEmailSpan.textContent = email;
        completeGoogleModal.removeAttribute('hidden');
        document.body.classList.add('modal-open');
        setTimeout(() => {
            completeGoogleModal.classList.add('active');
            console.log("Clase active añadida al modal.");
        }, 10);
    };

    const cerrarGoogleModal = () => {
        if (!completeGoogleModal) return;
        completeGoogleModal.classList.remove('active');
        document.body.classList.remove('modal-open');
        setTimeout(() => completeGoogleModal.setAttribute('hidden', ''), 200);
    };

    if (closeGoogleModalBtn) closeGoogleModalBtn.addEventListener('click', cerrarGoogleModal);
    
    // Validar inputs
    if (googleDocumentoInput) {
        googleDocumentoInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    if (googleTelefonoInput) {
        googleTelefonoInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    if (completeGoogleForm) {
        completeGoogleForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            completeGoogleBtn.disabled = true;
            completeGoogleBtn.innerHTML = '<span>Guardando...</span> <i class="ri-loader-4-line animate-spin"></i>';

            try {
                const formData = new FormData(completeGoogleForm);
                const res = await fetch('index.php?action=complete_google_register_ajax', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    window.location.href = data.extra.redirect;
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.message,
                        confirmButtonColor: '#0052FF'
                    });
                    completeGoogleBtn.disabled = false;
                    completeGoogleBtn.innerHTML = '<span>Finalizar Registro</span> <i class="ri-check-line"></i>';
                }
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error de conexión',
                    confirmButtonColor: '#0052FF'
                });
                completeGoogleBtn.disabled = false;
                completeGoogleBtn.innerHTML = '<span>Finalizar Registro</span> <i class="ri-check-line"></i>';
            }
        });
    }
});
