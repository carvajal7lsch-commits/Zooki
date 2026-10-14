/**
 * M5 C3 — El propietario de una mascota nueva, del lado del personal
 * (HU-1.5, RE-1.5.1, RN-109, RN-111; Modelos §12.3).
 *
 * - Búsqueda en tiempo real: al completar un documento (5 a 15 dígitos) o un
 *   correo válido, se busca solo, tras una espera corta. M5 C4b: también por
 *   nombre (3 letras o más), pero solo entre los clientes activos de esta
 *   clínica; el servidor limita las búsquedas por usuario (120 en 15 minutos).
 * - M5 C4b (captura 07): un solo bloque. Ya elegido, el propietario se ve en
 *   su tarjeta mini, con «Reenviar enlace» y «Cambiar» como botones de icono.
 * - Sin vínculo activo el servidor manda solo el aviso; la respuesta es la
 *   misma para una cuenta nunca vinculada, desvinculada o de plataforma
 *   (C1, C1.1). Con el vínculo, el cliente y sus mascotas en las tarjetas
 *   mini de Usuarios, para vincular la que ya existe en lugar de duplicarla.
 * - Vincular se pone al día en su sitio, con un aviso breve, sin recargar.
 * - Un cliente que aún no activa su cuenta lo dice, con «Reenviar enlace».
 *
 * Nada de style.display: se usan hidden y clases.
 */
