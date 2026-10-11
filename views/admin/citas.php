<link rel="stylesheet" href="css/gestion.css?v=1">
<link rel="stylesheet" href="css/admin-citas.css?v=1">
<?php
$nombre = explode(" ", trim($_SESSION["usuario_nombre"]))[0];
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js"></script>

<div class="animate__animated animate__fadeIn">
    <div class="header-container-white gestion-cabecera">
        <div class="head-title-desc gestion-descripcion">
            <h1 class="users-page-title gestion-titulo">Gestión de Citas</h1>
            <p class="users-module-desc gestion-subtitulo">Citas de la clínica activa, agrupadas por veterinario</p>
        </div>
        
        <!-- Mini KPIs Compactos Integrados -->
        <div class="mini-kpis-container gestion-indicadores">
            <div class="mini-kpi">
                <svg id="kpi-total-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-total">0</span>
                    <span class="mini-kpi-label">Total Citas</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-pendientes-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-pendientes">0</span>
                    <span class="mini-kpi-label">Pendientes</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-completadas-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-completadas">0</span>
                    <span class="mini-kpi-label">Completadas</span>
                </div>
            </div>
            <div class="kpi-divider"></div>
            <div class="mini-kpi">
                <svg id="kpi-canceladas-spark" class="kpi-sparkline" viewBox="0 0 100 30" preserveAspectRatio="none"></svg>
                <div class="mini-kpi-content">
                    <span class="mini-kpi-value" id="kpi-canceladas">0</span>
                    <span class="mini-kpi-label">Canceladas</span>
                </div>
            </div>
        </div>

        <div class="header-actions gestion-acciones">
            <button class="btn-secondary" data-ui-accion="exportar-citas-excel">
                <i class="fas fa-file-excel gestion-icono-azul"></i> Excel
            </button>
            <button class="btn-secondary" data-ui-accion="exportar-citas-pdf">
                <i class="fas fa-file-pdf gestion-icono-azul"></i> PDF
            </button>
        </div>
    </div>

    <!-- Filtros Avanzados -->
    <div class="filters-bar card-filters">
        <div class="filter-group">
            <label><i class="far fa-calendar-alt"></i> Fecha:</label>
            <select id="filtroFecha" data-filtrar-citas>
                <option value="">Todas</option>
                <option value="hoy" selected>Hoy</option>
                <option value="ayer">Ayer</option>
                <option value="semana">Esta semana</option>
                <option value="mes">Este mes</option>
                <option value="custom">Personalizada</option>
            </select>
            <input type="date" id="filtroFechaCustom" hidden data-filtrar-citas>
        </div>
        <div class="filter-group">
            <label>Tipo de cita:</label>
            <select id="filtroTipoCita" data-filtrar-citas>
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Estado:</label>
            <select id="filtroEstado" data-filtrar-citas>
                <option value="">Todos</option>
                <option value="pendiente">Pendiente</option>
                <option value="confirmada">Confirmada</option>
                <option value="en_curso">En curso</option>
                <option value="completada">Completada</option>
                <option value="no_asistio">No asistió</option>
                <option value="sin_cerrar">Sin cerrar</option>
                <option value="cancelada">Cancelada</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Veterinario:</label>
            <select id="filtroVet" data-filtrar-citas>
                <option value="">Todos</option>
            </select>
        </div>
        <div class="filter-search">
            <i class="fas fa-search"></i>
            <input type="text" id="filtroSearch" placeholder="Buscar paciente o cliente..." data-buscar-citas>
        </div>
        <button class="btn-clean" data-ui-accion="limpiar-citas">
            <i class="fas fa-eraser"></i> Limpiar
        </button>
    </div>

    <!-- Contenedor Kanban Carousel -->
    <div class="kanban-carousel-container citas-carrusel">
        <button class="carousel-btn left" data-ui-accion="carrusel-citas" data-direccion="left" id="btnScrollLeft" hidden>
            <i class="fas fa-chevron-left"></i>
        </button>
        
        <div class="kanban-wrapper" id="kanbanWrapper">
            <div class="kanban-board" id="kanbanBoard">
                <div class="citas-cargando">
                    <div class="loader-small citas-loader"></div>
                </div>
            </div>
        </div>

        <button class="carousel-btn right" data-ui-accion="carrusel-citas" data-direccion="right" id="btnScrollRight" hidden>
            <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<script src="js/indicadores.js?v=1"></script>
<script src="js/admin-citas.js?v=1"></script>
