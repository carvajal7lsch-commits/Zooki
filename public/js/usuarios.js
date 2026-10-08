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
    const modalCliente = document.getElementById('clienteModal');
    const formCliente = document.getElementById('clienteForm');
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
    const soloAlta = modal.querySelector('[data-solo-alta]');

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
    const modales = [modal, modalCliente, modalDetalle].filter(Boolean);

    function cerrarModal(ventana) {
        ventana.classList.remove('is-open');
        if (ventana === modal) {
            form.reset();
            form.elements.id_usuario.value = '';
        } else if (ventana === modalCliente && formCliente) {
            formCliente.reset();
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
    function abrirModal(esAlta) {
        titulo.textContent = esAlta ? 'Nuevo integrante' : 'Editar integrante';
        soloAlta.hidden = !esAlta;
        aviso.hidden = true;
        if (esAlta) bloquearIdentidad(false);
        modal.classList.add('is-open');
        form.querySelector('input[name="documento"]').focus();
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
        form.elements.telefono.value = u.telefono || '';
        form.elements.id_rol.value = String(u.id_rol);
        form.elements.estado.value = String(u.estado);
        abrirModal(false);
        bloquearIdentidad(!u.identidad_editable);
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
        const datos = new FormData(form);
        datos.set('tipo_documento', form.elements.tipo_documento.value);
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
        if (!modalCliente || !formCliente) return;
        const c = await cliente(id);
        if (!c) {
            mensaje('error', 'No se pudo cargar el cliente.');
            return;
        }
        formCliente.reset();
        formCliente.elements.id_usuario.value = c.id_usuario;
        formCliente.elements.nombre_completo.value = c.nombre_completo || '';
        formCliente.elements.telefono.value = c.telefono || '';
        formCliente.elements.estado.value = String(c.estado);
        // D1 (RN-109): un vínculo inactivo no se reactiva desde aquí.
        const inactivo = String(c.estado) !== '1';
        formCliente.elements.estado.querySelector('option[value="1"]').disabled = inactivo;
        modalCliente.querySelector('[data-ayuda-vinculo]').hidden = !inactivo;
        modalCliente.querySelector('[data-cliente-identidad]').textContent =
            [(c.tipo_documento || '') + ' ' + (c.documento || ''), c.email || ''].map((v) => v.trim()).filter(Boolean).join(' · ');
        modalCliente.classList.add('is-open');
        formCliente.elements.nombre_completo.focus();
    }

    if (formCliente) {
        formCliente.addEventListener('submit', async (evento) => {
            evento.preventDefault();
            if (!formCliente.reportValidity()) return;
            const r = await pedir('index.php?action=actualizar_propietario_ajax', new FormData(formCliente));
            if (r.success) {
                cerrarModal(modalCliente);
                zookiRecargarConAviso('Cliente actualizado.');
            } else {
                mensaje('error', r.message || 'No se pudo guardar.');
            }
        });
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
