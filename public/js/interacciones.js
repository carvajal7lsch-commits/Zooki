/** C9: acciones de las vistas en data-*, sin controladores de evento en línea. */
if (!document.zookiInteraccionesRegistradas) {
    document.zookiInteraccionesRegistradas = true;
    document.addEventListener('click', ejecutarInteraccion);
    document.addEventListener('keydown', (evento) => {
        if (evento.defaultPrevented) return;
        if (!['Enter', ' '].includes(evento.key)) return;
        const elemento = evento.target.closest('[data-ui-accion][role="button"]');
        if (!elemento || elemento !== evento.target) return;
        evento.preventDefault();
        ejecutarInteraccion(evento);
    });

    /**
     * C9.1: un campo con data-caracteres solo acepta esos caracteres mientras se
     * escribe o se pega, sin pasar de su maxlength. El servidor valida lo mismo.
     */
    document.addEventListener('input', (evento) => {
        const campo = evento.target;
        if (!campo || !campo.dataset || campo.dataset.caracteres === undefined) return;
        actualizarEntrada(campo, campo.value, campo.selectionStart);
    }, true);

    // D3.1: filtrar antes de la validación y antes del maxlength nativo al pegar.
    document.addEventListener('beforeinput', (evento) => {
        const campo = evento.target;
        if (!campo?.dataset || campo.dataset.caracteres === undefined || evento.isComposing) return;
        if (evento.inputType !== 'insertText' || evento.data === null || !evento.cancelable) return;
        // type=email no expone el cursor: conservar su edición nativa y bloquear solo la tecla inválida.
        if (typeof campo.selectionStart !== 'number') {
            if (limpiarCaracteres(evento.data, campo.dataset.caracteres, 0) !== evento.data) evento.preventDefault();
            return;
        }
        insertarEntrada(evento, evento.data);
    }, true);

    document.addEventListener('paste', (evento) => {
        if (evento.target?.dataset?.caracteres === undefined || !evento.clipboardData) return;
        if (typeof evento.target.selectionStart !== 'number') return;
        insertarEntrada(evento, evento.clipboardData.getData('text'));
    }, true);

    document.addEventListener('blur', (evento) => {
        const campo = evento.target;
        if (campo?.dataset?.espacios !== 'normalizar') return;
        const anterior = campo.value;
        actualizarEntrada(campo, campo.value.trim(), campo.selectionStart);
        if (campo.value !== anterior) campo.dispatchEvent(new Event('input', { bubbles: true }));
    }, true);
}

function insertarEntrada(evento, texto) {
    const campo = evento.target;
    const inicio = campo.selectionStart ?? campo.value.length;
    const fin = campo.selectionEnd ?? inicio;
    const valor = campo.value.slice(0, inicio) + texto + campo.value.slice(fin);
    // Conservar edición, cursor y deshacer nativos cuando la entrada ya cumple.
    if (sanearEntrada(valor, campo.dataset, campo.maxLength) === valor) return;
    evento.preventDefault();
    actualizarEntrada(campo, valor, inicio + texto.length);
    campo.dispatchEvent(new Event('input', { bubbles: true }));
}

function actualizarEntrada(campo, valor, posicion) {
    const limpio = sanearEntrada(valor, campo.dataset, campo.maxLength);
    const cursor = typeof posicion === 'number' ? sanearEntrada(valor.slice(0, posicion), campo.dataset, campo.maxLength).length : null;
    if (campo.value !== limpio) campo.value = limpio;
    if (cursor !== null && typeof campo.setSelectionRange === 'function' && ['text', 'tel', 'password', 'search'].includes(campo.type)) {
        campo.setSelectionRange(cursor, cursor);
    }
}

