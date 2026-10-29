<?php // RNF-16: misma espera accesible en los tres contextos; no contiene datos simulados. ?>
<div id="global-loader" class="carga-contenido" role="status" aria-live="polite" hidden>
    <span class="carga-contenido__mensaje">Cargando contenido…</span>
    <div class="carga-contenido__hoja" aria-hidden="true">
        <div class="carga-contenido__barra">
            <span class="carga-contenido__forma carga-contenido__forma--titulo"></span>
            <span class="carga-contenido__forma carga-contenido__forma--chip"></span>
        </div>
        <?php for ($filaCarga = 0; $filaCarga < 4; $filaCarga += 1): ?>
        <div class="carga-contenido__fila">
            <span class="carga-contenido__forma carga-contenido__forma--avatar"></span>
            <div class="carga-contenido__texto">
                <span class="carga-contenido__forma carga-contenido__forma--titulo"></span>
                <span class="carga-contenido__forma carga-contenido__forma--breve"></span>
            </div>
            <span class="carga-contenido__forma carga-contenido__forma--chip"></span>
        </div>
        <?php endfor; ?>
    </div>
</div>
