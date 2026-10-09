<?php
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/VerificacionEmail.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Turnstile.php';

/**
 * Decide si unas credenciales abren sesión (HU-T.1). No toca la sesión ni
 * responde: devuelve el resultado y AuthController actúa. Así se prueba sin
 * servidor web.
 *
 * Resultados:
 *  · ok          credenciales correctas, cuenta activa y correo verificado
 *  · fallo       identificador o contraseña incorrectos (incluye la cuenta de
 *                Google que no ha creado contraseña)
 *  · inactiva    contraseña correcta, cuenta inactiva
 *  · pendiente   contraseña correcta, falta verificar el correo (HU-36)
 *  · nueva       (solo Google) no hay cuenta con ese correo
 *
 * `id_cuenta` es el id_usuario cuando el identificador corresponde a una
 * cuenta, aunque el intento falle: con él se cuenta el límite por cuenta
 * (RN-G15). El mensaje al cliente es el mismo en «fallo» y en «inactiva», así
 * que no revela si la cuenta existe.
 */
final class Autenticador
{
    /**
     * Hash de una cadena aleatoria. Se verifica contra él cuando no hay hash
     * real, para que responder «no existe» tarde lo mismo que «contraseña
     * incorrecta» y el tiempo de respuesta no delate cuentas.
     */
    private const HASH_SENUELO = '$2y$10$lrV6w0zx9QeY.96TiwGQ4uqE0PvRjofERQ9ZTiN0Dv6bG3anCO58K';

    public function __construct(private Usuario $usuarios, private VerificacionEmail $verificaciones)
    {
    }

    /** RE-T.1.1 — Documento o correo, y contraseña. */
    public function conPassword(string $identificador, string $password): array
    {
        $usuario = $this->usuarios->buscarParaLogin($identificador);
        if ($usuario === null) {
            password_verify($password, self::HASH_SENUELO);
            return $this->resultado('fallo', null);
        }

        // Una cuenta creada con Google no tiene contraseña (password NULL)
        // hasta que su dueño cree una: no se puede entrar con ninguna.
        $hash = $usuario['password'];
        unset($usuario['password']);
        if ($hash === null || $hash === '') {
            password_verify($password, self::HASH_SENUELO);
            return $this->resultado('fallo', $usuario);
        }
        if (!password_verify($password, $hash)) {
            return $this->resultado('fallo', $usuario);
        }

        return $this->despuesDeVerificar($usuario);
    }

    /** RE-T.13.5: IP y cuenta se tratan por separado; CAPTCHA sustituible en pruebas. */
    public function conPasswordProtegido(string $identificador, string $password, string $token, Turnstile $captcha): array
    {
        $resultado = $this->conPassword($identificador, $password);
        return $this->proteger($resultado, $token, $captcha);
    }

    public function conGoogleProtegido(string $email, string $token, Turnstile $captcha): array
    {
        return $this->proteger($this->conGoogle($email), $token, $captcha);
    }

    private function proteger(array $resultado, string $token, Turnstile $captcha): array
    {
        $cuenta = $resultado['id_cuenta'] !== null ? (string) $resultado['id_cuenta'] : null;
        if (!Security::checkRateLimit($cuenta)) {
            return ['resultado' => 'limite_ip', 'usuario' => null, 'id_cuenta' => $resultado['id_cuenta']];
        }
        // D2.1: la misma IP real que los límites (Auditoria::ipCliente), no la del proxy.
        if (Security::exigeCaptcha($cuenta) && !$captcha->validar($token, Auditoria::ipCliente())) {
            return ['resultado' => 'fallo', 'usuario' => null, 'id_cuenta' => $resultado['id_cuenta']];
        }
        return $resultado;
    }

    /**
     * RE-T.1.3 — Google ya demostró que el correo es de quien entra; aquí solo
     * se busca la cuenta. El token se valida antes, en GoogleToken.
     */
    public function conGoogle(string $email): array
    {
        $usuario = $this->usuarios->buscarPorEmail($email);
        if ($usuario === null) {
            return $this->resultado('nueva', null);
        }
        // Una verificación de correo pendiente no aplica: Google acaba de
        // demostrar que el buzón es de quien entra (como en la v1).
        return $this->despuesDeVerificar($usuario, false);
    }

    private function despuesDeVerificar(array $usuario, bool $exigeCorreoVerificado = true): array
    {
        if ((int) $usuario['estado'] !== 1) {
            return $this->resultado('inactiva', $usuario);
        }
        if ($exigeCorreoVerificado && $this->verificaciones->hayPendiente((int) $usuario['id_usuario'])) {
            return $this->resultado('pendiente', $usuario);
        }
        return $this->resultado('ok', $usuario);
    }

    private function resultado(string $resultado, ?array $usuario): array
    {
        return [
            'resultado' => $resultado,
            'usuario' => $resultado === 'ok' ? $usuario : null,
            'id_cuenta' => $usuario !== null ? (int) $usuario['id_usuario'] : null,
        ];
    }
}
