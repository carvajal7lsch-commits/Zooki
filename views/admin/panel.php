<?php
/**
 * Inicio del administrador: la operación de la clínica activa (HU-6.2,
 * RE-6.2.1 a RE-6.2.13, RE-6.4.1). Diseño aprobado por el dueño:
 * specs/referencias/inicio-administrador.dc.html. Los datos llegan de
 * PanelController::datosAdministrador() en $panel; el día de hoy y el mes en
 * curso vienen pintados del servidor, sin esperar al AJAX.
 */
require_once __DIR__ . '/../../helpers/ResumenPanel.php';

if (!isset($panel)) {
    require_once __DIR__ . '/../../controllers/PanelController.php';
    $panel = (new PanelController())->datosAdministrador();
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$plural = fn(int $n, string $uno, string $varios) => $n === 1 ? $uno : $varios;
$cont = $panel['contadores'];
$pend = $panel['pendientes'];
$variacion = $panel['variacion_consultas'];

/** «Beto», «Beto y Elena» o «Beto, Elena y 2 más». */
$nombres = function (array $lista) {
    $lista = array_values(array_unique(array_filter(array_map('strval', $lista), fn($n) => $n !== '')));
    if (count($lista) <= 2) {
        return implode(' y ', $lista);
    }
    return $lista[0] . ', ' . $lista[1] . ' y ' . (count($lista) - 2) . ' más';
};

// RE-6.2.7: subtítulo de «Hoy en la clínica»; las no asistidas de hoy cumplen RE-6.2.1.
$veterinariosHoy = (int) $panel['veterinarios_hoy'];
$subtitulo = [$veterinariosHoy === 0 ? 'Ningún veterinario atiende hoy' : $veterinariosHoy . ' ' . $plural($veterinariosHoy, 'veterinario atendiendo', 'veterinarios atendiendo')];
$subtitulo[] = $panel['abierta_hasta'] === null ? 'cerrada hoy' : 'abierta hasta las ' . $panel['abierta_hasta'];
if ((int) $cont['no_asistio'] > 0) {
    $subtitulo[] = $cont['no_asistio'] . ' ' . $plural((int) $cont['no_asistio'], 'no asistió', 'no asistieron');
}
$cifras = [
    ['valor' => (int) $cont['citas'], 'texto' => $plural((int) $cont['citas'], 'cita hoy', 'citas hoy'), 'urgente' => false],
    ['valor' => (int) $cont['atendidas'], 'texto' => $plural((int) $cont['atendidas'], 'atendida', 'atendidas'), 'urgente' => false],
    ['valor' => (int) $panel['en_espera'], 'texto' => 'en espera', 'urgente' => false],
    ['valor' => (int) $panel['urgencias'], 'texto' => $plural((int) $panel['urgencias'], 'urgencia', 'urgencias'), 'urgente' => (int) $panel['urgencias'] > 0],
];

// RE-6.2.11: una fila por tipo de pendiente, solo los que tienen algo, cada uno con una pantalla que existe.
$pendientes = [];
if ($panel['sin_cerrar']) {
    $primera = $panel['sin_cerrar'][0];
    $n = count($panel['sin_cerrar']);
    $pendientes[] = ['clave' => 'cerrar', 'n' => $n, 'texto' => 'Sin cerrar',
        'detalle' => $primera['mascota'] . ', de ' . ResumenPanel::primerNombre($primera['veterinario']) . ' (' . mb_strtolower($primera['dia']) . ')' . ($n > 1 ? ' y ' . ($n - 1) . ' más' : ''),
        'accion' => 'Ver', 'etiqueta' => 'Ver las atenciones sin cerrar en Gestión de citas', 'href' => 'index.php?action=admin_citas'];
}
if ((int) $pend['sin_marcar'] > 0) {
    $pendientes[] = ['clave' => 'marcar', 'n' => (int) $pend['sin_marcar'], 'texto' => $plural((int) $pend['sin_marcar'], 'Cita sin marcar', 'Citas sin marcar'),
        'detalle' => 'últimos 30 días', 'accion' => 'Revisar', 'etiqueta' => 'Revisar las citas sin marcar en Gestión de citas', 'href' => 'index.php?action=admin_citas'];
}
if ((int) $pend['por_confirmar'] > 0) {
    $pendientes[] = ['clave' => 'confirmar', 'n' => (int) $pend['por_confirmar'], 'texto' => 'Por confirmar',
        'detalle' => 'próximos 7 días', 'accion' => 'Ver', 'etiqueta' => 'Ver las citas por confirmar en Gestión de citas', 'href' => 'index.php?action=admin_citas'];
}
if ($panel['ausencias_por_aprobar']) {
    $primera = $panel['ausencias_por_aprobar'][0];
    $n = count($panel['ausencias_por_aprobar']);
    $pendientes[] = ['clave' => 'ausencia', 'n' => $n, 'texto' => $plural($n, 'Ausencia por aprobar', 'Ausencias por aprobar'),
        'detalle' => ResumenPanel::primerNombre($primera['veterinario']) . ', ' . mb_strtolower($primera['dia']) . ($n > 1 ? ' y ' . ($n - 1) . ' más' : ''),
        'accion' => 'Revisar', 'etiqueta' => 'Revisar las ausencias en Configuración', 'href' => 'index.php?action=admin_configuracion#ausencias'];
}
if ($panel['propuestas_por_aprobar']) {
    $n = count($panel['propuestas_por_aprobar']);
    $pendientes[] = ['clave' => 'horario', 'n' => $n, 'texto' => $plural($n, 'Horario propuesto', 'Horarios propuestos'),
        'detalle' => $nombres(array_map(fn($p) => ResumenPanel::primerNombre($p['veterinario']), $panel['propuestas_por_aprobar'])),
        'accion' => 'Revisar', 'etiqueta' => 'Revisar los horarios propuestos en Configuración', 'href' => 'index.php?action=admin_configuracion#horarios'];
}
$totalPendientes = array_sum(array_column($pendientes, 'n'));

// RE-6.2.10: nombre de cada recurso del plan.
$recursosPlan = ['mascotas' => 'Mascotas', 'citas' => 'Citas de ' . $panel['mes_actual'], 'personal' => 'Personal'];

// RE-6.2.12 y RE-6.2.4: la serie de 6 meses en una escala común para que las barras se comparen.
$maximoSerie = max(1, ...array_map(fn($m) => (int) $m['atendidas'] + (int) $m['no_asistidas'], $panel['tendencia']));
$ultimoMes = count($panel['tendencia']) - 1;
?>
<div class="inicio inicio--admin" data-inicio data-inicio-accion="panel_admin_dia_ajax" data-hoy="<?= $e($panel['hoy']) ?>">

    <div class="inicio__arriba">
        <!-- Hoy en la clínica (RE-6.2.7) -->
        <section class="inicio-tile inicio-hero inicio-clinica" aria-labelledby="inicioTituloClinica">
            <span class="inicio-hero__circulo" aria-hidden="true"></span>
            <span class="inicio-hero__cuarto" aria-hidden="true"></span>
            <img class="inicio-hero__mascota inicio-clinica__mascota" src="img/golden-retriever.png" alt="" decoding="async">
            <div class="inicio-hero__contenido inicio-clinica__contenido">
                <div>
                    <p class="inicio-hero__sobre" id="inicioTituloClinica">Hoy en la clínica</p>
                    <p class="inicio-clinica__sub"><?= $e(implode(' · ', $subtitulo)) ?></p>
                </div>
                <ul class="inicio-clinica__cifras">
                    <?php foreach ($cifras as $cifra): ?>
                        <li class="inicio-clinica__cifra<?= $cifra['urgente'] ? ' inicio-clinica__cifra--urgente' : '' ?>">
                            <strong><?= (int) $cifra['valor'] ?></strong>
                            <span><?= $e($cifra['texto']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="inicio-hero__acciones">
                    <!-- RN-401: el administrador no crea citas; desde aquí las gestiona. -->
                    <a class="inicio-boton inicio-boton--blanco" href="index.php?action=admin_citas">Gestión de citas</a>
                </div>
            </div>
        </section>

        <!-- Calendario del mes (RE-6.2.8) -->
        <section class="inicio-tile inicio-cal" aria-labelledby="inicioTituloCal" data-inicio-calendario data-elegido="<?= $e($panel['hoy']) ?>">
            <div class="inicio-cal__contenido" data-inicio-calendario-contenido>
                <?php $mes = $panel['mes']; require __DIR__ . '/../partials/inicio_calendario.php'; ?>
            </div>
        </section>

        <!-- Uso del plan (RE-6.2.10, HU-0.4) -->
        <section class="inicio-tile inicio-plan" aria-labelledby="inicioTituloPlan">
            <div class="inicio-cabecera">
                <h2 class="inicio-titulo" id="inicioTituloPlan">Uso del plan</h2>
                <?php if ($panel['plan'] !== null): ?><span class="inicio-plan__nombre"><?= $e($panel['plan']['nombre']) ?></span><?php endif; ?>
            </div>
            <?php if ($panel['plan'] === null): ?>
                <p class="inicio-aviso" role="status">No se pudo leer el uso del plan. Intenta recargar.</p>
            <?php else: ?>
                <ul class="inicio-plan__lista">
                    <?php foreach ($recursosPlan as $recurso => $nombreRecurso): ?>
                        <?php $uso = $panel['plan']['recursos'][$recurso]; ?>
                        <li class="inicio-plan__uso inicio-plan__uso--<?= $e($uso['nivel']) ?>">
                            <span class="inicio-plan__fila">
                                <span><?= $e($nombreRecurso) ?></span>
                                <span class="inicio-plan__valor"><?= $uso['limite'] === null ? (int) $uso['uso'] . ' · sin límite' : (int) $uso['uso'] . ' / ' . (int) $uso['limite'] . ($uso['nivel'] === 'limite' ? ' · al límite' : '') ?></span>
                            </span>
                            <?php if ($uso['limite'] !== null): ?>
                                <span class="inicio-barra" aria-hidden="true"><span class="inicio-barra__relleno inicio-ancho--<?= ResumenPanel::escala5($uso['porcentaje']) ?>"></span></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="inicio-plan__accesos">
                <a class="inicio-rapido" href="index.php?action=admin_usuarios"><i class="fas fa-users" aria-hidden="true"></i> Usuarios</a>
                <a class="inicio-rapido" href="index.php?action=admin_configuracion"><i class="fas fa-cog" aria-hidden="true"></i> Configuración</a>
            </div>
        </section>
    </div>

    <div class="inicio__abajo">
        <!-- Agenda del día por veterinario (RE-6.2.9) -->
        <section class="inicio-tile inicio-dia inicio-agenda-tile" aria-labelledby="inicioTituloDia" data-inicio-dia aria-busy="false">
            <div class="inicio-dia__contenido" data-inicio-dia-contenido>
                <?php $agenda = $panel['agenda']; require __DIR__ . '/partials/inicio_agenda.php'; ?>
            </div>
        </section>

        <div class="inicio-col">
            <!-- Pendientes de la clínica (RE-6.2.3, RE-6.2.11) -->
            <section class="inicio-tile inicio-pendientes<?= $pendientes ? '' : ' inicio-pendientes--vacio' ?>" aria-labelledby="inicioTituloPendientes">
                <div class="inicio-cabecera">
                    <h2 class="inicio-titulo" id="inicioTituloPendientes">Pendientes de la clínica</h2>
                    <?php if ($totalPendientes > 0): ?><span class="inicio-meta"><?= (int) $totalPendientes ?> por resolver</span><?php endif; ?>
                </div>
                <?php if ($pendientes): ?>
                    <ul class="inicio-pendientes__lista">
                        <?php foreach ($pendientes as $p): ?>
                            <li class="inicio-pend inicio-pend--<?= $e($p['clave']) ?>">
                                <span class="inicio-pend__num"><?= (int) $p['n'] ?></span>
                                <span class="inicio-pend__texto"><strong><?= $e($p['texto']) ?></strong> <span class="inicio-meta">· <?= $e($p['detalle']) ?></span></span>
                                <a class="inicio-accion" href="<?= $e($p['href']) ?>" aria-label="<?= $e($p['etiqueta']) ?>"><?= $e($p['accion']) ?></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="inicio-aldia"><i class="fas fa-check" aria-hidden="true"></i> Todo al día · la clínica no tiene nada pendiente</p>
                <?php endif; ?>
            </section>

            <!-- Este mes (RE-6.2.1, RE-6.2.4, RE-6.2.12, RE-6.4.1) -->
            <section class="inicio-tile inicio-mes" aria-labelledby="inicioTituloMes">
                <div class="inicio-cabecera">
                    <h2 class="inicio-titulo" id="inicioTituloMes">Este mes</h2>
                    <span class="inicio-meta"><?= $e($panel['mes_actual']) ?></span>
                </div>
                <div class="inicio-mes__resumen">
                    <div class="inicio-mes__consultas">
                        <p class="inicio-mes__cifra"><?= (int) $panel['consultas_mes'] ?> <span><?= $plural((int) $panel['consultas_mes'], 'consulta', 'consultas') ?></span></p>
                        <?php if ($variacion === null): ?>
                            <p class="inicio-mes__variacion">Sin datos de <?= $e($panel['mes_anterior']) ?> para comparar</p>
                        <?php else: ?>
                            <p class="inicio-mes__variacion inicio-mes__variacion--<?= $variacion > 0 ? 'sube' : ($variacion < 0 ? 'baja' : 'igual') ?>">
                                <i class="fas <?= $variacion > 0 ? 'fa-arrow-up' : ($variacion < 0 ? 'fa-arrow-down' : 'fa-equals') ?>" aria-hidden="true"></i>
                                <?php if ($variacion === 0): ?>
                                    Igual que el mismo tramo de <?= $e($panel['mes_anterior']) ?>
                                <?php else: ?>
                                    <span class="visualmente-oculto"><?= $variacion > 0 ? 'Sube' : 'Baja' ?></span>
                                    <?= abs($variacion) ?> % frente al mismo tramo de <?= $e($panel['mes_anterior']) ?>
                                <?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="inicio-grafica" aria-hidden="true">
                        <?php foreach ($panel['tendencia'] as $i => $m): ?>
                            <span class="inicio-grafica__mes<?= $i === $ultimoMes ? ' inicio-grafica__mes--actual' : '' ?>" title="<?= $e($m['etiqueta'] . ': ' . (int) $m['atendidas'] . ' atendidas, ' . (int) $m['no_asistidas'] . ' no asistieron') ?>">
                                <span class="inicio-grafica__barra inicio-grafica__barra--no-asistio inicio-alto--<?= ResumenPanel::escala5(ResumenPanel::porcentaje((int) $m['no_asistidas'], $maximoSerie)) ?>"></span>
                                <span class="inicio-grafica__barra inicio-alto--<?= ResumenPanel::escala5(ResumenPanel::porcentaje((int) $m['atendidas'], $maximoSerie)) ?>"></span>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- Una tabla no respeta el ancho de 1 px: la oculta un div, para no ensanchar la tarjeta. -->
                <div class="visualmente-oculto"><table>
                    <caption>Citas atendidas y no asistidas por mes, últimos 6 meses</caption>
                    <thead><tr><th scope="col">Mes</th><th scope="col">Atendidas</th><th scope="col">No asistieron</th></tr></thead>
                    <tbody>
                        <?php foreach ($panel['tendencia'] as $m): ?>
                            <tr><th scope="row"><?= $e($m['etiqueta']) ?></th><td><?= (int) $m['atendidas'] ?></td><td><?= (int) $m['no_asistidas'] ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <ul class="inicio-mes__datos">
                    <li class="inicio-dato"><strong><?= (int) $panel['pacientes_activos'] ?></strong><span class="inicio-meta">pacientes activos</span></li>
                    <li class="inicio-dato"><strong><?= (int) $panel['propietarios_nuevos'] ?></strong><span class="inicio-meta"><?= $plural((int) $panel['propietarios_nuevos'], 'propietario nuevo', 'propietarios nuevos') ?> · <?= (int) $panel['propietarios'] ?> en total</span></li>
                    <li class="inicio-dato"><strong><?= (int) $panel['no_asistencia']['porcentaje'] ?> %</strong><span class="inicio-meta">no asistieron</span></li>
                </ul>
            </section>
        </div>
    </div>
</div>
