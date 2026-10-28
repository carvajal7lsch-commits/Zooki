<?php require_once __DIR__ . '/../../config/App.php'; ?>
<link rel="stylesheet" href="css/estados-cita.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="css/agenda-herramientas.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="css/admin-citas.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="css/portal/calendario.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<script src="https://cdn.jsdelivr.net/npm/flatpickr" defer></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.3.0/exceljs.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.1/jspdf.plugin.autotable.min.js" defer></script>
<section class="citas-hoja" id="citasAdmin">
    <section id="agenda-panel-agenda" role="tabpanel" aria-labelledby="agenda-tab-agenda" class="citas-agenda">
        <div class="citas-herramientas">
            <div class="citas-periodos" role="group" aria-label="Periodo de citas">
                <?php foreach (['hoy' => 'Hoy', 'ayer' => 'Ayer', 'semana' => 'Semana', 'mes' => 'Mes', '' => 'Todas'] as $valorPeriodo => $textoPeriodo): ?>
                <button type="button" data-citas-periodo="<?= $valorPeriodo ?>" aria-pressed="<?= $valorPeriodo === 'hoy' ? 'true' : 'false' ?>"><?= $textoPeriodo ?></button>
                <?php endforeach; ?>
                <select id="filtroFecha" data-filtrar-citas aria-label="Periodo" hidden><option value="hoy">Hoy</option><option value="ayer">Ayer</option><option value="semana">Semana</option><option value="mes">Mes</option><option value="">Todas</option><option value="custom">Fecha específica</option></select>
            </div>
            <div data-citas-fecha-origen>
                <label class="citas-fecha" data-citas-fecha><span>Fecha específica (opcional)</span><input type="text" id="filtroFechaCustom" placeholder="dd/mm/aaaa" autocomplete="off" data-filtrar-citas aria-label="Fecha específica"><button type="button" class="citas-icono" data-citas-calendario aria-label="Elegir fecha"><i class="bi bi-calendar3" aria-hidden="true"></i></button></label>
            </div>
            <label class="citas-buscador"><i class="bi bi-search" aria-hidden="true"></i><input type="search" id="filtroSearch" placeholder="Buscar paciente, propietario o motivo" aria-label="Buscar paciente, propietario o motivo" data-buscar-citas></label>
            <div class="citas-selectores" data-citas-selectores-origen>
                <div data-citas-selectores>
                    <label><span class="ah-oculto">Tipo de cita</span><select id="filtroTipoCita" data-filtrar-citas><option value="">Todos los tipos</option></select></label>
                    <label class="citas-estado-select"><span class="ah-oculto">Estado</span><select id="filtroEstado" data-filtrar-citas><option value="">Todos los estados</option><?php foreach (['pendiente' => 'Pendiente', 'confirmada' => 'Confirmada', 'en_curso' => 'En curso', 'completada' => 'Completada', 'no_asistio' => 'No asistió', 'sin_cerrar' => 'Sin cerrar', 'cancelada' => 'Cancelada'] as $valorEstado => $textoEstado): ?><option value="<?= $valorEstado ?>"><?= $textoEstado ?></option><?php endforeach; ?></select></label>
                </div>
            </div>
            <label class="citas-equipo"><span class="ah-oculto">Veterinario</span><select id="filtroVet" data-filtrar-citas><option value="">Todo el equipo</option></select></label>
            <button type="button" class="citas-boton citas-filtros-abrir" data-abrir-dialogo="citasFiltros"><i class="bi bi-sliders" aria-hidden="true"></i>Filtros<span class="citas-conteo" data-citas-filtros-conteo hidden>0</span></button>
            <div data-citas-exportar-origen>
                <details class="citas-exportar" data-citas-exportar>
                    <summary class="citas-boton" aria-label="Exportar citas"><i class="bi bi-box-arrow-up" aria-hidden="true"></i><span>Exportar</span></summary>
                    <div class="citas-exportar-menu"><button type="button" data-ui-accion="exportar-citas-excel">Excel</button><button type="button" data-ui-accion="exportar-citas-pdf">PDF</button></div>
                </details>
            </div>
        </div>
        <div class="citas-resumen">
            <strong data-citas-periodo-texto>Hoy</strong>
            <div class="citas-metricas"><span class="citas-metrica"><span id="kpi-total">0</span> citas</span><span class="citas-metrica" data-estado="pendiente"><span id="kpi-pendientes">0</span> pendientes</span><span class="citas-metrica" data-estado="completada"><span id="kpi-completadas">0</span> completadas</span><span class="citas-metrica" data-estado="cancelada"><span id="kpi-canceladas">0</span> canceladas</span></div>
            <button type="button" class="citas-limpiar" data-ui-accion="limpiar-citas" hidden>Limpiar filtros</button>
        </div>
        <div class="citas-veterinarios" data-citas-veterinarios role="group" aria-label="Veterinario"></div>
        <div class="citas-carrusel">
            <button type="button" class="citas-flecha citas-flecha--izquierda" data-ui-accion="carrusel-citas" data-direccion="left" id="btnScrollLeft" aria-label="Veterinarios anteriores" hidden><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
            <div class="kanban-wrapper" id="kanbanWrapper"><div class="kanban-board" id="kanbanBoard"><p class="citas-vacio" role="status">Cargando citas…</p></div></div>
            <button type="button" class="citas-flecha citas-flecha--derecha" data-ui-accion="carrusel-citas" data-direccion="right" id="btnScrollRight" aria-label="Veterinarios siguientes" hidden><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
        </div>
    </section>
    <?php $agendaAdminIntegrada = true; require __DIR__ . '/../partials/agenda_herramientas.php'; unset($agendaAdminIntegrada); ?>
