/**
 * Avisos al usuario (TR-02).
 *
 * ZOOKI_REGLAS §4 prohíbe alert() y prompt() nativos: bloquean la página, no se
 * pueden estilizar, y en varias llamadas del proyecto se usaban además con dos
 * argumentos, `alert(mensaje, "success")`, como si fueran un toast — el segundo
 * se descartaba en silencio.
 *
 * Va en su propio archivo y no dentro de extras.js porque lo necesitan los
 * cuatro layouts, incluido el portal del propietario, que no carga las
 * notificaciones push del navegador.
 *
 * Requiere SweetAlert2 cargado antes que este archivo. Si no está, cada función
 * degrada a consola en vez de romper el flujo.
 */

const ZOOKI_COLOR_OK = '#0052FF';
const ZOOKI_COLOR_ERR = '#EF4444';

/** Aviso breve, no bloqueante, en la esquina superior derecha. */
function zookiToast(mensaje, tipo = 'success') {
    if (!window.Swal) {
        console.log(`[${tipo}] ${mensaje}`);
        return Promise.resolve();
    }

    return Swal.fire({
        toast: true,
        position: 'top-end',
        icon: tipo,
        iconColor: tipo === 'success' ? ZOOKI_COLOR_OK : undefined,
        title: mensaje,
        showConfirmButton: false,
        timer: tipo === 'error' ? 4000 : 2600,
        timerProgressBar: true,
    });
}

/**
 * C9.1: recarga en cuanto responde el servidor y muestra el aviso al volver.
 *
 * Antes se esperaba a que el toast se cerrara (2–3 s) para recargar, y la
 * pantalla parecía no haber hecho nada. El aviso viaja en sessionStorage; si
 * el navegador no lo permite, se muestra igual y la recarga no espera.
 */
const ZOOKI_AVISO_PENDIENTE = 'zooki.avisoPendiente';

function zookiRecargarConAviso(mensaje, tipo = 'success') {
    try {
        sessionStorage.setItem(ZOOKI_AVISO_PENDIENTE, JSON.stringify({ mensaje, tipo }));
    } catch (error) {
        zookiToast(mensaje, tipo);
    }
    window.location.reload();
}

function zookiRedirigirConAviso(url, mensaje, tipo = 'success') {
    try {
        sessionStorage.setItem(ZOOKI_AVISO_PENDIENTE, JSON.stringify({ mensaje, tipo }));
    } catch (error) {
        zookiToast(mensaje, tipo);
    }
    window.location.href = url;
}

function zookiMostrarAvisoPendiente() {
    let aviso = null;
    try {
        aviso = JSON.parse(sessionStorage.getItem(ZOOKI_AVISO_PENDIENTE) || 'null');
        sessionStorage.removeItem(ZOOKI_AVISO_PENDIENTE);
    } catch (error) {
        return;
    }
    if (aviso && typeof aviso.mensaje === 'string' && aviso.mensaje !== '') {
        const tipos = ['success', 'error', 'warning', 'info'];
        zookiToast(aviso.mensaje, tipos.includes(aviso.tipo) ? aviso.tipo : 'success');
    }
}

document.addEventListener('DOMContentLoaded', zookiMostrarAvisoPendiente);

/** Aviso que exige confirmación; para errores que el usuario debe leer. */
function zookiAviso(mensaje, tipo = 'error', titulo = null) {
    if (!window.Swal) {
        console.log(`[${tipo}] ${mensaje}`);
        return;
    }

    Swal.fire({
        icon: tipo,
        iconColor: tipo === 'success' ? ZOOKI_COLOR_OK : undefined,
        title: titulo || (tipo === 'error' ? 'Algo salió mal' : 'Listo'),
        text: mensaje,
        confirmButtonColor: tipo === 'error' ? ZOOKI_COLOR_ERR : ZOOKI_COLOR_OK,
    });
}

/** Confirmación sí/no. Devuelve una promesa que resuelve a booleano. */
async function zookiConfirmar(mensaje, titulo = '¿Confirmas?', textoBoton = 'Sí, continuar') {
    return zookiConfirmarCorto(mensaje, textoBoton);
}

