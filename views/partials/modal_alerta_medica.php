<?php
/**
 * HU-2.1, RE-2.1.5 — Registrar una alergia o alerta médica. Lo abren las secciones
 * «Alergias y alertas» (js/alertas-medicas.js) en Pacientes, Consultas y Atención.
 * Los límites y los tipos de nodo enlazables salen de ValidadorAlertaMedica, igual que en el servidor.
 */
require_once __DIR__ . '/../../helpers/ValidadorAlertaMedica.php';
$tiposAlerta = ValidadorAlertaMedica::TIPOS;
$iconosAlerta = ['alergia' => 'fa-hand-dots', 'condicion_cronica' => 'fa-heart-pulse', 'medicacion_continua' => 'fa-pills', 'otra' => 'fa-circle-info'];
$nodosPorTipo = htmlspecialchars(json_encode(ValidadorAlertaMedica::NODOS_POR_TIPO), ENT_QUOTES, 'UTF-8');
$alertaEnAtencion = ($_GET['action'] ?? '') === 'vet_atencion';
?>
<div class="users-modal alertas-medicas__modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="alertaMedicaTitulo" data-alerta-modal<?= $alertaEnAtencion ? ' data-alerta-presentacion="compacta"' : '' ?>>
    <div class="modal-content users-modal__panel alertas-medicas__panel">
        <div class="modal-header">
            <div>
                <h3 id="alertaMedicaTitulo"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i> <?= $alertaEnAtencion ? 'Nueva alergia o alerta' : 'Registrar alergia o alerta' ?></h3>
                <p class="alertas-medicas__subtitulo" data-alerta-mascota></p>
            </div>
            <button type="button" class="close-modal" aria-label="Cerrar" data-alerta-cerrar><i class="fas fa-times" aria-hidden="true"></i></button>
        </div>
        <form class="alertas-medicas__form" novalidate data-alerta-form data-nodos-por-tipo="<?= $nodosPorTipo ?>">
            <div class="users-modal__body">
                <input type="hidden" name="id_mascota" data-alerta-campo="id_mascota">
                <input type="hidden" name="nodo" data-alerta-campo="nodo">
                <input type="hidden" name="seleccion_estructurada" value="1">
                <fieldset class="alertas-medicas__tipos">
                    <legend>Tipo</legend>
                    <div class="alertas-medicas__opciones">
                        <?php foreach ($tiposAlerta as $valor => $nombre): ?>
                            <label class="alertas-medicas__opcion">
                                <input type="radio" name="tipo" value="<?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?>" required data-alerta-tipo<?= $alertaEnAtencion && $valor === 'alergia' ? ' checked' : '' ?>>
                                <i class="fas <?= $iconosAlerta[$valor] ?>" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small class="cuenta-validacion" aria-live="polite" data-alerta-aviso="tipo"></small>
                </fieldset>

                <div class="input-group alertas-medicas__zona-nodo" data-alerta-zona-nodo hidden>
                    <label for="alertaBuscarNodo" data-alerta-nombre-titulo>Alérgico/a a</label>
                    <div class="input-wrapper" data-alerta-buscar>
                        <i class="fas fa-search field-icon" aria-hidden="true"></i>
                        <input type="search" id="alertaBuscarNodo" name="nombre_alerta" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="alertaSugerenciasNodo" placeholder="Busca un medicamento o sustancia" maxlength="<?= ValidadorAlertaMedica::BUSQUEDA_MAX ?>" data-caracteres="<?= ValidadorAlertaMedica::CARACTERES ?>" data-espacios="normalizar" data-alerta-campo="busqueda">
                    </div>
                    <div class="alertas-medicas__nodo-elegido" hidden data-alerta-nodo-elegido></div>
                    <div class="alertas-medicas__sugerencias" id="alertaSugerenciasNodo" role="listbox" aria-label="Medicamentos, sustancias o condiciones" hidden data-alerta-sugerencias></div>
                    <small class="cuenta-validacion" aria-live="polite" data-alerta-aviso="busqueda"></small>
                    <label class="alertas-medicas__no-encuentro"><input type="checkbox" name="no_encuentro_nombre" value="1" data-alerta-no-encuentro><span>No encuentro el nombre</span></label>
                    <small class="alertas-medicas__nota" data-alerta-nombre-aviso>Escribe para buscar y elige una sugerencia.</small>
                </div>

                <div class="input-group">
                    <label for="alertaDescripcion" data-alerta-descripcion-titulo>Reacción o detalles</label>
                    <?php if ($alertaEnAtencion): ?><div class="alertas-medicas__entrada-descripcion"><?php endif; ?>
                    <textarea id="alertaDescripcion" name="descripcion" rows="3" placeholder="Ej.: ronchas en la piel" <?= ValidadorAlertaMedica::atributosDescripcion() ?> data-descripcion-max="<?= ValidadorAlertaMedica::DESCRIPCION_MAX ?>" data-alerta-campo="descripcion"></textarea>
                    <?php if ($alertaEnAtencion): ?><span class="alertas-medicas__contador-texto" data-alerta-contador>0/<?= ValidadorAlertaMedica::DESCRIPCION_MAX ?></span></div><?php endif; ?>
                    <div class="alertas-medicas__pie-campo">
                        <small class="cuenta-validacion" aria-live="polite" data-alerta-aviso="descripcion"></small>
                        <?php if (!$alertaEnAtencion): ?><span class="alertas-medicas__contador-texto" data-alerta-contador>0/<?= ValidadorAlertaMedica::DESCRIPCION_MAX ?></span><?php endif; ?>
                    </div>
                </div>

                <p class="alertas-medicas__resultado" role="status" data-alerta-resultado></p>
            </div>
            <div class="users-modal__footer">
                <button type="button" class="btn-modal-secondary" data-alerta-cerrar>Cancelar</button>
                <button type="submit" class="btn-modal-primary" data-alerta-enviar><i class="fas fa-floppy-disk" aria-hidden="true"></i> Registrar</button>
            </div>
        </form>
    </div>
</div>
