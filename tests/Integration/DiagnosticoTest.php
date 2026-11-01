<?php
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/../Support/DosClinicas.php';
require_once __DIR__ . '/../Support/ConocimientoRealPrueba.php';
require_once __DIR__ . '/../../controllers/DiagnosticoController.php';
require_once __DIR__ . '/../../models/Usuario.php';

final class DiagnosticoTest extends TestCase
{
    private PDO $db;
    private DateTimeImmutable $fecha;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->db = DosClinicas::sqlite();
        DosClinicas::crearMascotasSqlite($this->db);
        ConocimientoPrueba::crearGrafo($this->db);
        $this->db->exec('CREATE TABLE consultas (id_consulta INTEGER PRIMARY KEY, id_clinica INTEGER, id_mascota INTEGER, fecha_hora TEXT, peso NUMERIC)');
        ConocimientoRealPrueba::taxonomia($this->db);
        (new CargadorConocimiento($this->db))->cargar(ConocimientoRealPrueba::CARPETA);
        $this->db->exec('CREATE TABLE alertas_medicas (id_alerta INTEGER PRIMARY KEY AUTOINCREMENT, id_mascota INTEGER, id_clinica INTEGER, id_veterinario INTEGER, tipo TEXT, id_nodo INTEGER, descripcion TEXT, activa INTEGER, fecha TEXT)');
        $this->db->exec('CREATE TABLE vacunas (id_vacuna INTEGER PRIMARY KEY, id_mascota INTEGER, id_clinica INTEGER, fecha_proxima_dosis TEXT)');
        $usuarios = new Usuario($this->db);
        Security::definirFuenteDeContextos(fn (int $id): ?array => $usuarios->contextosDe($id));
        $this->fecha = new DateTimeImmutable('2026-10-15T12:00:00-05:00');
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['csrf_token' => 'token', 'id_mascota' => '1', 'sintomas' => ['sintoma.vomito', 'sintoma.diarrea_sangre', 'sintoma.no_come']];
        $this->como(DosClinicas::VET_NORTE, Contexto::deClinica(1, 'Norte', Roles::VETERINARIO));
        http_response_code(200);
    }

    protected function tearDown(): void
    {
        Security::definirFuenteDeContextos(null);
        $_SESSION = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        http_response_code(200);
    }

    private function como(int $id, array $contexto): void
    {
        $_SESSION = ['id_usuario' => $id, 'csrf_default' => 'token'];
        Contexto::activar($contexto, 1);
    }

    private function llamar(bool $seguridad = true): array
    {
        if ($seguridad) {
            Security::autorizar('sugerir_diagnosticos_ajax');
        }
        ob_start();
        try {
            (new DiagnosticoController($this->db, $this->fecha))->sugerirAjax();
        } finally {
            $salida = ob_get_clean();
        }
        return json_decode($salida, true, 64, JSON_THROW_ON_ERROR);
    }

    public function testContextoSaleDeLaFichaYSinVacunasQuedaDesconocida(): void
    {
        $this->db->exec("UPDATE mascotas SET fecha_nacimiento = '2026-07-15', sexo = 'Macho', esterilizado = 0 WHERE id_mascota = 1");
        // Estos valores de identidad no son datos que la API permita sobrescribir.
        $_POST += ['especie' => 'especie.felino', 'edad_meses' => 100, 'sexo' => 'Hembra', 'esterilizado' => true];
        $grafo = (new GrafoConocimiento($this->db))->instantanea();
        $contexto = (new PacienteDiagnostico($this->db))->contexto(1, $grafo, $this->fecha, []);
        $this->assertSame('especie.canino', $contexto['especie']);
        $this->assertSame(3, $contexto['edad_meses']);
        $this->assertSame('Macho', $contexto['sexo']);
        $this->assertFalse($contexto['esterilizado']);
        $this->assertSame('desconocida', $contexto['vacunacion']);
        $cachorro = $this->llamar();
        $this->assertContains('ranking.parvovirosis_contexto', $cachorro['candidatos'][0]['reglas_aplicadas']);
        // RN-416: en adulto sin registros la vacunación desconocida ya es un estado evaluable.
        $this->db->exec("UPDATE mascotas SET fecha_nacimiento = '2020-07-15' WHERE id_mascota = 1");
        $antes = ConocimientoPrueba::instantanea($this->db);
        $sin = $this->llamar();
        $this->assertTrue($sin['success']);
        $this->assertNotContains(['elemento' => 'ranking.parvovirosis_contexto', 'campos' => ['vacunacion']], $sin['datos_faltantes']);
        $_POST['vacunacion'] = 'incompleta';
        $con = $this->llamar();
        $this->assertSame('diagnostico.parvovirosis_canina', $con['candidatos'][0]['codigo']);
        $a = array_column($sin['candidatos'], null, 'codigo');
        $b = array_column($con['candidatos'], null, 'codigo');
        $this->assertEqualsWithDelta(0.0, $b['diagnostico.parvovirosis_canina']['puntuacion'] - $a['diagnostico.parvovirosis_canina']['puntuacion'], 0.00001);
        $this->assertSame($antes, ConocimientoPrueba::instantanea($this->db));
        $this->assertSame(0, (int) $this->db->query('SELECT COUNT(*) FROM alertas_medicas')->fetchColumn());
    }

    public function testClinicaSinVinculoOVinculoInactivoRecibe403Auditado(): void
    {
        foreach (['ausente', 'inactivo'] as $vinculo) {
            $this->como(DosClinicas::VET_SUR, Contexto::deClinica(2, 'Sur', Roles::VETERINARIO));
            if ($vinculo === 'inactivo') {
                $this->db->exec("INSERT INTO mascota_clinica (id_mascota, id_clinica, estado) VALUES (1, 2, 'inactivo')");
            }
            try {
                $this->llamar();
                $this->fail('Sin vínculo activo recibió sugerencias.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        $this->assertSame(2, (int) $this->db->query("SELECT COUNT(*) FROM auditoria_sistema WHERE accion = 'OTHER' AND tabla_afectada = 'mascotas' AND id_clinica = 2")->fetchColumn());
    }

    public static function vacunaciones(): array
    {
        return [
            'sin registros' => [[], [], 'desconocida'],
            'vencida en otra clínica' => [['2026-10-14', '2026-11-01'], [], 'incompleta'],
            'vigente en otra clínica' => [['2026-11-01'], [], 'completa'],
            'vence hoy' => [['2026-10-15'], [], 'completa'],
            'sin próxima dosis' => [[null], [], 'completa'],
            'declarada completa manda' => [['2026-10-14'], ['vacunacion' => 'completa'], 'completa'],
            'declarada incompleta manda' => [['2026-11-01'], ['vacunacion' => 'incompleta'], 'incompleta'],
            'declarada desconocida manda' => [['2026-10-14'], ['vacunacion' => 'desconocida'], 'desconocida'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('vacunaciones')]
    public function testVacunacionRegistradaGlobalSoloDeLaMascotaAutorizada(array $fechas, array $declarados, ?string $esperado): void
    {
        $this->db->exec("INSERT INTO vacunas VALUES (99, 999, 1, '2020-01-01')");
        $insertar = $this->db->prepare('INSERT INTO vacunas (id_mascota,id_clinica,fecha_proxima_dosis) VALUES (1,2,?)');
        foreach ($fechas as $fecha) {
            $insertar->execute([$fecha]);
        }
        $grafo = (new GrafoConocimiento($this->db))->instantanea();
        $contexto = (new PacienteDiagnostico($this->db))->contexto(1, $grafo, $this->fecha, $declarados);
        $this->assertSame($esperado, $contexto['vacunacion']);
        $this->como(DosClinicas::PROPIETARIO, Contexto::dePropietario());
        $portal = (new PacienteDiagnostico($this->db))->enClinicaDelPropietario(1);
        $this->assertSame($esperado, $portal->contexto(1, $grafo, $this->fecha, $declarados)['vacunacion']);
        $this->expectException(AccesoDenegado::class);
        $portal->contexto(999, $grafo, $this->fecha, []);
    }

    public function testLosOtrosRolesNoEntranYSinCsrfSeRechaza(): void
    {
        foreach ([
            [DosClinicas::ADMIN_NORTE, Contexto::deClinica(1, 'Norte', Roles::ADMIN)],
            [DosClinicas::PROPIETARIO, Contexto::dePropietario()],
            [DosClinicas::SUPER_ADMIN, Contexto::dePlataforma()],
        ] as [$id, $contexto]) {
            $this->como($id, $contexto);
            try {
                $this->llamar();
                $this->fail('Rol no permitido.');
            } catch (AccesoDenegado $error) {
                $this->assertSame(403, $error->codigo());
            }
        }
        $this->como(DosClinicas::VET_NORTE, Contexto::deClinica(1, 'Norte', Roles::VETERINARIO));
        unset($_POST['csrf_token']);
        $this->expectException(AccesoDenegado::class);
        $this->llamar();
    }

    public function testRechazaCodigosInexistentesRetiradosODeOtroTipoYDeclaradosInvalidos(): void
    {
        $this->db->exec("UPDATE grafo_nodos SET activo = 0 WHERE codigo = 'sintoma.fiebre'");
        foreach (['sintoma.no_existe', 'sintoma.fiebre', 'farmaco.paracetamol', ['dato' => 'a']] as $codigo) {
            $_POST['sintomas'] = [$codigo];
            $this->assertFalse($this->llamar()['success']);
            $this->assertSame(422, http_response_code());
        }
        $_POST['sintomas'] = [];
        foreach ([['vacunacion' => 'si'], ['vacunacion' => []], ['dias_desde_celo' => -1], ['dias_desde_celo' => []]] as $cambio) {
            unset($_POST['vacunacion'], $_POST['dias_desde_celo']);
            $_POST = array_replace($_POST, $cambio);
            $this->assertFalse($this->llamar()['success']);
            $this->assertSame(422, http_response_code());
        }
    }

    public function testSinCoincidenciasIndicaDiagnosticoLibreYGetDa405(): void
    {
        $_POST['sintomas'] = [];
        $respuesta = $this->llamar();
        $this->assertSame([], $respuesta['candidatos']);
        $this->assertTrue($respuesta['diagnostico_libre']);
        $this->assertStringContainsString('Escribe el diagnóstico', $respuesta['message']);
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertFalse($this->llamar()['success']);
        $this->assertSame(405, http_response_code());
    }

    public function testAlertasGlobalesDeOtraClinicaYEdadDesconocida(): void
    {
        DosClinicas::vincularLunaASur($this->db);
        $this->db->exec("INSERT INTO alertas_medicas (id_mascota, id_clinica, id_veterinario, tipo, id_nodo, descripcion, activa, fecha)
            SELECT 1, 2, 4, 'condicion_cronica', id_nodo, 'Epilepsia', 1, '2026-10-15' FROM grafo_nodos WHERE codigo = 'condicion.epilepsia'");
        $grafo = (new GrafoConocimiento($this->db))->instantanea();
        $modelo = new PacienteDiagnostico($this->db);
        foreach ([null, '2030-01-01', '2026-99-99'] as $nacimiento) {
            $this->db->prepare('UPDATE mascotas SET fecha_nacimiento = ? WHERE id_mascota = 1')->execute([$nacimiento]);
            $contexto = $modelo->contexto(1, $grafo, $this->fecha, []);
            $this->assertNull($contexto['edad_meses']);
            $this->assertContains('condicion.epilepsia', $contexto['alertas_activas']);
        }
        $this->assertTrue($this->llamar()['success']);
    }

    public function testTamanoPorPesoRespetaRazaYVisibilidadDeConsultas(): void
    {
        $grafo = (new GrafoConocimiento($this->db))->instantanea();
        $modelo = new PacienteDiagnostico($this->db);
        foreach ([[9.99, 'pequeno'], [10, 'mediano'], [25, 'mediano'], [25.01, 'grande'], [45, 'grande'], [45.01, 'gigante']] as [$peso, $esperado]) {
            $this->db->prepare('UPDATE mascotas SET peso=? WHERE id_mascota=1')->execute([$peso]);
            $this->assertSame($esperado, $modelo->contexto(1, $grafo, $this->fecha, [])['raza']['atributos']['tamanio']);
        }
        $this->db->exec("UPDATE mascotas SET id_raza=18, peso=8.5 WHERE id_mascota=1");
        $this->db->exec("INSERT INTO consultas VALUES (1,1,1,'2026-10-14 10:00:00',40),(2,2,1,'2026-10-15 10:00:00',60),(3,1,1,'2030-01-01 10:00:00',70)");
        $contexto = $modelo->contexto(1, $grafo, $this->fecha, []);
        $this->assertSame('raza.criollo_canino', $contexto['raza']['codigo']);
        $this->assertSame('grande', $contexto['raza']['atributos']['tamanio']);
        require_once __DIR__ . '/../../helpers/MotorTriage.php';
        $marcas = array_fill_keys(['sintoma.arcadas_improductivas', 'sintoma.abdomen_distendido'], ['tiempo' => 'no_se', 'minutos' => null]);
        $this->assertSame('rojo', MotorTriage::calcular($marcas, [], $contexto, $grafo, $this->fecha)['nivel']);
        $this->db->exec('UPDATE mascotas SET id_raza=1 WHERE id_mascota=1');
        $this->assertSame('grande', $modelo->contexto(1, $grafo, $this->fecha, [])['raza']['atributos']['tamanio']);
    }

    public function testInstantaneaSeLeeUnaVezEsInmutableYNoIncluyeRetirados(): void
    {
        $this->db->exec("UPDATE grafo_nodos SET activo = 0 WHERE codigo = 'sintoma.fiebre'");
        $lector = new GrafoConocimiento($this->db);
        $a = $lector->instantanea();
        $this->assertArrayNotHasKey('sintoma.fiebre', $a->nodos);
        $this->assertNotContains('sintoma.fiebre', array_column($a->aristas, 'origen'));
        $this->db->exec('DROP TABLE grafo_aristas');
        $this->assertSame($a, $lector->instantanea());
        $this->expectException(Error::class);
        $a->nodos['nuevo'] = [];
    }
}
