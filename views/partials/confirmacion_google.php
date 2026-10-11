<?php
/** RE-T.2.4: crear la primera contraseña requiere confirmar otra vez con Google. */
require_once __DIR__ . '/../../helpers/GoogleToken.php';
$clienteConfirmacion = htmlspecialchars(GoogleToken::clientId(), ENT_QUOTES, 'UTF-8');
$botonConfirmacion = isset($usuarioData) ? 'btn-enlace' : (isset($perfil) ? 'perfil-btn' : 'btn-primary');
?>
<p>Antes de crear tu contraseña, confirma nuevamente tu identidad con Google.</p>
<button type="button" class="<?= $botonConfirmacion ?>" data-confirmar-google data-google-client="<?= $clienteConfirmacion ?>">Confirmar mi identidad con Google</button>
<input type="hidden" name="access_token" value="">
<p role="status" aria-live="polite" data-google-resultado></p>
