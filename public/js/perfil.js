/**
 * HU-42 / HU-39 — Panel "Mi perfil" del personal.
 *
 * Datos de contacto: actualizar_mi_perfil_ajax (el sujeto sale de la sesión).
 * Contraseña: cambiar_password_ajax. Se pide la actual solo si la cuenta ya
 * tiene una contraseña conocida (password_definida, HU-39).
 */
(function () {
    function mensaje(el, texto, tipo) {
        el.textContent = texto;
        el.className = 'perfil-msg' + (tipo ? ` perfil-msg--${tipo}` : '');
    }

    async function enviar(accion, datos) {
        const fd = new FormData();
        Object.entries(datos).forEach(([k, v]) => fd.append(k, v));
        const res = await fetch(`index.php?action=${accion}`, { method: 'POST', body: fd });
        return res.json();
    }

    // ── Datos de contacto ──
    const contacto = document.getElementById('perfilContactoForm');
    const email = document.getElementById('perfilEmail');
    const telefono = document.getElementById('perfilTelefono');
    const guardar = document.getElementById('perfilContactoGuardar');
    const msgContacto = document.getElementById('perfilContactoMsg');
    let original = { email: email.value.trim(), telefono: telefono.value.trim() };

    const hayCambios = () => email.value.trim() !== original.email || telefono.value.trim() !== original.telefono;

    [email, telefono].forEach(campo => campo.addEventListener('input', () => {
        guardar.disabled = !hayCambios();
        mensaje(msgContacto, '');
    }));

    contacto.addEventListener('submit', async e => {
        e.preventDefault();
        const nuevo = { email: email.value.trim(), telefono: telefono.value.trim() };

        // Validación en el front para responder rápido; el backend la repite.
        if (!nuevo.email || !email.checkValidity()) {
            mensaje(msgContacto, 'Escribe un correo electrónico válido.', 'error');
            email.focus();
            return;
        }
        // D1: la regla del teléfono viene del campo (pattern y title de ValidadorTelefono).
        if (nuevo.telefono && !telefono.checkValidity()) {
            mensaje(msgContacto, telefono.title, 'error');
            telefono.focus();
            return;
        }

        guardar.disabled = true;
        guardar.textContent = 'Guardando…';
        try {
            const r = await enviar('actualizar_mi_perfil_ajax', nuevo);
            if (!r.success) {
                mensaje(msgContacto, r.message || 'No se pudieron guardar los cambios.', 'error');
                return;
            }
            original = nuevo;
            document.getElementById('perfilEmailHero').textContent = nuevo.email;
            mensaje(msgContacto, 'Datos actualizados.', 'ok');
        } catch (err) {
            console.error('Perfil:', err);
            mensaje(msgContacto, 'Error de conexión. Intenta nuevamente.', 'error');
        } finally {
            guardar.textContent = 'Guardar cambios';
            guardar.disabled = !hayCambios();
        }
    });

    // ── Contraseña ──
    const formPwd = document.getElementById('perfilPasswordForm');
    const actual = document.getElementById('perfilPwdActual');   // no existe si la cuenta no tiene contraseña
    const nueva = document.getElementById('perfilPwdNueva');
    const confirmar = document.getElementById('perfilPwdConfirmar');
    const feedback = document.getElementById('perfilPwdFeedback');
    const coincide = document.getElementById('perfilPwdCoincide');
    const btnPwd = document.getElementById('perfilPasswordGuardar');
    const msgPwd = document.getElementById('perfilPasswordMsg');
    const textoBoton = btnPwd.textContent;

    // D2: validacion-cuenta.js consulta la política completa del servidor mientras se escribe.

    // Mostrar u ocultar cada contraseña.
    formPwd.querySelectorAll('[data-ver]').forEach(boton => boton.addEventListener('click', () => {
        const campo = document.getElementById(boton.dataset.ver);
        const mostrar = campo.type === 'password';
        campo.type = mostrar ? 'text' : 'password';
        boton.setAttribute('aria-pressed', String(mostrar));
        boton.setAttribute('aria-label', mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña');
        boton.innerHTML = `<i class="far ${mostrar ? 'fa-eye-slash' : 'fa-eye'}"></i>`;
        campo.focus();
    }));

    formPwd.addEventListener('submit', async e => {
        e.preventDefault();
        btnPwd.disabled = true;
        btnPwd.textContent = 'Actualizando…';
        try {
            const datos = { nueva_password: nueva.value };
            if (actual) datos.password_actual = actual.value;
            const r = await enviar('cambiar_password_ajax', datos);
            if (!r.success) {
                mensaje(msgPwd, r.message || 'No se pudo actualizar la contraseña.', 'error');
                return;
            }
            formPwd.reset();
            formPwd.querySelectorAll('input').forEach(i => { i.type = 'password'; });
            // La cuenta ya tiene contraseña: desde ahora se pide la actual (HU-39),
            // así que se recarga para mostrar ese campo; sin esperar (C9.1).
            if (!actual) {
                zookiRecargarConAviso('Contraseña creada.');
                return;
            }
            mensaje(msgPwd, 'Contraseña actualizada.', 'ok');
        } catch (err) {
            console.error('Perfil:', err);
            mensaje(msgPwd, 'Error de conexión. Intenta nuevamente.', 'error');
        } finally {
            btnPwd.disabled = false;
            btnPwd.textContent = textoBoton;
        }
    });

    // "Cambiar contraseña" en el menú del avatar llega con #seguridad.
    if (window.location.hash === '#seguridad') (actual || nueva).focus();
})();
