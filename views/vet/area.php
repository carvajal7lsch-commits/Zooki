<?php
/**
 * Inicio del veterinario en la clínica activa (HU-6.1, RE-6.1.1 a RE-6.1.12).
 * Diseño aprobado por el dueño: specs/referencias/inicio-veterinario.dc.html.
 * Los datos llegan de PanelController::datosVeterinario() en $panel; el día de
 * hoy y el mes en curso vienen pintados del servidor, sin esperar al AJAX.
 */
require_once __DIR__ . '/../../helpers/ResumenPanel.php';

if (!isset($panel)) {
    require_once __DIR__ . '/../../controllers/PanelController.php';
    $panel = (new PanelController())->datosVeterinario((int) Contexto::idUsuario());
}

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fichaUrl = fn($c) => 'index.php?action=vet_pacientes&propietario=' . (int) $c['id_propietario'] . '&mascota=' . (int) $c['id_mascota'];
$atencionUrl = fn($c) => 'index.php?action=vet_atencion&id_cita=' . (int) $c['id_cita'];
$cont = $panel['contadores'];
$sig = $panel['siguiente'];
$plural = fn(int $n, string $uno, string $varios) => $n === 1 ? $uno : $varios;
$niveles = ['rojo' => 'Rojo', 'naranja' => 'Naranja', 'amarillo' => 'Amarillo', 'verde' => 'Verde'];

/** Agrupa los pendientes del mismo nombre sin repetir «Mia y Mia». */
$nombres = function (array $lista) {
    $lista = array_values(array_filter(array_map('strval', $lista), fn($n) => $n !== ''));
    $conteos = array_count_values($lista);
    $lista = [];
    foreach ($conteos as $nombre => $cantidad) {
        $lista[] = (string) $nombre . ($cantidad > 1 ? ' (' . $cantidad . ')' : '');
    }
    if (count($lista) <= 2) {
        return implode(' y ', $lista);
    }
    return $lista[0] . ', ' . $lista[1] . ' y ' . (count($lista) - 2) . ' más';
};

/** Acción de RE-6.1.5 sobre la cita: iniciar, esperar la ventana o continuar. */
$botonAccion = function (array $c) use ($e, $atencionUrl) {
    $accion = $c['accion'] ?? ['tipo' => null];
    switch ($accion['tipo']) {
        case 'continuar':
            $texto = $c['estado'] === 'sin_cerrar' ? 'Resolver' : 'Continuar atención';
            return '<a class="inicio-boton inicio-boton--blanco" href="' . $e($atencionUrl($c)) . '">' . $texto . '</a>';
        case 'iniciar':
            return '<button type="button" class="inicio-boton inicio-boton--blanco" data-iniciar="' . (int) $c['id_cita'] . '">Iniciar atención</button>';
        case 'esperar':
            return '<button type="button" class="inicio-boton inicio-boton--blanco" data-iniciar="' . (int) $c['id_cita'] . '" data-desde="' . $e($accion['desde_iso']) . '" disabled title="Se habilita 15 minutos antes de la cita">'
                . 'Desde las ' . $e(ResumenPanel::hora($accion['desde'])) . '</button>';
        default:
            return '';
    }
};

