<?php
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$s = $estadoSuscripcion;
$contrato = $s['plan_contratado'];
$precio = static fn ($p): string => $p === null ? 'No disponible' : '$' . number_format((int) $p, 0, ',', '.') . ' COP';
$fecha = static fn ($f): string => $f === null ? 'Sin fecha' : VigenciaSuscripcion::fecha($f)->format('d/m/Y H:i');
$etiquetas = ['sin_vigencia' => 'Sin vigencia de pago', 'al_dia' => 'Al día', 'gracia' => 'En gracia', 'congelada' => 'Beneficios congelados'];
?>
<section class="suscripcion" aria-labelledby="suscripcionTitulo" data-suscripcion-pantalla>
    <header class="suscripcion__cabecera">
        <div><p class="suscripcion__contexto"><?= $h($s['clinica']['nombre']) ?></p><h1 id="suscripcionTitulo">Suscripción</h1></div>
        <a class="suscripcion__volver" href="index.php?action=admin_panel">Volver al Inicio</a>
    </header>
    <div class="suscripcion__tarjetas">
        <article class="suscripcion__tarjeta">
            <h2>Tu plan</h2>
            <dl class="suscripcion__datos">
                <div><dt>Contratado</dt><dd><?= $h($contrato['nombre']) ?></dd></div>
                <div><dt>Efectivo hoy</dt><dd><?= $h($s['plan_efectivo']['nombre']) ?></dd></div>
                <div><dt>Estado</dt><dd data-suscripcion-estado><?= $h((int) $contrato['id_plan'] === (int) $s['plan_gratuito']['id_plan'] ? 'Gratuito · sin vencimiento' : $etiquetas[$s['situacion']]) ?></dd></div>
                <div><dt>Ciclo</dt><dd><?= $h($s['ciclo'] === 'anual' ? 'Anual' : 'Mensual') ?></dd></div>
                <div><dt>Fin del periodo</dt><dd><?= $h($fecha($s['periodo_fin'])) ?></dd></div>
                <div><dt>Congelación sin renovar</dt><dd><?= $h($fecha($s['gracia_hasta'])) ?></dd></div>
            </dl>
            <?php if ($s['legado_sin_fecha']): ?>
            <p class="suscripcion__nota" role="status">Tu plan fue asignado manualmente y aún no tiene vencimiento. Conserva sus condiciones; el super-administrador debe fijar la fecha antes de iniciar renovaciones.</p>
            <?php endif; ?>
            <?php if ($s['situacion'] === 'gracia'): ?>
            <p class="suscripcion__nota" role="status">Conservas los beneficios durante <?= (int) $s['clinica']['suscripcion_gracia_dias'] ?> días de gracia. Después se aplican los límites gratuitos a nuevas altas.</p>
            <?php endif; ?>
        </article>
        <article class="suscripcion__tarjeta">
            <h2>Profesional</h2>
            <?php
            $profesional = $profesionalSuscripcion;
            ?>
            <p class="suscripcion__precio"><?= $h($precio($profesional['precio_mensual'])) ?><span> al mes</span></p>
            <p><?= $h($precio($profesional['precio_anual'])) ?> al año<?= (int) $profesional['precio_anual'] === (int) $profesional['precio_mensual'] * 10 ? ' · paga 10, usa 12' : '' ?></p>
            <p>Sin límites de mascotas, citas ni cuentas de personal. Un cambio de precio conserva los periodos ya comprados.</p>
            <button class="suscripcion__pagar" type="button" disabled aria-describedby="suscripcionProximamente">Pagar/Renovar</button>
            <p id="suscripcionProximamente" class="suscripcion__ayuda">Disponible pronto</p>
        </article>
    </div>
    <p class="suscripcion__ayuda">El periodo termina a la fecha y hora indicadas. Tus datos e historia se conservan al congelar beneficios; las urgencias rojas siempre se atienden. Una suspensión de la clínica se gestiona aparte.</p>
</section>
