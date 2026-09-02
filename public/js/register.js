document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const documentoInput = document.getElementById('documento_reg'); // ID updated in login.php
    const telefonoInput = document.getElementById('telefono');
    const passwordInput = document.getElementById('password_reg');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const emailInput = document.getElementById('email_reg');

    // Este script es de la pestana de registro de login.php. La vista suelta
    // views/auth/register.php no tiene estos ids, y sin esta guarda el primer
    // addEventListener sobre null tumbaba el script entero en esa pagina.
    if (!registerForm || !documentoInput || !passwordInput || !emailInput) return;

    const submitBtn = registerForm.querySelector('button[type="submit"]');

    const docValidationMsg = document.getElementById('docValidationMsg');
    const emailValidationMsg = document.getElementById('emailValidationMsg');
    const passwordValidationMsg = document.getElementById('passwordValidationMsg');
    const passwordMeter = document.getElementById('passwordMeter');

    let isDocumentValid = false;
    let isEmailValid = false;
    let isPasswordValid = false;

    // ── Utilidades ──
    function debounce(func, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    // ── Límites Estrictos y Validaciones en Tiempo Real ──
    
    // Solo números en documento y teléfono
    documentoInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    telefonoInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // ── AJAX Validación de Documento ──
    const checkDocument = debounce(async (doc) => {
        if (doc.length < 5) {
            docValidationMsg.textContent = "Mínimo 5 dígitos";
            docValidationMsg.className = "validation-msg error";
            isDocumentValid = false;
            updateSubmitButton();
            return;
        }

        docValidationMsg.textContent = "Verificando...";
        docValidationMsg.className = "validation-msg";

        try {
            const formData = new FormData();
            formData.append('documento', doc);
            const response = await fetch('index.php?action=check_document_ajax', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.extra && data.extra.exists) {
                docValidationMsg.innerHTML = '<i class="ri-close-circle-line"></i> Documento ya registrado';
                docValidationMsg.className = "validation-msg error";
                isDocumentValid = false;
            } else {
                docValidationMsg.innerHTML = '<i class="ri-checkbox-circle-line"></i> Documento disponible';
                docValidationMsg.className = "validation-msg success";
                isDocumentValid = true;
            }
        } catch (error) {
            docValidationMsg.textContent = "Error de red";
            isDocumentValid = false;
        }
        updateSubmitButton();
    }, 500);

    documentoInput.addEventListener('input', (e) => checkDocument(e.target.value));

    // ── AJAX Validación de Email ──
    const checkEmail = debounce(async (email) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            emailValidationMsg.textContent = "Correo inválido";
            emailValidationMsg.className = "validation-msg error";
            isEmailValid = false;
            updateSubmitButton();
            return;
        }

        emailValidationMsg.textContent = "Verificando...";
        emailValidationMsg.className = "validation-msg";

        try {
            const formData = new FormData();
            formData.append('email', email);
            const response = await fetch('index.php?action=check_email_ajax', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            
            if (data.extra && data.extra.exists) {
                emailValidationMsg.innerHTML = '<i class="ri-close-circle-line"></i> Correo ya registrado';
                emailValidationMsg.className = "validation-msg error";
                isEmailValid = false;
            } else {
                emailValidationMsg.innerHTML = '<i class="ri-checkbox-circle-line"></i> Correo disponible';
                emailValidationMsg.className = "validation-msg success";
                isEmailValid = true;
            }
        } catch (error) {
            emailValidationMsg.textContent = "Error de red";
            isEmailValid = false;
        }
        updateSubmitButton();
    }, 500);

    emailInput.addEventListener('input', (e) => checkEmail(e.target.value));

    // ── Mostrar / ocultar contraseña ──
    // Mismo patrón que el campo de inicio de sesión (login.js) y el de
    // restablecimiento, para que el ojito se comporte igual en todo el sistema.
    [
        ['togglePasswordReg', passwordInput],
        ['toggleConfirmPasswordReg', confirmPasswordInput]
    ].forEach(function ([idBoton, campo]) {
        const boton = document.getElementById(idBoton);
        if (!boton || !campo) return;

        boton.addEventListener('click', function () {
            const visible = campo.getAttribute('type') === 'password';
            campo.setAttribute('type', visible ? 'text' : 'password');
            this.setAttribute('aria-pressed', visible ? 'true' : 'false');
            this.innerHTML = visible
                ? '<i class="ri-eye-line"></i>'
                : '<i class="ri-eye-off-line"></i>';
        });
    });

    // ── Medidor de Fuerza de Contraseña ──
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Reset classes
        passwordMeter.className = 'password-meter';
        
        if (password.length === 0) {
            passwordValidationMsg.textContent = "Mínimo 8 caracteres, con mayúscula, minúscula y número";
            passwordValidationMsg.className = "validation-msg";
            isPasswordValid = false;
            updateSubmitButton();
            return;
        }

        // La política manda antes que el medidor: de nada sirve marcar
        // "Fuerte" una contraseña que el servidor va a rechazar. Ej: "Password1"
        // cumple la forma (8 + mayúscula + minúscula + número) pero está entre
        // las primeras de cualquier diccionario.
        const motivo = window.motivoPasswordInvalida
            ? window.motivoPasswordInvalida(password)
            : null;

        if (motivo !== null) {
            passwordMeter.classList.add('weak');
            passwordValidationMsg.textContent = motivo;
            passwordValidationMsg.className = "validation-msg error";
            isPasswordValid = false;
            updateSubmitButton();
            return;
        }

        // Ya cumple; el medidor solo indica cuánto margen tiene por encima.
        strength = 3;
        if (/[^A-Za-z0-9]/.test(password)) strength++; // Símbolos
        if (password.length >= 12) strength++;         // Longitud extra

        if (strength === 3 || strength === 4) {
            passwordMeter.classList.add('medium');
            passwordValidationMsg.textContent = "Media: Contraseña aceptable ✅";
            passwordValidationMsg.className = "validation-msg success";
            isPasswordValid = true;
        } else {
            passwordMeter.classList.add('strong');
            passwordValidationMsg.textContent = "Fuerte: Excelente ✅";
            passwordValidationMsg.className = "validation-msg success";
            isPasswordValid = true;
        }
        updateSubmitButton();
    });

    // ── Habilitar / Deshabilitar Botón ──
    function updateSubmitButton() {
        if (isDocumentValid && isEmailValid && isPasswordValid) {
            submitBtn.disabled = false;
        } else {
            submitBtn.disabled = true;
        }
    }

    // Deshabilitar por defecto
    submitBtn.disabled = true;

    // ── Validación final al enviar ──
    registerForm.addEventListener('submit', function(event) {
        const password = passwordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        if (password !== confirmPassword) {
            event.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Contraseñas no coinciden',
                text: 'Asegúrate de escribir la misma contraseña en ambos campos.',
                confirmButtonColor: '#0052FF'
            });
            return;
        }

        if (!isDocumentValid || !isEmailValid || !isPasswordValid) {
            event.preventDefault();
            Swal.fire({
                icon: 'warning',
                title: 'Revisa los campos',
                text: 'Asegúrate de que no haya errores de validación antes de continuar.',
                confirmButtonColor: '#0052FF'
            });
            return;
        }

        // A partir de aquí se envía por fetch: la página ya no recarga ni
        // rebota al login. La cuenta se crea y el formulario da paso a la
        // pantalla de espera, que sondea hasta que el usuario abra el correo.
        event.preventDefault();
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Creando cuenta...</span> <i class="ri-loader-4-line animate-spin"></i>';
        enviarRegistro();
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
                // Sin verificación por correo configurada: se conserva el
                // comportamiento anterior de volver al login con el aviso.
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
