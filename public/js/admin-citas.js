/** C9: tablero de citas de la clínica activa; RE-T.15.1. */
let todasLasCitas = [];
let citasFiltradasGlobal = [];
let veterinariosMap = {};
let veterinariosArray = [];
let tiposCita = [];

document.addEventListener('DOMContentLoaded', function() {
    cargarDatosIniciales();
    
    document.getElementById('filtroFecha').addEventListener('change', function() {
        const customInput = document.getElementById('filtroFechaCustom');
        customInput.hidden = this.value !== 'custom';
    });

    const kanbanWrapper = document.getElementById('kanbanWrapper');
    if (kanbanWrapper) {
        kanbanWrapper.addEventListener('scroll', checkScrollButtons);
        window.addEventListener('resize', checkScrollButtons);

        // Lógica de arrastre para scroll
        let isDown = false;
        let startX;
        let scrollLeft;

        kanbanWrapper.addEventListener('mousedown', (e) => {
            isDown = true;
            kanbanWrapper.style.cursor = 'grabbing';
            startX = e.pageX - kanbanWrapper.offsetLeft;
            scrollLeft = kanbanWrapper.scrollLeft;
        });

        kanbanWrapper.addEventListener('mouseleave', () => {
            isDown = false;
            kanbanWrapper.style.cursor = 'grab';
        });

        kanbanWrapper.addEventListener('mouseup', () => {
            isDown = false;
            kanbanWrapper.style.cursor = 'grab';
        });

        kanbanWrapper.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - kanbanWrapper.offsetLeft;
            const walk = (x - startX) * 2; // Multiplicador de velocidad
            kanbanWrapper.scrollLeft = scrollLeft - walk;
        });
        
        kanbanWrapper.style.cursor = 'grab';
    }
});

