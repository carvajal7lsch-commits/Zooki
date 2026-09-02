<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/GoogleToken.php';

/**
 * HU-36 (VD-SEG-10) — "El login con Google valida aud/iss contra el client_id propio".
 *
 * El flujo anterior preguntaba a Google si el token era autentico y aceptaba
 * cualquier respuesta con HTTP 200. Eso confirma que el token lo emitio Google,
 * pero no que sea para Zooki: un token emitido para OTRA aplicacion, con el
 * correo de un usuario de Zooki, entraba como ese usuario.
 *
 * Se prueba motivoDeRechazo(), que es donde vive la regla; la llamada de red
 * queda aparte justamente para poder cubrir esto sin depender de Google.
 */
class GoogleTokenTest extends TestCase
{
    private const CLIENTE = '123456789-abcdef.apps.googleusercontent.com';
    private const OTRO_CLIENTE = '999999999-malicioso.apps.googleusercontent.com';

    private function payloadValido(array $sobrescribir = []): array
    {
        return array_merge([
            'aud' => self::CLIENTE,
            'iss' => 'https://accounts.google.com',
            'exp' => time() + 3600,
            'email' => 'usuario@zooki.test',
            'email_verified' => 'true',
        ], $sobrescribir);
    }

    public function testAceptaUnTokenEmitidoParaZooki()
    {
        $this->assertNull(
            GoogleToken::motivoDeRechazo($this->payloadValido(), self::CLIENTE, true)
        );
    }

    /**
     * EL caso de la vulnerabilidad: token legitimo de Google, firmado y sin
     * vencer, pero emitido para la aplicacion de otro.
     */
    public function testRechazaUnTokenEmitidoParaOtraAplicacion()
    {
        $motivo = GoogleToken::motivoDeRechazo(
            $this->payloadValido(['aud' => self::OTRO_CLIENTE]),
            self::CLIENTE,
            true
        );

        $this->assertNotNull($motivo, 'un token de otra aplicacion NO puede pasar');
        $this->assertStringContainsString('esta aplicacion', $motivo);
    }

    public function testRechazaUnTokenSinAudiencia()
    {
        $payload = $this->payloadValido();
        unset($payload['aud']);

        $this->assertNotNull(GoogleToken::motivoDeRechazo($payload, self::CLIENTE, true));
    }

    /** Si el token no trae `aud` pero si `azp`, se compara contra ese. */
    public function testUsaAzpCuandoNoHayAud()
    {
        $payload = $this->payloadValido(['azp' => self::CLIENTE]);
        unset($payload['aud']);

        $this->assertNull(GoogleToken::motivoDeRechazo($payload, self::CLIENTE, true));
    }

    public function testRechazaUnEmisorQueNoEsGoogle()
    {
        $motivo = GoogleToken::motivoDeRechazo(
            $this->payloadValido(['iss' => 'https://accounts.impostor.com']),
            self::CLIENTE,
            true
        );

        $this->assertNotNull($motivo);
        $this->assertStringContainsString('emisor', $motivo);
    }

    public function testAceptaLasDosFormasDelEmisorLegitimo()
    {
        foreach (['accounts.google.com', 'https://accounts.google.com'] as $iss) {
            $this->assertNull(
                GoogleToken::motivoDeRechazo($this->payloadValido(['iss' => $iss]), self::CLIENTE, true),
                "Google emite el iss como '$iss'"
            );
        }
    }

    /** El access_token no lleva iss, asi que no se le puede exigir. */
    public function testElAccessTokenNoExigeEmisor()
    {
        $payload = $this->payloadValido();
        unset($payload['iss']);

        $this->assertNull(GoogleToken::motivoDeRechazo($payload, self::CLIENTE, false));
        $this->assertNotNull(
            GoogleToken::motivoDeRechazo($payload, self::CLIENTE, true),
            'pero el id_token si'
        );
    }

    public function testRechazaUnTokenVencido()
    {
        $motivo = GoogleToken::motivoDeRechazo(
            $this->payloadValido(['exp' => time() - 10]),
            self::CLIENTE,
            true
        );

        $this->assertNotNull($motivo);
        $this->assertStringContainsString('vencido', $motivo);
    }

    public function testRechazaUnTokenSinCorreo()
    {
        $payload = $this->payloadValido();
        unset($payload['email']);

        $this->assertNotNull(GoogleToken::motivoDeRechazo($payload, self::CLIENTE, true));
    }

    /** Un correo no verificado por Google no sirve para identificar a nadie. */
    public function testRechazaUnCorreoNoVerificado()
    {
        foreach (['false', false, '0', 0] as $valor) {
            $this->assertNotNull(
                GoogleToken::motivoDeRechazo($this->payloadValido(['email_verified' => $valor]), self::CLIENTE, true),
                'email_verified = ' . var_export($valor, true)
            );
        }
    }

    /** Google devuelve el campo como cadena en tokeninfo y como booleano en otras respuestas. */
    public function testAceptaLasDosFormasDeEmailVerificado()
    {
        foreach (['true', true, '1', 1] as $valor) {
            $this->assertNull(
                GoogleToken::motivoDeRechazo($this->payloadValido(['email_verified' => $valor]), self::CLIENTE, true),
                'email_verified = ' . var_export($valor, true)
            );
        }
    }

    /**
     * Sin client_id configurado no se puede comparar nada. Antes que aceptar
     * a ciegas, se rechaza: si no, un .env incompleto reabriria el agujero.
     */
    public function testSinClientIdConfiguradoNoSeAceptaNingunToken()
    {
        $motivo = GoogleToken::motivoDeRechazo($this->payloadValido(), '', true);

        $this->assertNotNull($motivo);
        $this->assertStringContainsString('GOOGLE_CLIENT_ID', $motivo);
    }
}
