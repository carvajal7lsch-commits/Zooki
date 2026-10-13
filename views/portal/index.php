<?php
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/ResumenPanel.php';
require_once __DIR__ . '/../../helpers/ReglaAtencion.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/PresentacionPortal.php';
require_once __DIR__ . '/../../helpers/PresentacionPlataforma.php';
require_once __DIR__ . '/../../helpers/ValidadorMascota.php';

/**
 * M5 C2 — Portal del propietario: Inicio · Citas · Clínicas · Perfil (Anexo A
 * §4 y decisión 4 de «A — Revisión»). Recibe de PortalController::index()
 * $mascotas, $agenda, $proxima_cita, $todas_citas, $todas_vacunas,
 * $todas_desparasitaciones, $clinicas_portal, $clinicas_disponibles,
 * $usuarioData, $primer_nombre y $catalogo_especies.
 */
$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$hoy = new DateTimeImmutable('now', new DateTimeZone(ReglaAtencion::ZONA));
$mascotaPorId = array_column($mascotas, null, 'id_mascota');

/** Avatar de la mascota: su foto o sus iniciales, como las tarjetas de Usuarios. */
$avatarMascota = static function (?string $foto, string $nombre, string $extra = '') use ($e): void {
    if ($foto): ?>
        <span class="avatar-mini <?= $extra ?>"><img src="<?= $e($foto) ?>" alt="" loading="lazy"></span>
    <?php else: ?>
        <span class="avatar-mini avatar-iniciales <?= $extra ?>" aria-hidden="true"><?= $e(PresentacionPlataforma::iniciales($nombre)) ?></span>
    <?php endif;
};

/** Tarjeta de una cita: la misma en Inicio, Próximas y Pasadas. */
$tarjetaCita = static function (array $cita) use ($e, $hoy, $avatarMascota): void { ?>
    <article class="person-card portal-cita" role="button" tabindex="0" data-ui-accion="detalle-cita-portal" data-ui-id="<?= (int) $cita['id_cita'] ?>" data-pet-id="<?= (int) $cita['id_mascota'] ?>">
        <div class="card-header-mini">
            <?php $avatarMascota($cita['foto_mascota'] ?? null, (string) $cita['nombre_mascota']); ?>
            <span class="<?= PresentacionPortal::claseEstado($cita['estado']) ?>"><?= $e(PresentacionPortal::estado($cita['estado'])) ?></span>
        </div>
        <div class="card-body-mini">
            <h3 class="card-title-mini"><?= $e($cita['nombre_mascota']) ?></h3>
            <p class="portal-cita__cuando"><?= $e(ResumenPanel::etiquetaDia($cita['fecha'], $hoy)) ?> · <?= $e(ResumenPanel::hora($cita['hora'])) ?></p>
            <div class="card-tags-mini">
                <span class="tag-mini"><i class="ri-hospital-line" aria-hidden="true"></i> <?= $e($cita['clinica_nombre']) ?></span>
                <?php if (!empty($cita['nombre_tipo'])): ?><span class="tag-mini"><?= $e($cita['nombre_tipo']) ?></span><?php endif; ?>
            </div>
            <span class="contact-text-mini"><i class="ri-stethoscope-line" aria-hidden="true"></i> <?= $e($cita['veterinario_nombre'] ?? 'Veterinario') ?></span>
        </div>
        <?php // RN-405: solo se cancela en una clínica con vínculo activo; es lo único que confirma. ?>
        <?php if (!empty($cita['cancelable'])): ?>
            <div class="card-footer-mini">
                <button type="button" class="portal-boton-secundario" data-ui-accion="cancelar-cita-portal" data-ui-id="<?= (int) $cita['id_cita'] ?>">
                    <i class="ri-close-circle-line" aria-hidden="true"></i> Cancelar cita
                </button>
            </div>
        <?php endif; ?>
    </article>
<?php };

/** Estado vacío compacto: icono a la izquierda y dos líneas (M0.2.1). */
$vacio = static function (string $icono, string $titulo, string $texto, string $extra = '') use ($e): void { ?>
    <div class="portal-vacio">
        <span class="portal-vacio__icono" aria-hidden="true"><i class="<?= $e($icono) ?>"></i></span>
        <div>
            <h3><?= $e($titulo) ?></h3>
            <p><?= $e($texto) ?></p>
            <?= $extra ?>
        </div>
    </div>
<?php };
?>