(function () {
    const ESPERA_MS = 450;
    const AYUDA = 'Escribe el nombre, el documento o el correo completo: lo buscamos solo.';
    const SIN_NOMBRE = 'Ningún cliente de esta clínica con ese nombre. Búscalo por documento o correo, o regístralo.';
    /** El mismo mínimo de PropietarioClinica::NOMBRE_MIN. */
    const NOMBRE_MIN = 3;
    const BUSCANDO = 'Buscando…';
    const SIN_COINCIDENCIA = 'No hay una cuenta con ese dato. Regístralo en la pestaña Propietario.';
    const PENDIENTE = 'Aún no activa su cuenta';

    let espera = null;
    let turno = 0;

    /** Documento de 5 a 15 dígitos o correo con forma válida (las mismas reglas de ValidadorCuenta). */
    function identificadorCompleto(valor) {
        const limpio = String(valor || '').trim();
        if (/^\d{5,15}$/.test(limpio)) return true;
        return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(limpio);
    }

    /** Un nombre para buscar entre los clientes: letras y espacios, sin dígitos ni @ (ValidadorCuenta::NOMBRE_CARACTERES). */
    function esNombre(valor) {
        const limpio = String(valor || '').trim();
        return limpio.length >= NOMBRE_MIN && /^[\p{L}\p{M} '’\-]+$/u.test(limpio);
    }

    /** 'exacta' (documento o correo completos), 'nombre' o null si todavía no alcanza. */
    function tipoDeBusqueda(valor) {
        if (identificadorCompleto(valor)) return 'exacta';
        if (esNombre(valor)) return 'nombre';
        return null;
    }

    function nodo(etiqueta, clase, texto) {
        const elemento = document.createElement(etiqueta);
        if (clase) elemento.className = clase;
        if (texto !== undefined) elemento.textContent = texto;
        return elemento;
    }

    function boton(clase, texto) {
        const elemento = nodo('button', clase, texto);
        elemento.type = 'button';
        return elemento;
    }

    /** Botón de icono de las tarjetas mini (Bootstrap Icons), con su nombre accesible. */
    function botonIcono(icono, etiqueta) {
        const elemento = boton('action-btn-mini');
        elemento.title = etiqueta;
        elemento.setAttribute('aria-label', etiqueta);
        const i = nodo('i', 'bi ' + icono);
        i.setAttribute('aria-hidden', 'true');
        elemento.append(i);
        return elemento;
    }

    function iniciales(nombre) {
        const partes = String(nombre || '').trim().split(/\s+/).filter(Boolean);
        return partes.slice(0, 2).map(parte => parte.charAt(0).toUpperCase()).join('') || '?';
    }

    function ayuda(texto, error = false) {
        const linea = document.getElementById('ownerSearchAyuda');
        if (!linea) return;
        linea.textContent = texto;
        linea.classList.toggle('cuenta-validacion--error', error);
    }

    async function enviar(url, datos) {
        const respuesta = await fetch(url, { method: 'POST', body: datos });
        return respuesta.json();
    }

    /** La tarjeta mini del cliente, como en Usuarios › Clientes. */
    function tarjetaPropietario(propietario, elegido = false) {
        const tarjeta = nodo('article', 'client-card person-card busqueda-propietario__cliente');
        const cabecera = nodo('div', 'card-header-mini');
        cabecera.append(nodo('span', 'avatar-mini avatar-iniciales avatar-iniciales--cliente', iniciales(propietario.nombre_completo)));
        const cuerpo = nodo('div', 'card-body-mini');
        cuerpo.append(nodo('h3', 'card-title-mini', propietario.nombre_completo));
        const etiquetas = nodo('div', 'card-tags-mini');
        etiquetas.append(nodo('span', 'tag-mini', `${propietario.mascotas.length} mascota(s) en Zooki`));
        if (propietario.pendiente) etiquetas.append(nodo('span', 'tag-mini tag-mini--aviso', PENDIENTE));
        if (propietario.documento) etiquetas.append(nodo('span', 'tag-mini', propietario.documento));
        cuerpo.append(etiquetas);
        tarjeta.append(cabecera, cuerpo);
        const acciones = [];
        if (propietario.pendiente) {
            const reenviar = botonIcono('bi-envelope-arrow-up-fill', 'Reenviar el enlace de activación');
            reenviar.dataset.reenviarActivacion = String(propietario.id_usuario);
            acciones.push(reenviar);
        }
        if (elegido) {
            const cambiar = botonIcono('bi-x-lg', 'Cambiar de propietario');
            cambiar.dataset.quitarPropietario = '';
            acciones.push(cambiar);
        }
        if (acciones.length) {
            const pie = nodo('div', 'card-footer-mini');
            pie.append(...acciones);
            tarjeta.append(pie);
        }
        return tarjeta;
    }

    /** M5 C4b: un cliente encontrado por nombre; al elegirlo se trae su ficha con la búsqueda exacta. */
    function filaResultado(cliente) {
        const fila = boton('propietario-resultado');
        fila.dataset.identificadorCliente = cliente.identificador;
        const avatar = nodo('span', 'avatar-mini avatar-iniciales avatar-iniciales--cliente', iniciales(cliente.nombre_completo));
        avatar.setAttribute('aria-hidden', 'true');
        const texto = nodo('span', 'propietario-resultado__texto');
        texto.append(nodo('strong', '', cliente.nombre_completo));
        texto.append(nodo('span', '', cliente.documento || ''));
        fila.append(avatar, texto);
        if (cliente.pendiente) fila.append(nodo('span', 'tag-mini tag-mini--aviso', PENDIENTE));
        return fila;
    }

    function pintarResultados(panel, clientes) {
        panel.replaceChildren();
        if (!clientes.length) {
            ayuda(SIN_NOMBRE);
            return;
        }
        ayuda(clientes.length === 1 ? 'Elige al cliente.' : `Elige al cliente (${clientes.length}).`);
        const lista = nodo('ul', 'propietario-resultados');
        clientes.forEach(cliente => {
            const item = nodo('li');
            item.append(filaResultado(cliente));
            lista.append(item);
        });
        panel.append(lista);
    }

    /** RE-1.5.1: nombre, especie, raza y foto; sin datos clínicos. */
    function tarjetaMascota(mascota) {
        const tarjeta = nodo('article', 'client-card person-card pet-card mascota-existente');
        tarjeta.dataset.idMascota = String(mascota.id_mascota);
        const cabecera = nodo('div', 'card-header-mini');
        const foto = nodo('img', 'avatar-mini');
        foto.src = typeof fotoMascotaUrl === 'function' ? fotoMascotaUrl(mascota.url_foto) : 'img/default-pet.svg';
        foto.alt = '';
        foto.addEventListener('error', () => { foto.src = 'img/default-pet.svg'; }, { once: true });
        cabecera.append(foto);
        const cuerpo = nodo('div', 'card-body-mini');
        cuerpo.append(nodo('h3', 'card-title-mini', mascota.nombre));
        const raza = typeof razaConIndicada === 'function' ? razaConIndicada(mascota.nombre_raza, mascota.raza_indicada) : (mascota.nombre_raza || '');
        cuerpo.append(nodo('span', 'contact-text-mini', [mascota.nombre_especie, raza].filter(Boolean).join(' · ')));
        const pie = nodo('div', 'card-footer-mini');
        const vincular = boton('action-btn-mini', 'Vincular a la clínica');
        vincular.dataset.vincularMascota = String(mascota.id_mascota);
        vincular.dataset.nombre = mascota.nombre;
        pie.append(vincular);
        tarjeta.append(cabecera, cuerpo, pie);
        return tarjeta;
    }

    /** Lo que muestra el panel según la respuesta del servidor. */
    function pintar(panel, propietario, identificador) {
        panel.replaceChildren();
        panel.dataset.identificador = identificador;
        if (!propietario) {
            ayuda(SIN_COINCIDENCIA);
            return;
        }
        // RE-1.5.1 y RN-109: sin vínculo activo el servidor no manda nombre, contacto ni mascotas.
        if (!propietario.vinculado) {
            ayuda(propietario.mensaje);
            const pedir = boton('btn-modal-secondary', 'Pedir confirmación por correo');
            pedir.dataset.pedirConfirmacion = identificador;
            panel.append(pedir);
            return;
        }
        panel.propietario = propietario;
        if (!propietario.mascotas.length) {
            // M5 C4b (captura 07): sin mascotas que vincular, se elige directo, en un solo bloque.
            elegirPropietario(propietario);
            return;
        }
        ayuda('Si la mascota ya está en Zooki, vincúlala en lugar de registrarla otra vez.');
        panel.append(tarjetaPropietario(propietario));
        const lista = nodo('div', 'mascotas-existentes__lista');
        propietario.mascotas.forEach(mascota => lista.append(tarjetaMascota(mascota)));
        panel.append(lista);
        const nueva = boton('btn-modal-secondary', 'Es otra mascota: registrar nueva');
        nueva.dataset.elegirPropietario = String(propietario.id_usuario);
        panel.append(nueva);
    }

    async function buscar(valor) {
        const panel = document.getElementById('mascotasExistentes');
        if (!panel) return;
        const miTurno = ++turno;
        ayuda(BUSCANDO);
        try {
            const respuesta = await fetch(`index.php?action=buscar_propietario_exacto_ajax&identificador=${encodeURIComponent(valor.trim())}`);
            const datos = await respuesta.json();
            if (miTurno !== turno) return;
            if (!datos.success) {
                panel.replaceChildren();
                ayuda(datos.message || 'No se pudo buscar. Intenta de nuevo.', true);
                return;
            }
            pintar(panel, datos.propietario, valor.trim());
        } catch (error) {
            if (miTurno === turno) ayuda('No se pudo buscar. Revisa la conexión.', true);
        }
    }

    /** M5 C4b: por nombre, solo entre los clientes activos de esta clínica. */
    async function buscarNombre(valor) {
        const panel = document.getElementById('mascotasExistentes');
        if (!panel) return;
        const miTurno = ++turno;
        ayuda(BUSCANDO);
        try {
            const respuesta = await fetch(`index.php?action=buscar_propietario_nombre_ajax&nombre=${encodeURIComponent(valor.trim())}`);
            const datos = await respuesta.json();
            if (miTurno !== turno) return;
            if (!datos.success) {
                panel.replaceChildren();
                ayuda(datos.message || 'No se pudo buscar. Intenta de nuevo.', true);
                return;
            }
            pintarResultados(panel, datos.clientes || []);
        } catch (error) {
            if (miTurno === turno) ayuda('No se pudo buscar. Revisa la conexión.', true);
        }
    }

    /** Al escribir: espera corta y busca solo con un dato completo o un nombre. */
    function alEscribir(campo) {
        clearTimeout(espera);
        quitarPropietario(false);
        const panel = document.getElementById('mascotasExistentes');
        if (panel) panel.replaceChildren();
        turno++;
        const tipo = tipoDeBusqueda(campo.value);
        if (tipo === null) {
            ayuda(AYUDA);
            return;
        }
        espera = setTimeout(() => (tipo === 'exacta' ? buscar(campo.value) : buscarNombre(campo.value)), ESPERA_MS);
    }

    /** Buscar ya (desde la ficha del cliente o tras registrarlo). */
    function buscarAhora(valor) {
        const campo = document.getElementById('ownerSearchInput');
        if (!campo) return;
        campo.value = String(valor || '');
        quitarPropietario(false);
        if (identificadorCompleto(campo.value)) buscar(campo.value);
    }

    /** M5 C4b (captura 07): el elegido queda en su tarjeta mini; la búsqueda y sus resultados se ocultan. */
    function elegirPropietario(propietario) {
        document.getElementById('petOwnerDoc').value = propietario.id_usuario;
        document.getElementById('ownerSearchWrapper').hidden = true;
        const panel = document.getElementById('mascotasExistentes');
        if (panel) panel.replaceChildren();
        const elegido = document.getElementById('selectedOwnerInfo');
        elegido.replaceChildren(tarjetaPropietario(propietario, true));
        elegido.hidden = false;
        ayuda('');
    }

    function quitarPropietario(limpiarCampo = true) {
        const id = document.getElementById('petOwnerDoc');
        if (id) id.value = '';
        const envoltura = document.getElementById('ownerSearchWrapper');
        if (envoltura) envoltura.hidden = false;
        const elegido = document.getElementById('selectedOwnerInfo');
        if (elegido) {
            elegido.hidden = true;
            elegido.replaceChildren();
        }
        if (!limpiarCampo) return;
        const campo = document.getElementById('ownerSearchInput');
        if (campo) {
            campo.value = '';
            campo.focus();
        }
        const panel = document.getElementById('mascotasExistentes');
        if (panel) panel.replaceChildren();
        ayuda(AYUDA);
    }

    async function pedirConfirmacion(control) {
        control.disabled = true;
        const datos = new FormData();
        datos.set('identificador', control.dataset.pedirConfirmacion);
        try {
            const respuesta = await enviar('index.php?action=solicitar_vinculo_propietario_ajax', datos);
            zookiToast(respuesta.message, respuesta.success ? 'success' : 'error');
        } catch (error) {
            zookiToast('No se pudo enviar la solicitud.', 'error');
        } finally {
            control.disabled = false;
        }
    }

    /** RE-1.5.2/3: vincula sin duplicar; la tarjeta y la lista se ponen al día en su sitio. */
    async function vincular(control) {
        const panel = document.getElementById('mascotasExistentes');
        const propietario = panel.propietario;
        control.disabled = true;
        const datos = new FormData();
        datos.set('id_propietario', propietario.id_usuario);
        datos.set('id_mascota', control.dataset.vincularMascota);
        try {
            const respuesta = await enviar('index.php?action=vincular_mascota_ajax', datos);
            if (!respuesta.success) {
                control.disabled = false;
                zookiToast(respuesta.message || 'No se pudo vincular la mascota.', 'error');
                return;
            }
            const marca = nodo('span', 'tag-mini tag-mini--azul', 'Vinculada a la clínica');
            control.replaceWith(marca);
            zookiToast(`${control.dataset.nombre} quedó vinculada a la clínica.`);
            refrescarPacientes();
        } catch (error) {
            control.disabled = false;
            zookiToast('No se pudo vincular la mascota.', 'error');
        }
    }

    /** M5 C3: el enlace de activación nuevo vence en 72 horas; el anterior deja de servir. */
    async function reenviarActivacion(control) {
        control.disabled = true;
        const datos = new FormData();
        datos.set('id_usuario', control.dataset.reenviarActivacion);
        try {
            const respuesta = await enviar('index.php?action=reenviar_activacion_propietario_ajax', datos);
            zookiToast(respuesta.message, respuesta.success ? 'success' : 'error');
        } catch (error) {
            zookiToast('No se pudo reenviar el enlace.', 'error');
        } finally {
            control.disabled = false;
        }
    }

    /**
     * Las tarjetas y la tabla de pacientes salen del servidor: se piden otra
     * vez y se cambian solo esas partes, sin recargar la página.
     */
    async function refrescarPacientes() {
        const partes = ['petsGrid', 'petsTableBody'];
        try {
            const html = await (await fetch(window.location.href, { headers: { 'X-Requested-With': 'fetch' } })).text();
            const nuevo = new DOMParser().parseFromString(html, 'text/html');
            partes.forEach(id => {
                const actual = document.getElementById(id);
                const recibido = nuevo.getElementById(id);
                if (!actual || !recibido) return;
                actual.innerHTML = recibido.innerHTML;
                if (typeof enlazarEventosPacientes === 'function') enlazarEventosPacientes(actual);
            });
            if (typeof filterTable === 'function') filterTable();
        } catch (error) {
            console.error('No se pudo poner al día la lista de pacientes:', error);
        }
    }

    /** RN-109: al registrar un documento o correo con cuenta, el aviso y «Pedir confirmación». */
    function mostrarExistente(contenedor, respuesta) {
        contenedor.replaceChildren();
        contenedor.append(nodo('p', 'propietario-existente__aviso', respuesta.message));
        if (!respuesta.vinculado) {
            const pedir = boton('btn-modal-secondary', 'Pedir confirmación por correo');
            pedir.dataset.pedirConfirmacion = respuesta.identificador;
            contenedor.append(pedir);
        }
        contenedor.hidden = false;
    }

    function mostrarError(contenedor, mensaje) {
        contenedor.replaceChildren(nodo('p', 'propietario-existente__aviso propietario-existente__aviso--error', mensaje));
        contenedor.hidden = false;
    }

    function limpiarExistente(contenedor) {
        contenedor.replaceChildren();
        contenedor.hidden = true;
    }

    document.addEventListener('input', (evento) => {
        if (evento.target.matches && evento.target.matches('[data-buscar-propietario]')) alEscribir(evento.target);
    });

    document.addEventListener('click', (evento) => {
        const control = evento.target.closest ? evento.target.closest('button') : null;
        if (!control) return;
        if (control.dataset.pedirConfirmacion !== undefined) pedirConfirmacion(control);
        else if (control.dataset.vincularMascota !== undefined) vincular(control);
        else if (control.dataset.reenviarActivacion !== undefined) reenviarActivacion(control);
        else if (control.dataset.elegirPropietario !== undefined) elegirPropietario(document.getElementById('mascotasExistentes').propietario);
        else if (control.dataset.quitarPropietario !== undefined) quitarPropietario(true);
        else if (control.dataset.identificadorCliente !== undefined) buscar(control.dataset.identificadorCliente);
    });

    window.ZookiPropietarioMascota = {
        identificadorCompleto,
        tipoDeBusqueda,
        buscarNombre,
        pintarResultados,
        pintar,
        buscar,
        buscarAhora,
        alEscribir,
        elegirPropietario,
        quitarPropietario,
        mostrarExistente,
        mostrarError,
        limpiarExistente,
        refrescarPacientes,
        ESPERA_MS,
    };
})();
