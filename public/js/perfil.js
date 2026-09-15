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
        if (nuevo.telefono && !/^[0-9+\s-]{7,20}$/.test(nuevo.telefono)) {
            mensaje(msgContacto, 'El teléfono admite números, espacios, + y guiones (7 a 20 caracteres).', 'error');
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

    // password-policy.js se carga después de esta vista; se consulta al usarlo.
    const motivoInvalida = valor => (typeof window.motivoPasswordInvalida === 'function'
        ? window.motivoPasswordInvalida(valor) : null);

    // Los mismos requisitos de helpers/PoliticaPassword.php, marcados al escribir.
    const REQUISITOS = {
        longitud: v => v.length >= 8,
        mayuscula: v => /[A-ZÁÉÍÓÚÑ]/.test(v),
        minuscula: v => /[a-záéíóúñ]/.test(v),
        numero: v => /\d/.test(v),
    };
    const itemsRequisito = document.querySelectorAll('[data-requisito]');

    function pintarRequisitos() {
        const valor = nueva.value;
        let todos = true;
        itemsRequisito.forEach(li => {
            const cumple = REQUISITOS[li.dataset.requisito](valor);
            li.classList.toggle('is-ok', cumple);
            if (!cumple) todos = false;
        });
        // Con los requisitos básicos cumplidos, la política aún puede rechazarla
        // (contraseña común o con tus datos): eso se explica debajo.
        const motivo = valor && todos ? motivoInvalida(valor) : null;
        feedback.textContent = motivo || '';
        feedback.className = 'perfil-field__hint' + (motivo ? ' is-error' : '');
    }

    function pintarCoincidencia() {
        if (!confirmar.value) {
            coincide.textContent = '';
            coincide.className = 'perfil-field__hint';
            return;
        }
        const iguales = confirmar.value === nueva.value;
        coincide.textContent = iguales ? 'Las contraseñas coinciden.' : 'Las contraseñas no coinciden.';
        coincide.className = 'perfil-field__hint ' + (iguales ? 'is-ok' : 'is-error');
    }

    nueva.addEventListener('input', () => { pintarRequisitos(); pintarCoincidencia(); mensaje(msgPwd, ''); });
    confirmar.addEventListener('input', () => { pintarCoincidencia(); mensaje(msgPwd, ''); });

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
        if (actual && !actual.value) {
            mensaje(msgPwd, 'Escribe tu contraseña actual.', 'error');
            actual.focus();
            return;
        }
        if (!nueva.value) {
            mensaje(msgPwd, 'Escribe la nueva contraseña.', 'error');
            nueva.focus();
            return;
        }
        const motivo = motivoInvalida(nueva.value);
        if (motivo) {
            mensaje(msgPwd, motivo, 'error');
            nueva.focus();
            return;
        }
        if (nueva.value !== confirmar.value) {
            mensaje(msgPwd, 'Las contraseñas no coinciden.', 'error');
            confirmar.focus();
            return;
        }

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
            pintarRequisitos();
            pintarCoincidencia();
            mensaje(msgPwd, actual ? 'Contraseña actualizada.' : 'Contraseña creada.', 'ok');
            // La cuenta ya tiene contraseña: desde ahora se pide la actual (HU-39),
            // así que se recarga para mostrar ese campo.
            if (!actual) setTimeout(() => window.location.reload(), 1200);
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