</section>
<dialog class="zk-dialogo citas-filtros" id="citasFiltros" aria-labelledby="citasFiltrosTitulo">
    <div class="zk-dialogo__form">
        <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar filtros"></button>
        <header class="zk-dialogo__head"><h2 id="citasFiltrosTitulo">Filtros</h2><button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar filtros"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
        <div class="zk-dialogo__cuerpo"><div data-citas-fecha-destino></div><div data-citas-selectores-destino></div><div class="citas-estados" role="group" aria-label="Estado"><span>Estado</span><div data-citas-estados></div></div></div>
        <footer class="zk-dialogo__pie"><button type="button" class="citas-limpiar" data-ui-accion="limpiar-citas">Limpiar</button><button type="button" class="zk-btn" data-cerrar-dialogo data-citas-ver>Ver 0 citas</button></footer>
    </div>
</dialog>
<dialog class="zk-dialogo citas-detalle" id="citasDetalle" aria-labelledby="citasDetalleTitulo">
    <div class="zk-dialogo__form">
        <button type="button" class="zk-hoja__asa" data-cerrar-dialogo aria-label="Cerrar detalle"></button>
        <header class="zk-dialogo__head"><span class="zk-dialogo__icono"><i class="bi bi-heart-pulse" aria-hidden="true"></i></span><div><h2 id="citasDetalleTitulo"><span data-cita-detalle="mascota_nombre"></span><span class="kb-status" data-cita-detalle-estado></span></h2><p data-cita-detalle-fecha></p></div><button type="button" class="zk-dialogo__cerrar" data-cerrar-dialogo aria-label="Cerrar detalle"><i class="bi bi-x-lg" aria-hidden="true"></i></button></header>
        <div class="zk-dialogo__cuerpo"><dl class="citas-detalle-datos"><div><dt>Paciente</dt><dd data-cita-detalle="mascota_nombre"></dd></div><div><dt>Propietario</dt><dd data-cita-detalle="propietario_nombre"></dd></div><div><dt>Veterinario</dt><dd data-cita-detalle="veterinario_nombre"></dd></div><div><dt>Tipo de cita</dt><dd data-cita-detalle="tipo_cita_nombre"></dd></div></dl><div class="citas-motivo"><h3>Motivo</h3><p data-cita-detalle="motivo"></p></div></div>
    </div>
</dialog>
<script src="js/selector-fechas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="js/admin-citas.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>"></script>