// RE-6.1.10: una fila por tipo de pendiente, solo los que tienen algo.
$pendientes = [];
if ($panel['pendientes']) {
    $primero = $panel['pendientes'][0];
    $n = count($panel['pendientes']);
    $pendientes[] = ['clave' => 'cerrar', 'n' => $n, 'texto' => 'Sin cerrar',
        'detalle' => $primero['mascota'] . ', ' . mb_strtolower($primero['dia']) . ' ' . ResumenPanel::hora($primero['hora']) . ($n > 1 ? ' y ' . ($n - 1) . ' más' : ''),
        'accion' => 'Cerrar', 'etiqueta' => 'Cerrar la atención de ' . $primero['mascota'], 'href' => $atencionUrl($primero)];
}
if ($panel['triages']) {
    $n = count($panel['triages']);
    $pendientes[] = ['clave' => 'triage', 'n' => $n, 'texto' => $plural($n, 'Triage por revisar', 'Triages por revisar'),
        'detalle' => $nombres(array_column($panel['triages'], 'nombre')),
        'accion' => 'Revisar', 'etiqueta' => 'Revisar triages en la agenda', 'href' => 'index.php?action=vet_agenda#triage'];
}
if ($panel['reasignaciones']) {
    $primera = $panel['reasignaciones'][0];
    $n = count($panel['reasignaciones']);
    $horaPropuesta = $primera['inicio_propuesto'] ? ' a las ' . ResumenPanel::hora(substr((string) $primera['inicio_propuesto'], 11, 5)) : '';
    $pendientes[] = ['clave' => 'reasignacion', 'n' => $n, 'texto' => $plural($n, 'Reasignación', 'Reasignaciones'),
        'detalle' => $primera['mascota'] . $horaPropuesta . ($n > 1 ? ' y ' . ($n - 1) . ' más' : ''),
        'accion' => 'Responder', 'etiqueta' => 'Responder reasignaciones en la agenda', 'href' => 'index.php?action=vet_agenda#reasignaciones'];
}
if ($panel['total_recordatorios'] > 0) {
    $detalle = [];
    foreach ($panel['recordatorios'] as $grupo) {
        foreach ($grupo['items'] as $r) {
            $detalle[] = $r['mascota'] . ($grupo['etiqueta'] === 'Hoy' || $grupo['etiqueta'] === 'Mañana' ? ' ' . mb_strtolower($grupo['etiqueta']) : '');
        }
    }
    $pendientes[] = ['clave' => 'dosis', 'n' => $panel['total_recordatorios'], 'texto' => 'Próximas dosis',
        'detalle' => $nombres(array_unique($detalle)),
        'accion' => 'Ver', 'etiqueta' => 'Ver vacunas y desparasitaciones próximas', 'dialogo' => 'inicioRecordatorios'];
}
$totalPendientes = array_sum(array_column($pendientes, 'n'));

