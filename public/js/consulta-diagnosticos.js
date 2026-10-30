/**
 * M2 C5a — HU-2.8 (RE-2.8.1 a RE-2.8.4, RN-209, RN-202): diagnósticos
 * sugeridos en la consulta, antes del campo de diagnóstico.
 *
 * Al cambiar los síntomas (con una espera corta) pide sugerir_diagnosticos_ajax
 * y muestra hasta 5 candidatos con su cobertura, los síntomas que coinciden y
 * la fuente, sin porcentajes. «Usar» agrega diagnóstico, plan y pautas editables
 * con Deshacer (2.3.0); el veterinario revisa antes de guardar. El campo sigue
 * siendo obligatorio. Los datos que ayudarían (revisión de C2) salen con
 * nombre legible; vacunación y días desde el celo tienen un campo opcional que
 * vuelve a calcular y no se guarda en la consulta.
 *
 * M2 C8 (H7): cada candidato es una fila compacta con su nombre, la cobertura y
 * «Usar»; la fila se expande para ver los síntomas que coinciden y la fuente.
 */
(function () {
    'use strict';

    const RUTA_SUGERIR = 'index.php?action=sugerir_diagnosticos_ajax';
    const ESPERA_MS = 400;
    const MAXIMO = 5;
    const APOYO = 'Sugerencia de apoyo · pendiente de validación veterinaria';
    const TEXTOS = {
        sinSintomas: 'Marca síntomas en «Motivo» para ver diagnósticos probables del conocimiento.',
        buscando: 'Buscando diagnósticos probables…',
        sinCoincidencias: 'Sin coincidencias en el conocimiento: escribe el diagnóstico.',
        error: 'No se pudieron calcular las sugerencias. Escribe el diagnóstico.',
    };

    /** Nombre legible de cada dato que informa el motor; los códigos internos nunca se muestran. */
    const DATOS = {
        vacunacion: { etiqueta: 'Estado de vacunación', editable: true },
        dias_desde_celo: { etiqueta: 'Días desde el último celo', editable: true },
        edad_meses: { etiqueta: 'Edad (falta la fecha de nacimiento en la ficha)', editable: false },
        sexo: { etiqueta: 'Sexo (en la ficha)', editable: false },
        esterilizado: { etiqueta: 'Si está esterilizado (en la ficha)', editable: false },
        especie: { etiqueta: 'Especie (en la ficha)', editable: false },
        raza: { etiqueta: 'Datos de la raza (en la ficha)', editable: false },
        inicio: { etiqueta: 'Desde cuándo empezaron los síntomas', editable: false },
        exposicion: { etiqueta: 'Exposición a un tóxico', editable: false },
    };

    function claveDato(campo) {
        if (typeof campo !== 'string') return null;
        if (Object.prototype.hasOwnProperty.call(DATOS, campo)) return campo;
        if (campo.startsWith('raza.')) return 'raza';
        if (campo.startsWith('sintomas.') && campo.endsWith('.minutos')) return 'inicio';
        if (campo.startsWith('exposiciones')) return 'exposicion';
        return null;
    }

    /** datos_faltantes del motor → [{clave, etiqueta, editable}] sin repetir y sin códigos. */
    function datosLegibles(faltantes) {
        const claves = [];
        for (const faltante of Array.isArray(faltantes) ? faltantes : []) {
            for (const campo of Array.isArray(faltante && faltante.campos) ? faltante.campos : []) {
                const clave = claveDato(campo);
                if (clave !== null && !claves.includes(clave)) claves.push(clave);
            }
        }
        return claves.map(clave => ({ clave, etiqueta: DATOS[clave].etiqueta, editable: DATOS[clave].editable }));
    }

    function urlSegura(url) {
        return typeof url === 'string' && /^https?:\/\//i.test(url) ? url : null;
    }

    /** La fuente que se muestra: la clínica primero; el resto se cuenta. */
    function fuentePrincipal(fuentes) {
        const lista = (Array.isArray(fuentes) ? fuentes : []).filter(f => f && (f.titulo || f.entidad));
        if (lista.length === 0) return null;
        const principal = lista.find(f => f.fundamento === 'clinico') || lista[0];
        const texto = [principal.entidad, principal.titulo].filter(Boolean).join(' — ');
        return { texto, url: urlSegura(principal.url), otras: lista.length - 1 };
    }

    /** Copia la etiqueta al campo (sin borrar lo escrito) y deja editar; RE-2.8.2. */
    function usarDiagnostico(campo, etiqueta) {
        const actual = campo.value.trim();
        const lineas = actual.split('\n').map(linea => linea.trim());
        if (!lineas.includes(etiqueta)) {
            campo.value = actual === '' ? etiqueta : `${actual}\n${etiqueta}`;
            campo.dispatchEvent(new Event('input', { bubbles: true }));
        }
        campo.focus();
    }

    function cuerpoPeticion(idMascota, sintomas, datos) {
        const cuerpo = new URLSearchParams();
        cuerpo.append('id_mascota', String(idMascota));
        sintomas.forEach(sintoma => cuerpo.append('sintomas[]', sintoma.codigo));
        Object.entries(datos).forEach(([clave, valor]) => {
            if (valor !== '') cuerpo.append(clave, valor);
        });
        return cuerpo;
    }

    function elemento(etiqueta, clase, texto) {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    }

    let detalles = 0;

    /** Abre o cierra el detalle de una fila (síntomas que coinciden y fuente). */
    function alternarDetalle(item, boton, detalle) {
        const abierto = boton.getAttribute('aria-expanded') === 'true';
        boton.setAttribute('aria-expanded', String(!abierto));
        detalle.hidden = abierto;
        item.classList.toggle('is-abierta', !abierto);
    }

    /**
     * Prepara un bloque [data-diagnosticos]. opciones.idMascota() da la
     * mascota; opciones.campoDiagnostico es el textarea obligatorio.
     */
    function crear(raiz, opciones = {}) {
        const estado = raiz.querySelector('[data-diagnosticos-estado]');
        const lista = raiz.querySelector('[data-diagnosticos-lista]');
        const zonaDatos = raiz.querySelector('[data-diagnosticos-datos]');
        const ficha = raiz.querySelector('[data-diagnosticos-ficha]');
        const campos = [...raiz.querySelectorAll('[data-dato-campo]')];
        const idMascota = opciones.idMascota || (() => '');
        let sintomas = [];
        let temporizador = null;
        let peticion = 0;

        function datosDeclarados() {
            const datos = {};
            campos.forEach(campo => { datos[campo.dataset.datoCampo] = String(campo.value || '').trim(); });
            return datos;
        }

        function mostrarEstado(texto) {
            estado.textContent = texto;
            estado.hidden = texto === '';
        }

        function pintarDatos(legibles) {
            const declarados = datosDeclarados();
            const pedidos = legibles.map(dato => dato.clave);
            raiz.querySelectorAll('[data-dato]').forEach(envoltura => {
                const clave = envoltura.dataset.dato;
                envoltura.hidden = !pedidos.includes(clave) && declarados[clave] === '';
            });
            const deLaFicha = legibles.filter(dato => !dato.editable);
            ficha.replaceChildren(...deLaFicha.map(dato => elemento('li', '', dato.etiqueta)));
            ficha.hidden = deLaFicha.length === 0;
            const algunCampo = [...raiz.querySelectorAll('[data-dato]')].some(envoltura => !envoltura.hidden);
            zonaDatos.hidden = !algunCampo && deLaFicha.length === 0;
        }

        function tarjeta(candidato, etiquetas) {
            const item = elemento('li', 'consulta-sugerencia');
            item.dataset.diagnostico = candidato.codigo;
            detalles += 1;
            const detalle = elemento('div', 'consulta-sugerencia__detalle');
            detalle.id = `consulta-sugerencia-${detalles}`;
            detalle.hidden = true;

            const fila = elemento('div', 'consulta-sugerencia__fila');
            const abrir = elemento('button', 'consulta-sugerencia__abrir');
            abrir.type = 'button';
            abrir.setAttribute('aria-expanded', 'false');
            abrir.setAttribute('aria-controls', detalle.id);
            const flecha = elemento('i', 'fas fa-chevron-right consulta-sugerencia__flecha');
            flecha.setAttribute('aria-hidden', 'true');
            const cobertura = candidato.cobertura && candidato.cobertura.texto ? candidato.cobertura.texto : '';
            abrir.append(flecha, elemento('strong', 'consulta-sugerencia__nombre', candidato.etiqueta), elemento('span', 'consulta-sugerencia__cobertura', cobertura));
            abrir.addEventListener('click', () => alternarDetalle(item, abrir, detalle));
            const usar = elemento('button', 'consulta-sugerencia__usar', 'Usar');
            usar.type = 'button';
            usar.dataset.usarDiagnostico = candidato.codigo;
            usar.setAttribute('aria-label', `Usar este diagnóstico: ${candidato.etiqueta}`);
            usar.addEventListener('click', () => {
                if (opciones.alUsar) {
                    opciones.alUsar(candidato);
                    return;
                }
                if (opciones.campoDiagnostico) usarDiagnostico(opciones.campoDiagnostico, candidato.etiqueta);
            });
            fila.append(abrir, usar);

            const coinciden = (candidato.sintomas_coincidentes || []).map(codigo => etiquetas[codigo]).filter(Boolean);
            if (coinciden.length) detalle.append(elemento('p', 'consulta-sugerencia__sintomas', `Coinciden: ${coinciden.join(', ')}`));
            const fuente = fuentePrincipal(candidato.fuentes);
            if (fuente) {
                const parrafo = elemento('p', 'consulta-sugerencia__fuente', 'Fuente: ');
                if (fuente.url) {
                    const enlace = elemento('a', '', fuente.texto);
                    enlace.href = fuente.url;
                    enlace.target = '_blank';
                    enlace.rel = 'noopener noreferrer';
                    parrafo.append(enlace);
                } else {
                    parrafo.append(fuente.texto);
                }
                if (fuente.otras > 0) parrafo.append(` (+${fuente.otras})`);
                detalle.append(parrafo);
            }
            detalle.append(elemento('p', 'consulta-sugerencia__apoyo', APOYO));
            item.append(fila, detalle);
            return item;
        }

        function pintar(respuesta) {
            const etiquetas = {};
            sintomas.forEach(sintoma => { etiquetas[sintoma.codigo] = sintoma.etiqueta; });
            const candidatos = (Array.isArray(respuesta.candidatos) ? respuesta.candidatos : []).slice(0, MAXIMO);
            lista.replaceChildren(...candidatos.map(candidato => tarjeta(candidato, etiquetas)));
            lista.hidden = candidatos.length === 0;
            mostrarEstado(candidatos.length === 0 ? TEXTOS.sinCoincidencias : '');
            pintarDatos(datosLegibles(respuesta.datos_faltantes));
        }

        function reiniciar() {
            peticion++;
            clearTimeout(temporizador);
            lista.replaceChildren();
            lista.hidden = true;
            mostrarEstado(TEXTOS.sinSintomas);
            pintarDatos([]);
        }

        async function calcular() {
            const numero = ++peticion;
            mostrarEstado(TEXTOS.buscando);
            try {
                const respuesta = await (await fetch(RUTA_SUGERIR, { method: 'POST', body: cuerpoPeticion(idMascota(), sintomas, datosDeclarados()) })).json();
                if (numero !== peticion) return;
                if (!respuesta.success) {
                    lista.replaceChildren();
                    lista.hidden = true;
                    mostrarEstado(respuesta.message || TEXTOS.error);
                    return;
                }
                pintar(respuesta);
            } catch (error) {
                if (numero !== peticion) return;
                lista.hidden = true;
                mostrarEstado(TEXTOS.error);
            }
        }

        function programar() {
            clearTimeout(temporizador);
            if (sintomas.length === 0) {
                reiniciar();
                return;
            }
            temporizador = setTimeout(calcular, ESPERA_MS);
        }

        campos.forEach(campo => {
            campo.addEventListener('change', programar);
            campo.addEventListener('input', programar);
        });
        reiniciar();

        return {
            actualizar(nuevos) {
                sintomas = Array.isArray(nuevos) ? nuevos.slice() : [];
                programar();
            },
            limpiar() {
                sintomas = [];
                campos.forEach(campo => { campo.value = ''; });
                reiniciar();
            },
        };
    }

    /** RE-2.8.2/4: una operación reversible por diagnóstico, compartida por modal y Atención. */
    function completar(form, raiz, tratamientos, idMascota) {
        const diagnostico = form.querySelector('[data-consulta-campo="diagnostico"]');
        const plan = form.querySelector('[name="plan_tratamiento"]');
        const peso = form.querySelector('[data-consulta-campo="peso"]');
        const operaciones = new Map();
        const registros = [];
        let generacion = 0;
        let ultima = null;
        let temporizadorPeso = null;
        const aviso = elemento('div', 'consulta-ayuda');
        aviso.setAttribute('role', 'status');
        aviso.hidden = true;
        const mensaje = elemento('span');
        const deshacer = elemento('button', 'consulta-sugerencia__usar', 'Deshacer');
        deshacer.type = 'button';
        deshacer.dataset.deshacerSugerencia = '';
        aviso.append(mensaje, deshacer);
        raiz.append(aviso);
        const pesoActual = () => peso && !peso.disabled ? peso.value.trim().replace(',', '.') : '';
        const notificar = campo => campo.dispatchEvent(new Event('input', { bubbles: true }));

        // Mantiene el intervalo agregado al editar texto anterior o posterior; una edición dentro se conserva.
        for (const campo of [diagnostico, plan].filter(Boolean)) {
            let anterior = campo.value;
            campo.addEventListener('input', () => {
                const actual = campo.value;
                let inicio = 0;
                while (inicio < anterior.length && inicio < actual.length && anterior[inicio] === actual[inicio]) inicio++;
                let fin = anterior.length;
                let finNuevo = actual.length;
                while (fin > inicio && finNuevo > inicio && anterior[fin - 1] === actual[finNuevo - 1]) {
                    fin--;
                    finNuevo--;
                }
                for (const registro of registros.filter(r => r.campo === campo && !r.editado)) {
                    if (fin <= registro.inicio) {
                        registro.inicio += actual.length - anterior.length;
                    } else if (inicio < registro.inicio + registro.texto.length) {
                        registro.editado = true;
                    }
                }
                anterior = actual;
            });
        }

        function agregarTexto(campo, texto, operacion) {
            if (!campo || !texto || campo.value.split('\n').map(t => t.trim()).includes(texto)) return;
            const agregado = (campo.value ? '\n' : '') + texto;
            if (campo.maxLength > 0 && campo.value.length + agregado.length > campo.maxLength) {
                throw new Error('No cabe la sugerencia completa. Reduce el texto del diagnóstico o plan y vuelve a usarla.');
            }
            const inicio = campo.value.length;
            campo.value += agregado;
            notificar(campo);
            const registro = { campo, inicio, texto: agregado, editado: false };
            registros.push(registro);
            operacion.textos.push(registro);
        }

        async function consultar(codigo, vigente) {
            let respuesta;
            do {
                const valorPeso = pesoActual();
                const cuerpo = new URLSearchParams({ id_mascota: String(idMascota()), diagnostico: codigo, peso: valorPeso });
                respuesta = await (await fetch('index.php?action=sugerir_tratamientos_ajax', { method: 'POST', body: cuerpo })).json();
                if (vigente !== generacion) return null;
                if (!respuesta.success) throw new Error(respuesta.message || 'No se pudieron obtener las pautas. Vuelve a intentarlo.');
                if (valorPeso === pesoActual()) return respuesta;
            } while (vigente === generacion);
            return null;
        }

        async function usar(candidato) {
            if (operaciones.has(candidato.codigo)) return;
            const vigente = generacion;
            const operacion = { textos: [], filas: [], cargando: true, codigo: candidato.codigo };
            operaciones.set(candidato.codigo, operacion);
            try {
                const respuesta = await consultar(candidato.codigo, vigente);
                if (!respuesta) return;
                agregarTexto(diagnostico, candidato.etiqueta, operacion);
                const partes = [];
                if (respuesta.plan.examenes.length) partes.push('Exámenes sugeridos: ' + respuesta.plan.examenes.join('; '));
                if (respuesta.plan.recomendaciones.length) partes.push('Recomendaciones: ' + respuesta.plan.recomendaciones.join('; '));
                for (const parte of partes) agregarTexto(plan, parte, operacion);
                for (const pauta of respuesta.tratamientos) {
                    if (pauta.seguridad.bloqueada) continue;
                    if (tratamientos.filas().some(f => f.dataset.pautaSugerida === pauta.codigo)) continue;
                    const fila = tratamientos.agregarSugerida(pauta);
                    operacion.filas.push(fila);
                }
                // Atención debe advertir cambios incluso si solo se agregaron filas al texto existente.
                notificar(form);
                operacion.cargando = false;
                ultima = operacion;
                const llenado = [];
                if (operacion.textos.some(r => r.campo === diagnostico)) llenado.push('diagnóstico');
                if (operacion.textos.some(r => r.campo === plan)) llenado.push('plan');
                if (operacion.filas.length) llenado.push(`${operacion.filas.length} tratamiento${operacion.filas.length === 1 ? '' : 's'}`);
                const resumen = llenado.length > 1 ? llenado.slice(0, -1).join(', ') + ' y ' + llenado[llenado.length - 1] : llenado[0];
                mensaje.textContent = llenado.length ? `Zooki llenó: ${resumen}. Revisa antes de guardar. ` : 'No hay contenido nuevo para agregar. ';
                aviso.hidden = false;
                deshacer.hidden = false;
            } catch (error) {
                if (vigente !== generacion) return;
                retirar(operacion);
                operaciones.delete(candidato.codigo);
                mensaje.textContent = error.message;
                aviso.hidden = false;
                deshacer.hidden = true;
            }
        }

        function retirar(operacion) {
            for (const registro of operacion.textos.slice().reverse()) {
                const { campo, inicio, texto, editado } = registro;
                registro.editado = true;
                if (!editado && campo.value.slice(inicio, inicio + texto.length) === texto) {
                    campo.value = campo.value.slice(0, inicio) + campo.value.slice(inicio + texto.length);
                    notificar(campo);
                }
            }
            tratamientos.retirarSugeridas(operacion.filas);
            notificar(form);
        }

        deshacer.addEventListener('click', () => {
            if (!ultima) return;
            retirar(ultima);
            operaciones.delete(ultima.codigo);
            ultima = [...operaciones.values()].filter(o => !o.cargando).pop() || null;
            mensaje.textContent = 'Se quitó lo agregado; se conservaron tus ediciones. ';
            deshacer.hidden = !ultima;
        });

        async function recalcular() {
            const vigente = generacion;
            for (const operacion of operaciones.values()) {
                if (operacion.cargando) continue;
                try {
                    const respuesta = await consultar(operacion.codigo, vigente);
                    if (respuesta && operaciones.get(operacion.codigo) === operacion) tratamientos.recalcularSugeridas(operacion.filas, respuesta.tratamientos);
                } catch (error) {
                    if (vigente !== generacion) return;
                    mensaje.textContent = 'No se pudieron recalcular las dosis. Revisa el peso y vuelve a intentarlo. ';
                    aviso.hidden = false;
                }
            }
        }
        function programarRecalculo() {
            clearTimeout(temporizadorPeso);
            temporizadorPeso = setTimeout(recalcular, 250);
        }
        if (peso) peso.addEventListener('input', programarRecalculo);
        const sinPeso = form.querySelector('[data-sin-peso]');
        if (sinPeso) sinPeso.addEventListener('change', programarRecalculo);
        return {
            usar,
            limpiar() {
                generacion++;
                clearTimeout(temporizadorPeso);
                operaciones.clear();
                registros.length = 0;
                ultima = null;
                aviso.hidden = true;
            },
        };
    }

    window.ZookiDiagnosticos = { RUTA_SUGERIR, MAXIMO, APOYO, TEXTOS, datosLegibles, fuentePrincipal, usarDiagnostico, cuerpoPeticion, crear, completar };
})();
