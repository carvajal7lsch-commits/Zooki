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
$nombreUsuario = (string) ($_SESSION['usuario_nombre'] ?? '');
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
</head>
<body>
<div class="dashboard-layout">
<nav class="pill-sidebar" aria-label="Plataforma"><a class="pill-logo" href="index.php?action=plataforma_inicio"><img class="pill-logo-img" src="img/icon_blue.png" alt="Zooki"></a><div class="pill-nav"><a class="pill-nav-item active" href="index.php?action=plataforma_inicio" title="Plataforma"><i class="fas fa-building" aria-hidden="true"></i></a></div><a class="pill-logout" href="index.php?action=logout" title="Cerrar sesión"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></a></nav>
<div class="main-content plataforma-contenido">
<header class="top-header plataforma-cabecera">
    <div class="plataforma-cabecera__titulo">
        <h1 class="top-header-title">Plataforma</h1>
    </div>
    <div class="plataforma-cabecera__cuenta">
        <?php include __DIR__ . '/../partials/contexto_actual.php'; ?>
        <div class="header-user" title="<?= $e($nombreUsuario) ?>">
            <div class="header-user__avatar" aria-hidden="true"><?= $e(PresentacionPlataforma::iniciales($nombreUsuario !== '' ? $nombreUsuario : 'Zooki')) ?></div>
            <div class="header-user__info">
                <span class="header-user__name"><?= $e(explode(' ', trim($nombreUsuario))[0] ?? '') ?></span>
                <span class="header-user__role">Super-administrador</span>
            </div>
        </div>
    </div>