// Encabezado de la tarjeta del siguiente paciente.
if ($sig) {
    $enAtencion = in_array($sig['estado'], ['en_curso', 'pausada'], true);
    $sobreSiguiente = $enAtencion
        ? 'En atención · desde las ' . ResumenPanel::hora($sig['hora'])
        : 'Siguiente · ' . ResumenPanel::hora($sig['hora']) . ' · ' . $sig['cuenta'];
    if ($enAtencion) {
        $llegada = 'en atención';
    } elseif (!empty($sig['hora_llegada'])) {
        $llegada = 'llegó a las ' . ResumenPanel::hora(substr((string) $sig['hora_llegada'], 11, 5));
    } else {
        $llegada = 'aún no llega';
    }
    $datosSiguiente = array_filter([$sig['especie'] ?? '', $sig['edad'] ?? '', $sig['propietario'] ? 'de ' . $sig['propietario'] : '', $llegada]);
    $alergias = array_map(fn($a) => ($a['tipo'] === 'alergia' ? 'alergia: ' : '') . $a['descripcion'], array_slice($sig['alertas'], 0, 2));
    $puedeLlegar = in_array($sig['estado'], ['pendiente', 'confirmada'], true) && empty($sig['hora_llegada']);
}
$segmentos = array_merge(
    array_fill(0, $cont['atendidas'], 'atendida'),
    array_fill(0, $cont['sin_cerrar'], 'sin-cerrar'),
    array_fill(0, $cont['por_atender'], 'pendiente'),
    array_fill(0, $cont['no_asistio'], 'no-asistio')
);
$colorAvatar = fn(array $r) => 'inicio-ava--' . ((int) $r['id_mascota'] % 5);
$iniciales = fn(string $nombre) => mb_strtoupper(mb_substr(trim($nombre), 0, 2));
?>
<div class="inicio" data-inicio data-hoy="<?= $e($panel['hoy']) ?>" data-clinica="<?= (int) Contexto::clinicaActiva() ?>">

    <div class="inicio__arriba">
        <!-- Siguiente paciente (RE-6.1.5) -->
        <section class="inicio-tile inicio-siguiente" aria-labelledby="inicioTituloSiguiente">
            <span class="inicio-siguiente__circulo" aria-hidden="true"></span>
            <span class="inicio-siguiente__cuarto" aria-hidden="true"></span>
            <img class="inicio-siguiente__mascota" src="img/gato-gris.png" alt="" decoding="async">
            <div class="inicio-siguiente__contenido">
                <?php if ($sig): ?>
                    <p class="inicio-siguiente__sobre" id="inicioTituloSiguiente"><?= $e($sobreSiguiente) ?></p>
                    <div class="inicio-siguiente__nombre">
                        <h2><?= $e($sig['mascota']) ?></h2>
                        <?php if (isset($niveles[$sig['prioridad'] ?? ''])): ?>
                            <span class="inicio-nivel inicio-nivel--<?= $e($sig['prioridad']) ?>"><span class="visualmente-oculto">Nivel </span><?= $e($niveles[$sig['prioridad']]) ?></span>
                        <?php endif; ?>
                    </div>
                    <p class="inicio-siguiente__datos"><?= $e(implode(' · ', $datosSiguiente)) ?></p>
                    <?php if (trim((string) $sig['motivo']) !== '' || $alergias): ?>
                        <p class="inicio-siguiente__motivo"><?= $e(trim((string) $sig['motivo'])) ?><?php if (trim((string) $sig['motivo']) !== '' && $alergias): ?> · <?php endif; ?><?php if ($alergias): ?><strong><?= $e(implode(' · ', $alergias)) ?></strong><?php endif; ?></p>
                    <?php endif; ?>
                    <div class="inicio-siguiente__acciones">
                        <?php if ($puedeLlegar): ?>
                            <button type="button" class="inicio-boton inicio-boton--blanco" data-inicio-llegada="<?= (int) $sig['id_cita'] ?>">Registrar llegada</button>
                        <?php else: ?>
                            <?= $botonAccion($sig) ?>
                        <?php endif; ?>
                        <a class="inicio-boton inicio-boton--vidrio" href="<?= $e($fichaUrl($sig)) ?>">Ver ficha</a>
                    </div>
                <?php else: ?>
                    <p class="inicio-siguiente__sobre" id="inicioTituloSiguiente">Siguiente paciente</p>
                    <div class="inicio-siguiente__nombre"><h2><?= $cont['citas'] > 0 ? 'Todo atendido' : 'Sin citas hoy' ?></h2></div>
                    <p class="inicio-siguiente__datos"><?= $cont['citas'] > 0 ? 'No te quedan pacientes por atender hoy.' : 'No tienes citas agendadas para hoy.' ?></p>
                    <div class="inicio-siguiente__acciones">
                        <a class="inicio-boton inicio-boton--blanco" href="index.php?action=vet_agenda">Ver agenda</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Calendario del mes (RE-6.1.8) -->
        <section class="inicio-tile inicio-cal" aria-labelledby="inicioTituloCal" data-inicio-calendario data-elegido="<?= $e($panel['hoy']) ?>">
            <div class="inicio-cal__contenido" data-inicio-calendario-contenido>
                <?php $mes = $panel['mes']; require __DIR__ . '/partials/inicio_calendario.php'; ?>
            </div>
        </section>

        <div class="inicio-lado">
            <!-- HU-2.2: la atención sin cita se registra como llegada en la agenda -->
            <a class="inicio-tile inicio-sincita" href="index.php?action=vet_agenda#llegadas">
                <span class="inicio-sincita__cuarto" aria-hidden="true"></span>
                <img class="inicio-sincita__mascota" src="img/cachorro-negro.png" alt="" decoding="async">
                <span class="inicio-sincita__icono" aria-hidden="true"><i class="fas fa-plus"></i></span>
                <strong class="inicio-sincita__texto">Atención sin cita</strong>
            </a>
            <!-- RE-6.1.10: cómo va el día -->
            <section class="inicio-tile inicio-hoy" aria-labelledby="inicioTituloHoy">
                <div class="inicio-cabecera">
                    <h2 class="inicio-titulo" id="inicioTituloHoy">Hoy</h2>
                    <p class="inicio-hoy__total"><?= (int) $cont['citas'] ?> <span><?= $plural((int) $cont['citas'], 'cita', 'citas') ?></span></p>
                </div>
                <div class="inicio-hoy__barra" aria-hidden="true">
                    <?php foreach ($segmentos ?: ['vacio'] as $segmento): ?><span class="inicio-hoy__segmento inicio-hoy__segmento--<?= $segmento ?>"></span><?php endforeach; ?>
                </div>
                <p class="inicio-meta"><strong class="inicio-tinta--verde"><?= (int) $cont['atendidas'] ?></strong> <?= $plural((int) $cont['atendidas'], 'atendida', 'atendidas') ?> · <strong class="inicio-tinta--rojo"><?= (int) $cont['sin_cerrar'] ?></strong> sin cerrar · <strong class="inicio-tinta--texto"><?= (int) $cont['por_atender'] ?></strong> <?= $plural((int) $cont['por_atender'], 'pendiente', 'pendientes') ?></p>
            </section>
        </div>
    </div>

    <div class="inicio__abajo">
        <!-- Tu día (RE-6.1.9) -->
        <section class="inicio-tile inicio-dia" aria-labelledby="inicioTituloDia" data-inicio-dia aria-busy="false">
            <div class="inicio-dia__contenido" data-inicio-dia-contenido>
                <?php $dia = $panel['dia']; require __DIR__ . '/partials/inicio_dia.php'; ?>
            </div>
        </section>

        <div class="inicio-col">
            <!-- Pendientes (RE-6.1.6, RE-6.1.10) -->
            <section class="inicio-tile inicio-pendientes" aria-labelledby="inicioTituloPendientes">
                <div class="inicio-cabecera">
                    <h2 class="inicio-titulo" id="inicioTituloPendientes">Pendientes</h2>
                    <?php if ($totalPendientes > 0): ?><span class="inicio-meta"><?= (int) $totalPendientes ?> por resolver</span><?php endif; ?>
                </div>
                <?php if ($pendientes): ?>
                    <ul class="inicio-pendientes__lista">
                        <?php foreach ($pendientes as $p): ?>
                            <li class="inicio-pend inicio-pend--<?= $e($p['clave']) ?>">
                                <span class="inicio-pend__num"><?= (int) $p['n'] ?></span>
                                <span class="inicio-pend__texto"><strong><?= $e($p['texto']) ?></strong><?php if ($p['detalle'] !== ''): ?> <span class="inicio-meta">· <?= $e($p['detalle']) ?></span><?php endif; ?></span>
                                <?php if (isset($p['dialogo'])): ?>
                                    <button type="button" class="inicio-accion" data-inicio-abrir="<?= $e($p['dialogo']) ?>" aria-label="<?= $e($p['etiqueta']) ?>"><?= $e($p['accion']) ?></button>
                                <?php else: ?>
                                    <a class="inicio-accion" href="<?= $e($p['href']) ?>" aria-label="<?= $e($p['etiqueta']) ?>"><?= $e($p['accion']) ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="inicio-aldia"><i class="fas fa-check" aria-hidden="true"></i> Todo al día · no tienes nada pendiente</p>
                <?php endif; ?>
            </section>

            <!-- Pacientes recientes (RE-6.1.11) -->
            <section class="inicio-tile inicio-recientes" aria-labelledby="inicioTituloRecientes">
                <div class="inicio-cabecera">
                    <h2 class="inicio-titulo" id="inicioTituloRecientes">Pacientes recientes</h2>
                    <a class="inicio-enlace" href="index.php?action=vet_pacientes">Todos</a>
                </div>
                <?php if (!$panel['recientes']): ?>
                    <div class="inicio-recientes__vacio">
                        <img src="img/golden-retriever.png" alt="" loading="lazy" decoding="async">
                        <div><p class="inicio-recientes__titulo">Aún no has atendido pacientes</p><p class="inicio-meta">Cuando atiendas en esta clínica, los verás aquí.</p></div>
                    </div>
                <?php else: ?>
                    <ul class="inicio-recientes__lista">
                        <?php foreach ($panel['recientes'] as $r): ?>
                            <li class="inicio-rec">
                                <?php if (!empty($r['url_foto'])): ?>
                                    <span class="inicio-ava inicio-ava--foto" data-iniciales="<?= $e($iniciales($r['mascota'])) ?>" data-color="<?= $e($colorAvatar($r)) ?>"><img src="uploads/mascotas/<?= $e(rawurlencode((string) $r['url_foto'])) ?>" alt="" loading="lazy" data-inicio-foto></span>
                                <?php else: ?>
                                    <span class="inicio-ava <?= $e($colorAvatar($r)) ?>" aria-hidden="true"><?= $e($iniciales($r['mascota'])) ?></span>
                                <?php endif; ?>
                                <span class="inicio-rec__texto">
                                    <strong><?= $e($r['mascota']) ?></strong>
                                    <span class="inicio-meta"><?= $e(implode(' · ', array_filter([$r['cuando'], mb_strtolower((string) $r['motivo']), $r['propietario'] ? 'de ' . $r['propietario'] : '']))) ?></span>
                                </span>
                                <a class="inicio-accion inicio-accion--sec" href="<?= $e($fichaUrl($r)) ?>" aria-label="Ver ficha de <?= $e($r['mascota']) ?>">Ver ficha</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <!-- RE-6.1.1: vacunas y desparasitaciones próximas de sus pacientes -->
    <dialog class="inicio-dialogo" id="inicioRecordatorios" aria-labelledby="inicioTituloRecordatorios" data-inicio-dialogo>
        <div class="inicio-cabecera">
            <h2 class="inicio-titulo" id="inicioTituloRecordatorios">Vacunas y desparasitaciones</h2>
            <button type="button" class="inicio-dialogo__cerrar" data-inicio-cerrar aria-label="Cerrar"><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <p class="inicio-meta">Próximos 7 días de tus pacientes en esta clínica.</p>
        <?php foreach ($panel['recordatorios'] as $grupo): ?>
            <h3 class="inicio-dialogo__dia"><?= $e($grupo['etiqueta']) ?></h3>
            <ul class="inicio-dialogo__lista">
                <?php foreach ($grupo['items'] as $r): ?>
                    <li class="inicio-rec">
                        <span class="inicio-ava <?= $e($colorAvatar($r)) ?>" aria-hidden="true"><i class="fas <?= $r['tipo'] === 'vacuna' ? 'fa-syringe' : 'fa-shield-alt' ?>"></i></span>
                        <span class="inicio-rec__texto">
                            <strong><?= $e($r['mascota']) ?></strong>
                            <span class="inicio-meta"><?= $r['tipo'] === 'vacuna' ? 'Vacuna' : 'Desparasitación' ?>: <?= $e($r['nombre']) ?></span>
                        </span>
                        <a class="inicio-accion inicio-accion--sec" href="<?= $e($fichaUrl($r)) ?>" aria-label="Ver ficha de <?= $e($r['mascota']) ?>">Ver ficha</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </dialog>
</div>
