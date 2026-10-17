<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../../models/Consulta.php';
require_once __DIR__ . '/../../models/ConsultaSintoma.php';
require_once __DIR__ . '/../../controllers/ConsultaController.php';

/**
 * M2 C5a — RN-210 y A.1: la consulta guarda los síntomas marcados en
 * consulta_sintomas dentro de su transacción, validados como síntomas activos
 * del conocimiento; el historial los muestra solo en las consultas que la
 * clínica ya puede ver (RN-113). Además, los errores de campo salen con su
 * campo (422) y el registro devuelve la fila del listado para no recargar.
 */
final class ConsultaSintomasTest extends TestCase
{
    private PDO $db;
    private Consulta $consultas;

    protected function setUp(): void
    {
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        DosClinicas::crearHistoriaSqlite($this->db);
        // El fixture ya trae sintoma.vomito (5); se suman uno activo y uno retirado.
        $this->db->exec("INSERT INTO grafo_nodos (id_nodo, codigo, activo, tipo, etiqueta) VALUES
            (20, 'sintoma.diarrea', 1, 'sintoma', 'Diarrea'), (21, 'sintoma.tos_retirada', 0, 'sintoma', 'Tos retirada')");
        $this->consultas = new Consulta($this->db);
        $this->comoVeterinario(DosClinicas::NORTE, DosClinicas::VET_NORTE);
        $_POST = [];
        $_FILES = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_FILES = [];
        http_response_code(200);
    }

    // ── RN-210: guardado en la misma transacción ────────────────────────

    public function testLosSintomasSeGuardanConLaConsultaSinRepetirYSalenEnElHistorial(): void
    {
        $id = $this->registrar(['sintomas' => ['sintoma.vomito', 'sintoma.diarrea', 'sintoma.vomito']]);

        $guardados = $this->db->query("SELECT id_nodo FROM consulta_sintomas WHERE id_consulta = $id ORDER BY id_nodo")->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([5, 20], array_map('intval', $guardados));

        $historial = $this->consultas->historialDeMascota(1);
        $this->assertSame([
            ['codigo' => 'sintoma.diarrea', 'etiqueta' => 'Diarrea'],
            ['codigo' => 'sintoma.vomito', 'etiqueta' => 'Vómito'],
        ], $historial[0]['sintomas']);

        // Sin síntomas la consulta se guarda igual: son opcionales.
        $sinSintomas = $this->registrar();
        $this->assertSame(0, $this->contar("SELECT COUNT(*) FROM consulta_sintomas WHERE id_consulta = $sinSintomas"));
    }

    /** Un código inexistente, retirado o de otro tipo da 422 junto al campo y no se escribe nada. */
    public function testCodigoInvalidoRetiradoODeOtroTipoSeRechazaJuntoAlCampoSinEscribir(): void
    {
        $invalidos = [
            ['sintoma.no_existe'],
            ['sintoma.tos_retirada'],
            ['farmaco.paracetamol'],
            [['sintoma.vomito']],
            ['clave' => 'sintoma.vomito'],
            'sintoma.vomito',
            array_map(fn (int $i): string => 'sintoma.n' . $i, range(1, ValidadorClinico::SINTOMAS_MAX + 1)),
        ];
        foreach ($invalidos as $sintomas) {
            try {
                $this->registrar(['sintomas' => $sintomas]);
                $this->fail('Debió rechazar ' . json_encode($sintomas));
            } catch (CampoInvalido $e) {
                $this->assertSame('sintomas', $e->campo);
            }
        }

        // Por el controlador: 422 con el campo y el aviso corto, sin datos parciales.
        $_POST = ['id_mascota' => '1', 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano', 'sintomas' => ['sintoma.vomito', 'sintoma.tos_retirada']];
        $respuesta = $this->ejecutarControlador();
        $this->assertFalse($respuesta['success']);
        $this->assertSame(422, http_response_code());
        $this->assertSame('sintomas', $respuesta['campo']);
        $this->assertSame(ValidadorClinico::AVISOS_CONSULTA['sintomas'], $respuesta['aviso']);

        $this->assertSinRegistroClinico();
    }

    /** RE-2.6.1: si algo falla después de escribir los síntomas, el rollback también los deshace. */
    public function testUnFalloPosteriorDeshaceTambienLosSintomas(): void
    {
        $this->db->exec("CREATE TRIGGER falla_tratamiento BEFORE INSERT ON tratamientos BEGIN SELECT RAISE(ABORT, 'falla simulada'); END");
        $tratamiento = ['medicamento' => 'Amoxicilina', 'dosis' => '1 ml', 'via_administracion' => 'Oral', 'duracion' => '5 días', 'fecha_inicio' => '2026-10-07'];

        try {
            $this->registrar(['sintomas' => ['sintoma.vomito', 'sintoma.diarrea']], [$tratamiento]);
            $this->fail('Debió fallar el tratamiento.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('falla simulada', $e->getMessage());
        }

        $this->assertSinRegistroClinico();
    }

    // ── RN-113: síntomas solo de consultas visibles ─────────────────────

    public function testElHistorialMuestraSintomasSoloDeConsultasQueLaClinicaPuedeVer(): void
    {
        $idNorte = $this->registrar(['sintomas' => ['sintoma.vomito']]);

        // Sur sin vínculo: 403, como el resto de la historia.
        $this->comoVeterinario(DosClinicas::SUR, DosClinicas::VET_SUR);
        try {
            $this->consultas->historialDeMascota(1);
            $this->fail('Sin vínculo no se ve la historia.');
        } catch (AccesoDenegado $e) {
            $this->assertSame(403, $e->codigo());
        }

        // Vinculada sin autorización: ni la consulta de Norte ni sus síntomas, tampoco pidiéndolos por id.
        DosClinicas::vincularLunaASur($this->db, 0);
        $this->assertSame([], $this->consultas->historialDeMascota(1));
        $this->assertSame([], (new ConsultaSintoma($this->db))->deConsultas([$idNorte]));
        $this->assertSame([], $this->consultas->conSintomas([['id_consulta' => $idNorte]])[0]['sintomas']);

        // Con la autorización del propietario (RN-113) llegan de solo lectura, con sus síntomas.
        $this->db->exec('UPDATE propietario_clinica SET autoriza_historia_compartida = 1 WHERE id_clinica = ' . DosClinicas::SUR);
        $historial = $this->consultas->historialDeMascota(1);
        $this->assertFalse($historial[0]['es_propia']);
        $this->assertSame(['Vómito'], array_column($historial[0]['sintomas'], 'etiqueta'));
    }

    /** Un nodo retirado después de guardarse sigue mostrándose: es lo que se registró. */
    public function testElHistorialConservaLaEtiquetaDeUnSintomaRetiradoDespues(): void
    {
        $this->registrar(['sintomas' => ['sintoma.diarrea']]);
        $this->db->exec("UPDATE grafo_nodos SET activo = 0 WHERE codigo = 'sintoma.diarrea'");

        $this->assertSame(['Diarrea'], array_column($this->consultas->historialDeMascota(1)[0]['sintomas'], 'etiqueta'));
    }

    // ── Errores junto al campo y respuesta para no recargar (A.1) ───────

    public function testLosErroresDelServidorDicenSuCampoYSuAvisoCorto(): void
    {
        $casos = [
            'motivo' => ['motivo' => '   '],
            'diagnostico' => ['diagnostico' => ''],
            'temperatura' => ['temperatura' => '80'],
            'peso' => ['peso' => '0'],
            'frecuencia_cardiaca' => ['frecuencia_cardiaca' => '5'],
            'frecuencia_respiratoria' => ['frecuencia_respiratoria' => '1.5'],
        ];
        foreach ($casos as $campo => $cambio) {
            $_POST = array_replace(['id_mascota' => '1', 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano'], $cambio);
            $respuesta = $this->ejecutarControlador();
            $this->assertSame(422, http_response_code(), $campo);
            $this->assertSame($campo, $respuesta['campo']);
            $this->assertSame(ValidadorClinico::AVISOS_CONSULTA[$campo], $respuesta['aviso']);
        }

        // Más largo que la columna: se rechaza sin recortar lo que escribió el veterinario.
        $_POST = ['id_mascota' => '1', 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => str_repeat('d', ValidadorClinico::TEXTO_MAX + 1)];
        $respuesta = $this->ejecutarControlador();
        $this->assertSame('diagnostico', $respuesta['campo']);
        $this->assertStringContainsString((string) ValidadorClinico::TEXTO_MAX, $respuesta['aviso']);

        $this->assertSinRegistroClinico();
    }

    public function testElRegistroDevuelveLaFilaDelListadoParaPonerLaPantallaAlDia(): void
    {
        $_POST = ['id_mascota' => '1', 'peso' => '8.5', 'motivo' => 'Vomita', 'diagnostico' => 'Gastritis', 'sintomas' => ['sintoma.vomito']];

        $respuesta = $this->ejecutarControlador();

        $this->assertTrue($respuesta['success'], $respuesta['message'] ?? '');
        $this->assertSame($respuesta['id_consulta'], (int) $respuesta['consulta']['id_consulta']);
        $this->assertSame('Luna', $respuesta['consulta']['nombre_mascota']);
        $this->assertSame('Beto Norte', $respuesta['consulta']['veterinario']);
        $this->assertSame('Gastritis', $respuesta['consulta']['diagnostico']);
        $this->assertSame(1, $this->contar('SELECT COUNT(*) FROM consulta_sintomas'));
    }

    // ── Apoyo ───────────────────────────────────────────────────────────

    private function comoVeterinario(int $clinica, int $idUsuario): void
    {
        $_SESSION = ['id_usuario' => $idUsuario];
        Contexto::activar(Contexto::deClinica($clinica, 'Clínica', Roles::VETERINARIO), 1);
    }

    private function registrar(array $extra = [], array $tratamientos = []): int
    {
        $entrada = array_replace(['id_mascota' => 1, 'peso' => '8.5', 'motivo' => 'Control', 'diagnostico' => 'Sano'], $extra);
        $sinAdjuntos = fn (int $idConsulta, array $adjunto, int $indice): array => [];
        return $this->consultas->registrar($entrada, $tratamientos, [], $sinAdjuntos);
    }

    private function ejecutarControlador(): array
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

    private function assertSinRegistroClinico(): void
    {
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consultas'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM consulta_sintomas'));
        $this->assertSame(0, $this->contar('SELECT COUNT(*) FROM tratamientos'));
        $this->assertNull($this->db->query('SELECT numero_historia_clinica FROM mascota_clinica WHERE id_mascota = 1 AND id_clinica = ' . DosClinicas::NORTE)->fetchColumn());
    }

    private function contar(string $sql): int
    {
        return (int) $this->db->query($sql)->fetchColumn();
    }
}
