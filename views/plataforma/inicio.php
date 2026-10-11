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
    <link rel="stylesheet" href="css/plataforma.css?v=<?= $e(App::assetVersion()) ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>
    <script src="js/interacciones.js?v=e1" defer></script>
    <script src="js/avisos.js?v=e1" defer></script>
    <script src="js/plataforma.js?v=e1" defer></script>
</head>
<body>
<header class="plataforma-cabecera">
    <a href="index.php?action=plataforma_inicio"><img src="img/icon_blue.png" alt=""> Plataforma Zooki</a>
    <nav aria-label="Cuenta"><span><?= $e($_SESSION['usuario_nombre'] ?? '') ?></span> <a href="index.php?action=seleccionar_contexto">Cambiar contexto</a> <a href="index.php?action=logout">Cerrar sesión</a></nav>
</header>
<main id="plataformaContenido">
    <h1>Administración de la plataforma</h1>
    <p class="plataforma-ayuda">Gestión de clínicas y seguridad. Este panel no da acceso a datos clínicos.</p>
    <section aria-labelledby="clinicasTitulo">
        <div class="plataforma-titulo"><h2 id="clinicasTitulo">Clínicas</h2><button type="button" data-vista="tarjetas">Tarjetas</button><button type="button" data-vista="tabla">Tabla</button></div>
        <form method="GET" class="plataforma-filtros">
            <input type="hidden" name="action" value="plataforma_inicio">
            <label>Nombre o NIT <input name="buscar" value="<?= $e($buscar) ?>" maxlength="<?= ValidadorPlataforma::BUSQUEDA_MAX ?>" data-caracteres="\p{L}\p{M}\p{N}\p{P}\p{S} " data-espacios="normalizar"></label>
            <label>Estado <select name="estado"><option value="">Todos</option><?php foreach ($etiquetas as $valor => $texto): ?><option value="<?= $e($valor) ?>" <?= $estado === $valor ? 'selected' : '' ?>><?= $e($texto) ?></option><?php endforeach; ?></select></label>
            <button type="submit">Buscar</button>
        </form>
        <?php if ($error !== null): ?><p role="alert"><?= $e($error) ?></p><?php endif; ?>
        <?php if (!$clinicas): ?><p>No hay clínicas con estos filtros.</p><?php endif; ?>
        <div class="plataforma-tarjetas" data-lista-vista="tarjetas">
            <?php foreach ($clinicas as $clinica): ?>
            <article class="plataforma-tarjeta">
                <h3><?= $e($clinica['nombre']) ?></h3><p>NIT: <?= $e($clinica['nit']) ?></p><p class="plataforma-estado"><?= $e($etiquetas[$clinica['estado']]) ?></p>
                <dl><dt>Plan</dt><dd><?= $e($clinica['plan'] ?? 'Sin asignar') ?></dd><dt>Suscripción</dt><dd><?= $e($clinica['suscripcion'] ?? 'Sin suscripción') ?></dd><dt>Registro</dt><dd><?= $e($clinica['fecha_registro']) ?></dd><dt>Administrador</dt><dd><?= $e($clinica['administrador'] ?? $clinica['administrador_pendiente'] ?? 'Sin administrador activo') ?></dd></dl>
                <?php require __DIR__ . '/boton_clinica.php'; ?>
            </article>
            <?php endforeach; ?>
        </div>
        <div class="plataforma-tabla" data-lista-vista="tabla" hidden>
            <table><thead><tr><th>Clínica</th><th>NIT</th><th>Estado</th><th>Plan</th><th>Suscripción</th><th>Registro</th><th>Administrador</th><th>Acciones</th></tr></thead><tbody>
            <?php foreach ($clinicas as $clinica): ?>
                <tr><td><?= $e($clinica['nombre']) ?></td><td><?= $e($clinica['nit']) ?></td><td><?= $e($etiquetas[$clinica['estado']]) ?></td><td><?= $e($clinica['plan'] ?? 'Sin asignar') ?></td><td><?= $e($clinica['suscripcion'] ?? 'Sin suscripción') ?></td><td><?= $e($clinica['fecha_registro']) ?></td><td><?= $e($clinica['administrador'] ?? $clinica['administrador_pendiente'] ?? 'Sin administrador activo') ?></td><td><?php require __DIR__ . '/boton_clinica.php'; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>
    </section>
    <section aria-labelledby="soporteTitulo">
        <h2 id="soporteTitulo">Casos de soporte</h2>
        <?php if (!$casos): ?><p>No hay casos de soporte.</p><?php endif; ?>
        <?php foreach ($casos as $caso): ?>
        <article class="plataforma-tarjeta">
            <h3>Caso <?= (int) $caso['id_caso'] ?> · <?= $e(str_replace('_', ' ', $caso['tipo'])) ?></h3>
            <p><?= $e($caso['fecha']) ?> · <?= $e($caso['estado']) ?></p><p><?= $e($caso['descripcion']) ?></p>
            <?php if ($caso['estado'] === 'abierto'): ?>
            <form data-plataforma-accion="plataforma_caso_ajax">
                <?php Csrf::field(); ?>
                <input type="hidden" name="id_caso" value="<?= (int) $caso['id_caso'] ?>">
                <label>Resultado <select name="estado" required><option value="resuelto">Resuelto</option><option value="descartado">Descartado</option></select></label>
                <label>Nota <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
                <button type="submit">Cerrar caso</button><p data-resultado role="status"></p>
            </form>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </section>
    <section aria-labelledby="ipTitulo">
        <h2 id="ipTitulo">IP bloqueadas ahora</h2>
        <?php if (!$ips): ?><p>No hay IP bloqueadas.</p><?php endif; ?>
        <?php foreach ($ips as $ip): ?>
        <article class="plataforma-tarjeta">
            <h3><?= $e($ip['ip']) ?></h3><p>Bloqueada hasta <?= $e($ip['bloqueado_hasta']) ?></p>
            <p>Fallos desde <?= $e($ip['primer_intento']) ?>. Las clínicas se obtienen de los vínculos de las cuentas; una IP puede ser compartida.</p>
            <ul><?php foreach ($ip['cuentas'] as $cuenta): ?><li><?= $e($cuenta['email'] ?? 'Cuenta no identificada') ?> — <?= $e(implode(', ', array_column($cuenta['clinicas'], 'nombre')) ?: 'Sin clínica vinculada') ?></li><?php endforeach; ?></ul>
            <form data-plataforma-accion="plataforma_desbloquear_ajax" data-confirmacion="Desbloquear esta IP permite volver a intentar el acceso. Los contadores de las cuentas se conservan.">
                <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>"><button type="submit">Desbloquear</button><p data-resultado role="status"></p>
            </form>
            <?php if ($ip['administradores']): ?>
            <details><summary>Enviar aviso</summary>
                <form data-plataforma-accion="plataforma_aviso_ajax">
                    <?php Csrf::field(); ?><input type="hidden" name="ip" value="<?= $e($ip['ip']) ?>">
                    <label>Administrador destinatario <select name="destinatario" required><option value="">Elige a quién avisar</option><?php foreach ($ip['administradores'] as $clave => $admin): ?><option value="<?= $e($clave) ?>"><?= $e($admin['clinica'] . ' · ' . $admin['email']) ?></option><?php endforeach; ?></select></label>
                    <label>Texto propuesto <textarea name="texto" <?= ValidadorPlataforma::atributos() ?>>Se detectaron intentos fallidos de acceso desde la IP <?= $e($ip['ip']) ?>, bloqueada temporalmente hasta <?= $e($ip['bloqueado_hasta']) ?>. Revisa con tu equipo si fueron errores al entrar. Si no reconoces estos intentos, contacta a soporte de Zooki.</textarea><small data-error-campo role="status"></small></label>
                    <p class="plataforma-ayuda">El aviso se envía solo al pulsar Enviar y al administrador elegido.</p><button type="submit">Enviar aviso</button><p data-resultado role="status"></p>
                </form>
            </details>
            <?php endif; ?>
        </article>
        <?php endforeach; ?>
    </section>
    <dialog id="gestionarClinica">
        <button type="button" data-cerrar-dialogo aria-label="Cerrar">Cerrar</button><h2 data-clinica-titulo></h2>
        <form data-plataforma-accion="plataforma_suscripcion_ajax">
            <h3>Plan y suscripción</h3><?php Csrf::field(); ?><input type="hidden" name="id_clinica">
            <label>Plan <select name="id_plan" required><?php foreach ($planes as $plan): ?><option value="<?= (int) $plan['id_plan'] ?>"><?= $e($plan['nombre']) ?></option><?php endforeach; ?></select></label>
            <label>Suscripción <select name="estado" required><option value="activa">Activa</option><option value="suspendida">Suspendida</option><option value="cancelada">Cancelada</option></select></label>
            <label>Motivo <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
            <button type="submit">Guardar plan y suscripción</button><p data-resultado role="status"></p>
        </form>
        <form data-plataforma-accion="plataforma_estado_ajax">
            <h3>Estado de la clínica</h3><?php Csrf::field(); ?><input type="hidden" name="id_clinica">
            <label>Nuevo estado <select name="estado" required><option value="suspendida">Suspender</option><option value="baja">Dar de baja</option><option value="activa">Reactivar</option></select></label>
            <label>Motivo <textarea name="motivo" <?= ValidadorPlataforma::atributos() ?>></textarea><small data-error-campo role="status"></small></label>
            <button type="submit">Cambiar estado</button><p data-resultado role="status"></p>
        </form>
        <p class="plataforma-ayuda">Una clínica pendiente completa su verificación por correo. Dar de baja conserva sus registros y no permite reactivarla desde este panel.</p>
    </dialog>
</main>
</body>
</html>