function sanearEntrada(valor, opciones, maximo) {
    if (opciones.espacios === 'normalizar') valor = String(valor).replace(/\s+/gu, ' ');
    // M2 C8 (H8): en los signos de la consulta la coma decimal se toma como punto.
    if (opciones.formato === 'decimal') valor = String(valor).replace(/,/g, '.');
    let limpio = limpiarCaracteres(valor, opciones.caracteres, 0);
    if (opciones.formato === 'decimal') limpio = sanearDecimal(limpio, opciones);
    if (opciones.formato === 'nit') {
        const partes = limpio.split('-');
        limpio = partes[0] + (partes[0] && partes.length > 1 ? '-' + partes.slice(1).join('').slice(0, 1) : partes.slice(1).join(''));
    }
    if (opciones.espacios === 'normalizar') limpio = limpio.replace(/\s+/gu, ' ').trimStart();
    if (maximo > 0) limpio = limpio.slice(0, maximo);
    const maximoBytes = Number(opciones.maxBytes);
    if (maximoBytes > 0) {
        let salida = '';
        let bytes = 0;
        const codificador = new TextEncoder();
        for (const caracter of limpio) {
            const longitud = codificador.encode(caracter).length;
            if (bytes + longitud > maximoBytes) break;
            salida += caracter;
            bytes += longitud;
        }
        limpio = salida;
    }
    return limpio;
}

/**
 * M2 C8 (H8): un número con las cifras de su columna (data-enteros y
 * data-decimales) que no pasa de data-maximo: «20000» queda en «200» y una
 * tecla que lo haría pasar del máximo no entra. El mínimo no se impone al
 * escribir (para llegar a 38 hay que pasar por 3); lo valida el aviso del campo.
 */
function sanearDecimal(texto, opciones) {
    const enteros = Number(opciones.enteros) || 3;
    const decimales = Number(opciones.decimales) || 0;
    const [parteEntera, ...resto] = String(texto).split('.');
    let salida = parteEntera.slice(0, enteros);
    if (decimales > 0 && resto.length > 0) salida += '.' + resto.join('').slice(0, decimales);
    const tope = Number(opciones.maximo);
    while (salida !== '' && tope > 0 && Number(salida) > tope) salida = salida.slice(0, -1);
    return salida;
}

function limpiarCaracteres(valor, caracteres, maximo) {
    const fuera = new RegExp('[^' + caracteres + ']', 'gu');
    const limpio = String(valor).replace(fuera, '');
    return maximo > 0 ? limpio.slice(0, maximo) : limpio;
}

function ejecutarInteraccion(evento) {
    if (evento.target.closest('[data-ui-detener]')) return;
    const boton = evento.target.closest('[data-ui-accion]');
    if (!boton || boton.disabled) return;
    if (boton.hasAttribute('data-ui-fondo') && evento.target !== boton) return;
    if (evento.target.closest('.btn-cancel-agenda')) return;
    const id = Number(boton.dataset.uiId);
    switch (boton.dataset.uiAccion) {
        case 'cerrar-consulta':
            closeModal('modalConsulta');
            break;
        case 'pestana-consulta':
            switchModalTab({ target: evento.target, currentTarget: boton }, boton.dataset.tab);
            break;
        case 'cerrar-cita':
            closeCitaModal();
            break;
        case 'cambiar-mascota':
            limpiarMascotaSeleccionada(true);
            break;
        case 'cerrar-reprogramacion':
            cerrarModalReprogramar();
            break;
        case 'confirmar-reprogramacion':
            confirmarReprogramacion();
            break;
        case 'detalle-mascota':
            verDetalle(id);
            break;
        case 'detalle-cita-portal':
            mostrarDetalleCita(id);
            break;
        case 'cerrar-drawer':
            cerrarDrawer();
            break;
        case 'acordeon-historia':
            toggleAccordion(boton);
            break;
        case 'cancelar-cita-portal':
            cancelarCitaPortal(id);
            break;
        case 'imprimir':
            window.print();
            break;
        case 'exportar-citas-excel':
            exportarCitas();
            break;
        case 'exportar-citas-pdf':
            exportarCitasPDF();
            break;
        case 'limpiar-citas':
            limpiarFiltros();
            break;
        case 'carrusel-citas':
            scrollKanban(boton.dataset.direccion);
            break;
        case 'detalle-cita-admin':
            verDetalle(id);
            break;
        case 'exportar-auditoria-excel':
            exportarAuditoriaExcel();
            break;
        case 'exportar-auditoria-pdf':
            exportarAuditoriaPDF();
            break;
    }
}