function scrollKanban(direction) {
    const wrapper = document.getElementById('kanbanWrapper');
    const scrollAmount = 340; // Approx one column width + gap
    if (direction === 'left') {
        wrapper.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        wrapper.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function checkScrollButtons() {
    const wrapper = document.getElementById('kanbanWrapper');
    const btnLeft = document.getElementById('btnScrollLeft');
    const btnRight = document.getElementById('btnScrollRight');
    
    if (!wrapper || !btnLeft || !btnRight) return;
    
    if (wrapper.scrollWidth > wrapper.clientWidth) {
        btnLeft.hidden = wrapper.scrollLeft <= 0;
        btnRight.hidden = wrapper.scrollLeft >= wrapper.scrollWidth - wrapper.clientWidth - 5;
    } else {
        btnLeft.hidden = true;
        btnRight.hidden = true;
    }
}

async function cargarDatosIniciales() {
    try {
        const [citasRes, vetsRes] = await Promise.all([
            fetch('index.php?action=listar_todas_citas_ajax'),
            fetch('index.php?action=listar_veterinarios_ajax')
        ]);
        
        const citasData = await citasRes.json();
        const vetsData = await vetsRes.json();
        
        if (!citasData.success) {
            document.getElementById('kanbanBoard').innerHTML = 
                `<div class="kanban-empty">Error al cargar las citas: ${citasData.message}</div>`;
            return;
        }
        
        todasLasCitas = citasData.citas;
        
        const selectVet = document.getElementById('filtroVet');
        if (Array.isArray(vetsData)) {
            veterinariosArray = vetsData;
            vetsData.forEach(v => {
                veterinariosMap[String(v.id_usuario)] = v.nombre_completo;
                selectVet.innerHTML += `<option value="${String(v.id_usuario)}">Dr. ${v.nombre_completo.split(' ')[0]}</option>`;
            });
        }
        
        const tiposUnicos = new Set();
        todasLasCitas.forEach(cita => {
            if (cita.tipo_cita_nombre) tiposUnicos.add(cita.tipo_cita_nombre);
        });
        
        const selectTipo = document.getElementById('filtroTipoCita');
        tiposUnicos.forEach(tipo => {
            selectTipo.innerHTML += `<option value="${tipo}">${tipo}</option>`;
        });
        
        // Cargar por defecto las de HOY
        aplicarFiltros();
    } catch (error) {
        console.error('Error cargando datos:', error);
        document.getElementById('kanbanBoard').innerHTML = 
            `<div class="kanban-empty">Error de conexión: ${error.message}</div>`;
    }
}

function filtrarCitas() {
    aplicarFiltros();
}

function aplicarFiltros() {
    const fecha = document.getElementById('filtroFecha').value;
    const fechaCustom = document.getElementById('filtroFechaCustom').value;
    const tipoCita = document.getElementById('filtroTipoCita').value;
    const estado = document.getElementById('filtroEstado').value;
    const vetFiltro = document.getElementById('filtroVet').value;
    const search = document.getElementById('filtroSearch').value.toLowerCase();
    
    let citasFiltradas = [...todasLasCitas];
    
    if (fecha) {
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        let fechaInicio, fechaFin;
        
        switch(fecha) {
            case 'hoy':
                fechaInicio = hoy;
                fechaFin = new Date(hoy);
                break;
            case 'ayer':
                fechaInicio = new Date(hoy);
                fechaInicio.setDate(fechaInicio.getDate() - 1);
                fechaFin = new Date(fechaInicio);
                break;
            case 'semana':
                fechaInicio = new Date(hoy);
                fechaInicio.setDate(hoy.getDate() - hoy.getDay());
                fechaFin = new Date(fechaInicio);
                fechaFin.setDate(fechaFin.getDate() + 6);
                break;
            case 'mes':
                fechaInicio = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
                fechaFin = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0);
                break;
            case 'custom':
                if (fechaCustom) {
                    fechaInicio = new Date(fechaCustom);
                    // Add timezone offset to prevent date shifting
                    fechaInicio.setMinutes(fechaInicio.getMinutes() + fechaInicio.getTimezoneOffset());
                    fechaInicio.setHours(0, 0, 0, 0);
                    fechaFin = new Date(fechaInicio);
                }
                break;
        }
        
        if (fechaInicio && fechaFin) {
            fechaFin.setHours(23, 59, 59, 999);
            citasFiltradas = citasFiltradas.filter(c => {
                // Ensure correct parsing of local date
                const [year, month, day] = c.fecha.split('-');
                const citaFecha = new Date(year, month - 1, day);
                return citaFecha >= fechaInicio && citaFecha <= fechaFin;
            });
        }
    }
    
    if (tipoCita) citasFiltradas = citasFiltradas.filter(c => c.tipo_cita_nombre === tipoCita);
    if (estado) citasFiltradas = citasFiltradas.filter(c => c.estado === estado);
    if (vetFiltro) citasFiltradas = citasFiltradas.filter(c => String(c.id_veterinario) === vetFiltro);
    if (search) {
        citasFiltradas = citasFiltradas.filter(c => 
            (c.mascota_nombre && c.mascota_nombre.toLowerCase().includes(search)) ||
            (c.propietario_nombre && c.propietario_nombre.toLowerCase().includes(search)) ||
            (c.motivo && c.motivo.toLowerCase().includes(search))
        );
    }
    
    // Order by date and time
    citasFiltradas.sort((a, b) => {
        const fechaA = new Date(a.fecha + 'T' + (a.hora || '00:00:00'));
        const fechaB = new Date(b.fecha + 'T' + (b.hora || '00:00:00'));
        return fechaA - fechaB; // Ascending order
    });
    
    actualizarMétricas(citasFiltradas);
    renderizarKanban(citasFiltradas, vetFiltro);
    citasFiltradasGlobal = citasFiltradas;
}

function actualizarMétricas(citasFiltradas) {
    const total = citasFiltradas.length;
    let pendientes = 0, completadas = 0, canceladas = 0;
    
    citasFiltradas.forEach(c => {
        if (c.estado === 'pendiente' || c.estado === 'confirmada') pendientes++;
        else if (c.estado === 'completada') completadas++;
        else if (c.estado === 'cancelada') canceladas++;
    });
    
    document.getElementById('kpi-total').textContent = total;
    document.getElementById('kpi-pendientes').textContent = pendientes;
    document.getElementById('kpi-completadas').textContent = completadas;
    document.getElementById('kpi-canceladas').textContent = canceladas;

    dibujarIndicador('kpi-total-spark', total, total, '#0052FF');
    dibujarIndicador('kpi-pendientes-spark', pendientes, total, '#F59E0B');
    dibujarIndicador('kpi-completadas-spark', completadas, total, '#10B981');
    dibujarIndicador('kpi-canceladas-spark', canceladas, total, '#EF4444');
}

function renderizarKanban(citas, vetFiltro) {
    const board = document.getElementById('kanbanBoard');
    board.innerHTML = '';
    
    // Filter veterinarians if one is selected in the dropdown
    let vetsToRender = veterinariosArray;
    if (vetFiltro) {
        vetsToRender = veterinariosArray.filter(v => String(v.id_usuario) === vetFiltro);
    }
    
    if (vetsToRender.length === 0) {
        board.innerHTML = `<div class="kanban-empty">No hay veterinarios registrados.</div>`;
        return;
    }
    
    // Agrupar citas por veterinario
    const agrupadas = {};
    vetsToRender.forEach(v => agrupadas[String(v.id_usuario)] = []);
    
    citas.forEach(c => {
        if (agrupadas[String(c.id_veterinario)]) {
            agrupadas[String(c.id_veterinario)].push(c);
        }
    });
    
    
    vetsToRender.forEach(vet => {
        const vetCitas = agrupadas[String(vet.id_usuario)];
        const initial = vet.nombre_completo.charAt(0);
        const firstName = vet.nombre_completo.split(' ')[0];
        const numCitas = vetCitas.length;
        
        let columnHtml = `
            <div class="kanban-col">
                <div class="kb-col-header">
                    <div class="kb-vet-info">
                        <div class="kb-avatar">${escapeHtml(initial)}</div>
                        <div>
                            <div class="kb-vet-name">Dr. ${escapeHtml(firstName)}</div>
                            <div class="kb-vet-count">${numCitas} cita(s)</div>
                        </div>
                    </div>
                </div>
                <div class="kb-col-body">
        `;
        
        if (numCitas === 0) {
            columnHtml += `
                <div class="kb-empty-state">
                    <i class="far fa-calendar-times"></i>
                    <p>Sin citas para mostrar</p>
                </div>
            `;
        } else {
            vetCitas.forEach(cita => {
                const horaStr = cita.hora ? cita.hora.substring(0, 5) : '—';
                const fechaParts = cita.fecha.split('-');
                const diaMesStr = `${fechaParts[2]}/${fechaParts[1]}`;
                
                columnHtml += `
                    <div class="kb-card" data-ui-accion="detalle-cita-admin" data-ui-id="${Number(cita.id_cita)}" role="button" tabindex="0">
                        <div class="kb-card-header">
                            <span class="kb-time"><i class="far fa-clock"></i> ${horaStr} <span class="kb-date">(${diaMesStr})</span></span>
                            <span class="kb-status" data-estado="${escapeHtml(cita.estado)}">${escapeHtml(cita.estado)}</span>
                        </div>
                        <div class="kb-patient-name">${escapeHtml(cita.mascota_nombre || '—')}</div>
                        <div class="kb-owner-name"><i class="far fa-user"></i> ${escapeHtml(cita.propietario_nombre || '—')}</div>
                        <div class="kb-reason">${escapeHtml(cita.tipo_cita_nombre || 'Consulta')}</div>
                    </div>
                `;
            });
        }
        
        columnHtml += `</div></div>`; // Cierre kb-col-body y kanban-col
        board.innerHTML += columnHtml;
    });
    
    setTimeout(checkScrollButtons, 100);
}

function verDetalle(idCita) {
    const cita = todasLasCitas.find(c => c.id_cita == idCita);
    if (!cita) return;
    
    
    Swal.fire({
        html: `
            <div class="cita-modal-container">
                <div class="cita-modal-header citas-detalle-cabecera">
                    <div class="citas-detalle-fila">
                        <h3 class="citas-detalle-titulo">Detalle de la Cita</h3>
                        <span data-estado="${escapeHtml(cita.estado)}" class="citas-detalle-estado">${escapeHtml(cita.estado)}</span>
                    </div>
                    <p class="citas-detalle-fecha"><i class="far fa-calendar-alt"></i> ${cita.fecha} &nbsp;&bull;&nbsp; <i class="far fa-clock"></i> ${cita.horaStr || cita.hora}</p>
                </div>

                <div class="cita-modal-body citas-detalle-cuerpo">
                    <div class="citas-detalle-grid">
                        <div class="modal-info-box citas-detalle-caja">
                            <p class="citas-detalle-etiqueta">Paciente</p>
                            <p class="citas-detalle-valor"><i class="fas fa-paw citas-detalle-icono-azul"></i> ${escapeHtml(cita.mascota_nombre || 'No especificado')}</p>
                        </div>
                        <div class="modal-info-box citas-detalle-caja">
                            <p class="citas-detalle-etiqueta">Propietario</p>
                            <p class="citas-detalle-valor"><i class="far fa-user citas-detalle-icono-azul"></i> ${escapeHtml(cita.propietario_nombre || 'No especificado')}</p>
                        </div>
                    </div>

                    <div class="citas-detalle-grid">
                        <div class="citas-detalle-veterinario">
                            <p class="citas-detalle-etiqueta-secundaria">Veterinario Asignado</p>
                            <p class="citas-detalle-valor-secundario"><i class="fas fa-user-md citas-detalle-icono-gris"></i> Dr. ${escapeHtml(cita.veterinario_nombre || (veterinariosMap[String(cita.id_veterinario)]))}</p>
                        </div>
                        <div class="citas-detalle-tipo">
                            <p class="citas-detalle-etiqueta-secundaria">Tipo de Cita</p>
                            <p class="citas-detalle-valor-secundario"><i class="fas fa-clipboard-list citas-detalle-icono-gris"></i> ${escapeHtml(cita.tipo_cita_nombre)}</p>
                        </div>
                    </div>

                    <div class="citas-detalle-notas">
                        <p class="citas-detalle-notas-etiqueta"><i class="far fa-comment-alt citas-detalle-notas-icono"></i> Motivo / Notas Adicionales</p>
                        <p class="citas-detalle-notas-texto">"${escapeHtml(cita.motivo || 'Sin observaciones adicionales.')}"</p>
                    </div>
                </div>
            </div>
        `,
        showCloseButton: true,
        showConfirmButton: false,
        padding: '0',
        customClass: {
            popup: 'premium-modal',
            htmlContainer: 'premium-html-container',
            closeButton: 'premium-close-btn'
        },
        width: '600px'
    });
}

function limpiarFiltros() {
    document.getElementById('filtroFecha').value = 'hoy';
    document.getElementById('filtroFechaCustom').value = '';
    document.getElementById('filtroFechaCustom').hidden = true;
    document.getElementById('filtroTipoCita').value = '';
    document.getElementById('filtroEstado').value = '';
    document.getElementById('filtroVet').value = '';
    document.getElementById('filtroSearch').value = '';
    aplicarFiltros();
}



async function exportarCitas() {
    if (citasFiltradasGlobal.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin datos',
            text: 'No hay citas para exportar con los filtros aplicados actualmente.',
            confirmButtonColor: '#0052FF'
        });
        return;
    }

    try {
        // Mostrar cargando mientras procesa la librería (en caso de que sean muchas filas)
        Swal.fire({
            title: 'Generando Excel...',
            text: 'Dando formato corporativo a los datos.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Crear libro y hoja
        const workbook = new ExcelJS.Workbook();
        workbook.creator = 'Zooki Software';
        const sheet = workbook.addWorksheet('Reporte de Citas');

        // Fila 1: Título Principal
        sheet.mergeCells('A1:I1');
        const titleCell = sheet.getCell('A1');
        titleCell.value = 'ZOOKI - REPORTE DE CITAS';
        titleCell.font = { name: 'Arial', size: 14, bold: true, color: { argb: 'FFFFFFFF' } };
        titleCell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0052FF' } }; // Azul Zooki
        titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
        sheet.getRow(1).height = 35;

        // Fila 2: Subtítulo Fecha
        sheet.mergeCells('A2:I2');
        const subtitleCell = sheet.getCell('A2');
        subtitleCell.value = `Generado el: ${new Date().toLocaleString()}`;
        subtitleCell.font = { name: 'Arial', size: 10, italic: true, color: { argb: 'FF64748B' } };
        subtitleCell.alignment = { horizontal: 'center', vertical: 'middle' };
        sheet.getRow(2).height = 20;

        // Fila 3: Espacio en blanco
        sheet.addRow([]);

        // Fila 4: Encabezados de Columna
        const headerRow = sheet.addRow([
            'ID', 'Paciente', 'Propietario', 'Veterinario', 
            'Fecha', 'Hora', 'Estado', 'Tipo de Cita', 'Motivo'
        ]);

        headerRow.eachCell((cell) => {
            cell.font = { name: 'Arial', bold: true, color: { argb: 'FFFFFFFF' } };
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF334155' } }; // Slate dark
            cell.alignment = { horizontal: 'center', vertical: 'middle' };
        });
        headerRow.height = 25;

        // Insertar los Datos
        citasFiltradasGlobal.forEach(c => {
            const estadoTexto = c.estado.toUpperCase();
            
            const row = sheet.addRow([
                c.id_cita,
                c.mascota_nombre || '—',
                c.propietario_nombre || '—',
                `Dr. ${c.veterinario_nombre || veterinariosMap[String(c.id_veterinario)] || '—'}`,
                c.fecha,
                c.hora ? c.hora.substring(0, 5) : '—',
                estadoTexto,
                c.tipo_cita_nombre || 'Consulta',
                c.motivo || 'Sin observaciones.'
            ]);

            // Estilos individuales para las celdas de esta fila
            row.eachCell((cell, colNumber) => {
                cell.font = { name: 'Arial', size: 10 };
                cell.alignment = { vertical: 'middle' };
                
                // Centrar ciertas columnas (ID, Fecha, Hora)
                if (colNumber === 1 || colNumber === 5 || colNumber === 6) {
                    cell.alignment = { horizontal: 'center', vertical: 'middle' };
                }

                // Columna Estado (7): Pintar según el status
                if (colNumber === 7) {
                    cell.font.bold = true;
                    cell.alignment = { horizontal: 'center', vertical: 'middle' };
                    
                    if (estadoTexto === 'PENDIENTE') cell.font.color = { argb: 'FFF59E0B' }; // Naranja
                    else if (estadoTexto === 'COMPLETADA') cell.font.color = { argb: 'FF10B981' }; // Verde
                    else if (estadoTexto === 'CONFIRMADA') cell.font.color = { argb: 'FF5560FF' }; // Azul clarito
                    else if (estadoTexto === 'CANCELADA') cell.font.color = { argb: 'FFEF4444' }; // Rojo
                }
            });
        });

        // Configurar el ancho de las columnas
        sheet.columns = [
            { width: 8 },  // ID
            { width: 18 }, // Paciente
            { width: 22 }, // Propietario
            { width: 25 }, // Veterinario
            { width: 12 }, // Fecha
            { width: 10 }, // Hora
            { width: 16 }, // Estado
            { width: 25 }, // Tipo
            { width: 40 }  // Motivo
        ];

        // Crear el archivo binario y forzar descarga
        const buffer = await workbook.xlsx.writeBuffer();
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url = URL.createObjectURL(blob);
        
        const fechaStr = new Date().toISOString().split('T')[0];
        
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `Zooki_Reporte_Citas_${fechaStr}.xlsx`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        // Cerrar alerta de carga y mostrar éxito
        Swal.close();
        
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'Excel Exportado Correctamente' });

    } catch (error) {
        console.error('Error al generar Excel:', error);
        Swal.fire('Error', 'Hubo un problema al generar el archivo Excel. Revisa tu consola.', 'error');
    }
}

