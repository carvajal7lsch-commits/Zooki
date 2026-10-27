/**
 * HU-42 / HU-39 — Panel "Mi perfil" del personal.
 *
 * Datos de contacto: actualizar_mi_perfil_ajax (el sujeto sale de la sesión).
 * Contraseña: cambiar_password_ajax. Se pide la actual solo si la cuenta ya
 * tiene una contraseña conocida (password_definida, HU-39).
 */
(function () {
    // HU-T.5: presentación local; no cambia lecturas, permisos ni formularios.
    const presentacion = document.querySelector?.('[data-perfil-secciones]');
    if (presentacion) {
        const salida = presentacion.querySelector('.perfil-salir');
        const lateral = presentacion.querySelector('.perfil-lateral');
        const seguridad = document.getElementById('seguridad');
        const movil = window.matchMedia('(max-width: 1023px)');
        const situarSalida = () => {
            (movil.matches ? seguridad : lateral).append(salida);
            salida.classList.toggle('perfil-salir--seguridad', movil.matches);
        };
        movil.addEventListener('change', situarSalida);
        situarSalida();
        const eventos = Array.from(presentacion.querySelectorAll('#perfilActividad .perfil-evento'));
        const anterior = presentacion.querySelector('[data-actividad-anterior]');
        const siguiente = presentacion.querySelector('[data-actividad-siguiente]');
        const paginaTexto = presentacion.querySelector('[data-actividad-pagina]');
        let pagina = 0;
        function paginar(cambio) {
            pagina += cambio;
            eventos.forEach((evento, indice) => { evento.hidden = Math.floor(indice / 5) !== pagina; });
            anterior.disabled = pagina === 0;
            siguiente.disabled = (pagina + 1) * 5 >= eventos.length;
            paginaTexto.textContent = 'Página ' + (pagina + 1) + ' de ' + Math.ceil(eventos.length / 5);
        }
        anterior?.addEventListener('click', () => paginar(-1));
        siguiente?.addEventListener('click', () => paginar(1));
        presentacion.addEventListener('horario-trabajo:actualizado', evento => {
            const lista = presentacion.querySelector('[data-resumen-semana]');
            lista.replaceChildren();
            ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'].forEach((dia, indice) => {
                const item = document.createElement('li');
                const etiqueta = document.createElement('strong');
                etiqueta.textContent = dia;
                const texto = document.createElement('span');
                const turnos = evento.detail.filter(f => Number(f.dia_semana) === indice + 1);
                texto.textContent = turnos.map(f => f.hora_inicio.slice(0, 5) + '–' + f.hora_fin.slice(0, 5)).join(' / ') || 'Libre';
                item.append(etiqueta, texto);
                lista.append(item);
            });
        });
        presentacion.addEventListener('ausencias:actualizadas', evento => {
            const proxima = evento.detail.filter(a => !['rechazada', 'anulada'].includes(a.estado) && Date.parse(String(a.fecha_hora_fin).replace(' ', 'T') + '-05:00') >= Date.now()).sort((a, b) => String(a.fecha_hora_inicio).localeCompare(String(b.fecha_hora_inicio)))[0];
            presentacion.querySelector('[data-resumen-ausencia]').textContent = proxima ? proxima.fecha_hora_inicio + ' · ' + proxima.estado : 'Sin ausencias';
        });
        const coordinacion = presentacion.querySelector('[data-coordinacion-horarios]');
        if (coordinacion) {
            const afectadas = coordinacion.querySelector('[data-afectadas]');
            const tarjetaAfectadas = afectadas.closest('.coord-card');
            const cierres = coordinacion.querySelector('[data-cierres]').closest('.coord-card');
            // El mismo partial conserva sus formularios y oyentes: solo cambia el orden visual.
            coordinacion.append(tarjetaAfectadas, cierres);
            cierres.classList.add('perfil-cierres');
            const actualizarIndicadores = () => {
                const hayAfectadas = afectadas.querySelectorAll('.coord-item').length > 0;
                tarjetaAfectadas.classList.toggle('perfil-sin-afectadas', !hayAfectadas);
                presentacion.querySelector('[data-perfil-pendiente="ausencias"]').hidden = !hayAfectadas;
                const pendiente = Array.from(coordinacion.querySelectorAll('[data-propuestas] .coord-item strong')).some(titulo => titulo.textContent.endsWith(' · Pendiente'));
                presentacion.querySelector('[data-perfil-pendiente="horarios"]').hidden = !pendiente;
            };
            new MutationObserver(actualizarIndicadores).observe(coordinacion, { childList: true, subtree: true, characterData: true });
            actualizarIndicadores();
        }
    }
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

    const hayCambios = () => telefono.value.trim() !== original.telefono && telefono.checkValidity();

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
            // HU-T.5: Ajustes comparte el formulario sin la cabecera de Mi perfil.
            if (document.getElementById('perfilEmailHero')) document.getElementById('perfilEmailHero').textContent = nuevo.email;
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
    const btnPwd = document.getElementById('perfilPasswordGuardar');
    const msgPwd = document.getElementById('perfilPasswordMsg');
    const textoBoton = btnPwd.textContent;

    // D2: validacion-cuenta.js escribe los avisos (política completa del
    // servidor y «Las contraseñas coinciden»). D2.1: aquí solo se marca, en
    // cada tecla, la lista de requisitos básicos de helpers/PoliticaPassword.php.
    const REQUISITOS = {
        longitud: valor => valor.length >= 8,
        mayuscula: valor => /[A-ZÁÉÍÓÚÜÑ]/.test(valor),
        minuscula: valor => /[a-záéíóúüñ]/.test(valor),
        numero: valor => /\d/.test(valor),
    };
    const itemsRequisito = formPwd.querySelectorAll('[data-requisito]');

    function pintarRequisitos() {
        itemsRequisito.forEach(item => {
            item.classList.toggle('is-ok', REQUISITOS[item.dataset.requisito](nueva.value));
        });
    }

    nueva.addEventListener('input', () => {
        pintarRequisitos();
        mensaje(msgPwd, '');
    });
    confirmar.addEventListener('input', () => mensaje(msgPwd, ''));
    formPwd.addEventListener('reset', () => setTimeout(pintarRequisitos));

    // Mostrar u ocultar cada contraseña.
    (presentacion || formPwd).querySelectorAll('[data-ver]').forEach(boton => boton.addEventListener('click', () => {
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
            if (formPwd.elements.access_token) datos.access_token = formPwd.elements.access_token.value;
            const r = await enviar('cambiar_password_ajax', datos);
            if (!r.success) {
                mensaje(msgPwd, r.message || 'No se pudo actualizar la contraseña.', 'error');
                return;
            }
            formPwd.reset();
            formPwd.querySelectorAll('input:not([type="hidden"])').forEach(i => { i.type = 'password'; });
            // La cuenta ya tiene contraseña: desde ahora se pide la actual (HU-39),
            // así que se recarga para mostrar ese campo; sin esperar (C9.1).
            if (!actual) {
                zookiRecargarConAviso('Contraseña creada.');
                return;
            }
            mensaje(msgPwd, 'Contraseña actualizada.', 'ok');
            window.zookiToast('Contraseña actualizada.');
        } catch (err) {
            console.error('Perfil:', err);
            mensaje(msgPwd, 'Error de conexión. Intenta nuevamente.', 'error');
        } finally {
            btnPwd.disabled = false;
            btnPwd.textContent = textoBoton;
        }
    });

    // "Cambiar contraseña" en el menú del avatar llega con #seguridad.
    if (!presentacion && window.location.hash === '#seguridad') (actual || nueva).focus();
})();
