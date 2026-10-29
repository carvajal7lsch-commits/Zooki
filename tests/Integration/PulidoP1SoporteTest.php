<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/PlataformaFixture.php';
require_once __DIR__ . '/../../models/CasoSoporte.php';
require_once __DIR__ . '/../../models/Plataforma.php';
require_once __DIR__ . '/../../controllers/CuentaController.php';

/** HU-0.3/RE-0.3.4, RE-T.18.3 y RN-G24: soporte privado, sin SMTP real. */
final class PulidoP1SoporteTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $this->db = PlataformaFixture::sqlite();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        http_response_code(200);
    }

    private function abrir(?callable $correo = null): CasoSoporte
    {
        $modelo = new CasoSoporte($this->db, $correo);
        $modelo->documentoDuplicado(6, '1000000001', 'RN-G24');
        return $modelo;
    }

    public function testCorreoIndividualSoloParaSuperadministradoresActivosYVerificadosSinDatosDelTitular(): void
    {
        $this->db->exec("INSERT INTO usuarios (id_usuario, nombre_completo, email, es_super_admin, estado) VALUES
            (30, 'Otra plataforma', 'otra@zooki.test', 1, 1),
            (31, 'Inactiva', 'inactiva@zooki.test', 1, 0),
            (32, 'Sin verificar', 'pendiente@zooki.test', 1, 1)");
        $this->db->exec("INSERT INTO verificaciones_email (id_usuario, email, proposito, token_hash, expires_at) VALUES (32, 'pendiente@zooki.test', 'registro', 'prueba', '2000-01-01')");
        $enviados = [];
        $modelo = $this->abrir(function ($email, $asunto, $cuerpo) use (&$enviados): bool {
            $this->assertFalse($this->db->inTransaction());
            $enviados[] = [$email, $asunto, $cuerpo];
            return true;
        });
        $this->assertSame([], $enviados, 'Abrir el caso nunca espera al correo.');
        $modelo->enviarAviso(1);
        $esperados = $this->db->query('SELECT email FROM usuarios WHERE id_usuario IN (7,30) ORDER BY id_usuario')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame($esperados, array_column($enviados, 0));
        foreach ($enviados as [, $asunto, $cuerpo]) {
            $this->assertSame('Zooki: nuevo caso de soporte #1', $asunto);
            $this->assertStringContainsString('Documento asociado a otra cuenta', $cuerpo);
            $this->assertStringContainsString('action=plataforma_inicio#soporte', $cuerpo);
            $this->assertMatchesRegularExpression('/Fecha: [0-9]{2}\/[0-9]{2}\/[0-9]{4}/', $cuerpo);
            foreach (['1000000001', 'Fabio', 'fabio@', 'RN-G24', 'RE-T.18.3'] as $privado) {
                $this->assertStringNotContainsString($privado, $asunto . $cuerpo);
            }
        }
    }

    public function testElFalloNoDeshaceElCasoYContinuaConElSiguienteDestinatario(): void
    {
        $this->db->exec("INSERT INTO usuarios (id_usuario, email, es_super_admin, estado) VALUES (30, 'otra@zooki.test', 1, 1)");
        $intentos = 0;
        $modelo = $this->abrir(function () use (&$intentos): bool {
            $intentos++;
            if ($intentos === 1) throw new RuntimeException('Fallo simulado');
            return false;
        });
        $modelo->enviarAviso(1);
        $this->assertSame(2, $intentos);
        $this->assertSame('abierto', $this->db->query('SELECT estado FROM casos_soporte')->fetchColumn());
    }

    public function testNoEnviaAntesDelCommitNiDespuesDeRollback(): void
    {
        $enviados = 0;
        $this->db->beginTransaction();
        $modelo = $this->abrir(function () use (&$enviados): bool { $enviados++; return true; });
        $modelo->enviarAviso(1);
        $this->assertSame(0, $enviados);
        $this->db->rollBack();
        $modelo->enviarAviso(1);
        $this->assertSame(0, $enviados);
    }

    public function testNoDuplicaElCasoAbiertoAunqueTengaLaDescripcionAnterior(): void
    {
        $this->db->exec("INSERT INTO casos_soporte (tipo, id_usuario, descripcion) VALUES ('documento_duplicado', 6, 'El titular solicitó el documento 1000000001 ya asociado a otra cuenta (al completar el perfil, RE-T.18.3).')");
        $this->abrir();
        $this->abrir();
        $this->assertSame(1, (int) $this->db->query('SELECT COUNT(*) FROM casos_soporte')->fetchColumn());
        PlataformaFixture::plataforma();
        $caso = (new Plataforma($this->db))->casos()[0];
        $this->assertStringNotContainsString('RE-T.', $caso['descripcion']);
        $this->assertSame('Fabio Dueño', $caso['solicitante_nombre']);
        $this->assertSame(6, (int) $caso['id_usuario']);
        $this->assertSame(1, (int) $caso['cuenta_documento']['id_usuario']);
        $this->assertSame('ana@zooki.test', $caso['cuenta_documento']['email']);
    }

    public function testSiElDocumentoYaNoPerteneceACuentaAlgunaElDetalleNoInventaUna(): void
    {
        $this->abrir();
        $this->db->exec('UPDATE usuarios SET documento = NULL WHERE id_usuario = 1');
        PlataformaFixture::plataforma();
        $this->assertNull((new Plataforma($this->db))->casos()[0]['cuenta_documento']);
    }

    public function testRespuestaAlTitularIdenticaConAvisoCorrectoFallidoOSinCorreo(): void
    {
        $respuestas = [];
        foreach ([null, static fn (): bool => true, static fn (): bool => false] as $correo) {
            try {
                (new CuentaController($this->db))->guardarPerfil(6, ['tipo_documento' => 'CC', 'documento' => '1000000001', 'telefono' => '3001234567']);
                $this->fail('El documento pertenece a otra cuenta.');
            } catch (InvalidArgumentException $error) {
                $respuestas[] = $error->getMessage();
            }
            (new CasoSoporte($this->db, $correo))->enviarAviso(1);
        }
        $this->assertSame(array_fill(0, 3, RegistroPropietario::MENSAJE_PERFIL_NO_COMPLETADO), $respuestas);
        $this->assertSame('abierto', $this->db->query('SELECT estado FROM casos_soporte')->fetchColumn());
    }

    public function testElDetalleSoloExisteEnLaAccionDePlataformaYElModeloRevalidaElContexto(): void
    {
        $matriz = (new ReflectionMethod(Security::class, 'actionRoles'))->invoke(null);
        foreach ([Roles::ADMIN, Roles::VETERINARIO, Roles::PROPIETARIO] as $rol) {
            $this->assertNotContains($rol, $matriz['plataforma_inicio']);
            $this->assertNotContains($rol, $matriz['plataforma_caso_ajax']);
        }
        $this->assertSame([Roles::SUPER_ADMIN], $matriz['plataforma_inicio']);
        $_SESSION = ['id_usuario' => 6];
        Contexto::activar(Contexto::dePropietario(), 1);
        $this->expectException(AccesoDenegado::class);
        (new Plataforma($this->db))->casos();
    }
}
