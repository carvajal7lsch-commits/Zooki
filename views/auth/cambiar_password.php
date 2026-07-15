<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Contraseña - Zooki</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }

        .change-password-container {
            background: white;
            padding: 3rem;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 450px;
            width: 100%;
        }

        .change-password-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .change-password-header i {
            font-size: 4rem;
            color: #0052FF;
            margin-bottom: 1rem;
        }

        .change-password-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 0.5rem 0;
        }

        .change-password-header p {
            color: #6B7280;
            font-size: 0.95rem;
            margin: 0;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid #E5E7EB;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: 'Inter', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0052FF;
            box-shadow: 0 0 0 4px rgba(0, 82, 255, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #0052FF 0%, #003bbb 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(0, 82, 255, 0.3);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 82, 255, 0.4);
        }

        .password-requirements {
            background: #F3F4F6;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .password-requirements h4 {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 0.5rem 0;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 1.25rem;
            font-size: 0.8rem;
            color: #6B7280;
        }

        .password-requirements li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="change-password-container">
        <div class="change-password-header">
            <i class="fas fa-lock"></i>
            <h1>Cambiar Contraseña</h1>
            <p>Por seguridad, debes cambiar tu contraseña en el primer inicio de sesión.</p>
        </div>

        <form id="changePasswordForm" onsubmit="cambiarPassword(event)">
            <div class="form-group">
                <label for="nueva_password">Nueva Contraseña</label>
                <input type="password" id="nueva_password" name="nueva_password" required minlength="6" placeholder="Mínimo 6 caracteres">
            </div>

            <div class="form-group">
                <label for="confirmar_password">Confirmar Contraseña</label>
                <input type="password" id="confirmar_password" name="confirmar_password" required minlength="6" placeholder="Repite tu nueva contraseña">
            </div>

            <div class="password-requirements">
                <h4>Requisitos de contraseña:</h4>
                <ul>
                    <li>Mínimo 6 caracteres</li>
                    <li>Recomendado: usar letras, números y símbolos</li>
                </ul>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-key"></i> Cambiar Contraseña
            </button>
        </form>
    </div>

    <script>
        async function cambiarPassword(event) {
            event.preventDefault();
            
            const nuevaPassword = document.getElementById('nueva_password').value;
            const confirmarPassword = document.getElementById('confirmar_password').value;

            if (nuevaPassword !== confirmarPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden',
                    confirmButtonColor: '#0052FF'
                });
                return;
            }

            if (nuevaPassword.length < 6) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La contraseña debe tener al menos 6 caracteres',
                    confirmButtonColor: '#0052FF'
                });
                return;
            }

            try {
                const res = await (await fetch('index.php?action=cambiar_password_ajax', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `nueva_password=${encodeURIComponent(nuevaPassword)}`
                })).json();

                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Contraseña cambiada!',
                        text: 'Tu contraseña ha sido actualizada exitosamente.',
                        confirmButtonColor: '#0052FF'
                    }).then(() => {
                        // Redirigir según el rol del usuario
                        const rol = <?php echo $_SESSION['usuario_id_rol'] ?? 0; ?>;
                        if (rol == 4) {
                            window.location.href = 'index.php?action=portal_propietario';
                        } else if (rol == 1) {
                            window.location.href = 'index.php?action=admin_panel';
                        } else if (rol == 2) {
                            window.location.href = 'index.php?action=vet_area';
                        } else if (rol == 3) {
                            window.location.href = 'index.php?action=reception_dashboard';
                        } else {
                            window.location.href = 'index.php?action=dashboard';
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.message || 'No se pudo cambiar la contraseña',
                        confirmButtonColor: '#0052FF'
                    });
                }
            } catch (e) {
                console.error(e);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#0052FF'
                });
            }
        }
    </script>
</body>
</html>
