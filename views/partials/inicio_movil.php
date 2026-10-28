<?php
// RNF-16: piezas optativas; solo el Inicio que las habilita cambia su presentación móvil.
$hMovil = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
?>
<div class="inicio-movil" data-inicio-movil>
    <nav class="inicio-movil__tabs" role="tablist" aria-label="Resumen de la clínica">
        <?php foreach (($pestanasInicioMovil ?? ['agenda' => 'Agenda', 'pendientes' => 'Pendientes', 'mes' => 'Este mes']) as $claveMovil => $tituloMovil): ?>
        <button type="button" id="inicioMovilTab-<?= $hMovil($claveMovil) ?>" role="tab" data-inicio-tab="<?= $hMovil($claveMovil) ?>" aria-controls="inicioMovilPanel-<?= $hMovil($claveMovil) ?>" aria-selected="<?= $claveMovil === 'agenda' ? 'true' : 'false' ?>" tabindex="<?= $claveMovil === 'agenda' ? '0' : '-1' ?>">
            <?= $hMovil($tituloMovil) ?><?php if ($claveMovil === 'pendientes'): ?><span class="inicio-movil__conteo"><?= (int) ($conteoInicioMovil ?? 0) ?></span><?php endif; ?>
        </button>
        <?php endforeach; ?>
    </nav>
    <section class="inicio-movil__panel inicio-movil__panel--agenda" id="inicioMovilPanel-agenda" role="tabpanel" aria-labelledby="inicioMovilTab-agenda" data-inicio-panel="agenda">
        <?php $dialogoSemana = 'inicioMovilCalendario'; require __DIR__ . '/inicio_semana.php'; ?>
        <div class="inicio-selector-vet" data-inicio-selector-vet role="group" aria-label="Veterinario"></div>
        <div data-inicio-destino="agenda"></div>
    </section>
    <section class="inicio-movil__panel" id="inicioMovilPanel-pendientes" role="tabpanel" aria-labelledby="inicioMovilTab-pendientes" data-inicio-panel="pendientes" hidden><div data-inicio-destino="pendientes"></div></section>
    <section class="inicio-movil__panel inicio-movil__panel--mes" id="inicioMovilPanel-mes" role="tabpanel" aria-labelledby="inicioMovilTab-mes" data-inicio-panel="mes" hidden><div data-inicio-destino="mes"></div><div data-inicio-destino="plan"></div></section>
    <dialog class="zk-dialogo inicio-movil__calendario" id="inicioMovilCalendario" data-inicio-dialogo aria-labelledby="inicioTituloCal">
        <div class="inicio-movil__calendario-interior"><button type="button" class="zk-hoja__asa" data-inicio-cerrar aria-label="Cerrar calendario"></button><div data-inicio-calendario-destino></div><p class="inicio-movil__leyenda"><span class="inicio-cal__marca" aria-hidden="true"></span>Días con citas · toca un día para ver su agenda</p></div>
    </dialog>
</div>
