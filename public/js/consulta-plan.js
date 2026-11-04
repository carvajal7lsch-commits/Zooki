/** RE-2.8.6: un único plan persistido, con partes editables y procedencia por diagnóstico. */
(function () {
    'use strict';
    function crear(form) {
        const campo = form.querySelector('[name="plan_tratamiento"]');
        const otras = form.querySelector('[data-otras-indicaciones]');
        if (!otras) return null;
        const envoltura = form.querySelector('[data-plan-elegible]');
        const examenes = form.querySelector('[data-examenes-elegibles]');
        const recomendaciones = form.querySelector('[data-recomendaciones-elegibles]');
        let entradas = [];
        const notificar = () => campo.dispatchEvent(new Event('input', { bubbles: true }));
        function componer() {
            const seleccionados = entradas.filter(e => e.tipo === 'examen' && e.seleccionado).map(e => e.texto);
            const textos = entradas.filter(e => e.tipo === 'recomendacion' && e.texto.trim()).map(e => e.texto.trim());
            campo.value = [seleccionados.length ? 'Exámenes sugeridos: ' + seleccionados.join('; ') : '', textos.length ? 'Recomendaciones: ' + textos.join('; ') : '', otras.value.trim()].filter(Boolean).join('\n');
            return campo.value;
        }
        function pintar() {
            examenes.replaceChildren();
            recomendaciones.replaceChildren();
            envoltura.hidden = entradas.length === 0;
            for (const entrada of entradas) {
                const etiqueta = document.createElement(entrada.tipo === 'examen' ? 'label' : 'li');
                const input = document.createElement('input');
                if (entrada.tipo === 'examen') {
                    etiqueta.className = 'consulta-examen';
                    input.type = 'checkbox';
                    input.checked = entrada.seleccionado;
                    input.addEventListener('change', () => {
                        entrada.seleccionado = input.checked;
                        entrada.editado = true;
                        componer();
                        notificar();
                    });
                    etiqueta.append(input, document.createTextNode(entrada.texto));
                    examenes.append(etiqueta);
                } else {
                    input.type = 'text';
                    input.value = entrada.texto;
                    input.maxLength = Number(recomendaciones.dataset.maximo);
                    input.dataset.recomendacion = '';
                    input.setAttribute('aria-label', 'Recomendación');
                    input.addEventListener('input', () => {
                        entrada.texto = input.value;
                        entrada.editado = true;
                        componer();
                        notificar();
                    });
                    etiqueta.append(input);
                    recomendaciones.append(etiqueta);
                }
            }
            componer();
            notificar();
        }
        function recuperarLegado(legado) {
            let restante = campo.value;
            const textos = (legado?.operaciones || []).flatMap(o => (o.textos || []).map(t => ({ ...t, origen: o.codigo })))
                .filter(t => t.campo === 'plan_tratamiento' && !t.editado && Number.isInteger(t.inicio) && typeof t.texto === 'string')
                .sort((a, b) => b.inicio - a.inicio);
            for (const registro of textos) {
                if (restante.slice(registro.inicio, registro.inicio + registro.texto.length) !== registro.texto) continue;
                const texto = registro.texto.trim();
                const examenes = legado?.plan?.elegidos || legado?.plan?.examenes || [];
                const recomendaciones = legado?.plan?.recomendaciones || [];
                let recuperadas = [];
                if (texto === 'Exámenes sugeridos: ' + examenes.join('; ')) {
                    recuperadas = examenes.map(t => ({ tipo: 'examen', texto: t, seleccionado: true, editado: false, origen: registro.origen }));
                } else if (texto === 'Recomendaciones: ' + recomendaciones.join('; ')) {
                    recuperadas = recomendaciones.map(t => ({ tipo: 'recomendacion', texto: t, editado: false, origen: registro.origen }));
                }
                // Un fragmento revisado o no reconocible queda como texto propio, nunca se pierde.
                if (!recuperadas.length) continue;
                entradas.unshift(...recuperadas);
                restante = restante.slice(0, registro.inicio) + restante.slice(registro.inicio + registro.texto.length);
            }
            otras.value = restante.trim();
        }
        otras.addEventListener('input', () => {
            componer();
            notificar();
        });
        form.addEventListener('formdata', evento => evento.formData.set('plan_tratamiento', componer()));
        return {
            agregar(plan, codigo) {
                for (const texto of plan.examenes || []) {
                    if (!entradas.some(e => e.tipo === 'examen' && e.texto === texto)) entradas.push({ tipo: 'examen', texto, seleccionado: true, editado: false, origen: codigo });
                }
                for (const texto of plan.recomendaciones || []) {
                    if (!entradas.some(e => e.tipo === 'recomendacion' && e.texto === texto)) entradas.push({ tipo: 'recomendacion', texto, editado: false, origen: codigo });
                }
                pintar();
            },
            retirar(codigo) {
                entradas = entradas.filter(e => e.origen !== codigo || e.editado);
                for (const entrada of entradas) if (entrada.origen === codigo) entrada.origen = null;
                pintar();
            },
            pendiente: codigo => entradas.some(e => e.origen === codigo && !e.editado),
            exportar: () => ({ entradas, otras: otras.value }),
            restaurar(datos, legado) {
                entradas = Array.isArray(datos?.entradas) ? datos.entradas : [];
                if (datos) otras.value = datos.otras || '';
                else recuperarLegado(legado);
                pintar();
            },
            limpiar() {
                entradas = [];
                otras.value = '';
                pintar();
            },
            componer,
        };
    }
    window.ZookiPlanConsulta = { crear };
})();
