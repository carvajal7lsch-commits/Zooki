/**
 * Registro del propietario, cara trasera de login.php (HU-5.8, D1: la única
 * página de registro del propietario).
 *
 * validacion-cuenta.js intercepta primero el envío y deja el error junto a
 * cada campo; cuando todo cumple, aquí se envía con la ventana de carga y de
 * «Revisa tu correo» que comparte con el registro de clínica (M0.1,
 * registro-cuenta.js). RE-T.13.2: todas las respuestas muestran la misma
 * pantalla, sin sondeo de identidad.
 */
document.addEventListener('DOMContentLoaded', () => {
    const registerForm = document.getElementById('registerForm');
    if (!registerForm || !window.ZookiRegistro) return;

    registerForm.addEventListener('submit', event => {
        event.preventDefault();
        window.ZookiRegistro.enviar(registerForm, 'process_register');
    });

    [
        ['togglePasswordReg', 'password_reg'],
        ['toggleConfirmPasswordReg', 'confirm_password']
    ].forEach(([idBoton, idCampo]) => {
        const campo = document.getElementById(idCampo);
        document.getElementById(idBoton)?.addEventListener('click', () => {
            campo.type = campo.type === 'password' ? 'text' : 'password';
        });
    });
});
