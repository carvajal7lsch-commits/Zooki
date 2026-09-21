<?php
/**
 * Panel de inicio del administrador: la operación de la clínica hoy (HU-57).
 * Los datos llegan de PanelController::datosAdministrador() en $panel.
 */
require_once __DIR__ . '/../../helpers/ResumenPanel.php';

if (!isset($panel)) {
    require_once __DIR__ . '/../../controllers/PanelController.php';
    $panel = (new PanelController())->datosAdministrador();
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$cont = $panel['contadores'];
$pend = $panel['pendientes'];
$variacion = $panel['variacion_consultas'];

// Grupo de cada estado para los filtros de la lista de citas.
$grupoEstado = [
    'pendiente' => 'por_atender', 'confirmada' => 'por_atender', 'en_curso' => 'por_atender', 'sin_cerrar' => 'por_atender',
    'completada' => 'cerradas', 'cerrada_sin_consulta' => 'cerradas',
    'cancelada' => 'no_atendidas', 'no_asistio' => 'no_atendidas',
];

$totalSinCerrar = array_sum($pend['sin_cerrar']);
$totalEnCursoOtroDia = array_sum($pend['en_curso_otro_dia']);
$listaVets = fn(array $porVet) => implode(', ', array_map(
    fn($n, $v) => $e(ResumenPanel::primerNombre($v)) . " ($n)",
    $porVet,
    array_keys($porVet)
));
?>
<div class="panel panel--admin">

    <header class="panel-cabecera">
        <div>
            <p class="panel-cabecera__fecha"><?= $e(ucfirst($panel['fecha'])) ?></p>
            <h2 class="panel-cabecera__titulo">Hoy en la clínica</h2>
        </div>
        <div class="panel-cabecera__acciones">
            <a class="panel-btn panel-btn--sec" href="index.php?action=admin_usuarios"><i class="fas fa-users"></i> Usuarios</a>
            <a class="panel-btn panel-btn--pri" href="index.php?action=admin_citas"><i class="far fa-calendar-alt"></i> Gestión de citas</a>
        </div>
    </header>

    <div class="panel-contadores">
        <div class="panel-contador">
            <span class="panel-contador__valor"><?= (int) $cont['citas'] ?></span>
            <span class="panel-contador__etiqueta">Citas hoy</span>
        </div>
        <div class="panel-contador">
            <span class="panel-contador__valor"><?= (int) $cont['atendidas'] ?></span>
            <span class="panel-contador__etiqueta">Atendidas</span>
        </div>
        <div class="panel-contador">
            <span class="panel-contador__valor"><?= (int) $cont['no_asistio'] ?></span>
            <span class="panel-contador__etiqueta">No asistieron</span>
        </div>
        <div class="panel-contador">
            <span class="panel-contador__valor"><?= (int) $panel['consultas_mes'] ?></span>
            <span class="panel-contador__etiqueta">Consultas del mes</span>
            <?php if ($variacion === null): ?>
                <span class="panel-contador__nota">Sin datos de <?= $e($panel['mes_anterior']) ?> para comparar</span>
            <?php else: ?>
                <span class="panel-contador__nota" data-tendencia="<?= $variacion > 0 ? 'sube' : ($variacion < 0 ? 'baja' : 'igual') ?>">
                    <i class="fas <?= $variacion > 0 ? 'fa-arrow-up' : ($variacion < 0 ? 'fa-arrow-down' : 'fa-equals') ?>"></i>
                    <?= abs($variacion) ?> % vs. mismo periodo de <?= $e($panel['mes_anterior']) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel-cuerpo">

        <!-- Citas de hoy -->
        <section class="panel-card panel-card--llena" aria-labelledby="tituloCitas">
            <div class="panel-card__cabecera">
                <h3 class="panel-card__titulo" id="tituloCitas">Citas de hoy</h3>
                <a class="panel-enlace" href="index.php?action=admin_citas">Gestionar citas <i class="fas fa-chevron-right"></i></a>
            </div>
            <?php if (!$panel['citas']): ?>
                <p class="panel-vacio">No hay citas agendadas para hoy.</p>
            <?php else: ?>
                <div class="panel-filtros" role="group" aria-label="Filtrar citas">
                    <button type="button" class="panel-filtro is-activo" data-filtro="todas" aria-pressed="true">Todas</button>
                    <button type="button" class="panel-filtro" data-filtro="por_atender" aria-pressed="false">Por atender</button>
                    <button type="button" class="panel-filtro" data-filtro="cerradas" aria-pressed="false">Atendidas</button>
                    <button type="button" class="panel-filtro" data-filtro="no_atendidas" aria-pressed="false">Canceladas y no asistidas</button>
                </div>
                <ul class="panel-lista panel-desplazable" id="panelCitasHoy">
                    <?php foreach ($panel['citas'] as $c): ?>
                        <li class="panel-fila" data-grupo="<?= $e($grupoEstado[$c['estado']] ?? 'por_atender') ?>">
                            <span class="panel-fila__hora"><?= $e(ResumenPanel::hora($c['hora'])) ?></span>
                            <div class="panel-fila__info">
                                <span class="panel-fila__titulo"><?= $e($c['mascota']) ?> <span class="panel-texto-sec">· <?= $e($c['tipo']) ?></span></span>
                                <span class="panel-texto-sec"><?= $e($c['propietario']) ?> · Dr(a). <?= $e(ResumenPanel::primerNombre($c['veterinario'])) ?></span>
                            </div>
                            <span class="panel-badge" data-estado="<?= $e($c['estado']) ?>"><?= $e(ResumenPanel::etiquetaEstado($c['estado'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p class="panel-vacio" id="panelCitasVacio" hidden>No hay citas en este filtro.</p>
            <?php endif; ?>
        </section>

        <div class="panel-col">

            <!-- Carga de cada veterinario -->
            <section class="panel-card panel-card--acotada" aria-labelledby="tituloCarga">
                <h3 class="panel-card__titulo" id="tituloCarga">Agenda por veterinario</h3>
                <?php if (!$panel['carga']): ?>
                    <p class="panel-vacio">No hay veterinarios activos.</p>
                <?php else: ?>
                    <ul class="panel-carga panel-desplazable">
                        <?php foreach ($panel['carga'] as $v):
                            $total = (int) $v['total'];
                            $atendidas = (int) $v['atendidas'];
                            $pct = $total > 0 ? round($atendidas / $total * 100) : 0; ?>
                            <li class="panel-carga__fila">
                                <span class="panel-carga__nombre"><?= $e($v['veterinario']) ?></span>
                                <span class="panel-carga__barra" role="img" aria-label="<?= $atendidas ?> de <?= $total ?> citas atendidas">
                                    <span class="panel-carga__progreso" style="--pct: <?= $pct ?>%"></span>
                                </span>
                                <span class="panel-carga__valor"><?= $total === 0 ? 'Sin citas' : "$atendidas / $total" ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>

            <!-- Tendencia de asistencia -->
            <section class="panel-card panel-card--llena" aria-labelledby="tituloTendencia">
                <h3 class="panel-card__titulo" id="tituloTendencia">Asistencia de los últimos 6 meses</h3>
                <div class="panel-grafica">
                    <canvas id="panelTendencia" data-serie="<?= $e(json_encode($panel['tendencia'], JSON_UNESCAPED_UNICODE)) ?>" aria-describedby="tablaTendencia"></canvas>
                </div>
                <table class="panel-sr" id="tablaTendencia">
                    <caption>Citas atendidas y no asistidas por mes</caption>
                    <thead><tr><th scope="col">Mes</th><th scope="col">Atendidas</th><th scope="col">No asistieron</th></tr></thead>
                    <tbody>
                        <?php foreach ($panel['tendencia'] as $m): ?>
                            <tr><th scope="row"><?= $e($m['etiqueta']) ?></th><td><?= (int) $m['atendidas'] ?></td><td><?= (int) $m['no_asistidas'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>

        <!-- Pendientes de la operación -->
        <section class="panel-card panel-card--llena" aria-labelledby="tituloPendientesAdmin">
            <h3 class="panel-card__titulo" id="tituloPendientesAdmin">Pendientes</h3>
            <ul class="panel-pendientes panel-desplazable">
                <li class="panel-pendiente" data-nivel="<?= $totalSinCerrar ? 'alerta' : 'ok' ?>">
                    <span class="panel-pendiente__valor"><?= $totalSinCerrar ?></span>
                    <div>
                        <p class="panel-pendiente__titulo">Atenciones sin cerrar</p>
                        <p class="panel-texto-sec"><?= $totalSinCerrar ? 'Debe cerrarlas su veterinario: ' . $listaVets($pend['sin_cerrar']) : 'Ninguna.' ?></p>
                    </div>
                </li>
                <?php if ($totalEnCursoOtroDia): ?>
                    <li class="panel-pendiente" data-nivel="aviso">
                        <span class="panel-pendiente__valor"><?= $totalEnCursoOtroDia ?></span>
                        <div>
                            <p class="panel-pendiente__titulo">Atenciones abiertas de días anteriores</p>
                            <p class="panel-texto-sec"><?= $listaVets($pend['en_curso_otro_dia']) ?></p>
                        </div>
                    </li>
                <?php endif; ?>
                <li class="panel-pendiente" data-nivel="<?= $pend['sin_marcar'] ? 'aviso' : 'ok' ?>">
                    <span class="panel-pendiente__valor"><?= (int) $pend['sin_marcar'] ?></span>
                    <div>
                        <p class="panel-pendiente__titulo">Citas pasadas sin marcar</p>
                        <p class="panel-texto-sec">Ya pasó su hora y no se atendieron ni se marcaron como no asistidas (últimos 30 días).</p>
                    </div>
                </li>
                <li class="panel-pendiente" data-nivel="<?= $pend['por_confirmar'] ? 'aviso' : 'ok' ?>">
                    <span class="panel-pendiente__valor"><?= (int) $pend['por_confirmar'] ?></span>
                    <div>
                        <p class="panel-pendiente__titulo">Citas por confirmar</p>
                        <p class="panel-texto-sec">Pendientes en los próximos 7 días.</p>
                    </div>
                </li>
            </ul>
            <a class="panel-enlace" href="index.php?action=admin_citas">Revisar en Gestión de citas <i class="fas fa-chevron-right"></i></a>
        </section>
    </div>
</div>
