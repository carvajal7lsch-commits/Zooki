/* RNF-16: una presentación común para Pendientes, conservando los formularios y sus oyentes. */
(function () {
    'use strict';

    function nodo(etiqueta, clase, texto = '') {
        const elemento = document.createElement(etiqueta);
        elemento.className = clase;
        elemento.textContent = texto;
        return elemento;
    }

    function actualizarHora(elemento) {
        const fecha = new Date(elemento.dataset.llegada.replace(' ', 'T') + '-05:00');
        if (!Number.isFinite(fecha.getTime())) return;
        const minutos = Math.max(0, Math.floor((Date.now() - fecha.getTime()) / 60000));
        const demora = minutos < 60 ? `${minutos} min` : minutos < 1440 ? `${Math.floor(minutos / 60)} h` : `${Math.floor(minutos / 1440)} d`;
        const hora = fecha.toLocaleTimeString('es-CO', { timeZone: 'America/Bogota', hour: '2-digit', minute: '2-digit', hour12: false });
        elemento.textContent = `Llegó ${hora} · hace ${demora}`;
        elemento.title = fecha.toLocaleString('es-CO', { timeZone: 'America/Bogota' });
        elemento.dateTime = fecha.toISOString();
    }

    function preparar(tarjeta) {
        tarjeta.classList.add('ah-tarjeta');
        if (tarjeta.classList.contains('ah-item--rojo') || tarjeta.querySelector('[data-nivel="rojo"]')) tarjeta.classList.add('ah-tarjeta--roja');
        let cabeza = tarjeta.querySelector(':scope > .ah-item__head');
        const propuesta = tarjeta.querySelector('.agenda-reasignacion__nombre');
        if (!cabeza && propuesta) {
            cabeza = nodo('div', 'ah-item__head');
            cabeza.append(nodo('strong', '', propuesta.querySelector('.agenda-solo-escritorio').textContent));
            cabeza.append(nodo('span', 'ah-nivel', 'Propuesta'));
            tarjeta.prepend(cabeza);
            const resumen = tarjeta.querySelector('.ah-item__texto');
            resumen.textContent = ['hora', 'plazo'].map(clave => tarjeta.querySelector(`.agenda-reasignacion__${clave} .agenda-solo-escritorio`).textContent).join(' · ');
        }
        const nombre = cabeza?.querySelector('strong, h3');
        if (nombre) nombre.title = nombre.textContent;
        if (cabeza && tarjeta.dataset.llegada) {
            const hora = nodo('time', 'ah-tarjeta__hora');
            hora.dataset.llegada = tarjeta.dataset.llegada;
            actualizarHora(hora);
            cabeza.append(hora);
            tarjeta.querySelector(':scope > .ah-item__meta')?.remove();
        } else if (nombre && /^\d/.test(nombre.textContent) && nombre.textContent.includes(' · ')) {
            const separador = nombre.textContent.indexOf(' · ');
            cabeza.append(nodo('span', 'ah-tarjeta__hora', nombre.textContent.slice(0, separador)));
            nombre.textContent = nombre.textContent.slice(separador + 3);
            nombre.title = nombre.textContent;
        }

        const descripciones = Array.from(tarjeta.querySelectorAll(':scope > .ah-item__texto, :scope > .ah-item__meta'));
        if (descripciones.length) {
            const descripcion = descripciones[0];
            descripcion.textContent = descripciones.map(elemento => elemento.textContent).filter(Boolean).join(' · ');
            descripcion.classList.add('ah-tarjeta__resumen');
            descripcion.title = descripcion.textContent;
            descripciones.slice(1).forEach(elemento => elemento.remove());
        }

        const acciones = nodo('div', 'ah-tarjeta__acciones');
        Array.from(tarjeta.children).filter(elemento => ['FORM', 'BUTTON', 'DETAILS'].includes(elemento.tagName) && !elemento.classList.contains('triage-detalle-completo')).forEach(elemento => {
            let accion = elemento;
            let boton = elemento;
            if (elemento.tagName === 'FORM') {
                boton = elemento.querySelector('[type="submit"]');
                const tieneCampos = Array.from(elemento.querySelectorAll('input, select, textarea')).some(campo => {
                    const oculto = campo.closest('[hidden]');
                    return campo.type !== 'hidden' && !campo.disabled && (!oculto || !elemento.contains(oculto));
                });
                if (tieneCampos) {
                    accion = nodo('details', 'ah-tarjeta__desplegable');
                    const etiqueta = tarjeta.closest('[data-agenda-solicitudes]') ? 'Revisar nivel' : boton.textContent;
                    const resumen = nodo('summary', 'ah-tarjeta__boton', etiqueta);
                    accion.append(resumen, elemento);
                    boton = resumen;
                } else {
                    elemento.classList.add('ah-tarjeta__form-directo');
                }
            } else if (elemento.tagName === 'DETAILS') {
                boton = elemento.querySelector('summary');
                elemento.classList.add('ah-tarjeta__desplegable');
            }
            boton.classList.add('ah-tarjeta__boton');
            if (!acciones.children.length) boton.classList.add('ah-tarjeta__boton--principal');
            acciones.append(accion);
        });
        if (tarjeta.dataset.ingreso && !tarjeta.closest('[data-rojos-lista]')) {
            const ficha = tarjeta.closest('[data-agenda-herramientas]').querySelector(`[data-conciliar-identidad="${tarjeta.dataset.ingreso}"]`);
            if (ficha) {
                const completar = nodo('button', 'ah-tarjeta__boton', 'Completar ficha');
                completar.type = 'button';
                completar.addEventListener('click', () => ficha.click());
                acciones.insertBefore(completar, Array.from(acciones.children).find(elemento => elemento.tagName === 'DETAILS') || null);
            }
        }
        if (acciones.children.length) tarjeta.append(acciones);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const raiz = document.querySelector('.agenda-herramientas--tarjetas');
        if (!raiz) return;
        const actualizar = () => raiz.querySelectorAll('.ah-lista > .ah-item:not(.ah-tarjeta)').forEach(preparar);
        const observador = new MutationObserver(actualizar);
        observador.observe(raiz, { childList: true, subtree: true });
        actualizar();
        window.setInterval(() => raiz.querySelectorAll('.ah-tarjeta__hora[data-llegada]').forEach(actualizarHora), 60000);
    });
})();
