<?php
$nombre = explode(" ", trim($_SESSION["usuario_nombre"]))[0];
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_CO.UTF-8', 'spanish');
$fecha_banner = mb_strtoupper(strftime("%A, %d DE %B %Y"));
?>

<!-- ══ WELCOME BAR + KPIs inline ══════════════════════════════ -->
<div class="hero-banner animate__animated animate__fadeInDown">
    <div class="hero-content">
        <div class="hero-date"><?= $fecha_banner ?></div>
        <h1 class="hero-title">Resumen del Panel</h1>
        <div class="hero-stats">
            <span class="hero-greeting">Bienvenido, <?= htmlspecialchars($nombre) ?></span>
            <span style="opacity: 0.5; font-size: 0.8rem;">•</span>
            
            <!-- KPIs originales integrados en el Hero -->
            <div class="hero-badges">
                <span class="hero-badge"><i class="far fa-calendar-check"></i> Citas Hoy: <strong id="stat-vet-citas-hoy" style="margin-left:5px;"><div class="loader-small" style="width:10px;height:10px; border-top-color: #254EDD;"></div></strong></span>
                
                <span class="hero-badge inactivos"><i class="fas fa-stethoscope"></i> Consultas Hoy: <strong id="stat-vet-consultas" style="margin-left:5px;"><div class="loader-small" style="width:10px;height:10px; border-top-color: #64748b;"></div></strong></span>
                
                <span class="hero-badge"><i class="fas fa-paw"></i> Pacientes Atendidos: <strong id="stat-vet-pacientes" style="margin-left:5px;"><div class="loader-small" style="width:10px;height:10px; border-top-color: #10B981;"></div></strong></span>
            </div>
        </div>
    </div>
    <div class="hero-image">
        <img src="img/pets.png" alt="Mascotas" onerror="this.src='img/hero-puppy.png';">
    </div>
</div>

