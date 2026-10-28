/* R A: directorio ≥768. Se trasladan controles existentes y se restauran al volver al celular. */
(function (global) {
    'use strict';
    const escritorio = global.matchMedia('(min-width: 768px)');
    let raiz;
    let piezas = [];
    let turno = 0;
    let observadorTarjetas;
    const plural = (numero, uno, varios) => numero + ' ' + (numero === 1 ? uno : varios);

    function mover(nodo, destino) {
        if (!nodo || !destino) return;
        const marca = document.createComment('Posición móvil aprobada');
        nodo.before(marca);
        piezas.push({ nodo, marca, destino });
    }
    function abrirMascota(fila) {
        if (!fila.dataset.propietario || !fila.dataset.id) return;
        global.viewPetInDossier(fila.dataset.propietario, fila.dataset.id);
    }
    function prepararMascotas() {
        raiz.querySelectorAll('.pet-card, #petsTable tbody tr[data-id]').forEach(fila => {
            fila.classList.add('paciente-abrible');
            if (escritorio.matches) {
                fila.setAttribute('role', 'button');
                fila.tabIndex = 0;
                fila.setAttribute('aria-label', 'Abrir ficha de ' + (fila.querySelector('.card-title-mini, .pet-name-cell')?.textContent.trim() || 'mascota'));
            } else {
                fila.removeAttribute('role');
                fila.removeAttribute('tabindex');
                fila.removeAttribute('aria-label');
            }
            if (fila._fichaPreparada) return;
            fila._fichaPreparada = true;
            fila.addEventListener('click', evento => {
                if (!escritorio.matches || evento.target.closest('button, input, label, a')) return;
                evento.preventDefault();
                evento.stopImmediatePropagation();
                abrirMascota(fila);
            }, true);
            fila.addEventListener('keydown', evento => {
                if (!escritorio.matches || evento.target !== fila || !['Enter', ' '].includes(evento.key)) return;
                evento.preventDefault();
                abrirMascota(fila);
            });
        });
        raiz.querySelectorAll('.pet-card').forEach(tarjeta => {
            observadorTarjetas?.observe(tarjeta, { attributes: true, attributeFilter: ['style', 'data-estado'] });
            const imagen = tarjeta.querySelector('.avatar-mini img');
            if (imagen && !imagen._respaldoDirectorio) {
                imagen._respaldoDirectorio = true;
                const respaldo = document.createElement('span');
                respaldo.className = 'paciente-solo-escritorio paciente-foto-respaldo';
                respaldo.textContent = global.inicialesDueno(tarjeta.querySelector('.card-title-mini').textContent);
                respaldo.setAttribute('aria-hidden', 'true');
                imagen.after(respaldo);
                tarjeta.dataset.fotoRespaldo = String(imagen.getAttribute('src').includes('default-pet.svg'));
                imagen.addEventListener('error', () => { tarjeta.dataset.fotoRespaldo = 'true'; });
            }
            const toggle = tarjeta.querySelector('.status-indicator');
            const pie = tarjeta.querySelector('.card-footer-mini');
            if (toggle && !toggle._marcaMovil) {
                toggle._marcaMovil = document.createComment('Interruptor original');
                toggle.before(toggle._marcaMovil);
                for (const [clase, texto] of [['on', 'Activa'], ['off', 'Inactiva']]) {
                    const etiqueta = document.createElement('span');
                    etiqueta.className = 'paciente-solo-escritorio paciente-toggle-' + clase;
                    etiqueta.textContent = texto;
                    toggle.querySelector('label').append(etiqueta);
                }
            }
            if (toggle) {
                if (escritorio.matches) pie.prepend(toggle);
                else toggle._marcaMovil.after(toggle);
            }
        });
        raiz.querySelectorAll('[data-paciente-nacimiento]').forEach(campo => {
            campo.textContent = [campo.dataset.pacienteSexo, campo.dataset.pacienteNacimiento ? global.calculateAge(campo.dataset.pacienteNacimiento) : 'Edad sin dato'].filter(Boolean).join(' · ');
        });
    }
    function pintarEstadosPropietario() {
        raiz.querySelectorAll('.pet-card .tag-mini:has(.bi-moon-stars)').forEach(chip => {
            const texto = Array.from(chip.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
            if (!texto) return;
            if (!chip._textoMovil) chip._textoMovil = texto.textContent;
            const valor = escritorio.matches ? ' Inactiva' : chip._textoMovil;
            if (texto.textContent !== valor) texto.textContent = valor;
        });
        raiz.querySelectorAll('#ownersGrid .client-card[data-pendiente-activacion="1"]').forEach(tarjeta => {
            const chip = tarjeta.querySelector('.tag-mini--aviso');
            if (!chip) return;
            const icono = chip.querySelector('i');
            const texto = Array.from(chip.childNodes).find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
            if (texto) {
                if (!chip._textoMovil) chip._textoMovil = texto.textContent;
                const valor = escritorio.matches ? 'Cuenta sin activar' : chip._textoMovil;
                if (texto.textContent !== valor) texto.textContent = valor;
            }
            if (icono) {
                if (!icono._claseMovil) icono._claseMovil = icono.className;
                const clase = escritorio.matches ? 'bi bi-clock' : icono._claseMovil;
                if (icono.className !== clase) icono.className = clase;
            }
        });
    }
    function actualizar() {
        turno = 0;
        if (!raiz) return;
        const propietarios = Array.from(raiz.querySelectorAll('#ownersGrid .client-card'));
        const mascotas = Array.from(raiz.querySelectorAll('#petsGrid .pet-card'));
        for (const [clave, cantidad] of [['owners', propietarios.length], ['pets', mascotas.length]]) {
            const contador = document.querySelector('[data-pacientes-conteo="' + clave + '"]');
            if (contador && contador.textContent !== String(cantidad)) contador.textContent = cantidad;
        }
        const esMascotas = document.getElementById('tabPets')?.classList.contains('active');
        const visibles = (esMascotas ? mascotas : propietarios).filter(n => n.style.display !== 'none');
        const pendientes = esMascotas ? visibles.filter(n => n.dataset.sinHistoria === '1').length : visibles.filter(n => n.dataset.pendienteActivacion === '1' && n.dataset.estado === '1').length;
        const texto = esMascotas
            ? plural(visibles.length, 'mascota', 'mascotas') + ' · ' + pendientes + ' sin historia clínica'
            : plural(visibles.length, 'propietario', 'propietarios') + ' · ' + pendientes + ' sin activar su cuenta';
        const resumen = raiz.querySelector('[data-pacientes-resumen]');
        if (resumen.textContent !== texto) resumen.textContent = texto;
        pintarEstadosPropietario();
    }
    function programar() {
        if (!turno) turno = requestAnimationFrame(actualizar);
    }
    function ajustar() {
        document.body.classList.toggle('pacientes-escritorio', escritorio.matches);
        piezas.forEach(({ nodo, marca, destino }) => {
            if (escritorio.matches) destino.append(nodo);
            else marca.after(nodo);
        });
        raiz.querySelector('[data-pacientes-hoja-cabecera]').hidden = !escritorio.matches;
        prepararMascotas();
        actualizar();
    }
    function iniciar() {
        raiz = document.querySelector('.pacientes-modulo');
        if (!raiz) return;
        mover(document.querySelector('[data-pacientes-directorio] .tabs-wrapper'), raiz.querySelector('[data-pacientes-pestanas-destino]'));
        mover(raiz.querySelector('.command-center-actions'), raiz.querySelector('[data-pacientes-acciones-destino]'));
        const observador = new MutationObserver(programar);
        const propietarios = document.getElementById('ownersGrid');
        observador.observe(propietarios, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] });
        document.querySelectorAll('#tabOwners, #tabPets').forEach(tab => observador.observe(tab, { attributes: true, attributeFilter: ['class'] }));
        observadorTarjetas = new MutationObserver(programar);
        // HU-1.4: guardar repinta estos listados sin recargar; preparar también los nodos nuevos.
        // Solo hijos directos: mover el toggle dentro de una tarjeta no dispara otro repintado.
        const recambios = new MutationObserver(() => {
            prepararMascotas();
            programar();
        });
        ['petsGrid', 'petsTableBody'].forEach(id => recambios.observe(document.getElementById(id), { childList: true }));
        escritorio.addEventListener('change', ajustar);
        ajustar();
    }
    document.addEventListener('DOMContentLoaded', iniciar);
})(window);
