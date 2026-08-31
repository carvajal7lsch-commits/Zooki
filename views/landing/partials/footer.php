<?php
/**
 * Pie de página con cuatro columnas.
 */
?>
<footer class="lp-footer" id="contacto">
    <div class="lp-container">

        <div class="lp-footer__grid">

            <div class="lp-footer__brand">
                <div class="lp-footer__brand-logo">
                    <img src="img/icon_blue.png" alt="" class="lp-footer__brand-img"
                         width="36" height="37" loading="lazy" decoding="async">
                    <span class="lp-footer__brand-name">Zooki</span>
                </div>
                <p class="lp-footer__tagline">
                    Cuidamos a quienes amas. Sistema de gestión clínica para veterinarias,
                    creado para resolver un problema real de las clínicas pequeñas.
                </p>
                <ul class="lp-footer__meta">
                    <li><i class="ri-map-pin-line" aria-hidden="true"></i> Neiva, Huila &middot; Colombia</li>
                    <li><i class="ri-global-line" aria-hidden="true"></i> Aplicación web, sin instalación</li>
                </ul>
            </div>

            <div>
                <h3 class="lp-footer__title">Producto</h3>
                <nav class="lp-footer__links">
                    <a href="#modulos">Módulos</a>
                    <a href="#roles">Para tu equipo</a>
                    <a href="#como-funciona">Cómo funciona</a>
                    <a href="#pacientes">Pacientes</a>
                    <a href="#seguridad">Seguridad</a>
                </nav>
            </div>

            <div>
                <h3 class="lp-footer__title">Recursos</h3>
                <nav class="lp-footer__links">
                    <a href="docs/index.php" target="_blank" rel="noopener">Portal de documentación</a>
                    <a href="docs/index.php#ers" target="_blank" rel="noopener">Especificación de requisitos</a>
                    <a href="docs/index.php#mer" target="_blank" rel="noopener">Modelo de datos</a>
                    <a href="docs/index.php#readme" target="_blank" rel="noopener">Historial de versiones</a>
                    <a href="#preguntas">Preguntas frecuentes</a>
                </nav>
            </div>

            <div>
                <h3 class="lp-footer__title">Legal</h3>
                <nav class="lp-footer__links">
                    <a href="#seguridad">Seguridad del sistema</a>
                    <a href="index.php?action=privacidad">Política de privacidad</a>
                    <a href="index.php?action=terminos">Términos de uso</a>
                    <a href="index.php?action=cookies">Política de cookies</a>
                    <span class="lp-footer__soon">Manual de usuario <span>Pronto</span></span>
                </nav>
            </div>

        </div>

        <div class="lp-footer__bottom">
            <p class="lp-footer__credit">
                &copy; <?php echo date('Y'); ?> Zooki &middot; Desarrollado en el marco del programa
                Análisis y Desarrollo de Software &mdash; SENA, Centro Tecnológico de la Amazonia.
            </p>
        </div>

    </div>
</footer>
