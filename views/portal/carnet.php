<?php
/** RN-503: misma tarjeta para previsualización e impresión, salida escapada. */
$escaparCarnet = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<?php if (!$carnet['disponible']): ?>
    <p class="carnet-aviso" role="status"><?= $escaparCarnet($carnet['motivo']) ?></p>
<?php else:
    $datosCarnet = $carnet['datos'];
    $fotoCarnet = !empty($datosCarnet['foto']) ? 'uploads/mascotas/' . rawurlencode(basename($datosCarnet['foto'])) : null;
?>
    <div class="carnet-contenido" data-carnet-datos data-id-mascota="<?= (int) $idMascota ?>" data-token-visto="<?= $escaparCarnet($carnet['token']) ?>" data-enlace="<?= $escaparCarnet($carnet['enlace']) ?>">
        <article class="carnet-tarjeta" aria-label="Carnet de emergencia de <?= $escaparCarnet($datosCarnet['nombre']) ?>">
            <header class="carnet-identidad">
                <?php if ($fotoCarnet !== null): ?>
                    <img class="carnet-foto" src="<?= $escaparCarnet($fotoCarnet) ?>" alt="Foto de <?= $escaparCarnet($datosCarnet['nombre']) ?>">
                <?php else: ?>
                    <span class="carnet-foto carnet-foto--iniciales" aria-hidden="true"><?= $escaparCarnet(mb_strtoupper(mb_substr($datosCarnet['nombre'] ?? '', 0, 1))) ?></span>
                <?php endif; ?>
                <div><small>Zooki · Carnet de emergencia</small><h3><?= $escaparCarnet($datosCarnet['nombre']) ?></h3><p><?= $escaparCarnet($datosCarnet['especie']) ?> · <?= $escaparCarnet($datosCarnet['raza'] ?: 'Raza sin confirmar') ?></p></div>
            </header>
            <img class="carnet-qr" src="index.php?action=portal_carnet_svg&amp;id_mascota=<?= (int) $idMascota ?>&amp;v=<?= $escaparCarnet(hash('sha256', $carnet['token'])) ?>" alt="QR de emergencia" width="156" height="156">
            <section class="carnet-alertas" aria-label="Alergias y alertas activas">
                <?php foreach ($datosCarnet['alertas'] as $alertaCarnet): ?>
                    <span class="carnet-chip<?= $alertaCarnet['tipo'] === 'alergia' ? ' carnet-chip--alergia' : '' ?>"><?= $alertaCarnet['tipo'] === 'alergia' ? 'Alergia: ' : '' ?><?= $escaparCarnet($alertaCarnet['descripcion']) ?></span>
                <?php endforeach; ?>
                <?php if (!$datosCarnet['alertas']): ?><p>Sin alergias ni alertas activas registradas.</p><?php endif; ?>
            </section>
            <section class="carnet-contactos" aria-label="Contactos de emergencia">
                <p><strong>Propietario:</strong> <?= $escaparCarnet($datosCarnet['telefono_propietario'] ?: 'Sin teléfono registrado') ?></p>
                <?php foreach ($datosCarnet['clinicas'] as $clinicaCarnet): ?>
                    <p><strong><?= $escaparCarnet($clinicaCarnet['nombre']) ?>:</strong> <?= $escaparCarnet($clinicaCarnet['telefono'] ?: 'Sin teléfono registrado') ?></p>
                <?php endforeach; ?>
            </section>
            <section class="carnet-vacunas" aria-label="Vacunas vigentes">
                <h4>Vacunas vigentes</h4>
                <p class="carnet-nota-vacunas">Las vacunas sin próxima dosis tienen vigencia sin confirmar y no aparecen en esta lista.</p>
                <?php if (!$datosCarnet['vacunas']): ?><p>Sin vacunas con vigencia confirmada.</p><?php endif; ?>
                <?php foreach ($datosCarnet['vacunas'] as $vacunaCarnet): ?>
                    <p><strong><?= $escaparCarnet($vacunaCarnet['nombre_vacuna']) ?></strong> · <?= $escaparCarnet($vacunaCarnet['clinica']) ?> · Próxima dosis: <?= $escaparCarnet($vacunaCarnet['fecha_proxima_dosis']) ?></p>
                <?php endforeach; ?>
            </section>
        </article>
        <?php if (empty($impresionCarnet)): ?>
            <div class="carnet-acciones">
                <a class="portal-boton-secundario" href="index.php?action=portal_imprimir_carnet&amp;id_mascota=<?= (int) $idMascota ?>" target="_blank" rel="noopener">Imprimir</a>
                <button type="button" class="portal-boton-secundario" data-carnet-compartir>Compartir</button>
                <button type="button" class="portal-boton-secundario" data-carnet-regenerar>Regenerar QR</button>
            </div>
            <p class="carnet-ayuda">Al regenerar el QR, los carnets impresos y enlaces anteriores dejarán de servir.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
