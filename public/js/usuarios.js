/**
 * HU-T.7 — Personal y clientes de la clínica activa (views/admin/usuarios.php).
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
    const modalDetalle = document.getElementById('clienteDetalleModal');

    // C9: preferencia local; la primera visita conserva las tarjetas de v1.12.0.
    const claveVista = 'zooki.usuarios.vista';
    function cambiarVista(vista) {
        const elegida = vista === 'tabla' ? 'tabla' : 'tarjetas';
        modulo.dataset.vista = elegida;
        modulo.querySelectorAll('[data-vista-contenido]').forEach((panel) => {
            panel.hidden = panel.dataset.vistaContenido !== elegida;
        });
        modulo.querySelectorAll('[data-vista-usuarios]').forEach((boton) => {
            const activo = boton.dataset.vistaUsuarios === elegida;
            boton.classList.toggle('active', activo);
            boton.setAttribute('aria-pressed', String(activo));
        });
        try {
            localStorage.setItem(claveVista, elegida);
        } catch (error) {
            console.debug('Preferencia de vista no disponible.', error);
        }
    }
    let vistaGuardada = 'tarjetas';
    try {
        vistaGuardada = localStorage.getItem(claveVista) || 'tarjetas';
    } catch (error) {
        console.debug('Preferencia de vista no disponible.', error);
    }
    cambiarVista(vistaGuardada);
    modulo.querySelectorAll('[data-vista-usuarios]').forEach((boton) => {
        boton.addEventListener('click', () => cambiarVista(boton.dataset.vistaUsuarios));
    });

    const aviso = modal.querySelector('[data-aviso]');
    const titulo = modal.querySelector('[data-titulo]');

    // D2.1: teléfono con bandera y prefijo, como en Pacientes (intl-tel-input).
    const telefonoPersonal = (typeof window !== 'undefined' && window.intlTelInput && form.elements.telefono)
        ? window.intlTelInput(form.elements.telefono, {
            initialCountry: 'co',
            preferredCountries: ['co', 'us', 'mx', 'es'],
            nationalMode: false,
            autoInsertDialCode: true,
            strictMode: true,
            dropdownContainer: document.body,
            utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.10/build/js/utils.js',
        })
        : null;

    const mensaje = (icono, texto) => zookiToast(texto, icono);

    async function pedir(url, datos) {
        const opciones = datos ? { method: 'POST', body: datos } : {};
        opciones.headers = { 'X-Requested-With': 'XMLHttpRequest' };
        try {
            const respuesta = await fetch(url, opciones);
            const cuerpo = await respuesta.json().catch(() => ({ success: false, message: 'Respuesta inesperada del servidor.' }));
            if (cuerpo && cuerpo.redirect && (respuesta.status === 401 || respuesta.status === 403)) {
                window.location.href = cuerpo.redirect;
            }
            return cuerpo;
        } catch (error) {
            console.error('Usuarios:', error);
            return { success: false, message: 'No se pudo conectar con el servidor.' };
        }
    }

    // ── Pestañas ──────────────────────────────────────────────────────────
    modulo.querySelectorAll('.tab-btn').forEach((boton) => {
        boton.addEventListener('click', () => {
            const pestana = boton.dataset.tab;
            modulo.querySelectorAll('.tab-btn').forEach((b) => {
                b.classList.toggle('active', b === boton);
                b.setAttribute('aria-selected', b === boton ? 'true' : 'false');
            });
            modulo.querySelectorAll('[data-panel]').forEach((panel) => {
                const visible = panel.dataset.panel === pestana;
                panel.hidden = !visible;
                panel.classList.toggle('active', visible);
            });
            modulo.querySelectorAll('[data-filtros]').forEach((barra) => {
                barra.classList.toggle('is-active', barra.dataset.filtros === pestana);
            });
        });
    });

    // ── Búsqueda y filtros (por pestaña; tarjetas y tabla a la vez) ────────────
    function filtrar(pestana) {
        const panel = modulo.querySelector('[data-panel="' + pestana + '"]');
        const barra = modulo.querySelector('[data-filtros="' + pestana + '"]');
        if (!panel || !barra) return;
        const campoTexto = barra.querySelector('[data-filtro-texto]');
        const campoRol = barra.querySelector('[data-filtro-rol]');
        const campoEstado = barra.querySelector('[data-filtro-estado]:checked');
        const texto = campoTexto ? campoTexto.value.trim().toLowerCase() : '';
        const rol = campoRol ? campoRol.value : '';
        const estado = campoEstado ? campoEstado.value : '';

        panel.querySelectorAll('[data-vista-contenido]').forEach((vista) => {
            const filas = vista.querySelectorAll('[data-fila]');
            let visibles = 0;
            filas.forEach((fila) => {
                const coincide = (texto === '' || fila.dataset.buscar.includes(texto))
                    && (rol === '' || fila.dataset.rol === rol)
                    && (estado === '' || fila.dataset.estado === estado);
                fila.hidden = !coincide;
                if (coincide) visibles++;
            });
            const sinResultados = vista.querySelector('[data-sin-resultados]');
            if (sinResultados) sinResultados.hidden = filas.length === 0 || visibles > 0;
        });
    }
    modulo.querySelectorAll('[data-filtros]').forEach((barra) => {
        barra.addEventListener('input', () => filtrar(barra.dataset.filtros));
        barra.addEventListener('change', () => filtrar(barra.dataset.filtros));
    });

    // ── Modales ───────────────────────────────────────────────────────────
    const modales = [modal, modalDetalle].filter(Boolean);

    function cerrarModal(ventana) {
        ventana.classList.remove('is-open');
        if (ventana === modal) {
            form.reset();
            form.elements.id_usuario.value = '';
            ponerEstado(true);
            if (telefonoPersonal) telefonoPersonal.setNumber('');
        }
    }

    modales.forEach((ventana) => {
        ventana.addEventListener('click', (evento) => {
            if (evento.target === ventana || evento.target.closest('[data-accion="cerrar"]')) cerrarModal(ventana);
        });
    });
    document.addEventListener('keydown', (evento) => {
        if (evento.key !== 'Escape') return;
        modales.filter((ventana) => ventana.classList.contains('is-open')).forEach(cerrarModal);
    });

    // ── Personal: alta y edición ────────────────────────────────────────────
    // D2.1: diseño de v1.12.0. «Nuevo Usuario» pide tipo y número de
    // documento; «Editar Usuario» los muestra en el subtítulo (nombre •
    // documento) y no los edita, como en producción (RE-T.7.1).
    function ponerEstado(activo) {
        const interruptor = modal.querySelector('[data-estado-interruptor]');
        const texto = modal.querySelector('[data-estado-texto]');
        if (!interruptor || !texto || !form.elements.estado) return;
        interruptor.checked = activo;
        form.elements.estado.value = activo ? '1' : '0';
        texto.textContent = activo ? 'Activo' : 'Inactivo';
        texto.classList.toggle('estado-usuario__texto--activo', activo);
        texto.classList.toggle('estado-usuario__texto--inactivo', !activo);
    }

    modal.addEventListener('change', (evento) => {
        if (evento.target.matches && evento.target.matches('[data-estado-interruptor]')) {
            ponerEstado(evento.target.checked);
        }
    });

    /**
     * D2.2: como en v1.12.0, el cliente se edita en este mismo modal con el
     * rol oculto. Se conservan las reglas de C1.7 (documento y correo solo
     * los cambia el titular) y de RN-109 (la clínica no reactiva el vínculo).
     */
    function modoCliente(esCliente, vinculoActivo = true) {
        modal.dataset.modo = esCliente ? 'cliente' : 'personal';
        modal.querySelector('[data-grupo-rol]').hidden = esCliente;
        form.elements.id_rol.disabled = esCliente;
        form.elements.documento.disabled = esCliente;
        form.elements.tipo_documento.disabled = esCliente;
        form.elements.email.readOnly = esCliente;
        form.elements.telefono.required = esCliente;
        modal.querySelector('[data-etiqueta-estado]').textContent = esCliente ? 'Vínculo con la clínica' : 'Estado';
        modal.querySelector('[data-ayuda-vinculo]').hidden = !(esCliente && !vinculoActivo);
        if (esCliente) {
            modal.querySelector('[data-estado-interruptor]').disabled = !vinculoActivo;
            aviso.textContent = 'Solo el titular cambia su documento y su correo desde su cuenta.';
            aviso.hidden = false;
        }
    }

    function abrirModal(esAlta, persona = null) {
        modoCliente(false);
        titulo.textContent = esAlta ? 'Nuevo Usuario' : 'Editar Usuario';
        const icono = modal.querySelector('[data-icono-titulo]');
        icono.classList.toggle('fa-user-plus', esAlta);
        icono.classList.toggle('fa-user-edit', !esAlta);
        const subtitulo = modal.querySelector('[data-subtitulo]');
        subtitulo.hidden = esAlta;
        modal.querySelector('[data-subtitulo-nombre]').textContent = persona ? persona.nombre_completo || '' : '';
        modal.querySelector('[data-subtitulo-documento]').textContent = persona ? persona.documento || '' : '';
        modal.querySelectorAll('[data-solo-alta]').forEach((elemento) => { elemento.hidden = !esAlta; });
        modal.querySelector('[data-texto-guardar]').textContent = esAlta ? 'Crear Usuario' : 'Guardar Cambios';
        // Nadie se desactiva a sí mismo (C1).
        const esYo = persona !== null && String(persona.id_usuario) === modal.dataset.yo;
        modal.querySelector('[data-estado-interruptor]').disabled = esYo;
        aviso.hidden = true;
        if (esAlta) bloquearIdentidad(false);
        form.elements.password.disabled = esAlta;
        modal.classList.add('is-open');
        (esAlta ? form.elements.documento : form.elements.nombre_completo).focus();
    }

    function bloquearIdentidad(bloquear) {
        form.elements.documento.readOnly = bloquear;
        form.elements.email.readOnly = bloquear;
        form.elements.tipo_documento.disabled = bloquear;
        form.elements.password.disabled = bloquear;
        if (bloquear) {
            aviso.textContent = 'Cuenta con otros vínculos: solo el titular puede cambiar documento, correo y contraseña desde su perfil.';
            aviso.hidden = false;
        }
    }

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
        if (telefonoPersonal) {
            telefonoPersonal.setNumber(u.telefono || '');
        } else {
            form.elements.telefono.value = u.telefono || '';
        }
        form.elements.id_rol.value = String(u.id_rol);
        ponerEstado(String(u.estado) === '1');
        abrirModal(false, u);
        bloquearIdentidad(!u.identidad_editable);
    }

    form.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!form.reportValidity()) return;

        if (modal.dataset.modo === 'cliente') {
            await guardarCliente();
            return;
        }
        const esAlta = form.elements.id_usuario.value === '';
        const datos = new FormData(form);
        datos.set('tipo_documento', form.elements.tipo_documento.value);
        // Con bandera: se guarda el número completo con su prefijo (+57…).
        if (telefonoPersonal && telefonoPersonal.isValidNumber()) {
            datos.set('telefono', telefonoPersonal.getNumber());
        }
        const r = await pedir('index.php?action=' + (esAlta ? 'registrar_usuario_ajax' : 'actualizar_usuario_ajax'), datos);
        if (r.success) {
            cerrarModal(modal);
            // C9.1: la lista se actualiza en cuanto responde el servidor.
            zookiRecargarConAviso(r.message);
        } else {
            mensaje('error', r.message || 'No se pudo guardar.');
        }
    });

    // ── Interruptores de estado ─────────────────────────────────────────────
    // Desactivar pide confirmación (revoca el acceso); activar no.
    async function confirmarDesactivar(interruptor, texto) {
        if (interruptor.checked) return true;
        const ok = await zookiConfirmar(texto, '¿Desactivar?', 'Desactivar');
        if (!ok) interruptor.checked = true;
        return ok;
    }

    async function cambiarEstadoPersonal(interruptor) {
        const nombre = interruptor.dataset.nombre;
        if (!(await confirmarDesactivar(interruptor, '¿Desactivar a ' + nombre + ' en esta clínica? Su cuenta y sus otros roles no cambian.'))) return;
        interruptor.disabled = true;
        const datos = new FormData();
        datos.append('id_usuario', interruptor.dataset.id);
        datos.append('estado', interruptor.checked ? '1' : '0');
        const r = await pedir('index.php?action=cambiar_estado_usuario_ajax', datos);
        if (r.success) {
            zookiRecargarConAviso(r.message);
            return;
        }
        interruptor.checked = !interruptor.checked;
        interruptor.disabled = false;
        mensaje('error', r.message || 'No se pudo cambiar el estado.');
    }

    async function cliente(id) {
        const c = await pedir('index.php?action=get_propietario_ajax&id_usuario=' + encodeURIComponent(id));
        return c && c.id_usuario ? c : null;
    }

    // D1 (RN-109): la clínica desactiva el vínculo, pero no lo reactiva; para
    // reactivarlo se envía la solicitud y el titular la confirma por correo.
    async function solicitarVinculo(c, nombre) {
        const ok = await zookiConfirmar(
            'Le enviaremos a ' + nombre + ' un correo para que confirme el vínculo con esta clínica. Quedará activo cuando lo confirme.',
            '¿Enviar la solicitud?',
            'Enviar'
        );
        if (!ok) return null;
        const datos = new FormData();
        datos.append('identificador', c.email || '');
        return pedir('index.php?action=solicitar_vinculo_propietario_ajax', datos);
    }

    async function cambiarEstadoCliente(interruptor) {
        const nombre = interruptor.dataset.nombre;
        const activar = interruptor.checked;
        // El interruptor no se enciende solo: el vínculo sigue inactivo hasta que confirme.
        if (activar) interruptor.checked = false;
        if (!activar && !(await confirmarDesactivar(interruptor, '¿Desactivar el vínculo de ' + nombre + ' con esta clínica? Su cuenta y sus otras clínicas no cambian.'))) return;
        interruptor.disabled = true;
        const c = await cliente(interruptor.dataset.id);
        let r = { success: false, message: 'No se pudo cargar el cliente.' };
        if (c && activar) {
            r = await solicitarVinculo(c, nombre);
            interruptor.disabled = false;
            if (r) mensaje(r.success ? 'success' : 'error', r.message || 'No se pudo enviar la solicitud.');
            return;
        }
        if (c) {
            const datos = new FormData();
            datos.append('id_usuario', c.id_usuario);
            datos.append('nombre_completo', c.nombre_completo || '');
            datos.append('telefono', c.telefono || '');
            datos.append('estado', '0');
            r = await pedir('index.php?action=actualizar_propietario_ajax', datos);
        }
        if (r.success) {
            zookiRecargarConAviso('Vínculo desactivado.');
            return;
        }
        interruptor.checked = true;
        interruptor.disabled = false;
        mensaje('error', r.message || 'No se pudo cambiar el vínculo.');
    }

    modulo.addEventListener('change', (evento) => {
        const interruptor = evento.target.closest('input[data-accion]');
        if (!interruptor) return;
        if (interruptor.dataset.accion === 'estado') cambiarEstadoPersonal(interruptor);
        else if (interruptor.dataset.accion === 'estado-cliente') cambiarEstadoCliente(interruptor);
    });

    // ── Clientes: edición y detalle ────────────────────────────────────────
    async function editarCliente(id) {
        const c = await cliente(id);
        if (!c) {
            mensaje('error', 'No se pudo cargar el cliente.');
            return;
        }
        form.reset();
        form.elements.id_usuario.value = c.id_usuario;
        form.elements.tipo_documento.value = c.tipo_documento || 'CC';
        form.elements.documento.value = c.documento || '';
        form.elements.nombre_completo.value = c.nombre_completo || '';
        form.elements.email.value = c.email || '';
        if (telefonoPersonal) {
            telefonoPersonal.setNumber(c.telefono || '');
        } else {
            form.elements.telefono.value = c.telefono || '';
        }
        const activo = String(c.estado) === '1';
        ponerEstado(activo);
        abrirModal(false, c);
        modoCliente(true, activo);
    }

    // Solo viajan los datos que la clínica puede cambiar del cliente (C1.7).
    async function guardarCliente() {
        const datos = new FormData();
        datos.set('id_usuario', form.elements.id_usuario.value);
        datos.set('nombre_completo', form.elements.nombre_completo.value);
        const telefono = telefonoPersonal && telefonoPersonal.isValidNumber() ? telefonoPersonal.getNumber() : form.elements.telefono.value;
        datos.set('telefono', telefono);
        datos.set('estado', form.elements.estado.value);
        const r = await pedir('index.php?action=actualizar_propietario_ajax', datos);
        if (r.success) {
            cerrarModal(modal);
            zookiRecargarConAviso('Cliente actualizado.');
        } else {
            mensaje('error', r.message || 'No se pudo guardar.');
        }
    }

    // ── Invitaciones pendientes (D2.2, RE-T.7.5) ──────────────────────────
    async function reenviarInvitacion(boton) {
        boton.disabled = true;
        const datos = new FormData();
        datos.append('id_invitacion', boton.dataset.id);
        const r = await pedir('index.php?action=reenviar_invitacion_ajax', datos);
        boton.disabled = false;
        mensaje(r.success ? 'success' : 'error', r.message || 'No se pudo reenviar la invitación.');
    }

    async function cancelarInvitacion(boton) {
        const ok = await zookiConfirmar('La invitación de ' + boton.dataset.nombre + ' dejará de servir. Si la necesitas, invítala otra vez.', '¿Cancelar la invitación?', 'Cancelar invitación');
        if (!ok) return;
        const datos = new FormData();
        datos.append('id_invitacion', boton.dataset.id);
        const r = await pedir('index.php?action=cancelar_invitacion_ajax', datos);
        if (r.success) {
            zookiRecargarConAviso(r.message);
            return;
        }
        mensaje('error', r.message || 'No se pudo cancelar la invitación.');
    }

    function itemMascota(m) {
        const item = document.createElement('div');
        item.className = 'mascota-item';
        const avatar = document.createElement('div');
        avatar.className = 'mascota-avatar';
        const inicial = document.createElement('span');
        inicial.className = 'avatar-iniciales avatar-iniciales--tabla avatar-iniciales--mascota';
        inicial.textContent = (m.nombre || '?').charAt(0).toUpperCase();
        if (m.url_foto) {
            const foto = document.createElement('img');
            foto.src = 'uploads/mascotas/' + encodeURIComponent(m.url_foto);
            foto.alt = '';
            foto.addEventListener('error', () => foto.replaceWith(inicial));
            avatar.appendChild(foto);
        } else {
            avatar.appendChild(inicial);
        }
        const info = document.createElement('div');
        info.className = 'mascota-info';
        const nombre = document.createElement('strong');
        nombre.textContent = m.nombre || '';
        const especie = document.createElement('span');
        especie.textContent = m.especie || m.nombre_especie || 'Especie sin indicar';
        const raza = document.createElement('span');
        raza.textContent = m.raza || m.raza_indicada || 'Raza sin indicar';
        info.append(nombre, especie, raza);
        item.append(avatar, info);
        return item;
    }

    async function verCliente(id) {
        if (!modalDetalle) return;
        const [c, mascotas] = await Promise.all([
            cliente(id),
            pedir('index.php?action=listar_mascotas_propietario_ajax&id_usuario=' + encodeURIComponent(id)),
        ]);
        if (!c) {
            mensaje('error', 'No se pudo cargar el cliente.');
            return;
        }
        const campo = (nombre) => modalDetalle.querySelector('[data-detalle="' + nombre + '"]');
        const palabras = (c.nombre_completo || '').trim().split(/\s+/).slice(0, 2);
        campo('iniciales').textContent = palabras.map((p) => p.charAt(0)).join('').toUpperCase();
        campo('nombre').textContent = c.nombre_completo || '';
        campo('documento').textContent = ((c.tipo_documento || '') + ' ' + (c.documento || '')).trim();
        campo('email').textContent = c.email || 'No registrado';
        campo('telefono').textContent = c.telefono || 'No registrado';

        const lista = campo('mascotas');
        lista.replaceChildren();
        const filas = Array.isArray(mascotas) ? mascotas : [];
        if (filas.length === 0) {
            const vacio = document.createElement('div');
            vacio.className = 'no-mascotas';
            vacio.textContent = 'No tiene mascotas activas en esta clínica.';
            lista.appendChild(vacio);
        }
        filas.forEach((m) => lista.appendChild(itemMascota(m)));
        modalDetalle.classList.add('is-open');
    }

    // ── Botones de tarjetas y tabla ────────────────────────────────────────
    modulo.addEventListener('click', async (evento) => {
        const boton = evento.target.closest('button[data-accion]');
        if (!boton) return;
        const id = boton.dataset.id;

        if (boton.dataset.accion === 'nuevo') {
            cerrarModal(modal);
            abrirModal(true);
        } else if (boton.dataset.accion === 'editar') {
            editar(id);
        } else if (boton.dataset.accion === 'editar-cliente') {
            editarCliente(id);
        } else if (boton.dataset.accion === 'ver-cliente') {
            verCliente(id);
        } else if (boton.dataset.accion === 'reenviar-invitacion') {
            reenviarInvitacion(boton);
        } else if (boton.dataset.accion === 'cancelar-invitacion') {
            cancelarInvitacion(boton);
        } else if (boton.dataset.accion === 'restablecer') {
            const ok = await Swal.fire({
                icon: 'warning',
                text: 'Se invalidará la contraseña actual de ' + boton.dataset.nombre + ', se cerrarán sus sesiones abiertas y se enviará un enlace para que el titular cree otra.',
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
