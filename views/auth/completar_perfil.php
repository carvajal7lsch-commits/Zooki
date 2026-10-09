<?php
/**
 * RE-T.18.3 (RN-G20) — Una cuenta de Google registra su documento y su
 * teléfono antes de usar el portal. Recibe de CuentaController $error.
 */
require_once __DIR__ . '/../../helpers/Csrf.php';
require_once __DIR__ . '/../../helpers/ValidadorTelefono.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../config/App.php';

$e = static fn ($valor): string => htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
$nombres = ['CC' => 'Cédula de ciudadanía', 'CE' => 'Cédula de extranjería', 'TI' => 'Tarjeta de identidad', 'PP' => 'Pasaporte', 'NIT' => 'NIT'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completa tu perfil · Zooki</title>
    <link rel="icon" type="image/png" href="img/icon_blue.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/contexto.css?v=<?= $e(App::assetVersion()) ?>">
    <link rel="stylesheet" href="css/cuenta-pendiente.css?v=<?= $e(App::assetVersion()) ?>">
    <link rel="stylesheet" href="css/validacion-cuenta.css?v=d2">
</head>
<body class="contexto-pagina">
    <main class="contexto-tarjeta">
        <img src="img/icon_blue.png" alt="Zooki" class="contexto-logo">
        <h1 class="contexto-titulo">Completa tu perfil</h1>

        <?php if ($error): ?>
            <p class="contexto-aviso contexto-aviso--error" role="alert"><?= $e($error) ?></p>
        <?php endif; ?>

        <p class="contexto-texto">
            Para registrar tus mascotas y agendar citas, la clínica necesita tu documento y un teléfono de contacto.
        </p>

        <form method="POST" action="index.php?action=completar_perfil" class="cuenta-form" data-validacion-cuenta>
            <?php Csrf::field('default'); ?>
            <label class="cuenta-campo" for="perfilTipoDocumento">Tipo de documento</label>
            <select id="perfilTipoDocumento" name="tipo_documento" required>
                <?php foreach (Usuario::TIPOS_DOCUMENTO as $tipo): ?>
                    <option value="<?= $e($tipo) ?>"><?= $e($nombres[$tipo] ?? $tipo) ?></option>
                <?php endforeach; ?>
            </select>

            <label class="cuenta-campo" for="perfilDocumento">Número de documento</label>
            <input type="text" id="perfilDocumento" name="documento" required inputmode="numeric" pattern="\d{5,15}" maxlength="15" data-caracteres="0-9" autocomplete="off">

            <label class="cuenta-campo" for="perfilTelefono">Teléfono</label>
            <input type="tel" id="perfilTelefono" name="telefono" required minlength="<?= ValidadorTelefono::MIN ?>" <?= ValidadorTelefono::atributosHtml() ?> autocomplete="tel">

            <button type="submit" class="cuenta-boton">Guardar y continuar</button>
        </form>

        <a class="contexto-salir" href="index.php?action=logout"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Cerrar sesión</a>
    </main>
    <script src="js/interacciones.js?v=2"></script>
<?php require __DIR__ . "/../partials/validacion_cuenta.php"; ?>
</body>
</html>
