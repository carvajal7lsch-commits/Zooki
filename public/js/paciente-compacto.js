/* M2 C8.1 (H10): desplegables de presentación; los permisos y los datos no cambian. */
(function (global) {
    'use strict';
    const doc = global.document;

    function plegar(raiz) {
        if (!raiz) return;
        for (const tipo of ['alertas', 'ficha']) {
            raiz.classList.remove('is-' + tipo + '-abierta');
            const boton = raiz.querySelector('[data-paciente-' + tipo + ']');
            if (boton) boton.setAttribute('aria-expanded', 'false');
        }
    }

    function actualizarAlertas(contenedor, activas) {
        const raiz = contenedor ? contenedor.closest('[data-paciente-compacto]') : null;
        if (!raiz) return;
        const boton = raiz.querySelector('[data-paciente-alertas]');
        if (!boton) return;
        if (!Array.isArray(activas)) {
            boton.textContent = 'Alertas sin cargar';
            boton.classList.remove('paciente-compacto__alertas--alergia');
            boton.setAttribute('aria-label', 'No se pudieron cargar las alertas');
            return;
        }
        const texto = activas.length + (activas.length === 1 ? ' alerta' : ' alertas');
        boton.textContent = texto;
        boton.classList.toggle('paciente-compacto__alertas--alergia', activas.some(alerta => alerta.tipo === 'alergia'));
        boton.setAttribute('aria-label', texto + (activas.some(alerta => alerta.tipo === 'alergia') ? ', incluye alergias' : ''));
    }

    function preparar(raiz) {
        if (!raiz) return;
        plegar(raiz);
        const boton = raiz.querySelector('[data-paciente-alertas]');
        if (!boton) return;
        boton.textContent = 'Cargando alertas';
        boton.setAttribute('aria-label', 'Cargando alertas');
        boton.classList.remove('paciente-compacto__alertas--alergia');
    }

    function acomodarAlertasModal() {
        if (!global.matchMedia) return;
        const modal = doc.getElementById('modalConsulta');
        if (!modal) return;
        const alertas = modal.querySelector('[data-alertas-consulta]');
        const destino = doc.getElementById(global.matchMedia('(max-width: 767px)').matches ? 'consultaAlertas' : 'consultaAlertasEscritorio');
        if (alertas && destino) destino.appendChild(alertas);
    }

    function iniciar() {
        acomodarAlertasModal();
        if (global.addEventListener) global.addEventListener('resize', acomodarAlertasModal);
        for (const raiz of doc.querySelectorAll('[data-paciente-compacto]')) {
            for (const tipo of ['alertas', 'ficha']) {
                const boton = raiz.querySelector('[data-paciente-' + tipo + ']');
                if (!boton) continue;
                boton.addEventListener('click', () => {
                    const abierta = boton.getAttribute('aria-expanded') !== 'true';
                    raiz.classList.toggle('is-' + tipo + '-abierta', abierta);
                    boton.setAttribute('aria-expanded', String(abierta));
                });
            }
        }
    }

    global.ZookiPacienteCompacto = { actualizarAlertas, plegar, preparar };
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})(window);
