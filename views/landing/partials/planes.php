<?php
/**
 * Planes y precios. Todo valor sale del catálogo `planes` (RN-003, RN-008):
 * nada de precios ni límites escritos a mano. Un límite NULL es ilimitado.
 * Espera del contexto: $planGratuito, $planProfesional y $ofertaLanzamiento.
 *
 * El conmutador Mensual/Anual cambia `data-ciclo` en la sección (landing.js)
 * y el CSS muestra el precio de ese ciclo; sin JS queda el mensual.
 * La sección debe caber en una pantalla de 1366×768: el conmutador va dentro
 * de la tarjeta Profesional, que es la única cuyo precio cambia.
 */
$lp_pesos = static fn (int $valor): string => '$' . number_format($valor, 0, ',', '.');

$lp_limite = static function ($limite, string $singular, string $plural, string $sinLimite): string {
    if ($limite === null) {
        return $sinLimite;
    }
    $cantidad = (int) $limite;
    return number_format($cantidad, 0, ',', '.') . ' ' . ($cantidad === 1 ? $singular : $plural);
};

$lp_limites = static fn (array $plan): array => [
    $lp_limite($plan['limite_personal'], 'cuenta de personal', 'cuentas de personal', 'Personal ilimitado'),
    $lp_limite($plan['limite_mascotas'], 'mascota vinculada', 'mascotas vinculadas', 'Mascotas ilimitadas'),
    $lp_limite($plan['limite_citas_mes'], 'cita al mes', 'citas al mes', 'Citas ilimitadas'),
];

$lp_mensual = (int) $planProfesional['precio_mensual'];
$lp_anual   = (int) $planProfesional['precio_anual'];
?>
<section class="lp-section lp-section--alt lp-planes" id="planes" data-ciclo="mensual">
    <div class="lp-container">

        <div class="lp-section-head lp-reveal">
            <span class="lp-eyebrow">Planes</span>
            <h2 class="lp-section-title">Empieza gratis. Crece cuando lo necesites.</h2>
            <p class="lp-section-desc">
                El plan gratuito trae todo el sistema, incluidos los grafos. El profesional quita los límites.
            </p>
        </div>

        <div class="lp-planes__grid">

            <article class="lp-bento__card lp-planes__card lp-reveal">
                <header>
                    <h3 class="lp-bento__title"><?php echo htmlspecialchars($planGratuito['nombre'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="lp-bento__text">Para arrancar con pacientes reales</p>
                </header>
                <div>
                    <p class="lp-planes__precio"><?php echo $lp_pesos((int) $planGratuito['precio_mensual']); ?></p>
                </div>
                <a href="index.php?action=registro_clinica" class="lp-btn lp-btn--ghost lp-btn--block">Registra tu clínica</a>
                <ul class="lp-roles__list">
                    <?php foreach ($lp_limites($planGratuito) as $lp_texto): ?>
                    <li><i class="ri-check-line" aria-hidden="true"></i> <?php echo $lp_texto; ?></li>
                    <?php endforeach; ?>
                    <li><i class="ri-check-line" aria-hidden="true"></i> Copiloto clínico y agenda inteligente</li>
                    <li><i class="ri-check-line" aria-hidden="true"></i> Portal del propietario y carnet QR</li>
                </ul>
            </article>

            <article class="lp-bento__card lp-planes__card lp-planes__card--destacada lp-reveal" data-delay="1">
                <header class="lp-planes__cabecera">
                    <div>
                        <h3 class="lp-bento__title"><?php echo htmlspecialchars($planProfesional['nombre'], ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p class="lp-bento__text">Para la clínica que ya va en serio</p>
                    </div>
                    <div class="lp-roles__tabs lp-planes__ciclo" role="group" aria-label="Ciclo de pago">
                        <button type="button" class="lp-roles__tab lp-planes__opcion is-active" data-ciclo-opcion="mensual" aria-pressed="true">
                            Mensual
                        </button>
                        <button type="button" class="lp-roles__tab lp-planes__opcion" data-ciclo-opcion="anual" aria-pressed="false">
                            Anual
                        </button>
                    </div>
                </header>
                <div>
                    <div class="lp-planes__mensual">
                        <div class="lp-planes__fila">
                            <p class="lp-planes__precio">
                                <?php echo $lp_pesos($lp_mensual); ?> <span class="lp-planes__periodo">COP / mes</span>
                            </p>
                            <?php if ($ofertaLanzamiento): ?>
                            <span class="lp-planes__insignia">Lanzamiento · Paga 1, lleva 2</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($ofertaLanzamiento): ?>
                        <p class="lp-planes__nota">Tu primer pago mensual te da 2 meses</p>
                        <?php endif; ?>
                    </div>
                    <div class="lp-planes__anual">
                        <div class="lp-planes__fila">
                            <p class="lp-planes__precio">
                                <?php echo $lp_pesos($lp_anual); ?> <span class="lp-planes__periodo">COP / año</span>
                            </p>
                            <?php if ($lp_anual === $lp_mensual * 10): ?>
                            <span class="lp-planes__insignia">Ahorras 2 meses</span>
                            <?php endif; ?>
                        </div>
                        <p class="lp-planes__nota">Equivale a <?php echo $lp_pesos(intdiv($lp_anual, 12)); ?> al mes</p>
                    </div>
                </div>
                <a href="index.php?action=registro_clinica" class="lp-btn lp-btn--primary lp-btn--block">Empezar con Profesional</a>
                <ul class="lp-roles__list">
                    <?php foreach ($lp_limites($planProfesional) as $lp_texto): ?>
                    <li><i class="ri-check-line" aria-hidden="true"></i> <?php echo $lp_texto; ?></li>
                    <?php endforeach; ?>
                    <li><i class="ri-check-line" aria-hidden="true"></i> Todo lo del plan gratuito</li>
                </ul>
            </article>

        </div>

    </div>
</section>
