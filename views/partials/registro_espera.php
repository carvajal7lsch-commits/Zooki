<?php
/**
 * M0.1 — Ventana de espera de los registros (propietario y clínica): primero
 * la carga del envío y después «Revisa tu correo». La maneja
 * public/js/registro-cuenta.js; sin JavaScript, la página la muestra ya en
 * «listo» cuando el servidor aceptó el envío.
 *
 * Variables: $esperaId, $esperaTexto (qué recibe el titular) y, opcionales,
 * $esperaListo (bool) y $esperaMensaje (respuesta del servidor).
 * RE-T.13.2: el texto es el mismo exista o no la cuenta.
 */
$esperaListo = $esperaListo ?? false;
$esperaMensaje = $esperaMensaje ?? 'Revisa tu correo para confirmar la solicitud.';
$h = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<div class="registro-espera" id="<?= $h($esperaId) ?>" <?= $esperaListo ? '' : 'hidden' ?>>
    <div data-espera-estado="cargando" role="status" aria-live="polite" <?= $esperaListo ? 'hidden' : '' ?>>
        <div class="registro-espera-icono">
            <span class="registro-espera-spinner" aria-hidden="true"></span>
            <i class="ri-send-plane-2-line" aria-hidden="true"></i>
        </div>
        <h3>Enviando tu registro…</h3>
        <p class="registro-espera-texto">Estamos revisando los datos. No cierres esta página.</p>
    </div>
    <div data-espera-estado="listo" <?= $esperaListo ? '' : 'hidden' ?>>
        <div class="registro-espera-icono">
            <i class="ri-mail-send-line" aria-hidden="true"></i>
        </div>
        <h3>Revisa tu correo</h3>
        <p class="registro-espera-texto"><?= $h($esperaTexto) ?></p>
        <p class="registro-espera-estado" data-espera-mensaje role="status" aria-live="polite"><?= $h($esperaMensaje) ?></p>
        <p class="registro-espera-ayuda">Si no lo ves, revisa la carpeta de spam. El enlace vence en 24 horas.</p>
        <a class="btn-secondary" href="index.php?action=login">Volver al inicio de sesión</a>
    </div>
</div>
