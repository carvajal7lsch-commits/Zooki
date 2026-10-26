<?php
$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$s = $estadoSuscripcion;
$contrato = $s['plan_contratado'];
$profesional = $profesionalSuscripcion;
$precio = static fn ($p): string => $p === null ? 'No disponible' : '$' . number_format((int) $p, 0, ',', '.') . ' COP';
$monto = static fn ($p): string => $p === null ? 'No disponible' : '$' . number_format((int) $p, 0, ',', '.');
$fecha = static fn ($f): string => $f === null ? 'Sin fecha' : VigenciaSuscripcion::fecha($f)->format('d/m/Y');
$incluida = static fn ($f): string => VigenciaSuscripcion::fechaIncluida($f);
$etiquetas = ['sin_vigencia' => 'Sin vigencia de pago', 'al_dia' => 'Al día', 'gracia' => 'En gracia', 'congelada' => 'Beneficios congelados'];
$gratuito = (int) $contrato['id_plan'] === (int) $s['plan_gratuito']['id_plan'];
// Rediseño (2026-10-26): rojo y ámbar solo para lo que pide atención; al día va en azul.
$tonoEstado = $gratuito ? 'neutro' : (['al_dia' => 'ok', 'gracia' => 'aviso', 'congelada' => 'alerta', 'sin_vigencia' => 'neutro'][$s['situacion']] ?? 'neutro');
$pagos = $historialPagos['filas'];
$estadosPago = ['pendiente' => ['Pendiente', 'aviso'], 'confirmado' => ['Confirmado', 'ok'], 'cancelado' => ['Cancelado', 'neutro'], 'revision' => ['En revisión', 'aviso']];
// La paginación vuelve a la pestaña Historial (#historial) y conserva el origen del botón volver.
$paginaHistorial = static fn (int $pagina): string => 'index.php?' . http_build_query(array_filter(['action' => 'admin_suscripcion', 'desde' => $origenSuscripcion, 'pagina' => $pagina])) . '#historial';
$medioTexto = $fuentePago ? ($fuentePago['tipo'] === 'NEQUI' ? 'Nequi' : (($fuentePago['marca'] ?: 'Tarjeta') . ($fuentePago['ultimos_cuatro'] ? ' · termina en ' . $fuentePago['ultimos_cuatro'] : ''))) : 'No tienes un medio guardado.';
$automaticoActivo = $fuentePago && (int) $fuentePago['cobro_automatico'] === 1;
$anualConAhorro = (int) $profesional['precio_anual'] === (int) $profesional['precio_mensual'] * 10;
?>
<?php // Rediseño v2 (aprobado 2026-10-26): «Mi plan» e «Historial de pagos» en pestañas; pagar y el medio de pago en ventanas. ?>
<section class="suscripcion" aria-label="Suscripción" data-suscripcion-pantalla data-pago-habilitado="<?= !empty($pagoDisponible) ? '1' : '0' ?>" data-version="<?= (int) ($s['suscripcion']['version_periodo'] ?? 0) ?>">

    <div class="suscripcion-pestanas" role="tablist" aria-label="Secciones de la suscripción" data-suscripcion-pestanas>
        <button type="button" role="tab" class="suscripcion-pestanas__pestana" id="suscripcionPestanaPlan" aria-controls="suscripcionPlan" aria-selected="true" data-suscripcion-pestana="plan">
            <span class="suscripcion-pestanas__icono" aria-hidden="true"><i class="fas fa-crown"></i></span>Mi plan
        </button>
        <button type="button" role="tab" class="suscripcion-pestanas__pestana" id="suscripcionPestanaHistorial" aria-controls="suscripcionHistorial" aria-selected="false" tabindex="-1" data-suscripcion-pestana="historial">
            <span class="suscripcion-pestanas__icono" aria-hidden="true"><i class="fas fa-list-ul"></i></span>Historial<span class="suscripcion-pestanas__largo"> de pagos</span>
            <span class="suscripcion-pestanas__conteo"><?= count($pagos) ?><?= $historialPagos['mas'] ? '+' : '' ?></span>
        </button>
    </div>

    <?php if ($retornoPago !== null): ?>
    <p class="suscripcion__aviso" role="status">Tu pago está <?= $h($retornoPago['pago']['estado']) ?>. Tu plan se activa en cuanto Wompi confirme el pago; puede tardar unos minutos. Puedes consultar su estado.</p>
    <?php endif; ?>

    <div class="suscripcion__panel" id="suscripcionPlan" role="tabpanel" aria-labelledby="suscripcionPestanaPlan" data-suscripcion-panel="plan">
        <section class="suscripcion__tarjeta" aria-labelledby="suscripcionTitulo">
            <div class="suscripcion__plan">
                <div class="suscripcion__plan-cabecera">
                    <span class="suscripcion__plan-icono" aria-hidden="true"><i class="fas fa-crown"></i></span>
                    <div class="suscripcion__plan-nombre">
                        <p class="suscripcion__sobretitulo">Tu plan</p>
                        <h2 id="suscripcionTitulo"><?= $h($contrato['nombre']) ?></h2>
                    </div>
                    <span class="suscripcion-chip suscripcion-chip--<?= $tonoEstado ?>" data-suscripcion-estado><?= $h($gratuito ? 'Gratuito · sin vencimiento' : $etiquetas[$s['situacion']]) ?></span>
                    <?php if (!$gratuito): ?>
                    <span class="suscripcion-chip suscripcion-chip--neutro suscripcion-chip--ciclo"><?= $h($s['ciclo'] === 'anual' ? 'Anual' : 'Mensual') ?></span>
                    <?php endif; ?>
                </div>
                <dl class="suscripcion__franja">
                    <div><dt>Último día del periodo</dt><dd data-suscripcion-fin><?= $h($incluida($s['periodo_fin'])) ?></dd></div>
                    <div><dt>Beneficios sin renovar hasta</dt><dd><?= $h($incluida($s['gracia_hasta'])) ?></dd></div>
                    <div><dt>Efectivo hoy</dt><dd data-suscripcion-plan><?= $h($s['plan_efectivo']['nombre']) ?></dd></div>
                    <div class="suscripcion__franja-ciclo"><dt>Ciclo</dt><dd><?= $h($s['ciclo'] === 'anual' ? 'Anual' : 'Mensual') ?></dd></div>
                </dl>
                <?php if ($s['legado_sin_fecha']): ?>
                <p class="suscripcion__aviso" role="status">Tu plan fue asignado manualmente y aún no tiene vencimiento. Conserva sus condiciones; el super-administrador debe fijar la fecha antes de iniciar renovaciones.</p>
                <?php endif; ?>
                <?php if ($s['situacion'] === 'gracia'): ?>
                <p class="suscripcion__aviso" role="status">Conservas los beneficios durante <?= (int) $s['clinica']['suscripcion_gracia_dias'] ?> días de gracia. Después se aplican los límites gratuitos a nuevas altas.</p>
                <?php endif; ?>
                <ul class="suscripcion__beneficios" aria-label="Incluye el plan Profesional">
                    <li><i class="fas fa-check" aria-hidden="true"></i>Mascotas sin límite</li>
                    <li><i class="fas fa-check" aria-hidden="true"></i>Citas sin límite</li>
                    <li><i class="fas fa-check" aria-hidden="true"></i>Cuentas de personal sin límite</li>
                </ul>
            </div>

            <?php // Las dos acciones de la pantalla; cada una abre su ventana (hoja inferior en el celular). ?>
            <div class="suscripcion__acciones">
                <div class="suscripcion__accion">
                    <div class="suscripcion__accion-texto">
                        <p class="suscripcion__accion-titulo">Renovar el plan</p>
                        <p class="suscripcion__ayuda"><?= $h($monto($profesional['precio_mensual'])) ?> al mes o <?= $h($monto($profesional['precio_anual'])) ?> al año<?= $anualConAhorro ? ' (paga 10, usa 12)' : '' ?></p>
                    </div>
                    <button type="button" class="suscripcion__abrir-renovar" data-abrir-dialogo="suscripcionRenovarDialogo" aria-haspopup="dialog"><i class="fas fa-credit-card" aria-hidden="true"></i>Pagar o renovar</button>
                </div>

                <div class="suscripcion__fuente" data-fuente-pantalla data-automatico-habilitado="<?= $automaticoDisponible ? '1' : '0' ?>">
                    <button type="button" class="suscripcion__accion suscripcion__accion--medio<?= $fuentePago ? ' suscripcion__accion--guardado' : '' ?>" data-abrir-dialogo="suscripcionMedioDialogo" aria-haspopup="dialog">
                        <span class="suscripcion__medio-icono" aria-hidden="true"><i class="fas fa-credit-card"></i></span>
                        <span class="suscripcion__accion-texto">
                            <span class="suscripcion__accion-titulo">Medio de pago</span>
                            <span class="suscripcion__ayuda"><span data-fuente-resumen><?= $h($medioTexto) ?></span> <span class="suscripcion__medio-estado" data-fuente-estado><?= $h($fuentePago ? ($fuentePago['estado'] === 'disponible' ? 'Disponible' : 'No disponible') : '') ?></span></span>
                            <span class="suscripcion-chip suscripcion-chip--<?= $automaticoActivo ? 'ok' : 'neutro' ?> suscripcion__automatico-chip" data-automatico-estado><?= $h($requiereAceptacion ? 'El precio cambió. Acepta el nuevo importe antes de renovar automáticamente.' : ($automaticoActivo ? 'Cobro mensual autorizado.' : 'Cobro automático desactivado.')) ?></span>
                        </span>
                        <span class="suscripcion__gestionar">Gestionar <i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                    </button>
                    <p class="suscripcion__ayuda suscripcion__mensaje-tarjeta" role="status" aria-live="polite" data-fuente-mensaje-tarjeta></p>

                    <?php // Medio de pago: estado, retirar o quitar y, al agregar o cambiar, las aceptaciones de Wompi en tres pasos. ?>
                    <dialog class="zk-dialogo suscripcion-medio" id="suscripcionMedioDialogo" aria-labelledby="suscripcionMedioDialogoTitulo">
                        <div class="zk-dialogo__form">
                            <div class="zk-dialogo__head">
                                <span class="zk-dialogo__icono" aria-hidden="true"><i class="fas fa-credit-card"></i></span>
                                <div>
                                    <h3 id="suscripcionMedioDialogoTitulo">Medio de pago</h3>
                                    <p>Tarjeta o Nequi, guardado por Wompi</p>
                                </div>
                                <button type="button" class="zk-dialogo__cerrar" aria-label="Cerrar" data-cerrar-dialogo><i class="fas fa-times" aria-hidden="true"></i></button>
                            </div>
                            <div class="zk-dialogo__cuerpo">
                                <div class="suscripcion-medio__resumen">
                                    <p class="suscripcion__ayuda"><strong data-importe-mensual><?= $h($precio($profesional['precio_mensual'])) ?></strong> al vencer cada mes si autorizas el cobro automático. El anual se paga con el botón.</p>
                                    <p class="suscripcion__ayuda"><?= $h($mensajeAutomatico) ?></p>
                                    <div class="suscripcion-medio__botones">
                                        <button class="suscripcion__boton-suave" type="button" data-fuente-preparar <?= empty($pagoDisponible) ? 'disabled' : '' ?>><i class="fas fa-<?= $fuentePago ? 'pen' : 'plus' ?>" aria-hidden="true"></i><?= $fuentePago ? 'Cambiar medio' : 'Agregar medio' ?></button>
                                        <button class="suscripcion__boton-texto" type="button" data-automatico-retirar <?= !$automaticoActivo ? 'hidden' : '' ?>>Retirar cobro automático</button>
                                        <button class="suscripcion__boton-texto suscripcion__boton-texto--peligro" type="button" data-fuente-quitar <?= !$fuentePago ? 'hidden' : '' ?>>Quitar medio de pago</button>
                                    </div>
                                </div>
                                <div class="suscripcion-medio__pasos" data-fuente-condiciones hidden>
                                    <fieldset class="suscripcion-medio__paso">
                                        <legend><span class="suscripcion-medio__numero" aria-hidden="true">1</span>Acepta las condiciones de Wompi</legend>
                                        <label class="suscripcion-medio__casilla"><input type="checkbox" data-acepta-politica> <span>He leído y acepto <a data-contrato-politica target="_blank" rel="noopener noreferrer">la política de Wompi</a>.</span></label>
                                        <label class="suscripcion-medio__casilla"><input type="checkbox" data-acepta-datos> <span>He leído y acepto <a data-contrato-datos target="_blank" rel="noopener noreferrer">el tratamiento de datos de Wompi</a>.</span></label>
                                        <p data-fuente-validacion class="suscripcion__ayuda" aria-live="polite"></p>
                                    </fieldset>
                                    <div class="suscripcion-medio__paso">
                                        <p class="suscripcion-medio__titulo"><span class="suscripcion-medio__numero" aria-hidden="true">2</span>Guarda tu tarjeta o Nequi</p>
                                        <button class="suscripcion__boton-suave suscripcion__boton-suave--ancho" type="button" data-fuente-guardar disabled><i class="fas fa-lock" aria-hidden="true"></i>Abrir Wompi para guardarlo</button>
                                        <button class="suscripcion__boton-suave suscripcion__boton-suave--ancho" type="button" data-nequi-comprobar hidden>Comprobar Nequi</button>
                                        <p class="suscripcion__ayuda">Guardar el medio no autoriza ningún cobro.</p>
                                    </div>
                                    <div class="suscripcion-medio__paso">
                                        <p class="suscripcion-medio__titulo"><span class="suscripcion-medio__numero suscripcion-medio__numero--opcional" aria-hidden="true">3</span>Cobro automático mensual <span class="suscripcion__opcional">(opcional)</span></p>
                                        <label class="suscripcion-medio__casilla suscripcion-medio__casilla--caja"><input type="checkbox" data-acepta-mensual> <span>Autorizo un cargo de <strong data-mandato-importe><?= $h($precio($profesional['precio_mensual'])) ?></strong> cada mes al vencer mi plan. Puedo retirar esta autorización.</span></label>
                                    </div>
                                </div>
                                <p class="suscripcion__ayuda" role="status" aria-live="polite" data-fuente-mensaje></p>
                            </div>
                            <div class="zk-dialogo__pie">
                                <button type="button" class="zk-btn zk-btn--sec" data-cerrar-dialogo>Cerrar</button>
                                <button type="button" class="zk-btn" data-automatico-autorizar disabled>Autorizar cobro mensual</button>
                            </div>
                        </div>
                    </dialog>
                </div>
            </div>
        </section>
        <p class="suscripcion__ayuda suscripcion__pie">Las fechas son el último día incluido. Tus datos se conservan aunque se congelen los beneficios y las urgencias rojas siempre se atienden. Una suspensión de la clínica se gestiona aparte.</p>
    </div>

    <div class="suscripcion__panel" id="suscripcionHistorial" role="tabpanel" aria-labelledby="suscripcionPestanaHistorial" data-suscripcion-panel="historial">
        <section class="suscripcion__tarjeta suscripcion__historial" aria-label="Historial de pagos">
            <?php if (!$pagos): ?>
            <p class="suscripcion__vacio">Aún no hay pagos registrados.</p>
            <?php else: ?>
            <table class="suscripcion__tabla">
                <thead><tr><th scope="col">Fecha</th><th scope="col">Periodo</th><th scope="col">Ciclo</th><th scope="col">Referencia</th><th scope="col" class="suscripcion__valor">Valor</th><th scope="col">Estado</th></tr></thead>
                <tbody>
                <?php foreach ($pagos as $pago): ?>
                    <?php [$estadoTexto, $estadoTono] = $estadosPago[$pago['estado']] ?? [$pago['estado'], 'neutro']; ?>
                    <tr class="suscripcion__pago">
                        <td data-etiqueta="Fecha"><?= $h($fecha($pago['creado_en'])) ?></td>
                        <td data-etiqueta="Periodo"><?= $pago['inicio'] !== null ? $h($fecha($pago['inicio'])) . ' – ' . $h($incluida($pago['fin'])) : '—' ?></td>
                        <td data-etiqueta="Ciclo"><?= $h($pago['ciclo'] === 'anual' ? 'Anual' : 'Mensual') ?></td>
                        <td data-etiqueta="Referencia"><span class="suscripcion__referencia" title="<?= $h($pago['referencia']) ?>"><?= $h($pago['referencia']) ?></span></td>
                        <td data-etiqueta="Valor" class="suscripcion__valor"><?= $h($monto(intdiv((int) $pago['total_centavos'], 100))) ?></td>
                        <td data-etiqueta="Estado">
                            <span class="suscripcion-chip suscripcion-chip--<?= $estadoTono ?>"><?= $h($estadoTexto) ?></span>
                            <span class="suscripcion__intento">Wompi: <?= $h($pago['estado_intento']) ?></span>
                            <?php if ($pago['codigo_error'] !== null): ?><span class="suscripcion__intento">En revisión: <?= $h($pago['codigo_error']) ?></span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <div class="suscripcion__historial-pie">
                <p class="suscripcion__ayuda">Las concesiones manuales no son pagos y no aparecen aquí.</p>
                <nav class="suscripcion__paginacion" aria-label="Páginas del historial">
                    <?php if ($historialPagos['pagina'] > 1): ?><a class="suscripcion__pagina" href="<?= $h($paginaHistorial((int) $historialPagos['pagina'] - 1)) ?>" aria-label="Página anterior"><i class="fas fa-chevron-left" aria-hidden="true"></i></a><?php endif; ?>
                    <?php if ($historialPagos['mas']): ?><a class="suscripcion__pagina" href="<?= $h($paginaHistorial((int) $historialPagos['pagina'] + 1)) ?>" aria-label="Página siguiente"><i class="fas fa-chevron-right" aria-hidden="true"></i></a><?php endif; ?>
                </nav>
            </div>
        </section>
    </div>

    <?php // Pagar o renovar: misma lógica de suscripcion.js; el pago abre Wompi a pantalla completa. ?>
    <dialog class="zk-dialogo suscripcion-renovar" id="suscripcionRenovarDialogo" aria-labelledby="suscripcionRenovarTitulo">
        <div class="zk-dialogo__form">
            <div class="zk-dialogo__head">
                <span class="zk-dialogo__icono" aria-hidden="true"><i class="fas fa-crown"></i></span>
                <div>
                    <h3 id="suscripcionRenovarTitulo">Pagar o renovar</h3>
                    <p>Plan Profesional</p>
                </div>
                <button type="button" class="zk-dialogo__cerrar" aria-label="Cerrar" data-cerrar-dialogo><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <div class="zk-dialogo__cuerpo suscripcion-renovar__cuerpo">
                <fieldset class="suscripcion__ciclos">
                    <legend class="visualmente-oculto">Elige el periodo que vas a pagar</legend>
                    <label class="suscripcion__ciclo">
                        <input type="radio" name="suscripcion_ciclo" value="mensual" checked data-total="<?= $h($monto($profesional['precio_mensual'])) ?>" data-unidad="COP / mes">
                        <span class="suscripcion__ciclo-nombre">Mensual</span>
                        <span class="suscripcion__ciclo-precio"><?= $h($monto($profesional['precio_mensual'])) ?></span>
                    </label>
                    <label class="suscripcion__ciclo">
                        <input type="radio" name="suscripcion_ciclo" value="anual" data-total="<?= $h($monto($profesional['precio_anual'])) ?>" data-unidad="COP / año">
                        <span class="suscripcion__ciclo-nombre">Anual<?php if ($anualConAhorro): ?> <span class="suscripcion__ahorro">paga 10, usa 12</span><?php endif; ?></span>
                        <span class="suscripcion__ciclo-precio"><?= $h($monto($profesional['precio_anual'])) ?></span>
                    </label>
                </fieldset>
                <p class="suscripcion__total"><span>Total a pagar</span><span><strong data-suscripcion-total><?= $h($monto($profesional['precio_mensual'])) ?></strong> <span data-suscripcion-unidad>COP / mes</span></span></p>
                <p class="suscripcion__ayuda">Un cambio de precio conserva los periodos ya comprados. Wompi se abre a pantalla completa y al terminar vuelves aquí.</p>
                <p id="suscripcionMensaje" class="suscripcion__ayuda suscripcion__mensaje" role="status" aria-live="polite"><?= $h($mensajePago ?? 'Disponible pronto') ?></p>
            </div>
            <div class="zk-dialogo__pie suscripcion-renovar__pie">
                <button class="suscripcion__consultar" type="button"><i class="fas fa-sync-alt" aria-hidden="true"></i>Consultar estado</button>
                <button class="suscripcion__pagar" type="button" <?= empty($pagoDisponible) ? 'disabled' : '' ?> aria-describedby="suscripcionMensaje"><i class="fas fa-lock" aria-hidden="true"></i>Pagar con Wompi</button>
            </div>
        </div>
    </dialog>
</section>