function exportarCitasPDF() {
    if (citasFiltradasGlobal.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Sin datos',
            text: 'No hay citas para exportar con los filtros aplicados actualmente.',
            confirmButtonColor: '#0052FF'
        });
        return;
    }

    try {
        Swal.fire({
            title: 'Generando PDF...',
            text: 'Preparando documento corporativo.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');

        // Título del PDF
        doc.setFillColor(0, 82, 255);
        doc.rect(0, 0, doc.internal.pageSize.width, 25, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(16);
        doc.setFont("helvetica", "bold");
        doc.text('ZOOKI - REPORTE DE CITAS', 14, 16);
        
        doc.setFontSize(9);
        doc.setFont("helvetica", "normal");
        doc.text(`Generado el: ${new Date().toLocaleString()}`, doc.internal.pageSize.width - 14, 16, { align: 'right' });

        // Preparar Datos
        const columnas = ['ID', 'Paciente', 'Propietario', 'Veterinario', 'Fecha', 'Hora', 'Estado', 'Tipo de Cita'];
        const filas = citasFiltradasGlobal.map(c => [
            c.id_cita,
            c.mascota_nombre || '—',
            c.propietario_nombre || '—',
            `Dr. ${c.veterinario_nombre || veterinariosMap[String(c.id_veterinario)] || '—'}`,
            c.fecha,
            c.hora ? c.hora.substring(0, 5) : '—',
            c.estado.toUpperCase(),
            c.tipo_cita_nombre || 'Consulta'
        ]);

        // AutoTable
        doc.autoTable({
            head: [columnas],
            body: filas,
            startY: 30,
            theme: 'grid',
            headStyles: {
                fillColor: [51, 65, 85], // slate-800
                textColor: 255,
                fontSize: 9,
                fontStyle: 'bold',
                halign: 'center'
            },
            bodyStyles: {
                fontSize: 8,
                textColor: [30, 41, 59]
            },
            columnStyles: {
                0: { halign: 'center', cellWidth: 15 },
                4: { halign: 'center', cellWidth: 25 },
                5: { halign: 'center', cellWidth: 20 },
                6: { halign: 'center', fontStyle: 'bold', cellWidth: 30 }
            },
            didParseCell: function(data) {
                // Colorear columna de Estado
                if (data.section === 'body' && data.column.index === 6) {
                    const estado = data.cell.raw;
                    if (estado === 'PENDIENTE') data.cell.styles.textColor = [245, 158, 11]; // Amber
                    else if (estado === 'COMPLETADA') data.cell.styles.textColor = [16, 185, 129]; // Emerald
                    else if (estado === 'CONFIRMADA') data.cell.styles.textColor = [85, 96, 255]; // Zooki Blue
                    else if (estado === 'CANCELADA') data.cell.styles.textColor = [239, 68, 68]; // Red
                }
            }
        });

        const fechaStr = new Date().toISOString().split('T')[0];
        doc.save(`Zooki_Reporte_Citas_${fechaStr}.pdf`);

        Swal.close();
        
        const Toast = Swal.mixin({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
        });
        Toast.fire({ icon: 'success', title: 'PDF Exportado Correctamente' });

    } catch (error) {
        console.error('Error al generar PDF:', error);
        Swal.fire('Error', 'Hubo un problema al generar el PDF.', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-filtrar-citas]').forEach((campo) => {
        campo.addEventListener('change', filtrarCitas);
    });
    document.querySelector('[data-buscar-citas]').addEventListener('input', filtrarCitas);
});