<!-- ══ INICIO ═══════════════════════════════════════════════════════ -->
<section id="screen-home" class="app-screen active" aria-labelledby="homeSaludo">
    <header class="portal-cabecera">
        <div>
            <p class="portal-cabecera__ayuda">Hola,</p>
            <h1 id="homeSaludo" class="portal-cabecera__titulo"><?= $e($primer_nombre) ?></h1>
        </div>
    </header>

    <div class="home-grid">
        <div class="home-grid__principal">
            <section aria-labelledby="tituloProxima">
                <div class="section-title-row">
                    <h2 id="tituloProxima">Próxima cita</h2>
                    <?php if ($proxima_cita): ?>
                        <button type="button" class="btn-enlace" data-nav="citas">Ver todas</button>
                    <?php endif; ?>
                </div>
                <?php if ($proxima_cita): ?>
                    <?php $tarjetaCita($proxima_cita); ?>
                <?php else: ?>
                    <?php $vacio('ri-calendar-line', 'Sin citas próximas', 'Cuando agendes una cita, aparecerá aquí.', '<button type="button" class="btn-enlace" data-agendar>Agendar una cita</button>'); ?>
                <?php endif; ?>
            </section>

            <section aria-labelledby="tituloMascotas">
                <div class="section-title-row">
                    <h2 id="tituloMascotas">Mis mascotas</h2>
                    <button id="btnOpenAddPetModal" type="button" class="action-btn-mini" aria-label="Registrar una mascota" title="Registrar una mascota">
                        <i class="ri-add-line" aria-hidden="true"></i>
                    </button>
                </div>

                <?php if (empty($mascotas)): ?>
                    <?php $vacio('ri-heart-3-line', 'Todavía no tienes mascotas', 'Registra a tu compañero con el botón +.'); ?>
                <?php else: ?>
                    <div class="portal-tarjetas">
                        <?php foreach ($mascotas as $m): ?>
                            <article class="person-card portal-mascota" role="button" tabindex="0" data-ui-accion="detalle-mascota" data-ui-id="<?= (int) $m['id_mascota'] ?>">
                                <div class="card-header-mini">
                                    <?php $avatarMascota($m['foto'], (string) $m['nombre']); ?>
                                </div>
                                <div class="card-body-mini">
                                    <h3 class="card-title-mini"><?= $e($m['nombre']) ?></h3>
                                    <div class="card-tags-mini">
                                        <span class="tag-mini"><?= $e(PresentacionPortal::especieYRaza($m)) ?></span>
                                        <?php // RN-110: las clínicas donde se atiende; sin ninguna, se vincula al agendar. ?>
                                        <?php foreach ($m['clinicas'] as $clinica): ?>
                                            <span class="tag-mini tag-mini--azul"><i class="ri-hospital-line" aria-hidden="true"></i> <?= $e($clinica['nombre']) ?></span>
                                        <?php endforeach; ?>
                                        <?php if (!$m['clinicas']): ?>
                                            <span class="tag-mini">Sin clínica todavía</span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="contact-text-mini">
                                        <i class="ri-syringe-line" aria-hidden="true"></i>
                                        <?php if ($m['proxima_dosis']): ?>
                                            Próxima dosis: <?= $e($m['proxima_dosis']['nombre']) ?> · <?= $e(PresentacionPlataforma::fecha($m['proxima_dosis']['fecha'])) ?>
                                        <?php else: ?>
                                            Sin dosis pendientes
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>

        <div class="home-grid__lateral">
            <!-- RE-5.1.7 (decisión C6): el horario real de cada clínica con vínculo activo, en compacto. -->
            <section class="portal-horario" aria-labelledby="tituloHorario">
                <div class="section-title-row">
                    <h2 id="tituloHorario">Horario de hoy</h2>
                </div>
                <?php if (empty($clinicas_portal)): ?>
                    <?php $vacio('ri-hospital-line', 'Aún no tienes clínicas', 'Vincúlate a una para agendar citas.', '<button type="button" class="btn-enlace" data-nav="clinicas">Ver clínicas</button>'); ?>
                <?php endif; ?>
                <?php foreach ($clinicas_portal as $clinica): $abierta = (bool) $clinica['ahora']['abierta']; ?>
                    <article class="portal-horario__clinica" data-abierta="<?= $abierta ? '1' : '0' ?>">
                        <div class="portal-horario__fila">
                            <h3><?= $e($clinica['nombre']) ?></h3>
                            <span class="tag-mini<?= $abierta ? ' tag-mini--azul' : '' ?>"><?= $abierta ? 'Abierta' : 'Cerrada' ?></span>
                        </div>
                        <p class="portal-horario__detalle"><?= $e($clinica['ahora']['texto']) ?> · <?= $e($clinica['ahora']['detalle']) ?></p>
                        <details class="portal-horario__semana">
                            <summary>Horario de la semana <i class="ri-arrow-down-s-line" aria-hidden="true"></i></summary>
                            <dl>
                                <?php foreach ($clinica['semana'] as $n => $dia): ?>
                                    <div class="portal-horario__dia<?= $n === $clinica['ahora']['hoy'] ? ' is-hoy' : '' ?><?= !$dia['franjas'] ? ' is-cerrado' : '' ?>">
                                        <dt><?= $e($dia['dia']) ?></dt>
                                        <dd><?= $e(HorarioAtencion::texto($dia['franjas'])) ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </details>
                    </article>
                <?php endforeach; ?>
            </section>
        </div>
    </div>
</section>

<!-- ══ CITAS ════════════════════════════════════════════════════════ -->
<section id="screen-citas" class="app-screen" aria-labelledby="tituloCitas">
    <header class="portal-cabecera">
        <div>
            <h1 id="tituloCitas" class="portal-cabecera__titulo">Citas</h1>
            <p class="portal-cabecera__ayuda">Las de todas tus mascotas, en todas tus clínicas.</p>
        </div>
    </header>

    <?php // M5 C2b: contenedor estable; portal.js lo repinta tras una acción. ?>
    <div id="filtroMascotasCitas">
        <?php if (count($mascotas) > 1): ?>
            <div class="pet-filter-chips-wrapper" role="group" aria-label="Filtrar por mascota">
                <button type="button" class="pet-chip active" data-pet-id="all" aria-pressed="true"><span>Todas</span></button>
                <?php foreach ($mascotas as $m): ?>
                    <button type="button" class="pet-chip" data-pet-id="<?= (int) $m['id_mascota'] ?>" aria-pressed="false"><span><?= $e($m['nombre']) ?></span></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="tabs-wrapper portal-pestanas" role="tablist" aria-label="Citas" data-pestanas="citas">
        <button type="button" class="tab-btn active" role="tab" aria-selected="true" aria-controls="citas-proximas" data-pestana="citas-proximas">
            <i class="ri-calendar-event-line" aria-hidden="true"></i> Próximas <span class="tag-mini" id="contadorProximas"><?= count($agenda['proximas']) ?></span>
        </button>
        <button type="button" class="tab-btn" role="tab" aria-selected="false" aria-controls="citas-pasadas" data-pestana="citas-pasadas">
            <i class="ri-history-line" aria-hidden="true"></i> Pasadas
        </button>
        <button type="button" class="tab-btn" role="tab" aria-selected="false" aria-controls="agenda-salud" data-pestana="agenda-salud">
            <i class="ri-heart-line" aria-hidden="true"></i> Calendario de salud
        </button>
    </div>

    <div id="citas-proximas" class="portal-panel" role="tabpanel" data-panel-citas>
        <?php if (empty($agenda['proximas'])): ?>
            <?php $vacio('ri-calendar-line', 'Sin citas próximas', 'Agenda una cita con el botón Agendar.'); ?>
        <?php else: ?>
            <div class="portal-tarjetas">
                <?php foreach ($agenda['proximas'] as $cita) $tarjetaCita($cita); ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="citas-pasadas" class="portal-panel" role="tabpanel" data-panel-citas hidden>
        <?php if (empty($agenda['pasadas'])): ?>
            <?php $vacio('ri-history-line', 'Sin citas pasadas', 'Aquí quedarán las citas atendidas y las que no se cumplieron.'); ?>
        <?php else: ?>
            <div class="portal-tarjetas">
                <?php foreach ($agenda['pasadas'] as $cita) $tarjetaCita($cita); ?>
            </div>
        <?php endif; ?>
    </div>

    <div id="agenda-salud" class="portal-panel" role="tabpanel" hidden>
        <div class="agenda-card">
            <div class="mini-calendar-wrapper">
                <div class="mini-calendar-header">
                    <button type="button" class="btn-cal-prev" id="btnPrevMonth" aria-label="Mes anterior"><i class="ri-arrow-left-s-line" aria-hidden="true"></i></button>
                    <button type="button" id="calMonthTitle" class="mini-calendar-title" aria-label="Elegir mes y año">—</button>
                    <button type="button" class="btn-cal-next" id="btnNextMonth" aria-label="Mes siguiente"><i class="ri-arrow-right-s-line" aria-hidden="true"></i></button>
                </div>
                <div class="mini-calendar-days" aria-hidden="true">
                    <span>D</span><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span>
                </div>
                <div id="miniCalendarGrid" class="mini-calendar-grid"></div>

                <!-- Selector de mes y año -->
                <div id="calWin11Nav" class="fp-win11-nav mini-calendar-nav">
                    <div class="win11-header">
                        <button type="button" id="calWin11Title" class="win11-title mini-calendar-title">—</button>
                    </div>
                    <div class="win11-content">
                        <div id="calWin11MonthsGrid" class="win11-grid mini-calendar-nav__grid"></div>
                        <div id="calWin11YearsGrid" class="win11-grid win11-years mini-calendar-nav__grid"></div>
                    </div>
                </div>

                <div class="mini-calendar-legend">
                    <span><span class="cal-dot cal-dot--cita"></span> Citas</span>
                    <span><span class="cal-dot cal-dot--vacuna"></span> Vacunas</span>
                    <span><span class="cal-dot cal-dot--control"></span> Controles</span>
                </div>

                <div id="calendarFilterAlert" class="calendar-filter-alert">
                    <span>Filtrado por fecha seleccionada</span>
                    <button type="button" id="btnResetDateFilter" class="btn-enlace">Ver todos</button>
                </div>
            </div>

            <div class="agenda-salud-listas">
                <div id="agenda-salud-proximas" class="agenda-salud-bloque">
                    <h3 class="agenda-salud-titulo agenda-salud-titulo--proximas">Próximas dosis</h3>
                    <div class="agenda-list" id="saludProximasList"></div>
                </div>

                <div id="agenda-salud-historial" class="agenda-salud-bloque">
                    <h3 class="agenda-salud-titulo">Aplicadas</h3>
                    <div class="agenda-list" id="saludHistorialList"></div>
                </div>

                <a href="#" id="btnImprimirHistorial" class="btn-export-pdf" hidden>
                    <i class="ri-printer-line" aria-hidden="true"></i> <span>Imprimir la ficha de salud</span>
                </a>
            </div>
        </div>
    </div>
