<?php
$nombre = explode(" ", trim($_SESSION["usuario_nombre"]))[0];
?>

<?php
setlocale(LC_TIME, 'es_ES.UTF-8', 'es_CO.UTF-8', 'spanish');
$fecha_banner = mb_strtoupper(strftime("%A, %d DE %B %Y"));
?>

<style>
/* Reducir el tamaño de los KPIs en el Dashboard Principal */
.stats-row .stat-card {
    padding: 1rem 1.2rem !important;
}
.stats-row .sc-header i {
    width: 30px !important;
    height: 30px !important;
    font-size: 0.85rem !important;
}
.stats-row .sc-body h2 {
    font-size: 1.6rem !important;
    margin: 0.2rem 0 !important;
}
.stats-row .sc-footer {
    font-size: 0.7rem !important;
    margin-top: 0.3rem !important;
}
.stats-row .add-metric strong {
    font-size: 1.4rem !important;
}
</style>

<div class="hero-banner animate__animated animate__fadeInDown">
    <div class="hero-content">
        <div class="hero-date"><?= $fecha_banner ?></div>
        <h1 class="hero-title">Resumen del Panel</h1>
        <div class="hero-stats">
            <span class="hero-greeting">Bienvenido, <?= htmlspecialchars($nombre) ?></span>
            <span style="opacity: 0.5; font-size: 0.8rem;">•</span>
            <div class="hero-badges">
                <span class="hero-badge"><i class="fas fa-check-circle"></i> <span id="hero-clientes-activos">14</span> clientes activos</span>
                <span class="hero-badge inactivos"><i class="fas fa-exclamation-circle"></i> <span id="hero-inactivos">2</span> inactivos</span>
                <span class="hero-badge"><i class="fas fa-box"></i> <span id="hero-productos">19</span> productos activos</span>
            </div>
        </div>
    </div>
    <div class="hero-image">
        <!-- El usuario debe guardar su imagen sin fondo como mascotas_hero.png en la carpeta public/img -->
        <img src="img/pets.png" alt="Mascotas" onerror="this.src='img/hero-puppy.png';">
    </div>
</div>

