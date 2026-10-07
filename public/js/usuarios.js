/**
 * HU-T.7 — Gestión del personal de la clínica activa (views/admin/usuarios.php).
 *
 * Toda decisión de permisos y de clínica la toma el servidor; aquí solo se
 * arma la interfaz. Las peticiones POST llevan el token CSRF que agrega
 * js/csrf.js al FormData.
 */
(function () {
    'use strict';

    const modulo = document.getElementById('usuariosModulo');
    const modal = document.getElementById('usuarioModal');
    const form = document.getElementById('usuarioForm');
    if (!modulo || !modal || !form) return;

    const aviso = modal.querySelector('[data-aviso]');
    const titulo = modal.querySelector('[data-titulo]');
    const soloAlta = modal.querySelector('[data-solo-alta]');

    const mensaje = (icono, texto) => Swal.fire({ icon: icono, text: texto, confirmButtonColor: '#0052FF' });

    async function pedir(url, datos) {
        const opciones = datos ? { method: 'POST', body: datos } : {};
        opciones.headers = { 'X-Requested-With': 'XMLHttpRequest' };
        const respuesta = await fetch(url, opciones);
        const cuerpo = await respuesta.json().catch(() => ({ success: false, message: 'Respuesta inesperada del servidor.' }));
        if (cuerpo.redirect && (respuesta.status === 401 || respuesta.status === 403)) {
            window.location.href = cuerpo.redirect;
        }
        return cuerpo;
    }

    // ── Pestañas ──────────────────────────────────────────────────────────
    modulo.querySelectorAll('.tab-btn').forEach((boton) => {
        boton.addEventListener('click', () => {
            modulo.querySelectorAll('.tab-btn').forEach((b) => {
                b.classList.toggle('active', b === boton);
                b.setAttribute('aria-selected', b === boton ? 'true' : 'false');
            });
            modulo.querySelectorAll('[data-panel]').forEach((panel) => {
                const visible = panel.dataset.panel === boton.dataset.tab;
                panel.hidden = !visible;
                panel.classList.toggle('active', visible);
            });
        });
    });

    // ── Búsqueda ──────────────────────────────────────────────────────────
    const buscar = document.getElementById('usuariosBuscar');
    if (buscar) {
        buscar.addEventListener('input', () => {
            const texto = buscar.value.trim().toLowerCase();
            modulo.querySelectorAll('[data-fila]').forEach((fila) => {
                fila.hidden = texto !== '' && !fila.dataset.buscar.includes(texto);
            });
        });
    }

    // ── Modal ─────────────────────────────────────────────────────────────
    function abrirModal(esAlta) {
        titulo.textContent = esAlta ? 'Nuevo integrante' : 'Editar integrante';
        soloAlta.hidden = !esAlta;
        aviso.hidden = true;
        modal.classList.add('is-open');
        form.querySelector('input[name="documento"]').focus();
    }

    function cerrarModal() {
        modal.classList.remove('is-open');
        form.reset();
        form.elements.id_usuario.value = '';
    }

    modal.addEventListener('click', (evento) => {
        if (evento.target === modal || evento.target.closest('[data-accion="cerrar"]')) cerrarModal();
    });
    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && modal.classList.contains('is-open')) cerrarModal();
    });

    async function editar(id) {
        const r = await pedir('index.php?action=get_usuario_ajax&id_usuario=' + encodeURIComponent(id));
        if (!r.success) {
            mensaje('error', r.message || 'No se pudo cargar el usuario.');
            return;
        }
        const u = r.usuario;
        form.reset();
        form.elements.id_usuario.value = u.id_usuario;
        form.elements.tipo_documento.value = u.tipo_documento || 'CC';
        form.elements.documento.value = u.documento || '';
        form.elements.nombre_completo.value = u.nombre_completo || '';
        form.elements.email.value = u.email || '';
        form.elements.telefono.value = u.telefono || '';
        form.elements.id_rol.value = String(u.id_rol);
        form.elements.estado.value = String(u.estado);
        abrirModal(false);
    }

    // RE-T.7.5: si el documento o el correo ya tienen cuenta en Zooki, el
    // alta asigna el rol a esa persona; se avisa antes de guardar.
    async function revisarExistente(campo) {
        if (form.elements.id_usuario.value !== '' || campo.value.trim() === '') return;
        const accion = campo.name === 'documento' ? 'verificar_documento_ajax&documento=' : 'verificar_email_ajax&email=';
        const r = await pedir('index.php?action=' + accion + encodeURIComponent(campo.value.trim()));
        if (r.en_clinica) {
            aviso.textContent = 'Esa persona ya es parte del personal de esta clínica.';
            aviso.hidden = false;
        } else if (r.exists) {
            aviso.textContent = 'Esa persona ya tiene cuenta en Zooki: al guardar se le asignará el rol en esta clínica, sin cambiar sus datos.';
            aviso.hidden = false;
        }
    }
    form.elements.documento.addEventListener('blur', () => revisarExistente(form.elements.documento));
    form.elements.email.addEventListener('blur', () => revisarExistente(form.elements.email));

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!form.reportValidity()) return;

        const esAlta = form.elements.id_usuario.value === '';
        const r = await pedir('index.php?action=' + (esAlta ? 'registrar_usuario_ajax' : 'actualizar_usuario_ajax'), new FormData(form));
        if (r.success) {
            cerrarModal();
            await mensaje('success', r.message);
            window.location.reload();
        } else {
            mensaje('error', r.message || 'No se pudo guardar.');
        }
    });

    // ── Acciones de la tabla ──────────────────────────────────────────────
    modulo.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('[data-accion]');
        if (!boton) return;
        const id = boton.dataset.id;

        if (boton.dataset.accion === 'nuevo') {
            cerrarModal();
            abrirModal(true);
        } else if (boton.dataset.accion === 'editar') {
            editar(id);
        } else if (boton.dataset.accion === 'estado') {
            const activar = boton.dataset.estado === '1';
            const ok = await Swal.fire({
                icon: 'question',
                text: (activar ? '¿Activar a ' : '¿Desactivar a ') + boton.dataset.nombre + ' en esta clínica? Su cuenta y sus otros roles no cambian.',
                showCancelButton: true,
                confirmButtonText: activar ? 'Activar' : 'Desactivar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0052FF',
            });
            if (!ok.isConfirmed) return;
            const datos = new FormData();
            datos.append('id_usuario', id);
            datos.append('estado', boton.dataset.estado);
            const r = await pedir('index.php?action=cambiar_estado_usuario_ajax', datos);
            await mensaje(r.success ? 'success' : 'error', r.message);
            if (r.success) window.location.reload();
        } else if (boton.dataset.accion === 'restablecer') {
            const ok = await Swal.fire({
                icon: 'warning',
                text: 'Se generará una contraseña temporal para ' + boton.dataset.nombre + ' y se enviará a su correo. Deberá cambiarla al entrar.',
                showCancelButton: true,
                confirmButtonText: 'Restablecer',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#0052FF',
            });
            if (!ok.isConfirmed) return;
            const datos = new FormData();
            datos.append('id_usuario', id);
            const r = await pedir('index.php?action=resetear_password_usuario_ajax', datos);
            mensaje(r.success ? 'success' : 'error', r.message);
        }
    });
})();
