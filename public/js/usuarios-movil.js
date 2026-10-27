/** HU-T.7 / RNF-16: filas y acciones móviles, con los permisos ya pintados por el servidor. */
(() => {
    'use strict';
    const modulo = document.getElementById('usuariosModulo');
    if (!modulo) return;
    const pestanasUsuarios = Object.fromEntries(Array.from(modulo.querySelectorAll('[data-tab]'), boton => [boton.dataset.tab, boton]));
    const movil = window.matchMedia('(max-width: 767px)');
    const hoja = document.getElementById('usuarioAccionesMovil');
    // La delegación existente de usuarios.js sigue recibiendo los mismos controles.
    modulo.append(hoja);
    const nuevoOriginal = modulo.querySelector('[data-accion="nuevo"]');
    const nuevo = nuevoOriginal.cloneNode(true);
    nuevo.className = 'btn-create usuarios-nuevo-movil';
    nuevo.removeAttribute('data-accion');
    nuevo.replaceChildren(document.createTextNode('+'));
    nuevo.setAttribute('aria-label', 'Nuevo usuario');
    nuevo.title = 'Nuevo usuario';
    nuevo.addEventListener('click', () => nuevoOriginal.click());
    const etiquetasAccion = { editar: 'Editar', 'editar-cliente': 'Editar', restablecer: 'Restablecer contraseña', 'ver-cliente': 'Ver detalle y mascotas', 'reenviar-invitacion': 'Reenviar invitación', 'cancelar-invitacion': 'Cancelar invitación', 'reenviar-activacion': 'Reenviar enlace de activación' };
    function textoDato(fila, icono) {
        return fila.querySelector(icono)?.parentElement.textContent.trim() || '';
    }
    function datosFila(fila) {
        const cliente = fila.closest('[data-panel]').dataset.panel === 'clientes';
        const invitacion = fila.hasAttribute('data-id-invitacion');
        const rol = textoDato(fila, '.bi-shield-shaded, .bi-heart-pulse');
        const documento = textoDato(fila, '.bi-person-badge');
        const telefono = textoDato(fila, '.bi-telephone') || textoDato(fila, '.bi-phone');
        const pendienteCliente = !!fila.querySelector('.tag-mini--aviso');
        const cuentaInactiva = Array.from(fila.querySelectorAll('.tag-mini')).some(tag => tag.textContent.trim() === 'Cuenta inactiva');
        return { cliente, invitacion, rol, documento, telefono, pendienteCliente, cuentaInactiva };
    }
    function prepararFilas() {
        modulo.querySelectorAll('[data-vista-contenido="tarjetas"] [data-fila]').forEach(fila => {
            if (fila.querySelector('.usuario-fila-meta')) return;
            const datos = datosFila(fila);
            const meta = document.createElement('p');
            meta.className = 'usuario-fila-meta';
            const chip = document.createElement('span');
            chip.className = 'usuario-fila-chip';
            if (datos.invitacion || datos.pendienteCliente) {
                chip.classList.add('usuario-fila-chip--pendiente');
                chip.textContent = datos.invitacion ? 'Pendiente de activación' : 'Sin activar';
            } else chip.textContent = datos.cliente ? (fila.dataset.estado === '0' ? 'Desvinculado' : '') : (datos.cuentaInactiva ? 'Cuenta inactiva' : datos.rol);
            if (chip.textContent) meta.append(chip);
            const referencia = document.createElement('span');
            referencia.textContent = datos.cliente ? [datos.documento, datos.telefono].filter(Boolean).join(' · ') : datos.documento;
            meta.append(referencia);
            fila.querySelector('.card-body-mini').append(meta);
            fila.tabIndex = movil.matches ? 0 : -1;
            fila.setAttribute('aria-label', 'Ver datos y acciones de ' + fila.querySelector('h3').textContent.trim());
            fila.setAttribute('aria-haspopup', 'dialog');
        });
        for (const panel of ['personal', 'clientes']) {
            const filas = Array.from(modulo.querySelectorAll(`[data-panel="${panel}"] [data-vista-contenido="tarjetas"] [data-fila]`));
            const pestana = pestanasUsuarios[panel];
            let conteo = pestana.querySelector('.usuarios-tab-conteo');
            if (!conteo) {
                conteo = document.createElement('span');
                conteo.className = 'usuarios-tab-conteo';
                pestana.prepend(conteo);
            }
            if (conteo.textContent !== String(filas.length)) conteo.textContent = String(filas.length);
            modulo.querySelectorAll(`[data-filtros="${panel}"] [data-filtro-estado]`).forEach(radio => {
                const cantidad = filas.filter(fila => radio.value === '' || fila.dataset.estado === radio.value).length;
                const etiqueta = modulo.querySelector(`label[for="${radio.id}"]`);
                let numero = etiqueta.querySelector('.usuarios-estado-conteo');
                if (!numero) {
                    numero = document.createElement('span');
                    numero.className = 'usuarios-estado-conteo';
                    etiqueta.append(numero);
                }
                if (numero.textContent !== String(cantidad)) numero.textContent = String(cantidad);
            });
        }
    }
    function abrir(fila) {
        const datos = datosFila(fila);
        hoja.querySelector('[data-usuario-avatar]').textContent = fila.querySelector('.avatar-iniciales').textContent;
        hoja.querySelector('[data-usuario-nombre]').textContent = fila.querySelector('h3').textContent;
        hoja.querySelector('[data-usuario-estado]').textContent = datos.invitacion ? 'Pendiente de activación' : (datos.cliente ? (fila.dataset.estado === '1' ? 'Cliente vinculado' : 'Desvinculado') : datos.rol + ' · ' + (fila.dataset.estado === '1' ? 'Activo en la clínica' : 'Inactivo en la clínica'));
        if (datos.cuentaInactiva) hoja.querySelector('[data-usuario-estado]').textContent = datos.rol + ' · Cuenta inactiva';
        for (const clave of ['documento', 'telefono']) {
            hoja.querySelector(`[data-usuario-${clave}]`).textContent = datos[clave];
            hoja.querySelector(`[data-usuario-dato-${clave}]`).hidden = !datos[clave];
        }
        hoja.querySelector('.usuario-acciones-movil__datos').hidden = !datos.documento && !datos.telefono;
        const interruptor = fila.querySelector('.toggle-switch');
        hoja.querySelector('[data-usuario-interruptor]').replaceChildren(...(interruptor ? [interruptor.cloneNode(true)] : []));
        const acciones = hoja.querySelector('[data-usuario-acciones]');
        acciones.replaceChildren();
        fila.querySelectorAll('.card-footer-mini button[data-accion]').forEach(original => {
            const boton = original.cloneNode(true);
            boton.className = 'usuario-acciones-movil__accion';
            const texto = document.createElement('span');
            texto.textContent = etiquetasAccion[boton.dataset.accion] || boton.title;
            boton.append(texto);
            acciones.append(boton);
        });
        globalThis.zookiAbrirDialogo('usuarioAccionesMovil');
    }
    modulo.addEventListener('click', evento => {
        if (!movil.matches) return;
        if (hoja.contains(evento.target)) {
            if (evento.target.closest('[data-accion]')) hoja.close();
            return;
        }
        if (evento.target.closest('button,input,label,select,a')) return;
        const fila = evento.target.closest('[data-vista-contenido="tarjetas"] [data-fila]');
        if (fila) abrir(fila);
    }, true);
    hoja.addEventListener('change', () => hoja.close());
    modulo.addEventListener('keydown', evento => {
        if (!movil.matches || !['Enter', ' '].includes(evento.key) || evento.target.closest('button,input,select,a')) return;
        const fila = evento.target.closest('[data-vista-contenido="tarjetas"] [data-fila]');
        if (!fila) return;
        evento.preventDefault();
        abrir(fila);
    });
    function situar() {
        const cabecera = document.querySelector('.top-header .header-left');
        if (movil.matches && cabecera) cabecera.append(nuevo);
        else nuevo.remove();
        modulo.querySelectorAll('.person-card[data-fila]').forEach(fila => { fila.tabIndex = movil.matches ? 0 : -1; });
        actualizarAlta();
    }
    const modalAlta = document.getElementById('usuarioModal');
    const tiposDocumento = Array.from(document.getElementById('usuarioTipoDoc').options, opcion => [opcion, opcion.textContent]);
    const opcionRol = modulo.querySelector('[data-filtro-rol] option[value=""]');
    const textoRol = opcionRol.textContent;
    const etiquetaTipo = modalAlta.querySelector('label[for="usuarioTipoDoc"]');
    const textoTipo = etiquetaTipo.textContent;
    const buscadores = Array.from(modulo.querySelectorAll('[data-filtro-texto]'), campo => [campo, campo.placeholder]);
    function actualizarAlta() {
        if (modalAlta.dataset.alta === 'true') modalAlta.querySelector('[data-texto-guardar]').textContent = movil.matches ? 'Enviar invitación' : 'Crear Usuario';
        for (const [opcion, etiqueta] of tiposDocumento) opcion.textContent = movil.matches ? opcion.value : etiqueta;
        opcionRol.textContent = movil.matches ? 'Rol' : textoRol;
        etiquetaTipo.textContent = movil.matches ? 'Tipo' : textoTipo;
        for (const [campo, etiqueta] of buscadores) campo.placeholder = movil.matches ? 'Nombre o documento' : etiqueta;
    }
    new MutationObserver(actualizarAlta).observe(modalAlta, { attributes: true, attributeFilter: ['data-alta'] });
    movil.addEventListener('change', situar);
    new MutationObserver(prepararFilas).observe(modulo, { childList: true, subtree: true });
    prepararFilas();
    situar();
})();