</header>
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
        <div class="empty-state plataforma-vacio" data-sin-resultados <?= $clinicas ? 'hidden' : '' ?>>
            <div class="empty-icon"><i class="fas fa-search" aria-hidden="true"></i></div>
            <h3>Sin resultados</h3>
            <p>No hay clínicas que coincidan con la búsqueda o los filtros.</p>
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
                        <td><?= $e($p::plan($clinica['plan'], $clinica['suscripcion'])) ?></td>
                        <td><?= $e($p::administrador($clinica)) ?></td>
                        <td><?= $e($p::fecha($clinica['fecha_registro'])) ?></td>
                        <td><?php require __DIR__ . '/uso_plan.php'; ?></td>
                        <td><div class="table-actions"><?php require __DIR__ . '/boton_clinica.php'; ?></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="plataforma-panel" id="panelSoporte" role="tabpanel" aria-labelledby="pestanaSoporte" data-panel="soporte" hidden>
        <?php if (!$casos): ?>
        <div class="empty-state plataforma-vacio">
            <div class="empty-icon"><i class="bi bi-life-preserver" aria-hidden="true"></i></div>
            <h3>No hay casos de soporte</h3>
            <p>Los reportes de NIT duplicado aparecerán aquí.</p>
        </div>
        <?php endif; ?>
        <div class="plataforma-lista">
            <?php foreach ($casos as $caso): ?>
            <article class="plataforma-item">
                <div class="plataforma-item__cabeza">
                    <span class="plataforma-item__icono" aria-hidden="true"><i class="bi bi-life-preserver"></i></span>
                    <div class="plataforma-item__titulo">
                        <h3>Caso <?= (int) $caso['id_caso'] ?> · <?= $e(ucfirst(str_replace('_', ' ', $caso['tipo']))) ?></h3>
                        <span class="plataforma-item__meta"><?= $e($p::fecha($caso['fecha'])) ?></span>
                    </div>
                    <span class="tag-mini plataforma-estado <?= $caso['estado'] === 'abierto' ? 'plataforma-estado--activa' : '' ?>"><i class="bi <?= $caso['estado'] === 'abierto' ? 'bi-envelope-open' : 'bi-check2-circle' ?>" aria-hidden="true"></i> <?= $e(ucfirst($caso['estado'])) ?></span>
                </div>
                <p class="plataforma-item__texto"><?= $e($caso['descripcion']) ?></p>
                <?php if ($caso['estado'] === 'abierto'): ?>
                <details class="plataforma-item__accion">
                    <summary class="btn-modal-secondary">Cerrar caso</summary>
                    <form class="plataforma-form" data-plataforma-accion="plataforma_caso_ajax">
                        <?php Csrf::field(); ?>
                        <input type="hidden" name="id_caso" value="<?= (int) $caso['id_caso'] ?>">
                        <label class="input-group">Resultado <select name="estado" required><option value="resuelto">Resuelto</option><option value="descartado">Descartado</option></select></label>
                        <label class="input-group">Nota <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                        <div class="plataforma-form__pie"><p data-resultado role="status"></p><button type="submit" class="btn-modal-primary">Cerrar caso</button></div>
                    </form>
                </details>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="plataforma-panel" id="panelSeguridad" role="tabpanel" aria-labelledby="pestanaSeguridad" data-panel="seguridad" hidden>
        <?php if (!$ips): ?>
        <div class="empty-state plataforma-vacio">
            <div class="empty-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></div>
            <h3>No hay IP bloqueadas</h3>
            <p>Una IP se bloquea 15 minutos tras 20 intentos fallidos de inicio de sesión.</p>
        </div>
        <?php endif; ?>
        <div class="plataforma-lista">
            <?php foreach ($ips as $ip): ?>
            <article class="plataforma-item">
                <div class="plataforma-item__cabeza">
                    <span class="plataforma-item__icono" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
                    <div class="plataforma-item__titulo">
                        <h3><?= $e($ip['ip']) ?></h3>
                        <span class="plataforma-item__meta">Bloqueada hasta <?= $e(date('H:i', strtotime((string) $ip['bloqueado_hasta']) ?: time())) ?> · fallos desde <?= $e(date('H:i', strtotime((string) $ip['primer_intento']) ?: time())) ?></span>
                    </div>
                    <form class="plataforma-item__directa" data-plataforma-accion="plataforma_desbloquear_ajax">
                        <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>"><button type="submit" class="btn-modal-primary">Desbloquear</button><p data-resultado role="status"></p>
                    </form>
                </div>
                <ul class="plataforma-item__cuentas">
                    <?php foreach ($ip['cuentas'] as $cuenta): ?>
                    <li><strong><?= $e($cuenta['email'] ?? 'Cuenta no identificada') ?></strong> · <?= $e(implode(', ', array_column($cuenta['clinicas'], 'nombre')) ?: 'Sin clínica vinculada') ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="plataforma-item__nota">Las clínicas salen de los vínculos de las cuentas; una IP puede ser compartida.</p>
                <?php if ($ip['administradores']): ?>
                <details class="plataforma-item__accion">
                    <summary class="btn-modal-secondary">Enviar aviso</summary>
                    <form class="plataforma-form" data-plataforma-accion="plataforma_aviso_ajax">
                        <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>">
                        <label class="input-group">Administrador destinatario <select name="destinatario" required><option value="">Elige a quién avisar</option><?php foreach ($ip['administradores'] as $clave => $admin): ?><option value="<?= $e($clave) ?>"><?= $e($admin['clinica'] . ' · ' . $admin['email']) ?></option><?php endforeach; ?></select></label>
                        <label class="input-group">Texto propuesto <textarea name="texto" <?= ValidadorPlataforma::atributos() ?>>Se detectaron intentos fallidos de acceso desde la IP <?= $e($ip['ip']) ?>, bloqueada temporalmente hasta <?= $e($ip['bloqueado_hasta']) ?>. Revisa con tu equipo si fueron errores al entrar. Si no reconoces estos intentos, contacta a soporte de Zooki.</textarea><small data-error-campo role="status"></small></label>
                        <div class="plataforma-form__pie"><p data-resultado role="status">El aviso se envía solo al pulsar Enviar y al administrador elegido.</p><button type="submit" class="btn-modal-primary">Enviar aviso</button></div>
                    </form>
                </details>
                <?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="users-modal" id="gestionarClinica" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="clinicaModalTitulo">
        <div class="modal-content users-modal__panel plataforma-modal">
            <div class="modal-header">
                <div class="plataforma-modal__cabeza">
                    <h3 id="clinicaModalTitulo"><i class="bi bi-sliders" aria-hidden="true"></i><span data-clinica-titulo></span></h3>
                    <p class="modal-subtitle"><span class="tag-mini plataforma-estado" data-clinica-estado></span><span data-clinica-nit></span><span data-clinica-admin></span></p>
                </div>
                <button type="button" data-cerrar-dialogo class="close-modal" aria-label="Cerrar">×</button>
            </div>
            <div class="plataforma-modal__pestanas" role="tablist" aria-label="Gestionar la clínica">
                <button type="button" role="tab" class="plataforma-modal__pestana active" aria-selected="true" data-modal-pestana="plan">Plan y suscripción</button>
                <button type="button" role="tab" class="plataforma-modal__pestana" aria-selected="false" data-modal-pestana="estado">Estado</button>
            </div>
            <div class="users-modal__body">
                <form class="plataforma-form" data-plataforma-accion="plataforma_suscripcion_ajax" data-modal-panel="plan">
                    <?php Csrf::field(); ?><input type="hidden" name="id_clinica">
                    <div class="plataforma-form__fila">
                        <label class="input-group">Plan <select name="id_plan" required><?php foreach ($planes as $opcion): ?><option value="<?= (int) $opcion['id_plan'] ?>"><?= $e($opcion['nombre']) ?></option><?php endforeach; ?></select></label>
                        <label class="input-group">Suscripción <select name="estado" required><option value="activa">Activa</option><option value="suspendida">Suspendida</option><option value="cancelada">Cancelada</option></select></label>
                    </div>
                    <label class="input-group">Motivo <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                    <p class="plataforma-ayuda" data-aviso-plan hidden>La clínica debe estar verificada y no dada de baja para cambiar su plan.</p>
                    <div class="plataforma-form__pie"><p data-resultado role="status"></p><button type="submit" class="btn-modal-primary">Guardar plan</button></div>
                </form>
                <form class="plataforma-form" data-plataforma-accion="plataforma_estado_ajax" data-modal-panel="estado" hidden>
                    <?php Csrf::field(); ?><input type="hidden" name="id_clinica">
                    <label class="input-group">Nuevo estado <select name="estado" required><option value="suspendida">Suspender</option><option value="baja">Dar de baja</option><option value="activa">Reactivar</option></select></label>
                    <label class="input-group">Motivo <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                    <p class="plataforma-ayuda">Una clínica pendiente completa su verificación por correo. Dar de baja conserva sus registros y no permite reactivarla desde este panel.</p>
                    <div class="plataforma-form__pie"><p data-resultado role="status"></p><button type="submit" class="btn-modal-primary">Cambiar estado</button></div>
                </form>
            </div>
        </div>
    </div>
</main>
</div></div>
</body>
</html>