</section>

<?php
// Eventos del calendario de salud para portal.js, en data-* (sin JS en línea).
$eventos_js = [];
foreach ((array) $todas_citas as $c) {
    $eventos_js[] = [
        'tipo' => 'cita',
        'id_cita' => $c['id_cita'],
        'id_mascota' => $c['id_mascota'],
        'nombre_mascota' => $c['nombre_mascota'],
        'foto_mascota' => $c['foto_mascota'],
        'titulo' => $c['nombre_tipo'] ?? 'Consulta',
        'detalle' => ($c['veterinario_nombre'] ?? 'Veterinario') . ' · ' . $c['clinica_nombre'],
        'fecha' => $c['fecha'],
        'hora' => substr($c['hora'], 0, 5),
        'estado' => $c['estado'],
        'proxima' => null
    ];
}
foreach ((array) $todas_vacunas as $vac) {
    $eventos_js[] = [
        'tipo' => 'vacuna',
        'id_mascota' => $vac['id_mascota'],
        'nombre_mascota' => $vac['nombre_mascota'],
        'foto_mascota' => $vac['foto_mascota'],
        'titulo' => $vac['nombre_vacuna'],
        // La tabla vacunas no tiene «dosis» ni «fecha_proxima» (esas son de
        // desparasitaciones): con esos nombres las vacunas nunca salían en
        // «Próximas dosis» y cada carga dejaba avisos de PHP en el log.
        'detalle' => $vac['clinica_nombre'] . (!empty($vac['laboratorio']) ? ' · Laboratorio: ' . $vac['laboratorio'] : ''),
        'fecha' => $vac['fecha_aplicacion'],
        'hora' => null,
        'estado' => 'aplicada',
        'proxima' => (!empty($vac['fecha_proxima_dosis']) && $vac['fecha_proxima_dosis'] !== '0000-00-00') ? $vac['fecha_proxima_dosis'] : null
    ];
}
foreach ((array) $todas_desparasitaciones as $d) {
    $eventos_js[] = [
        'tipo' => 'control',
        'id_mascota' => $d['id_mascota'],
        'nombre_mascota' => $d['nombre_mascota'],
        'foto_mascota' => $d['foto_mascota'],
        'titulo' => $d['producto'],
        // Tampoco hay «dosis» en desparasitaciones: se muestran el tipo y la periodicidad.
        'detalle' => ucfirst((string) ($d['tipo'] ?? '')) . ' · ' . ($d['periodicidad'] ?? '') . ' · ' . $d['clinica_nombre'],
        'fecha' => $d['fecha_aplicacion'],
        'hora' => null,
        'estado' => 'aplicado',
        'proxima' => ($d['fecha_proxima'] !== '0000-00-00') ? $d['fecha_proxima'] : null
    ];
}
?>
<div id="portalAgendaDatos" hidden data-eventos="<?= htmlspecialchars(json_encode($eventos_js, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"></div>

