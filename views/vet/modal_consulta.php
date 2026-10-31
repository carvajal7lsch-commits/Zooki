<!-- MODAL NUEVA CONSULTA -->
<?php
// M2 C5a: los límites y avisos son los de ValidadorClinico; el comportamiento
// está en js/consulta-formulario.js (sin eventos en línea).
// M2 C8 (H5, H9): la identidad de Zooki (css/consulta-clinica.css), secciones
// claras, pie siempre a la vista y los mismos campos que Atención.
require_once __DIR__ . '/../../helpers/ValidadorClinico.php';
require_once __DIR__ . '/../../helpers/ValidadorTratamiento.php';
$avisoConsulta = fn(string $campo): string => htmlspecialchars(ValidadorClinico::AVISOS_CONSULTA[$campo], ENT_QUOTES, 'UTF-8');
$ayudaSigno = fn(string $campo): string => htmlspecialchars(ValidadorClinico::ayudaSigno($campo), ENT_QUOTES, 'UTF-8');
$claseCampo = 'consulta-campo';
$claseEtiqueta = 'consulta-campo__etiqueta';
$prefijoConsulta = 'modalConsulta';
?>
<div id="modalConsulta" class="users-modal consulta-modal" data-paciente-compacto data-ui-accion="cerrar-consulta" data-ui-fondo>
    <div class="modal-content users-modal__panel modal-lg consulta-modal__panel" role="dialog" aria-modal="true" aria-labelledby="consultaModalTitulo">
        <div class="modal-header consulta-modal__cabecera">
            <div class="consulta-modal__paciente">
                <span class="avatar-iniciales consulta-avatar" id="consultationPetInicial" aria-hidden="true"></span>
                <div>
                    <p class="consulta-modal__sobretitulo">Nueva consulta</p>
                    <h3 class="consulta-modal__titulo" id="consultaModalTitulo"><span id="consultationPetName"></span></h3>
                    <p class="paciente-compacto__especie" id="consultaPacienteEspecie"></p>
                </div>
            </div>
            <button type="button" class="close-modal" data-ui-accion="cerrar-consulta" aria-label="Cerrar">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="consulta-modal__ficha">
            <p class="paciente-compacto__datos" id="consultaPacienteDatos"></p>
            <div class="paciente-compacto__controles">
                <button type="button" class="paciente-compacto__alertas" data-paciente-alertas aria-expanded="false" aria-controls="consultaAlertas">Cargando alertas</button>
                <button type="button" class="paciente-compacto__ficha" data-paciente-ficha aria-expanded="false" aria-controls="consultaFicha">Ver ficha <i class="fas fa-chevron-down" aria-hidden="true"></i></button>
            </div>
            <p class="paciente-compacto__ficha-contenido" id="consultaFicha"></p>
            <div class="paciente-compacto__lista" id="consultaAlertas">
                <section data-alertas-consulta data-alertas-medicas data-alertas-chips aria-label="Alergias y alertas"></section>
            </div>
        </div>
        <form id="formConsulta" class="consulta-formulario" autocomplete="off" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="id_mascota" id="consultation_id_mascota">

            <div class="users-modal__body consulta-modal__cuerpo">
                <?php $consultaEnAtencion = false; ?>
                <?php include __DIR__ . '/../partials/consulta_pasos.php'; ?>
            </div>
            <div class="users-modal__footer consulta-modal__pie">
                <button type="button" class="btn-modal-secondary" data-ui-accion="cerrar-consulta">Cancelar</button>
                <button type="button" class="btn-modal-secondary" data-consulta-anterior>Anterior</button>
                <button type="button" class="btn-modal-primary" data-consulta-siguiente>Siguiente: Diagnóstico y tratamiento</button>
                <button type="submit" class="btn-modal-primary" data-consulta-finalizar hidden><i class="fas fa-floppy-disk" aria-hidden="true"></i> Guardar consulta</button>
            </div>
        </form>
    </div>
</div>
