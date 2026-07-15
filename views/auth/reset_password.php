<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$tokenId = $tokenId ?? 0;
$tokenPlano = $tokenPlano ?? '';
$tokenValido = $tokenValido ?? false;
$errorMessage = $errorMessage ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña - Zooki</title>
    <link rel="icon" type="image/png" href="img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="login-page reset-page">
    <div class="reset-wrapper">
        <div class="reset-card">
            <div class="reset-hero">
                <div class="reset-icon">
                    <i class="ri-lock-password-line"></i>
                </div>
                <h1><?php echo $tokenValido ? 'Crea una nueva contraseña' : 'Enlace no disponible'; ?></h1>
                <p class="reset-subtitle">
                    <?php if ($tokenValido): ?>
                        Protege tu cuenta con una contraseña segura y fácil de recordar.
                    <?php else: ?>
                        <?php echo htmlspecialchars($errorMessage ?: 'El enlace que intentas usar no es válido o ya expiró.'); ?>
                    <?php endif; ?>
                </p>
            </div>

            <?php if ($tokenValido): ?>
            <form class="reset-form" id="resetPasswordForm">
                <input type="hidden" name="token_id" value="<?php echo (int)$tokenId; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($tokenPlano, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="input-group">
                    <label for="newPassword">Nueva contraseña</label>
                    <div class="input-wrapper">
                        <i class="ri-shield-keyhole-line"></i>
                        <input type="password" id="newPassword" name="password" placeholder="••••••••" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleNewPassword" tabindex="-1">
                            <i class="ri-eye-off-line"></i>
                        </button>
                    </div>
                    <div class="password-meter-container">
                        <div class="password-meter" id="passwordMeter"></div>
                    </div>
                    <span class="validation-msg" id="passwordValidationMsg">Mínimo 6 caracteres (letras y números)</span>
                </div>

                <div class="input-group">
                    <label for="confirmPassword">Confirmar contraseña</label>
                    <div class="input-wrapper">
                        <i class="ri-check-double-line"></i>
                        <input type="password" id="confirmPassword" name="password_confirmation" placeholder="Repite tu contraseña" required minlength="6" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggleConfirmPassword" tabindex="-1">
                            <i class="ri-eye-off-line"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="resetSubmitBtn">
                    <span>Actualizar contraseña</span>
                    <i class="ri-refresh-line"></i>
                </button>
            </form>
            <?php else: ?>
                <div class="reset-actions">
                    <a class="btn-secondary" href="index.php?action=login">
                        <i class="ri-login-box-line"></i>
                        <span>Ir a inicio de sesión</span>
                    </a>
                    <a class="btn-link" href="index.php?action=login" id="requestAnotherLink">
                        <i class="ri-mail-send-line"></i>
                        <span>Solicitar un nuevo enlace</span>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const resetForm = document.querySelector('#resetPasswordForm');
            const submitBtn = document.querySelector('#resetSubmitBtn');
            const newPassword = document.querySelector('#newPassword');
            const confirmPassword = document.querySelector('#confirmPassword');
            const toggleNewPassword = document.querySelector('#toggleNewPassword');
            const toggleConfirmPassword = document.querySelector('#toggleConfirmPassword');
            const passwordMeter = document.getElementById('passwordMeter');
            const passwordValidationMsg = document.getElementById('passwordValidationMsg');

            if (!resetForm) {
                return;
            }

            let isPasswordValid = false;

            function updateSubmitButton() {
                if (isPasswordValid) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }

            // Deshabilitar por defecto para forzar contraseña válida
            submitBtn.disabled = true;

            // Ojo de visibilidad: nueva contraseña
            if (toggleNewPassword && newPassword) {
                toggleNewPassword.addEventListener('click', function () {
                    const type = newPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    newPassword.setAttribute('type', type);
                    this.innerHTML = type === 'password'
                        ? '<i class="ri-eye-off-line"></i>'
                        : '<i class="ri-eye-line"></i>';
                });
            }

            // Ojo de visibilidad: confirmar contraseña
            if (toggleConfirmPassword && confirmPassword) {
                toggleConfirmPassword.addEventListener('click', function () {
                    const type = confirmPassword.getAttribute('type') === 'password' ? 'text' : 'password';
                    confirmPassword.setAttribute('type', type);
                    this.innerHTML = type === 'password'
                        ? '<i class="ri-eye-off-line"></i>'
                        : '<i class="ri-eye-line"></i>';
                });
            }

            // Medidor de fuerza
            if (newPassword && passwordMeter && passwordValidationMsg) {
                newPassword.addEventListener('input', function() {
                    const val = this.value;
                    let strength = 0;
                    
                    passwordMeter.className = 'password-meter';
                    
                    if (val.length === 0) {
                        passwordValidationMsg.textContent = "Mínimo 6 caracteres";
                        passwordValidationMsg.className = "validation-msg";
                        isPasswordValid = false;
                        updateSubmitButton();
                        return;
                    }

                    if (val.length >= 6) strength++;
                    if (/[A-Z]/.test(val) || /[a-z]/.test(val)) strength++;
                    if (/[0-9]/.test(val)) strength++;
                    if (/[^A-Za-z0-9]/.test(val)) strength++;
                    if (val.length >= 10) strength++;

                    if (strength <= 2) {
                        passwordMeter.classList.add('weak');
                        passwordValidationMsg.textContent = "Débil: Agrega letras y números";
                        passwordValidationMsg.className = "validation-msg error";
                        isPasswordValid = false;
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
            }

            resetForm.addEventListener('submit', async (event) => {
                event.preventDefault();

                const formData = new FormData(resetForm);
                const password = formData.get('password');
                const confirm = formData.get('password_confirmation');

                if (password !== confirm) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Contraseñas distintas',
                        text: 'Asegúrate de que ambas contraseñas coincidan.',
                        confirmButtonColor: '#5560FF'
                    });
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Guardando...</span> <i class="ri-loader-4-line animate-spin"></i>';

                try {
                    const response = await fetch('index.php?action=procesar_reset_password_ajax', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: new URLSearchParams({
                            token_id: formData.get('token_id'),
                            token: formData.get('token'),
                            password,
                            password_confirmation: confirm
                        })
                    });
                    const data = await response.json();

                    Swal.fire({
                        icon: data.success ? 'success' : 'error',
                        title: data.success ? '¡Contraseña actualizada!' : 'No pudimos actualizarla',
                        text: data.message,
                        confirmButtonColor: '#5560FF'
                    }).then(() => {
                        if (data.success) {
                            window.location.href = 'index.php?action=login';
                        }
                    });
                } catch (error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Algo salió mal',
                        text: 'Intenta nuevamente en unos minutos.',
                        confirmButtonColor: '#5560FF'
                    });
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<span>Actualizar contraseña</span> <i class="ri-refresh-line"></i>';
                }
            });
        });
    </script>

    <style>
        .reset-page {
            background: linear-gradient(135deg, #f4f7ff 0%, #fff7f0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .reset-wrapper {
            width: min(480px, 92%);
        }
        .reset-card {
            background: #ffffff;
            border-radius: 32px;
            padding: 3rem 3rem 2.5rem;
            box-shadow: 0 40px 80px rgba(15, 23, 42, 0.15);
            position: relative;
            overflow: hidden;
        }
        .reset-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(85, 96, 255, 0.12), transparent 50%);
            pointer-events: none;
        }
        .reset-hero {
            position: relative;
            z-index: 1;
            text-align: center;
            margin-bottom: 2.5rem;
        }
        .reset-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 1rem;
            border-radius: 22px;
            display: grid;
            place-items: center;
            font-size: 2.25rem;
            color: #2563eb;
            background: linear-gradient(135deg, rgba(85, 96, 255, 0.15), rgba(164, 202, 255, 0.45));
        }
        .reset-hero h1 {
            margin: 0;
            font-size: 2rem;
            color: #0f172a;
        }
        .reset-subtitle {
            margin-top: 0.75rem;
            color: #475569;
            line-height: 1.6;
        }
        .reset-form {
            position: relative;
            z-index: 1;
        }
        .reset-form .input-group {
            margin-bottom: 1.5rem;
        }
        .reset-form label {
            font-weight: 600;
            color: #0f172a;
            display: block;
            margin-bottom: 0.6rem;
        }
        .reset-form .input-wrapper {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: 1px solid rgba(99, 102, 241, 0.35);
            border-radius: 16px;
            padding: 0.9rem 1rem;
            background: rgba(248, 250, 252, 0.9);
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }
        .reset-form .input-wrapper:focus-within {
            border-color: #5560FF;
            box-shadow: 0 0 0 4px rgba(85, 96, 255, 0.12);
        }
        .reset-form .input-wrapper input {
            flex: 1;
            border: none !important;
            background: transparent !important;
            outline: none !important;
            font-size: 1rem;
            color: #0f172a;
            padding: 0 0 0 6px !important;
            box-shadow: none !important;
        }
        .reset-form .input-wrapper input:focus {
            box-shadow: none !important;
            background: transparent !important;
        }
        .toggle-password {
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            padding: 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            transition: color 0.2s ease;
        }
        .toggle-password:hover {
            color: #5560FF;
        }
        .btn-primary {
            width: 100%;
            justify-content: center;
            gap: 0.75rem;
        }
        .btn-primary i {
            font-size: 1.1rem;
        }
        .reset-actions {
            position: relative;
            z-index: 1;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }
        .btn-secondary {
            display: inline-flex;
            gap: 0.65rem;
            align-items: center;
            justify-content: center;
            padding: 0.85rem 1.25rem;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 600;
            background: #f1f5f9;
            color: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 35px rgba(15, 23, 42, 0.12);
        }
        .btn-link {
            display: inline-flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
            color: #2563eb;
            text-decoration: none;
            font-weight: 600;
        }
        @media (max-width: 640px) {
            .reset-card {
                padding: 2.5rem 1.75rem;
            }
        }
    </style>
</body>
</html>
