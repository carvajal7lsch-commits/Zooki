/* RNF-16: páginas locales; las marcas de filtrado y los totales son independientes de la página. */
(function (global) {
    'use strict';
    const CANTIDAD = 12;
    function rango(total, pagina = 1) {
        const paginas = Math.max(1, Math.ceil(total / CANTIDAD));
        const actual = Math.max(1, Math.min(paginas, pagina));
        return { pagina: actual, paginas, inicio: (actual - 1) * CANTIDAD, fin: Math.min(actual * CANTIDAD, total), total };
    }
    function crear(contenedor, etiqueta) {
        let pagina = 1;
        let grupos = [];
        const pie = document.createElement('footer');
        pie.className = 'directorio-paginacion';
        pie.hidden = true;
        const contador = document.createElement('span');
        contador.setAttribute('role', 'status');
        const navegacion = document.createElement('nav');
        navegacion.setAttribute('aria-label', 'Páginas de ' + etiqueta);
        function boton(texto, paso) {
            const control = document.createElement('button');
            control.type = 'button';
            control.textContent = texto;
            control.dataset.paginaPaso = String(paso);
            control.addEventListener('click', () => {
                pagina += paso;
                pintar();
            });
            return control;
        }
        const anterior = boton('Anterior', -1);
        const siguiente = boton('Siguiente', 1);
        navegacion.append(anterior, siguiente);
        pie.append(contador, navegacion);
        contenedor.append(pie);
        function pintar() {
            // HU-T.7: refrescar Clientes sustituye sus hijos y debe recuperar el mismo pie.
            if (pie.parentElement !== contenedor) contenedor.append(pie);
            const filtrados = grupos.map(filas => filas.filter(fila => !fila.hidden && fila.style.display !== 'none'));
            const datos = rango(filtrados[0]?.length || 0, pagina);
            pagina = datos.pagina;
            grupos.forEach(filas => filas.forEach(fila => fila.removeAttribute('data-fuera-pagina')));
            filtrados.forEach(filas => filas.forEach((fila, indice) => {
                if (indice < datos.inicio || indice >= datos.fin) fila.setAttribute('data-fuera-pagina', '');
            }));
            pie.hidden = datos.total <= CANTIDAD;
            contador.textContent = (datos.total ? datos.inicio + 1 : 0) + '–' + datos.fin + ' de ' + datos.total + ' ' + etiqueta;
            anterior.disabled = datos.pagina === 1;
            siguiente.disabled = datos.pagina === datos.paginas;
            return datos;
        }
        return {
            actualizar(nuevosGrupos, reiniciar = true) {
                grupos = nuevosGrupos.map(filas => Array.from(filas));
                if (reiniciar) pagina = 1;
                return pintar();
            },
        };
    }
    global.zookiPaginacionDirectorio = { crear, rango };
})(window);
