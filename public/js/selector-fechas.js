/* Selector del sistema: calendario y navegación por mes/año compartidos. */
(function(global){
    'use strict';
    if(global.SelectorFechas) return;
function iniciar(selector = ".flatpickr-date", opciones = {}) {
    if (typeof flatpickr === 'undefined') return;
    try {
        let currentLocale = "es";
        if (flatpickr.l10ns && flatpickr.l10ns.es) {
            currentLocale = flatpickr.l10ns.es;
            if (currentLocale.weekdays) {
                currentLocale.weekdays.shorthand = ["Do", "Lu", "Ma", "Mi", "Ju", "Vi", "Sa"];
            }
        }
        const fpConfig = {
            locale: currentLocale,
            dateFormat: "Y-m-d",
            maxDate: "today",
            disableMobile: true,
            altInput: true,
            altFormat: "Y-m-d",
            monthSelectorType: "static",
            onReady: function(selectedDates, dateStr, instance) {
                const header = instance.monthNav.querySelector('.flatpickr-current-month');
                if (header) {
                    header.title = 'Seleccionar Mes/Año';
                    header.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const nav = instance.calendarContainer.querySelector('.fp-win11-nav');
                        if (nav) {
                            nav.classList.toggle('active');
                            const isNavActive = nav.classList.contains('active');
                            instance.calendarContainer.querySelector('.flatpickr-prev-month').style.visibility = isNavActive ? 'hidden' : 'visible';
                            instance.calendarContainer.querySelector('.flatpickr-next-month').style.visibility = isNavActive ? 'hidden' : 'visible';
                            if (isNavActive) {
                                viewYear = instance.currentYear;
                                nav._renderMonthsView();
                            }
                        }
                    });
                }

                const nav = document.createElement('div');
                nav.className = 'fp-win11-nav';

                const navHeader = document.createElement('div');
                navHeader.className = 'win11-header';
                const navTitle = document.createElement('button');
                navTitle.className = 'win11-title';
                navTitle.type = 'button';
                navHeader.appendChild(navTitle);
                nav.appendChild(navHeader);

                const navContent = document.createElement('div');
                navContent.className = 'win11-content';

                const monthsGrid = document.createElement('div');
                monthsGrid.className = 'win11-grid';

                const yearsGrid = document.createElement('div');
                yearsGrid.className = 'win11-grid win11-years';
                yearsGrid.style.display = 'none';

                navContent.appendChild(monthsGrid);
                navContent.appendChild(yearsGrid);
                nav.appendChild(navContent);

                instance.calendarContainer.appendChild(nav);

                let currentView = 'months';
                let viewYear = instance.currentYear;
                const monthNames = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];

                function renderMonthsView() {
                    currentView = 'months';
                    monthsGrid.style.display = 'grid';
                    yearsGrid.style.display = 'none';
                    navTitle.innerText = viewYear;

                    monthsGrid.innerHTML = '';
                    const limite = instance.config.maxDate;

                    monthNames.forEach((m, i) => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'win11-btn';

                        if (instance.currentYear === viewYear && instance.currentMonth === i) btn.classList.add('active');

                        // El límite de nacimiento no se aplica a la solicitud de una ausencia futura.
                        if (limite && (viewYear > limite.getFullYear() || (viewYear === limite.getFullYear() && i > limite.getMonth()))) {
                            btn.classList.add('disabled');
                            btn.disabled = true;
                        }

                        btn.innerText = m;

                        if (!btn.disabled) {
                            btn.onclick = (e) => {
                                e.stopPropagation();
                                instance.changeYear(viewYear);
                                instance.changeMonth(i);
                                nav.classList.remove('active');
                                instance.calendarContainer.querySelector('.flatpickr-prev-month').style.visibility = 'visible';
                                instance.calendarContainer.querySelector('.flatpickr-next-month').style.visibility = 'visible';
                            };
                        }
                        monthsGrid.appendChild(btn);
                    });
                }

                function renderYearsView() {
                    currentView = 'years';
                    monthsGrid.style.display = 'none';
                    yearsGrid.style.display = 'grid';

                    yearsGrid.innerHTML = '';
                    const actual = new Date().getFullYear();
                    const currentY = instance.config.maxDate ? instance.config.maxDate.getFullYear() : Math.max(actual, viewYear) + 100;
                    const startY = instance.config.maxDate ? currentY - 100 : Math.min(actual, viewYear) - 100;
                    navTitle.innerText = `${startY} - ${currentY}`;

                    for (let y = currentY; y >= startY; y--) {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'win11-btn';
                        if (y === viewYear) btn.classList.add('active');
                        btn.innerText = y;
                        btn.onclick = (e) => {
                            e.stopPropagation();
                            viewYear = y;
                            renderMonthsView();
                        };
                        yearsGrid.appendChild(btn);
                    }

                    // Mover scroll al año activo
                    setTimeout(() => {
                        const activeBtn = yearsGrid.querySelector('.active');
                        if (activeBtn) activeBtn.scrollIntoView({ block: 'center' });
                    }, 10);
                }

                nav._renderMonthsView = renderMonthsView; // bind for use in header click
                navTitle.onclick = (e) => {
                    e.stopPropagation();
                    if (currentView === 'months') {
                        renderYearsView();
                    } else {
                        renderMonthsView();
                    }
                };
            }
        };
        return flatpickr(selector, { ...fpConfig, ...opciones });
    } catch(e) {
        console.error("Error inicializando Flatpickr:", e);
    }
}
    /** Calendario de fecha/hora flotante: comparte la navegación, sin ocupar altura del formulario. */
    function iniciarFechaHora(campo, opciones = {}) {
        const dialogo = campo.closest('dialog');
        function colocar(instancia) {
            const calendario = instancia.calendarContainer;
            const ancla = instancia.altInput || campo;
            const rect = ancla.getBoundingClientRect();
            const ancho = calendario.offsetWidth;
            const alto = calendario.offsetHeight;
            const debajo = rect.bottom + 6;
            const arriba = rect.top - alto - 6;
            const superior = debajo + alto <= global.innerHeight - 12 ? debajo : Math.max(12, arriba);
            calendario.style.setProperty('--selector-fecha-x', Math.max(12, Math.min(rect.left, global.innerWidth - ancho - 12)) + 'px');
            calendario.style.setProperty('--selector-fecha-y', Math.min(superior, Math.max(12, global.innerHeight - alto - 12)) + 'px');
        }
        const instancia = iniciar(campo, {
            maxDate: null,
            enableTime: true,
            enableSeconds: true,
            time_24hr: true,
            dateFormat: 'Y-m-d\\TH:i:S',
            altFormat: 'd/m/Y H:i',
            altInputClass: 'coord-fecha-visible',
            ...opciones,
            static: false,
            appendTo: dialogo || document.body,
            position: colocar,
        });
        if (!instancia) return;
        const calendario = instancia.calendarContainer;
        calendario.classList.add('selector-fechas-flotante');
        const cabeceraHora = document.createElement('div');
        cabeceraHora.className = 'selector-fechas-dia';
        const cambiarDia = document.createElement('button');
        cambiarDia.type = 'button';
        cambiarDia.className = 'selector-fechas-cambiar';
        cambiarDia.textContent = 'Cambiar día';
        const diaElegido = document.createElement('strong');
        cabeceraHora.append(diaElegido, cambiarDia);
        calendario.prepend(cabeceraHora);
        function mostrarPaso(paso) {
            calendario.dataset.paso = paso;
            if (paso === 'hora') diaElegido.textContent = instancia.formatDate(instancia.selectedDates[0], 'd/m/Y');
            colocar(instancia);
        }
        cambiarDia.addEventListener('click', () => mostrarPaso('fecha'));
        const reloj = calendario.querySelector('.flatpickr-time');
        const etiquetaHora = document.createElement('span');
        etiquetaHora.className = 'selector-fechas-hora';
        etiquetaHora.textContent = 'Hora (24 h)';
        reloj.prepend(etiquetaHora);
        // La precisión original sigue en el valor enviado; el control visible se lee como HH:mm.
        const segundos = calendario.querySelector('.flatpickr-second');
        segundos.parentElement.classList.add('selector-fechas-segundos');
        segundos.parentElement.previousElementSibling.classList.add('selector-fechas-segundos');
        const horas = calendario.querySelector('.flatpickr-hour');
        const minutos = calendario.querySelector('.flatpickr-minute');
        for (const [entrada, maximo] of [[horas, 23], [minutos, 59]]) {
            entrada.type = 'text';
            entrada.inputMode = 'numeric';
            entrada.maxLength = 2;
            entrada.setAttribute('aria-label', entrada === horas ? 'Horas, de 00 a 23' : 'Minutos, de 00 a 59');
            entrada.addEventListener('beforeinput', evento => {
                if (!evento.data || evento.inputType.startsWith('delete')) return;
                const valor = entrada.value.slice(0, entrada.selectionStart) + evento.data + entrada.value.slice(entrada.selectionEnd);
                if (!/^\d{0,2}$/.test(valor) || Number(valor) > maximo) evento.preventDefault();
            });
            function limitar() {
                const valor = entrada.value.replace(/\D/g, '').slice(0, 2);
                entrada.value = valor === '' ? '' : String(Math.min(maximo, Number(valor)));
                if (valor.length === 2) entrada.value = entrada.value.padStart(2, '0');
            }
            entrada.addEventListener('input', limitar, true);
            entrada.addEventListener('paste', evento => {
                evento.preventDefault();
                entrada.value = evento.clipboardData.getData('text');
                limitar();
                entrada.dispatchEvent(new Event('input', { bubbles: true }));
            });
        }
        const terminar = document.createElement('button');
        terminar.type = 'button';
        terminar.className = 'selector-fechas-listo';
        terminar.textContent = 'Listo';
        terminar.addEventListener('click', () => {
            if (!instancia.selectedDates.length) return;
            const fecha = new Date(instancia.selectedDates[0]);
            fecha.setHours(Math.min(23, Number(horas.value) || 0), Math.min(59, Number(minutos.value) || 0));
            instancia.setDate(fecha, true);
            instancia.altInput.focus({ preventScroll: true });
            instancia.close();
        });
        reloj.append(terminar);
        // El popover permanece en el diálogo en el DOM y sube a su propia capa, sin quedar inerte detrás.
        if (typeof calendario.showPopover === 'function') calendario.setAttribute('popover', 'manual');
        instancia.config.onOpen.push(() => {
            mostrarPaso('fecha');
            if (calendario.hasAttribute('popover') && !calendario.matches(':popover-open')) calendario.showPopover();
            colocar(instancia);
        });
        instancia.config.onChange.push(() => {
            if (!instancia.isOpen || !instancia.selectedDates.length) return;
            const pasaAHora = calendario.dataset.paso !== 'hora';
            mostrarPaso('hora');
            if (pasaAHora) {
                horas.focus({ preventScroll: true });
                horas.select();
            }
        });
        instancia.config.onClose.push(() => {
            if (calendario.hasAttribute('popover') && calendario.matches(':popover-open')) calendario.hidePopover();
        });
        const recolocar = () => {
            if (instancia.isOpen) colocar(instancia);
        };
        global.addEventListener('resize', recolocar);
        dialogo?.addEventListener('scroll', recolocar, true);
        dialogo?.addEventListener('close', () => instancia.close());
        dialogo?.addEventListener('cancel', evento => {
            if (!instancia.isOpen) return;
            evento.preventDefault();
            instancia.close();
        });
        return instancia;
    }
    global.SelectorFechas = { iniciar, iniciarFechaHora };
})(typeof window !== 'undefined' ? window : globalThis);
