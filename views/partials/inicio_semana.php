<?php
// RNF-16: la misma franja se usa en Inicio y Agenda; cada pantalla aporta sus eventos.
$dialogoSemana = $dialogoSemana ?? 'inicioMovilCalendario';
?>
<div class="inicio-semana" data-inicio-semana>
    <div class="inicio-semana__cabecera">
        <button type="button" class="inicio-semana__mes" data-inicio-calendario-abrir aria-haspopup="dialog" aria-controls="<?= htmlspecialchars($dialogoSemana, ENT_QUOTES, 'UTF-8') ?>"><span data-inicio-semana-mes></span><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
        <button type="button" class="inicio-semana__flecha" data-inicio-semana-paso="-1" aria-label="Semana anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
        <button type="button" class="inicio-semana__flecha" data-inicio-semana-paso="1" aria-label="Semana siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
    </div>
    <div class="inicio-semana__dias" data-inicio-semana-dias role="group" aria-label="Días de la semana"></div>
</div>
