<?php
require_once __DIR__ . '/../../config/App.php';
require_once __DIR__ . '/../../helpers/ValidadorPlataforma.php';
$e = static fn ($valor) => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$etiquetas = ['pendiente_verificacion' => 'Pendiente de verificación', 'activa' => 'Activa', 'suspendida' => 'Suspendida', 'baja' => 'Dada de baja'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plataforma · Zooki</title>
    <link rel="icon" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css?v=e2">
    <link rel="stylesheet" href="css/dashboard.css?v=e2">
    <link rel="stylesheet" href="css/usuarios.css?v=e2">
    <link rel="stylesheet" href="css/pill-sidebar.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/plataforma.css?v=<?= $e(App::assetVersion()) ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <script src="js/interacciones.js?v=e2" defer></script>
    <script src="js/avisos.js?v=e2" defer></script>
    <script src="js/plataforma.js?v=e2" defer></script>
</head>
<body>
<div class="dashboard-layout">
<nav class="pill-sidebar" aria-label="Plataforma"><a class="pill-logo" href="index.php?action=plataforma_inicio"><img class="pill-logo-img" src="img/icon_blue.png" alt="Zooki"></a><div class="pill-nav"><a class="pill-nav-item active" href="index.php?action=plataforma_inicio" title="Plataforma"><i class="fas fa-building" aria-hidden="true"></i></a></div><a class="pill-logout" href="index.php?action=logout" title="Cerrar sesión"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></a></nav>
<div class="main-content plataforma-contenido">
<header class="top-header">
    <h1 class="top-header-title">Plataforma Zooki</h1>
    <nav aria-label="Cuenta"><span><?= $e($_SESSION['usuario_nombre'] ?? '') ?></span> <a href="index.php?action=seleccionar_contexto">Cambiar contexto</a> <a href="index.php?action=logout">Cerrar sesión</a></nav>
</header>
<main id="plataformaContenido" class="content-body users-page">
    <h1>Administración de la plataforma</h1>
    <p class="plataforma-ayuda">Gestión de clínicas y seguridad. Este panel no da acceso a datos clínicos.</p>
    <section aria-labelledby="clinicasTitulo">
        <div class="users-head"><h2 id="clinicasTitulo">Clínicas</h2><div class="view-toggle" role="group" aria-label="Tipo de vista"><button type="button" class="view-toggle-btn" data-vista="tarjetas" title="Tarjetas"><i class="bi bi-grid" aria-hidden="true"></i></button><button type="button" class="view-toggle-btn" data-vista="tabla" title="Tabla"><i class="bi bi-table" aria-hidden="true"></i></button></div></div>
        <form method="GET" class="plataforma-filtros users-head">
            <input type="hidden" name="action" value="plataforma_inicio">
            <label class="search-input">Nombre o NIT <input name="buscar" value="<?= $e($buscar) ?>" maxlength="<?= ValidadorPlataforma::BUSQUEDA_MAX ?>" data-caracteres="\p{L}\p{M}\p{N}\p{P}\p{S} " data-espacios="normalizar"></label>
            <label class="head-status-filter">Estado <select name="estado"><option value="">Todos</option><?php foreach ($etiquetas as $valor => $texto): ?><option value="<?= $e($valor) ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $e($texto) ?></option><?php endforeach; ?></select></label>
            <button type="submit" class="btn-modal-primary">Buscar</button>
        </form>
        <?php if ($error !== null): ?><p role="alert"><?= $e($error) ?></p><?php endif; ?>
        <?php if (!$clinicas): ?><p>No hay clínicas con estos filtros.</p><?php endif; ?>
        <div class="personal-grid view-grid" data-lista-vista="tarjetas">
            <?php foreach ($clinicas as $clinica): ?>
            <article class="person-card plataforma-ficha">
                <h3><?= $e($clinica['nombre']) ?></h3><p>NIT: <?= $e($clinica['nit']) ?></p><p class="plataforma-estado"><?= $e($etiquetas[$clinica['estado']]) ?></p>
                <dl><dt>Plan</dt><dd><?= $e($clinica['plan'] ?? 'Sin asignar') ?></dd><dt>Suscripción</dt><dd><?= $e($clinica['suscripcion'] ?? 'Sin suscripción') ?></dd><dt>Registro</dt><dd><?= $e($clinica['fecha_registro']) ?></dd><dt>Administrador</dt><dd><?= $e($clinica['administrador'] ?? $clinica['administrador_pendiente'] ?? 'Sin administrador activo') ?></dd></dl>
                <?php require __DIR__ . '/uso_plan.php'; ?>
                <?php require __DIR__ . '/boton_clinica.php'; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="table-responsive" data-lista-vista="tabla" hidden>
            <table class="users-table"><thead><tr><th>Clínica</th><th>NIT</th><th>Estado</th><th>Plan</th><th>Suscripción</th><th>Registro</th><th>Administrador</th><th>Uso del plan</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($clinicas as $clinica): ?>
                <tr><td><?= $e($clinica['nombre']) ?></td><td><?= $e($clinica['nit']) ?></td><td><?= $e($etiquetas[$clinica['estado']]) ?></td><td><?= $e($clinica['plan'] ?? 'Sin asignar') ?></td><td><?= $e($clinica['suscripcion'] ?? 'Sin suscripción') ?></td><td><?= $e($clinica['fecha_registro']) ?></td><td><?= $e($clinica['administrador'] ?? $clinica['administrador_pendiente'] ?? 'Sin administrador activo') ?></td><td><?php require __DIR__ . '/uso_plan.php'; ?></td><td>
                <?php require __DIR__ . '/boton_clinica.php'; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </section>
    <section aria-labelledby="soporteTitulo">
        <h2 id="soporteTitulo">Casos de soporte</h2>
        <?php if (!$casos): ?><p>No hay casos de soporte.</p><?php endif; ?>
        <?php foreach ($casos as $caso): ?>
        <article class="person-card plataforma-ficha">
            <h3>Caso <?= (int) $caso['id_caso'] ?> · <?= $e(str_replace('_', ' ', $caso['tipo'])) ?></h3>
            <p><?= $e($caso['fecha']) ?> · <?= $e($caso['estado']) ?></p><p><?= $e($caso['descripcion']) ?></p>
            <?php if ($caso['estado'] === 'abierto'): ?>
            <form data-plataforma-accion="plataforma_caso_ajax">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id_caso" value="<?= (int) $caso['id_caso'] ?>">
                <label class="input-group">Resultado <select name="estado" required><option value="resuelto">Resuelto</option><option value="descartado">Descartado</option></select></label>
                <label class="input-group">Nota <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                <button type="submit" class="btn-modal-primary">Cerrar caso</button><p data-resultado role="status"></p>
            </form>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </section>
    <section aria-labelledby="ipTitulo">
        <h2 id="ipTitulo">IP bloqueadas ahora</h2>
        <?php if (!$ips): ?><p>No hay IP bloqueadas.</p><?php endif; ?>
        <?php foreach ($ips as $ip): ?>
        <article class="person-card plataforma-ficha">
            <h3><?= $e($ip['ip']) ?></h3><p>Bloqueada hasta <?= $e($ip['bloqueado_hasta']) ?></p>
            <p>Fallos desde <?= $e($ip['primer_intento']) ?>. Las clínicas se obtienen de los vínculos de las cuentas; una IP puede ser compartida.</p>
            <ul><?php foreach ($ip['cuentas'] as $cuenta): ?><li><?= $e($cuenta['email'] ?? 'Cuenta no identificada') ?> — <?= $e(implode(', ', array_column($cuenta['clinicas'], 'nombre')) ?: 'Sin clínica vinculada') ?></li><?php endforeach; ?></ul>
            <form data-plataforma-accion="plataforma_desbloquear_ajax">
                <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>"><button type="submit" class="btn-modal-primary">Desbloquear</button><p data-resultado role="status"></p>
            </form>
            <?php if ($ip['administradores']): ?>
            <details><summary>Enviar aviso</summary>
                <form data-plataforma-accion="plataforma_aviso_ajax">
                    <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>">
                    <label class="input-group">Administrador destinatario <select name="destinatario" required><option value="">Elige a quién avisar</option><?php foreach ($ip['administradores'] as $clave => $admin): ?><option value="<?= $e($clave) ?>"><?= $e($admin['clinica'] . ' · ' . $admin['email']) ?></option><?php endforeach; ?></select></label>
                    <label class="input-group">Texto propuesto <textarea name="texto" <?= ValidadorPlataforma::atributos() ?>>Se detectaron intentos fallidos de acceso desde la IP <?= $e($ip['ip']) ?>, bloqueada temporalmente hasta <?= $e($ip['bloqueado_hasta']) ?>. Revisa con tu equipo si fueron errores al entrar. Si no reconoces estos intentos, contacta a soporte de Zooki.</textarea><small data-error-campo role="status"></small></label>
                    <p class="plataforma-ayuda">El aviso se envía solo al pulsar Enviar y al administrador elegido.</p><button type="submit" class="btn-modal-primary">Enviar aviso</button><p data-resultado role="status"></p>
                </form>
            </details>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </section>
    <div class="users-modal" id="gestionarClinica" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="clinicaModalTitulo"><div class="modal-content users-modal__panel"><div class="modal-header">
        <button type="button" data-cerrar-dialogo class="close-modal" aria-label="Cerrar">×</button><h3 id="clinicaModalTitulo" data-clinica-titulo></h3></div><div class="users-modal__body">
        <form data-plataforma-accion="plataforma_suscripcion_ajax">
            <h3>Plan y suscripción</h3><?php Csrf::field(); ?><input type="hidden" name="id_clinica">
            <label class="input-group">Plan <select name="id_plan" required><?php foreach ($planes as $plan): ?><option value="<?= (int) $plan['id_plan'] ?>"><?= $e($plan['nombre']) ?></option><?php endforeach; ?></select></label>
            <label class="input-group">Suscripción <select name="estado" required><option value="activa">Activa</option><option value="suspendida">Suspendida</option><option value="cancelada">Cancelada</option></select></label>
            <label class="input-group">Motivo <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
            <button type="submit" class="btn-modal-primary">Guardar plan y suscripción</button><p data-resultado role="status"></p>
        </form>
        <form data-plataforma-accion="plataforma_estado_ajax">
            <h3>Estado de la clínica</h3><?php Csrf::field(); ?><input type="hidden" name="id_clinica">
            <label class="input-group">Nuevo estado <select name="estado" required><option value="suspendida">Suspender</option><option value="baja">Dar de baja</option><option value="activa">Reactivar</option></select></label>
            <label class="input-group">Motivo <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
            <button type="submit" class="btn-modal-primary">Cambiar estado</button><p data-resultado role="status"></p>
        </form>
        <p class="plataforma-ayuda">Una clínica pendiente completa su verificación por correo. Dar de baja conserva sus registros y no permite reactivarla desde este panel.</p>
    </div></div></div>
</main>
</div></div>
</body>
</html>