<!-- Contenedor principal de Layout (Gráficos Izquierda, KPIs Derecha) -->
<div class="panel-grid-main">

    <!-- Lado Izquierdo: Gráficos y Tablas -->
    <div class="panel-grid-top animate__animated animate__fadeInUp animate__delay-1s">
        <!-- Gráfico de Flujo de Citas -->
        <div class="panel-card">
            <div class="card-header-flex">
                <h3><i class="fas fa-chart-line"></i> Flujo de Citas</h3>
            </div>
            <div class="panel-chart-lg">
                <canvas id="chart-citas-mes"></canvas>
            </div>
        </div>

        <!-- 2. Citas de Hoy -->
        <div class="panel-card agenda-timeline-card">
            <div class="card-header-flex">
                <h3><i class="far fa-calendar-check"></i> Citas de Hoy</h3>
                <a href="index.php?action=admin_citas" class="btn-view-all">Ver Todas</a>
            </div>
            <div id="todayAppointments" class="today-appointments custom-scroll" style="overflow-y: auto; max-height: 190px; padding-right: 0.5rem;">
                <div class="loader-small" style="margin: 1.5rem auto;"></div>
            </div>
        </div>
        <!-- 3. Por Especie (Alineado con los otros dos) -->
        <div class="panel-card animate__animated animate__fadeInUp animate__delay-1s">
            <div class="card-header-flex">
                <h3><i class="fas fa-paw"></i> Especies</h3>
            </div>
            <div class="panel-chart-sm">
                <canvas id="chart-especies"></canvas>
            </div>
            <div id="especies-legend" style="margin-top:1rem; display:flex; flex-wrap: wrap; justify-content:center; gap:1rem; font-size:0.75rem;"></div>
        </div>

    </div>

    <!-- Lado Derecho: Contenedor Vertical para KPIs y Dona -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- KPIs (Cuadrícula 2x2 compacta) -->
        <div class="stats-row animate__animated animate__fadeInUp" style="display: grid !important; grid-template-columns: 1fr 1fr; gap: 1rem;">
            
            <!-- 1. Total Active Patients -->
            <div class="stat-card primary" style="min-height: 100px;">
                <div class="sc-header">
                    <i class="fas fa-paw"></i>
                    <span>Pacientes Activos</span>
                </div>
                <div class="sc-body">
                    <h2 id="stat-pacientes">—</h2>
                    <span class="sc-badge positive">+4.2%</span>
                </div>
                <p class="sc-footer">Mascotas activas</p>
            </div>

            <!-- 2. Appointments -->
            <div class="stat-card" style="min-height: 100px;">
                <div class="sc-header">
                    <i class="fas fa-calendar-check" style="background:rgba(0,0,0,0.05); color:var(--text-primary);"></i>
                    <span style="color:var(--text-primary);">Citas Hoy</span>
                </div>
                <div class="sc-body">
                    <h2 id="stat-citas-hoy" style="color:var(--text-primary);">—</h2>
                    <span class="sc-badge positive" style="background:rgba(16,185,129,0.1); color:#10B981;">Hoy</span>
                </div>
                <p class="sc-footer">Pacientes programados</p>
            </div>

            <!-- 3. Clientes -->
            <div class="stat-card" style="min-height: 100px;">
                <div class="sc-header">
                    <i class="fas fa-users" style="background:rgba(0,0,0,0.05); color:var(--text-primary);"></i>
                    <span style="color:var(--text-primary);">Clientes</span>
                </div>
                <div class="sc-body">
                    <h2 id="stat-clientes" style="color:var(--text-primary);">—</h2>
                    <span class="sc-badge positive" style="background:rgba(16,185,129,0.1); color:#10B981;">+1.5%</span>
                </div>
                <p class="sc-footer">Dueños registrados</p>
            </div>

            <!-- 4. Consultas Mes -->
            <div class="stat-card dashed" onclick="window.location.href='index.php?action=admin_reportes'" style="min-height: 100px; display: flex; align-items: center; justify-content: center;">
                <div class="add-metric">
                    <div class="am-icon"><i class="fas fa-stethoscope"></i></div>
                    <span>Consultas Mes</span>
                    <strong id="stat-consultas" style="font-size:1.5rem; color:var(--text-primary); margin-top:0.3rem;">—</strong>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cargar todas las citas del día usando el endpoint existente
    fetch('index.php?action=get_timeline_ajax')
        .then(response => response.json())
        .then(res => {
            const appointmentsContainer = document.getElementById('todayAppointments');
            
            if (!res.success || !res.citas || res.citas.length === 0) {
                appointmentsContainer.innerHTML = '<div class="schedule-empty">No hay citas programadas para hoy</div>';
                return;
            }

            const STATE_COLORS = {
                pendiente: '#F59E0B',
                confirmada: '#5560FF',
                completada: '#10B981',
                cancelada: '#EF4444'
            };
            const STATE_LABELS = {
                pendiente: 'Pendiente',
                confirmada: 'Confirmada',
                completada: 'Completada',
                cancelada: 'Cancelada'
            };

            const appointmentsHTML = `
                <table class="data-table" style="font-size: 0.85rem;">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Mascota</th>
                            <th>Propietario</th>
                            <th>Veterinario</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${res.citas.map(cita => {
                            const time = cita.hora ? cita.hora.substring(0, 5) : '—';
                            const petName = cita.mascota_nombre || 'Mascota';
                            const ownerName = cita.propietario_nombre || '—';
                            const vetName = cita.veterinario_nombre ? cita.veterinario_nombre.split(' ')[0] : '—';
                            const estado = cita.estado || 'pendiente';
                            const stateColor = STATE_COLORS[estado] || '#6B7280';
                            const stateLabel = STATE_LABELS[estado] || estado;
                            
                            return `
                                <tr>
                                    <td><strong>${time}</strong></td>
                                    <td>${petName}</td>
                                    <td>${ownerName}</td>
                                    <td>Dr. ${vetName}</td>
                                    <td><span style="background: ${stateColor}20; color: ${stateColor}; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; font-weight: 600;">${stateLabel}</span></td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            `;

            appointmentsContainer.innerHTML = appointmentsHTML;
        })
        .catch(error => {
            console.error('Error cargando citas:', error);
            document.getElementById('todayAppointments').innerHTML = '<div class="schedule-empty">Error al cargar las citas</div>';
        });
});
</script>
