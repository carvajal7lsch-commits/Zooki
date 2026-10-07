<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../helpers/Autenticador.php';

/**
 * HU-T.1 — Inicio de sesión de la v2 (RE-T.1.1, RE-T.1.3, RE-T.1.5, RN-G15):
 * por documento o por correo, con Google sin contraseña, y sin revelar si la
 * cuenta existe.
 */
class AuthTest extends TestCase
{
    private PDO $db;
    private Autenticador $auth;
    private Usuario $usuarios;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        $this->usuarios = new Usuario($this->db);
        $this->auth = new Autenticador($this->usuarios, new VerificacionEmail($this->db));
    }

    /** RE-T.1.1: con el documento. */
    public function testEntraConElDocumento(): void
    {
        $r = $this->auth->conPassword('1000000001', DosClinicas::PASSWORD);

        $this->assertSame('ok', $r['resultado']);
        $this->assertSame(DosClinicas::ADMIN_NORTE, (int) $r['usuario']['id_usuario']);
        $this->assertArrayNotHasKey('password', $r['usuario'], 'El hash no sale del modelo hacia la sesión');
    }

    /** RE-T.1.1: con el correo, y el mismo id_usuario que con el documento. */
    public function testEntraConElCorreo(): void
    {
        $r = $this->auth->conPassword('ana@zooki.test', DosClinicas::PASSWORD);

        $this->assertSame('ok', $r['resultado']);
        $this->assertSame(DosClinicas::ADMIN_NORTE, (int) $r['usuario']['id_usuario']);
    }

    /**
     * RE-T.1.5 / RN-G15: contraseña incorrecta y cuenta inexistente dan el
     * mismo resultado. La diferencia es interna: con una cuenta real se sabe
     * su id para el límite por cuenta.
     */
    public function testUnaCredencialInvalidaNoDistingueSiLaCuentaExiste(): void
    {
        $mala = $this->auth->conPassword('1000000001', 'otra-clave');
        $inexistente = $this->auth->conPassword('9999999999', 'otra-clave');

        $this->assertSame('fallo', $mala['resultado']);
        $this->assertSame('fallo', $inexistente['resultado']);
        $this->assertNull($mala['usuario']);
        $this->assertSame(DosClinicas::ADMIN_NORTE, $mala['id_cuenta']);
        $this->assertNull($inexistente['id_cuenta']);
    }

    /** RE-T.1.3 / riesgo 9 de A.6: la cuenta de Google no tiene contraseña y no se entra con ninguna. */
    public function testUnaCuentaDeGoogleNoEntraConContrasena(): void
    {
        $this->assertSame(0, (int) $this->usuarios->buscarPorId(DosClinicas::GOOGLE)['tiene_password']);

        $r = $this->auth->conPassword('iris@zooki.test', '');
        $this->assertSame('fallo', $r['resultado']);
        $this->assertSame('fallo', $this->auth->conPassword('1000000009', 'cualquiera')['resultado']);
        $this->assertFalse($this->usuarios->verificarPassword(DosClinicas::GOOGLE, ''));
    }

    /** RE-T.1.3: con Google la misma cuenta entra por su correo. */
    public function testUnaCuentaDeGoogleEntraConGoogle(): void
    {
        $r = $this->auth->conGoogle('iris@zooki.test');

        $this->assertSame('ok', $r['resultado']);
        $this->assertSame(DosClinicas::GOOGLE, (int) $r['usuario']['id_usuario']);
        $this->assertSame('nueva', $this->auth->conGoogle('nadie@zooki.test')['resultado']);
    }

    /** Google crea la identidad con password NULL y su google_uid, sin rol (MER §2). */
    public function testElRegistroConGoogleCreaUnaCuentaSinContrasena(): void
    {
        $id = $this->usuarios->crear([
            'documento' => '1000000099', 'tipo_documento' => 'CC', 'nombre_completo' => 'Nueva Google',
            'telefono' => '3000000000', 'email' => 'nueva@zooki.test', 'password' => null, 'google_uid' => 'sub-nueva',
        ]);

        $cuenta = $this->usuarios->buscarPorId($id);
        $this->assertSame(0, (int) $cuenta['tiene_password']);
        $this->assertSame(1, (int) $cuenta['tiene_google']);
        $this->assertSame([], $this->usuarios->contextosDe($id), 'Sin vínculo todavía (HU-5.8, etapa D)');
        $this->assertSame('ok', $this->auth->conGoogle('nueva@zooki.test')['resultado']);
    }

    /** HU-38: una cuenta inactiva no entra; el mensaje al cliente es el de credenciales. */
    public function testUnaCuentaInactivaNoEntra(): void
    {
        $r = $this->auth->conPassword('hugo@zooki.test', DosClinicas::PASSWORD);

        $this->assertSame('inactiva', $r['resultado']);
        $this->assertNull($r['usuario']);
        $this->assertSame('inactiva', $this->auth->conGoogle('hugo@zooki.test')['resultado']);
    }

    /** HU-36: con el correo sin verificar no se entra por contraseña. */
    public function testUnCorreoSinVerificarNoEntra(): void
    {
        (new VerificacionEmail($this->db))->crear(DosClinicas::PROPIETARIO, 'fabio@zooki.test', 'hash', date('Y-m-d H:i:s', time() + 3600));

        $this->assertSame('pendiente', $this->auth->conPassword('fabio@zooki.test', DosClinicas::PASSWORD)['resultado']);
        $this->assertSame('ok', $this->auth->conGoogle('fabio@zooki.test')['resultado'], 'Google ya demostró el buzón');
    }

    /** RN-G06: documento y correo son únicos; la comprobación ignora a la propia persona. */
    public function testUnicidadDeDocumentoYCorreo(): void
    {
        $this->assertTrue($this->usuarios->existeDocumento('1000000001'));
        $this->assertFalse($this->usuarios->existeDocumento('1000000001', DosClinicas::ADMIN_NORTE));
        $this->assertTrue($this->usuarios->existeEmail('ana@zooki.test', DosClinicas::VET_NORTE));
        $this->assertFalse($this->usuarios->existeEmail('libre@zooki.test'));
    }
}
