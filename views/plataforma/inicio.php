<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/ValidadorPlataforma.php';
require_once __DIR__ . '/../../helpers/PresentacionPlataforma.php';
/**
 * HU-0.3 — Panel del super-administrador. M0.2: pestañas Clínicas /
 * Soporte / Seguridad con la cabecera, los filtros, la tarjeta y la tabla de
 * Usuarios, y modal «Gestionar» por pestañas.
 * Solo metadatos de clínicas y cantidades del uso del plan (RN-004, RN-G13).
 * El comportamiento está en js/plataforma.js; los datos viajan en data-*.
 */
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$v = $e(App::assetVersion());
$p = PresentacionPlataforma::class;
$contadores = $contadores ?? PresentacionPlataforma::contadores($casos, $ips);
$plan = $plan ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma · Zooki</title>
    <link rel="icon" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/dashboard.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/usuarios.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/pill-sidebar.css?v=<?= $v ?>">
    <link rel="stylesheet" href="css/contexto.css?v=<?= $v ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/plataforma.css?v=<?= $v ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <script src="js/interacciones.js?v=<?= $v ?>" defer></script>
    <script src="js/avisos.js?v=<?= $v ?>" defer></script>
    <script src="js/plataforma.js?v=<?= $v ?>" defer></script>
    <script src="js/pagos-plataforma.js?v=<?= $v ?>" defer></script>
