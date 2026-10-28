<?php
/**
 * Calendario del mes de los Inicios del veterinario (HU-6.1, RE-6.1.8) y del
 * administrador (HU-6.2, RE-6.2.8): hoy resaltado, el día elegido y un punto
 * en los días con citas (las del veterinario o las de la clínica). Lo pintan
 * el primer render y las acciones AJAX del Inicio al cambiar de mes.
 * Recibe $mes de ResumenPanel::mes().
 */
$hCal = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="inicio-cabecera" data-inicio-mes-actual="<?= $hCal($mes['mes']) ?>">
    <h2 class="inicio-titulo" id="inicioTituloCal" aria-live="polite"><?= $hCal($mes['titulo']) ?></h2>
    <div class="inicio-cal__flechas">
        <button type="button" class="inicio-cal__flecha" data-inicio-mes="<?= $hCal($mes['anterior']) ?>" aria-label="Mes anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
        <button type="button" class="inicio-cal__flecha" data-inicio-mes="<?= $hCal($mes['siguiente']) ?>" aria-label="Mes siguiente"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
    </div>
</div>
<div class="inicio-cal__semana" aria-hidden="true"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
<div class="inicio-cal__dias">
    <?php foreach ($mes['celdas'] as $celda): ?>
        <?php if ($celda === null): ?>
            <span class="inicio-cal__vacio" aria-hidden="true"></span>
        <?php else: ?>
            <button type="button"
                    class="inicio-cal__dia<?= $celda['pasado'] ? ' is-pasado' : '' ?><?= $celda['hoy'] ? ' is-hoy' : '' ?><?= $celda['elegido'] ? ' is-elegido' : '' ?>"
                    data-inicio-fecha="<?= $hCal($celda['fecha']) ?>"
                    aria-label="<?= $hCal($celda['etiqueta']) ?>"
                    aria-pressed="<?= $celda['elegido'] ? 'true' : 'false' ?>"<?= $celda['hoy'] ? ' aria-current="date"' : '' ?>>
                <?= (int) $celda['numero'] ?><?php if ($celda['citas']): ?><span class="inicio-cal__marca" aria-hidden="true"></span><?php endif; ?>
            </button>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
