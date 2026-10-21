<?php
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/ValidadorCuenta.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../helpers/ValidadorMascota.php';
$esVetUrgencia = Contexto::rolActivo() === Roles::VETERINARIO;
$reglasUrgencia = ['nombreMax'=>ValidadorCuenta::NOMBRE_MAX,'nombreCaracteres'=>ValidadorCuenta::NOMBRE_CARACTERES,'telefonoMin'=>ValidadorTelefono::MIN,'telefonoMax'=>ValidadorTelefono::MAX,'mascotaMax'=>ValidadorMascota::NOMBRE_MAX];
?>
<details data-urgencias-agenda data-vet="<?= $esVetUrgencia ? '1' : '0' ?>" data-clinica="<?= (int)Contexto::clinicaActiva() ?>" data-reglas="<?= htmlspecialchars(json_encode($reglasUrgencia),ENT_QUOTES,'UTF-8') ?>">
    <summary>Urgencias presentes y fichas por completar</summary>
    <p>El aviso remoto no acredita llegada. La prioridad y la interrupción las decide el veterinario.</p>
    <div data-urgencias-csrf hidden><?php Csrf::field(); ?></div>
    <?php if ($esVetUrgencia): ?>
    <form data-ingreso-rojo novalidate class="perfil-card">
        <h3>Registrar un rojo presencial</h3>
        <label>Identidad <select name="identidad"><option value="conocida">Cuenta y ficha verificadas</option><option value="provisional">Relación no verificada o sin cuenta</option></select></label>
        <label data-identidad-conocida>Mascota <select name="id_mascota" required></select><span data-error-campo="id_mascota" aria-live="polite"></span></label>
        <fieldset data-identidad-provisional hidden disabled>
            <legend>Ficha provisional; sin atribuir propiedad ni crear cuenta</legend>
            <label>Nombre de mascota (opcional) <input name="nombre_mascota" <?= ValidadorMascota::atributosHtml('nombre') ?>><span data-error-campo="nombre_mascota" aria-live="polite"></span></label>
            <label>Nombre del acompañante (opcional) <input name="nombre_acompanante" <?= ValidadorCuenta::atributosHtml('nombre_completo') ?>><span data-error-campo="nombre_acompanante" aria-live="polite"></span></label>
            <label>Teléfono del acompañante (opcional) <input name="telefono_acompanante" <?= ValidadorTelefono::atributosHtml() ?>><span data-error-campo="telefono_acompanante" aria-live="polite"></span></label>
            <p>El acompañante no acredita titularidad. No se exige contacto para atender.</p>
        </fieldset>
        <label>Motivo clínico de prioridad roja <input name="motivo" maxlength="255" data-caracteres="\P{C}" data-espacios="normalizar" required><span data-error-campo="motivo" aria-live="polite"></span></label>
        <button type="submit" class="btn-primary">Registrar llegada roja</button>
        <p data-aviso role="status"></p>
    </form>
    <?php endif; ?>
    <button type="button" data-urgencias-actualizar class="btn-secondary">Actualizar urgencias</button>
    <p data-urgencias-aviso role="status"></p>
    <div data-rojos-lista></div>
    <h3>Identidad pendiente</h3>
    <p>Usa cuenta, vínculo o confirmación existentes del titular. Un ID, el contacto o ser acompañante no acreditan propiedad.</p>
    <div data-ingresos-lista></div>
    <template data-completar-ficha>
        <fieldset>
            <legend>Completar la misma ficha</legend>
            <label>Nombre <input name="nombre" <?= ValidadorMascota::atributosHtml('nombre') ?> required><span data-error-campo="nombre"></span></label>
            <label>Especie <select name="especie" required></select><span data-error-campo="especie"></span></label>
            <label>Raza <select name="raza" required></select><span data-error-campo="raza"></span></label>
            <label>Sexo <select name="sexo" required><option value="">Elige</option><option>Macho</option><option>Hembra</option></select><span data-error-campo="sexo"></span></label>
            <label>Peso (kg) <input name="peso" <?= ValidadorMascota::atributosHtml('peso') ?> required><span data-error-campo="peso"></span></label>
            <label>Nacimiento (opcional) <input name="fecha_nacimiento" type="date"><span data-error-campo="fecha_nacimiento"></span></label>
        </fieldset>
    </template>
</details>
<script src="js/urgencias-agenda.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