<!-- ══ MAIN DASHBOARD GRID (70/30) ══════════════════════════════ -->
<div class="vet-dashboard-grid animate__animated animate__fadeInUp animate__delay-1s">
    
    <!-- Columna Izquierda (70%) -->
    <div class="vet-col-main">

        <!-- Gráfica Principal: Tendencia de Motivos de Consulta -->
        <div class="chart-card-minimal">
            <div class="chart-header-clean" style="flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3>Tendencia de Citas</h3>
                    <p>Por rango de tiempo y tipo de cita</p>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <select id="filtro-rango-tiempo" class="form-control-minimal" style="border:1px solid #e2e8f0; border-radius:8px; padding:0.4rem 1rem; color:#475569; font-size:0.85rem; background:#fff; cursor: pointer;">
                        <option value="este_mes">Este Mes</option>
                        <option value="mes_pasado">Mes Pasado</option>
                        <option value="ultimos_3_meses">Últimos 3 Meses</option>
                        <option value="ultimos_6_meses">Últimos 6 Meses</option>
                    </select>
                    <select id="filtro-tendencia-motivo" class="form-control-minimal" style="border:1px solid #e2e8f0; border-radius:8px; padding:0.4rem 1rem; color:#475569; font-size:0.85rem; background:#fff; cursor: pointer;">
                        <option value="todos">Todos los tipos</option>
                        <!-- Options se llenan dinámicamente -->
                    </select>
                </div>
            </div>
            <div class="chart-wrap-main" style="position:relative; height: 250px; width:100%; margin-top:1rem;">
                <canvas id="chart-motivos-consulta"></canvas>
            </div>
        </div>

        <!-- Timeline: Mi Agenda de Hoy -->
        <div class="chart-card-minimal">
            <div class="chart-header-clean">
                <div>
                    <h3>Mi Agenda de Hoy</h3>
                    <p><?= date('d M Y') ?></p>
                </div>
                <a href="index.php?action=vet_agenda" class="btn-text-primary">Ir al Calendario &rsaquo;</a>
            </div>
            <div id="vet-timeline-container" class="vet-timeline-container">
                <div class="loader-small" style="margin: 2rem auto;"></div>
            </div>
        </div>
    </div>

    <!-- Columna Derecha (30%) -->
    <div class="vet-col-side">
        <!-- Gráfica Secundaria: Distribución Demográfica -->
        <div class="chart-card-minimal">
            <div class="chart-header-clean">
                <div>
                    <h3>Demografía de Pacientes</h3>
                    <p>Porcentaje por especie</p>
                </div>
            </div>
            <div class="chart-wrap-donut" style="position:relative; height: 200px; width:100%; margin-top: 1rem;">
                <canvas id="chart-especies-demografia"></canvas>
            </div>
            <div id="demografia-legend" class="donut-legend-clean"></div>
        </div>

        <!-- Centro de Alertas Unificado -->
        <div class="chart-card-minimal alerts-card">
            <div class="chart-header-clean">
                <div>
                    <h3>Centro de Alertas</h3>
                    <p>Próximos 7 días</p>
                </div>
            </div>
            <div id="unified-alerts-list" class="unified-alerts-list">
                <div class="loader-small" style="margin: 2rem auto;"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Cargar el Timeline de Hoy
    fetch('index.php?action=get_timeline_ajax')
        .then(response => response.json())
        .then(res => {
            const container = document.getElementById('vet-timeline-container');
            if (!res.success || !res.citas || res.citas.length === 0) {
                container.innerHTML = `
                    <div class="empty-state-clean">
                        <i class="far fa-calendar-check"></i>
                        <p>No tienes citas programadas para hoy.</p>
                        <a href="index.php?action=vet_agenda" class="btn-secondary-sm">Revisar otros días</a>
                    </div>`;
                return;
            }

            const html = res.citas.map(cita => {
                const time = cita.hora ? cita.hora.substring(0, 5) : '—';
                const statusClass = cita.estado === 'completada' ? 'status-done' : (cita.estado === 'cancelada' ? 'status-canceled' : 'status-pending');
                const typeIcon = cita.tipo === 'vacunacion' ? '<i class="fas fa-syringe text-emerald"></i>' : '<i class="fas fa-stethoscope text-blue"></i>';
                
                return `
                    <div class="timeline-item ${statusClass}">
                        <div class="timeline-time">${time}</div>
                        <div class="timeline-dot"></div>
                        <div class="timeline-content">
                            <h4>${cita.mascota_nombre || 'Mascota'} <span class="timeline-type">${typeIcon} ${cita.tipo || 'Consulta'}</span></h4>
                            <p>Propietario: ${cita.propietario_nombre || 'No registrado'}</p>
                        </div>
                    </div>
                `;
            }).join('');
            container.innerHTML = html;
        })
        .catch(error => {
            document.getElementById('vet-timeline-container').innerHTML = '<div class="empty-state-clean"><p>Error cargando agenda.</p></div>';
        });

    // 2. Cargar el Centro de Alertas Unificado
    fetch('index.php?action=get_pendientes_ajax')
        .then(r => r.json())
        .then(res => {
            const container = document.getElementById('unified-alerts-list');
            if (!res.success) return;
            const p = res.pendientes;
            
            let alertsHtml = '';
            
            // Vacunas
            if(p.vacunas_proximas && p.vacunas_proximas.length > 0) {
                p.vacunas_proximas.slice(0,5).forEach(v => {
                    alertsHtml += `
                        <div class="alert-item alert-warning">
                            <div class="alert-icon"><i class="fas fa-syringe"></i></div>
                            <div class="alert-info">
                                <strong>Vacuna: ${v.mascota}</strong>
                                <span>${v.nombre_vacuna} - ${v.fecha_proxima_dosis}</span>
                            </div>
                        </div>`;
                });
            }

            // Desparasitaciones
            if(p.desparasitaciones_proximas && p.desparasitaciones_proximas.length > 0) {
                p.desparasitaciones_proximas.slice(0,5).forEach(d => {
                    alertsHtml += `
                        <div class="alert-item alert-info-blue">
                            <div class="alert-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="alert-info">
                                <strong>Desparasitante: ${d.mascota}</strong>
                                <span>${d.producto} - ${d.fecha_proxima}</span>
                            </div>
                        </div>`;
                });
            }

            if(alertsHtml === '') {
                container.innerHTML = `
                    <div class="empty-state-clean">
                        <i class="fas fa-check-circle" style="color:#10B981; font-size: 2rem;"></i>
                        <p>Todo al día. No hay alertas pendientes.</p>
                    </div>`;
            } else {
                container.innerHTML = alertsHtml;
            }
        });

    // 3. Renderizar Gráficas con datos reales del backend
    fetch('index.php?action=get_charts_data_ajax')
        .then(r => r.json())
        .then(res => {
            if (!res.success) return;
            const d = res.data;

            // --- Gráfica Demografía (Donut) ---
            let especiesData = d.mis_especies;
            if (!especiesData || especiesData.length === 0) {
                // Mock data para Demo
                especiesData = [
                    { nombre_especie: 'Perros', total: 65 },
                    { nombre_especie: 'Gatos', total: 30 },
                    { nombre_especie: 'Exóticos', total: 5 }
                ];
            }
            
            const labelsDemog = especiesData.map(item => item.nombre_especie);
            const dataValsDemog = especiesData.map(item => parseInt(item.total));
            const colorsDemog = ['#254EDD', '#60A5FA', '#DBEAFE', '#93C5FD', '#1E3A8A'];
            
            const ctxDemografia = document.getElementById('chart-especies-demografia').getContext('2d');
            new Chart(ctxDemografia, {
                type: 'doughnut',
                data: {
                    labels: labelsDemog,
                    datasets: [{
                        data: dataValsDemog,
                        backgroundColor: colorsDemog.slice(0, dataValsDemog.length),
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: { legend: { display: false } }
                }
            });

            let totalMascotas = dataValsDemog.reduce((a, b) => a + b, 0);
            document.getElementById('demografia-legend').innerHTML = especiesData.map((item, i) => {
                let perc = Math.round((parseInt(item.total) / totalMascotas) * 100);
                return `<div class="legend-item"><span class="legend-color" style="background:${colorsDemog[i]}"></span> ${item.nombre_especie} (${perc}%)</div>`;
            }).join('');

            // --- Gráfica Tendencia (Line) con Doble Filtro ---
            const ctxMotivos = document.getElementById('chart-motivos-consulta').getContext('2d');
            let tendenciaChart = null;
            let rawTrendData = d.tendencia_citas || [];

            function renderTrendChart() {
                const timeFilter = document.getElementById('filtro-rango-tiempo').value;
                const typeFilter = document.getElementById('filtro-tendencia-motivo').value;
                
                let dateMap = {};
                let typesSet = new Set();
                
                const now = new Date();
                const currentMonth = now.getMonth();
                const currentYear = now.getFullYear();
                
                let hasData = false;

                if (rawTrendData.length > 0) {
                    rawTrendData.forEach(row => {
                        const rowDate = new Date(row.fecha + "T00:00:00");
                        const rowMonth = rowDate.getMonth();
                        const rowYear = rowDate.getFullYear();
                        
                        let includeRow = false;
                        
                        if (timeFilter === 'este_mes') {
                            if (rowMonth === currentMonth && rowYear === currentYear) includeRow = true;
                        } else if (timeFilter === 'mes_pasado') {
                            let pm = currentMonth - 1;
                            let py = currentYear;
                            if (pm < 0) { pm = 11; py--; }
                            if (rowMonth === pm && rowYear === py) includeRow = true;
                        } else if (timeFilter === 'ultimos_3_meses') {
                            let cutoff = new Date(now);
                            cutoff.setMonth(now.getMonth() - 3);
                            if (rowDate >= cutoff) includeRow = true;
                        } else if (timeFilter === 'ultimos_6_meses') {
                            includeRow = true; // Query ya trae últimos 6 meses
                        }

                        if (includeRow) {
                            const tipo = row.tipo_cita || 'General';
                            typesSet.add(tipo);
                            
                            if (typeFilter === 'todos' || typeFilter === tipo) {
                                hasData = true;
                                // Para meses múltiples usar MMM-YYYY, para mes actual/pasado usar Dia
                                let dateLabel = row.fecha.substring(8, 10); // Día
                                if (timeFilter === 'ultimos_3_meses' || timeFilter === 'ultimos_6_meses') {
                                    const shortMonths = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                                    dateLabel = `${shortMonths[rowMonth]} ${rowYear.toString().substring(2,4)}`;
                                }
                                
                                if (!dateMap[dateLabel]) dateMap[dateLabel] = {};
                                if (!dateMap[dateLabel][tipo]) dateMap[dateLabel][tipo] = 0;
                                dateMap[dateLabel][tipo] += parseInt(row.total);
                            }
                        }
                    });
                }
                
                if (!hasData) {
                    // Mock Data solo si no hay data real para el filtro seleccionado
                    const labelsDemo = timeFilter.includes('meses') ? ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'] : ['01', '05', '10', '15', '20', '25', '30'];
                    typesSet.add('Consulta general');
                    typesSet.add('Vacunación');
                    
                    labelsDemo.forEach((lbl, idx) => {
                        dateMap[lbl] = {};
                        if (typeFilter === 'todos' || typeFilter === 'Consulta general') {
                            dateMap[lbl]['Consulta general'] = [5, 8, 3, 6, 9, 4, 7][idx % 7];
                        }
                        if (typeFilter === 'todos' || typeFilter === 'Vacunación') {
                            dateMap[lbl]['Vacunación'] = [2, 4, 1, 3, 5, 2, 4][idx % 7];
                        }
                    });
                }

                // Generar Labels (X-axis) ordenados. Si es por día, ordenar alfanumérico. Si es mes/año, confiar en el orden natural si ya está ordenado, o usar el array original
                const labelsTrend = Object.keys(dateMap);
                if (timeFilter === 'este_mes' || timeFilter === 'mes_pasado') {
                    labelsTrend.sort((a,b) => parseInt(a) - parseInt(b));
                }
                
                let activeTypes = Array.from(typesSet);
                if (typeFilter !== 'todos') {
                    activeTypes = activeTypes.filter(m => m === typeFilter);
                }
                
                const themeColors = [
                    { border: '#254EDD', bg: 'rgba(37, 78, 221, 0.2)' },
                    { border: '#10B981', bg: 'transparent', dash: [5,5] },
                    { border: '#F59E0B', bg: 'transparent' },
                    { border: '#8B5CF6', bg: 'transparent' }
                ];

                const datasetsTrend = activeTypes.map((tipo, i) => {
                    const style = themeColors[i % themeColors.length];
                    return {
                        label: tipo.charAt(0).toUpperCase() + tipo.slice(1),
                        data: labelsTrend.map(lbl => dateMap[lbl][tipo] || 0),
                        borderColor: style.border,
                        backgroundColor: i === 0 ? (() => {
                            let gradient = ctxMotivos.createLinearGradient(0, 0, 0, 400);
                            gradient.addColorStop(0, style.bg);
                            gradient.addColorStop(1, 'rgba(37,78,221,0)');
                            return gradient;
                        })() : style.bg,
                        borderWidth: i === 0 ? 3 : 2,
                        borderDash: style.dash || [],
                        tension: 0.4,
                        fill: i === 0,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: style.border,
                        pointBorderWidth: 2,
                        pointRadius: 4
                    };
                });

                if (tendenciaChart) {
                    tendenciaChart.destroy();
                }

                tendenciaChart = new Chart(ctxMotivos, {
                    type: 'line',
                    data: { labels: labelsTrend, datasets: datasetsTrend },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 12, font: {family: "'Inter', sans-serif"} } } },
                        scales: {
                            y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f1f5f9' }, border: {display: false}, ticks: {stepSize: 1} },
                            x: { grid: { display: false }, border: {display: false} }
                        },
                        interaction: { mode: 'index', intersect: false }
                    }
                });
            }

            // Inicializar filtro de tipos
            let selectFiltroTipo = document.getElementById('filtro-tendencia-motivo');
            let todosTipos = new Set();
            if (rawTrendData.length > 0) {
                rawTrendData.forEach(row => todosTipos.add(row.tipo_cita || 'General'));
            } else {
                todosTipos.add('Consulta general');
                todosTipos.add('Vacunación');
            }
            
            todosTipos.forEach(tipo => {
                let opt = document.createElement('option');
                opt.value = tipo;
                opt.textContent = tipo.charAt(0).toUpperCase() + tipo.slice(1);
                selectFiltroTipo.appendChild(opt);
            });

            // Listeners
            selectFiltroTipo.addEventListener('change', renderTrendChart);
            document.getElementById('filtro-rango-tiempo').addEventListener('change', renderTrendChart);

            // Primer renderizado
            renderTrendChart();
        });

    // 4. Cargar KPIs originales
    fetch('index.php?action=get_stats_ajax')
        .then(r => r.json())
        .then(res => {
            if(res.success && res.stats) {
                document.getElementById('stat-vet-citas-hoy').textContent = res.stats.citas_hoy || '0';
                document.getElementById('stat-vet-consultas').textContent = res.stats.consultas_hoy || '0';
                document.getElementById('stat-vet-pacientes').textContent = res.stats.pacientes_atendidos || '0';
            } else {
                document.getElementById('stat-vet-citas-hoy').textContent = '0';
                document.getElementById('stat-vet-consultas').textContent = '0';
                document.getElementById('stat-vet-pacientes').textContent = '0';
            }
        }).catch(err => {
            document.getElementById('stat-vet-citas-hoy').textContent = '0';
            document.getElementById('stat-vet-consultas').textContent = '0';
            document.getElementById('stat-vet-pacientes').textContent = '0';
        });
});
</script>
