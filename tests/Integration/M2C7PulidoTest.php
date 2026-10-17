<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/PropietarioClinica.php';
require_once __DIR__ . '/../../models/HistoriaPropietario.php';
require_once __DIR__ . '/../../controllers/ConsultaController.php';
require_once __DIR__ . '/../../controllers/PortalController.php';

/**
 * M2 C7 — pulido de lo que dejó M2: síntomas en la lista de Consultas y en el
 * portal (RN-113 / RN-G02), alergias y peso en el imprimible del portal,
 * frecuencia respiratoria y observaciones del modal y el aviso de vincular.
 */
final class M2C7PulidoTest extends TestCase
{
    private const LUNA = 1;
    private const MILO = 2;
    private const DUENO_DE_MILO = 9;

    private PDO $db;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearCatalogosSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        DosClinicas::vincularLunaASur($this->db, 1);
        $this->db->exec("INSERT INTO mascotas (id_mascota, id_propietario, id_clinica_registro, token_carnet, nombre, id_especie, estado)
            VALUES (2, 9, 2, '" . str_repeat('m', 43) . "', 'Milo', 2, 1)");
        $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (2, 2, 'activo')");
        $_POST = [];
        $_GET = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_GET = [];
        http_response_code(200);
    }

    // ── 2. Síntomas en la lista de Consultas (RN-113) ───────────────────

    public function testLaListaDeConsultasTraeLosSintomasSoloDeSuClinica(): void
    {
        $this->comoPersonal(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $consultas = new Consulta($this->db);
        $id = $this->registrar(['sintoma.vomito']);

        $lista = $consultas->listarDeLaClinica();
        $this->assertSame(['Vómito'], array_column($lista[0]['sintomas'], 'etiqueta'));
        $this->assertSame(['Vómito'], array_column($consultas->filaDelListado($id)['sintomas'], 'etiqueta'));

        // Sur, aunque el propietario la autorizó, lista solo lo suyo: ni la consulta de Norte ni sus síntomas.
        $this->comoPersonal(DosClinicas::SUR, DosClinicas::VET_SUR);
        $this->assertSame([], (new Consulta($this->db))->listarDeLaClinica());
        $this->assertNull((new Consulta($this->db))->filaDelListado($id));
    }

    // ── 2. Síntomas en el portal (RN-G02) ───────────────────────────────

    public function testElPortalMuestraLosSintomasDeSuMascotaYNoLosDeOtroPropietario(): void
    {
        $this->comoPersonal(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $this->registrar(['sintoma.vomito']);

        $this->comoPropietario(DosClinicas::PROPIETARIO);
        $consultas = (new HistoriaPropietario($this->db))->consultas(self::LUNA);
        $this->assertSame([['codigo' => 'sintoma.vomito', 'etiqueta' => 'Vómito']], $consultas[0]['sintomas']);

        $this->comoPropietario(self::DUENO_DE_MILO);
        try {
            (new HistoriaPropietario($this->db))->consultas(self::LUNA);
            $this->fail('Un propietario vio los síntomas de la mascota de otro.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
    }

    // ── 1, 2 y 3. Imprimible del portal ─────────────────────────────────

    public function testElImprimibleTraeAlergiasActivasConSuClinicaSintomasYPesoSinRegistrar(): void
    {
        $this->comoPersonal(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $this->registrar(['sintoma.vomito']);
        $this->db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, id_veterinario, tipo, descripcion, activa) VALUES
            (1, 2, 4, 'alergia', 'Alergia a la amoxicilina', 1), (1, 1, 2, 'otra', 'Alerta ya desactivada', 0)");
        $this->db->exec('UPDATE mascotas SET peso = NULL WHERE id_mascota = 1');

        $this->comoPropietario(DosClinicas::PROPIETARIO);
        $html = $this->imprimir(self::LUNA);
        $this->assertStringContainsString('Alergias y alertas activas', $html);
        $this->assertStringContainsString('Alergia a la amoxicilina', $html);
        $this->assertStringContainsString('Clínica Sur', $html);
        $this->assertStringNotContainsString('Alerta ya desactivada', $html);
        $this->assertStringContainsString('<strong>Síntomas:</strong> Vómito', $html);
        $this->assertStringContainsString('Sin peso registrado', $html);

        $this->comoPropietario(self::DUENO_DE_MILO);
        try {
            $this->imprimir(self::LUNA);
            $this->fail('Imprimió la historia de la mascota de otro propietario.');
        } catch (AccesoDenegado $error) {
            $this->assertSame(403, $error->codigo());
        }
    }

    // ── 4. Frecuencia respiratoria y observaciones desde el modal ───────

    public function testElModalGuardaFrecuenciaRespiratoriaYObservacionesValidadasEnElServidor(): void
    {
        $this->comoPersonal(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $_POST = ['id_mascota' => '1', 'motivo' => 'Control', 'peso' => '8.5', 'diagnostico' => 'Sano', 'frecuencia_respiratoria' => '24', 'observaciones' => 'Respira tranquilo'];
        $respuesta = $this->registrarPorFormulario();
        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        $fila = $this->db->query('SELECT frecuencia_respiratoria, observaciones FROM consultas WHERE id_consulta = ' . (int) $respuesta['id_consulta'])->fetch(PDO::FETCH_ASSOC);
        $this->assertSame(['24', 'Respira tranquilo'], [(string) $fila['frecuencia_respiratoria'], $fila['observaciones']]);

        $_POST['frecuencia_respiratoria'] = (string) (ValidadorClinico::FR_MAX + 1);
        $respuesta = $this->registrarPorFormulario();
        $this->assertSame(422, http_response_code());
        $this->assertSame('frecuencia_respiratoria', $respuesta['campo']);
        $this->assertSame(ValidadorClinico::AVISOS_CONSULTA['frecuencia_respiratoria'], $respuesta['aviso']);
    }

    // ── 6. Vincular: qué hacer si aún no es cliente ─────────────────────

    public function testElAvisoSinVinculoDiceEnUnaLineaQueHacer(): void
    {
        $aviso = PropietarioClinica::MENSAJE_SIN_VINCULO;
        $this->assertStringContainsString('confirmación por correo', $aviso);
        $this->assertStringContainsString('desde su portal', $aviso);
        $this->assertStringNotContainsString("\n", $aviso);
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function registrar(array $sintomas): int
    {
        $entrada = ['id_mascota' => self::LUNA, 'peso' => '8.5', 'motivo' => 'Vomita', 'diagnostico' => 'Gastritis', 'sintomas' => $sintomas];
        return (new Consulta($this->db))->registrar($entrada, [], [], fn (): array => []);
    }

    private function registrarPorFormulario(): array
    {
        $controlador = new ConsultaController($this->db, 'copy', sys_get_temp_dir());
        http_response_code(200);
        ob_start();
        try {
            $controlador->registrarAjax();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true);
    }

    private function imprimir(int $idMascota): string
    {
        $_GET = ['id_mascota' => (string) $idMascota];
        ob_start();
        try {
            (new PortalController($this->db))->imprimirHistorial();
        } finally {
            $salida = ob_get_clean();
        }
        return $salida;
    }

    private function comoPropietario(int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::dePropietario(), 1);
    }

    private function comoPersonal(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }
}