<!-- ══ CLÍNICAS ═════════════════════════════════════════════════════ -->
<!-- HU-5.12 y HU-5.13: sección propia (decisión 4 de «A — Revisión»), Modelos §11.1. -->
<section id="screen-clinicas" class="app-screen" aria-labelledby="tituloMisClinicas">
    <header class="portal-cabecera">
        <div>
            <h1 id="tituloMisClinicas" class="portal-cabecera__titulo">Clínicas</h1>
            <p class="portal-cabecera__ayuda">Dónde se atienden tus mascotas y quién ve su historia.</p>
        </div>
    </header>

    <div class="mis-clinicas" id="misClinicas">
        <h2 class="portal-subtitulo">Mis clínicas</h2>
        <?php if (empty($clinicas_portal)): ?>
            <?php $vacio('ri-hospital-line', 'Aún no tienes clínicas', 'Vincúlate a una de la lista de abajo para agendar citas.'); ?>
        <?php else: ?>
            <div class="portal-tarjetas">
                <?php foreach ($clinicas_portal as $clinica): $idInterruptor = 'autoriza_' . (int) $clinica['id_clinica']; $abierta = (bool) $clinica['ahora']['abierta']; ?>
                    <article class="person-card portal-clinica" data-id-clinica="<?= (int) $clinica['id_clinica'] ?>">
                        <div class="card-header-mini">
                            <span class="avatar-mini avatar-iniciales" aria-hidden="true"><?= $e(PresentacionPlataforma::iniciales($clinica['nombre'])) ?></span>
                            <span class="tag-mini<?= $abierta ? ' tag-mini--azul' : '' ?>"><?= $abierta ? 'Abierta ahora' : 'Cerrada ahora' ?></span>
                        </div>
                        <div class="card-body-mini">
                            <h3 class="card-title-mini"><?= $e($clinica['nombre']) ?></h3>
                            <?php if (!empty($clinica['direccion'])): ?>
                                <span class="contact-text-mini"><i class="ri-map-pin-line" aria-hidden="true"></i> <?= $e($clinica['direccion']) ?></span>
                            <?php endif; ?>
                            <span class="contact-text-mini"><i class="ri-time-line" aria-hidden="true"></i> <?= $e($clinica['ahora']['texto']) ?></span>
                        </div>
                        <?php // RE-5.12.1/2: aplica de inmediato, sin confirmación (se revoca igual de fácil). ?>
                        <label class="portal-interruptor" for="<?= $idInterruptor ?>">
                            <span class="toggle-switch">
                                <input type="checkbox" id="<?= $idInterruptor ?>" data-autorizar-clinica="<?= (int) $clinica['id_clinica'] ?>" aria-describedby="<?= $idInterruptor ?>_ayuda"<?= (int) $clinica['autoriza_historia_compartida'] === 1 ? ' checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </span>
                            <span class="portal-interruptor__texto">Puede ver la historia de otras clínicas</span>
                        </label>
                        <p class="portal-ayuda" id="<?= $idInterruptor ?>_ayuda">Las consultas, tratamientos y archivos que registraron otras. Las vacunas, desparasitaciones y datos básicos los ve siempre.</p>
                        <div class="card-footer-mini">
                            <button type="button" class="portal-boton-secundario" data-desvincular-clinica="<?= (int) $clinica['id_clinica'] ?>" data-nombre="<?= $e($clinica['nombre']) ?>">
                                <i class="ri-link-unlink" aria-hidden="true"></i> Desvincularme
                            </button>
                        </div>
                        <?php // RE-5.13.3: el rechazo por citas sin resolver sale aquí, en una línea. ?>
                        <p class="cuenta-validacion portal-aviso" role="status" data-aviso-clinica></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($clinicas_disponibles)): ?>
            <div class="portal-subtitulo-fila">
                <h2 class="portal-subtitulo">Otras clínicas en Zooki</h2>
                <div class="search-input portal-buscador">
                    <i class="ri-search-line" aria-hidden="true"></i>
                    <input type="search" data-buscar-clinica maxlength="100" placeholder="Buscar clínica" aria-label="Buscar una clínica por su nombre o dirección">
                </div>
            </div>
            <div class="portal-tarjetas" data-otras-clinicas>
                <?php foreach ($clinicas_disponibles as $clinica): ?>
                    <article class="person-card portal-clinica" data-buscar="<?= $e(mb_strtolower($clinica['nombre'] . ' ' . ($clinica['direccion'] ?? ''))) ?>">
                        <div class="card-header-mini">
                            <span class="avatar-mini avatar-iniciales" aria-hidden="true"><?= $e(PresentacionPlataforma::iniciales($clinica['nombre'])) ?></span>
                        </div>
                        <div class="card-body-mini">
                            <h3 class="card-title-mini"><?= $e($clinica['nombre']) ?></h3>
                            <?php if (!empty($clinica['direccion'])): ?>
                                <span class="contact-text-mini"><i class="ri-map-pin-line" aria-hidden="true"></i> <?= $e($clinica['direccion']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer-mini">
                            <button type="button" class="btn-primary btn-primary--compacto" data-vincular-clinica="<?= (int) $clinica['id_clinica'] ?>" data-nombre="<?= $e($clinica['nombre']) ?>">
                                <i class="ri-link" aria-hidden="true"></i> Vincularme
                            </button>
                        </div>
                        <p class="cuenta-validacion portal-aviso" role="status" data-aviso-clinica></p>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="portal-filtro-vacio" data-sin-clinicas hidden>No hay clínicas con ese nombre.</p>
        <?php endif; ?>
    </div>
</section>

<!-- ══ PERFIL ═══════════════════════════════════════════════════════ -->
<!-- M5 C2b: datos, contacto, datos de acceso, contraseña y salir, en tarjetas plegables. -->
<section id="screen-account" class="app-screen" aria-labelledby="tituloCuenta">
    <header class="portal-cabecera">
        <div>
            <h1 id="tituloCuenta" class="portal-cabecera__titulo">Perfil</h1>
            <p class="portal-cabecera__ayuda">Tus datos y el acceso a tu cuenta.</p>
        </div>
    </header>

    <div class="portal-plegables">
        <details class="profile-card portal-plegable" open>
            <summary><i class="ri-user-3-line" aria-hidden="true"></i> Mis datos</summary>
            <div class="portal-plegable__cuerpo">
                <div class="portal-perfil">
                    <span class="avatar-mini avatar-iniciales avatar-iniciales--grande" aria-hidden="true"><?= $e($iniciales) ?></span>
                    <div>
                        <h2 class="card-title-mini"><?= $e($_SESSION['usuario_nombre'] ?? 'Propietario') ?></h2>
                        <span class="tag-mini">Propietario</span>
                    </div>
                </div>
                <dl class="profile-details-list">
                    <div class="profile-detail-row">
                        <dt class="profile-detail-label">Cédula</dt>
                        <dd class="profile-detail-value" data-documento-cuenta><?= $e($usuarioData['documento'] ?? '—') ?></dd>
                    </div>
                    <div class="profile-detail-row">
                        <dt class="profile-detail-label">Correo</dt>
                        <dd class="profile-detail-value" id="profileEmailVal"><?= $e($usuarioData['email'] ?? 'No registrado') ?></dd>
                    </div>
                    <div class="profile-detail-row">
                        <dt class="profile-detail-label">Teléfono</dt>
                        <dd class="profile-detail-value" id="profilePhoneVal"><?= $e($usuarioData['telefono'] ?? 'No registrado') ?></dd>
                    </div>
                </dl>
            </div>
        </details>

        <details class="profile-card portal-plegable" id="contactEditSection">
            <summary><i class="ri-contacts-line" aria-hidden="true"></i> Datos de contacto</summary>
            <form id="portalContactEditForm" class="portal-form portal-plegable__cuerpo" novalidate data-validacion-cuenta>
                <?php // Decisión C6: el correo es el dato de acceso; su cambio con verificación llega en la etapa D (RE-T.5.7). ?>
                <div class="input-group">
                    <span class="portal-label">Correo electrónico</span>
                    <p class="portal-ayuda"><?= $e($usuarioData['email'] ?? '') ?> · Puedes cambiarlo en Datos de acceso, verificando el correo nuevo.</p>
                </div>
                <div class="input-group">
                    <label class="portal-label" for="portal_contact_phone">Teléfono de contacto *</label>
                    <div class="search-input-wrapper campo">
                        <i class="ri-phone-line" aria-hidden="true"></i>
                        <?php // C9.1: los mismos límites que valida el servidor (ValidadorTelefono). ?>
                        <input type="tel" name="telefono" id="portal_contact_phone" required autocomplete="tel" placeholder="Tu número de teléfono" minlength="<?= ValidadorTelefono::MIN ?>" <?= ValidadorTelefono::atributosHtml() ?> value="<?= htmlspecialchars($usuarioData['telefono'] ?? '') ?>">
                    </div>
                </div>
                <p role="status" data-password-resultado></p>
                <div class="portal-plegable__pie">
                    <button type="submit" class="btn-primary btn-primary--compacto" id="btnSubmitContactEdit">
                        <span>Guardar cambios</span>
                        <i class="ri-check-line" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </details>

        <?php require __DIR__ . "/../partials/cambios_identidad.php"; ?>

        <details class="profile-card portal-plegable" id="passwordChangeSection">
            <summary><i class="ri-lock-password-line" aria-hidden="true"></i> Contraseña</summary>
            <form id="portalChangePasswordForm" data-password-accion="cambiar_password_ajax" class="portal-form portal-plegable__cuerpo" data-validacion-cuenta>
                <?php if (empty($usuarioData['tiene_password'])) require __DIR__ . '/../partials/confirmacion_google.php'; ?>
                <?php // HU-39: se pide la actual si la cuenta tiene una contraseña conocida ?>
                <?php if ((int) ($usuarioData['tiene_password'] ?? 1) === 1): ?>
                <div class="input-group">
                    <label class="portal-label" for="portal_current_password">Contraseña actual</label>
                    <div class="search-input-wrapper campo">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" name="current_password" id="portal_current_password" required autocomplete="current-password" placeholder="Tu contraseña actual">
                    </div>
                </div>
                <?php endif; ?>
                <div class="input-group">
                    <label class="portal-label" for="portal_new_password">Nueva contraseña</label>
                    <div class="search-input-wrapper campo">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" name="new_password" id="portal_new_password" data-cuenta-ayuda="portal_pwd_strength_text" required autocomplete="new-password" placeholder="Mínimo 8 caracteres"  aria-describedby="portal_pwd_strength_text">
                    </div>
                    <div class="password-strength-meter" aria-hidden="true">
                        <div id="portal_pwd_strength_bar"></div>
                    </div>
                    <small id="portal_pwd_strength_text" class="password-strength-text">Mínimo 8 caracteres, una mayúscula y un número.</small>
                </div>
                <div class="input-group">
                    <label class="portal-label" for="portal_confirm_password">Confirmar nueva contraseña</label>
                    <div class="search-input-wrapper campo">
                        <input <?= ValidadorCuenta::atributosHtml('password') ?> type="password" name="confirm_password" id="portal_confirm_password" required autocomplete="new-password" placeholder="Repite la nueva contraseña" >
                    </div>
                    <small id="portal_pwd_match_text" class="password-match-text" role="alert">Las contraseñas no coinciden.</small>
                </div>
                <p role="status" data-password-resultado></p>
                <div class="portal-plegable__pie">
                    <button type="submit" id="portal_btn_change_pwd" class="btn-primary btn-primary--compacto">Actualizar contraseña</button>
                </div>
            </form>
        </details>

        <a href="index.php?action=logout" class="profile-card portal-plegable portal-plegable--enlace">
            <span><i class="ri-logout-box-r-line" aria-hidden="true"></i> Cerrar sesión</span>
            <i class="ri-arrow-right-s-line" aria-hidden="true"></i>
        </a>
    </div>
</section>

<!-- ══ MASCOTA ══════════════════════════════════════════════════════ -->
<section id="screen-pet-detail" class="app-screen pet-detail" aria-labelledby="drawerPetTitle">
    <header class="pet-detail__cabecera">
        <button type="button" class="portal-drawer-back" data-ui-accion="cerrar-drawer" aria-label="Volver">
            <i class="ri-arrow-left-line" aria-hidden="true"></i>
        </button>
        <div class="pet-detail__identidad">
            <h1 id="drawerPetTitle" class="pet-detail__titulo">—</h1>
            <div id="drawerPetTags" class="card-tags-mini"></div>
        </div>
        <div class="pet-detail__acciones">
            <?php // C9.1 (RE-5.5): imprimir el historial desde la ficha; portal.js pone el enlace. ?>
            <a href="#" id="btnImprimirFichaMascota" class="action-btn-mini" title="Imprimir historial" aria-label="Imprimir historial" hidden>
                <i class="ri-printer-line" aria-hidden="true"></i>
            </a>
            <button type="button" id="btnEditPetProfile" class="action-btn-mini" title="Editar la mascota" aria-label="Editar la mascota">
                <i class="ri-edit-line" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <div class="pet-detail__layout">
        <aside id="drawerPetSummary" class="portal-drawer-summary pet-detail__ficha"></aside>

        <div class="pet-detail__contenido">
            <?php // RN-114: filtro por clínica cuando hay registros de varias; portal.js lo llena. ?>
            <div id="filtroClinicaMascota" class="pet-filter-chips-wrapper" role="group" aria-label="Filtrar por clínica" hidden></div>

            <nav class="tabs-wrapper portal-pestanas" role="tablist" aria-label="Información de la mascota" data-pestanas="mascota">
                <button type="button" class="tab-btn active" data-pestana="tab-historial" role="tab" aria-selected="true" aria-controls="tab-historial">
                    <i class="ri-history-line" aria-hidden="true"></i> Historia
                </button>
                <button type="button" class="tab-btn" data-pestana="tab-vacunas" role="tab" aria-selected="false" aria-controls="tab-vacunas">
                    <i class="ri-syringe-line" aria-hidden="true"></i> Vacunas
                </button>
                <button type="button" class="tab-btn" data-pestana="tab-desparasitaciones" role="tab" aria-selected="false" aria-controls="tab-desparasitaciones">
                    <i class="ri-capsule-line" aria-hidden="true"></i> <span class="texto-largo">Desparasitaciones</span><span class="texto-corto" aria-hidden="true">Desparasit.</span>
                </button>
                <button type="button" class="tab-btn" data-pestana="tab-citas" role="tab" aria-selected="false" aria-controls="tab-citas">
                    <i class="ri-calendar-line" aria-hidden="true"></i> Citas
                </button>
            </nav>

            <div class="pet-detail__paneles">
                <div id="tab-historial" class="portal-panel" role="tabpanel">
                    <div id="historialContent"><div class="portal-loading">Cargando historia…</div></div>
                </div>
                <div id="tab-vacunas" class="portal-panel" role="tabpanel" hidden>
                    <div id="vacunasContent"></div>
                </div>
                <div id="tab-desparasitaciones" class="portal-panel" role="tabpanel" hidden>
                    <div id="desparasitacionesContent"></div>
                </div>
                <div id="tab-citas" class="portal-panel" role="tabpanel" hidden>
                    <div id="citasContent"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php
/**
 * Encabezado común de las ventanas: botón volver, ícono, título y ayuda.
 * En móvil son hojas que suben desde abajo; desde tablet, ventanas centradas.
 */
$cabeceraVentana = function (string $idCerrar, string $icono, string $titulo, string $ayuda, string $idTitulo): void { ?>
        <div class="portal-drawer-header">
            <button type="button" id="<?= $idCerrar ?>" class="portal-drawer-back" aria-label="Cerrar">
                <i class="ri-arrow-left-line" aria-hidden="true"></i>
            </button>
            <div class="portal-drawer-title-wrap">
                <h2 id="<?= $idTitulo ?>"><i class="<?= $icono ?>" aria-hidden="true"></i> <?= $titulo ?></h2>
                <p><?= $ayuda ?></p>
            </div>
        </div>
<?php };

/**
 * Campo con el borde y el ícono de los formularios del portal.
 * Los obligatorios llevan * y los demás «(opcional)», como en el panel del personal.
 * M5 C2b: con $aviso, una línea reservada debajo para el aviso del campo (M0.1/M0.2:
 * aparecer o vaciarse no mueve nada).
 */
$campo = function (string $etiqueta, string $control, string $icono = '', string $for = '', bool $obligatorio = true, string $ayuda = '', bool $aviso = false): void { ?>
                <div class="input-group">
                    <label class="portal-label"<?= $for ? " for=\"$for\"" : '' ?>><?= $etiqueta ?><?= $obligatorio ? ' <span class="portal-requerido" aria-hidden="true">*</span>' : ' <span class="portal-opcional">(opcional)</span>' ?></label>
                    <div class="search-input-wrapper campo">
                        <?php if ($icono): ?><i class="<?= $icono ?>" aria-hidden="true"></i><?php endif; ?>
                        <?= $control ?>
                    </div>
                    <?php if ($ayuda): ?><p class="portal-ayuda"<?= $for ? " id=\"{$for}_ayuda\"" : '' ?>><?= $ayuda ?></p><?php endif; ?>
                    <?php if ($aviso): ?><small class="cuenta-validacion portal-aviso" id="<?= $for ?>_aviso" aria-live="polite"></small><?php endif; ?>
                </div>
<?php };

$leyenda = '<p class="portal-form__leyenda"><span class="portal-requerido" aria-hidden="true">*</span> Obligatorio. Lo demás es opcional.</p>';

// Días en que cada clínica no atiende en toda la jornada (1 = lunes … 7 = domingo).
$diasCerradosPorClinica = [];
foreach ($clinicas_portal as $clinica) {
    $diasCerradosPorClinica[(int) $clinica['id_clinica']] = array_values($clinica['dias_cerrados']);
}
?>

<!-- Ventana: agendar cita, por pasos (M5 C2b): clínica y mascota, tipo y
     veterinario, día y hora, y confirmar. -->
<div id="portalBookingModal" class="portal-drawer-overlay">
    <div class="portal-drawer portal-drawer--ancho" role="dialog" aria-modal="true" aria-labelledby="bookingTitulo">
        <?php $cabeceraVentana('btnCloseBookingModal', 'ri-calendar-event-line', 'Agendar una cita', 'En cuatro pasos: clínica y mascota, tipo y veterinario, día y hora, y confirmar.', 'bookingTitulo'); ?>
        <div class="portal-drawer-body">
            <form id="portalBookingForm" class="portal-form booking" novalidate data-dias-cerrados="<?= $e(json_encode($diasCerradosPorClinica)) ?>">
                <ol class="booking__progreso" aria-label="Pasos para agendar">
                    <?php foreach (['Clínica y mascota', 'Tipo y veterinario', 'Día y hora', 'Confirmar'] as $n => $nombrePaso): ?>
                        <li class="booking__progreso-paso<?= $n === 0 ? ' is-activo' : '' ?>" data-paso-indicador="<?= $n + 1 ?>"><span aria-hidden="true"><?= $n + 1 ?></span> <?= $e($nombrePaso) ?></li>
                    <?php endforeach; ?>
                </ol>

                <fieldset class="booking__paso" data-paso="1">
                    <legend class="sr-only">Clínica y mascota</legend>
                    <?php // RE-5.9.3 (hueco 8): solo sus clínicas; con una sola, su nombre y el id oculto. ?>
                    <div class="input-group" id="bookingEleccionClinica">
                        <?php if (empty($clinicas_portal)): ?>
                            <?php $vacio('ri-hospital-line', 'Aún no tienes clínicas', 'Vincúlate a una en Clínicas para poder agendar.', '<button type="button" class="btn-enlace" data-nav="clinicas" data-cerrar-agendar>Ver clínicas</button>'); ?>
                        <?php elseif (count($clinicas_portal) === 1): $unica = $clinicas_portal[0]; ?>
                            <span class="portal-label">Clínica</span>
                            <p class="booking__unica"><i class="ri-hospital-line" aria-hidden="true"></i> <?= $e($unica['nombre']) ?></p>
                            <input type="hidden" name="id_clinica" value="<?= (int) $unica['id_clinica'] ?>" data-nombre="<?= $e($unica['nombre']) ?>">
                        <?php else: ?>
                            <span class="portal-label" id="booking_clinica_etq">Clínica <span class="portal-requerido" aria-hidden="true">*</span></span>
                            <div class="portal-opciones" role="radiogroup" aria-labelledby="booking_clinica_etq">
                                <?php foreach ($clinicas_portal as $clinica): ?>
                                    <label class="portal-opcion">
                                        <input type="radio" name="id_clinica" value="<?= (int) $clinica['id_clinica'] ?>" data-nombre="<?= $e($clinica['nombre']) ?>" required>
                                        <span class="portal-opcion__icono" aria-hidden="true"><i class="ri-hospital-line"></i></span>
                                        <span class="portal-opcion__texto">
                                            <strong><?= $e($clinica['nombre']) ?></strong>
                                            <span><?= $e($clinica['ahora']['texto']) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="input-group" id="bookingEleccionMascota">
                        <?php if (empty($mascotas)): ?>
                            <?php $vacio('ri-heart-3-line', 'Todavía no tienes mascotas', 'Regístrala desde Inicio para poder agendarle una cita.'); ?>
                        <?php else: ?>
                            <span class="portal-label" id="booking_mascota_etq">Mascota <span class="portal-requerido" aria-hidden="true">*</span></span>
                            <div class="portal-opciones" role="radiogroup" aria-labelledby="booking_mascota_etq">
                                <?php foreach ($mascotas as $m): ?>
                                    <label class="portal-opcion">
                                        <input type="radio" name="id_mascota" value="<?= (int) $m['id_mascota'] ?>" data-nombre="<?= $e($m['nombre']) ?>" required>
                                        <?php $avatarMascota($m['foto'], (string) $m['nombre'], 'portal-opcion__avatar'); ?>
                                        <span class="portal-opcion__texto">
                                            <strong><?= $e($m['nombre']) ?></strong>
                                            <span><?= $e(PresentacionPortal::especieYRaza($m)) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </fieldset>

                <fieldset class="booking__paso" data-paso="2" hidden>
                    <legend class="sr-only">Tipo y veterinario</legend>
                    <?php
                    $campo('Tipo de cita', '<select name="id_tipo_cita" id="booking_tipo_cita" required><option value="">Elige primero la clínica</option></select>', 'ri-briefcase-line', 'booking_tipo_cita');
                    $campo('Veterinario', '<select name="id_veterinario" id="booking_veterinario" required><option value="">Elige primero la clínica</option></select>', 'ri-stethoscope-line', 'booking_veterinario');
                    $campo('Motivo', '<textarea name="motivo" id="booking_motivo" maxlength="255" placeholder="Ej: control de peso, vómito desde ayer…" rows="2"></textarea>', '', 'booking_motivo', false);
                    ?>
                </fieldset>

                <fieldset class="booking__paso booking__agenda" data-paso="3" hidden>
                    <legend class="sr-only">Día y hora</legend>
                    <div class="input-group">
                        <span class="portal-label" id="booking_dia_etq">Día <span class="portal-requerido" aria-hidden="true">*</span></span>
                        <input type="text" name="fecha" id="booking_fecha" class="booking__fecha" aria-labelledby="booking_dia_etq" readonly tabindex="-1">
                        <p class="portal-ayuda">Los días en gris no se pueden elegir: ya pasaron o la clínica no atiende.</p>
                    </div>
                    <div class="input-group">
                        <span class="portal-label" id="booking_hora_etq">Horario disponible <span class="portal-requerido" aria-hidden="true">*</span></span>
                        <input type="hidden" name="hora" id="booking_hora">
                        <div class="slot-panel" id="booking_slots" role="group" aria-labelledby="booking_hora_etq" aria-live="polite"></div>
                    </div>
                </fieldset>

                <fieldset class="booking__paso" data-paso="4" hidden>
                    <legend class="sr-only">Confirmar</legend>
                    <dl class="booking__resumen" id="booking_resumen"></dl>
                </fieldset>

                <p class="booking__guia" id="booking_guia" aria-live="polite"></p>

                <div class="users-modal__footer portal-modal__pie">
                    <button type="button" class="portal-boton-secundario" data-paso-atras hidden>
                        <i class="ri-arrow-left-line" aria-hidden="true"></i> Atrás
                    </button>
                    <button type="button" class="btn-primary" data-paso-siguiente disabled>
                        <span>Siguiente</span>
                        <i class="ri-arrow-right-line" aria-hidden="true"></i>
                    </button>
                    <button type="submit" class="btn-primary" id="booking_confirmar" hidden disabled>
                        <span>Confirmar cita</span>
                        <i class="ri-calendar-check-line" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
/** Formulario de mascota: el mismo para registrar y para editar. */
$formularioMascota = function (string $p, bool $editar) use ($campo, $leyenda, $catalogo_especies): void {
    // Los ids edit_pet_* los usa portal.js para rellenar la edición.
    // El color no se pide: lo registra la clínica en la consulta, viendo a la mascota.
    $especies = '<option value="">Selecciona…</option>';
    foreach ($catalogo_especies as $especie) {
        $especies .= '<option value="' . (int) $especie['id_especie'] . '">' . htmlspecialchars((string) $especie['nombre_especie']) . '</option>';
    }
    echo $leyenda;
    ?>
                <div class="portal-form__fila">
                    <?php
                    $campo('Nombre', "<input type=\"text\" name=\"nombre\" id=\"{$p}_pet_nombre\" required " . ValidadorMascota::atributosHtml('nombre') . " placeholder=\"Ej: Toby\" autocomplete=\"off\">", '', "{$p}_pet_nombre", true, '', true);
                    $campo('Fecha de nacimiento', "<input type=\"text\" class=\"flatpickr-date\" name=\"fecha_nacimiento\" id=\"{$p}_pet_nacimiento\" placeholder=\"Selecciona la fecha\" " . ValidadorMascota::atributosHtml('fecha_nacimiento') . ">", 'ri-calendar-line', "{$p}_pet_nacimiento", false, '', true);
                    ?>
                </div>
                <div class="portal-form__fila">
                    <?php
                    $campo('Especie', "<select name=\"especie\" id=\"{$p}_pet_especie\" required " . ValidadorMascota::atributosHtml('especie') . ">$especies</select>", '', "{$p}_pet_especie", true, '', true);
                    $campo('Raza', "<select name=\"raza\" id=\"{$p}_pet_raza\" required aria-describedby=\"{$p}_pet_raza_ayuda\" disabled " . ValidadorMascota::atributosHtml('raza') . "><option value=\"\">Primero elige la especie</option></select>", '', "{$p}_pet_raza", true, '', true);
                    ?>
                </div>
                <?php // portal.js la muestra cuando carga las razas de la especie. ?>
                <p class="portal-ayuda portal-ayuda--fila" id="<?= $p ?>_pet_raza_ayuda" hidden></p>
                <?php // «Mi raza no está en la lista»: no crea la raza, la deja para que la clínica la revise (RE-15.9). ?>
                <div class="input-group raza-otra" id="<?= $p ?>_raza_otra" hidden>
                    <label class="portal-label" for="<?= $p ?>_pet_raza_indicada">¿Cuál es la raza? <span class="portal-requerido" aria-hidden="true">*</span></label>
                    <div class="search-input-wrapper campo">
                        <input type="text" name="raza_indicada" id="<?= $p ?>_pet_raza_indicada" <?= ValidadorMascota::atributosHtml('raza_indicada') ?> placeholder="Ej: Pomerania" autocomplete="off" disabled>
                    </div>
                    <small class="cuenta-validacion portal-aviso" id="<?= $p ?>_pet_raza_indicada_aviso" aria-live="polite"></small>
                    <p class="portal-ayuda">La clínica la revisa y la agrega a la lista. Mientras tanto se ve como «por confirmar».</p>
                </div>
                <div class="portal-form__fila">
                    <fieldset class="input-group segmento-campo">
                        <legend class="portal-label">Sexo <span class="portal-requerido" aria-hidden="true">*</span></legend>
                        <div class="segmento">
                            <label class="segmento__opcion">
                                <input type="radio" name="sexo" value="Macho" id="<?= $p ?>_pet_sexo" required <?= ValidadorMascota::atributosHtml('sexo') ?>>
                                <span><i class="ri-men-line" aria-hidden="true"></i> Macho</span>
                            </label>
                            <label class="segmento__opcion">
                                <input type="radio" name="sexo" value="Hembra" required <?= ValidadorMascota::atributosHtml('sexo') ?>>
                                <span><i class="ri-women-line" aria-hidden="true"></i> Hembra</span>
                            </label>
                        </div>
                        <small class="cuenta-validacion portal-aviso" id="<?= $p ?>_pet_sexo_aviso" aria-live="polite"></small>
                    </fieldset>
                    <?php $campo('Peso (kg)', "<input type=\"text\" name=\"peso\" id=\"{$p}_pet_peso\" required " . ValidadorMascota::atributosHtml('peso') . " placeholder=\"Ej: 8,5\">", '', "{$p}_pet_peso", true, '', true); ?>
                </div>

                <div class="input-group">
                    <span class="portal-label" id="<?= $p ?>_foto_etq">Foto de perfil <span class="portal-opcional">(opcional)</span></span>
                    <div class="foto-campo" data-foto="<?= $p ?>">
                        <div class="foto-campo__vista" aria-hidden="true"><i class="ri-image-line"></i></div>
                        <div class="foto-campo__acciones">
                            <input type="file" name="foto" id="<?= $p ?>_pet_foto" accept="image/jpeg,image/png" class="foto-campo__input" aria-labelledby="<?= $p ?>_foto_etq" aria-describedby="<?= $p ?>_foto_ayuda" <?= ValidadorMascota::atributosHtml('foto') ?>>
                            <label class="foto-campo__boton" for="<?= $p ?>_pet_foto"><i class="ri-upload-2-line" aria-hidden="true"></i> <span><?= $editar ? 'Cambiar foto' : 'Elegir foto' ?></span></label>
                            <button type="button" class="btn-enlace foto-campo__quitar" hidden>Quitar la foto elegida</button>
                            <p class="portal-ayuda foto-campo__ayuda" id="<?= $p ?>_foto_ayuda">JPG o PNG, hasta 5 MB.</p>
                        </div>
                    </div>
                </div>
<?php }; ?>

<!-- Ventana: registrar mascota -->
<div id="portalAddPetModal" class="portal-drawer-overlay">
    <div class="portal-drawer" role="dialog" aria-modal="true" aria-labelledby="addPetTitulo">
        <?php $cabeceraVentana('btnCloseAddPetModal', 'ri-baidu-line', 'Registrar mascota', 'Agrega un nuevo compañero a tu cuenta.', 'addPetTitulo'); ?>
        <div class="portal-drawer-body">
            <form id="portalAddPetForm" class="portal-form portal-form--mascota" enctype="multipart/form-data" novalidate data-validacion-mascota data-aviso-obligatorio="<?= $e(ValidadorMascota::AVISO_OBLIGATORIO) ?>">
                <?php $formularioMascota('add', false); ?>
                <p class="portal-form__resumen" role="alert" data-resumen-mascota hidden></p>
                <div class="users-modal__footer portal-modal__pie">
                    <button type="submit" class="btn-primary">
                        <span>Registrar mascota</span>
                        <i class="ri-check-line" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ventana: editar mascota -->
<div id="portalEditPetModal" class="portal-drawer-overlay">
    <div class="portal-drawer" role="dialog" aria-modal="true" aria-labelledby="editPetTitulo">
        <?php $cabeceraVentana('btnCloseEditPetModal', 'ri-edit-line', 'Editar mascota', 'Personaliza y actualiza la información de tu mascota.', 'editPetTitulo'); ?>
        <div class="portal-drawer-body">
            <form id="portalEditPetForm" class="portal-form portal-form--mascota" enctype="multipart/form-data" novalidate data-validacion-mascota data-aviso-obligatorio="<?= $e(ValidadorMascota::AVISO_OBLIGATORIO) ?>">
                <input type="hidden" name="id_mascota" id="edit_pet_id">
                <?php $formularioMascota('edit', true); ?>
                <p class="portal-form__resumen" role="alert" data-resumen-mascota hidden></p>
                <div class="users-modal__footer portal-modal__pie">
                    <button type="submit" class="btn-primary">
                        <span>Guardar cambios</span>
                        <i class="ri-check-line" aria-hidden="true"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ventana: detalle de la cita y ficha clínica -->
<div id="portalCitaDetalleModal" class="portal-drawer-overlay">
    <div class="portal-drawer" role="dialog" aria-modal="true" aria-labelledby="citaDetalleTitulo">
        <?php $cabeceraVentana('btnCloseCitaDetalleModal', 'ri-heart-pulse-fill', 'Detalle de la cita', 'Información y resultado clínico de la consulta.', 'citaDetalleTitulo'); ?>
        <div class="portal-drawer-body">
            <div class="cita-info">
                <div class="cita-info__fila">
                    <span class="cita-info__tipo" id="detCitaTipo">—</span>
                    <span class="status-badge" id="detCitaEstado">—</span>
                </div>
                <div class="cita-info__mascota">Mascota: <span id="detCitaMascota">—</span></div>
                <div class="cita-info__vet">Veterinario: <span id="detCitaVet">—</span></div>
                <div class="cita-info__fecha"><i class="ri-calendar-line" aria-hidden="true"></i> <span id="detCitaFechaHora">—</span></div>
            </div>

            <div id="detCitaClinicaArea" class="cita-clinica">
                <h3 class="cita-clinica__titulo"><i class="ri-folder-shield-2-line cita-clinica__icono--ok" aria-hidden="true"></i> Resultado clínico</h3>

                <div class="cita-signos">
                    <div class="cita-signo"><span>Peso</span><strong id="detClinicaPeso">—</strong></div>
                    <div class="cita-signo"><span>Temp</span><strong id="detClinicaTemp">—</strong></div>
                    <div class="cita-signo"><span>F. cardiaca</span><strong id="detClinicaFc">—</strong></div>
                </div>

                <div class="cita-campos">
                    <div><span class="cita-campo__etiqueta">Motivo de consulta</span><p id="detClinicaMotivo" class="cita-campo"></p></div>
                    <div><span class="cita-campo__etiqueta">Anamnesis / observaciones</span><p id="detClinicaAnamnesis" class="cita-campo"></p></div>
                    <div><span class="cita-campo__etiqueta">Diagnóstico</span><p id="detClinicaDiagnostico" class="cita-campo cita-campo--diagnostico"></p></div>
                    <div><span class="cita-campo__etiqueta">Plan de tratamiento</span><p id="detClinicaPlan" class="cita-campo"></p></div>
                </div>

                <h3 class="cita-clinica__titulo"><i class="ri-capsule-fill" aria-hidden="true"></i> Receta de medicamentos</h3>
                <div id="detClinicaTratamientosList" class="cita-tratamientos"></div>
            </div>

            <div id="detCitaSinClinica" class="cita-sin-clinica">
                <i class="ri-health-book-line" id="detSinClinicaIcon" aria-hidden="true"></i>
                <p class="cita-sin-clinica__titulo" id="detSinClinicaTitle">Esta cita aún no ha sido atendida.</p>
                <p class="cita-sin-clinica__texto" id="detSinClinicaDesc">Cuando el veterinario finalice la consulta, aquí podrás visualizar la historia clínica, diagnóstico y medicamentos recetados.</p>
            </div>
        </div>
    </div>
</div>
