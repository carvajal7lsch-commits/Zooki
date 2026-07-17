document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    const documentoInput = document.getElementById('documento_reg'); // ID updated in login.php
    const telefonoInput = document.getElementById('telefono');
    const passwordInput = document.getElementById('password_reg');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const emailInput = document.getElementById('email_reg');
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

    // ── Medidor de Fuerza de Contraseña ──
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Reset classes
        passwordMeter.className = 'password-meter';
        
        if (password.length === 0) {
            passwordValidationMsg.textContent = "Mínimo 6 caracteres";
            passwordValidationMsg.className = "validation-msg";
            isPasswordValid = false;
            updateSubmitButton();
            return;
        }

        // Checklist de seguridad
        if (password.length >= 6) strength++; // Min length
        if (/[A-Z]/.test(password) || /[a-z]/.test(password)) strength++; // Has letters
        if (/[0-9]/.test(password)) strength++; // Has numbers
        if (/[^A-Za-z0-9]/.test(password)) strength++; // Has special chars
        if (password.length >= 10) strength++; // Bonus length

        if (strength <= 2) {
            passwordMeter.classList.add('weak');
            passwordValidationMsg.textContent = "Débil: Agrega letras y números";
            passwordValidationMsg.className = "validation-msg error";
            isPasswordValid = false; // Bloquear si es débil
        } else if (strength === 3 || strength === 4) {
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

        // Mostrar estado de carga en el botón
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span>Creando cuenta...</span> <i class="ri-loader-4-line animate-spin"></i>';
    });
});