</head>
<body>
<div class="dashboard-layout">
<?php $seccionPlataforma = 'clinicas'; require __DIR__ . '/barra_lateral.php'; ?>
<div class="main-content plataforma-contenido">
<?php require __DIR__ . '/cabecera.php'; ?>
<main id="plataformaContenido" class="content-body plataforma">
    <?php // Revisión del usuario (2026-10-12): la misma cabecera, filtros y tarjeta de Usuarios; sin franja de cifras. ?>
    <div class="header-container-white plataforma-encabezado">
        <div class="head-title-desc">
            <h2 class="users-page-title">Administración de la plataforma</h2>
            <p class="users-module-desc">Gestiona las clínicas, sus planes y suscripciones, los casos de soporte y las IP bloqueadas.</p>
        </div>
        <div class="head-top-right">
            <div class="tabs-wrapper" role="tablist" aria-label="Secciones del panel">
                <button type="button" class="tab-btn active" role="tab" id="pestanaClinicas" aria-controls="panelClinicas" aria-selected="true" data-pestana="clinicas"><i class="bi bi-buildings" aria-hidden="true"></i><span>Clínicas</span></button>
                <button type="button" class="tab-btn" role="tab" id="pestanaSoporte" aria-controls="panelSoporte" aria-selected="false" data-pestana="soporte"><i class="bi bi-life-preserver" aria-hidden="true"></i><span>Soporte</span><span class="plataforma-contador" data-contador-soporte><?= (int) $contadores['casos'] ?></span></button>
                <button type="button" class="tab-btn" role="tab" id="pestanaSeguridad" aria-controls="panelSeguridad" aria-selected="false" data-pestana="seguridad"><i class="bi bi-shield-lock" aria-hidden="true"></i><span>Seguridad</span><span class="plataforma-contador" data-contador-seguridad><?= (int) $contadores['ips'] ?></span></button>
            </div>
        </div>
        <?php // Sin JavaScript el formulario filtra en el servidor; con él, al escribir. Solo se ve en la pestaña Clínicas. ?>
        <form method="GET" class="users-controls-bar__filters is-active plataforma-filtros" data-filtros-clinicas>
            <input type="hidden" name="action" value="plataforma_inicio">
            <div class="head-search">
                <div class="search-input">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="search" name="buscar" value="<?= $e($buscar) ?>" placeholder="Nombre o NIT" aria-label="Buscar clínica por nombre o NIT" maxlength="<?= ValidadorPlataforma::BUSQUEDA_MAX ?>" data-caracteres="\p{L}\p{M}\p{N}\p{P}\p{S} " data-espacios="normalizar" data-filtro-buscar>
                </div>
            </div>
            <div class="head-status-filters">
                <select class="filter-select" name="estado" aria-label="Filtrar por estado" data-filtro-estado>
                    <option value="">Todos los estados</option>
                    <?php foreach (PresentacionPlataforma::ESTADOS as $valor => $texto): ?>
                        <option value="<?= $e($valor) ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $e($texto) ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="filter-select" name="plan" aria-label="Filtrar por plan" data-filtro-plan>
                    <option value="">Todos los planes</option>
                    <?php foreach ($planes as $opcion): ?>
                        <option value="<?= (int) $opcion['id_plan'] ?>" <?= $plan === (string) $opcion['id_plan'] ? 'selected' : '' ?>><?= $e($opcion['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-modal-primary plataforma-filtros__enviar">Buscar</button>
            </div>
            <div class="head-view-toggle">
                <div class="view-toggle" role="group" aria-label="Tipo de vista">
                    <button type="button" class="view-toggle-btn" data-vista="tabla" aria-pressed="false" title="Vista de tabla"><i class="bi bi-table" aria-hidden="true"></i></button>
                    <button type="button" class="view-toggle-btn" data-vista="tarjetas" aria-pressed="true" title="Vista de tarjetas"><i class="bi bi-grid" aria-hidden="true"></i></button>
                </div>
            </div>
        </form>
    </div>

    <section class="plataforma-panel" id="panelClinicas" role="tabpanel" aria-labelledby="pestanaClinicas" data-panel="clinicas">
        <?php if ($error !== null): ?><p class="plataforma-alerta" role="alert"><?= $e($error) ?></p><?php endif; ?>
        <div class="plataforma-vacio" data-sin-resultados <?= $clinicas ? 'hidden' : '' ?>>
            <span class="plataforma-vacio__icono" aria-hidden="true"><i class="bi bi-search"></i></span>
            <div><h3>Sin resultados</h3><p>No hay clínicas que coincidan con la búsqueda o los filtros.</p></div>
        </div>
        <div class="personal-grid view-grid plataforma-tarjetas" data-lista-vista="tarjetas">
            <?php foreach ($clinicas as $clinica): ?>
            <article class="person-card plataforma-tarjeta" data-clinica-fila data-buscar="<?= $e($p::buscable($clinica)) ?>" data-estado="<?= $e($clinica['estado']) ?>" data-plan="<?= $e($clinica['id_plan']) ?>">
                <div class="card-header-mini">
                    <span class="avatar-mini avatar-iniciales" aria-hidden="true"><?= $e($p::iniciales($clinica['nombre'])) ?></span>
                </div>
                <div class="card-body-mini">
                    <h3 class="card-title-mini" title="<?= $e($clinica['nombre']) ?>"><?= $e($clinica['nombre']) ?></h3>
                    <div class="card-tags-mini">
                        <?php require __DIR__ . '/estado_clinica.php'; ?>
                        <span class="tag-mini"><i class="bi bi-gem" aria-hidden="true"></i> <?= $e($clinica['plan'] ?? 'Sin plan') ?></span>
                    </div>
                    <div class="card-contact-mini">
                        <span class="contact-text-mini"><i class="bi bi-upc" aria-hidden="true"></i> NIT <?= $e($p::nit($clinica['nit'])) ?></span>
                        <span class="contact-text-mini" title="<?= $e($p::administrador($clinica)) ?>"><i class="bi bi-person" aria-hidden="true"></i> <span class="plataforma-recorte"><?= $e($p::administrador($clinica)) ?></span></span>
                        <span class="contact-text-mini"><i class="bi bi-credit-card" aria-hidden="true"></i> <?= $e($p::suscripcion($clinica['plan'], $clinica['suscripcion'])) ?></span>
                        <span class="contact-text-mini">Efectivo: <?= $e($clinica['plan_efectivo']) ?> · <?= $e($p::situacion($clinica['vigencia'])) ?></span>
                        <span class="contact-text-mini">Automático: <?= !empty($clinica['vigencia']['suscripcion']['cobro_automatico']) ? 'Sí' : 'No' ?></span>
                        <span class="contact-text-mini">Fin: <?= $e($clinica['fecha_fin_suscripcion'] ?: 'sin fecha') ?></span>
                        <span class="contact-text-mini"><i class="bi bi-calendar3" aria-hidden="true"></i> Registro: <?= $e($p::fecha($clinica['fecha_registro'])) ?></span>
                    </div>
                    <?php require __DIR__ . '/uso_plan.php'; ?>
                </div>
                <div class="card-footer-mini"><?php require __DIR__ . '/boton_clinica.php'; ?></div>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="table-view plataforma-tabla" data-lista-vista="tabla" hidden>
            <table class="users-table">
                <thead><tr><th>Clínica</th><th>Estado</th><th>Plan</th><th>Administrador</th><th>Registro</th><th>Uso del plan</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($clinicas as $clinica): ?>
                    <tr data-clinica-fila data-buscar="<?= $e($p::buscable($clinica)) ?>" data-estado="<?= $e($clinica['estado']) ?>" data-plan="<?= $e($clinica['id_plan']) ?>">
                        <td><div class="table-user"><span class="avatar-iniciales avatar-iniciales--tabla" aria-hidden="true"><?= $e($p::iniciales($clinica['nombre'])) ?></span><div><strong><?= $e($clinica['nombre']) ?></strong><span class="plataforma-nit">NIT <?= $e($p::nit($clinica['nit'])) ?></span></div></div></td>
                        <td><?php require __DIR__ . '/estado_clinica.php'; ?></td>
                        <td><?= $e($p::plan($clinica['plan'], $clinica['suscripcion'])) ?><span class="plataforma-nit">Efectivo: <?= $e($clinica['plan_efectivo']) ?> · <?= $e($p::situacion($clinica['vigencia'])) ?></span><span class="plataforma-nit">Fin: <?= $e($clinica['fecha_fin_suscripcion'] ?: 'sin fecha') ?></span><span class="plataforma-nit">Automático: <?= !empty($clinica['vigencia']['suscripcion']['cobro_automatico']) ? 'Sí' : 'No' ?></span></td>
                        <td><?= $e($p::administrador($clinica)) ?></td>
                        <td><?= $e($p::fecha($clinica['fecha_registro'])) ?></td>
                        <td><?php require __DIR__ . '/uso_plan.php'; ?></td>
                        <td><div class="table-actions"><?php require __DIR__ . '/boton_clinica.php'; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="button" class="btn-modal-secondary" data-abrir-dialogo="incidenciasSinClinica" data-finanzas-global>Incidencias · Sin clínica identificada</button>
    </section>

    <div class="users-modal plataforma-dialogo" id="incidenciasSinClinica" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="incidenciasSinClinicaTitulo">
        <div class="modal-content users-modal__panel plataforma-modal">
            <div class="modal-header"><h3 id="incidenciasSinClinicaTitulo">Sin clínica identificada</h3><button type="button" data-cerrar-dialogo class="close-modal" aria-label="Cerrar">×</button></div>
            <div class="users-modal__body" data-finanzas-destino data-clinica="0"></div>
        </div>
    </div>

    <?php
    // Revisión del usuario (2026-10-12): Soporte y Seguridad en tarjetas de Usuarios, en cuadrícula
    // (no filas a todo el ancho), estados vacíos compactos y las acciones en modales pequeños.
    $motivosCaso = ['Se corrigió el dato duplicado', 'Reporte duplicado', 'Sin información suficiente', 'Se contactó a la clínica'];
    ?>
    <section class="plataforma-panel" id="panelSoporte" role="tabpanel" aria-labelledby="pestanaSoporte" data-panel="soporte" hidden>
        <?php if (!$casos): ?>
        <div class="plataforma-vacio">
            <span class="plataforma-vacio__icono" aria-hidden="true"><i class="bi bi-life-preserver"></i></span>
            <div><h3>No hay casos de soporte</h3><p>Cuando alguien reporte un NIT o un documento duplicado, el caso aparecerá aquí para revisarlo.</p></div>
        </div>
        <?php endif; ?>
        <div class="personal-grid view-grid plataforma-tarjetas">
            <?php foreach ($casos as $caso): ?>
            <?php $abierto = $caso['estado'] === 'abierto'; ?>
            <article class="person-card plataforma-tarjeta">
                <div class="card-header-mini">
                    <span class="avatar-mini avatar-iniciales plataforma-icono-tarjeta" aria-hidden="true"><i class="bi bi-life-preserver"></i></span>
                    <span class="tag-mini plataforma-estado <?= $abierto ? 'plataforma-estado--activa' : '' ?>"><i class="bi <?= $abierto ? 'bi-envelope-open' : 'bi-check2-circle' ?>" aria-hidden="true"></i> <?= $e(ucfirst($caso['estado'])) ?></span>
                </div>
                <div class="card-body-mini">
                    <h3 class="card-title-mini" title="Caso <?= (int) $caso['id_caso'] ?>">Caso <?= (int) $caso['id_caso'] ?> · <?= $e(CasoSoporte::tipoVisible($caso['tipo'])) ?></h3>
                    <div class="card-contact-mini">
                        <span class="contact-text-mini"><i class="bi bi-buildings" aria-hidden="true"></i> <span class="plataforma-recorte"><?= $e($caso['clinica'] ?? 'Sin clínica') ?></span></span>
                        <span class="contact-text-mini"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= $e($p::fecha($caso['fecha'])) ?></span>
                    </div>
                    <p class="plataforma-tarjeta__texto"><?= $e($caso['descripcion']) ?></p>
                </div>
                <div class="card-footer-mini">
                    <button type="button" class="plataforma-boton-mini" data-abrir-dialogo="casoModal<?= (int) $caso['id_caso'] ?>"><i class="bi bi-check2-square" aria-hidden="true"></i> Ver detalle</button>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php // Fuera de las tarjetas: su animación (transform) encerraría el fondo fijo del modal. ?>
        <?php foreach ($casos as $caso): ?>
            <?php $abierto = $caso['estado'] === 'abierto'; ?>
            <div class="users-modal plataforma-dialogo" id="casoModal<?= (int) $caso['id_caso'] ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="casoTitulo<?= (int) $caso['id_caso'] ?>">
                <div class="modal-content users-modal__panel plataforma-modal plataforma-modal--angosto">
                    <div class="modal-header">
                        <div class="plataforma-modal__cabeza">
                            <h3 id="casoTitulo<?= (int) $caso['id_caso'] ?>">Detalle del caso <?= (int) $caso['id_caso'] ?></h3>
                            <p class="plataforma-modal__datos"><?= $e(CasoSoporte::tipoVisible($caso['tipo'])) ?><span class="plataforma-modal__separador" aria-hidden="true">·</span><?= $e($caso['clinica'] ?? 'Sin clínica') ?></p>
                        </div>
                        <button type="button" data-cerrar-dialogo class="close-modal" aria-label="Cerrar">×</button>
                    </div>
                    <div class="users-modal__body">
                        <dl class="plataforma-caso__datos">
                            <dt>Tipo</dt><dd><?= $e(CasoSoporte::tipoVisible($caso['tipo'])) ?></dd>
                            <dt>Fecha</dt><dd><?= $e($p::fecha($caso['fecha'])) ?></dd>
                            <dt>Cuenta que lo pidió</dt><dd><?= $e($caso['solicitante_nombre'] ?? 'No identificada') ?> · <?= $e($caso['solicitante_email'] ?? 'Sin correo disponible') ?><?php if ($caso['id_usuario'] !== null): ?> · Cuenta #<?= (int) $caso['id_usuario'] ?><?php endif; ?></dd>
                            <?php if ($caso['tipo'] === 'documento_duplicado'): ?>
                            <dt>Cuenta que actualmente tiene el documento</dt><dd><?php if ($caso['cuenta_documento'] !== null): ?><?= $e($caso['cuenta_documento']['nombre_completo'] ?? 'Sin nombre') ?> · <?= $e($caso['cuenta_documento']['email'] ?? 'Sin correo disponible') ?> · Cuenta #<?= (int) $caso['cuenta_documento']['id_usuario'] ?><?php else: ?>No se pudo identificar con los datos disponibles.<?php endif; ?></dd>
                            <?php endif; ?>
                            <dt>Descripción</dt><dd><?= $e($caso['descripcion']) ?></dd>
                            <dt>Estado</dt><dd><?= $e(ucfirst($caso['estado'])) ?></dd>
                        </dl>
                        <?php if ($abierto): ?>
                        <form class="plataforma-form" id="casoForm<?= (int) $caso['id_caso'] ?>" data-plataforma-accion="plataforma_caso_ajax">
                            <?php Csrf::field(); ?><input type="hidden" name="id_caso" value="<?= (int) $caso['id_caso'] ?>">
                            <fieldset class="plataforma-grupo">
                                <legend>Resultado</legend>
                                <div class="plataforma-opciones">
                                    <label class="plataforma-opcion"><input type="radio" name="estado" value="resuelto" required checked><span class="plataforma-opcion__icono" aria-hidden="true"><i class="bi bi-check2-circle"></i></span><span class="plataforma-opcion__texto"><strong>Resuelto</strong><span>Se atendió el reporte.</span></span><span class="plataforma-opcion__marca" aria-hidden="true"><i class="bi bi-check-lg"></i></span></label>
                                    <label class="plataforma-opcion"><input type="radio" name="estado" value="descartado"><span class="plataforma-opcion__icono" aria-hidden="true"><i class="bi bi-slash-circle"></i></span><span class="plataforma-opcion__texto"><strong>Descartado</strong><span>No hacía falta actuar.</span></span><span class="plataforma-opcion__marca" aria-hidden="true"><i class="bi bi-check-lg"></i></span></label>
                                </div>
                            </fieldset>
                            <label class="input-group">Nota <textarea name="motivo" rows="2" placeholder="Escribe la nota o elige una abajo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                            <div class="plataforma-sugerencias" role="group" aria-label="Notas frecuentes">
                                <?php foreach ($motivosCaso as $motivo): ?><button type="button" class="plataforma-sugerencia" data-motivo-sugerido="<?= $e($motivo) ?>"><?= $e($motivo) ?></button><?php endforeach; ?>
                            </div>
                            <p class="plataforma-form__resultado" data-resultado role="status"></p>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="users-modal__footer">
                        <button type="button" class="btn-modal-secondary" data-cerrar-dialogo>Cancelar</button>
                        <?php if ($abierto): ?>
                        <button type="submit" class="btn-modal-primary" form="casoForm<?= (int) $caso['id_caso'] ?>"><i class="bi bi-check2-square" aria-hidden="true"></i> Guardar resultado</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="plataforma-panel" id="panelSeguridad" role="tabpanel" aria-labelledby="pestanaSeguridad" data-panel="seguridad" hidden>
        <?php if (!$ips): ?>
        <div class="plataforma-vacio">
            <span class="plataforma-vacio__icono" aria-hidden="true"><i class="bi bi-shield-check"></i></span>
            <div><h3>No hay direcciones IP bloqueadas</h3><p>Una IP se bloquea 15 minutos después de 20 intentos fallidos de inicio de sesión. Si pasa, aquí podrás desbloquearla o avisar a la clínica.</p></div>
        </div>
        <?php endif; ?>
        <div class="personal-grid view-grid plataforma-tarjetas">
            <?php foreach ($ips as $indice => $ip): ?>
            <article class="person-card plataforma-tarjeta">
                <div class="card-header-mini">
                    <span class="avatar-mini avatar-iniciales plataforma-icono-tarjeta" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                    <span class="tag-mini plataforma-estado plataforma-estado--activa"><i class="bi bi-clock" aria-hidden="true"></i> Hasta las <?= $e(date('H:i', strtotime((string) $ip['bloqueado_hasta']) ?: time())) ?></span>
                </div>
                <div class="card-body-mini">
                    <h3 class="card-title-mini"><?= $e($ip['ip']) ?></h3>
                    <div class="card-contact-mini">
                        <span class="contact-text-mini"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Fallos desde las <?= $e(date('H:i', strtotime((string) $ip['primer_intento']) ?: time())) ?></span>
                        <?php foreach ($ip['cuentas'] as $cuenta): ?>
                            <span class="contact-text-mini" title="<?= $e(implode(', ', array_column($cuenta['clinicas'], 'nombre'))) ?>"><i class="bi bi-person" aria-hidden="true"></i> <span class="plataforma-recorte"><?= $e($cuenta['email'] ?? 'Cuenta no identificada') ?> · <?= $e(implode(', ', array_column($cuenta['clinicas'], 'nombre')) ?: 'sin clínica') ?></span></span>
                        <?php endforeach; ?>
                    </div>
                    <p class="plataforma-tarjeta__nota">Las clínicas salen de las cuentas que fallaron; una IP puede ser compartida.</p>
                </div>
                <div class="card-footer-mini">
                    <form class="plataforma-pie-accion" data-plataforma-accion="plataforma_desbloquear_ajax">
                        <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>">
                        <button type="submit" class="plataforma-boton-mini plataforma-boton-mini--principal"><i class="bi bi-unlock" aria-hidden="true"></i> Desbloquear</button>
                        <p class="plataforma-form__resultado" data-resultado role="status"></p>
                    </form>
                    <?php if ($ip['administradores']): ?>
                        <button type="button" class="plataforma-boton-mini" data-abrir-dialogo="avisoModal<?= (int) $indice ?>"><i class="bi bi-envelope" aria-hidden="true"></i> Enviar aviso</button>
                    <?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <?php foreach ($ips as $indice => $ip): ?>
            <?php if ($ip['administradores']): ?>
            <div class="users-modal plataforma-dialogo" id="avisoModal<?= (int) $indice ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="avisoTitulo<?= (int) $indice ?>">
                <div class="modal-content users-modal__panel plataforma-modal plataforma-modal--angosto">
                    <div class="modal-header">
                        <div class="plataforma-modal__cabeza">
                            <h3 id="avisoTitulo<?= (int) $indice ?>">Avisar a una clínica</h3>
                            <p class="plataforma-modal__datos">IP <?= $e($ip['ip']) ?><span class="plataforma-modal__separador" aria-hidden="true">·</span>se envía solo al pulsar «Enviar aviso»</p>
                        </div>
                        <button type="button" data-cerrar-dialogo class="close-modal" aria-label="Cerrar">×</button>
                    </div>
                    <div class="users-modal__body">
                        <form class="plataforma-form" id="avisoForm<?= (int) $indice ?>" data-plataforma-accion="plataforma_aviso_ajax">
                            <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>">
                            <fieldset class="plataforma-grupo">
                                <legend>Destinatario</legend>
                                <div class="plataforma-opciones">
                                    <?php foreach ($ip['administradores'] as $clave => $admin): ?>
                                        <label class="plataforma-opcion"><input type="radio" name="destinatario" value="<?= $e($clave) ?>" required><span class="plataforma-opcion__icono" aria-hidden="true"><i class="bi bi-person-badge"></i></span><span class="plataforma-opcion__texto"><strong><?= $e($admin['clinica']) ?></strong><span class="plataforma-recorte"><?= $e($admin['email']) ?></span></span><span class="plataforma-opcion__marca" aria-hidden="true"><i class="bi bi-check-lg"></i></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                            <label class="input-group">Texto <textarea name="texto" rows="4" <?= ValidadorPlataforma::atributos() ?>>Se detectaron intentos fallidos de acceso desde la IP <?= $e($ip['ip']) ?>, bloqueada temporalmente hasta <?= $e($ip['bloqueado_hasta']) ?>. Revisa con tu equipo si fueron errores al entrar. Si no reconoces estos intentos, contacta a soporte de Zooki.</textarea><small data-error-campo role="status"></small></label>
                            <p class="plataforma-form__resultado" data-resultado role="status"></p>
                        </form>
                    </div>
                    <div class="users-modal__footer">
                        <button type="button" class="btn-modal-secondary" data-cerrar-dialogo>Cancelar</button>
                        <button type="submit" class="btn-modal-primary" form="avisoForm<?= (int) $indice ?>"><i class="bi bi-send" aria-hidden="true"></i> Enviar aviso</button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>

    <?php
    // M0.2.1: cabecera compacta; cada pestaña con su cuerpo y su pie (el del modal de Usuarios).
    // Los botones del pie se asocian a su formulario con el atributo form.
    // Las acciones de estado se ofrecen según el estado actual (plataforma.js), como tarjetas;
    // el servidor sigue validando cada transición (Plataforma::cambiarEstado).
    ?>
    <div class="users-modal" id="gestionarClinica" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="clinicaModalTitulo">
        <div class="modal-content users-modal__panel plataforma-modal">
            <div class="modal-header">
                <div class="plataforma-modal__cabeza">
                    <div class="plataforma-modal__titulo">
                        <h3 id="clinicaModalTitulo" data-clinica-titulo></h3>
                        <span class="tag-mini plataforma-estado" data-clinica-estado></span>
                    </div>
                    <p class="plataforma-modal__datos"><span data-clinica-nit></span><span class="plataforma-modal__separador" aria-hidden="true">·</span><span data-clinica-admin></span></p>
                </div>
                <button type="button" data-cerrar-dialogo class="close-modal" aria-label="Cerrar">×</button>
            </div>
            <div class="plataforma-modal__pestanas" role="tablist" aria-label="Gestionar la clínica">
                <button type="button" role="tab" class="plataforma-modal__pestana active" aria-selected="true" data-modal-pestana="plan">Plan y suscripción</button>
                <button type="button" role="tab" class="plataforma-modal__pestana" aria-selected="false" data-modal-pestana="estado">Estado</button>
                <button type="button" role="tab" class="plataforma-modal__pestana" aria-selected="false" data-modal-pestana="pagos">Pagos e incidencias</button>
            </div>

            <?php
            // Revisión del usuario (2026-10-12): elecciones con tarjetas, interruptor y sugerencias,
            // nada de listas nativas; «Dar de baja» es una tarjeta de acción, no un aviso.
            $motivosPlan = ['Pago recibido', 'Cambio pedido por la clínica', 'Fin del periodo de prueba', 'Falta de pago'];
            $motivosEstado = ['Falta de pago', 'Uso indebido del plan gratuito', 'Solicitud de la clínica', 'Pago al día'];
            ?>
            <div class="plataforma-modal__panel" data-modal-panel="plan">
                <div class="users-modal__body">
                    <p class="plataforma-nota" data-aviso-plan hidden><i class="bi bi-info-circle" aria-hidden="true"></i><span>La clínica debe estar verificada y no dada de baja para cambiar su plan.</span></p>
                    <form class="plataforma-form" id="plataformaPlanForm" data-plataforma-accion="plataforma_suscripcion_ajax">
                        <?php Csrf::field(); ?><input type="hidden" name="id_clinica"><input type="hidden" name="estado" value="activa" data-suscripcion-valor>
                        <fieldset class="plataforma-grupo">
                            <legend>Plan</legend>
                            <div class="plataforma-opciones">
                                <?php foreach ($planes as $opcion): ?>
                                    <?php $gratuito = $opcion['nombre'] === 'Gratuito'; ?>
                                    <label class="plataforma-opcion">
                                        <input type="radio" name="id_plan" value="<?= (int) $opcion['id_plan'] ?>" required data-plan-gratuito="<?= $gratuito ? '1' : '0' ?>">
                                        <span class="plataforma-opcion__icono" aria-hidden="true"><i class="bi <?= $gratuito ? 'bi-gift' : 'bi-gem' ?>"></i></span>
                                        <span class="plataforma-opcion__texto"><strong><?= $e($opcion['nombre']) ?></strong><span><?= $e(PresentacionPlataforma::topesPlan($opcion)) ?></span></span>
                                        <span class="plataforma-opcion__marca" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <div class="plataforma-suscripcion" data-suscripcion>
                            <div class="plataforma-suscripcion__fila">
                                <span class="plataforma-suscripcion__texto"><strong>Suscripción activa</strong><span>Concesión administrativa, separada del pago.</span></span>
                                <label class="toggle-switch"><input type="checkbox" data-suscripcion-activa checked aria-label="Suscripción activa"><span class="toggle-slider"></span></label>
                            </div>
                            <div class="segmented-control plataforma-segmentado" role="radiogroup" aria-label="Suscripción inactiva" data-suscripcion-inactiva hidden>
                                <input type="radio" name="suscripcion_inactiva" id="suscripcionSuspendida" value="suspendida" checked><label for="suscripcionSuspendida"><i class="bi bi-pause-circle" aria-hidden="true"></i> Suspendida</label>
                                <input type="radio" name="suscripcion_inactiva" id="suscripcionCancelada" value="cancelada"><label for="suscripcionCancelada"><i class="bi bi-x-circle" aria-hidden="true"></i> Cancelada</label>
                            </div>
                            <p class="plataforma-ayuda">Sin suscripción activa, la clínica usa los límites del plan Gratuito.</p>
                        </div>
                        <p class="plataforma-ayuda" data-suscripcion-gratuito hidden>El plan Gratuito no tiene pago: no hay suscripción que cambiar.</p>
                        <label class="input-group" data-concesion-fin>Último día incluido de la concesión <input type="date" name="fecha_fin" min="0001-01-01" max="<?= $e(VigenciaSuscripcion::FECHA_FIN_MAX) ?>" maxlength="10" data-suscripcion-fin><small data-error-campo role="status"></small></label>
                        <fieldset class="plataforma-grupo"><legend>Ciclo</legend><div class="segmented-control"><input type="radio" name="ciclo" id="cicloMensual" value="mensual" checked><label for="cicloMensual">Mensual</label><input type="radio" name="ciclo" id="cicloAnual" value="anual"><label for="cicloAnual">Anual</label></div></fieldset>
                        <p class="plataforma-ayuda">El profesional requiere motivo y fecha fin. No registra un pago ni reactiva una clínica suspendida.</p>
                        <label class="input-group">Motivo <textarea name="motivo" rows="2" placeholder="Escribe el motivo o elige uno abajo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                        <div class="plataforma-sugerencias" role="group" aria-label="Motivos frecuentes">
                            <?php foreach ($motivosPlan as $motivo): ?><button type="button" class="plataforma-sugerencia" data-motivo-sugerido="<?= $e($motivo) ?>"><?= $e($motivo) ?></button><?php endforeach; ?>
                        </div>
                        <p class="plataforma-form__resultado" data-resultado role="status"></p>
                    </form>
                </div>
                <div class="users-modal__footer">
                    <button type="button" class="btn-modal-secondary" data-cerrar-dialogo>Cancelar</button>
                    <button type="submit" class="btn-modal-primary" form="plataformaPlanForm" data-guardar-plan><i class="bi bi-floppy" aria-hidden="true"></i> Guardar plan</button>
                </div>
            </div>

            <div class="plataforma-modal__panel" data-modal-panel="pagos" hidden>
                <div class="users-modal__body" data-finanzas-destino></div>
            </div>

            <div class="plataforma-modal__panel" data-modal-panel="estado" hidden>
                <div class="users-modal__body">
                    <p class="plataforma-nota" data-estado-nota hidden><i class="bi bi-info-circle" aria-hidden="true"></i><span data-estado-nota-texto></span></p>
                    <form class="plataforma-form" id="plataformaEstadoForm" data-plataforma-accion="plataforma_estado_ajax" data-estado-acciones>
                        <?php Csrf::field(); ?><input type="hidden" name="id_clinica">
                        <fieldset class="plataforma-grupo">
                            <legend>¿Qué quieres hacer?</legend>
                            <div class="plataforma-opciones plataforma-opciones--acciones">
                                <label class="plataforma-opcion" data-opcion-estado="suspendida">
                                    <input type="radio" name="estado" value="suspendida" required>
                                    <span class="plataforma-opcion__icono" aria-hidden="true"><i class="bi bi-pause-circle"></i></span>
                                    <span class="plataforma-opcion__texto"><strong>Suspender</strong><span>Deja de operar y su personal pierde el acceso hasta reactivarla. Sus datos se conservan.</span></span>
                                    <span class="plataforma-opcion__marca" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                </label>
                                <label class="plataforma-opcion" data-opcion-estado="activa">
                                    <input type="radio" name="estado" value="activa">
                                    <span class="plataforma-opcion__icono" aria-hidden="true"><i class="bi bi-play-circle"></i></span>
                                    <span class="plataforma-opcion__texto"><strong>Reactivar</strong><span>Vuelve a operar y su personal puede elegirla otra vez al entrar.</span></span>
                                    <span class="plataforma-opcion__marca" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                </label>
                                <?php // Lo único irreversible desde el panel: en rojo y con confirmación (decisión del 2026-10-10). ?>
                                <label class="plataforma-opcion plataforma-opcion--riesgo" data-opcion-estado="baja">
                                    <input type="radio" name="estado" value="baja">
                                    <span class="plataforma-opcion__icono" aria-hidden="true"><i class="bi bi-trash3"></i></span>
                                    <span class="plataforma-opcion__texto"><strong>Dar de baja</strong><span>Definitivo: no se puede reactivar desde este panel. Sus registros se conservan.</span></span>
                                    <span class="plataforma-opcion__marca" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                </label>
                            </div>
                        </fieldset>
                        <div class="plataforma-detalle" data-estado-detalle hidden>
                            <label class="input-group">Motivo <textarea name="motivo" rows="2" placeholder="Escribe el motivo o elige uno abajo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                            <div class="plataforma-sugerencias" role="group" aria-label="Motivos frecuentes">
                                <?php foreach ($motivosEstado as $motivo): ?><button type="button" class="plataforma-sugerencia" data-motivo-sugerido="<?= $e($motivo) ?>"><?= $e($motivo) ?></button><?php endforeach; ?>
                            </div>
                        </div>
                        <p class="plataforma-form__resultado" data-resultado role="status"></p>
                    </form>
                </div>
                <div class="users-modal__footer" data-pie-estado>
                    <button type="button" class="btn-modal-secondary" data-cerrar-dialogo>Cancelar</button>
                    <button type="submit" class="btn-modal-primary" form="plataformaEstadoForm" data-aplicar-estado disabled>Elige una acción</button>
                </div>
            </div>
        </div>
    </div>
</main>
</div></div>
</body>
</html>