/**
 * M5 C3 (decisión del usuario): confirmación corta con el estilo de Zooki.
 * Pregunta, filas opcionales [etiqueta, valor], descripción opcional y botones.
 * Resuelve a booleano. textoVolver nombra el botón
 * que no hace nada («Volver» cuando la acción misma es «Cancelar cita»).
 *
 * M5 C4a (decisión del usuario, 2026-10-13): es el modal de Zooki en todo el
 * sistema, ya no SweetAlert: hoja desde abajo en el celular y ventana
 * centrada desde tableta (usuarios.css). Escape o tocar el fondo cancelan,
 * el foco no sale de la ventana y vuelve a quien la abrió. Los textos van con
 * textContent, sin armar HTML.
 */
function zookiConfirmarCorto(pregunta, textoBoton, filas = [], textoVolver = 'Cancelar', descripcion = '') {
    const previo = document.activeElement;
    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    const fondo = crear('div', 'zooki-confirmacion');
    const panel = crear('div', 'zooki-confirmacion__panel');
    panel.setAttribute('role', 'alertdialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-labelledby', 'zookiConfirmacionTitulo');
    const titulo = crear('h2', 'zooki-confirmacion__titulo', pregunta);
    titulo.id = 'zookiConfirmacionTitulo';
    const cabecera = crear('div', 'zooki-confirmacion__cabecera');
    const cerrarBoton = crear('button', 'zooki-confirmacion__cerrar', '×');
    cerrarBoton.type = 'button';
    cerrarBoton.setAttribute('aria-label', 'Cerrar');
    cabecera.appendChild(titulo);
    cabecera.appendChild(cerrarBoton);
    panel.appendChild(cabecera);
    if (descripcion) {
        const detalle = crear('p', 'zooki-confirmacion__descripcion', descripcion);
        detalle.id = 'zookiConfirmacionDescripcion';
        panel.setAttribute('aria-describedby', detalle.id);
        panel.appendChild(detalle);
    }
    if (filas.length) {
        const lista = crear('dl', 'zooki-confirmacion__filas');
        filas.forEach(([etiqueta, valor]) => {
            const fila = crear('div');
            fila.appendChild(crear('dt', '', etiqueta));
            fila.appendChild(crear('dd', '', valor));
            lista.appendChild(fila);
        });
        panel.appendChild(lista);
    }
    const botones = crear('div', 'zooki-confirmacion__botones');
    const cancelar = crear('button', 'btn-modal-secondary', textoVolver);
    cancelar.type = 'button';
    const aceptar = crear('button', 'btn-modal-primary', textoBoton);
    aceptar.type = 'button';
    botones.appendChild(cancelar);
    botones.appendChild(aceptar);
    panel.appendChild(botones);
    fondo.appendChild(panel);

    return new Promise((resolver) => {
        function cerrar(respuesta) {
            document.removeEventListener('keydown', teclado, true);
            fondo.remove();
            if (previo && typeof previo.focus === 'function') previo.focus();
            resolver(respuesta);
        }
        function teclado(evento) {
            if (evento.key === 'Escape') {
                // Solo cierra la confirmación, no la ventana que haya debajo.
                evento.preventDefault();
                evento.stopPropagation();
                cerrar(false);
                return;
            }
            if (evento.key === 'Tab') {
                evento.preventDefault();
                const controles = [cerrarBoton, cancelar, aceptar];
                const indice = controles.indexOf(document.activeElement);
                controles[(indice + (evento.shiftKey ? -1 : 1) + controles.length) % controles.length].focus();
            }
        }
        cerrarBoton.addEventListener('click', () => cerrar(false));
        cancelar.addEventListener('click', () => cerrar(false));
        aceptar.addEventListener('click', () => cerrar(true));
        fondo.addEventListener('click', (evento) => {
            if (evento.target === fondo) cerrar(false);
        });
        document.addEventListener('keydown', teclado, true);
        document.body.appendChild(fondo);
        aceptar.focus();
    });
}

/**
 * Escapa texto para insertarlo en HTML sin abrir un XSS.
 *
 * Vive aquí porque lo necesitan tanto el portal como el módulo clínico, y
 * estaba declarada solo en portal.js: el área del veterinario no carga ese
 * archivo, así que cualquier uso desde allí habría dado ReferenceError.
 */
function escapeHtml(str) {
    if (str == null) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}
